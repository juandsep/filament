<?php

namespace Vibefilter\Filament\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Decision extends Model
{
    protected $table = 'vibefilter_decisions';

    protected $fillable = ['content_hash', 'question_id', 'driver', 'probability'];

    protected $casts = [
        'probability' => 'float',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
