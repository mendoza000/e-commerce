<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'code'])]
class State extends Model
{
    use HasFactory;

    public function municipalities(): HasMany
    {
        return $this->hasMany(Municipality::class);
    }
}
