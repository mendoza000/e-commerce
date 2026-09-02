<?php

use App\Http\Controllers\Api\Admin\AuthController;
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
    });
});
