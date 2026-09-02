<?php

namespace App\Services;

use App\Domain\Enums\InventoryMovementType;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryReservationService
{
    /**
     * Locks the requested variants and validates existence, active status, and
     * available stock before an order is created. Must be called inside an
     * already-open DB transaction (see OrderService::createOrder).
     *
     * Variants are locked in ascending id order (never in request order) so
     * that two concurrent checkouts sharing a variant always acquire their
     * row locks in the same sequence, which avoids deadlocks.
     *
     * Note: `product_variants.reserved_until` is intentionally left unused
     * here. It is a per-variant aggregate and cannot track the expiry of
     * multiple concurrent orders reserving the same variant — that is what
     * `orders.reservation_expires_at` is for.
     *
     * @param  array<int, int>  $quantitiesByVariantId  Map of product_variant_id => quantity, in the same order as the request's `items` array.
     * @return Collection<int, ProductVariant> Locked variants keyed by id.
     *
     * @throws ValidationException
     */
    public function lockVariantsForOrder(array $quantitiesByVariantId): Collection
    {
        $variantIds = array_keys($quantitiesByVariantId);

        $variants = ProductVariant::query()
            ->whereIn('id', $variantIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $errors = [];

        foreach (array_values($variantIds) as $index => $variantId) {
            $quantity = $quantitiesByVariantId[$variantId];
            $variant = $variants->get($variantId);

            if ($variant === null) {
                $errors["items.{$index}.product_variant_id"] = ['La variante de producto seleccionada no existe.'];

                continue;
            }

            if (! $variant->is_active) {
                $errors["items.{$index}.product_variant_id"] = ['Esta variante de producto ya no está disponible.'];

                continue;
            }

            $available = max(0, $variant->stock - $variant->reserved_quantity);

            if ($available < $quantity) {
                $errors["items.{$index}.quantity"] = ["Solo hay {$available} unidad(es) disponible(s) de este producto."];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $variants;
    }

    /**
     * Reserves stock for the given locked variants and records one
     * Reservation movement per line. The increment is atomic because the
     * rows were already locked by lockVariantsForOrder in the same
     * transaction.
     *
     * @param  Collection<int, ProductVariant>  $lockedVariants
     * @param  array<int, int>  $quantitiesByVariantId
     */
    public function reserve(Order $order, Collection $lockedVariants, array $quantitiesByVariantId): void
    {
        foreach ($quantitiesByVariantId as $variantId => $quantity) {
            $variant = $lockedVariants->get($variantId);

            $variant->increment('reserved_quantity', $quantity);

            InventoryMovement::create([
                'product_variant_id' => $variant->id,
                'sku' => $variant->sku,
                'type' => InventoryMovementType::Reservation,
                'quantity_change' => -$quantity,
                'reason' => null,
                'order_id' => $order->id,
                'created_by' => null,
            ]);
        }
    }

    /**
     * Turns an order's reservation into a definitive sale (PRD 5quater, step
     * 2): the units leave `stock` for good and stop being counted as reserved,
     * with one Sale movement per line for the kardex.
     *
     * Must be called inside an already-open transaction, from
     * Order::confirmPayment, which holds the order's row lock. Follows the same
     * ascending-lock discipline as lockVariantsForOrder.
     */
    public function commit(Order $order, ?User $admin = null): void
    {
        $items = $order->items()->orderBy('product_variant_id')->get();

        $variants = $this->lockVariantsOf($items);

        if ($variants === null) {
            return;
        }

        foreach ($items as $item) {
            if ($variants->get($item->product_variant_id) === null) {
                continue;
            }

            $quantity = (int) $item->quantity;

            // Both counters move in one statement so stock and reserved_quantity
            // can never be observed out of step. The clamps are defensive: an
            // out-of-band manual adjustment must not push either negative.
            ProductVariant::query()
                ->whereKey($item->product_variant_id)
                ->update([
                    'stock' => DB::raw("GREATEST(stock - {$quantity}, 0)"),
                    'reserved_quantity' => DB::raw("GREATEST(reserved_quantity - {$quantity}, 0)"),
                ]);

            InventoryMovement::create([
                'product_variant_id' => $item->product_variant_id,
                'sku' => $item->sku,
                'type' => InventoryMovementType::Sale,
                'quantity_change' => -$quantity,
                'reason' => 'Pago confirmado.',
                'order_id' => $order->id,
                'created_by' => $admin?->id,
            ]);
        }
    }

    /**
     * Releases the stock reserved by an order's items (e.g. because its
     * reservation expired) and records one Release movement per line.
     * Follows the same ascending-lock discipline as lockVariantsForOrder.
     */
    public function release(Order $order, string $reason, ?User $admin = null): void
    {
        $items = $order->items()->orderBy('product_variant_id')->get();

        $variants = $this->lockVariantsOf($items);

        if ($variants === null) {
            return;
        }

        foreach ($items as $item) {
            $variant = $variants->get($item->product_variant_id);

            if ($variant === null) {
                continue;
            }

            // Defensive clamp: reserved_quantity must never go negative, even
            // if it was already released or adjusted out of band.
            ProductVariant::query()
                ->whereKey($variant->id)
                ->update([
                    'reserved_quantity' => DB::raw('GREATEST(reserved_quantity - '.(int) $item->quantity.', 0)'),
                ]);

            InventoryMovement::create([
                'product_variant_id' => $variant->id,
                'sku' => $item->sku,
                'type' => InventoryMovementType::Release,
                'quantity_change' => $item->quantity,
                'reason' => $reason,
                'order_id' => $order->id,
                // Null when the expiry sweeper is the one releasing: nobody
                // decided it, the deadline did.
                'created_by' => $admin?->id,
            ]);
        }
    }

    /**
     * Puts back on the shelf the units an order had already taken out of
     * `stock`, because a sale that was committed is being cancelled. The
     * mirror image of commit(): `stock` goes back up and no reservation is
     * involved, since confirmPayment cleared it when it committed.
     *
     * Must be called inside an already-open transaction, from Order::cancel,
     * which holds the order's row lock. Follows the same ascending-lock
     * discipline as lockVariantsForOrder.
     */
    public function restock(Order $order, string $reason, ?User $admin = null): void
    {
        $items = $order->items()->orderBy('product_variant_id')->get();

        $variants = $this->lockVariantsOf($items);

        if ($variants === null) {
            return;
        }

        foreach ($items as $item) {
            $variant = $variants->get($item->product_variant_id);

            if ($variant === null) {
                continue;
            }

            $variant->increment('stock', (int) $item->quantity);

            InventoryMovement::create([
                'product_variant_id' => $variant->id,
                'sku' => $item->sku,
                'type' => InventoryMovementType::Restock,
                'quantity_change' => $item->quantity,
                'reason' => $reason,
                'order_id' => $order->id,
                'created_by' => $admin?->id,
            ]);
        }
    }

    /**
     * Manual stock correction by an admin: a restock, a fix after a physical
     * count, a write-off for damage or loss. Adjustment is the one movement
     * type no automatic path emits — everything else in this service is driven
     * by an order — which is why the reason is mandatory rather than optional:
     * an unexplained change in stock is exactly what the kardex exists to
     * prevent.
     *
     * `$quantityChange` is signed and relative, never an absolute target:
     * "+12 arrived" and "-3 broken" are what the admin actually knows, and a
     * delta cannot silently overwrite a concurrent sale the way a target can.
     *
     * The row is re-read under `lockForUpdate` instead of trusting the instance
     * handed in, so the ceiling below is checked against the stock as it is
     * now. Stock may never end up under `reserved_quantity`: those units are
     * already promised to open orders, and pushing stock beneath them is how a
     * store oversells against live reservations.
     *
     * @throws ValidationException
     */
    public function adjust(
        ProductVariant $variant,
        int $quantityChange,
        string $reason,
        ?User $admin = null,
    ): ProductVariant {
        return DB::transaction(function () use ($variant, $quantityChange, $reason, $admin) {
            $locked = ProductVariant::query()
                ->whereKey($variant->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $resulting = $locked->stock + $quantityChange;

            if ($resulting < 0) {
                throw ValidationException::withMessages([
                    'quantity_change' => [
                        "No puedes descontar {$this->units(-$quantityChange)}: solo hay {$this->units($locked->stock)} en stock.",
                    ],
                ]);
            }

            if ($resulting < $locked->reserved_quantity) {
                throw ValidationException::withMessages([
                    'quantity_change' => [
                        "No puedes dejar el stock por debajo de {$this->units($locked->reserved_quantity)}: ".
                        'hay órdenes abiertas que ya las tienen reservadas.',
                    ],
                ]);
            }

            $locked->update(['stock' => $resulting]);

            InventoryMovement::create([
                'product_variant_id' => $locked->id,
                'sku' => $locked->sku,
                'type' => InventoryMovementType::Adjustment,
                'quantity_change' => $quantityChange,
                'reason' => $reason,
                // No order caused this: it is a human correcting the shelf.
                'order_id' => null,
                'created_by' => $admin?->id,
            ]);

            return $locked;
        });
    }

    private function units(int $quantity): string
    {
        return $quantity === 1 ? '1 unidad' : "{$quantity} unidades";
    }

    /**
     * Locks the variants behind a set of order items, in ascending id order.
     * Returns null when the order has no variants left to touch.
     *
     * @param  Collection<int, OrderItem>  $items
     * @return Collection<int, ProductVariant>|null Locked variants keyed by id.
     */
    private function lockVariantsOf(Collection $items): ?Collection
    {
        $variantIds = $items->pluck('product_variant_id')->filter()->unique()->values()->all();

        if ($variantIds === []) {
            return null;
        }

        return ProductVariant::query()
            ->whereIn('id', $variantIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }
}
