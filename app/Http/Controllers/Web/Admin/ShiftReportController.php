<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\RestaurantContext;
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

        $orders = Order::where('restaurant_id', $restaurant->id)
            ->whereBetween('created_at', [$date, $endDate])
            ->get();

        $paidOrders = $orders->filter(function ($o) {
            $statusVal = $o->status?->value ?? (string) $o->status;
            return !empty($o->paid_at) || in_array($statusVal, ['accepted', 'preparing', 'ready', 'served']);
        });

        $cancelledOrders = $orders->filter(function ($o) {
            $statusVal = $o->status?->value ?? (string) $o->status;
            return $statusVal === 'cancelled';
        });

        $pendingOrders = $orders->filter(function ($o) {
            $statusVal = $o->status?->value ?? (string) $o->status;
            return $statusVal === 'pending' && empty($o->paid_at);
        });

        $totalSales = $paidOrders->sum(fn ($o) => (float) $o->total);
        $totalSubtotal = $paidOrders->sum(fn ($o) => (float) $o->subtotal);
        $totalDiscount = $paidOrders->sum(fn ($o) => (float) ($o->discount_amount ?? 0));

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
            'totalSales'           => number_format($totalSales, 2, '.', ''),
            'totalSubtotal'        => number_format($totalSubtotal, 2, '.', ''),
            'totalDiscount'        => number_format($totalDiscount, 2, '.', ''),
            'takeawayCount'        => $takeawayCount,
            'dineInCount'          => $dineInCount,
        ]);
    }
}

