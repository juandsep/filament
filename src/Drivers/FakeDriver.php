<?php

namespace Vibefilter\Filament\Drivers;

use Closure;
use Vibefilter\Filament\Contracts\DecisionDriver;

/**
 * A driver for tests: scores rows with a callback instead of an API call,
 * and remembers every call it received.
 */
class FakeDriver implements DecisionDriver
{
    /** @var list<array{statement: string, texts: array<array-key, string>}> */
    public array $calls = [];

    /**
     * @param  (Closure(string $statement, string $text): float)|null  $scorer
     */
    public function __construct(protected ?Closure $scorer = null) {}

    public function name(): string
    {
        return 'fake';
    }

    public function decide(string $statement, array $texts, ?Closure $onScored = null, ?Closure $onProgress = null): array
    {
        $this->calls[] = ['statement' => $statement, 'texts' => $texts];

        return array_map(
            fn (string $text) => $this->scorer ? (float) ($this->scorer)($statement, $text) : 0.0,
            $texts,
        );
    }
}
