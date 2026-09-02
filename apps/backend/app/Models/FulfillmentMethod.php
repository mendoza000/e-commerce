<?php

namespace App\Models;

use App\Domain\Enums\FulfillmentMethodType;
use App\Domain\Fulfillment\Contracts\FulfillmentProviderInterface;
use App\Domain\Fulfillment\FulfillmentProviderRegistry;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
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
            'type' => FulfillmentMethodType::class,
            'requires_tracking_code' => 'boolean',
            'base_cost' => 'decimal:6',
            'is_active' => 'boolean',
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

    public function zoneRates(): HasMany
    {
        return $this->hasMany(FulfillmentZoneRate::class);
    }

    /**
     * What the storefront is allowed to offer, in the order the admin
     * arranged. Mirrors PaymentMethod::active().
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('position')->orderBy('id');
    }

    public function provider(): FulfillmentProviderInterface
    {
        return app(FulfillmentProviderRegistry::class)->for($this);
    }

    public function estimateCostFor(?State $state, ?Municipality $municipality): ?string
    {
        return $this->provider()->estimateCost($state, $municipality);
    }

    /**
     * The most specific configured rate for a destination: a municipality row
     * beats a state-wide one (municipality_id null), which beats no row at
     * all. Null state means there is nothing to look up — an order is never
     * created without one, but a cost preview might be requested before an
     * address is picked.
     */
    public function zoneRateFor(?State $state, ?Municipality $municipality): ?FulfillmentZoneRate
    {
        if ($state === null) {
            return null;
        }

        return $this->zoneRates()
            ->where('state_id', $state->id)
            ->where(function (Builder $query) use ($municipality) {
                $query->whereNull('municipality_id');

                if ($municipality !== null) {
                    $query->orWhere('municipality_id', $municipality->id);
                }
            })
            ->orderByRaw('municipality_id IS NULL')
            ->first();
    }

    /**
     * Whether any order was ever placed with this method. Same reasoning as
     * PaymentMethod::hasOrders(): `orders.fulfillment_method_id` is
     * `nullOnDelete`, so deleting a used method would quietly erase how those
     * orders were meant to ship.
     */
    public function hasOrders(): bool
    {
        return $this->orders()->exists();
    }
}
