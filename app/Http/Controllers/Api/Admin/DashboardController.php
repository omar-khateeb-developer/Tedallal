<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Api\ApiController;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use App\Services\RestaurantContext;
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

        $todayRevenue = Order::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('created_at', '>=', $today)
            ->whereNotIn('status', [OrderStatus::Cancelled])
            ->sum('total');

        return response()->json([
            'success' => true,
            'data' => [
                'total_orders' => Order::withoutGlobalScopes()->where('restaurant_id', $restaurantId)->count(),
                'pending_orders' => $orderStats[OrderStatus::Pending->value] ?? 0,
                'preparing_orders' => ($orderStats[OrderStatus::Preparing->value] ?? 0)
                    + ($orderStats[OrderStatus::Accepted->value] ?? 0),
                'ready_orders' => $orderStats[OrderStatus::Ready->value] ?? 0,
                'served_orders' => $orderStats[OrderStatus::Served->value] ?? 0,
                'today_revenue' => number_format((float) $todayRevenue, 2, '.', ''),
                'active_tables' => TableSession::query()
                    ->where('status', 'active')
                    ->whereHas('table', fn($q) => $q->where('restaurant_id', $restaurantId))
                    ->count(),
                'unavailable_menu_items' => MenuItem::withoutGlobalScopes()
                    ->where('restaurant_id', $restaurantId)
                    ->where(fn ($q) => $q->where('is_available', false)->orWhere('is_active', false))
                    ->count(),
            ],
        ]);
    }
}
