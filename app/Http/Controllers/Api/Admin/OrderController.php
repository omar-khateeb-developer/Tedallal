<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\OrderResource;
use App\Http\Requests\UpdateOrderRequest;
use App\Models\Order;
use App\Services\OrderStatusTransitionService;
use App\Services\RestaurantContext;
use Illuminate\Http\Request;

class OrderController extends ApiController
{
    public function __construct(
        private readonly RestaurantContext $restaurantContext,
        private readonly OrderStatusTransitionService $statusService,
    ) {}

    public function index(Request $request)
    {
        $restaurant = $this->restaurantContext->current();

        $query = Order::query()
            ->with(['items', 'table'])
            
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return $this->success(OrderResource::collection($query->paginate(20)));
    }

    public function show(Order $order)
    {
        $this->authorizeOrder($order);
        $order->load(['items', 'table']);

        return $this->success(new OrderResource($order));
    }

    public function updateStatus(UpdateOrderRequest $request, Order $order)
    {
        $this->authorizeOrder($order);
        $validated = $request->validated();

        try {
            $updated = $this->statusService->transition(
                $order,
                OrderStatus::from($validated['status']),
                $request->user(),
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->error(
                collect($e->errors())->flatten()->first() ?? 'فشل تحديث الحالة.',
                422,
                $e->errors()
            );
        }

        \App\Services\ActivityLogger::log('update_order', "قام بتحديث حالة الطلب رقم {$order->order_number} إلى " . $order->status->label());

        return $this->success(new OrderResource($updated));
    }

    private function authorizeOrder(Order $order): void
    {
        $restaurant = $this->restaurantContext->current();

        if ($order->restaurant_id !== $restaurant->id) {
            abort(404);
        }
    }
}
