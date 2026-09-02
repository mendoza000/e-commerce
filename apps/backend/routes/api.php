<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CurrencyController;
use App\Http\Controllers\Api\FulfillmentMethodController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentMethodController;
use App\Http\Controllers\Api\PaymentProofController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\StoreController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/currencies', [CurrencyController::class, 'index']);
// Name, logo, colours and WhatsApp number: what the storefront needs to stop
// having the store's identity compiled into it (Fase 5d, docs/decisions.md).
Route::get('/store', [StoreController::class, 'show']);
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{slug}', [ProductController::class, 'show']);

Route::get('/locations/states', [LocationController::class, 'states']);
Route::get('/locations/municipalities', [LocationController::class, 'municipalities']);
Route::get('/locations/parishes', [LocationController::class, 'parishes']);

// No auth:sanctum here: guest checkout must work unauthenticated. Authenticated
// customer resolution is optional and handled inside OrderController.
Route::post('/orders', [OrderController::class, 'store']);
Route::get('/orders/{order:order_number}', [OrderController::class, 'show'])->middleware('throttle:20,1');

Route::get('/payment-methods', [PaymentMethodController::class, 'index']);

// Priced only when ?state_id= is given — see Api\FulfillmentMethodController.
Route::get('/fulfillment-methods', [FulfillmentMethodController::class, 'index']);

// Same guest-friendly access rule as GET /orders/{order}: ownership is checked
// inside the controller. Throttled harder because it accepts file uploads.
Route::post('/orders/{order:order_number}/payment-proof', [PaymentProofController::class, 'store'])
    ->middleware('throttle:10,1');

Route::get('/health', function () {
    try {
        DB::connection()->getPdo();
        $db = 'connected';
    } catch (Throwable $e) {
        $db = 'unreachable';
    }

    return response()->json([
        'status' => 'ok',
        'db' => $db,
    ]);
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
