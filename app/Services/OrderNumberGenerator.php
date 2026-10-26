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
            $prefix = "ORD-{$date}-";

            $lastSequence = Order::withoutGlobalScopes()
                ->where('order_number', 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderByDesc('order_number')
                ->value('order_number');

            $sequence = 1;

            if ($lastSequence) {
                $sequence = (int) substr($lastSequence, -4) + 1;
            }

            return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
        });
    }
}
