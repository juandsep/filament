<?php

namespace Vibefilter\Filament\Contracts;

/**
 * A driver that can tell how many API requests its last decide() call took.
 */
interface CountsRequests
{
    /**
     * Requests needed for the last call, one per batch.
     */
    public function lastRequestCount(): int;

    /**
     * Requests actually sent for the last call, retries included.
     */
    public function lastAttemptCount(): int;
}
