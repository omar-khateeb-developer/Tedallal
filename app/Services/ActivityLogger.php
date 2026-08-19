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

        ActivityLog::create([
            'restaurant_id' => $user->restaurant_id,
            'user_id' => $user->id,
            'action' => $action,
            'description' => $description,
        ]);
    }
}
