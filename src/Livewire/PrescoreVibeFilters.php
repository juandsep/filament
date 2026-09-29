<?php

namespace Vibefilter\Filament\Livewire;

use Closure;
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
    public function update(string $propertyName, string $fullPath, mixed $newValue): ?Closure
    {
        if ($propertyName === 'tableFilters') {
            return fn () => $this->prescore();
        }

        return null;
    }

    /**
     * The filters form's Apply button.
     *
     * @param  array<mixed>  $params
     */
    public function call(string $method, array $params, mixed $returnEarly): ?Closure
    {
        if ($method === 'applyTableFilters') {
            return fn () => $this->prescore();
        }

        return null;
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
