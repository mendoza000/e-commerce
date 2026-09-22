<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The panel's product listing. Unlike the storefront's, it can ask for
 * inactive and archived products — that is most of the point of a catalogue
 * screen.
 */
class ListProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorised by the route group: reading the catalogue is open to any
        // active admin, staff included (docs/decisions.md).
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'status' => ['nullable', 'in:active,inactive'],
            // Archived products are hidden unless asked for: `with` mixes them
            // in, `only` shows the archive on its own.
            'trashed' => ['nullable', 'in:with,only'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.in' => 'El estado solo puede ser "active" o "inactive".',
            'trashed.in' => 'El filtro de archivados solo puede ser "with" o "only".',
            'category_id.exists' => 'Esa categoría no existe.',
        ];
    }
}
