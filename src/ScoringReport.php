<?php

namespace Vibefilter\Filament;

/**
 * What one Scorer::score() call did, for showing it to the user.
 */
final class ScoringReport
{
    public function __construct(
        /** Rows asked about. */
        public readonly int $rows,
        /** Rows answered from the cache. */
        public readonly int $cached,
        /** Rows sent for scoring. Rows sharing a text count one by one, though the text goes out once. */
        public readonly int $scored,
        /** Requests needed, one per batch (0 if the driver can't tell). */
        public readonly int $requests,
        /** Requests sent, retries included. */
        public readonly int $attempts,
        public readonly float $seconds,
        /** US dollars the service reported, or null if it doesn't report prices. */
        public readonly ?float $cost = null,
    ) {}

    public function retries(): int
    {
        return max(0, $this->attempts - $this->requests);
    }
}
