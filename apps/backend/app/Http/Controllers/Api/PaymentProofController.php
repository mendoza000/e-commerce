<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PaymentProofStoreRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\PaymentProofService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class PaymentProofController extends Controller
{
    public function store(PaymentProofStoreRequest $request, Order $order, PaymentProofService $proofs): JsonResponse
    {
        if (! $order->isAccessibleBy($request->user('customer'), $request->input('document_number'))) {
            abort(404);
        }

        if (! $order->canAcceptPaymentProof()) {
            throw ValidationException::withMessages([
                'proof' => ["Esta orden ya no admite comprobantes: su estado es \"{$order->status->label()}\"."],
            ]);
        }

        $proofs->store($order, $request->file('proof'), $request->input('reference'));

        return OrderResource::make($order->fresh()->load([
            'items', 'baseCurrency', 'paymentCurrency', 'paymentMethod.currency', 'latestPaymentProof',
        ]))->response()->setStatusCode(201);
    }
}
