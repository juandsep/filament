<?php

namespace Vibefilter\Filament\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A statement the filter has been asked about.
 *
 * @property int $id
 * @property string $text
 * @property string $hash
 */
class Question extends Model
{
    protected $table = 'vibefilter_questions';

    protected $fillable = ['text', 'hash'];

    /**
     * @return HasMany<Decision, $this>
     */
    public function decisions(): HasMany
    {
        return $this->hasMany(Decision::class);
    }
}
