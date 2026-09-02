<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `image` on top of `mimes` on purpose: `mimes` trusts the extension, and
     * `image` makes the validator actually look at the file. A .png that is
     * really a script must not reach a public disk.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'logo' => [
                'required',
                'file',
                'image',
                'mimes:'.implode(',', config('commerce.store_logo.mimes')),
                'max:'.config('commerce.store_logo.max_kilobytes'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $maxMegabytes = round(((int) config('commerce.store_logo.max_kilobytes')) / 1024, 1);

        return [
            'logo.required' => 'Debes adjuntar una imagen.',
            'logo.image' => 'El archivo no es una imagen válida.',
            'logo.mimes' => 'El logo debe ser JPG, PNG o WEBP.',
            'logo.max' => "El logo no puede pesar más de {$maxMegabytes} MB.",
        ];
    }
}
