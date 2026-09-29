<?php

namespace Vibefilter\Filament\Tables\Filters;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Filters\Indicator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Js;
use Vibefilter\Filament\Exceptions\DriverException;
use Vibefilter\Filament\Scorer;
use Vibefilter\Filament\ScoringReport;

/**
 * A table filter that takes a plain-English statement and keeps the rows
 * it is true for. It only looks at the rows the table shows with every other
 * filter and the search applied, scores them (from the cache where possible),
 * and gives the table query a whereIn on the passing keys, so pagination,
 * counts and sorting keep working as usual.
 *
 * If more rows need a fresh score than the limit allows, nothing runs until
 * the user either narrows the table down or presses "Run anyway".
 */
class VibeFilter extends BaseFilter
{
    /** @var array<string>|Closure */
    protected array | Closure $textColumns = [];

    protected float | Closure | null $threshold = null;

    protected int | Closure | null $maxUnscoredRows = null;

    /** @var array<string, array<array-key>|null> Passing keys per statement, so one request scores only once. */
    protected array $resolved = [];

    /** True while this filter asks the table for its candidate rows, so it leaves itself out. */
    protected bool $collectingCandidates = false;

    public static function getDefaultName(): ?string
    {
        return 'vibe';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Vibe');

        $this->schema([
            TextInput::make('statement')
                ->label('Show rows where…')
                ->placeholder('The customer is angry')
                ->maxLength(500),
        ]);

        $this->query(fn (Builder $query, array $data) => $this->applyStatement(
            $query,
            $data['statement'] ?? null,
            $data['run_anyway'] ?? null,
        ));

        $this->indicateUsing(fn (array $data): array => filled($data['statement'] ?? null)
            ? [Indicator::make('Vibe: ' . $data['statement'])]
            : []);

        $this->excludeWhenResolvingRecord();
    }

    /**
     * The columns whose text is sent to the model, e.g. ['subject', 'body'].
     *
     * @param  array<string>|Closure  $columns
     */
    public function textColumns(array | Closure $columns): static
    {
        $this->textColumns = $columns;

        return $this;
    }

    /**
     * Rows scoring at or above this probability pass. Defaults to config('vibefilter.threshold').
     */
    public function threshold(float | Closure | null $threshold): static
    {
        $this->threshold = $threshold;

        return $this;
    }

    /**
     * Above this many rows without a cached score, the filter asks before it runs.
     * Defaults to config('vibefilter.max_unscored_rows').
     */
    public function maxUnscoredRows(int | Closure | null $max): static
    {
        $this->maxUnscoredRows = $max;

        return $this;
    }

    /**
     * @return array<string>
     */
    public function getTextColumns(): array
    {
        return $this->evaluate($this->textColumns);
    }

    public function getThreshold(): float
    {
        return (float) ($this->evaluate($this->threshold) ?? config('vibefilter.threshold', 0.8));
    }

    public function getMaxUnscoredRows(): int
    {
        return (int) ($this->evaluate($this->maxUnscoredRows) ?? config('vibefilter.max_unscored_rows', 1000));
    }

    /**
     * Scores the rows before the table renders, while the request is handling the
     * "Apply" or "Run anyway" click. Blade buffers output during rendering, so this
     * is the only point where the progress bar can be streamed to the browser.
     * Rendering then finds the result already resolved.
     */
    public function prescore(): void
    {
        $state = $this->getLivewire()->getTableFilterState($this->getName()) ?? [];

        $this->resolve($state['statement'] ?? null, $state['run_anyway'] ?? null);
    }

    protected function applyStatement(Builder $query, ?string $statement, ?string $runAnyway): void
    {
        if ($this->collectingCandidates) {
            return;
        }

        $keys = $this->resolve($statement, $runAnyway);

        if ($keys !== null) {
            $query->whereKey($keys);
        }
    }

    /**
     * @return array<array-key>|null The passing keys, or null to leave the table unfiltered.
     */
    protected function resolve(?string $statement, ?string $runAnyway): ?array
    {
        if (blank($statement)) {
            return null;
        }

        $statement = trim($statement);

        if (! array_key_exists($statement, $this->resolved)) {
            $this->resolved[$statement] = $this->passingKeys(
                $this->candidateQuery(),
                $statement,
                force: hash_equals($this->confirmationToken($statement), (string) $runAnyway),
            );
        }

        return $this->resolved[$statement];
    }

    /**
     * The rows the table shows with every other filter and the search applied.
     * Filament applies filters inside a nested where, which can't see the rest,
     * so this asks the table for its whole filtered query, minus this filter.
     */
    protected function candidateQuery(): Builder
    {
        $this->collectingCandidates = true;

        try {
            return $this->getLivewire()->getFilteredTableQuery();
        } finally {
            $this->collectingCandidates = false;
        }
    }

    /**
     * @return array<array-key>|null Null when the table should stay unfiltered (not run yet, or scoring failed).
     */
    protected function passingKeys(Builder $candidates, string $statement, bool $force): ?array
    {
        $model = $candidates->getModel();
        $columns = $this->getTextColumns();

        $texts = $candidates
            ->get([$model->getQualifiedKeyName(), ...array_map($model->qualifyColumn(...), $columns)])
            ->mapWithKeys(fn (Model $row) => [$row->getKey() => $this->rowText($row, $columns)])
            ->all();

        $scorer = app(Scorer::class);
        $max = $this->getMaxUnscoredRows();

        $unscored = $scorer->unscored($statement, $texts);

        if (! $force && $unscored > $max) {
            $this->askBeforeRunning($statement, $unscored, count($texts), $max);

            return null;
        }

        try {
            $scores = $scorer->score(
                $statement,
                $texts,
                fn (int $done, int $total, int $retries) => $this->streamProgress($unscored, $done, $total, $retries),
            );
        } catch (DriverException $exception) {
            // Batches that came back before the failure are cached: filter on those.
            $scores = $scorer->cachedScores($statement, $texts);
            $this->reportFailure($exception, count($scores), count($texts));

            if ($scores === []) {
                return null;
            }
        }

        $threshold = $this->getThreshold();
        $passing = array_keys(array_filter($scores, fn (float $probability) => $probability >= $threshold));

        if (isset($exception)) {
            return $passing;
        }

        if ($scorer->lastReport?->scored) {
            $this->reportRun($scorer->lastReport, count($passing));
        }

        return $passing;
    }

    /**
     * After a run that asked the model anything: how many rows match, and what it took.
     * Runs answered entirely from the cache stay quiet.
     */
    protected function reportRun(ScoringReport $report, int $matching): void
    {
        $details = [number_format($report->scored) . ' new ' . str('row')->plural($report->scored) . ' scored'];

        if ($report->requests) {
            $details[0] .= ' in ' . $report->requests . ' ' . str('request')->plural($report->requests);
        }

        if ($report->retries()) {
            $details[] = $report->retries() . ' retried after the API didn\'t answer';
        }

        $details[] = number_format($report->seconds, 1) . ' s';

        if ($report->cached) {
            $details[] = number_format($report->cached) . ' answered from the cache';
        }

        Notification::make()
            ->success()
            ->title(number_format($matching) . ' of ' . number_format($report->rows) . ' rows match')
            ->body(implode(' · ', $details))
            ->send();
    }

    /**
     * The API failed for some or all batches. What did come back is cached, so
     * trying again only sends the rows that are still missing.
     */
    protected function reportFailure(DriverException $exception, int $scored, int $total): void
    {
        $retry = Action::make('tryAgain')
            ->label('Try again')
            ->button()
            // Only a Livewire id goes into the script.
            ->alpineClickHandler("close(); Livewire.find('{$this->getLivewire()->getId()}').\$refresh();");

        if ($scored === 0) {
            Notification::make()
                ->danger()
                ->persistent()
                ->title('The vibe filter could not run')
                ->body($exception->getMessage() . ' The table isn\'t filtered.')
                ->actions([$retry])
                ->send();

            return;
        }

        $missing = $total - $scored;

        Notification::make()
            ->warning()
            ->persistent()
            ->title(number_format($scored) . ' of ' . number_format($total) . ' rows scored')
            ->body(implode(' ', [
                'The API didn\'t answer for ' . number_format($missing) . ' ' . str('row')->plural($missing) . ', so the table only shows matches among the scored ones.',
                'Trying again sends just the missing ' . number_format($missing) . '.',
                '(' . $exception->getMessage() . ')',
            ]))
            ->actions([$retry])
            ->send();
    }

    /**
     * Pushes the progress bar into the table while the request is still running
     * (Livewire streaming). The bar disappears when the table re-renders.
     */
    protected function streamProgress(int $rows, int $done, int $total, int $retries): void
    {
        $this->getLivewire()->stream(
            content: $this->progressHtml($rows, $done, $total, $retries),
            replace: true,
            el: '[data-vibefilter-progress]',
        );
    }

    protected function progressHtml(int $rows, int $done, int $total, int $retries): string
    {
        return view('vibefilter::progress', compact('rows', 'done', 'total', 'retries'))->render();
    }

    /**
     * A pop-up with the numbers, how to get under the limit, and a button to run anyway.
     */
    protected function askBeforeRunning(string $statement, int $unscored, int $total, int $max): void
    {
        $livewireId = $this->getLivewire()->getId();
        $statePath = 'tableFilters.' . $this->getName() . '.run_anyway';
        $token = $this->confirmationToken($statement);
        $requests = (int) ceil($unscored / max(1, (int) config('vibefilter.batch_size', 100)));
        $startingBar = Js::from($this->progressHtml($unscored, 0, $requests, 0));

        Notification::make('vibefilter-limit-' . $token)
            ->warning()
            ->persistent()
            ->title(number_format($unscored) . ' rows need a fresh score')
            ->body(implode(' ', [
                'The table has ' . number_format($total) . ' rows with the other filters and the search applied,',
                'and ' . number_format($unscored) . ' of them have no cached score for this statement yet.',
                'The limit is ' . number_format($max) . '.',
                'Narrow the table down with other filters or a search to get under it, or run it on all of them now.',
                'The table isn\'t filtered until then.',
            ]))
            ->actions([
                Action::make('runAnyway')
                    ->label('Run anyway')
                    ->button()
                    // Only ids, a filter name, a hex token and our own wording go into
                    // the script, never user input. The progress note closes when the
                    // run is done.
                    // Only ids, a filter name, a hex token and our own markup go into
                    // the script, never user input. The bar shows at once; the filter
                    // then streams the real progress into it.
                    ->alpineClickHandler(implode(' ', [
                        'close();',
                        "document.querySelectorAll('[data-vibefilter-progress]').forEach((el) => el.innerHTML = {$startingBar});",
                        "Livewire.find('{$livewireId}').set('{$statePath}', '{$token}');",
                    ])),
            ])
            ->send();
    }

    /**
     * Ties a "Run anyway" click to one statement, so a new statement asks again.
     */
    protected function confirmationToken(string $statement): string
    {
        return hash('sha256', trim($statement));
    }

    /**
     * @param  array<string>  $columns
     */
    protected function rowText(Model $row, array $columns): string
    {
        if (count($columns) === 1) {
            return (string) $row->getAttribute($columns[0]);
        }

        return collect($columns)
            ->map(fn (string $column) => $column . ': ' . $row->getAttribute($column))
            ->implode("\n");
    }
}
