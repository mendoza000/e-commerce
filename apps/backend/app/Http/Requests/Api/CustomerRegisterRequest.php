<?php

namespace App\Http\Requests\Api;

use App\Domain\Enums\DocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;

/**
 * Account creation, separate from checkout: guest checkout stays the norm
 * (docs/decisions.md), so registering is opt-in and asks for exactly what an
 * account needs to sign back in — not the delivery address, which is captured
 * per order regardless of whether the customer has an account.
 */
class CustomerRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:customers,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'phone' => ['required', 'string', 'regex:/^\+58\d{10}$/'],
            'document_type' => ['required', new Enum(DocumentType::class)],
            'document_number' => ['required', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'Ya existe una cuenta con ese correo.',
            'phone.regex' => 'El teléfono debe tener el formato +58 seguido de 10 dígitos.',
        ];
    }
}
