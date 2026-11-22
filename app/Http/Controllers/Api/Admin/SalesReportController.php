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
            ->with(['promoCode' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'source')])
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
            ->get(['id', 'subtotal', 'discount_amount', 'total', 'service_type', 'promo_code_id', 'created_at']);

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
            'currency'          => $restaurant->currency ?? 'SYP',
            'summary'           => array_merge([
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
            ], $this->computePromoDiscountBreakdown($validOrders)),
            'analytics'         => $this->computeAnalytics($validOrders, $restaurant->id),
            'records'           => $daysBreakdown,
        ]);
    }

    private function getMonthlyReport($baseQuery, $cancelledQuery, $restaurant, int $year)
    {
        $startDate = Carbon::createFromDate($year, 1, 1)->startOfYear();
        $endDate = Carbon::createFromDate($year, 12, 31)->endOfYear();

        $validOrders = (clone $baseQuery)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get(['id', 'subtotal', 'discount_amount', 'total', 'service_type', 'promo_code_id', 'created_at']);

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
            'currency'          => $restaurant->currency ?? 'SYP',
            'summary'           => array_merge([
                'gross_sales'       => $totalGross,
                'total_discounts'   => $totalDiscount,
                'net_sales'         => $totalNet,
                'total_orders'      => $totalOrdersCount,
                'aov'               => $totalOrdersCount > 0 ? number_format((float) $totalNet / $totalOrdersCount, 2, '.', '') : '0.00',
                'cancelled_orders'  => $cancelledCount,
                'cancelled_lost'    => $cancelledLost,
            ], $this->computePromoDiscountBreakdown($validOrders)),
            'analytics'         => $this->computeAnalytics($validOrders, $restaurant->id),
            'records'           => $monthsBreakdown,
        ]);
    }

    private function getYearlyReport($baseQuery, $cancelledQuery, $restaurant)
    {
        $allOrders = (clone $baseQuery)->get(['id', 'subtotal', 'discount_amount', 'total', 'promo_code_id', 'created_at']);
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
            'currency'          => $restaurant->currency ?? 'SYP',
            'summary'           => array_merge([
                'gross_sales'       => $totalGross,
                'total_discounts'   => $totalDiscount,
                'net_sales'         => $totalNet,
                'total_orders'      => $totalOrdersCount,
                'aov'               => $totalOrdersCount > 0 ? number_format((float) $totalNet / $totalOrdersCount, 2, '.', '') : '0.00',
                'cancelled_orders'  => $cancelledCount,
                'cancelled_lost'    => $cancelledLost,
            ], $this->computePromoDiscountBreakdown($allOrders)),
            'analytics'         => $this->computeAnalytics($allOrders, $restaurant->id),
            'records'           => $yearsBreakdown,
        ]);
    }

    private function getCustomReport($baseQuery, $cancelledQuery, $restaurant, ?string $from, ?string $to)
    {
        $startDate = $from ? Carbon::parse($from)->startOfDay() : Carbon::now()->subDays(30)->startOfDay();
        $endDate = $to ? Carbon::parse($to)->endOfDay() : Carbon::now()->endOfDay();

        $validOrders = (clone $baseQuery)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get(['id', 'subtotal', 'discount_amount', 'total', 'service_type', 'promo_code_id', 'created_at']);

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
            'currency'          => $restaurant->currency ?? 'SYP',
            'summary'           => array_merge([
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
            ], $this->computePromoDiscountBreakdown($validOrders)),
            'analytics'         => $this->computeAnalytics($validOrders, $restaurant->id),
            'records'           => [],
        ]);
    }

    /**
     * Separate discount totals by promo source (Loyalty grand prizes vs Wheel coupons vs Manual).
     *
     * @param \Illuminate\Support\Collection $orders
     */
    private function computePromoDiscountBreakdown($orders): array
    {
        $loyaltyDiscounts = '0.00';
        $loyaltyCount = 0;
        $wheelDiscounts = '0.00';
        $wheelCount = 0;

        foreach ($orders as $order) {
            $discount = number_format((float) ($order->discount_amount ?? 0), 2, '.', '');
            if ((float) $discount <= 0) {
                continue;
            }
            $source = $order->promoCode?->source;
            if ($source === 'loyalty') {
                $loyaltyDiscounts = MoneyCalculator::add($loyaltyDiscounts, $discount);
                $loyaltyCount++;
            } elseif ($source === 'wheel' || $source === 'demo_wheel') {
                $wheelDiscounts = MoneyCalculator::add($wheelDiscounts, $discount);
                $wheelCount++;
            }
        }

        return [
            'loyalty_discounts' => $loyaltyDiscounts,
            'loyalty_orders_count' => $loyaltyCount,
            'wheel_discounts' => $wheelDiscounts,
            'wheel_orders_count' => $wheelCount,
        ];
    }

    /**
     * حساب التحليلات الذكية: ساعات الذروة، وأفضل 5 أطباق مبيعاً ومساهمة في الدخل.
     *
     * @param \Illuminate\Support\Collection $validOrders
     * @param int $restaurantId
     * @return array
     */
    private function computeAnalytics($validOrders, int $restaurantId): array
    {
        // 1. توزيع المبيعات بحسب ساعات اليوم (00:00 - 23:00)
        $hourly = [];
        for ($h = 0; $h < 24; $h++) {
            $formattedHour = sprintf('%02d:00', $h);
            $hourly[$h] = [
                'hour' => $h,
                'label' => $formattedHour,
                'orders_count' => 0,
                'net_sales' => '0.00',
            ];
        }

        foreach ($validOrders as $order) {
            $h = (int) $order->created_at->format('G');
            $net = number_format((float) $order->total, 2, '.', '');
            $hourly[$h]['orders_count']++;
            $hourly[$h]['net_sales'] = MoneyCalculator::add($hourly[$h]['net_sales'], $net);
        }

        // تحديد ساعة الذروة (Peak Hour)
        $peakHour = null;
        $maxSales = -1.0;
        foreach ($hourly as $hData) {
            $salesFloat = (float) $hData['net_sales'];
            if ($salesFloat > $maxSales && $hData['orders_count'] > 0) {
                $maxSales = $salesFloat;
                $peakHour = $hData;
            }
        }

        // 2. أكثر 5 أطباق طلباً ومساهمة في الدخل (Top 5 Performing Dishes)
        $orderIds = $validOrders->pluck('id')->filter()->all();
        $topDishes = [];
        if (!empty($orderIds)) {
            $rows = DB::table('order_items')
                ->whereIn('order_id', $orderIds)
                ->select(
                    'item_name_snapshot as name',
                    DB::raw('SUM(quantity) as total_qty'),
                    DB::raw('SUM(subtotal) as total_revenue')
                )
                ->groupBy('item_name_snapshot')
                ->orderByDesc('total_revenue')
                ->limit(5)
                ->get();

            $totalRevenueSum = (float) $validOrders->sum('total');

            foreach ($rows as $r) {
                $rev = (float) $r->total_revenue;
                $sharePct = $totalRevenueSum > 0 ? round(($rev / $totalRevenueSum) * 100, 1) : 0;
                $topDishes[] = [
                    'name' => $r->name,
                    'quantity' => (int) $r->total_qty,
                    'revenue' => number_format($rev, 2, '.', ''),
                    'share_percentage' => $sharePct,
                ];
            }
        }

        return [
            'hourly' => array_values($hourly),
            'peak_hour' => $peakHour,
            'top_dishes' => $topDishes,
        ];
    }

    /**
     * تصدير الكشف المالي والمحاسبي إلى ملف CSV متوافق مع Excel باللغة العربية (UTF-8 BOM).
     */
    public function export(Request $request)
    {
        $restaurant = $this->restaurantContext->current();
        $currency = $restaurant->currency ?? 'SYP';
        $viewType = $request->query('view', 'daily');

        // جلب بيانات التقرير الرسمية
        $response = $this->index($request);
        $reportData = $response->getData(true)['data'] ?? [];

        $cleanSlug = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $restaurant->slug ?? 'restaurant');
        $filename = "sales_report_{$cleanSlug}_{$viewType}_" . now()->format('Y-m-d') . ".csv";

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($reportData, $restaurant, $currency, $viewType) {
            $output = fopen('php://output', 'w');
            // إضافة UTF-8 BOM لدعم مايكروسوفت إكسل العربي تلقائياً
            fwrite($output, "\xEF\xBB\xBF");

            // ترويسة المنشأة
            fputcsv($output, [$restaurant->name ?? 'منظومة تدلّل', 'كشف حركات المبيعات والتقارير المالية المعتمدة']);
            fputcsv($output, ['تاريخ استخراج التقرير', now()->toDateTimeString(), 'العملة', $currency]);
            fputcsv($output, []); // سطر فارغ

            $records = $reportData['records'] ?? [];
            $summary = $reportData['summary'] ?? [];

            if ($viewType === 'daily') {
                fputcsv($output, [
                    'رقم اليوم',
                    'التاريخ',
                    'اليوم',
                    'عدد الطلبات',
                    "إجمالي المبيعات ({$currency})",
                    "الخصومات ({$currency})",
                    "صافي المبيعات ({$currency})",
                    "متوسط الفاتورة AOV ({$currency})",
                ]);

                foreach ($records as $r) {
                    fputcsv($output, [
                        $r['day_number'] ?? '',
                        $r['date'] ?? '',
                        $r['day_name'] ?? '',
                        $r['orders_count'] ?? 0,
                        $r['gross_sales'] ?? '0.00',
                        $r['discounts'] ?? '0.00',
                        $r['net_sales'] ?? '0.00',
                        $r['aov'] ?? '0.00',
                    ]);
                }
            } elseif ($viewType === 'monthly') {
                fputcsv($output, [
                    'رقم الشهر',
                    'الشهر',
                    'عدد الطلبات',
                    "إجمالي المبيعات ({$currency})",
                    "الخصومات ({$currency})",
                    "صافي المبيعات ({$currency})",
                    "متوسط الفاتورة AOV ({$currency})",
                    'نسبة النمو الشهري %',
                ]);

                foreach ($records as $r) {
                    fputcsv($output, [
                        $r['month_number'] ?? '',
                        $r['month_name'] ?? '',
                        $r['orders_count'] ?? 0,
                        $r['gross_sales'] ?? '0.00',
                        $r['discounts'] ?? '0.00',
                        $r['net_sales'] ?? '0.00',
                        $r['aov'] ?? '0.00',
                        isset($r['growth_percentage']) ? $r['growth_percentage'] . '%' : '-',
                    ]);
                }
            } elseif ($viewType === 'yearly') {
                fputcsv($output, [
                    'السنة',
                    'عدد الطلبات',
                    "إجمالي المبيعات ({$currency})",
                    "الخصومات ({$currency})",
                    "صافي المبيعات ({$currency})",
                    "متوسط الفاتورة AOV ({$currency})",
                ]);

                foreach ($records as $r) {
                    fputcsv($output, [
                        $r['year'] ?? '',
                        $r['orders_count'] ?? 0,
                        $r['gross_sales'] ?? '0.00',
                        $r['discounts'] ?? '0.00',
                        $r['net_sales'] ?? '0.00',
                        $r['aov'] ?? '0.00',
                    ]);
                }
            }

            // سطر فارغ ثم الملخص المالي الرسمي
            fputcsv($output, []);
            fputcsv($output, ['--- ملخص الحسابات المالية المعتمدة ---']);
            fputcsv($output, ['إجمالي المبيعات قبل الخصم', $summary['gross_sales'] ?? '0.00', $currency]);
            fputcsv($output, ['إجمالي الخصومات المصروفة', $summary['total_discounts'] ?? '0.00', $currency]);
            fputcsv($output, ['خصومات عجلة المكافآت', $summary['wheel_discounts'] ?? '0.00', $currency]);
            fputcsv($output, ['خصومات برنامج الولاء', $summary['loyalty_discounts'] ?? '0.00', $currency]);
            fputcsv($output, ['صافي الإيراد النهائي', $summary['net_sales'] ?? '0.00', $currency]);
            fputcsv($output, ['إجمالي الطلبات المكتملة', $summary['total_orders'] ?? 0]);
            fputcsv($output, ['طلبات الصالة (Dine-in)', $summary['dine_in_count'] ?? 0, ($summary['dine_in_revenue'] ?? '0.00') . " {$currency}"]);
            fputcsv($output, ['طلبات السفري (Takeaway)', $summary['takeaway_count'] ?? 0, ($summary['takeaway_revenue'] ?? '0.00') . " {$currency}"]);
            fputcsv($output, ['الطلبات الملغاة وفاقد المبيعات', $summary['cancelled_orders'] ?? 0, ($summary['cancelled_lost'] ?? '0.00') . " {$currency}"]);

            fclose($output);
        };

        return response()->stream($callback, 200, $headers);
    }
}
