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

Route::middleware(['auth', 'verified', 'role:admin,staff'])->group(function () {
    Route::get('/staff/orders', [StaffController::class, 'orders'])->name('staff.orders');
});

Route::middleware(['auth', 'verified', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/orders', [AdminDashboardController::class, 'orders'])->name('orders');
    Route::get('/tables', [AdminDashboardController::class, 'tables'])->name('tables');
    Route::get('/tables/print', TablePrintController::class)->name('tables.print');
    Route::get('/settings', [AdminDashboardController::class, 'settings'])->name('settings');
    Route::get('/menu/categories', [AdminDashboardController::class, 'menuCategories'])->name('menu.categories');
    Route::get('/menu/items', [AdminDashboardController::class, 'menuItems'])->name('menu.items');
});

Route::get('/dashboard', function () {
    $user = auth()->user();

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

require __DIR__.'/auth.php';
