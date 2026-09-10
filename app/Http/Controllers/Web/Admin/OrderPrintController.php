<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\RestaurantContext;
use Illuminate\View\View;

class OrderPrintController extends Controller
{
    public function __construct(
        private readonly RestaurantContext $restaurantContext,
    ) {}

    public function __invoke(Order $order): View
    {
        $restaurant = $this->restaurantContext->current();

        // Ensure the order belongs to the current restaurant
        if ($order->restaurant_id !== $restaurant->id) {
            abort(404);
        }

        $order->load(['items', 'table', 'customer']);

        return view('admin.orders.print-invoice', [
            'restaurant' => $restaurant,
            'order' => $order,
        ]);
    }
}
