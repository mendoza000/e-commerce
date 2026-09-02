<?php

namespace App\Models;

use App\Domain\Enums\DocumentType;
use App\Domain\Enums\OrderStatus;
use App\Domain\Exceptions\InvalidOrderTransition;
use App\Services\InventoryReservationService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

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
    'shipping_amount',
    'courier',
    'tracking_code',
    'shipping_note',
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
            'shipping_amount' => 'decimal:6',
            'reservation_expires_at' => 'datetime',
        ];
    }

    // ---------------------------------------------------------------------
    // Relations
    // ---------------------------------------------------------------------

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

    public function paymentProofs(): HasMany
    {
        return $this->hasMany(PaymentProof::class);
    }

    public function latestPaymentProof(): HasOne
    {
        return $this->hasOne(PaymentProof::class)->latestOfMany('submitted_at');
    }

    // ---------------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------------

    /**
     * Orders still holding stock they have not paid for, past their deadline.
     * Which statuses count is decided by the enum, not repeated here.
     */
    #[Scope]
    protected function withExpiredReservation(Builder $query): void
    {
        $holding = array_map(
            fn (OrderStatus $status) => $status->value,
            array_filter(OrderStatus::cases(), fn (OrderStatus $status) => $status->holdsReservation()),
        );

        $query->whereIn('status', $holding)
            ->whereNotNull('reservation_expires_at')
            ->where('reservation_expires_at', '<=', now());
    }

    // ---------------------------------------------------------------------
    // Access control
    // ---------------------------------------------------------------------

    /**
     * A guest order has no owner, so the document number given at checkout is
     * the shared secret that proves the requester placed it.
     *
     * Callers must answer a failed check with a 404, never a 403: a 403 would
     * confirm that the order number exists.
     */
    public function isAccessibleBy(?Customer $customer, ?string $documentNumber): bool
    {
        if ($customer !== null && $this->customer_id === $customer->id) {
            return true;
        }

        return $documentNumber !== null && hash_equals($this->document_number, $documentNumber);
    }

    // ---------------------------------------------------------------------
    // Lifecycle
    // ---------------------------------------------------------------------

    /**
     * The only way an order's status ever changes. Rejects moves the business
     * does not allow and writes the audit trail, so no caller has to remember
     * to do either.
     *
     * @throws InvalidOrderTransition
     */
    public function transitionTo(OrderStatus $target, ?User $changedBy = null, ?string $reason = null): void
    {
        if (! $this->status->canTransitionTo($target)) {
            throw new InvalidOrderTransition($this->status, $target);
        }

        $from = $this->status;

        $this->update(['status' => $target]);

        $this->statusHistory()->create([
            'from_status' => $from->value,
            'to_status' => $target->value,
            'changed_by' => $changedBy?->id,
            'reason' => $reason,
        ]);
    }

    public function canAcceptPaymentProof(): bool
    {
        return in_array($this->status, [OrderStatus::PendingPayment, OrderStatus::PaymentSubmitted], true);
    }

    /**
     * Called once the proof file is safely stored. Extends the reservation so
     * the admin has time to review it: a customer who has already paid must not
     * lose their stock to the expiry sweeper mid-review.
     */
    public function markPaymentSubmitted(): void
    {
        $this->extendReservationForReview();

        if ($this->status === OrderStatus::PaymentSubmitted) {
            // Re-upload after an earlier attempt: the status is already right,
            // only the review window needed refreshing.
            return;
        }

        $this->transitionTo(OrderStatus::PaymentSubmitted, reason: 'El cliente envió el comprobante de pago.');
    }

    /**
     * Turns the stock reservation into a definitive sale (PRD 5quater, step 2).
     * Idempotent and safe to call from two admin sessions at once.
     */
    public function confirmPayment(User $admin): void
    {
        DB::transaction(function () use ($admin) {
            $this->lockAndRefresh();

            if ($this->status === OrderStatus::Paid) {
                return;
            }

            $this->paymentMethod?->provider()->confirm($this, $admin);

            $this->transitionTo(OrderStatus::Paid, $admin, 'Pago confirmado por el administrador.');

            app(InventoryReservationService::class)->commit($this, $admin);

            // The sale is definitive: there is no reservation left to expire.
            $this->update(['reservation_expires_at' => null]);
        });
    }

    /**
     * The proof did not check out. The customer gets a fresh reservation window
     * to pay again instead of losing the order outright.
     */
    public function rejectPayment(User $admin, string $reason): void
    {
        DB::transaction(function () use ($admin, $reason) {
            $this->lockAndRefresh();

            $this->update([
                'reservation_expires_at' => now()->addMinutes((int) config('commerce.reservation_minutes')),
            ]);

            $this->transitionTo(OrderStatus::PendingPayment, $admin, $reason);
        });
    }

    /**
     * Moves an already-paid order along its way to the customer (preparing →
     * shipped → delivered). Takes the same row lock as the money-handling
     * actions, so two admins clicking at once cannot both write a history
     * entry for the same move.
     *
     * `$shipping` carries the free-form courier/tracking/note fields the panel
     * may capture when marking an order shipped (PRD section 6). It is only
     * ever applied together with the Shipped transition — TransitionOrderRequest
     * rejects it for any other target — so this method does not need to check
     * $target itself before saving it.
     *
     * @param  array{courier?: ?string, tracking_code?: ?string, shipping_note?: ?string}  $shipping
     *
     * @throws InvalidOrderTransition
     */
    public function advanceTo(OrderStatus $target, User $admin, ?string $reason = null, array $shipping = []): void
    {
        DB::transaction(function () use ($target, $admin, $reason, $shipping) {
            $this->lockAndRefresh();

            if ($this->status === $target) {
                return;
            }

            $this->transitionTo($target, $admin, $reason);

            if ($shipping !== []) {
                $this->update($shipping);
            }
        });
    }

    /**
     * Cancels an order at an admin's request, undoing whatever hold it still
     * has on inventory — which depends on how far it got:
     *
     *  - not yet paid: the reservation is released and `stock` is left alone,
     *    because it was never touched;
     *  - already paid: confirmPayment took the units out of `stock` for good,
     *    so they go back on the shelf.
     *
     * Skipping either would leak inventory. A manual cancellation is invisible
     * to the expiry sweeper — it only looks at orders that still hold a
     * reservation — so units nobody released would stay locked away forever.
     *
     * Cancelling a shipped or delivered order is refused by the state machine:
     * the goods are already out the door, and putting them back is a warehouse
     * decision rather than a click.
     *
     * @throws InvalidOrderTransition
     */
    public function cancel(User $admin, string $reason): void
    {
        DB::transaction(function () use ($admin, $reason) {
            $this->lockAndRefresh();

            // Whoever got here second has nothing left to do.
            if ($this->status === OrderStatus::Cancelled) {
                return;
            }

            // Checked up front so the inventory work below never runs for a
            // move that is going to be rejected anyway. transitionTo would
            // catch it too, one rollback later.
            if (! $this->status->canTransitionTo(OrderStatus::Cancelled)) {
                throw new InvalidOrderTransition($this->status, OrderStatus::Cancelled);
            }

            $inventory = app(InventoryReservationService::class);

            if ($this->status->holdsReservation()) {
                $inventory->release($this, $reason, $admin);
            } elseif ($this->status->hasCommittedStock()) {
                $inventory->restock($this, $reason, $admin);
            }

            $this->transitionTo(OrderStatus::Cancelled, $admin, $reason);

            // Terminal state: there is no reservation left to expire.
            $this->update(['reservation_expires_at' => null]);
        });
    }

    /**
     * Releases the held stock and cancels the order. Returns false when there
     * was nothing to do, so an overlapping scheduler run is a no-op rather than
     * a double release.
     */
    public function cancelExpiredReservation(string $reason): bool
    {
        return DB::transaction(function () use ($reason) {
            $this->lockAndRefresh();

            if (! $this->hasExpiredReservation()) {
                return false;
            }

            app(InventoryReservationService::class)->release($this, $reason);

            $this->transitionTo(OrderStatus::Cancelled, reason: $reason);

            return true;
        });
    }

    public function hasExpiredReservation(): bool
    {
        return $this->status->holdsReservation()
            && $this->reservation_expires_at !== null
            && $this->reservation_expires_at->isPast();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function paymentInstructions(): ?array
    {
        return $this->paymentMethod?->instructionsFor($this);
    }

    private function extendReservationForReview(): void
    {
        $this->update([
            'reservation_expires_at' => now()->addMinutes((int) config('commerce.payment_review_minutes')),
        ]);
    }

    /**
     * Takes a row lock on this order inside the caller's transaction and
     * reloads the model, so the decisions below are made on committed state.
     */
    private function lockAndRefresh(): void
    {
        static::query()->whereKey($this->getKey())->lockForUpdate()->first();

        $this->refresh();
    }
}
