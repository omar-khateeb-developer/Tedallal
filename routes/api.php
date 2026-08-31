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
use App\Http\Controllers\Api\Public\PromoCodeController;
use App\Http\Controllers\Api\Admin\PromoCodeController as AdminPromoCodeController;

Route::prefix('public')->middleware('throttle:60,1')->group(function () {
    Route::get('tables/{token}', [TableController::class, 'show']);
    Route::post('sessions', [SessionController::class, 'store']);
    Route::get('sessions/{sessionToken}', [SessionController::class, 'show']);
    Route::get('menu', [MenuController::class, 'index']);
    Route::get('menu/categories', [MenuController::class, 'categories']);
    Route::post('promo-codes/validate', [PromoCodeController::class, 'validateCode']);

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

Route::prefix('admin')->middleware(['auth:sanctum'])->group(function () {
    Route::middleware('role:admin')->group(function () {
        Route::get('dashboard/stats', [AdminDashboardController::class, 'stats']);
        Route::get('restaurant', [RestaurantController::class, 'show']);
        Route::patch('restaurant', [RestaurantController::class, 'update']);
        Route::get('users', [UserController::class, 'index']);
        Route::post('users', [UserController::class, 'store']);
        Route::patch('users/{user}', [UserController::class, 'update']);
        Route::delete('users/{user}', [UserController::class, 'destroy']);
        Route::get('users/{user}/logs', [UserController::class, 'logs']);
        Route::get('analytics', [\App\Http\Controllers\Api\Admin\AnalyticsController::class, 'index']);
        Route::get('insights', [\App\Http\Controllers\Api\Admin\InsightsController::class, 'index']);
        Route::apiResource('promo-codes', AdminPromoCodeController::class);
    });

    Route::middleware('permission:manage_orders')->group(function () {
        Route::get('orders', [AdminOrderController::class, 'index']);
        Route::get('orders/{order}', [AdminOrderController::class, 'show']);
        Route::patch('orders/{order}/status', [AdminOrderController::class, 'updateStatus']);
    });

    Route::middleware('permission:manage_tables')->group(function () {
        Route::get('tables', [AdminTableController::class, 'index']);
        Route::post('tables', [AdminTableController::class, 'store']);
        Route::patch('tables/{table}', [AdminTableController::class, 'update']);
        Route::delete('tables/{table}', [AdminTableController::class, 'destroy']);
        Route::post('tables/{table}/regenerate-token', [AdminTableController::class, 'regenerateToken']);
        Route::get('tables/{table}/qr', [AdminTableController::class, 'qr']);
    });

    Route::middleware('permission:manage_menu')->group(function () {
        Route::get('menu/categories', [MenuCategoryController::class, 'index']);
        Route::post('menu/categories', [MenuCategoryController::class, 'store']);
        Route::patch('menu/categories/{category}', [MenuCategoryController::class, 'update']);
        Route::delete('menu/categories/{category}', [MenuCategoryController::class, 'destroy']);

        Route::get('menu/items', [MenuItemController::class, 'index']);
        Route::post('menu/items', [MenuItemController::class, 'store']);
        Route::patch('menu/items/{item}', [MenuItemController::class, 'update']);
        Route::delete('menu/items/{item}', [MenuItemController::class, 'destroy']);
    });
});

Route::prefix('super-admin')->middleware(['auth:sanctum', EnsureUserHasRole::class.':super_admin'])->group(function () {
    Route::get('dashboard/stats', [\App\Http\Controllers\Api\SuperAdmin\DashboardController::class, 'stats']);
    Route::get('restaurants', [\App\Http\Controllers\Api\SuperAdmin\RestaurantController::class, 'index']);
    Route::post('restaurants', [\App\Http\Controllers\Api\SuperAdmin\RestaurantController::class, 'store']);
    Route::patch('restaurants/{restaurant}', [\App\Http\Controllers\Api\SuperAdmin\RestaurantController::class, 'update']);
});
