<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\OrderStoreRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    public function store(OrderStoreRequest $request, OrderService $orders): JsonResponse
    {
        $order = $orders->createOrder($request->validated(), $request->user('customer'));

        return OrderResource::make($order)->response()->setStatusCode(201);
    }

    public function show(Request $request, Order $order): OrderResource
    {
        // Always 404, never 403/422: a mismatch must not reveal that the
        // order_number exists at all.
        abort_unless(
            $order->isAccessibleBy($request->user('customer'), $request->query('document_number')),
            404
        );

        $order->load([
            'items', 'baseCurrency', 'paymentCurrency', 'state', 'municipality', 'parish',
            'paymentMethod.currency', 'fulfillmentMethod.currency', 'latestPaymentProof',
        ]);

        return OrderResource::make($order);
    }

    /**
     * "Mis pedidos" (PRD section 5): the order history of the authenticated
     * customer. Guarded by the `auth:customer` route middleware, unlike show()
     * above — there is no document-number fallback here because there is no
     * order in the URL to prove ownership of; the token is the only key.
     */
    public function mine(Request $request): AnonymousResourceCollection
    {
        $orders = $request->user('customer')->orders()
            ->with(['items', 'baseCurrency', 'paymentCurrency', 'paymentMethod', 'fulfillmentMethod'])
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20))
            ->withQueryString();

        return OrderResource::collection($orders);
    }
}
