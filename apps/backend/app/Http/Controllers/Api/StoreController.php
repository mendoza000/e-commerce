<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StoreResource;
use App\Models\StoreSetting;
use Illuminate\Http\JsonResponse;

/**
 * The store's public identity.
 *
 * The Fase 2 theming was static — colours compiled into the frontend — which
 * was fine while nothing could change them. Now that the panel can, a static
 * frontend would go stale the moment an owner renames the store or uploads a
 * logo, and Fase 7 is precisely about configuring a fresh instance without
 * touching code. So the storefront reads this instead.
 */
class StoreController extends Controller
{
    public function show(): JsonResponse
    {
        return StoreResource::make(StoreSetting::current())->response();
    }
}
