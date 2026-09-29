<?php

namespace Vibefilter\Filament\Tests;

use Vibefilter\Filament\Drivers\FakeDriver;

class FakeDriverTest extends TestCase
{
    public function test_scores_with_the_callback_and_records_calls(): void
    {
        $driver = new FakeDriver(fn (string $statement, string $text) => str_contains($text, 'angry') ? 1 : 0);

        $this->assertSame([5 => 1.0, 9 => 0.0], $driver->decide('The customer is angry.', [5 => 'angry', 9 => 'calm']));
        $this->assertSame('The customer is angry.', $driver->calls[0]['statement']);
    }
}
