<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Order;
use App\Models\MenuItem;

class AnalyticsController extends ApiController
{
    public function index(Request $request)
    {
        $restaurant = auth()->user()->restaurant;

        if (!$restaurant->features || empty($restaurant->features['analytics'])) {
            return $this->error('Feature Locked', 403);
        }

        $orders = Order::where('restaurant_id', $restaurant->id)->where('status', 'completed');
        
        $totalRevenue = (clone $orders)->sum('total');
        $totalOrdersCount = (clone $orders)->count();
        $averageOrderValue = $totalOrdersCount > 0 ? $totalRevenue / $totalOrdersCount : 0;

        $topItems = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('menu_items', 'order_items.menu_item_id', '=', 'menu_items.id')
            ->where('orders.restaurant_id', $restaurant->id)
            ->where('orders.status', 'completed')
            ->select('menu_items.name', DB::raw('SUM(order_items.quantity) as total_quantity'), DB::raw('SUM(order_items.total) as total_revenue'))
            ->groupBy('menu_items.id', 'menu_items.name')
            ->orderByDesc('total_quantity')
            ->limit(5)
            ->get();

        return $this->success([
            'total_revenue' => $totalRevenue,
            'total_orders' => $totalOrdersCount,
            'average_order_value' => $averageOrderValue,
            'top_items' => $topItems,
        ]);
    }
}
