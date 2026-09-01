<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CurrencyController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentMethodController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/currencies', [CurrencyController::class, 'index']);
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
