<?php

namespace App\Console\Commands;

use App\Domain\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Services\InventoryReservationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('orders:release-expired-reservations')]
#[Description('Cancels pending orders whose stock reservation has expired and releases their reserved inventory.')]
class ReleaseExpiredReservations extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(InventoryReservationService $reservations): int
    {
        $expiredOrderIds = Order::query()
            ->where('status', OrderStatus::PendingPayment)
            ->where('reservation_expires_at', '<=', now())
            ->pluck('id');

        $released = 0;

        foreach ($expiredOrderIds as $orderId) {
            DB::transaction(function () use ($orderId, $reservations, &$released) {
                $order = Order::query()->whereKey($orderId)->lockForUpdate()->first();

                if ($order === null || $order->status !== OrderStatus::PendingPayment) {
                    // Already resolved by a previous/overlapping run: idempotent no-op.
                    return;
                }

                if ($order->reservation_expires_at === null || $order->reservation_expires_at->isAfter(now())) {
                    return;
                }

                $reservations->release($order, 'La reserva de inventario expiró sin pago.');

                $order->update(['status' => OrderStatus::Cancelled]);

                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'from_status' => OrderStatus::PendingPayment->value,
                    'to_status' => OrderStatus::Cancelled->value,
                    'changed_by' => null,
                    'reason' => 'La reserva de inventario expiró sin pago.',
                ]);

                $released++;
            });
        }

        $this->info("Released {$released} expired reservation(s).");

        return self::SUCCESS;
    }
}
