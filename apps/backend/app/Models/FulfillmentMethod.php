<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'type',
    'label',
    'requires_tracking_code',
    'base_cost',
    'currency_id',
    'is_active',
    'position',
])]
class FulfillmentMethod extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'base_cost' => 'decimal:6',
        ];
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
