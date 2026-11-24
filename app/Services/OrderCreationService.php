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

    public function createFromCart(TableSession $session, ?string $customerNote = null, ?string $promoCodeStr = null, ?string $serviceType = 'dine_in'): Order
    {
        return DB::transaction(function () use ($session, $customerNote, $promoCodeStr, $serviceType) {
            // Lock session row to prevent race conditions & double-submits
            $lockedSession = TableSession::where('id', $session->id)->lockForUpdate()->first();

            if (! $lockedSession || ! $lockedSession->isActive()) {
                throw ValidationException::withMessages([
                    'session' => ['انتهت جلسة الطاولة.'],
                ]);
            }

            $summary = $this->cartService->summary($lockedSession);

            if ($summary['items']->isEmpty()) {
                throw ValidationException::withMessages([
                    'cart' => ['السلة فارغة.'],
                ]);
            }

            $table = $lockedSession->table()->lockForUpdate()->first();
            $restaurant = $table->restaurant;

            if (! $restaurant || ! $restaurant->isActive()) {
                throw ValidationException::withMessages([
                    'restaurant' => ['عذراً، هذا المطعم غير متاح لاستقبال الطلبات حالياً.'],
                ]);
            }

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

            $promoCode = null;
            $discountAmount = '0.00';
            
            if ($promoCodeStr) {
                if (!$restaurant->hasOffersFeature()) {
                    throw ValidationException::withMessages(['promo_code' => ['نظام العروض غير مفعل في هذا المطعم.']]);
                }
                $cleanPromoStr = strtoupper(trim($promoCodeStr));
                $promoCode = \App\Models\PromoCode::withoutGlobalScopes()
                    ->where('restaurant_id', $restaurant->id)
                    ->where(function ($q) use ($promoCodeStr, $cleanPromoStr) {
                        $q->where('code', $promoCodeStr)
                          ->orWhere('code', $cleanPromoStr);
                    })
                    ->lockForUpdate()
                    ->first();
                if (!$promoCode || !$promoCode->isValid((float)$subtotal)) {
                    $diagnostics = $promoCode?->getValidationDiagnostics((float)$subtotal);
                    $msg = $diagnostics['reason_message'] ?? 'الكوبون غير صالح أو لا يغطي الحد الأدنى للطلب.';
                    throw ValidationException::withMessages(['promo_code' => [$msg]]);
                }
                if ($promoCode->source === 'demo_wheel' || $promoCode->isDemoCode()) {
                    throw ValidationException::withMessages(['promo_code' => ['عذراً، هذا الكوبون مخصص للعرض التوضيحي والتجريبي فقط ولا يمكن تطبيقه على الطلبات الفعلية.']]);
                }
                $discountAmount = $promoCode->calculateExactDiscount($subtotal);
            }
            
            $total = MoneyCalculator::subtract($subtotal, $discountAmount);
            if ((float)$total < 0) $total = '0.00';

            $effectiveServiceType = ($table && $table->isTakeaway())
                ? 'takeaway'
                : (in_array($serviceType, ['takeaway', 'dine_in'], true) ? $serviceType : 'dine_in');

            $order = Order::query()->create([
                'restaurant_id' => $restaurant->id,
                'table_id' => $table->id,
                'table_session_id' => $session->id,
                'order_number' => $this->orderNumberGenerator->generate($restaurant->id),
                'status' => OrderStatus::Pending,
                'service_type' => $effectiveServiceType,
                'subtotal' => $subtotal,
                'total' => $total,
                'promo_code_id' => $promoCode?->id,
                'discount_amount' => $discountAmount,
                'customer_note' => $customerNote,
            ]);

            foreach ($orderItemsData as $itemData) {
                $order->items()->create($itemData);
            }

            if ($promoCode) {
                $promoCode->increment('times_used');
                if ($promoCode->is_single_use) {
                    $promoCode->update(['is_active' => false]);
                }
            }

            $this->cartService->clear($session);

            $order->load(['items', 'table', 'promoCode']);

            if ($table && $order->service_type !== 'takeaway') {
                $table->update([
                    'operational_stage' => 'preparing',
                    'stage_updated_at' => now(),
                    'session_started_at' => $table->session_started_at ?? now(),
                ]);
            }

            try {
                OrderCreated::dispatch($order);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Broadcast failed on order creation: ' . $e->getMessage(), [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                ]);
            }

            // Dispatch Background Web Push Notification to restaurant staff
            try {
                $staffUsers = \App\Models\User::where('restaurant_id', $order->restaurant_id)
                    ->whereHas('pushSubscriptions')
                    ->get();

                if ($staffUsers->isNotEmpty()) {
                    $tableNumber = $order->table ? ($order->table->name ?? "طاولة {$order->table->number}") : 'طلب خارجي';
                    $formattedTotal = number_format((float) ($order->total ?? 0), 0) . ' ' . ($restaurant->currency ?? 'ل.س');

                    \Illuminate\Support\Facades\Notification::send($staffUsers, new \App\Notifications\TedallalPushNotification(
                        title: "🔔 طلب جديد #{$order->order_number} ({$tableNumber})",
                        body: "قيمة الطلب: {$formattedTotal} • {$order->items->count()} عناصر",
                        url: '/admin/orders',
                        orderId: $order->id,
                        tableId: $order->table_id,
                        data: [
                            'type' => 'new_order',
                            'order_number' => $order->order_number,
                            'total' => (string) $order->total,
                        ]
                    ));
                }
            } catch (\Throwable $pushErr) {
                \Illuminate\Support\Facades\Log::warning('Push notification failed on order creation: ' . $pushErr->getMessage());
            }

            return $order;
        });
    }
}
