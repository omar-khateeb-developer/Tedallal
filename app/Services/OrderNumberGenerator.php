<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\DB;

final class OrderNumberGenerator
{
    public function generate(int $restaurantId): string
    {
        return DB::transaction(function () use ($restaurantId) {
            $date = now()->format('Ymd');
            $prefix = "ORD-{$restaurantId}-{$date}-";
            $legacyPrefix = "ORD-{$date}-";

            // Find last sequence for this specific restaurant today (supporting backward-compatibility)
            $lastOrderNumber = Order::withoutGlobalScopes()
                ->where('restaurant_id', $restaurantId)
                ->where(function ($q) use ($prefix, $legacyPrefix) {
                    $q->where('order_number', 'like', $prefix.'%')
                      ->orWhere('order_number', 'like', $legacyPrefix.'%');
                })
                ->lockForUpdate()
                ->orderByDesc('id')
                ->value('order_number');

            $sequence = 1;

            if ($lastOrderNumber) {
                $parts = explode('-', $lastOrderNumber);
                $lastSeq = end($parts);
                $sequence = (int) $lastSeq + 1;
            }

            return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
        });
    }
}
