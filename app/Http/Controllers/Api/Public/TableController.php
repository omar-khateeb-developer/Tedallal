<?php

namespace App\Http\Controllers\Api\Public;

use App\Events\TableServiceAttended;
use App\Events\TableServiceCalled;
use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\RestaurantTableResource;
use App\Models\RestaurantTable;
use App\Models\TableServiceCall;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TableController extends ApiController
{
    public function show(string $token)
    {
        $table = RestaurantTable::withoutGlobalScopes()
            ->with('restaurant')
            ->whereToken($token)
            ->first();

        if (! $table || ! $table->isActive()) {
            Log::warning('Invalid table token accessed', ['token' => substr($token, 0, 8) . '...']);

            return $this->error('الطاولة غير موجودة.', 404);
        }

        if (! $table->restaurant->isActive()) {
            return $this->error('المطعم غير متاح حالياً.', 403);
        }

        return $this->success(new RestaurantTableResource($table));
    }

    public function callWaiter(Request $request, string $token): JsonResponse
    {
        $table = RestaurantTable::withoutGlobalScopes()
            ->with('restaurant')
            ->whereToken($token)
            ->first();

        if (! $table || ! $table->isActive()) {
            return $this->error('الطاولة غير موجودة.', 404);
        }

        // Anti-spam protection: check if a waiter call was sent in the last 45 seconds and is still pending
        $recentPending = TableServiceCall::where('table_id', $table->id)
            ->where('type', 'waiter_call')
            ->where('status', 'pending')
            ->where('created_at', '>=', now()->subSeconds(45))
            ->first();

        if ($recentPending) {
            return $this->success([
                'message' => 'تم استلام ندائك مسبقاً، والنادل في طريقه إليكم الآن 🛎️',
                'service_call' => $recentPending,
                'already_pending' => true,
            ]);
        }

        $reason = $request->input('reason', 'general');
        $notes = $request->input('notes');

        $reasonLabels = [
            'general' => 'مساعدة واستفسار عام',
            'water' => 'طلب ماء أو ثلج',
            'cutlery' => 'طلب أدوات طعام وشوك',
            'cleaning' => 'تنظيف أو مسح الطاولة',
            'order_inquiry' => 'استفسار عن الوجبات',
        ];
        $reasonText = $reasonLabels[$reason] ?? $reason;

        $serviceCall = TableServiceCall::create([
            'restaurant_id' => $table->restaurant_id,
            'table_id' => $table->id,
            'type' => 'waiter_call',
            'status' => 'pending',
            'reason' => $reasonText,
            'notes' => $notes,
        ]);

        \App\Services\ActivityLogger::log('waiter_called', "استدعاء نادل للطاولة {$table->name} ({$reasonText})");

        // Broadcast to floor plan & cashier screens
        try {
            broadcast(new TableServiceCalled($serviceCall));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Broadcast failed for TableServiceCalled (waiter): ' . $e->getMessage());
        }

        // Push notification to staff
        $this->dispatchStaffNotification(
            $table,
            "🛎️ استدعاء نادل - {$table->name}",
            "الزبون على الطاولة {$table->name} يطلب النادل ({$reasonText}).",
            'waiter_called',
            $serviceCall->id
        );

        return $this->success([
            'message' => 'تم استدعاء النادل بنجاح، وسيكون عند طاولتكم حالاً.',
            'service_call' => $serviceCall,
        ]);
    }

    public function requestBill(Request $request, string $token): JsonResponse
    {
        $table = RestaurantTable::withoutGlobalScopes()
            ->with('restaurant')
            ->whereToken($token)
            ->first();

        if (! $table || ! $table->isActive()) {
            return $this->error('الطاولة غير موجودة.', 404);
        }

        $paymentMethod = $request->input('payment_method', 'cash'); // cash, card, online
        $notes = $request->input('notes');

        $table->update([
            'operational_stage' => 'bill_requested',
            'stage_updated_at' => now(),
            'bill_requested_at' => now(),
        ]);

        // Find or create pending bill request
        $serviceCall = TableServiceCall::where('table_id', $table->id)
            ->where('type', 'bill_request')
            ->where('status', 'pending')
            ->first();

        if ($serviceCall) {
            $serviceCall->update([
                'payment_method' => $paymentMethod,
                'notes' => $notes,
            ]);
        } else {
            $serviceCall = TableServiceCall::create([
                'restaurant_id' => $table->restaurant_id,
                'table_id' => $table->id,
                'type' => 'bill_request',
                'status' => 'pending',
                'payment_method' => $paymentMethod,
                'notes' => $notes,
            ]);
        }

        \App\Services\ActivityLogger::log('bill_requested', "طلب الزبون الحساب للطاولة {$table->name} (" . ($paymentMethod === 'card' ? 'بطاقة/مدى' : 'نقداً') . ")");

        // Broadcast live event
        try {
            broadcast(new TableServiceCalled($serviceCall));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Broadcast failed for TableServiceCalled (bill): ' . $e->getMessage());
        }

        // Dispatch web push notification to staff
        $methodLabel = $paymentMethod === 'card' ? 'بطاقة / مدى' : 'نقداً';
        $this->dispatchStaffNotification(
            $table,
            "🧾 طلب حساب - {$table->name}",
            "الزبون على الطاولة {$table->name} يطلب الفاتورة ({$methodLabel}).",
            'bill_requested',
            $serviceCall->id
        );

        return $this->success([
            'message' => 'تم إرسال طلب الفاتورة إلى الكاشير بنجاح.',
            'operational_stage' => 'bill_requested',
            'service_call' => $serviceCall,
        ]);
    }

    public function cancelServiceCall(Request $request, string $token): JsonResponse
    {
        $table = RestaurantTable::withoutGlobalScopes()
            ->whereToken($token)
            ->first();

        if (! $table || ! $table->isActive()) {
            return $this->error('الطاولة غير موجودة.', 404);
        }

        $type = $request->input('type'); // 'waiter_call' or 'bill_request' or null for all

        $query = TableServiceCall::where('table_id', $table->id)->where('status', 'pending');
        if ($type) {
            $query->where('type', $type);
        }
        $pendingCalls = $query->get();

        foreach ($pendingCalls as $call) {
            $call->update(['status' => 'cancelled']);
            broadcast(new TableServiceAttended($call));
        }

        return $this->success([
            'message' => 'تم إلغاء الطلب بنجاح.',
        ]);
    }

    public function getServiceStatus(string $token): JsonResponse
    {
        $table = RestaurantTable::withoutGlobalScopes()
            ->whereToken($token)
            ->first();

        if (! $table || ! $table->isActive()) {
            return $this->error('الطاولة غير موجودة.', 404);
        }

        $pendingCalls = TableServiceCall::where('table_id', $table->id)
            ->where('status', 'pending')
            ->latest()
            ->get();

        return $this->success([
            'table_id' => $table->id,
            'table_name' => $table->name,
            'operational_stage' => $table->operational_stage,
            'has_active_call' => $pendingCalls->isNotEmpty(),
            'calls' => $pendingCalls,
        ]);
    }

    private function dispatchStaffNotification(RestaurantTable $table, string $title, string $body, string $type, int $callId): void
    {
        try {
            $staffUsers = \App\Models\User::where('restaurant_id', $table->restaurant_id)
                ->whereHas('pushSubscriptions')
                ->get();

            if ($staffUsers->isNotEmpty()) {
                \Illuminate\Support\Facades\Notification::send($staffUsers, new \App\Notifications\TedallalPushNotification(
                    title: $title,
                    body: $body,
                    url: '/admin/orders',
                    tableId: $table->id,
                    data: [
                        'type' => $type,
                        'service_call_id' => $callId,
                        'table_id' => $table->id,
                        'table_name' => $table->name,
                    ]
                ));
            }
        } catch (\Throwable $e) {
            Log::warning("Push notification for {$type} failed: " . $e->getMessage());
        }
    }
}
