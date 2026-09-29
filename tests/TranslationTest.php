<?php

namespace Vibefilter\Filament\Tests;

use Illuminate\Support\Arr;
use Livewire\Livewire;
use Vibefilter\Filament\Contracts\DecisionDriver;
use Vibefilter\Filament\Drivers\FakeDriver;
use Vibefilter\Filament\Tests\Fixtures\ListReviews;
use Vibefilter\Filament\Tests\Fixtures\Review;

class TranslationTest extends TestCase
{
    public function test_every_language_has_every_key_of_the_english_file(): void
    {
        $english = array_keys(Arr::dot(require __DIR__ . '/../resources/lang/en/vibefilter.php'));

        foreach (glob(__DIR__ . '/../resources/lang/*/vibefilter.php') as $file) {
            $keys = array_keys(Arr::dot(require $file));

            $this->assertSame([], array_values(array_diff($english, $keys)), 'Missing in ' . $file);
            $this->assertSame([], array_values(array_diff($keys, $english)), 'Unknown keys in ' . $file);
        }
    }

    public function test_the_filter_speaks_the_apps_language(): void
    {
        app()->setLocale('hu');
        $this->app->instance(DecisionDriver::class, new FakeDriver(fn (string $statement, string $text) => str_contains($text, 'furious') ? 0.95 : 0.05));
        Review::create(['body' => 'I am furious.']);
        Review::create(['body' => 'All good.']);

        Livewire::test(ListReviews::class)
            ->filterTable('vibe', ['statement' => 'The customer is angry.'])
            ->assertNotified('2 sorból 1 felel meg');
    }

    public function test_the_limit_pop_up_and_the_progress_bar_are_translated(): void
    {
        app()->setLocale('hu');
        config(['vibefilter.max_unscored_rows' => 1]);
        $this->app->instance(DecisionDriver::class, new FakeDriver);
        Review::create(['body' => 'One.']);
        Review::create(['body' => 'Two.']);

        Livewire::test(ListReviews::class)
            ->filterTable('vibe', ['statement' => 'The customer is angry.'])
            ->assertNotified('2 sor vár pontozásra');

        $bar = view('vibefilter::progress', ['rows' => 2, 'done' => 0, 'total' => 1, 'retries' => 0])->render();

        $this->assertStringContainsString('2 sor pontozása', $bar);
        $this->assertStringContainsString('0 / 1 kérés kész', $bar);
    }
}
