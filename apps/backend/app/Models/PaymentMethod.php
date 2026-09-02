<?php

namespace App\Models;

use App\Domain\Enums\PaymentMethodType;
use App\Domain\Payments\Contracts\PaymentProviderInterface;
use App\Domain\Payments\PaymentProviderRegistry;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['type', 'label', 'currency_id', 'instructions', 'is_active', 'position'])]
class PaymentMethod extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => PaymentMethodType::class,
            'instructions' => 'array',
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

    /**
     * What the storefront is allowed to offer, in the order the admin arranged.
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('position')->orderBy('id');
    }

    public function provider(): PaymentProviderInterface
    {
        return app(PaymentProviderRegistry::class)->for($this);
    }

    /**
     * @return array<string, mixed>
     */
    public function instructionsFor(Order $order): array
    {
        return $this->provider()->getInstructions($order);
    }

    public function requiresProof(): bool
    {
        return $this->provider()->requiresProof();
    }

    /**
     * Single accessor for the free-form `instructions` JSON the admin fills in,
     * so providers never have to null-check the blob themselves.
     */
    public function instructionValue(string $key): ?string
    {
        $value = data_get($this->instructions, $key);

        return $value === null ? null : (string) $value;
    }

    /**
     * Whether any order was ever placed with this method.
     *
     * `orders.payment_method_id` is `nullOnDelete`, so deleting one would not
     * fail — it would quietly erase how those orders were paid. The panel
     * refuses and offers deactivation instead, which is what "we stopped
     * accepting Zelle" actually means.
     */
    public function hasOrders(): bool
    {
        return $this->orders()->exists();
    }
}
