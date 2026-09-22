<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class PaymentProofStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Ownership is not a form concern: the order is resolved by route
        // binding and checked in PaymentProofController, which answers with a
        // 404 rather than leaking that the order exists.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'proof' => [
                'required',
                'file',
                'mimes:'.implode(',', config('commerce.payment_proof.mimes')),
                'max:'.config('commerce.payment_proof.max_kilobytes'),
            ],
            'reference' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $maxMegabytes = round(((int) config('commerce.payment_proof.max_kilobytes')) / 1024, 1);

        return [
            'proof.required' => 'Debes adjuntar el comprobante de pago.',
            'proof.mimes' => 'El comprobante debe ser una imagen (JPG, PNG o WEBP) o un PDF.',
            'proof.max' => "El comprobante no puede pesar más de {$maxMegabytes} MB.",
        ];
    }
}
