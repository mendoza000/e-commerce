<?php

namespace App\Services;

use App\Domain\Enums\InventoryMovementType;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\ProductVariant;
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
     * Releases the stock reserved by an order's items (e.g. because its
     * reservation expired) and records one Release movement per line.
     * Follows the same ascending-lock discipline as lockVariantsForOrder.
     */
    public function release(Order $order, string $reason): void
    {
        $items = $order->items()->orderBy('product_variant_id')->get();

        $variantIds = $items->pluck('product_variant_id')->filter()->unique()->values()->all();

        if ($variantIds === []) {
            return;
        }

        $variants = ProductVariant::query()
            ->whereIn('id', $variantIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

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
                'created_by' => null,
            ]);
        }
    }
}
