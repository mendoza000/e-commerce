<?php

namespace App\Http\Requests\Api\Admin;

use App\Domain\Enums\InventoryMovementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListInventoryMovementsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Reading the kardex is a catalogue read, so staff reaches it too.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['nullable', Rule::enum(InventoryMovementType::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.enum' => 'Ese tipo de movimiento de inventario no existe.',
        ];
    }
}
