<?php

namespace Vibefilter\Filament\Contracts;

use Closure;

interface DecisionDriver
{
    /**
     * The name the cache stores decisions under.
     */
    public function name(): string;

    /**
     * Score how likely the statement is true for each text.
     *
     * A driver that works in batches may call $onScored with each batch's scores
     * as soon as it has them, so they can be saved even if a later batch fails,
     * and $onProgress whenever a request comes back, so the user can see it move.
     *
     * @param  array<array-key, string>  $texts
     * @param  (Closure(array<array-key, float> $scores): void)|null  $onScored
     * @param  (Closure(int $done, int $total, int $retries): void)|null  $onProgress
     * @return array<array-key, float> Probabilities between 0 and 1, under the same keys as $texts.
     */
    public function decide(string $statement, array $texts, ?Closure $onScored = null, ?Closure $onProgress = null): array;
}
