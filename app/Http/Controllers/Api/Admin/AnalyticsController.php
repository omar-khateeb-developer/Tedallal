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

        if (!$restaurant->hasAnalyticsFeature()) {
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
            ->select('menu_items.name', DB::raw('SUM(order_items.quantity) as total_quantity'), DB::raw('SUM(order_items.subtotal) as total_revenue'))
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
            ->select('menu_items.name', DB::raw('SUM(order_items.quantity) as total_quantity'), DB::raw('SUM(order_items.subtotal) as total_revenue'))
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
            ->select('menu_categories.name', DB::raw('SUM(order_items.subtotal) as total_revenue'), DB::raw('SUM(order_items.quantity) as total_quantity'))
            ->groupBy('menu_categories.id', 'menu_categories.name')
            ->orderByDesc('total_revenue')
            ->get();

        // Hourly distribution
        $hourlyDistribution = DB::table('orders')
            
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

    /**
     * Menu Engineering Matrix & Business Intelligence Analysis.
     */
    public function menuEngineering(Request $request)
    {
        $restaurant = Auth::user()->restaurant;

        if (!$restaurant || !$restaurant->hasFeature('menu_engineering')) {
            return response()->json([
                'success' => false,
                'locked' => true,
                'message' => 'ميزة هندسة المنيو والربحية غير مفعّلة لهذا المطعم. يرجى التواصل مع إدارة منصة تدلّل لتفعيلها.',
            ], 403);
        }

        $period = $request->query('period', 'month'); // today, week, month, all

        // 1. All active menu items of this restaurant
        $allItems = DB::table('menu_items')
            ->leftJoin('menu_categories', 'menu_items.category_id', '=', 'menu_categories.id')
            ->where('menu_items.restaurant_id', $restaurant->id)
            ->where('menu_items.is_active', true)
            ->select(
                'menu_items.id',
                'menu_items.name',
                'menu_items.price',
                'menu_categories.name as category_name'
            )
            ->get();

        if ($allItems->isEmpty()) {
            return $this->success([
                'locked' => false,
                'stars' => [],
                'plowhorses' => [],
                'puzzles' => [],
                'dogs' => [],
                'summary' => [
                    'total_items' => 0,
                    'stars_count' => 0,
                    'plowhorses_count' => 0,
                    'puzzles_count' => 0,
                    'dogs_count' => 0,
                    'avg_volume' => 0,
                    'avg_price' => 0,
                ],
                'heatmap' => [],
            ]);
        }

        // 2. Served orders item quantities and revenue
        $salesQuery = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.restaurant_id', $restaurant->id)
            ->where('orders.status', 'served');

        if ($period === 'today') {
            $salesQuery->whereDate('orders.created_at', Carbon::today());
        } elseif ($period === 'week') {
            $salesQuery->where('orders.created_at', '>=', Carbon::now()->startOfWeek());
        } elseif ($period === 'month') {
            $salesQuery->where('orders.created_at', '>=', Carbon::now()->startOfMonth());
        }

        $salesByItem = $salesQuery
            ->select(
                'order_items.menu_item_id',
                DB::raw('SUM(order_items.quantity) as total_sold'),
                DB::raw('SUM(order_items.subtotal) as total_revenue')
            )
            ->groupBy('order_items.menu_item_id')
            ->get()
            ->keyBy('menu_item_id');

        // 3. Compute item metrics & thresholds
        $totalItemsCount = $allItems->count();
        $totalVolume = 0;
        $totalPriceSum = 0;

        $analyzedItems = $allItems->map(function ($item) use ($salesByItem, &$totalVolume, &$totalPriceSum) {
            $sales = $salesByItem->get($item->id);
            $qty = (int) ($sales?->total_sold ?? 0);
            $rev = (float) ($sales?->total_revenue ?? 0);
            $price = (float) $item->price;

            $totalVolume += $qty;
            $totalPriceSum += $price;

            return [
                'id' => $item->id,
                'name' => $item->name,
                'category' => $item->category_name ?? 'عام',
                'price' => $price,
                'total_sold' => $qty,
                'total_revenue' => $rev,
            ];
        });

        $avgVolume = $totalItemsCount > 0 ? ($totalVolume / $totalItemsCount) : 0;
        $avgPrice = $totalItemsCount > 0 ? ($totalPriceSum / $totalItemsCount) : 0;

        $stars = [];
        $plowhorses = [];
        $puzzles = [];
        $dogs = [];

        foreach ($analyzedItems as $it) {
            $isHighVolume = $it['total_sold'] >= $avgVolume;
            $isHighPrice = $it['price'] >= $avgPrice;

            if ($isHighVolume && $isHighPrice) {
                $it['type'] = 'star';
                $it['badge'] = 'نجم 🌟';
                $it['advice'] = 'صنف رابح ومرغوب جداً. حافظ على ثبات جودته واجعله في صدارة المنيو.';
                $stars[] = $it;
            } elseif ($isHighVolume && !$isHighPrice) {
                $it['type'] = 'plowhorse';
                $it['badge'] = 'حصان جر 🐴';
                $it['advice'] = 'إقبال كبير ولكن بسعر/هامش منخفض. يُنصح بزيادة طفيفة في السعر أو تقديم إضافات مدفوعة معه.';
                $plowhorses[] = $it;
            } elseif (!$isHighVolume && $isHighPrice) {
                $it['type'] = 'puzzle';
                $it['badge'] = 'لغز 🧩';
                $it['advice'] = 'عائد ربحي عالٍ لكن إقباله قليل. ضعه كجائزة رابحة في عجلة الحظ أو حسّن صورته في المنيو.';
                $puzzles[] = $it;
            } else {
                $it['type'] = 'dog';
                $it['badge'] = 'راكد 🐕';
                $it['advice'] = 'طلب ضعيف وسعر منخفض. قيّم إمكانية استبداله بوجبة أكثر إقبالاً لتخفيف عبء المطبخ.';
                $dogs[] = $it;
            }
        }

        // 4. Peak Hours Heatmap (Day of Week x Hour)
        $driver = DB::getDriverName();
        $dayExpr = $driver === 'sqlite' ? "CAST(strftime('%w', created_at) AS INTEGER) + 1" : "DAYOFWEEK(created_at)";
        $hourExpr = $driver === 'sqlite' ? "CAST(strftime('%H', created_at) AS INTEGER)" : "HOUR(created_at)";

        $heatmapQuery = DB::table('orders')
            ->where('restaurant_id', $restaurant->id)
            ->where('status', 'served')
            ->when($period === 'week', fn($q) => $q->where('created_at', '>=', Carbon::now()->startOfWeek()))
            ->when($period === 'month', fn($q) => $q->where('created_at', '>=', Carbon::now()->startOfMonth()))
            ->select(
                DB::raw("{$dayExpr} as day_of_week"),
                DB::raw("{$hourExpr} as hour"),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('day_of_week', 'hour')
            ->get();

        return $this->success([
            'locked' => false,
            'stars' => $stars,
            'plowhorses' => $plowhorses,
            'puzzles' => $puzzles,
            'dogs' => $dogs,
            'summary' => [
                'total_items' => $totalItemsCount,
                'stars_count' => count($stars),
                'plowhorses_count' => count($plowhorses),
                'puzzles_count' => count($puzzles),
                'dogs_count' => count($dogs),
                'avg_volume' => round($avgVolume, 1),
                'avg_price' => round($avgPrice, 2),
            ],
            'heatmap' => $heatmapQuery,
        ]);
    }
}
