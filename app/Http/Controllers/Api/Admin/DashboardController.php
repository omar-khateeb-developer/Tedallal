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
        $today = now()->startOfDay();

        $orderStats = Order::query()
            ->where('restaurant_id', $restaurant->id)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $todayRevenue = Order::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('created_at', '>=', $today)
            ->whereNotIn('status', [OrderStatus::Cancelled])
            ->sum('total');

        return response()->json([
            'success' => true,
            'data' => [
                'total_orders' => Order::where('restaurant_id', $restaurant->id)->count(),
                'pending_orders' => $orderStats[OrderStatus::Pending->value] ?? 0,
                'preparing_orders' => ($orderStats[OrderStatus::Preparing->value] ?? 0)
                    + ($orderStats[OrderStatus::Accepted->value] ?? 0),
                'ready_orders' => $orderStats[OrderStatus::Ready->value] ?? 0,
                'served_orders' => $orderStats[OrderStatus::Served->value] ?? 0,
                'today_revenue' => number_format((float) $todayRevenue, 2, '.', ''),
                'active_tables' => TableSession::query()
                    ->where('status', 'active')
                    ->whereHas('table', fn ($q) => $q->where('restaurant_id', $restaurant->id))
                    ->count(),
                'unavailable_menu_items' => MenuItem::query()
                    ->where('restaurant_id', $restaurant->id)
                    ->where(fn ($q) => $q->where('is_available', false)->orWhere('is_active', false))
                    ->count(),
            ],
        ]);
    }
}
