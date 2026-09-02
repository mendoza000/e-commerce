<?php

namespace App\Http\Resources\Admin;

use App\Domain\Enums\OrderStatus;
use App\Models\OrderStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One line of an order's audit trail. `changed_by` is null for the moves
 * nobody clicked: the customer uploading a proof, and the scheduler cancelling
 * an expired reservation.
 *
 * @mixin OrderStatusHistory
 */
class OrderStatusHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'from_status' => $this->from_status,
            'from_status_label' => $this->label($this->from_status),
            'to_status' => $this->to_status,
            'to_status_label' => $this->label($this->to_status),
            'reason' => $this->reason,
            'changed_by' => $this->whenLoaded('changedBy', fn () => $this->changedBy ? [
                'id' => $this->changedBy->id,
                'name' => $this->changedBy->name,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    /**
     * The columns store plain strings — an append-only log, not a live enum —
     * so a value written by an older version of the app must not blow up the
     * panel.
     */
    private function label(?string $status): ?string
    {
        return $status === null ? null : OrderStatus::tryFrom($status)?->label();
    }
}
