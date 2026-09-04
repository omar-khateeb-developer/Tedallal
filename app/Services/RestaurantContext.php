<?php

namespace App\Services;

use App\Models\Restaurant;
use App\Enums\RestaurantStatus;

final class RestaurantContext
{
    private ?Restaurant $cached = null;

    public function current(): Restaurant
    {
        if ($this->cached) {
            return $this->cached;
        }

        // 1. If admin is logged in, use their restaurant
        if (auth()->check() && auth()->user()->restaurant_id) {
            return $this->cached = auth()->user()->restaurant;
        }

        // 2. If it's a public customer request, use the session token
        $sessionToken = request()->cookie('tedallal_session') ?? request()->header('X-Session-Token');
        if ($sessionToken) {
            $tableSession = \App\Models\TableSession::query()
                ->where('session_token', $sessionToken)
                ->where('status', 'active')
                ->with('table.restaurant')
                ->first();
                
            if ($tableSession && $tableSession->table && $tableSession->table->restaurant) {
                return $this->cached = $tableSession->table->restaurant;
            }
        }

        // 3. Fallback (e.g. for superadmin or guest with no session)
        $restaurant = Restaurant::query()
            ->where('status', RestaurantStatus::Active)
            ->first();

        if (! $restaurant) {
            throw new \RuntimeException('No active restaurant configured.');
        }

        return $this->cached = $restaurant;
    }

    public function forget(): void
    {
        $this->cached = null;
    }
}
