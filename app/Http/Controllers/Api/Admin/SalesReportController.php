<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Api\ApiController;
use App\Models\Order;
use App\Models\Restaurant;
use App\Services\RestaurantContext;
use App\Support\MoneyCalculator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SalesReportController extends ApiController
{
    public function __construct(
        private readonly RestaurantContext $restaurantContext,
    ) {}

    public function index(Request $request)
    {
        $restaurant = $this->restaurantContext->current();

        return $this->success($this->buildReportData($restaurant, $request));
    }

    private function buildReportData(Restaurant $restaurant, Request $request): array
    {
        $viewType = $request->query('view', 'daily'); // 'daily', 'monthly', 'yearly', 'custom'
        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');

        $baseValidQuery = Order::withoutGlobalScopes()
            ->with(['promoCode' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'source')])
            ->where('restaurant_id', $restaurant->id)
            ->recognizedRevenue();

        $cancelledQuery = Order::withoutGlobalScopes()
            ->where('restaurant_id', $restaurant->id)
            ->where('status', OrderStatus::Cancelled->value);

        return match ($viewType) {
            'daily' => $this->getDailyReport($baseValidQuery, $cancelledQuery, $restaurant, $year, $month),
            'monthly' => $this->getMonthlyReport($baseValidQuery, $cancelledQuery, $restaurant, $year),
            'yearly' => $this->getYearlyReport($baseValidQuery, $cancelledQuery, $restaurant),
            default => $this->getCustomReport($baseValidQuery, $cancelledQuery, $restaurant, $fromDate, $toDate),
        };
    }

    private function getDailyReport($baseQuery, $cancelledQuery, Restaurant $restaurant, int $year, int $month): array
    {
        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $validOrders = (clone $baseQuery)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get(['id', 'subtotal', 'discount_amount', 'total', 'service_type', 'promo_code_id', 'created_at']);

        $cancelledOrders = (clone $cancelledQuery)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get(['id', 'total']);

        $arabicDayNames = [
            'Saturday' => 'السبت',
            'Sunday' => 'الأحد',
            'Monday' => 'الإثنين',
            'Tuesday' => 'الثلاثاء',
            'Wednesday' => 'الأربعاء',
            'Thursday' => 'الخميس',
            'Friday' => 'الجمعة',
        ];

        $daysBreakdown = [];
        for ($day = 1; $day <= $startDate->daysInMonth; $day++) {
            $dayCarbon = Carbon::createFromDate($year, $month, $day);
            $dateString = $dayCarbon->toDateString();
            $dayStats = $this->aggregateOrders($validOrders->filter(fn ($o) => $o->created_at->format('Y-m-d') === $dateString));

            $daysBreakdown[] = [
                'day_number' => $day,
                'date' => $dateString,
                'day_name' => $arabicDayNames[$dayCarbon->format('l')] ?? $dayCarbon->format('l'),
                'orders_count' => $dayStats['orders_count'],
                'gross_sales' => $dayStats['gross_sales'],
                'discounts' => $dayStats['total_discounts'],
                'net_sales' => $dayStats['net_sales'],
                'aov' => $dayStats['aov'],
            ];
        }

        return [
            'period_type' => 'daily',
            'year' => $year,
            'month' => $month,
            'month_label' => $startDate->translatedFormat('F Y'),
            'currency' => $restaurant->currency ?? 'SYP',
            'summary' => $this->buildSummary($validOrders, $cancelledOrders, includeChannels: true),
            'analytics' => $this->computeAnalytics($validOrders),
            'records' => $daysBreakdown,
        ];
    }

    private function getMonthlyReport($baseQuery, $cancelledQuery, Restaurant $restaurant, int $year): array
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
            9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر',
        ];

        $monthsBreakdown = [];
        $previousMonthNet = 0.0;

        for ($m = 1; $m <= 12; $m++) {
            $monthStats = $this->aggregateOrders($validOrders->filter(fn ($o) => (int) $o->created_at->format('n') === $m));
            $currentNetFloat = (float) $monthStats['net_sales'];
            $growthPercentage = $previousMonthNet > 0
                ? round((($currentNetFloat - $previousMonthNet) / $previousMonthNet) * 100, 1)
                : null;
            $previousMonthNet = $currentNetFloat;

            $monthsBreakdown[] = [
                'month_number' => $m,
                'month_name' => $arabicMonths[$m],
                'orders_count' => $monthStats['orders_count'],
                'gross_sales' => $monthStats['gross_sales'],
                'discounts' => $monthStats['total_discounts'],
                'net_sales' => $monthStats['net_sales'],
                'aov' => $monthStats['aov'],
                'growth_percentage' => $growthPercentage,
            ];
        }

        return [
            'period_type' => 'monthly',
            'year' => $year,
            'currency' => $restaurant->currency ?? 'SYP',
            'summary' => $this->buildSummary($validOrders, $cancelledOrders, includeChannels: false),
            'analytics' => $this->computeAnalytics($validOrders),
            'records' => $monthsBreakdown,
        ];
    }

    private function getYearlyReport($baseQuery, $cancelledQuery, Restaurant $restaurant): array
    {
        $allOrders = (clone $baseQuery)->get(['id', 'subtotal', 'discount_amount', 'total', 'promo_code_id', 'created_at']);
        $cancelledOrders = (clone $cancelledQuery)->get(['id', 'total']);

        $yearsGrouped = $allOrders->groupBy(fn ($o) => $o->created_at->format('Y'))->sortKeysDesc();
        $yearsBreakdown = [];

        foreach ($yearsGrouped as $year => $orders) {
            $yStats = $this->aggregateOrders($orders);
            $yearsBreakdown[] = [
                'year' => (int) $year,
                'orders_count' => $yStats['orders_count'],
                'gross_sales' => $yStats['gross_sales'],
                'discounts' => $yStats['total_discounts'],
                'net_sales' => $yStats['net_sales'],
                'aov' => $yStats['aov'],
            ];
        }

        return [
            'period_type' => 'yearly',
            'currency' => $restaurant->currency ?? 'SYP',
            'summary' => $this->buildSummary($allOrders, $cancelledOrders, includeChannels: false),
            'analytics' => $this->computeAnalytics($allOrders),
            'records' => $yearsBreakdown,
        ];
    }

    private function getCustomReport($baseQuery, $cancelledQuery, Restaurant $restaurant, ?string $from, ?string $to): array
    {
        $startDate = $from ? Carbon::parse($from)->startOfDay() : Carbon::now()->subDays(30)->startOfDay();
        $endDate = $to ? Carbon::parse($to)->endOfDay() : Carbon::now()->endOfDay();

        if ($startDate->greaterThan($endDate)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'from_date' => ['تاريخ البداية يجب أن يسبق تاريخ النهاية.'],
            ]);
        }

        if ($startDate->diffInDays($endDate) > 366) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'to_date' => ['الحد الأقصى لعرض التقرير المخصص هو 366 يوماً (سنة واحدة) حفاظاً على سرعة النظام.'],
            ]);
        }

        $validOrders = (clone $baseQuery)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get(['id', 'subtotal', 'discount_amount', 'total', 'service_type', 'promo_code_id', 'created_at']);

        $cancelledOrders = (clone $cancelledQuery)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get(['id', 'total']);

        return [
            'period_type' => 'custom',
            'from_date' => $startDate->toDateString(),
            'to_date' => $endDate->toDateString(),
            'currency' => $restaurant->currency ?? 'SYP',
            'summary' => $this->buildSummary($validOrders, $cancelledOrders, includeChannels: true),
            'analytics' => $this->computeAnalytics($validOrders),
            'records' => [],
        ];
    }

    /**
     * Aggregate gross, discounts, net, AOV, and optional service channels using exact BigDecimal math.
     */
    private function aggregateOrders(Collection $orders, bool $includeChannels = false): array
    {
        $gross = '0.00';
        $discounts = '0.00';
        $net = '0.00';
        $dineInCount = 0;
        $dineInRevenue = '0.00';
        $takeawayCount = 0;
        $takeawayRevenue = '0.00';
        $count = $orders->count();

        foreach ($orders as $order) {
            $orderSub = MoneyCalculator::normalize((string) $order->subtotal);
            $orderDisc = MoneyCalculator::normalize((string) ($order->discount_amount ?? '0'));
            $orderNet = MoneyCalculator::normalize((string) $order->total);

            $gross = MoneyCalculator::add($gross, $orderSub);
            $discounts = MoneyCalculator::add($discounts, $orderDisc);
            $net = MoneyCalculator::add($net, $orderNet);

            if ($includeChannels) {
                if (($order->service_type ?? 'dine_in') === 'takeaway') {
                    $takeawayCount++;
                    $takeawayRevenue = MoneyCalculator::add($takeawayRevenue, $orderNet);
                } else {
                    $dineInCount++;
                    $dineInRevenue = MoneyCalculator::add($dineInRevenue, $orderNet);
                }
            }
        }

        $result = [
            'orders_count' => $count,
            'gross_sales' => $gross,
            'total_discounts' => $discounts,
            'net_sales' => $net,
            'aov' => MoneyCalculator::divideByCount($net, $count),
        ];

        if ($includeChannels) {
            $result['dine_in_count'] = $dineInCount;
            $result['dine_in_revenue'] = $dineInRevenue;
            $result['takeaway_count'] = $takeawayCount;
            $result['takeaway_revenue'] = $takeawayRevenue;
        }

        return $result;
    }

    private function buildSummary(Collection $validOrders, Collection $cancelledOrders, bool $includeChannels): array
    {
        $agg = $this->aggregateOrders($validOrders, $includeChannels);
        $cancelledLost = '0.00';
        foreach ($cancelledOrders as $c) {
            $cancelledLost = MoneyCalculator::add($cancelledLost, MoneyCalculator::normalize((string) $c->total));
        }

        $summary = [
            'gross_sales' => $agg['gross_sales'],
            'total_discounts' => $agg['total_discounts'],
            'net_sales' => $agg['net_sales'],
            'total_orders' => $agg['orders_count'],
            'aov' => $agg['aov'],
            'cancelled_orders' => $cancelledOrders->count(),
            'cancelled_lost' => $cancelledLost,
        ];

        if ($includeChannels) {
            $summary['dine_in_count'] = $agg['dine_in_count'];
            $summary['dine_in_revenue'] = $agg['dine_in_revenue'];
            $summary['takeaway_count'] = $agg['takeaway_count'];
            $summary['takeaway_revenue'] = $agg['takeaway_revenue'];
        }

        return array_merge($summary, $this->computePromoDiscountBreakdown($validOrders));
    }

    private function computePromoDiscountBreakdown(Collection $orders): array
    {
        $loyaltyDiscounts = '0.00';
        $loyaltyCount = 0;
        $wheelDiscounts = '0.00';
        $wheelCount = 0;

        foreach ($orders as $order) {
            $discount = MoneyCalculator::normalize((string) ($order->discount_amount ?? '0'));
            if (MoneyCalculator::isZero($discount)) {
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

    private function computeAnalytics(Collection $validOrders): array
    {
        $hourly = [];
        for ($h = 0; $h < 24; $h++) {
            $hourly[$h] = [
                'hour' => $h,
                'label' => sprintf('%02d:00', $h),
                'orders_count' => 0,
                'net_sales' => '0.00',
            ];
        }

        foreach ($validOrders as $order) {
            $h = (int) $order->created_at->format('G');
            $hourly[$h]['orders_count']++;
            $hourly[$h]['net_sales'] = MoneyCalculator::add(
                $hourly[$h]['net_sales'],
                MoneyCalculator::normalize((string) $order->total)
            );
        }

        $peakHour = null;
        $maxSales = -1.0;
        foreach ($hourly as $hData) {
            $salesFloat = (float) $hData['net_sales'];
            if ($salesFloat > $maxSales && $hData['orders_count'] > 0) {
                $maxSales = $salesFloat;
                $peakHour = $hData;
            }
        }

        $orderIds = $validOrders->pluck('id')->filter()->values()->all();
        $topDishes = [];
        if (! empty($orderIds)) {
            $aggregatedDishes = [];
            foreach (array_chunk($orderIds, 500) as $idChunk) {
                $rows = DB::table('order_items')
                    ->whereIn('order_id', $idChunk)
                    ->select(
                        'item_name_snapshot as name',
                        DB::raw('SUM(quantity) as total_qty'),
                        DB::raw('SUM(subtotal) as total_revenue')
                    )
                    ->groupBy('item_name_snapshot')
                    ->get();

                foreach ($rows as $r) {
                    $name = (string) $r->name;
                    if (! isset($aggregatedDishes[$name])) {
                        $aggregatedDishes[$name] = ['qty' => 0, 'revenue' => 0.0];
                    }
                    $aggregatedDishes[$name]['qty'] += (int) $r->total_qty;
                    $aggregatedDishes[$name]['revenue'] += (float) $r->total_revenue;
                }
            }

            uasort($aggregatedDishes, fn ($a, $b) => $b['revenue'] <=> $a['revenue']);
            $topSlice = array_slice($aggregatedDishes, 0, 5, true);

            $totalGrossSum = (float) $validOrders->sum('subtotal');

            foreach ($topSlice as $name => $stats) {
                $rev = (float) $stats['revenue'];
                $topDishes[] = [
                    'name' => $name,
                    'quantity' => (int) $stats['qty'],
                    'revenue' => number_format($rev, 2, '.', ''),
                    'share_percentage' => $totalGrossSum > 0 ? round(($rev / $totalGrossSum) * 100, 1) : 0,
                ];
            }
        }

        return [
            'hourly' => array_values($hourly),
            'peak_hour' => $peakHour,
            'top_dishes' => $topDishes,
        ];
    }

    public function export(Request $request)
    {
        $restaurant = $this->restaurantContext->current();
        $currency = $restaurant->currency ?? 'SYP';
        $viewType = $request->query('view', 'daily');
        $reportData = $this->buildReportData($restaurant, $request);

        $cleanSlug = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $restaurant->slug ?? 'restaurant');
        $filename = "sales_report_{$cleanSlug}_{$viewType}_" . now()->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($reportData, $restaurant, $currency, $viewType) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");

            fputcsv($output, [$restaurant->name ?? 'منظومة تدلّل', 'كشف حركات المبيعات والتقارير المالية المعتمدة']);
            fputcsv($output, ['تاريخ استخراج التقرير', now()->toDateTimeString(), 'العملة', $currency]);
            fputcsv($output, []);

            $records = $reportData['records'] ?? [];
            $summary = $reportData['summary'] ?? [];

            if ($viewType === 'daily') {
                fputcsv($output, ['رقم اليوم', 'التاريخ', 'اليوم', 'عدد الطلبات', "إجمالي المبيعات ({$currency})", "الخصومات ({$currency})", "صافي المبيعات ({$currency})", "متوسط الفاتورة AOV ({$currency})"]);
                foreach ($records as $r) {
                    fputcsv($output, [$r['day_number'] ?? '', $r['date'] ?? '', $r['day_name'] ?? '', $r['orders_count'] ?? 0, $r['gross_sales'] ?? '0.00', $r['discounts'] ?? '0.00', $r['net_sales'] ?? '0.00', $r['aov'] ?? '0.00']);
                }
            } elseif ($viewType === 'monthly') {
                fputcsv($output, ['رقم الشهر', 'الشهر', 'عدد الطلبات', "إجمالي المبيعات ({$currency})", "الخصومات ({$currency})", "صافي المبيعات ({$currency})", "متوسط الفاتورة AOV ({$currency})", 'نسبة النمو الشهري %']);
                foreach ($records as $r) {
                    fputcsv($output, [$r['month_number'] ?? '', $r['month_name'] ?? '', $r['orders_count'] ?? 0, $r['gross_sales'] ?? '0.00', $r['discounts'] ?? '0.00', $r['net_sales'] ?? '0.00', $r['aov'] ?? '0.00', isset($r['growth_percentage']) ? $r['growth_percentage'] . '%' : '-']);
                }
            } elseif ($viewType === 'yearly') {
                fputcsv($output, ['السنة', 'عدد الطلبات', "إجمالي المبيعات ({$currency})", "الخصومات ({$currency})", "صافي المبيعات ({$currency})", "متوسط الفاتورة AOV ({$currency})"]);
                foreach ($records as $r) {
                    fputcsv($output, [$r['year'] ?? '', $r['orders_count'] ?? 0, $r['gross_sales'] ?? '0.00', $r['discounts'] ?? '0.00', $r['net_sales'] ?? '0.00', $r['aov'] ?? '0.00']);
                }
            }

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

    public function getAccountingVoucher(Request $request)
    {
        $restaurant = $this->restaurantContext->current();
        $date = (string) $request->query('date', now()->toDateString());

        return $this->success($this->buildAccountingVoucherData($restaurant, $date));
    }

    private function buildAccountingVoucherData(Restaurant $restaurant, string $date): array
    {
        $currency = $restaurant->currency ?? 'SYP';
        $start = Carbon::parse($date)->startOfDay();
        $end = Carbon::parse($date)->endOfDay();

        $mapping = array_merge([
            'cash_account_code' => '10101',
            'cash_account_name' => 'الصندوق الرئيسي',
            'dine_in_sales_code' => '40101',
            'dine_in_sales_name' => 'إيراد مبيعات الصالة',
            'takeaway_sales_code' => '40102',
            'takeaway_sales_name' => 'إيراد مبيعات سفري',
            'discount_account_code' => '40201',
            'discount_account_name' => 'حسميات وعروض ترويجية',
            'tax_account_code' => '20301',
            'tax_account_name' => 'أمانات الضرائب والرسوم',
        ], (array) (($restaurant->features ?? [])['accounting_mapping'] ?? []));

        $orders = Order::withoutGlobalScopes()
            ->with(['promoCode' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'source')])
            ->where('restaurant_id', $restaurant->id)
            ->recognizedRevenue()
            ->whereBetween('created_at', [$start, $end])
            ->get(['id', 'subtotal', 'discount_amount', 'total', 'service_type', 'promo_code_id', 'created_at']);

        $totalGross = '0.00';
        $totalDiscount = '0.00';
        $totalNetCash = '0.00';
        $dineInGross = '0.00';
        $takeawayGross = '0.00';
        $dineInCount = 0;
        $takeawayCount = 0;

        foreach ($orders as $order) {
            $sub = MoneyCalculator::normalize((string) $order->subtotal);
            $disc = MoneyCalculator::normalize((string) ($order->discount_amount ?? '0'));
            $net = MoneyCalculator::normalize((string) $order->total);

            $totalGross = MoneyCalculator::add($totalGross, $sub);
            $totalDiscount = MoneyCalculator::add($totalDiscount, $disc);
            $totalNetCash = MoneyCalculator::add($totalNetCash, $net);

            if (($order->service_type ?? 'dine_in') === 'takeaway') {
                $takeawayGross = MoneyCalculator::add($takeawayGross, $sub);
                $takeawayCount++;
            } else {
                $dineInGross = MoneyCalculator::add($dineInGross, $sub);
                $dineInCount++;
            }
        }

        $lines = [[
            'type' => 'debit',
            'account_code' => $mapping['cash_account_code'],
            'account_name' => $mapping['cash_account_name'],
            'debit' => $totalNetCash,
            'credit' => '0.00',
            'note' => "المقبوضات النقدية الصافية المحصلة - مبيعات {$date}",
        ]];

        if (! MoneyCalculator::isZero($totalDiscount)) {
            $lines[] = [
                'type' => 'debit',
                'account_code' => $mapping['discount_account_code'],
                'account_name' => $mapping['discount_account_name'],
                'debit' => $totalDiscount,
                'credit' => '0.00',
                'note' => "إجمالي خصومات الكوبونات وعجلة المكافآت ليوم {$date}",
            ];
        }

        if (! MoneyCalculator::isZero($dineInGross) || MoneyCalculator::isZero($totalGross)) {
            $lines[] = [
                'type' => 'credit',
                'account_code' => $mapping['dine_in_sales_code'],
                'account_name' => $mapping['dine_in_sales_name'],
                'debit' => '0.00',
                'credit' => $dineInGross,
                'note' => "إجمالي مبيعات طلبات الصالة ليوم {$date} ({$dineInCount} طلب)",
            ];
        }

        if (! MoneyCalculator::isZero($takeawayGross)) {
            $lines[] = [
                'type' => 'credit',
                'account_code' => $mapping['takeaway_sales_code'],
                'account_name' => $mapping['takeaway_sales_name'],
                'debit' => '0.00',
                'credit' => $takeawayGross,
                'note' => "إجمالي مبيعات طلبات السفري ليوم {$date} ({$takeawayCount} طلب)",
            ];
        }

        $totalDebit = MoneyCalculator::add($totalNetCash, $totalDiscount);
        $totalCredit = MoneyCalculator::add($dineInGross, $takeawayGross);
        $diff = MoneyCalculator::absDiff($totalDebit, $totalCredit);
        $isBalanced = MoneyCalculator::isZero($diff);

        $orderIds = $orders->pluck('id')->filter()->all();
        $itemizedSales = [];
        if (! empty($orderIds)) {
            $itemRows = DB::table('order_items')
                ->leftJoin('menu_items', 'order_items.menu_item_id', '=', 'menu_items.id')
                ->whereIn('order_items.order_id', $orderIds)
                ->select(
                    'order_items.menu_item_id',
                    'order_items.item_name_snapshot as name',
                    'menu_items.external_sku',
                    DB::raw('SUM(order_items.quantity) as total_qty'),
                    DB::raw('AVG(order_items.unit_price) as avg_unit_price'),
                    DB::raw('SUM(order_items.subtotal) as total_revenue')
                )
                ->groupBy('order_items.menu_item_id', 'order_items.item_name_snapshot', 'menu_items.external_sku')
                ->orderByDesc('total_revenue')
                ->get();

            foreach ($itemRows as $row) {
                $itemizedSales[] = [
                    'menu_item_id' => $row->menu_item_id,
                    'external_sku' => $row->external_sku ?? '-',
                    'name' => $row->name,
                    'quantity' => (int) $row->total_qty,
                    'unit_price' => number_format((float) $row->avg_unit_price, 2, '.', ''),
                    'total_revenue' => MoneyCalculator::normalize((string) $row->total_revenue),
                ];
            }
        }

        $cleanDate = str_replace('-', '', $date);

        return [
            'voucher_number' => "JV-{$cleanDate}-01",
            'date' => $date,
            'statement' => "قيد إثبات مبيعات يوم {$date} - {$restaurant->name}",
            'currency' => $currency,
            'restaurant_name' => $restaurant->name,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'is_balanced' => $isBalanced,
            'difference' => $diff,
            'orders_count' => count($orders),
            'dine_in_count' => $dineInCount,
            'takeaway_count' => $takeawayCount,
            'lines' => $lines,
            'itemized_sales' => $itemizedSales,
        ];
    }

    public function exportAccountingVoucher(Request $request)
    {
        $restaurant = $this->restaurantContext->current();
        $format = $request->query('format', 'al_ameen');
        $date = (string) $request->query('date', now()->toDateString());
        $voucherData = $this->buildAccountingVoucherData($restaurant, $date);

        $cleanSlug = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $restaurant->slug ?? 'restaurant');
        $lines = $voucherData['lines'] ?? [];
        $currency = $voucherData['currency'] ?? 'SYP';
        $voucherNumber = $voucherData['voucher_number'] ?? 'JV-01';

        if ($format === 'al_ameen') {
            $filename = "al_ameen_voucher_{$cleanSlug}_{$date}.txt";
            $headers = [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ];
            $callback = function () use ($lines, $date, $voucherNumber, $currency) {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");
                fwrite($out, implode("\t", ['رقم السند', 'التاريخ', 'رمز الحساب', 'اسم الحساب', 'مدين', 'دائن', 'العملة', 'البيان']) . "\r\n");
                foreach ($lines as $line) {
                    fwrite($out, implode("\t", [$voucherNumber, $date, $line['account_code'] ?? '', $line['account_name'] ?? '', $line['debit'] ?? '0.00', $line['credit'] ?? '0.00', $currency, $line['note'] ?? '']) . "\r\n");
                }
                fclose($out);
            };

            return response()->stream($callback, 200, $headers);
        }

        $isBayan = $format === 'al_bayan';
        $filename = $isBayan
            ? "al_bayan_voucher_{$cleanSlug}_{$date}.csv"
            : "accounting_voucher_pack_{$cleanSlug}_{$date}.csv";
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($isBayan, $voucherData, $lines, $currency, $date, $voucherNumber, $restaurant) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            if ($isBayan) {
                fputcsv($out, ['التاريخ', 'رقم السند', 'رمز الحساب', 'اسم الحساب', 'مدين', 'دائن', 'البيان', 'العملة']);
                foreach ($lines as $line) {
                    fputcsv($out, [$date, $voucherNumber, $line['account_code'] ?? '', $line['account_name'] ?? '', $line['debit'] ?? '0.00', $line['credit'] ?? '0.00', $line['note'] ?? '', $currency]);
                }
                fclose($out);

                return;
            }

            fputcsv($out, ['منظومة تدلّل - سند القيد المحاسبي اليومي المعتمد']);
            fputcsv($out, ['المطعم / المنشأة', $restaurant->name ?? '', 'التاريخ', $date]);
            fputcsv($out, ['رقم السند', $voucherNumber, 'العملة', $currency]);
            fputcsv($out, ['حالة التوازن المحاسبي', ($voucherData['is_balanced'] ?? false) ? 'متوازن 100% (فرق صفر)' : 'غير متوازن']);
            fputcsv($out, []);
            fputcsv($out, ['رمز الحساب', 'اسم الحساب', "مدين ({$currency})", "دائن ({$currency})", 'البيان / الشرح']);

            foreach ($lines as $line) {
                fputcsv($out, [$line['account_code'] ?? '', $line['account_name'] ?? '', $line['debit'] ?? '0.00', $line['credit'] ?? '0.00', $line['note'] ?? '']);
            }
            fputcsv($out, ['المجموع الإجمالي', '', $voucherData['total_debit'] ?? '0.00', $voucherData['total_credit'] ?? '0.00', 'سند قيد متوازن معتمد']);
            fputcsv($out, []);
            fputcsv($out, ['--- كشف حركة مبيعات الأصناف لإخراج المواد المستودعية (Inventory Depletion) ---']);
            fputcsv($out, ['رمز المادة المحاسبي (SKU)', 'اسم الصنف', 'الكمية المباعة', "متوسط السعر ({$currency})", "إجمالي القيمة ({$currency})"]);
            foreach ($voucherData['itemized_sales'] ?? [] as $it) {
                fputcsv($out, [$it['external_sku'] ?? '-', $it['name'] ?? '', $it['quantity'] ?? 0, $it['unit_price'] ?? '0.00', $it['total_revenue'] ?? '0.00']);
            }
            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }
}
