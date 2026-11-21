<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\RestaurantTableResource;
use App\Models\RestaurantTable;
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
            Log::warning('Invalid table token accessed', ['token' => substr($token, 0, 8).'...']);

            return $this->error('الطاولة غير موجودة.', 404);
        }

        if (! $table->restaurant->isActive()) {
            return $this->error('المطعم غير متاح حالياً.', 403);
        }

        return $this->success(new RestaurantTableResource($table));
    }

    public function requestBill(string $token)
    {
        $table = RestaurantTable::withoutGlobalScopes()
            ->with('restaurant')
            ->whereToken($token)
            ->first();

        if (! $table || ! $table->isActive()) {
            return $this->error('الطاولة غير موجودة.', 404);
        }

        $table->update([
            'operational_stage' => 'bill_requested',
            'stage_updated_at' => now(),
            'bill_requested_at' => now(),
        ]);

        \App\Services\ActivityLogger::log('bill_requested', "طلب الزبون الحساب للطاولة: {$table->name}");

        // Dispatch web push notification to restaurant staff
        try {
            $staffUsers = \App\Models\User::where('restaurant_id', $table->restaurant_id)
                ->whereHas('pushSubscriptions')
                ->get();

            if ($staffUsers->isNotEmpty()) {
                \Illuminate\Support\Facades\Notification::send($staffUsers, new \App\Notifications\TedallalPushNotification(
                    title: "🧾 طلب حساب - {$table->name}",
                    body: "الزبون على الطاولة {$table->name} يطلب الفاتورة الآن.",
                    url: '/admin/floor-plan',
                    tableId: $table->id,
                    data: [
                        'type' => 'bill_requested',
                        'table_id' => $table->id,
                        'table_name' => $table->name,
                    ]
                ));
            }
        } catch (\Throwable $e) {
            Log::warning('Push notification for bill request failed: ' . $e->getMessage());
        }

        return $this->success([
            'message' => 'تم إرسال طلب الفاتورة إلى الكاشير بنجاح.',
            'operational_stage' => 'bill_requested',
        ]);
    }
}
