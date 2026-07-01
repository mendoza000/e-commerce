<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'store_name',
    'logo_path',
    'primary_color',
    'secondary_color',
    'base_currency_id',
    'whatsapp_number',
])]
class StoreSetting extends Model
{
    use HasFactory;

    public function baseCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'base_currency_id');
    }

    public function enabledCurrencies(): BelongsToMany
    {
        return $this->belongsToMany(Currency::class, 'store_enabled_currencies');
    }
}
