<?php

namespace Vibefilter\Filament\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    protected $table = 'vibefilter_questions';

    protected $fillable = ['text', 'hash'];

    public function decisions(): HasMany
    {
        return $this->hasMany(Decision::class);
    }
}
