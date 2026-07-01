<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['state_id', 'name'])]
class Municipality extends Model
{
    use HasFactory;

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function parishes(): HasMany
    {
        return $this->hasMany(Parish::class);
    }
}
