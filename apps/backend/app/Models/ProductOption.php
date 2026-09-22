<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['product_id', 'name', 'position'])]
class ProductOption extends Model
{
    use HasFactory;

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(ProductOptionValue::class);
    }

    /**
     * Whether a live variant is built on one of this option's values.
     *
     * `product_option_values` cascades on delete all the way into
     * `variant_option_values`, so dropping an option in use would not fail —
     * it would quietly strip the value that told those variants apart, leaving
     * two identical rows where "Rojo" and "Azul" used to be. Archived variants
     * are deliberately not counted: they are already out of the catalogue, and
     * counting them would make an option undeletable forever.
     */
    public function isUsedByVariants(): bool
    {
        return ProductVariant::query()
            ->whereHas(
                'optionValues',
                fn (Builder $query) => $query->where('product_option_id', $this->getKey()),
            )
            ->exists();
    }
}
