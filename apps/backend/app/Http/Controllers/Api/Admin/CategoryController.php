<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\CategoryStoreRequest;
use App\Http\Requests\Api\Admin\CategoryUpdateRequest;
use App\Http\Resources\Admin\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

/**
 * Categories are the one part of the catalogue with no stock, no money and no
 * history hanging off it, so this is a plain CRUD — with one rule that is not:
 * a category in use cannot be deleted.
 *
 * Reading is open to any active admin; writing is owner-only, and that is
 * decided by which route group each action sits in (routes/admin.php).
 */
class CategoryController extends Controller
{
    /**
     * The whole tree, flat and ordered by name. Categories are a small,
     * hand-curated set — a store has a dozen, not a thousand — so this does
     * not paginate: the panel needs all of them at once to draw the parent
     * picker anyway.
     */
    public function index(): AnonymousResourceCollection
    {
        $categories = Category::query()
            ->with('parent')
            ->withCount(['products', 'children'])
            ->orderBy('name')
            ->get();

        return CategoryResource::collection($categories);
    }

    public function show(Category $category): JsonResponse
    {
        return CategoryResource::make($this->detail($category))->response();
    }

    public function store(CategoryStoreRequest $request): JsonResponse
    {
        $category = Category::create($request->validated());

        return CategoryResource::make($this->detail($category))->response()->setStatusCode(201);
    }

    public function update(CategoryUpdateRequest $request, Category $category): JsonResponse
    {
        $category->update($request->validated());

        return CategoryResource::make($this->detail($category->fresh()))->response();
    }

    /**
     * Both foreign keys pointing here are `nullOnDelete`, so the database
     * would accept this and quietly un-categorise every product underneath.
     * Refusing instead: where those products should go is a decision, and it
     * belongs to the admin rather than to a cascade rule.
     *
     * @throws ValidationException
     */
    public function destroy(Category $category): JsonResponse
    {
        if ($category->isInUse()) {
            throw ValidationException::withMessages([
                'category' => [
                    'Esta categoría todavía tiene productos o subcategorías. '.
                    'Muévelos a otra categoría antes de eliminarla.',
                ],
            ]);
        }

        $category->delete();

        return response()->json(status: 204);
    }

    private function detail(Category $category): Category
    {
        return $category->load('parent')->loadCount(['products', 'children']);
    }
}
