<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Enums\PaymentMethodType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\PaymentMethodStoreRequest;
use App\Http\Requests\Api\Admin\PaymentMethodUpdateRequest;
use App\Http\Requests\Api\Admin\ReorderPaymentMethodsRequest;
use App\Http\Resources\Admin\PaymentMethodResource;
use App\Models\PaymentMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * How the store gets paid.
 *
 * Every method here is manual — the customer transfers and uploads a proof
 * (PRD section 7) — so what an admin configures is an account to pay into, in
 * a currency, with a position in the checkout list. Which fields that account
 * has is decided by the type, not by this controller: see
 * PaymentMethodType::instructionFields().
 */
class PaymentMethodController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $methods = PaymentMethod::query()
            ->with('currency')
            ->withCount('orders')
            // The same order the storefront shows them in, so what the admin
            // arranges is what the admin sees.
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        return PaymentMethodResource::collection($methods);
    }

    public function show(PaymentMethod $paymentMethod): JsonResponse
    {
        return PaymentMethodResource::make($this->detail($paymentMethod))->response();
    }

    public function store(PaymentMethodStoreRequest $request): JsonResponse
    {
        $attributes = $request->validated();

        $method = PaymentMethod::create([
            ...$attributes,
            'is_active' => $request->boolean('is_active', true),
            // Appended to the end of the checkout list unless the panel says
            // otherwise.
            'position' => $attributes['position'] ?? (int) PaymentMethod::query()->max('position') + 1,
        ]);

        return PaymentMethodResource::make($this->detail($method))
            ->response()
            ->setStatusCode(201);
    }

    public function update(PaymentMethodUpdateRequest $request, PaymentMethod $paymentMethod): JsonResponse
    {
        $paymentMethod->update($request->validated());

        return PaymentMethodResource::make($this->detail($paymentMethod->fresh()))->response();
    }

    /**
     * A method that was ever used is never deleted: `orders.payment_method_id`
     * is `nullOnDelete`, so the delete would go through and quietly erase how
     * those orders were paid. Deactivating is what "we stopped accepting
     * Zelle" actually means, and it leaves the history readable.
     *
     * @throws ValidationException
     */
    public function destroy(PaymentMethod $paymentMethod): JsonResponse
    {
        if ($paymentMethod->hasOrders()) {
            throw ValidationException::withMessages([
                'payment_method' => [
                    'Hay órdenes pagadas con este método. Desactívalo en vez de eliminarlo, '.
                    'para que su historial siga siendo legible.',
                ],
            ]);
        }

        $paymentMethod->delete();

        return response()->json(status: 204);
    }

    /**
     * The order they appear in at checkout. Sent whole, like the product image
     * reorder — a partial list would leave the methods it omits sharing
     * positions with the ones it moved.
     *
     * @throws ValidationException
     */
    public function reorder(ReorderPaymentMethodsRequest $request): AnonymousResourceCollection
    {
        $sent = array_map('intval', $request->input('payment_methods'));
        $stored = PaymentMethod::query()->pluck('id')->all();

        $sortedSent = $sent;
        sort($sortedSent);
        sort($stored);

        if ($sortedSent !== $stored) {
            throw ValidationException::withMessages([
                'payment_methods' => ['La lista debe incluir exactamente una vez cada método de pago.'],
            ]);
        }

        DB::transaction(function () use ($sent) {
            foreach ($sent as $position => $id) {
                PaymentMethod::query()->whereKey($id)->update(['position' => $position]);
            }
        });

        return $this->index();
    }

    /**
     * The types this installation knows how to serve, with the account fields
     * each one expects. The panel builds its create form from this instead of
     * hardcoding a list that would drift from the providers.
     */
    public function types(): JsonResponse
    {
        return response()->json([
            'data' => array_map(fn (PaymentMethodType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
                'instruction_fields' => $type->instructionFields(),
            ], PaymentMethodType::cases()),
        ]);
    }

    private function detail(PaymentMethod $method): PaymentMethod
    {
        return $method->load('currency')->loadCount('orders');
    }
}
