<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Api\ApiController;
use App\Models\Order;
use App\Services\RestaurantContext;
use App\Support\MoneyCalculator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesReportController extends ApiController
{
    public function __construct(
        private readonly RestaurantContext $restaurantContext,
    ) {}

    public function index(Request $request)
    {
        $restaurant = $this->restaurantContext->current();
        $restaurantId = $restaurant->id;

        $viewType = $request->query('view', 'daily'); // 'daily', 'monthly', 'yearly', 'custom'
        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');

        // القاعدة المحاسبية الدقيقة:
        // المبيعات المعترف بها هي الطلبات المكتملة (served) أو المسددة (paid_at is not null وليست ملغاة)
        // الطلبات الملغاة (cancelled) يتم حصرها منفصلة كفاقد مبيعات ولا تدخل في صافي الإيرادات نهائياً.
        $baseValidQuery = Order::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where(function ($q) {
                $q->where('status', OrderStatus::Served->value)
                  ->orWhere(function ($q2) {
                      $q2->whereNotNull('paid_at')
                         ->where('status', '!=', OrderStatus::Cancelled->value);
                  });
            });

        $cancelledQuery = Order::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('status', OrderStatus::Cancelled->value);

        if ($viewType === 'daily') {
            return $this->getDailyReport($baseValidQuery, $cancelledQuery, $restaurant, $year, $month);
        } elseif ($viewType === 'monthly') {
            return $this->getMonthlyReport($baseValidQuery, $cancelledQuery, $restaurant, $year);
        } elseif ($viewType === 'yearly') {
            return $this->getYearlyReport($baseValidQuery, $cancelledQuery, $restaurant);
        } else {
            return $this->getCustomReport($baseValidQuery, $cancelledQuery, $restaurant, $fromDate, $toDate);
        }
    }

    private function getDailyReport($baseQuery, $cancelledQuery, $restaurant, int $year, int $month)
    {
        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $validOrders = (clone $baseQuery)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get(['id', 'subtotal', 'discount_amount', 'total', 'service_type', 'created_at']);

        $cancelledOrders = (clone $cancelledQuery)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get(['id', 'total']);

        // تجميع الحركات اليومية بدقة محاسبية 0% خطأ
        $daysCount = $startDate->daysInMonth;
        $arabicDayNames = [
            'Saturday'  => 'السبت',
            'Sunday'    => 'الأحد',
            'Monday'    => 'الإثنين',
            'Tuesday'   => 'الثلاثاء',
            'Wednesday' => 'الأربعاء',
            'Thursday'  => 'الخميس',
            'Friday'    => 'الجمعة',
        ];

        $daysBreakdown = [];
        $totalGross = '0.00';
        $totalDiscount = '0.00';
        $totalNet = '0.00';
        $totalOrdersCount = count($validOrders);
        $dineInCount = 0;
        $dineInRevenue = '0.00';
        $takeawayCount = 0;
        $takeawayRevenue = '0.00';

        // تفكيك ومطابقة كل يوم في الشهر
        for ($day = 1; $day <= $daysCount; $day++) {
            $dayCarbon = Carbon::createFromDate($year, $month, $day);
            $dateString = $dayCarbon->toDateString();

            $dayOrders = $validOrders->filter(fn ($o) => $o->created_at->format('Y-m-d') === $dateString);

            $dayGross = '0.00';
            $dayDiscount = '0.00';
            $dayNet = '0.00';
            $dayCount = $dayOrders->count();

            foreach ($dayOrders as $order) {
                $subtotal = number_format((float) $order->subtotal, 2, '.', '');
                $discount = number_format((float) ($order->discount_amount ?? 0), 2, '.', '');
                $net = number_format((float) $order->total, 2, '.', '');

                $dayGross = MoneyCalculator::add($dayGross, $subtotal);
                $dayDiscount = MoneyCalculator::add($dayDiscount, $discount);
                $dayNet = MoneyCalculator::add($dayNet, $net);

                if (($order->service_type ?? 'dine_in') === 'takeaway') {
                    $takeawayCount++;
                    $takeawayRevenue = MoneyCalculator::add($takeawayRevenue, $net);
                } else {
                    $dineInCount++;
                    $dineInRevenue = MoneyCalculator::add($dineInRevenue, $net);
                }
            }

            $totalGross = MoneyCalculator::add($totalGross, $dayGross);
            $totalDiscount = MoneyCalculator::add($totalDiscount, $dayDiscount);
            $totalNet = MoneyCalculator::add($totalNet, $dayNet);

            $daysBreakdown[] = [
                'day_number'   => $day,
                'date'         => $dateString,
                'day_name'     => $arabicDayNames[$dayCarbon->format('l')] ?? $dayCarbon->format('l'),
                'orders_count' => $dayCount,
                'gross_sales'  => $dayGross,
                'discounts'    => $dayDiscount,
                'net_sales'    => $dayNet,
                'aov'          => $dayCount > 0 ? number_format((float) $dayNet / $dayCount, 2, '.', '') : '0.00',
            ];
        }

        $cancelledCount = count($cancelledOrders);
        $cancelledLost = '0.00';
        foreach ($cancelledOrders as $c) {
            $cancelledLost = MoneyCalculator::add($cancelledLost, number_format((float) $c->total, 2, '.', ''));
        }

        return $this->success([
            'period_type'       => 'daily',
            'year'              => $year,
            'month'             => $month,
            'month_label'       => $startDate->translatedFormat('F Y'),
            'currency'          => $restaurant->currency ?? 'SAR',
            'summary'           => [
                'gross_sales'       => $totalGross,
                'total_discounts'   => $totalDiscount,
                'net_sales'         => $totalNet,
                'total_orders'      => $totalOrdersCount,
                'aov'               => $totalOrdersCount > 0 ? number_format((float) $totalNet / $totalOrdersCount, 2, '.', '') : '0.00',
                'dine_in_count'     => $dineInCount,
                'dine_in_revenue'   => $dineInRevenue,
                'takeaway_count'    => $takeawayCount,
                'takeaway_revenue'  => $takeawayRevenue,
                'cancelled_orders'  => $cancelledCount,
                'cancelled_lost'    => $cancelledLost,
            ],
            'records'           => $daysBreakdown,
        ]);
    }

    private function getMonthlyReport($baseQuery, $cancelledQuery, $restaurant, int $year)
    {
        $startDate = Carbon::createFromDate($year, 1, 1)->startOfYear();
        $endDate = Carbon::createFromDate($year, 12, 31)->endOfYear();

        $validOrders = (clone $baseQuery)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get(['id', 'subtotal', 'discount_amount', 'total', 'service_type', 'created_at']);

        $cancelledOrders = (clone $cancelledQuery)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get(['id', 'total']);

        $arabicMonths = [
            1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
            5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
            9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر'
        ];

        $monthsBreakdown = [];
        $totalGross = '0.00';
        $totalDiscount = '0.00';
        $totalNet = '0.00';
        $totalOrdersCount = count($validOrders);
        $previousMonthNet = 0.0;

        for ($m = 1; $m <= 12; $m++) {
            $monthOrders = $validOrders->filter(fn ($o) => (int) $o->created_at->format('n') === $m);

            $mGross = '0.00';
            $mDiscount = '0.00';
            $mNet = '0.00';
            $mCount = $monthOrders->count();

            foreach ($monthOrders as $order) {
                $subtotal = number_format((float) $order->subtotal, 2, '.', '');
                $discount = number_format((float) ($order->discount_amount ?? 0), 2, '.', '');
                $net = number_format((float) $order->total, 2, '.', '');

                $mGross = MoneyCalculator::add($mGross, $subtotal);
                $mDiscount = MoneyCalculator::add($mDiscount, $discount);
                $mNet = MoneyCalculator::add($mNet, $net);
            }

            $totalGross = MoneyCalculator::add($totalGross, $mGross);
            $totalDiscount = MoneyCalculator::add($totalDiscount, $mDiscount);
            $totalNet = MoneyCalculator::add($totalNet, $mNet);

            $currentNetFloat = (float) $mNet;
            $growthPercentage = null;
            if ($previousMonthNet > 0) {
                $growthPercentage = round((($currentNetFloat - $previousMonthNet) / $previousMonthNet) * 100, 1);
            }
            $previousMonthNet = $currentNetFloat;

            $monthsBreakdown[] = [
                'month_number'      => $m,
                'month_name'        => $arabicMonths[$m],
                'orders_count'      => $mCount,
                'gross_sales'       => $mGross,
                'discounts'         => $mDiscount,
                'net_sales'         => $mNet,
                'aov'               => $mCount > 0 ? number_format($currentNetFloat / $mCount, 2, '.', '') : '0.00',
                'growth_percentage' => $growthPercentage,
            ];
        }

        $cancelledCount = count($cancelledOrders);
        $cancelledLost = '0.00';
        foreach ($cancelledOrders as $c) {
            $cancelledLost = MoneyCalculator::add($cancelledLost, number_format((float) $c->total, 2, '.', ''));
        }

        return $this->success([
            'period_type'       => 'monthly',
            'year'              => $year,
            'currency'          => $restaurant->currency ?? 'SAR',
            'summary'           => [
                'gross_sales'       => $totalGross,
                'total_discounts'   => $totalDiscount,
                'net_sales'         => $totalNet,
                'total_orders'      => $totalOrdersCount,
                'aov'               => $totalOrdersCount > 0 ? number_format((float) $totalNet / $totalOrdersCount, 2, '.', '') : '0.00',
                'cancelled_orders'  => $cancelledCount,
                'cancelled_lost'    => $cancelledLost,
            ],
            'records'           => $monthsBreakdown,
        ]);
    }

    private function getYearlyReport($baseQuery, $cancelledQuery, $restaurant)
    {
        $allOrders = (clone $baseQuery)->get(['id', 'subtotal', 'discount_amount', 'total', 'created_at']);
        $cancelledOrders = (clone $cancelledQuery)->get(['id', 'total']);

        $yearsGrouped = $allOrders->groupBy(fn ($o) => $o->created_at->format('Y'))->sortKeysDesc();

        $yearsBreakdown = [];
        $totalGross = '0.00';
        $totalDiscount = '0.00';
        $totalNet = '0.00';
        $totalOrdersCount = count($allOrders);

        foreach ($yearsGrouped as $year => $orders) {
            $yGross = '0.00';
            $yDiscount = '0.00';
            $yNet = '0.00';
            $yCount = $orders->count();

            foreach ($orders as $order) {
                $subtotal = number_format((float) $order->subtotal, 2, '.', '');
                $discount = number_format((float) ($order->discount_amount ?? 0), 2, '.', '');
                $net = number_format((float) $order->total, 2, '.', '');

                $yGross = MoneyCalculator::add($yGross, $subtotal);
                $yDiscount = MoneyCalculator::add($yDiscount, $discount);
                $yNet = MoneyCalculator::add($yNet, $net);
            }

            $totalGross = MoneyCalculator::add($totalGross, $yGross);
            $totalDiscount = MoneyCalculator::add($totalDiscount, $yDiscount);
            $totalNet = MoneyCalculator::add($totalNet, $yNet);

            $yearsBreakdown[] = [
                'year'         => (int) $year,
                'orders_count' => $yCount,
                'gross_sales'  => $yGross,
                'discounts'    => $yDiscount,
                'net_sales'    => $yNet,
                'aov'          => $yCount > 0 ? number_format((float) $yNet / $yCount, 2, '.', '') : '0.00',
            ];
        }

        $cancelledCount = count($cancelledOrders);
        $cancelledLost = '0.00';
        foreach ($cancelledOrders as $c) {
            $cancelledLost = MoneyCalculator::add($cancelledLost, number_format((float) $c->total, 2, '.', ''));
        }

        return $this->success([
            'period_type'       => 'yearly',
            'currency'          => $restaurant->currency ?? 'SAR',
            'summary'           => [
                'gross_sales'       => $totalGross,
                'total_discounts'   => $totalDiscount,
                'net_sales'         => $totalNet,
                'total_orders'      => $totalOrdersCount,
                'aov'               => $totalOrdersCount > 0 ? number_format((float) $totalNet / $totalOrdersCount, 2, '.', '') : '0.00',
                'cancelled_orders'  => $cancelledCount,
                'cancelled_lost'    => $cancelledLost,
            ],
            'records'           => $yearsBreakdown,
        ]);
    }

    private function getCustomReport($baseQuery, $cancelledQuery, $restaurant, ?string $from, ?string $to)
    {
        $startDate = $from ? Carbon::parse($from)->startOfDay() : Carbon::now()->subDays(30)->startOfDay();
        $endDate = $to ? Carbon::parse($to)->endOfDay() : Carbon::now()->endOfDay();

        $validOrders = (clone $baseQuery)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get(['id', 'subtotal', 'discount_amount', 'total', 'service_type', 'created_at']);

        $cancelledOrders = (clone $cancelledQuery)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get(['id', 'total']);

        $totalGross = '0.00';
        $totalDiscount = '0.00';
        $totalNet = '0.00';
        $totalOrdersCount = count($validOrders);
        $dineInCount = 0;
        $dineInRevenue = '0.00';
        $takeawayCount = 0;
        $takeawayRevenue = '0.00';

        foreach ($validOrders as $order) {
            $subtotal = number_format((float) $order->subtotal, 2, '.', '');
            $discount = number_format((float) ($order->discount_amount ?? 0), 2, '.', '');
            $net = number_format((float) $order->total, 2, '.', '');

            $totalGross = MoneyCalculator::add($totalGross, $subtotal);
            $totalDiscount = MoneyCalculator::add($totalDiscount, $discount);
            $totalNet = MoneyCalculator::add($totalNet, $net);

            if (($order->service_type ?? 'dine_in') === 'takeaway') {
                $takeawayCount++;
                $takeawayRevenue = MoneyCalculator::add($takeawayRevenue, $net);
            } else {
                $dineInCount++;
                $dineInRevenue = MoneyCalculator::add($dineInRevenue, $net);
            }
        }

        $cancelledCount = count($cancelledOrders);
        $cancelledLost = '0.00';
        foreach ($cancelledOrders as $c) {
            $cancelledLost = MoneyCalculator::add($cancelledLost, number_format((float) $c->total, 2, '.', ''));
        }

        return $this->success([
            'period_type'       => 'custom',
            'from_date'         => $startDate->toDateString(),
            'to_date'           => $endDate->toDateString(),
            'currency'          => $restaurant->currency ?? 'SAR',
            'summary'           => [
                'gross_sales'       => $totalGross,
                'total_discounts'   => $totalDiscount,
                'net_sales'         => $totalNet,
                'total_orders'      => $totalOrdersCount,
                'aov'               => $totalOrdersCount > 0 ? number_format((float) $totalNet / $totalOrdersCount, 2, '.', '') : '0.00',
                'dine_in_count'     => $dineInCount,
                'dine_in_revenue'   => $dineInRevenue,
                'takeaway_count'    => $takeawayCount,
                'takeaway_revenue'  => $takeawayRevenue,
                'cancelled_orders'  => $cancelledCount,
                'cancelled_lost'    => $cancelledLost,
            ],
            'records'           => [],
        ]);
    }
}
