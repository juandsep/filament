<?php

namespace Vibefilter\Filament\Tests;

use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;
use Vibefilter\Filament\Contracts\DecisionDriver;
use Vibefilter\Filament\Contracts\ReportsCost;
use Vibefilter\Filament\Drivers\TypeSafeDriver;
use Vibefilter\Filament\Exceptions\DriverException;

class TypeSafeDriverTest extends TestCase
{
    protected function driver(int $batchSize = 100): TypeSafeDriver
    {
        return new TypeSafeDriver(apiKey: 'test-key', batchSize: $batchSize, retryDelays: [0, 0, 0]);
    }

    /**
     * Jev's answer to a request: 0.9 for every row that mentions "angry", else 0.1.
     */
    protected function answer(Request $request)
    {
        $texts = collect($request['state']['records'])->pluck('text', 'id');

        $answers = collect($request['questions'])->map(fn ($question, $tag) => [
            'type' => 'noul',
            'noul' => str_contains($texts["#{$tag}"], 'angry') ? 0.9 : 0.1,
        ]);

        return Http::response(['model' => 'jev-1.13.0', 'answers' => $answers, 'usage' => []]);
    }

    protected function fakeJev(): void
    {
        Http::fake(fn (Request $request) => $this->answer($request));
    }

    public function test_it_reports_no_cost(): void
    {
        Http::fake(fn (Request $request) => Http::response([
            'answers' => collect($request['questions'])->map(fn () => ['type' => 'noul', 'noul' => 0.5]),
            'usage' => ['input_tokens' => 317, 'output_tokens' => 25, 'cost' => 0.00125],
        ]));
        $costs = [];

        $driver = new TypeSafeDriver('key');
        $driver->decide('The customer is angry.', ['a'], onProgress: function (int $done, int $total, int $retries, ?float $cost) use (&$costs) {
            $costs[] = $cost;
        });

        $this->assertNotInstanceOf(ReportsCost::class, $driver);
        $this->assertSame([null], $costs);
    }

    public function test_scores_map_back_to_the_callers_keys_in_order(): void
    {
        $this->fakeJev();

        $scores = $this->driver()->decide('The customer is angry.', [
            17 => 'I am angry about the delivery.',
            'b' => 'Lovely product.',
            3 => 'So angry right now.',
        ]);

        $this->assertSame([17 => 0.9, 'b' => 0.1, 3 => 0.9], $scores);
    }

    public function test_rows_are_sent_in_batches(): void
    {
        $this->fakeJev();

        $scores = $this->driver(batchSize: 2)->decide('The customer is angry.', array_fill(0, 5, 'angry'));

        $this->assertCount(5, $scores);
        Http::assertSentCount(3);
    }

    public function test_request_uses_random_tags_and_noul_questions(): void
    {
        $this->fakeJev();

        $this->driver()->decide('The customer is angry.', [42 => 'angry text']);

        Http::assertSent(function (Request $request) {
            $record = $request['state']['records'][0];
            $tag = ltrim($record['id'], '#');

            return $request->url() === 'https://api.typesafe.ai/v1/systemone'
                && $request->hasHeader('Authorization', 'Bearer test-key')
                && $request['model'] === 'jev-1.13.0'
                && preg_match('/^k[0-9a-f]{6}$/', $tag)
                && ! str_contains(json_encode($request->data()), '42')
                && $request['questions'][$tag] === [
                    'type' => 'noul',
                    'instructions' => "Regarding record #{$tag}: The customer is angry.",
                ];
        });
    }

    public function test_empty_input_makes_no_request(): void
    {
        Http::fake();

        $this->assertSame([], $this->driver()->decide('The customer is angry.', []));
        Http::assertNothingSent();
    }

    public function test_missing_api_key_throws(): void
    {
        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('TYPESAFE_API_KEY');

        (new TypeSafeDriver(apiKey: null))->decide('The customer is angry.', ['text']);
    }

    public function test_http_error_throws(): void
    {
        Http::fake(['*' => Http::response(['error' => 'invalid model'], 422)]);

        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('HTTP 422');

        $this->driver()->decide('The customer is angry.', ['text']);
    }

    public function test_missing_answer_throws(): void
    {
        Http::fake(['*' => Http::response(['answers' => []])]);

        $this->expectException(DriverException::class);

        $this->driver()->decide('The customer is angry.', ['text']);
    }

    public function test_container_resolves_the_configured_driver(): void
    {
        config(['vibefilter.driver' => 'fake']);
        $this->assertSame('fake', app(DecisionDriver::class)->name());

        config(['vibefilter.driver' => 'typesafe']);
        $this->assertSame('typesafe:jev-1.13.0', app(DecisionDriver::class)->name());
    }

    public function test_a_rate_limited_batch_is_retried(): void
    {
        $calls = 0;
        Http::fake(function (Request $request) use (&$calls) {
            return ++$calls === 1 ? Http::response(['error' => 'slow down'], 429) : $this->answer($request);
        });

        $this->assertSame([0.9], $this->driver()->decide('The customer is angry.', ['angry']));
        Http::assertSentCount(2);
    }

    public function test_a_server_error_is_retried_until_the_retries_run_out(): void
    {
        Http::fake(['*' => Http::response('down', 503)]);

        try {
            $this->driver()->decide('The customer is angry.', ['text']);
            $this->fail('Expected a DriverException.');
        } catch (DriverException $exception) {
            $this->assertStringContainsString('HTTP 503', $exception->getMessage());
        }

        Http::assertSentCount(4);
    }

    public function test_a_bad_request_is_not_retried(): void
    {
        Http::fake(['*' => Http::response(['error' => 'invalid key'], 401)]);

        $this->expectException(DriverException::class);

        try {
            $this->driver()->decide('The customer is angry.', ['text']);
        } finally {
            Http::assertSentCount(1);
        }
    }

    public function test_retry_after_is_respected_up_to_thirty_seconds(): void
    {
        $driver = new class('key') extends TypeSafeDriver
        {
            public function delayFor(int $attempt, Throwable $exception): int
            {
                return $this->retryDelay($attempt, $exception);
            }
        };

        $response = fn (array $headers) => new RequestException(
            new Response(new PsrResponse(429, $headers)),
        );

        $this->assertSame(2000, $driver->delayFor(1, $response(['Retry-After' => '2'])));
        $this->assertSame(30000, $driver->delayFor(1, $response(['Retry-After' => '120'])));
        $this->assertSame(500, $driver->delayFor(1, $response([])));
        $this->assertSame(5000, $driver->delayFor(3, $response([])));
    }

    public function test_successful_batches_are_reported_even_if_another_fails(): void
    {
        Http::fake(function (Request $request) {
            return str_contains($request['state']['records'][0]['text'], 'broken')
                ? Http::response('down', 503)
                : $this->answer($request);
        });

        $reported = [];

        try {
            $this->driver(batchSize: 1)->decide('The customer is angry.', ['a' => 'angry', 'b' => 'broken'], function (array $scores) use (&$reported) {
                $reported += $scores;
            });
            $this->fail('Expected a DriverException.');
        } catch (DriverException) {
            $this->assertSame(['a' => 0.9], $reported);
        }
    }

    public function test_a_batch_that_keeps_failing_is_split_in_half_once(): void
    {
        // Too slow for more than two rows at a time.
        Http::fake(fn (Request $request) => count($request['state']['records']) > 2
            ? Http::response('upstream connect error', 503)
            : $this->answer($request));

        $driver = $this->driver(batchSize: 4);
        $scores = $driver->decide('The customer is angry.', ['angry', 'calm', 'angry too', 'calm too']);

        $this->assertSame([0.9, 0.1, 0.9, 0.1], $scores);
        Http::assertSentCount(4 + 2); // the batch with its 3 retries, then the two halves
        $this->assertSame(3, $driver->lastRequestCount());
    }

    public function test_halves_that_fail_are_not_split_again(): void
    {
        Http::fake(fn (Request $request) => count($request['state']['records']) > 1
            ? Http::response('upstream connect error', 503)
            : $this->answer($request));

        $this->expectException(DriverException::class);

        try {
            $this->driver(batchSize: 4)->decide('The customer is angry.', ['a', 'b', 'c', 'd']);
        } finally {
            Http::assertSentCount(4 + 2 * 4); // no quarters
        }
    }

    public function test_a_rejected_batch_is_not_split(): void
    {
        Http::fake(['*' => Http::response(['error' => 'invalid key'], 401)]);

        $this->expectException(DriverException::class);

        try {
            $this->driver(batchSize: 2)->decide('The customer is angry.', ['a', 'b']);
        } finally {
            Http::assertSentCount(1);
        }
    }
}
