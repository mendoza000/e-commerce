<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Enums\FulfillmentMethodType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\FulfillmentMethodStoreRequest;
use App\Http\Requests\Api\Admin\FulfillmentMethodUpdateRequest;
use App\Http\Requests\Api\Admin\ReorderFulfillmentMethodsRequest;
use App\Http\Resources\Admin\FulfillmentMethodResource;
use App\Models\FulfillmentMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * How the store gets orders to customers.
 *
 * Structurally the mirror of Admin\PaymentMethodController: every method here
 * prices through a provider (App\Domain\Fulfillment), and what an admin
 * configures is a label, an optional flat cost/currency, and a position in
 * the checkout list. Per-zone overrides live in FulfillmentZoneRateController.
 */
class FulfillmentMethodController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $methods = FulfillmentMethod::query()
            ->with('currency')
            ->withCount('orders')
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        return FulfillmentMethodResource::collection($methods);
    }

    public function show(FulfillmentMethod $fulfillmentMethod): JsonResponse
    {
        return FulfillmentMethodResource::make($this->detail($fulfillmentMethod))->response();
    }

    public function store(FulfillmentMethodStoreRequest $request): JsonResponse
    {
        $attributes = $request->validated();

        $method = FulfillmentMethod::create([
            ...$attributes,
            'requires_tracking_code' => $request->boolean('requires_tracking_code'),
            'is_active' => $request->boolean('is_active', true),
            'position' => $attributes['position'] ?? (int) FulfillmentMethod::query()->max('position') + 1,
        ]);

        return FulfillmentMethodResource::make($this->detail($method))
            ->response()
            ->setStatusCode(201);
    }

    public function update(FulfillmentMethodUpdateRequest $request, FulfillmentMethod $fulfillmentMethod): JsonResponse
    {
        $fulfillmentMethod->update($request->validated());

        return FulfillmentMethodResource::make($this->detail($fulfillmentMethod->fresh()))->response();
    }

    /**
     * A method that was ever used is never deleted: `orders.fulfillment_method_id`
     * is `nullOnDelete`, so the delete would go through and quietly erase how
     * those orders were meant to ship.
     *
     * @throws ValidationException
     */
    public function destroy(FulfillmentMethod $fulfillmentMethod): JsonResponse
    {
        if ($fulfillmentMethod->hasOrders()) {
            throw ValidationException::withMessages([
                'fulfillment_method' => [
                    'Hay órdenes con este método de envío. Desactívalo en vez de eliminarlo, '.
                    'para que su historial siga siendo legible.',
                ],
            ]);
        }

        $fulfillmentMethod->delete();

        return response()->json(status: 204);
    }

    /**
     * The order they appear in at checkout. Sent whole, like the payment
     * method reorder — a partial list would leave the methods it omits
     * sharing positions with the ones it moved.
     *
     * @throws ValidationException
     */
    public function reorder(ReorderFulfillmentMethodsRequest $request): AnonymousResourceCollection
    {
        $sent = array_map('intval', $request->input('fulfillment_methods'));
        $stored = FulfillmentMethod::query()->pluck('id')->all();

        $sortedSent = $sent;
        sort($sortedSent);
        sort($stored);

        if ($sortedSent !== $stored) {
            throw ValidationException::withMessages([
                'fulfillment_methods' => ['La lista debe incluir exactamente una vez cada método de envío.'],
            ]);
        }

        DB::transaction(function () use ($sent) {
            foreach ($sent as $position => $id) {
                FulfillmentMethod::query()->whereKey($id)->update(['position' => $position]);
            }
        });

        return $this->index();
    }

    /**
     * The types this installation knows how to serve, so the panel builds its
     * create form from this instead of hardcoding a list.
     */
    public function types(): JsonResponse
    {
        return response()->json([
            'data' => array_map(fn (FulfillmentMethodType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ], FulfillmentMethodType::cases()),
        ]);
    }

    private function detail(FulfillmentMethod $method): FulfillmentMethod
    {
        return $method->load(['currency', 'zoneRates.state', 'zoneRates.municipality'])->loadCount('orders');
    }
}
