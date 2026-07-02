<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListProductsRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function index(ListProductsRequest $request): AnonymousResourceCollection
    {
        $products = Product::query()
            ->where('is_active', true)
            ->when($request->filled('category'), fn ($query) => $query->whereHas(
                'category',
                fn ($category) => $category->where('slug', $request->string('category'))
            ))
            ->when($request->filled('search'), fn ($query) => $query->where(
                'name',
                'ilike',
                '%'.$request->string('search').'%'
            ))
            ->with(['category', 'images' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('position')])
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return ProductResource::collection($products);
    }
}
