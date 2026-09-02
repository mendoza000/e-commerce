<?php

use App\Http\Controllers\Api\Admin\AuthController;
use App\Http\Controllers\Api\Admin\CategoryController;
use App\Http\Controllers\Api\Admin\CurrencyController;
use App\Http\Controllers\Api\Admin\ExchangeRateController;
use App\Http\Controllers\Api\Admin\ExchangeRateSettingController;
use App\Http\Controllers\Api\Admin\FulfillmentMethodController;
use App\Http\Controllers\Api\Admin\FulfillmentZoneRateController;
use App\Http\Controllers\Api\Admin\InventoryController;
use App\Http\Controllers\Api\Admin\OrderController;
use App\Http\Controllers\Api\Admin\PaymentMethodController;
use App\Http\Controllers\Api\Admin\PaymentProofController;
use App\Http\Controllers\Api\Admin\ProductController;
use App\Http\Controllers\Api\Admin\ProductImageController;
use App\Http\Controllers\Api\Admin\ProductOptionController;
use App\Http\Controllers\Api\Admin\ProductOptionValueController;
use App\Http\Controllers\Api\Admin\ProductVariantController;
use App\Http\Controllers\Api\Admin\StoreSettingController;
use App\Http\Controllers\Api\Admin\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin API
|--------------------------------------------------------------------------
|
| Mounted at /api/admin by bootstrap/app.php. Kept apart from routes/api.php
| so that "is this endpoint public?" is answered by which file it lives in,
| not by reading a middleware list.
|
| Authentication is session-based (Sanctum SPA mode) against the `web` guard,
| which resolves to User. Customers use the separate `customer` guard and
| never reach these routes — see docs/decisions.md.
|
*/

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:10,1')
    ->name('login');

Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/me', [AuthController::class, 'me'])->name('me');

    // Orders are the one area staff shares with owner: `staff` is an
    // order-operations role (docs/decisions.md), so these deliberately sit
    // outside the `role:owner` group below.
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order:order_number}', [OrderController::class, 'show'])->name('orders.show');

    // One endpoint per action rather than a single "set the status" call: each
    // of these moves money or stock, and the differences are the point.
    Route::post('/orders/{order:order_number}/confirm-payment', [OrderController::class, 'confirmPayment'])
        ->name('orders.confirm-payment');
    Route::post('/orders/{order:order_number}/reject-payment', [OrderController::class, 'rejectPayment'])
        ->name('orders.reject-payment');
    Route::post('/orders/{order:order_number}/cancel', [OrderController::class, 'cancel'])
        ->name('orders.cancel');

    // The moves that only advance fulfilment, and touch nothing else.
    Route::post('/orders/{order:order_number}/transition', [OrderController::class, 'transition'])
        ->name('orders.transition');

    // Not nested under the order: every admin may read every order, so an
    // order in the path would be decoration the endpoint has to re-verify.
    Route::get('/payment-proofs/{payment_proof}', [PaymentProofController::class, 'show'])
        ->name('payment-proofs.show');

    // Reading the catalogue is the other thing staff may do: an operator has
    // to be able to answer "do we still have that in blue?" while a customer
    // is on the phone. Writing it is owner-only, further down.
    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('/categories/{category}', [CategoryController::class, 'show'])->name('categories.show');

    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::get('/products/{product}', [ProductController::class, 'show'])
        // Archived products stay readable: the panel has to show one before
        // offering to restore it.
        ->withTrashed()
        ->name('products.show');
    Route::get('/products/{product}/images', [ProductImageController::class, 'index'])->name('products.images.index');

    // The kardex is a read, and a stock question is an order question.
    Route::get('/variants/{variant}/movements', [InventoryController::class, 'index'])
        ->name('variants.movements');

    // Staff management, catalogue writing and inventory are owner territory:
    // an operator must not be able to grant themselves configuration access,
    // reprice the catalogue, or invent stock.
    Route::middleware('role:owner')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('/users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
        Route::post('/users/{user}/activate', [UserController::class, 'activate'])->name('users.activate');

        Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::patch('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

        Route::post('/products', [ProductController::class, 'store'])->name('products.store');
        Route::patch('/products/{product}', [ProductController::class, 'update'])->name('products.update');
        // "Delete" is an archive: order history points at this product's
        // variants. See Product::archive().
        Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
        Route::post('/products/{product}/restore', [ProductController::class, 'restore'])
            ->withTrashed()
            ->name('products.restore');

        Route::post('/products/{product}/options', [ProductOptionController::class, 'store'])
            ->name('products.options.store');
        Route::patch('/options/{option}', [ProductOptionController::class, 'update'])->name('options.update');
        Route::delete('/options/{option}', [ProductOptionController::class, 'destroy'])->name('options.destroy');

        Route::post('/options/{option}/values', [ProductOptionValueController::class, 'store'])
            ->name('options.values.store');
        Route::patch('/option-values/{optionValue}', [ProductOptionValueController::class, 'update'])
            ->name('option-values.update');
        Route::delete('/option-values/{optionValue}', [ProductOptionValueController::class, 'destroy'])
            ->name('option-values.destroy');

        // Variants are generated from the option grid, never posted one by one
        // with a hand-written combination — see VariantGenerator.
        Route::post('/products/{product}/variants', [ProductVariantController::class, 'generate'])
            ->name('products.variants.generate');
        Route::patch('/variants/{variant}', [ProductVariantController::class, 'update'])->name('variants.update');
        Route::delete('/variants/{variant}', [ProductVariantController::class, 'destroy'])->name('variants.destroy');

        // The only way stock moves by hand, and it demands a reason: the
        // kardex has to be able to explain every unit.
        Route::post('/variants/{variant}/adjust-stock', [ProductVariantController::class, 'adjustStock'])
            ->name('variants.adjust-stock');

        Route::post('/products/{product}/images', [ProductImageController::class, 'store'])
            ->name('products.images.store');
        Route::post('/products/{product}/images/reorder', [ProductImageController::class, 'reorder'])
            ->name('products.images.reorder');
        Route::post('/images/{image}/primary', [ProductImageController::class, 'makePrimary'])
            ->name('images.primary');
        Route::delete('/images/{image}', [ProductImageController::class, 'destroy'])->name('images.destroy');

        // Store configuration. No id in the path: `store_settings` is a
        // single-row table (docs/decisions.md), so there is one store per
        // installation and the endpoint resolves it.
        Route::get('/settings', [StoreSettingController::class, 'show'])->name('settings.show');
        Route::put('/settings', [StoreSettingController::class, 'update'])->name('settings.update');
        Route::post('/settings/logo', [StoreSettingController::class, 'uploadLogo'])->name('settings.logo.store');
        Route::delete('/settings/logo', [StoreSettingController::class, 'deleteLogo'])->name('settings.logo.destroy');

        // The fixed catalogue the settings pickers are built from.
        Route::get('/currencies', [CurrencyController::class, 'index'])->name('currencies.index');

        // Rates are append-only: no update, no delete. Correcting one means
        // registering the right one now — see ExchangeRateController.
        Route::get('/exchange-rates', [ExchangeRateController::class, 'index'])->name('exchange-rates.index');
        Route::post('/exchange-rates', [ExchangeRateController::class, 'store'])->name('exchange-rates.store');

        Route::get('/exchange-rate-settings', [ExchangeRateSettingController::class, 'index'])
            ->name('exchange-rate-settings.index');
        Route::post('/exchange-rate-settings', [ExchangeRateSettingController::class, 'store'])
            ->name('exchange-rate-settings.store');
        Route::patch('/exchange-rate-settings/{rateSetting}', [ExchangeRateSettingController::class, 'update'])
            ->name('exchange-rate-settings.update');
        Route::delete('/exchange-rate-settings/{rateSetting}', [ExchangeRateSettingController::class, 'destroy'])
            ->name('exchange-rate-settings.destroy');

        // Declared before the {paymentMethod} routes so "types" and "reorder"
        // are never read as an id.
        Route::get('/payment-method-types', [PaymentMethodController::class, 'types'])
            ->name('payment-method-types.index');
        Route::post('/payment-methods/reorder', [PaymentMethodController::class, 'reorder'])
            ->name('payment-methods.reorder');

        Route::get('/payment-methods', [PaymentMethodController::class, 'index'])->name('payment-methods.index');
        Route::post('/payment-methods', [PaymentMethodController::class, 'store'])->name('payment-methods.store');
        Route::get('/payment-methods/{paymentMethod}', [PaymentMethodController::class, 'show'])
            ->name('payment-methods.show');
        Route::patch('/payment-methods/{paymentMethod}', [PaymentMethodController::class, 'update'])
            ->name('payment-methods.update');
        Route::delete('/payment-methods/{paymentMethod}', [PaymentMethodController::class, 'destroy'])
            ->name('payment-methods.destroy');

        // Same shape as payment methods: declared first so "types" and
        // "reorder" are never read as an id.
        Route::get('/fulfillment-method-types', [FulfillmentMethodController::class, 'types'])
            ->name('fulfillment-method-types.index');
        Route::post('/fulfillment-methods/reorder', [FulfillmentMethodController::class, 'reorder'])
            ->name('fulfillment-methods.reorder');

        Route::get('/fulfillment-methods', [FulfillmentMethodController::class, 'index'])
            ->name('fulfillment-methods.index');
        Route::post('/fulfillment-methods', [FulfillmentMethodController::class, 'store'])
            ->name('fulfillment-methods.store');
        Route::get('/fulfillment-methods/{fulfillmentMethod}', [FulfillmentMethodController::class, 'show'])
            ->name('fulfillment-methods.show');
        Route::patch('/fulfillment-methods/{fulfillmentMethod}', [FulfillmentMethodController::class, 'update'])
            ->name('fulfillment-methods.update');
        Route::delete('/fulfillment-methods/{fulfillmentMethod}', [FulfillmentMethodController::class, 'destroy'])
            ->name('fulfillment-methods.destroy');

        // Per-zone overrides (PRD section 6: "tarifa plana por zona"). Not
        // nested under a resource route because update/destroy address a rate
        // directly — the method only matters for index/store.
        Route::get('/fulfillment-methods/{fulfillmentMethod}/zone-rates', [FulfillmentZoneRateController::class, 'index'])
            ->name('fulfillment-methods.zone-rates.index');
        Route::post('/fulfillment-methods/{fulfillmentMethod}/zone-rates', [FulfillmentZoneRateController::class, 'store'])
            ->name('fulfillment-methods.zone-rates.store');
        Route::patch('/zone-rates/{zoneRate}', [FulfillmentZoneRateController::class, 'update'])
            ->name('zone-rates.update');
        Route::delete('/zone-rates/{zoneRate}', [FulfillmentZoneRateController::class, 'destroy'])
            ->name('zone-rates.destroy');
    });
});
