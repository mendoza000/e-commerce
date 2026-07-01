<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'from_currency_id',
    'to_currency_id',
    'rate',
    'source',
    'reference_amount',
    'effective_at',
    'created_by',
])]
class ExchangeRate extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'effective_at' => 'datetime',
            'rate' => 'decimal:6',
            'reference_amount' => 'decimal:6',
        ];
    }

    public function fromCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'from_currency_id');
    }

    public function toCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'to_currency_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
