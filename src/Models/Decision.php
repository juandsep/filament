<?php

namespace Vibefilter\Filament\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A cached score: how likely a question is true for one text.
 *
 * @property int $id
 * @property string $content_hash
 * @property int $question_id
 * @property string $driver
 * @property float $probability
 */
class Decision extends Model
{
    protected $table = 'vibefilter_decisions';

    protected $fillable = ['content_hash', 'question_id', 'driver', 'probability'];

    protected $casts = [
        'probability' => 'float',
    ];

    /**
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
