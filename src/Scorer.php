<?php

namespace Vibefilter\Filament;

use Closure;
use Illuminate\Support\Facades\Date;
use Vibefilter\Filament\Contracts\CountsRequests;
use Vibefilter\Filament\Contracts\DecisionDriver;
use Vibefilter\Filament\Contracts\ReportsCost;
use Vibefilter\Filament\Models\Decision;
use Vibefilter\Filament\Models\Question;
use Vibefilter\Filament\Support\TextNormalizer;

/**
 * Scores texts against a statement, asking the driver only about texts
 * it has not seen before. Decisions are cached by content, not by record:
 * two rows with the same text share one score, and an edited row simply
 * gets a new hash.
 */
class Scorer
{
    /** What the last score() call did. */
    public ?ScoringReport $lastReport = null;

    public function __construct(protected DecisionDriver $driver) {}

    /**
     * @param  array<array-key, string|null>  $texts
     * @param  (Closure(int $done, int $total, int $retries): void)|null  $onProgress
     * @return array<array-key, float> Probabilities under the same keys as $texts.
     */
    public function score(string $statement, array $texts, ?Closure $onProgress = null): array
    {
        $started = microtime(true);
        $this->lastReport = null;
        $question = $this->question($statement);

        $normalized = array_map(fn (?string $text) => TextNormalizer::text((string) $text), $texts);
        $hashes = array_map(TextNormalizer::hash(...), $normalized);

        $known = $this->cached($question, array_unique($hashes));

        // Ask about each unseen text once, however many rows share it.
        $missing = array_diff_key(array_combine($hashes, $normalized), $known);

        if ($missing !== []) {
            // Save each batch as it comes back, so a failure later on
            // doesn't lose the answers that were already paid for.
            $saved = [];
            $fresh = $this->driver->decide($question->text, $missing, function (array $scores) use ($question, &$saved) {
                $this->store($question, $scores);
                $saved += $scores;
            }, $onProgress);
            $this->store($question, array_diff_key($fresh, $saved));
            $known += $fresh;
        }

        $counts = $this->driver instanceof CountsRequests && $missing !== [];
        $sent = count(array_filter($hashes, fn (string $hash) => isset($missing[$hash])));
        $this->lastReport = new ScoringReport(
            rows: count($texts),
            cached: count($texts) - $sent,
            scored: $sent,
            requests: $counts ? $this->driver->lastRequestCount() : 0,
            attempts: $counts ? $this->driver->lastAttemptCount() : 0,
            seconds: microtime(true) - $started,
            cost: $this->driver instanceof ReportsCost && $missing !== [] ? $this->driver->lastCost() : null,
        );

        return array_map(fn (string $hash) => $known[$hash], $hashes);
    }

    /**
     * The scores already in the cache, under the keys of the texts that have one.
     * Asks the driver nothing, so it works when the driver is down.
     *
     * @param  array<array-key, string|null>  $texts
     * @return array<array-key, float>
     */
    public function cachedScores(string $statement, array $texts): array
    {
        $question = Question::firstWhere('hash', TextNormalizer::hash(TextNormalizer::question($statement)));

        if (! $question) {
            return [];
        }

        $hashes = array_map(fn (?string $text) => TextNormalizer::hash(TextNormalizer::text((string) $text)), $texts);
        $known = $this->cached($question, array_unique($hashes));

        return array_map(fn (string $hash) => $known[$hash], array_filter($hashes, fn (string $hash) => isset($known[$hash])));
    }

    /**
     * How many rows have no cached decision yet. Rows sharing a text count
     * one by one, although their text is only sent once.
     *
     * @param  array<array-key, string|null>  $texts
     */
    public function unscored(string $statement, array $texts): int
    {
        $hashes = array_map(
            fn (?string $text) => TextNormalizer::hash(TextNormalizer::text((string) $text)),
            $texts,
        );

        $question = Question::firstWhere('hash', TextNormalizer::hash(TextNormalizer::question($statement)));

        if (! $question) {
            return count($hashes);
        }

        $known = $this->cached($question, array_unique($hashes));

        return count(array_filter($hashes, fn (string $hash) => ! isset($known[$hash])));
    }

    protected function question(string $statement): Question
    {
        $normalized = TextNormalizer::question($statement);

        return Question::firstOrCreate(
            ['hash' => TextNormalizer::hash($normalized)],
            ['text' => TextNormalizer::text($statement)],
        );
    }

    /**
     * @param  array<int, string>  $hashes
     * @return array<string, float>
     */
    protected function cached(Question $question, array $hashes): array
    {
        $scores = [];

        foreach (array_chunk($hashes, 500) as $chunk) {
            $scores += Decision::query()
                ->where('question_id', $question->getKey())
                ->where('driver', $this->driver->name())
                ->whereIn('content_hash', $chunk)
                ->pluck('probability', 'content_hash')
                ->map(fn ($probability) => (float) $probability)
                ->all();
        }

        return $scores;
    }

    /**
     * @param  array<string, float>  $scores
     */
    protected function store(Question $question, array $scores): void
    {
        $now = Date::now();

        $rows = array_map(fn (string $hash, float $probability) => [
            'content_hash' => $hash,
            'question_id' => $question->getKey(),
            'driver' => $this->driver->name(),
            'probability' => $probability,
            'created_at' => $now,
            'updated_at' => $now,
        ], array_keys($scores), $scores);

        foreach (array_chunk($rows, 500) as $chunk) {
            Decision::upsert($chunk, ['content_hash', 'question_id', 'driver'], ['probability', 'updated_at']);
        }
    }
}
