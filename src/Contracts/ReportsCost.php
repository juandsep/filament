<?php

namespace Vibefilter\Filament\Contracts;

/**
 * A driver whose service reports what each request cost.
 */
interface ReportsCost
{
    /**
     * What the last decide() call cost in US dollars, as the service reported it.
     */
    public function lastCost(): float;
}
