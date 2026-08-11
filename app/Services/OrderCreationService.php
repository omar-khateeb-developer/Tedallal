<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Events\OrderCreated;
use App\Models\Order;
use App\Models\TableSession;
use App\Support\MoneyCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class OrderCreationService
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly OrderNumberGenerator $orderNumberGenerator,
    ) {}

    public function createFromCart(TableSession $session, ?string $customerNote = null): Order
    {
        if (! $session->isActive()) {
            throw ValidationException::withMessages([
                'session' => ['انتهت جلسة الطاولة.'],
            ]);
        }

        $summary = $this->cartService->summary($session);

        if ($summary['items']->isEmpty()) {
            throw ValidationException::withMessages([
                'cart' => ['السلة فارغة.'],
            ]);
        }

        return DB::transaction(function () use ($session, $customerNote, $summary) {
            $table = $session->table()->lockForUpdate()->first();
            $restaurant = $table->restaurant;

            $lockedItems = $summary['items']->map(function ($cartItem) {
                $menuItem = $cartItem->menuItem()->lockForUpdate()->first();

                if (! $menuItem || ! $menuItem->isOrderable()) {
                    throw ValidationException::withMessages([
                        'cart' => ["{$cartItem->menuItem?->name} غير متاح حالياً."],
                    ]);
                }

                return [
                    'cart_item' => $cartItem,
                    'menu_item' => $menuItem,
                ];
            });

            $subtotal = '0.00';
            $orderItemsData = [];

            foreach ($lockedItems as $row) {
                $menuItem = $row['menu_item'];
                $cartItem = $row['cart_item'];
                $itemSubtotal = MoneyCalculator::multiply((string) $menuItem->price, $cartItem->quantity);
                $subtotal = MoneyCalculator::add($subtotal, $itemSubtotal);

                $orderItemsData[] = [
                    'menu_item_id' => $menuItem->id,
                    'item_name_snapshot' => $menuItem->name,
                    'unit_price' => $menuItem->price,
                    'quantity' => $cartItem->quantity,
                    'subtotal' => $itemSubtotal,
                    'note' => $cartItem->note,
                ];
            }

            $order = Order::query()->create([
                'restaurant_id' => $restaurant->id,
                'table_id' => $table->id,
                'table_session_id' => $session->id,
                'order_number' => $this->orderNumberGenerator->generate($restaurant->id),
                'status' => OrderStatus::Pending,
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'customer_note' => $customerNote,
            ]);

            foreach ($orderItemsData as $itemData) {
                $order->items()->create($itemData);
            }

            $this->cartService->clear($session);

            $order->load(['items', 'table']);

            OrderCreated::dispatch($order);

            return $order;
        });
    }
}
