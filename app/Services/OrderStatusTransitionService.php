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
    public function transition(Order $order, OrderStatus $target, User $actor, array $attributes = []): Order
    {
        $current = $order->status;

        if (! $current->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => ["لا يمكن تغيير الحالة من {$current->label()} إلى {$target->label()}."],
            ]);
        }

        // تقييد صلاحيات المحاسب (الكاشير): الكاشير يستلم المبلغ فقط لنقل الطلب إلى نوافذ المطبخ ولا يملك صلاحية تغيير أي حالة أخرى
        if ($actor->isCashier() && ! $actor->isAdmin()) {
            if ($target !== OrderStatus::Accepted) {
                throw ValidationException::withMessages([
                    'status' => ['لا يملك المحاسب صلاحية تغيير حالة الطلب، دوره يقتصر على تأكيد استلام المبلغ ليظهر الطلب إلى نوافذ المطبخ.'],
                ]);
            }
        }

        if ($target === OrderStatus::Cancelled && $current === OrderStatus::Preparing && ! $actor->isAdmin()) {
            throw ValidationException::withMessages([
                'status' => ['فقط المدير يمكنه إلغاء الطلب أثناء التحضير.'],
            ]);
        }

        $updateData = ['status' => $target];

        $restaurant = $order->restaurant;

        // في مطاعم الدفع المسبق: التحقق من سداد الفاتورة قبل تحويل الطلب للتحضير
        if ($restaurant && $restaurant->isPrepaid()) {
            if ($current === OrderStatus::Pending && $target === OrderStatus::Preparing) {
                throw ValidationException::withMessages([
                    'status' => ['لا يمكن للنافذة استلام الطلب قبل تأكيد استلام المبلغ من الكاشير.'],
                ]);
            }
            if ($current === OrderStatus::Pending && $target === OrderStatus::Accepted && ! ($actor->isCashier() || $actor->isAdmin())) {
                throw ValidationException::withMessages([
                    'status' => ['تأكيد استلام المبلغ يتم عبر الكاشير حصراً.'],
                ]);
            }
            if ($target === OrderStatus::Accepted && ! $order->paid_at) {
                $updateData['paid_at'] = now();
                $updateData['paid_by'] = $actor->id;
            }
        }

        // تسجيل رقم النافذة عند استلام الطلب للتحضير من قبل إحدى النوافذ
        if (isset($attributes['pickup_window']) && $attributes['pickup_window'] !== null && $attributes['pickup_window'] !== '') {
            $updateData['pickup_window'] = (int) $attributes['pickup_window'];
        } elseif ($target === OrderStatus::Preparing && ! $order->pickup_window && $actor->default_window) {
            $updateData['pickup_window'] = (int) $actor->default_window;
        }

        // تسجيل وقت الحالة تلقائياً
        $timestampColumn = \App\Models\Order::statusTimestampFor($target);
        if ($timestampColumn) {
            $updateData[$timestampColumn] = now();
        }

        $order->update($updateData);

        Log::info('Order status changed', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'from' => $current->value,
            'to' => $target->value,
            'pickup_window' => $order->pickup_window,
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

        return $order->fresh(['items', 'table', 'restaurant', 'paidBy']);
    }
}
