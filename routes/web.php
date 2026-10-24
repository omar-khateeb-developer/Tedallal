<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Web\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Web\Admin\TablePrintController;
use App\Http\Controllers\Web\CustomerController;
use App\Http\Controllers\Web\StaffController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect('/login');
});

Route::get('/t/{token}', [CustomerController::class, 'table'])->name('customer.menu');
Route::get('/t/{token}/cart', [CustomerController::class, 'cart'])->name('customer.cart');
Route::get('/t/{token}/orders/{orderNumber}', [CustomerController::class, 'order'])->name('customer.order');

Route::middleware(['auth', 'verified', 'permission:manage_orders'])->group(function () {
    Route::get('/staff/orders', [StaffController::class, 'orders'])->name('staff.orders');
});

Route::middleware(['auth', 'verified', 'permission:manage_orders'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/orders/{order}/print', \App\Http\Controllers\Web\Admin\OrderPrintController::class)->name('orders.print');
});

Route::middleware(['auth', 'verified', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/orders', [AdminDashboardController::class, 'orders'])->name('orders');
    Route::get('/settings', [AdminDashboardController::class, 'settings'])->name('settings');
    Route::get('/users', [AdminDashboardController::class, 'users'])->name('users');
    Route::get('/analytics', function () {
        return Inertia::render('Admin/Analytics');
    })->name('analytics');
    Route::get('/offers', function () {
        return Inertia::render('Admin/Offers');
    })->name('offers');
});

Route::middleware(['auth', 'verified', 'permission:manage_tables'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/tables', [AdminDashboardController::class, 'tables'])->name('tables');
    Route::get('/tables/print', TablePrintController::class)->name('tables.print');
});

Route::middleware(['auth', 'verified', 'permission:manage_menu'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/menu/categories', [AdminDashboardController::class, 'menuCategories'])->name('menu.categories');
    Route::get('/menu/items', [AdminDashboardController::class, 'menuItems'])->name('menu.items');
});

Route::middleware(['auth', 'verified', 'role:super_admin'])->prefix('super-admin')->name('super_admin.')->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\Web\SuperAdminController::class, 'index'])->name('dashboard');
    Route::get('/restaurants/create', [\App\Http\Controllers\Web\SuperAdminController::class, 'createRestaurant'])->name('restaurants.create');
    Route::get('/users', [\App\Http\Controllers\Web\SuperAdminController::class, 'users'])->name('users');
});

Route::get('/dashboard', function () {
    $user = auth()->user();

    if ($user?->isSuperAdmin()) {
        return redirect()->route('super_admin.dashboard');
    }

    if ($user?->isAdmin()) {
        return redirect()->route('admin.dashboard');
    }

    return redirect()->route('staff.orders');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// مسار أمان لتوليد وعرض رمز QR تلقائياً عند غياب الملف المادي من التخزين
Route::get('/storage/qr/{restaurantId}/{filename}', function ($restaurantId, $filename) {
    $token = pathinfo($filename, PATHINFO_FILENAME);
    $table = \App\Models\RestaurantTable::withoutGlobalScopes()
        ->where('restaurant_id', $restaurantId)
        ->where('public_token', $token)
        ->firstOrFail();

    $content = app(\App\Services\QrCodeService::class)->getPngContents($table);

    return response($content, 200, [
        'Content-Type' => 'image/png',
        'Cache-Control' => 'public, max-age=86400',
    ]);
})->where('filename', '[A-Za-z0-9_-]+\.png');

require __DIR__.'/auth.php';

