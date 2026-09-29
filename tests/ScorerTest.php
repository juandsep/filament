<?php

namespace Vibefilter\Filament\Tests;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Vibefilter\Filament\Contracts\DecisionDriver;
use Vibefilter\Filament\Drivers\FakeDriver;
use Vibefilter\Filament\Drivers\TypeSafeDriver;
use Vibefilter\Filament\Exceptions\DriverException;
use Vibefilter\Filament\Models\Decision;
use Vibefilter\Filament\Models\Question;
use Vibefilter\Filament\Scorer;

class ScorerTest extends TestCase
{
    protected FakeDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driver = new FakeDriver(fn (string $statement, string $text) => str_contains($text, 'angry') ? 0.9 : 0.1);
        $this->app->instance(DecisionDriver::class, $this->driver);
    }

    protected function scorer(): Scorer
    {
        return $this->app->make(Scorer::class);
    }

    public function test_scores_texts_under_their_keys(): void
    {
        $scores = $this->scorer()->score('The customer is angry.', [7 => 'So angry.', 8 => 'Lovely.']);

        $this->assertSame([7 => 0.9, 8 => 0.1], $scores);
    }

    public function test_second_search_is_answered_from_the_cache(): void
    {
        $this->scorer()->score('The customer is angry.', [1 => 'So angry.', 2 => 'Lovely.']);
        $scores = $this->scorer()->score('the customer is ANGRY.', [1 => 'So angry.', 2 => 'Lovely.']);

        $this->assertSame([1 => 0.9, 2 => 0.1], $scores);
        $this->assertCount(1, $this->driver->calls);
        $this->assertSame(1, Question::count());
        $this->assertSame(2, Decision::count());
    }

    public function test_equal_texts_are_asked_about_once(): void
    {
        $scores = $this->scorer()->score('The customer is angry.', [1 => 'So angry.', 2 => "  So   angry. ", 3 => 'So angry.']);

        $this->assertSame([1 => 0.9, 2 => 0.9, 3 => 0.9], $scores);
        $this->assertCount(1, $this->driver->calls[0]['texts']);
        $this->assertSame(1, Decision::count());
    }

    public function test_only_new_or_edited_texts_reach_the_driver(): void
    {
        $this->scorer()->score('The customer is angry.', [1 => 'So angry.', 2 => 'Lovely.']);
        $this->scorer()->score('The customer is angry.', [1 => 'So angry.', 2 => 'Lovely, but now angry.']);

        $this->assertSame(['Lovely, but now angry.'], array_values($this->driver->calls[1]['texts']));
    }

    public function test_the_driver_gets_the_normalized_text(): void
    {
        $this->scorer()->score('The customer is angry.', ["  So \r\n angry.  "]);

        $this->assertSame(["So\nangry."], array_values($this->driver->calls[0]['texts']));
    }

    public function test_decisions_are_kept_per_driver(): void
    {
        $this->scorer()->score('The customer is angry.', ['So angry.']);

        $other = new class(fn () => 0.5) extends FakeDriver
        {
            public function name(): string
            {
                return 'other';
            }
        };
        $this->app->instance(DecisionDriver::class, $other);

        $this->assertSame([0.5], $this->scorer()->score('The customer is angry.', ['So angry.']));
        $this->assertSame(2, Decision::count());
    }

    public function test_empty_input_makes_no_driver_call(): void
    {
        $this->assertSame([], $this->scorer()->score('The customer is angry.', []));
        $this->assertCount(0, $this->driver->calls);
    }

    public function test_batches_that_came_back_are_cached_even_if_another_failed(): void
    {
        Http::fake(function (Request $request) {
            $record = $request['state']['records'][0];

            return str_contains($record['text'], 'broken')
                ? Http::response('down', 503)
                : Http::response(['answers' => [ltrim($record['id'], '#') => ['type' => 'noul', 'noul' => 0.9]]]);
        });
        $this->app->instance(DecisionDriver::class, new TypeSafeDriver('key', batchSize: 1, retryDelays: []));

        try {
            $this->scorer()->score('The customer is angry.', ['So angry.', 'broken']);
            $this->fail('Expected a DriverException.');
        } catch (DriverException) {
            $this->assertSame(1, Decision::count());
        }

        // Next time only the failed text goes out.
        Http::fake(fn (Request $request) => Http::response(['answers' => [ltrim($request['state']['records'][0]['id'], '#') => ['type' => 'noul', 'noul' => 0.1]]]));
        $this->scorer()->score('The customer is angry.', ['So angry.', 'fixed now']);
        Http::assertSentCount(1);
    }
}
