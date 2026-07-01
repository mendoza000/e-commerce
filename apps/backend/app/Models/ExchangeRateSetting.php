<?php

namespace App\Models;

use App\Domain\Enums\ExchangeRateMode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'from_currency_id',
    'to_currency_id',
    'mode',
    'provider',
    'frequency_minutes',
    'reference_amount',
    'is_active',
])]
class ExchangeRateSetting extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'mode' => ExchangeRateMode::class,
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
}
