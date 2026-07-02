<?php

namespace App\Models;

use App\Domain\Enums\DocumentType;
use App\Domain\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'customer_id',
    'status',
    'order_number',
    'customer_name',
    'customer_phone',
    'document_type',
    'document_number',
    'state_id',
    'municipality_id',
    'parish_id',
    'address_reference',
    'base_currency_id',
    'base_amount',
    'payment_currency_id',
    'exchange_rate_applied',
    'payment_amount',
    'payment_method_id',
    'fulfillment_method_id',
    'reservation_expires_at',
])]
class Order extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'document_type' => DocumentType::class,
            'base_amount' => 'decimal:6',
            'exchange_rate_applied' => 'decimal:6',
            'payment_amount' => 'decimal:6',
            'reservation_expires_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function baseCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'base_currency_id');
    }

    public function paymentCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'payment_currency_id');
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public function parish(): BelongsTo
    {
        return $this->belongsTo(Parish::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function fulfillmentMethod(): BelongsTo
    {
        return $this->belongsTo(FulfillmentMethod::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }
}
