<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Events\OrderStatusChanged;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class OrderStatusTransitionService
{
    public function transition(Order $order, OrderStatus $target, User $actor): Order
    {
        $current = $order->status;

        if (! $current->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => ["لا يمكن تغيير الحالة من {$current->label()} إلى {$target->label()}."],
            ]);
        }

        if ($target === OrderStatus::Cancelled && $current === OrderStatus::Preparing && ! $actor->isAdmin()) {
            throw ValidationException::withMessages([
                'status' => ['فقط المدير يمكنه إلغاء الطلب أثناء التحضير.'],
            ]);
        }

        $order->update(['status' => $target]);

        Log::info('Order status changed', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'from' => $current->value,
            'to' => $target->value,
            'user_id' => $actor->id,
            'user_role' => $actor->role instanceof UserRole ? $actor->role->value : $actor->role,
        ]);

        try {
            OrderStatusChanged::dispatch($order, $current, $target);
        } catch (\Throwable $e) {
            Log::warning('Broadcast failed (Reverb may be offline): ' . $e->getMessage(), [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);
        }

        return $order->fresh(['items', 'table']);
    }
}
