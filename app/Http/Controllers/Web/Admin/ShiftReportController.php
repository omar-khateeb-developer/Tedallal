<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\RestaurantContext;
use App\Support\MoneyCalculator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShiftReportController extends Controller
{
    public function __construct(
        private readonly RestaurantContext $restaurantContext,
    ) {}

    public function __invoke(Request $request): View
    {
        $restaurant = $this->restaurantContext->current();
        $dateParam = $request->query('date', now()->toDateString());
        $date = Carbon::parse($dateParam)->startOfDay();
        $endDate = $date->copy()->endOfDay();

        $orders = Order::withoutGlobalScopes()
            ->where('restaurant_id', $restaurant->id)
            ->whereBetween('created_at', [$date, $endDate])
            ->get();

        // استبعاد الطلبات الملغاة نهائياً حتى لو سبق دفعها، والاعتراف فقط بالمكتمل (served) أو المسدد غير الملغى
        $paidOrders = $orders->filter(fn (Order $o) => $o->isRecognizedRevenue());

        $cancelledOrders = $orders->filter(function ($o) {
            $statusVal = $o->status?->value ?? (string) $o->status;
            return $statusVal === 'cancelled';
        });

        $pendingOrders = $orders->filter(function ($o) {
            return ! $o->isRecognizedRevenue() && (($o->status?->value ?? (string) $o->status) !== 'cancelled');
        });

        $totalSales = '0.00';
        $totalSubtotal = '0.00';
        $totalDiscount = '0.00';

        foreach ($paidOrders as $order) {
            $totalSubtotal = MoneyCalculator::add($totalSubtotal, MoneyCalculator::normalize((string) $order->subtotal));
            $totalDiscount = MoneyCalculator::add($totalDiscount, MoneyCalculator::normalize((string) ($order->discount_amount ?? '0')));
            $totalSales = MoneyCalculator::add($totalSales, MoneyCalculator::normalize((string) $order->total));
        }

        $takeawayCount = $paidOrders->where('service_type', 'takeaway')->count();
        $dineInCount = $paidOrders->where('service_type', '!=', 'takeaway')->count();

        return view('admin.orders.print-shift-report', [
            'restaurant'           => $restaurant,
            'reportDate'           => $date->format('Y-m-d'),
            'printedAt'            => now()->format('Y-m-d H:i:s'),
            'cashierName'          => auth()->user()?->name ?? 'الكاشير',
            'totalOrders'          => $orders->count(),
            'paidOrdersCount'      => $paidOrders->count(),
            'cancelledOrdersCount' => $cancelledOrders->count(),
            'pendingOrdersCount'   => $pendingOrders->count(),
            'totalSales'           => $totalSales,
            'totalSubtotal'        => $totalSubtotal,
            'totalDiscount'        => $totalDiscount,
            'takeawayCount'        => $takeawayCount,
            'dineInCount'          => $dineInCount,
        ]);
    }
}

