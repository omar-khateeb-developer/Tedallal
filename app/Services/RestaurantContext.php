<?php

namespace App\Services;

use App\Models\Restaurant;

final class RestaurantContext
{
    private ?Restaurant $cached = null;

    public function current(): Restaurant
    {
        // 1. If table_session attribute was already validated and attached by ValidateTableSession middleware
        if (request()->attributes->has('table_session')) {
            /** @var \App\Models\TableSession|null $session */
            $session = request()->attributes->get('table_session');
            if ($session && $session->table && $session->table->restaurant) {
                return $this->cached = $session->table->restaurant;
            }
        }

        // 2. If it is a public customer request, check X-Session-Token header first (takes precedence over stale cookie)
        $sessionToken = request()->header('X-Session-Token') ?? request()->cookie('tedallal_session');
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

        // 3. If admin is logged in (and this is not a conflicting customer session), use their restaurant
        if (auth()->check() && auth()->user()->restaurant_id) {
            if ($this->cached && $this->cached->id === auth()->user()->restaurant_id) {
                return $this->cached;
            }
            return $this->cached = auth()->user()->restaurant;
        }

        if ($this->cached) {
            return $this->cached;
        }

        // 4. No valid context found - throw instead of leaking another tenant's data
        throw new \RuntimeException('No restaurant context found. Cannot resolve tenant.');
    }

    public function forget(): void
    {
        $this->cached = null;
    }
}
