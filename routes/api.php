<?php

use App\Http\Controllers\Api\Admin\RestaurantController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\Admin\MenuCategoryController;
use App\Http\Controllers\Api\Admin\MenuItemController;
use App\Http\Controllers\Api\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\Admin\TableController as AdminTableController;
use App\Http\Controllers\Api\Public\CartController;
use App\Http\Controllers\Api\Public\MenuController;
use App\Http\Controllers\Api\Public\OrderController;
use App\Http\Controllers\Api\Public\SessionController;
use App\Http\Controllers\Api\Public\TableController;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\ValidateTableSession;
use Illuminate\Support\Facades\Route;

Route::prefix('public')->middleware('throttle:60,1')->group(function () {
    Route::get('tables/{token}', [TableController::class, 'show']);
    Route::post('sessions', [SessionController::class, 'store']);
    Route::get('sessions/{sessionToken}', [SessionController::class, 'show']);
    Route::get('menu', [MenuController::class, 'index']);
    Route::get('menu/categories', [MenuController::class, 'categories']);

    Route::middleware(ValidateTableSession::class)->group(function () {
        Route::get('cart', [CartController::class, 'index']);
        Route::post('cart/items', [CartController::class, 'store']);
        Route::patch('cart/items/{cartItem}', [CartController::class, 'update']);
        Route::delete('cart/items/{cartItem}', [CartController::class, 'destroy']);
        Route::delete('cart', [CartController::class, 'clear']);
        Route::post('orders', [OrderController::class, 'store']);
        Route::get('orders/{orderNumber}', [OrderController::class, 'show']);
    });
});

Route::prefix('admin')->middleware(['auth:sanctum', EnsureUserHasRole::class.':admin,staff'])->group(function () {
    Route::get('dashboard/stats', [AdminDashboardController::class, 'stats']);
    Route::get('orders', [AdminOrderController::class, 'index']);
    Route::get('orders/{order}', [AdminOrderController::class, 'show']);
    Route::patch('orders/{order}/status', [AdminOrderController::class, 'updateStatus']);

    Route::middleware(EnsureUserHasRole::class.':admin')->group(function () {
        Route::get('tables', [AdminTableController::class, 'index']);
        Route::post('tables', [AdminTableController::class, 'store']);
        Route::patch('tables/{table}', [AdminTableController::class, 'update']);
        Route::delete('tables/{table}', [AdminTableController::class, 'destroy']);
        Route::post('tables/{table}/regenerate-token', [AdminTableController::class, 'regenerateToken']);
        Route::get('tables/{table}/qr', [AdminTableController::class, 'qr']);

        Route::get('menu/categories', [MenuCategoryController::class, 'index']);
        Route::post('menu/categories', [MenuCategoryController::class, 'store']);
        Route::patch('menu/categories/{category}', [MenuCategoryController::class, 'update']);
        Route::delete('menu/categories/{category}', [MenuCategoryController::class, 'destroy']);

        Route::get('menu/items', [MenuItemController::class, 'index']);
        Route::post('menu/items', [MenuItemController::class, 'store']);
        Route::patch('menu/items/{item}', [MenuItemController::class, 'update']);
        Route::delete('menu/items/{item}', [MenuItemController::class, 'destroy']);

        Route::get('restaurant', [RestaurantController::class, 'show']);
        Route::patch('restaurant', [RestaurantController::class, 'update']);

        Route::get('users', [UserController::class, 'index']);
        Route::post('users', [UserController::class, 'store']);
        Route::patch('users/{user}', [UserController::class, 'update']);
        Route::delete('users/{user}', [UserController::class, 'destroy']);
    });
});
