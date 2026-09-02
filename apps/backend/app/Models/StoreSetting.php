<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

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

    public static function current(): self
    {
        return static::query()->with(['baseCurrency', 'enabledCurrencies'])->firstOrFail();
    }

    /**
     * The logo as the browser should fetch it, or null when none is set.
     *
     * The disk comes from config rather than a literal, for the same reason as
     * product images: it is `public` in development and could be S3 in
     * production, and only the config knows.
     */
    public function logoUrl(): ?string
    {
        if ($this->logo_path === null) {
            return null;
        }

        return Storage::disk(config('commerce.store_logo.disk'))->url($this->logo_path);
    }
}
