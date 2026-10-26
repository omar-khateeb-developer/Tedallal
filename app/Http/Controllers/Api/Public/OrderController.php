<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\ApiController;
use App\Http\Middleware\ValidateTableSession;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderCreationService;
use Illuminate\Http\Request;

class OrderController extends ApiController
{
    public function __construct(
        private readonly OrderCreationService $orderCreationService,
    ) {}

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_note' => ['nullable', 'string', 'max:1000'],
            'promo_code' => ['nullable', 'string', 'max:50'],
            'service_type' => ['nullable', 'string', 'in:dine_in,takeaway'],
        ]);

        $session = ValidateTableSession::sessionFrom($request);

        try {
            $order = $this->orderCreationService->createFromCart(
                $session,
                $validated['customer_note'] ?? null,
                $validated['promo_code'] ?? null,
                $validated['service_type'] ?? 'dine_in',
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->error(
                collect($e->errors())->flatten()->first() ?? 'فشل إنشاء الطلب.',
                422,
                $e->errors()
            );
        }

        return $this->success(new OrderResource($order), 201);
    }

    public function show(Request $request, string $orderNumber)
    {
        $session = ValidateTableSession::sessionFrom($request);

        $order = Order::query()
            ->with(['items', 'table'])
            ->where('order_number', $orderNumber)
            ->where('table_session_id', $session->id)
            ->first();

        if (! $order) {
            return $this->error('الطلب غير موجود.', 404);
        }

        return $this->success(new OrderResource($order));
    }
}
