<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\OrderResource;
use App\Http\Requests\UpdateOrderRequest;
use App\Models\Order;
use App\Services\OrderStatusTransitionService;
use App\Services\RestaurantContext;
use Carbon\Carbon;
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
            ->with(['items', 'table', 'promoCode'])
            ->where('restaurant_id', $restaurant->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($request->filled('date')) {
            $dateInput = trim((string) $request->input('date'));
            try {
                $parsedDate = Carbon::createFromFormat('Y-m-d', $dateInput);
            } catch (\Throwable) {
                $parsedDate = false;
            }

            if (! $parsedDate || $parsedDate->format('Y-m-d') !== $dateInput) {
                return $this->error('صيغة التاريخ غير صالحة.', 422);
            }

            $query->whereBetween('created_at', [
                $parsedDate->copy()->startOfDay(),
                $parsedDate->copy()->endOfDay(),
            ]);
        } else {
            $period = $request->string('period')->toString() ?: 'today';

            if ($period === 'yesterday') {
                $yesterday = now()->subDay();
                $query->whereBetween('created_at', [
                    $yesterday->copy()->startOfDay(),
                    $yesterday->copy()->endOfDay(),
                ]);
            } elseif ($period !== 'all') {
                // الوضع الافتراضي (today): طلبات اليوم كاملة + أي طلب نشط من قبل منتصف الليل لم يُسلَّم أو يُلغَ بعد
                $todayStart = now()->startOfDay();
                $query->where(function ($q) use ($todayStart) {
                    $q->where('created_at', '>=', $todayStart)
                        ->orWhereNotIn('status', [OrderStatus::Served->value, OrderStatus::Cancelled->value]);
                });
            }
        }

        if ($request->filled('status') && $request->string('status')->toString() !== 'all') {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhereHas('table', fn ($tq) => $tq->withoutGlobalScopes()->where('name', 'like', "%{$search}%"));
            });
        }

        $orders = $query->get();

        return response()->json([
            'success' => true,
            'data' => OrderResource::collection($orders)->resolve(),
            'meta' => [
                'total' => $orders->count(),
            ],
        ]);
    }

    public function show(Order $order)
    {
        $this->authorizeOrder($order);
        $order->load(['items', 'table', 'promoCode']);

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
                $validated
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

        if ((int) $order->restaurant_id !== (int) $restaurant->id) {
            abort(403, 'غير مصرح.');
        }
    }
}
