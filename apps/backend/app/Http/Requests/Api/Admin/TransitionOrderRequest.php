<?php

namespace App\Http\Requests\Api\Admin;

use App\Domain\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TransitionOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Only the fulfilment statuses are accepted here. Becoming paid, going back
     * to pending or being cancelled all move stock, and each has its own
     * endpoint — routing them through this one would skip those side effects.
     *
     * Whether the move is legal *from the order's current status* is not
     * checked here: that is the state machine's answer to give, and it gives it
     * as a 422 `invalid_order_transition` with a message naming both states.
     *
     * courier/tracking_code/note are free-form (PRD section 6: "número de guía
     * si el cliente lo obtiene") and only make sense next to Shipped — see
     * after() below, which rejects them for any other target.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::in(array_map(
                    fn (OrderStatus $status) => $status->value,
                    OrderStatus::fulfillmentStatuses(),
                )),
            ],
            'reason' => ['nullable', 'string', 'max:500'],
            'courier' => ['nullable', 'string', 'max:255'],
            'tracking_code' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if ($this->input('status') === OrderStatus::Shipped->value) {
                    return;
                }

                foreach (['courier', 'tracking_code', 'note'] as $field) {
                    if ($this->filled($field)) {
                        $validator->errors()->add(
                            $field,
                            'Este dato solo aplica al marcar la orden como enviada.',
                        );
                    }
                }
            },
        ];
    }

    /**
     * The subset the domain layer actually stores, keyed to Order's columns.
     *
     * @return array{courier?: ?string, tracking_code?: ?string, shipping_note?: ?string}
     */
    public function shippingDetails(): array
    {
        if ($this->input('status') !== OrderStatus::Shipped->value) {
            return [];
        }

        return array_filter([
            'courier' => $this->input('courier'),
            'tracking_code' => $this->input('tracking_code'),
            'shipping_note' => $this->input('note'),
        ], fn ($value) => $value !== null);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Debes indicar el nuevo estado de la orden.',
            'status.in' => 'Ese estado no se puede asignar desde aquí.',
        ];
    }
}
