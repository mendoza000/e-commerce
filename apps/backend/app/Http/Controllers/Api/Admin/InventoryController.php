<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\ListInventoryMovementsRequest;
use App\Http\Resources\Admin\InventoryMovementResource;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The kardex of one variant: every unit that entered or left it, and why.
 *
 * Read-only by design. `inventory_movements` is an append-only ledger — the
 * rows are written by the order lifecycle and by manual adjustments, and
 * nothing in the panel edits or deletes one. A ledger that can be rewritten is
 * not a ledger.
 */
class InventoryController extends Controller
{
    public function index(ListInventoryMovementsRequest $request, ProductVariant $variant): AnonymousResourceCollection
    {
        $movements = $variant->inventoryMovements()
            ->with(['creator', 'order'])
            ->when(
                $request->filled('type'),
                fn (Builder $query) => $query->where('type', (string) $request->string('type')),
            )
            // Newest first, with the id as tiebreaker: several movements of one
            // order share a `created_at` down to the second.
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        return InventoryMovementResource::collection($movements);
    }
}
