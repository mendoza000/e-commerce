<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['municipality_id', 'name'])]
class Parish extends Model
{
    use HasFactory;

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }
}
