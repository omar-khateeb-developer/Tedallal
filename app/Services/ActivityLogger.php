<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class ActivityLogger
{
    public static function log(string $action, string $description): void
    {
        $user = Auth::user();

        if (! $user) {
            return;
        }

        $restaurantId = $user->restaurant_id ?? app(\App\Services\RestaurantContext::class)->current()?->id;

        if (! $restaurantId) {
            return;
        }

        ActivityLog::create([
            'restaurant_id' => $restaurantId,
            'user_id' => $user->id,
            'action' => $action,
            'description' => $description,
        ]);
    }
}
