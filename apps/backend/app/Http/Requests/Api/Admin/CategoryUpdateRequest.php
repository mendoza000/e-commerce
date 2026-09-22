<?php

namespace App\Http\Requests\Api\Admin;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CategoryUpdateRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('categories', 'slug')->ignore($this->category()->getKey()),
            ],
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->has('parent_id') || blank($this->input('parent_id'))) {
                    return;
                }

                $category = $this->category();
                $parentId = (int) $this->input('parent_id');

                // A category that is its own ancestor makes the tree infinite:
                // every walk up the parents loops, and the storefront's
                // breadcrumb never terminates.
                if ($parentId === (int) $category->getKey() || $this->isDescendant($parentId, (int) $category->getKey())) {
                    $validator->errors()->add(
                        'parent_id',
                        'Una categoría no puede colgar de sí misma ni de una de sus descendientes.',
                    );
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.unique' => 'Ya existe una categoría con esa URL.',
            'slug.alpha_dash' => 'La URL solo puede tener letras, números y guiones.',
            'parent_id.exists' => 'Esa categoría padre no existe.',
        ];
    }

    /**
     * Walks up from the candidate parent looking for the category being
     * edited. The depth guard is there so a cycle already in the table (from a
     * hand-written SQL fix, say) cannot hang the request.
     */
    private function isDescendant(int $candidateParentId, int $categoryId): bool
    {
        $seen = [];
        $current = Category::query()->find($candidateParentId);

        while ($current?->parent_id !== null) {
            if ((int) $current->parent_id === $categoryId) {
                return true;
            }

            if (isset($seen[$current->parent_id])) {
                return true;
            }

            $seen[$current->parent_id] = true;
            $current = Category::query()->find($current->parent_id);
        }

        return false;
    }

    private function category(): Category
    {
        return $this->route('category');
    }
}
