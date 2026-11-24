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
        $paidOrders = $orders->filter(function ($o) {
            $statusVal = $o->status?->value ?? (string) $o->status;
            if ($statusVal === 'cancelled') {
                return false;
            }
            return $statusVal === 'served' || !empty($o->paid_at);
        });

        $cancelledOrders = $orders->filter(function ($o) {
            $statusVal = $o->status?->value ?? (string) $o->status;
            return $statusVal === 'cancelled';
        });

        $pendingOrders = $orders->filter(function ($o) {
            $statusVal = $o->status?->value ?? (string) $o->status;
            return !in_array($statusVal, ['served', 'cancelled'], true) && empty($o->paid_at);
        });

        $totalSales = '0.00';
        $totalSubtotal = '0.00';
        $totalDiscount = '0.00';

        foreach ($paidOrders as $order) {
            $totalSubtotal = MoneyCalculator::add($totalSubtotal, number_format((float) $order->subtotal, 2, '.', ''));
            $totalDiscount = MoneyCalculator::add($totalDiscount, number_format((float) ($order->discount_amount ?? 0), 2, '.', ''));
            $totalSales = MoneyCalculator::add($totalSales, number_format((float) $order->total, 2, '.', ''));
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

