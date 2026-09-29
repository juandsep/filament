<?php

namespace Vibefilter\Filament\Livewire;

use Filament\Tables\Contracts\HasTable;
use Livewire\ComponentHook;
use Vibefilter\Filament\Tables\Filters\VibeFilter;

/**
 * Runs the vibe filters of a table right after the user applies filters or
 * presses "Run anyway", before the table renders. That way the filter can
 * stream its progress bar to the browser while it waits for the model:
 * during rendering, Blade buffers all output.
 */
class PrescoreVibeFilters extends ComponentHook
{
    /**
     * "Run anyway", and filters on tables that apply them without an Apply button.
     */
    public function update($propertyName, $fullPath, $newValue)
    {
        if ($propertyName === 'tableFilters') {
            return fn () => $this->prescore();
        }
    }

    /**
     * The filters form's Apply button.
     */
    public function call($method, $params, $returnEarly)
    {
        if ($method === 'applyTableFilters') {
            return fn () => $this->prescore();
        }
    }

    protected function prescore(): void
    {
        if (! $this->component instanceof HasTable) {
            return;
        }

        foreach ($this->component->getTable()->getFilters() as $filter) {
            if ($filter instanceof VibeFilter) {
                $filter->prescore();
            }
        }
    }
}
