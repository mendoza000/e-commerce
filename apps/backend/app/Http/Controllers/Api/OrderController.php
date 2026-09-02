<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\OrderStoreRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}
