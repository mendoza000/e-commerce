<?php

namespace App\Http\Requests\Api\Admin;

use App\Domain\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        ];
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
