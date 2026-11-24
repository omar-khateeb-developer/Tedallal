<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Api\ApiController;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use App\Services\RestaurantContext;
use App\Support\MoneyCalculator;
use Illuminate\Support\Facades\DB;

class DashboardController extends ApiController
{
    public function __construct(
        private readonly RestaurantContext $restaurantContext,
    ) {}

    public function stats()
    {
        $restaurant = $this->restaurantContext->current();
        $restaurantId = $restaurant->id;
        $today = now()->startOfDay();

        $orderStats = Order::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        // القاعدة المحاسبية الموحدة: يعترف بإيراد الطلبات المكتملة (served) أو المسددة غير الملغاة
        $recognizedTotals = Order::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('created_at', '>=', $today)
            ->where(function ($q) {
                $q->where('status', OrderStatus::Served->value)
                  ->orWhere(function ($q2) {
                      $q2->whereNotNull('paid_at')
                         ->where('status', '!=', OrderStatus::Cancelled->value);
                  });
            })
            ->pluck('total');

        $todayRevenue = '0.00';
        foreach ($recognizedTotals as $amt) {
            $todayRevenue = MoneyCalculator::add($todayRevenue, number_format((float) $amt, 2, '.', ''));
        }

        return response()->json([
            'success' => true,
            'data' => [
                'total_orders' => Order::withoutGlobalScopes()->where('restaurant_id', $restaurantId)->count(),
                'pending_orders' => $orderStats[OrderStatus::Pending->value] ?? 0,
                'preparing_orders' => ($orderStats[OrderStatus::Preparing->value] ?? 0)
                    + ($orderStats[OrderStatus::Accepted->value] ?? 0),
                'ready_orders' => $orderStats[OrderStatus::Ready->value] ?? 0,
                'served_orders' => $orderStats[OrderStatus::Served->value] ?? 0,
                'today_revenue' => $todayRevenue,
                'active_tables' => TableSession::query()
                    ->where('status', 'active')
                    ->whereHas('table', fn($q) => $q->where('restaurant_id', $restaurantId)->dineIn())
                    ->count(),
                'unavailable_menu_items' => MenuItem::withoutGlobalScopes()
                    ->where('restaurant_id', $restaurantId)
                    ->where(fn ($q) => $q->where('is_available', false)->orWhere('is_active', false))
                    ->count(),
            ],
        ]);
    }
}
