<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\Order;
use Carbon\Carbon;

class AnalyticsController extends ApiController
{
    public function index(Request $request)
    {
        $restaurant = Auth::user()->restaurant;

        if (!$restaurant->features || empty($restaurant->features['analytics'])) {
            return $this->error('Feature Locked', 403);
        }

        $period = $request->query('period', 'all'); // today, week, month, all

        $query = Order::where('restaurant_id', $restaurant->id);

        if ($period === 'today') {
            $query->whereDate('created_at', Carbon::today());
        } elseif ($period === 'week') {
            $query->where('created_at', '>=', Carbon::now()->startOfWeek());
        } elseif ($period === 'month') {
            $query->where('created_at', '>=', Carbon::now()->startOfMonth());
        }

        $totalOrdersCount = (clone $query)->count();
        $cancelledOrdersCount = (clone $query)->where('status', 'cancelled')->count();
        $cancellationRate = $totalOrdersCount > 0 ? ($cancelledOrdersCount / $totalOrdersCount) * 100 : 0;

        // Served orders for revenue and item stats
        $servedOrders = (clone $query)->where('status', 'served');
        $totalRevenue = (clone $servedOrders)->sum('total');
        $servedCount = (clone $servedOrders)->count();
        $averageOrderValue = $servedCount > 0 ? $totalRevenue / $servedCount : 0;

        $servedQueryForJoin = DB::table('orders')
            ->where('restaurant_id', $restaurant->id)
            ->where('status', 'served');

        if ($period === 'today') {
            $servedQueryForJoin->whereDate('created_at', Carbon::today());
        } elseif ($period === 'week') {
            $servedQueryForJoin->where('created_at', '>=', Carbon::now()->startOfWeek());
        } elseif ($period === 'month') {
            $servedQueryForJoin->where('created_at', '>=', Carbon::now()->startOfMonth());
        }

        // Top items
        $topItems = DB::table('order_items')
            ->joinSub($servedQueryForJoin, 'o', function ($join) {
                $join->on('order_items.order_id', '=', 'o.id');
            })
            ->join('menu_items', 'order_items.menu_item_id', '=', 'menu_items.id')
            ->select('menu_items.name', DB::raw('SUM(order_items.quantity) as total_quantity'), DB::raw('SUM(order_items.total) as total_revenue'))
            ->groupBy('menu_items.id', 'menu_items.name')
            ->orderByDesc('total_quantity')
            ->limit(5)
            ->get();

        // Worst items
        $worstItems = DB::table('order_items')
            ->joinSub($servedQueryForJoin, 'o', function ($join) {
                $join->on('order_items.order_id', '=', 'o.id');
            })
            ->join('menu_items', 'order_items.menu_item_id', '=', 'menu_items.id')
            ->select('menu_items.name', DB::raw('SUM(order_items.quantity) as total_quantity'), DB::raw('SUM(order_items.total) as total_revenue'))
            ->groupBy('menu_items.id', 'menu_items.name')
            ->orderBy('total_quantity')
            ->limit(5)
            ->get();
            
        // Category breakdown
        $categoryBreakdown = DB::table('order_items')
            ->joinSub($servedQueryForJoin, 'o', function ($join) {
                $join->on('order_items.order_id', '=', 'o.id');
            })
            ->join('menu_items', 'order_items.menu_item_id', '=', 'menu_items.id')
            ->join('menu_categories', 'menu_items.category_id', '=', 'menu_categories.id')
            ->select('menu_categories.name', DB::raw('SUM(order_items.total) as total_revenue'), DB::raw('SUM(order_items.quantity) as total_quantity'))
            ->groupBy('menu_categories.id', 'menu_categories.name')
            ->orderByDesc('total_revenue')
            ->get();

        // Hourly distribution
        $hourlyDistribution = DB::table('orders')
            ->where('restaurant_id', $restaurant->id)
            ->where('status', 'served')
            ->when($period === 'today', fn($q) => $q->whereDate('created_at', Carbon::today()))
            ->when($period === 'week', fn($q) => $q->where('created_at', '>=', Carbon::now()->startOfWeek()))
            ->when($period === 'month', fn($q) => $q->where('created_at', '>=', Carbon::now()->startOfMonth()))
            ->select(DB::raw('HOUR(created_at) as hour'), DB::raw('COUNT(*) as total_orders'))
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();
            
        $formattedHourly = [];
        for ($i = 0; $i < 24; $i++) {
            $formattedHourly[] = [
                'hour' => $i,
                'total_orders' => $hourlyDistribution->firstWhere('hour', $i)?->total_orders ?? 0,
            ];
        }
        
        // Table performance
        $tablePerformance = DB::table('orders')
            ->where('orders.restaurant_id', $restaurant->id)
            ->where('orders.status', 'served')
            ->whereNotNull('orders.table_id')
            ->join('restaurant_tables', 'orders.table_id', '=', 'restaurant_tables.id')
            ->when($period === 'today', fn($q) => $q->whereDate('orders.created_at', Carbon::today()))
            ->when($period === 'week', fn($q) => $q->where('orders.created_at', '>=', Carbon::now()->startOfWeek()))
            ->when($period === 'month', fn($q) => $q->where('orders.created_at', '>=', Carbon::now()->startOfMonth()))
            ->select('restaurant_tables.name', DB::raw('SUM(orders.total) as total_revenue'), DB::raw('COUNT(orders.id) as total_orders'))
            ->groupBy('restaurant_tables.id', 'restaurant_tables.name')
            ->orderByDesc('total_revenue')
            ->limit(5)
            ->get();

        return $this->success([
            'total_revenue' => $totalRevenue,
            'total_orders' => $totalOrdersCount,
            'average_order_value' => $averageOrderValue,
            'cancellation_rate' => $cancellationRate,
            'top_items' => $topItems,
            'worst_items' => $worstItems,
            'category_breakdown' => $categoryBreakdown,
            'hourly_distribution' => $formattedHourly,
            'table_performance' => $tablePerformance,
        ]);
    }
}
