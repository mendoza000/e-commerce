<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'product_id',
    'sku',
    'price_override',
    'stock',
    'reserved_quantity',
    'reserved_until',
    'is_active',
])]
class ProductVariant extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'price_override' => 'decimal:6',
            'reserved_until' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function optionValues(): BelongsToMany
    {
        return $this->belongsToMany(ProductOptionValue::class, 'variant_option_values');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    // Returns a string (not float) to preserve decimal precision from the 18,6 column.
    public function effectivePrice(): string
    {
        return (string) ($this->price_override ?? $this->product->base_price);
    }

    /**
     * What a new order could still take. Clamped at zero because an
     * out-of-band correction can leave `reserved_quantity` above `stock`, and
     * a negative "available" would read as a discount to every caller.
     */
    public function availableStock(): int
    {
        return max(0, $this->stock - $this->reserved_quantity);
    }

    /**
     * Whether an open order is holding units of this variant right now.
     * Archiving it, or archiving its product, would pull stock out from under
     * a customer who is on their way to pay.
     */
    public function hasLiveReservations(): bool
    {
        return $this->reserved_quantity > 0;
    }

    /**
     * The variant "without options" that every product has when no options are
     * configured — the implicit variant of the Fase 1 rule, which is the
     * product itself wearing a SKU.
     */
    public function isImplicit(): bool
    {
        return $this->optionValues()->doesntExist();
    }
}
