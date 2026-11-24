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

Route::prefix('public')->middleware('throttle:public-api')->group(function () {
    Route::get('tables/{token}', [TableController::class, 'show']);
    Route::post('tables/{token}/call-waiter', [TableController::class, 'callWaiter']);
    Route::post('tables/{token}/request-bill', [TableController::class, 'requestBill']);
    Route::post('tables/{token}/cancel-service-call', [TableController::class, 'cancelServiceCall']);
    Route::get('tables/{token}/service-status', [TableController::class, 'getServiceStatus']);
    Route::post('sessions', [SessionController::class, 'store'])->middleware('throttle:public-sessions');
    Route::get('sessions/{sessionToken}', [SessionController::class, 'show']);
    Route::get('menu', [MenuController::class, 'index']);
    Route::get('menu/categories', [MenuController::class, 'categories']);

    // مستشار تدلّل الذكي للأعمال (AI Restaurant Business Consultant)
    Route::get('ai-consultant/status', [\App\Http\Controllers\Api\Public\AiConsultantController::class, 'status']);
    Route::post('ai-consultant/chat', [\App\Http\Controllers\Api\Public\AiConsultantController::class, 'chat'])->middleware('throttle:25,1');

    // VIP Table Reservation APIs (Public Engine)
    Route::get('restaurants/{slug}/booking-availability', [\App\Http\Controllers\Web\Public\TableBookingController::class, 'getAvailability']);
    Route::post('restaurants/{slug}/reservations/hold', [\App\Http\Controllers\Web\Public\TableBookingController::class, 'requestHold'])->middleware('throttle:10,5');
    Route::post('restaurants/{slug}/reservations/verify-otp', [\App\Http\Controllers\Web\Public\TableBookingController::class, 'verifyOtp'])->middleware('throttle:15,5');

    // Smart Digital Waitlist APIs (Public Engine)
    Route::get('waitlist/{uuid}/status', [\App\Http\Controllers\Web\Public\WaitlistController::class, 'liveStatus']);
    Route::post('waitlist/{uuid}/cancel', [\App\Http\Controllers\Web\Public\WaitlistController::class, 'cancel']);
    Route::post('restaurants/{slug}/waitlist/join', [\App\Http\Controllers\Web\Public\WaitlistController::class, 'storePublic'])->middleware('throttle:10,5');

    Route::middleware(ValidateTableSession::class)->group(function () {
        Route::get('cart', [CartController::class, 'index']);
        Route::post('cart/items', [CartController::class, 'store']);
        Route::patch('cart/items/{cartItem}', [CartController::class, 'update']);
        Route::delete('cart/items/{cartItem}', [CartController::class, 'destroy']);
        Route::delete('cart', [CartController::class, 'clear']);
        Route::post('promo-codes/validate', [PromoCodeController::class, 'validateCode'])->middleware('throttle:public-promo-codes');
        Route::post('promo-codes/force-activate', [PromoCodeController::class, 'forceActivate']);
        // الويتر الذكي (AI Smart Waiter)
        Route::get('ai-waiter/status', [\App\Http\Controllers\Api\Public\AiWaiterController::class, 'status']);
        Route::post('ai-waiter/chat', [\App\Http\Controllers\Api\Public\AiWaiterController::class, 'chat'])->middleware('throttle:30,1');
        Route::post('orders', [OrderController::class, 'store'])->middleware('throttle:public-orders');
        Route::get('orders/{orderNumber}', [OrderController::class, 'show']);
    });
});

// WhatsApp Gateway Internal Webhook (Secured with X-Gateway-Secret)
Route::post('whatsapp/webhook', [\App\Http\Controllers\Api\Public\WhatsAppWebhookController::class, 'handle']);

Route::prefix('admin')->middleware(['auth:sanctum'])->group(function () {
    Route::middleware('role:admin')->group(function () {
        Route::get('dashboard/stats', [AdminDashboardController::class, 'stats']);
        Route::get('restaurant', [RestaurantController::class, 'show']);
        Route::patch('restaurant', [RestaurantController::class, 'update']);
        Route::post('restaurant/reset-test-data', [RestaurantController::class, 'resetTestData']);
        Route::get('users', [UserController::class, 'index']);
        Route::post('users', [UserController::class, 'store']);
        Route::patch('users/{user}', [UserController::class, 'update']);
        Route::delete('users/{user}', [UserController::class, 'destroy']);
        Route::get('users/{user}/logs', [UserController::class, 'logs']);
        Route::get('analytics', [\App\Http\Controllers\Api\Admin\AnalyticsController::class, 'index']);
        Route::get('analytics/menu-engineering', [\App\Http\Controllers\Api\Admin\AnalyticsController::class, 'menuEngineering']);
        Route::get('insights', [\App\Http\Controllers\Api\Admin\InsightsController::class, 'index']);
        Route::get('reports/sales', [\App\Http\Controllers\Api\Admin\SalesReportController::class, 'index']);
        Route::get('reports/sales/export', [\App\Http\Controllers\Api\Admin\SalesReportController::class, 'export']);
        Route::get('reports/accounting-voucher', [\App\Http\Controllers\Api\Admin\SalesReportController::class, 'getAccountingVoucher']);
        Route::get('reports/accounting-voucher/export', [\App\Http\Controllers\Api\Admin\SalesReportController::class, 'exportAccountingVoucher']);
        Route::apiResource('promo-codes', AdminPromoCodeController::class)->parameters([
            'promo-codes' => 'promoCode',
        ]);
        Route::get('customers', [\App\Http\Controllers\Api\Admin\CustomerCrmController::class, 'index']);
        Route::post('customers', [\App\Http\Controllers\Api\Admin\CustomerCrmController::class, 'store']);
        Route::patch('customers/{customerId}', [\App\Http\Controllers\Api\Admin\CustomerCrmController::class, 'update']);
        Route::delete('customers/{customerId}', [\App\Http\Controllers\Api\Admin\CustomerCrmController::class, 'destroy']);
        Route::post('customers/{customerId}/mark-reminded', [\App\Http\Controllers\Api\Admin\CustomerCrmController::class, 'markReminded']);
        Route::post('customers/{customerId}/send-campaign', [\App\Http\Controllers\Api\Admin\CustomerCrmController::class, 'sendCampaign']);
        Route::post('crm-settings', [\App\Http\Controllers\Api\Admin\CustomerCrmController::class, 'updateSettings']);
        Route::get('whatsapp/status', [\App\Http\Controllers\Api\Admin\WhatsAppController::class, 'status']);
        Route::post('whatsapp/connect', [\App\Http\Controllers\Api\Admin\WhatsAppController::class, 'connect']);
        Route::post('whatsapp/disconnect', [\App\Http\Controllers\Api\Admin\WhatsAppController::class, 'disconnect']);
        Route::post('whatsapp/settings', [\App\Http\Controllers\Api\Admin\WhatsAppController::class, 'updateSettings']);
        Route::post('whatsapp/send-test', [\App\Http\Controllers\Api\Admin\WhatsAppController::class, 'sendTest']);
        Route::post('customers/{customerId}/send-direct-reminder', [\App\Http\Controllers\Api\Admin\WhatsAppController::class, 'sendDirectReminder']);
    });

    Route::middleware('permission:manage_orders')->group(function () {
        Route::get('orders', [AdminOrderController::class, 'index']);
        Route::get('orders/{order}', [AdminOrderController::class, 'show']);
        Route::patch('orders/{order}/status', [AdminOrderController::class, 'updateStatus']);

        // نداءات الخدمة الصامتة للطاولات وطلب الفاتورة
        Route::get('service-calls', [\App\Http\Controllers\Api\Admin\TableServiceCallController::class, 'index']);
        Route::post('service-calls/{id}/attend', [\App\Http\Controllers\Api\Admin\TableServiceCallController::class, 'attend']);
    });

    Route::middleware('permission:manage_tables')->group(function () {
        Route::get('tables', [AdminTableController::class, 'index']);
        Route::post('tables', [AdminTableController::class, 'store']);
        Route::post('tables/batch-layout', [AdminTableController::class, 'batchLayout']);
        Route::patch('tables/{table}', [AdminTableController::class, 'update']);
        Route::delete('tables/{table}', [AdminTableController::class, 'destroy']);
        Route::post('tables/{table}/regenerate-token', [AdminTableController::class, 'regenerateToken']);
        Route::post('tables/{table}/close-session', [AdminTableController::class, 'closeActiveSession']);
        Route::patch('tables/{table}/stage', [AdminTableController::class, 'updateStage']);
        Route::get('tables/{table}/qr', [AdminTableController::class, 'qr']);

        // VIP Table Reservations Management
        Route::get('reservations', [\App\Http\Controllers\Api\Admin\ReservationController::class, 'index']);
        Route::patch('reservations/{reservation}/status', [\App\Http\Controllers\Api\Admin\ReservationController::class, 'updateStatus']);

        // Smart Digital Waitlist Management (WhatsApp Powered)
        Route::get('waitlist', [\App\Http\Controllers\Api\Admin\WaitlistController::class, 'index']);
        Route::post('waitlist', [\App\Http\Controllers\Api\Admin\WaitlistController::class, 'store']);
        Route::patch('waitlist/{entry}/notify', [\App\Http\Controllers\Api\Admin\WaitlistController::class, 'notifyGuest']);
        Route::patch('waitlist/{entry}/seat', [\App\Http\Controllers\Api\Admin\WaitlistController::class, 'seatGuest']);
        Route::patch('waitlist/{entry}/status', [\App\Http\Controllers\Api\Admin\WaitlistController::class, 'updateStatus']);
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
    Route::get('users', [\App\Http\Controllers\Api\SuperAdmin\UserController::class, 'index']);
    Route::post('users', [\App\Http\Controllers\Api\SuperAdmin\UserController::class, 'store']);
    Route::patch('users/{user}', [\App\Http\Controllers\Api\SuperAdmin\UserController::class, 'update']);
    Route::delete('users/{user}', [\App\Http\Controllers\Api\SuperAdmin\UserController::class, 'destroy']);
    Route::get('users/{user}/logs', [\App\Http\Controllers\Api\SuperAdmin\UserController::class, 'logs']);

    // وضع الصيانة
    Route::get('maintenance', [\App\Http\Controllers\Api\SuperAdmin\MaintenanceController::class, 'status']);
    Route::post('maintenance/toggle', [\App\Http\Controllers\Api\SuperAdmin\MaintenanceController::class, 'toggle']);

    // وضع التجريب وفحص الكوبونات
    Route::get('promo-testing', [\App\Http\Controllers\Api\SuperAdmin\PromoTestingController::class, 'status']);
    Route::post('promo-testing/toggle', [\App\Http\Controllers\Api\SuperAdmin\PromoTestingController::class, 'toggle']);
    Route::post('promo-testing/diagnose', [\App\Http\Controllers\Api\SuperAdmin\PromoTestingController::class, 'diagnose']);
    Route::post('promo-testing/force-activate', [\App\Http\Controllers\Api\SuperAdmin\PromoTestingController::class, 'forceActivate']);

    // إدارة طلبات الاستشارة والعملاء المحتملين (Leads CRM)
    Route::get('leads', [\App\Http\Controllers\Api\SuperAdmin\ConsultationLeadController::class, 'index']);
    Route::patch('leads/{lead}', [\App\Http\Controllers\Api\SuperAdmin\ConsultationLeadController::class, 'update']);
    Route::post('leads/{lead}/toggle-ai', [\App\Http\Controllers\Api\SuperAdmin\ConsultationLeadController::class, 'toggleAi']);
    Route::post('leads/{lead}/provision', [\App\Http\Controllers\Api\SuperAdmin\ConsultationLeadController::class, 'provision']);
    Route::get('leads/{lead}/pitch-card', [\App\Http\Controllers\Api\SuperAdmin\ConsultationLeadController::class, 'pitchCard']);
    Route::delete('leads/{lead}', [\App\Http\Controllers\Api\SuperAdmin\ConsultationLeadController::class, 'destroy']);

    // واتساب إدارة المنصة والمساعد التنفيذي الذكي (Platform Owner WhatsApp Gateway)
    Route::get('platform-whatsapp/status', [\App\Http\Controllers\Api\SuperAdmin\PlatformWhatsAppController::class, 'status']);
    Route::post('platform-whatsapp/connect', [\App\Http\Controllers\Api\SuperAdmin\PlatformWhatsAppController::class, 'connect']);
    Route::post('platform-whatsapp/disconnect', [\App\Http\Controllers\Api\SuperAdmin\PlatformWhatsAppController::class, 'disconnect']);
    Route::post('platform-whatsapp/settings', [\App\Http\Controllers\Api\SuperAdmin\PlatformWhatsAppController::class, 'updateSettings']);
    Route::post('platform-whatsapp/send-test', [\App\Http\Controllers\Api\SuperAdmin\PlatformWhatsAppController::class, 'sendTest']);

    // إدارة النسخ الاحتياطي للنظام (Automated Backups)
    Route::get('backups', [\App\Http\Controllers\Api\SuperAdmin\BackupController::class, 'index']);
    Route::post('backups/create', [\App\Http\Controllers\Api\SuperAdmin\BackupController::class, 'create']);
    Route::get('backups/download/{filename}', [\App\Http\Controllers\Api\SuperAdmin\BackupController::class, 'download']);
    Route::delete('backups/{filename}', [\App\Http\Controllers\Api\SuperAdmin\BackupController::class, 'destroy']);
});

