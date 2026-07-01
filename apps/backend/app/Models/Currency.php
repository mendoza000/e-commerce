<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'symbol', 'decimal_places', 'is_active'])]
class Currency extends Model
{
    use HasFactory;

    public function ratesFrom(): HasMany
    {
        return $this->hasMany(ExchangeRate::class, 'from_currency_id');
    }

    public function ratesTo(): HasMany
    {
        return $this->hasMany(ExchangeRate::class, 'to_currency_id');
    }

    public function rateSettingsFrom(): HasMany
    {
        return $this->hasMany(ExchangeRateSetting::class, 'from_currency_id');
    }

    public function rateSettingsTo(): HasMany
    {
        return $this->hasMany(ExchangeRateSetting::class, 'to_currency_id');
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class);
    }

    public function fulfillmentMethods(): HasMany
    {
        return $this->hasMany(FulfillmentMethod::class);
    }
}
