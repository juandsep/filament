<?php

namespace Vibefilter\Filament;

/**
 * What one Scorer::score() call did, for showing it to the user.
 */
final class ScoringReport
{
    public function __construct(
        /** Texts asked about, duplicates included. */
        public readonly int $rows,
        /** Distinct texts answered from the cache. */
        public readonly int $cached,
        /** Distinct texts sent to the driver. */
        public readonly int $scored,
        /** Requests needed, one per batch (0 if the driver can't tell). */
        public readonly int $requests,
        /** Requests sent, retries included. */
        public readonly int $attempts,
        public readonly float $seconds,
    ) {}

    public function retries(): int
    {
        return max(0, $this->attempts - $this->requests);
    }
}
