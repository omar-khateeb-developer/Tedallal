<?php

namespace App\Services;

use App\Models\Restaurant;

final class RestaurantContext
{
    private ?Restaurant $cached = null;

    public function current(): Restaurant
    {
        // 1. If admin is logged in, use their restaurant
        if (auth()->check() && auth()->user()->restaurant_id) {
            if ($this->cached && $this->cached->id === auth()->user()->restaurant_id) {
                return $this->cached;
            }
            return $this->cached = auth()->user()->restaurant;
        }

        if ($this->cached) {
            return $this->cached;
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

        // 3. No valid context found - throw instead of leaking another tenant's data
        throw new \RuntimeException('No restaurant context found. Cannot resolve tenant.');
    }

    public function forget(): void
    {
        $this->cached = null;
    }
}
