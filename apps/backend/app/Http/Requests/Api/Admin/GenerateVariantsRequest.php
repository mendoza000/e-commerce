<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Two shapes, one endpoint: with no `combinations` the generator produces
 * every combination the options allow; with a list it produces exactly those.
 * The panel needs both — "generate all" for a fresh product, a selection when
 * the store does not stock every colour in every size.
 */
class GenerateVariantsRequest extends FormRequest
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
            'combinations' => ['sometimes', 'nullable', 'array', 'min:1', 'max:'.config('commerce.catalog.max_variants_per_product')],
            'combinations.*' => ['array', 'min:1', 'max:20'],
            'combinations.*.*' => ['integer', 'min:1'],
            // Lets the admin key SKUs off something shorter than the slug
            // ("CAM" instead of "camisa-manga-larga-algodon").
            'sku_prefix' => ['nullable', 'string', 'max:32'],
        ];
    }

    /**
     * The generator itself rejects combinations that are not real points in
     * the option grid; those errors come back under the same `combinations.N`
     * keys these rules use.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'combinations.max' => 'Estás pidiendo más combinaciones que el máximo de variantes por producto.',
            'combinations.*.min' => 'Cada combinación debe indicar al menos un valor de opción.',
        ];
    }
}
