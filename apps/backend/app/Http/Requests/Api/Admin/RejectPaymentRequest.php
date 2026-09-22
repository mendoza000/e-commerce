<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RejectPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The reason is mandatory because the customer is about to be asked to pay
     * again: it is what the panel shows them, and what the status history keeps
     * as the record of why an admin turned a proof down.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Debes indicar por qué rechazas el comprobante.',
            'reason.min' => 'El motivo es demasiado corto.',
        ];
    }
}
