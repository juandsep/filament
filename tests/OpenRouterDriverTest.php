<?php

namespace Vibefilter\Filament\Tests;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Vibefilter\Filament\Contracts\DecisionDriver;
use Vibefilter\Filament\Drivers\OpenRouterDriver;
use Vibefilter\Filament\Exceptions\DriverException;

class OpenRouterDriverTest extends TestCase
{
    protected function fakeOpenRouter(): void
    {
        Http::fake(function (Request $request) {
            $texts = collect($request['state']['records'])->pluck('text', 'id');

            return Http::response(['answers' => collect($request['questions'])->map(fn ($question, $tag) => [
                'type' => 'noul',
                'noul' => str_contains($texts["#{$tag}"], 'angry') ? 0.9 : 0.1,
            ])]);
        });
    }

    public function test_it_sends_jev_requests_to_openrouter(): void
    {
        $this->fakeOpenRouter();

        $scores = (new OpenRouterDriver('sk-or-test'))->decide('The customer is angry.', [7 => 'So angry.', 8 => 'Lovely.']);

        $this->assertSame([7 => 0.9, 8 => 0.1], $scores);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://openrouter.ai/api/v1/systemone'
            && $request->hasHeader('Authorization', 'Bearer sk-or-test')
            && $request['model'] === 'typesafe/jev-1.13');
    }

    public function test_its_decisions_are_cached_apart_from_typesafe(): void
    {
        $this->assertSame('openrouter', (new OpenRouterDriver('key'))->name());
    }

    public function test_a_missing_key_names_the_openrouter_variable(): void
    {
        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('OPENROUTER_API_KEY');

        (new OpenRouterDriver(null))->decide('The customer is angry.', ['text']);
    }

    public function test_it_is_picked_by_the_driver_setting(): void
    {
        config([
            'vibefilter.driver' => 'openrouter',
            'vibefilter.drivers.openrouter.api_key' => 'sk-or-test',
            'vibefilter.drivers.openrouter.model' => 'typesafe/jev-1.14',
        ]);
        $this->fakeOpenRouter();

        app(DecisionDriver::class)->decide('The customer is angry.', ['angry']);

        Http::assertSent(fn (Request $request) => $request['model'] === 'typesafe/jev-1.14');
    }

    public function test_errors_name_openrouter(): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'No credits']], 402)]);

        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('OpenRouter request failed with HTTP 402');

        (new OpenRouterDriver('key', retryDelays: []))->decide('The customer is angry.', ['text']);
    }
}
