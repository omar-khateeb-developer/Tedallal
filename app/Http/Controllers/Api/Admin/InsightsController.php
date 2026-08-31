<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\Order;
use Carbon\Carbon;

class InsightsController extends ApiController
{
    public function index(Request $request)
    {
        $restaurant = Auth::user()->restaurant;

        if (!$restaurant->features || empty($restaurant->features['analytics'])) {
            return $this->error('Feature Locked', 403);
        }

        // We will generate rule-based insights based on recent data (e.g., last 30 days)
        $thirtyDaysAgo = Carbon::now()->subDays(30);

        $insights = [];

        $orders = Order::where('restaurant_id', $restaurant->id)
            ->where('created_at', '>=', $thirtyDaysAgo);

        $totalOrdersCount = (clone $orders)->count();
        $cancelledOrdersCount = (clone $orders)->where('status', 'cancelled')->count();
        
        $cancellationRate = $totalOrdersCount > 0 ? ($cancelledOrdersCount / $totalOrdersCount) * 100 : 0;

        if ($cancellationRate > 15) {
            $insights[] = [
                'type' => 'warning',
                'title' => 'معدل إلغاء مرتفع',
                'description' => 'نسبة إلغاء الطلبات في آخر 30 يوماً تجاوزت 15%. يُنصح بمراجعة أسباب الإلغاء مع طاقم العمل.'
            ];
        }

        $servedOrdersQuery = DB::table('orders')
            ->where('restaurant_id', $restaurant->id)
            ->where('status', 'served')
            ->where('created_at', '>=', $thirtyDaysAgo);
            
        $servedCount = $servedOrdersQuery->count();

        if ($servedCount > 0) {
            // Find worst performing items (available items that are rarely ordered)
            $worstItems = DB::table('order_items')
                ->joinSub($servedOrdersQuery, 'o', function ($join) {
                    $join->on('order_items.order_id', '=', 'o.id');
                })
                ->join('menu_items', 'order_items.menu_item_id', '=', 'menu_items.id')
                ->where('menu_items.is_active', true)
                ->where('menu_items.is_available', true)
                ->select('menu_items.name', DB::raw('SUM(order_items.quantity) as total_quantity'))
                ->groupBy('menu_items.id', 'menu_items.name')
                ->having('total_quantity', '<', 5) // Less than 5 orders in 30 days
                ->orderBy('total_quantity')
                ->limit(3)
                ->get();

            if ($worstItems->isNotEmpty()) {
                $itemNames = $worstItems->pluck('name')->implode('، ');
                $insights[] = [
                    'type' => 'info',
                    'title' => 'أصناف بطيئة الحركة',
                    'description' => "الأصناف التالية مبيعاتها ضعيفة جداً (أقل من 5 طلبات): {$itemNames}. فكر في عمل عروض عليها أو تغيير وصفها."
                ];
            }
            
            // Peak hours insight
            $peakHours = DB::table('orders')
                ->where('restaurant_id', $restaurant->id)
                ->where('status', 'served')
                ->where('created_at', '>=', $thirtyDaysAgo)
                ->select(DB::raw('HOUR(created_at) as hour'), DB::raw('COUNT(*) as total'))
                ->groupBy('hour')
                ->orderByDesc('total')
                ->limit(2)
                ->get();
                
            if ($peakHours->count() >= 1) {
                $hoursStr = $peakHours->map(function($h) {
                    $period = $h->hour >= 12 ? 'مساءً' : 'صباحاً';
                    $hour12 = $h->hour > 12 ? $h->hour - 12 : ($h->hour == 0 ? 12 : $h->hour);
                    return "{$hour12}:00 {$period}";
                })->implode(' و ');
                
                $insights[] = [
                    'type' => 'success',
                    'title' => 'أوقات الذروة المربحة',
                    'description' => "أكثر الطلبات تأتي في الأوقات التالية: {$hoursStr}. تأكد من جاهزية طاقم العمل في هذه الأوقات لزيادة الأرباح."
                ];
            }
        }
        
        if (empty($insights)) {
            $insights[] = [
                'type' => 'info',
                'title' => 'الأمور مستقرة',
                'description' => 'بيانات مطعمك في آخر 30 يوماً تبدو مستقرة ولا توجد ملاحظات تستدعي التدخل العاجل.'
            ];
        }

        return $this->success($insights);
    }
}
