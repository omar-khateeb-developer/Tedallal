<?php

namespace App\Services;

use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\TableSession;

final class RestaurantContext
{
    private ?Restaurant $cached = null;

    public function current(): Restaurant
    {
        // 0. Return immediately if context is already resolved and cached in memory
        if ($this->cached !== null) {
            return $this->cached;
        }

        // 1. If table_session attribute was already validated and attached by ValidateTableSession middleware
        if (request()->attributes->has('table_session')) {
            /** @var TableSession|null $session */
            $session = request()->attributes->get('table_session');
            if ($session) {
                // If relation is already loaded in memory, use it directly
                if ($session->relationLoaded('table') && $session->table?->relationLoaded('restaurant') && $session->table->restaurant) {
                    return $this->cached = $session->table->restaurant;
                }

                // If not loaded, query table directly without scopes to prevent any recursion with TenantScope
                if ($session->table_id) {
                    $table = RestaurantTable::withoutGlobalScopes()
                        ->with('restaurant')
                        ->find($session->table_id);
                    if ($table && $table->restaurant) {
                        $session->setRelation('table', $table);
                        return $this->cached = $table->restaurant;
                    }
                }
            }
        }

        // 2. If it is a public customer request, check X-Session-Token header first (takes precedence over stale cookie)
        $sessionToken = request()->header('X-Session-Token') ?? request()->cookie('tedallal_session');
        if ($sessionToken) {
            $tableSession = TableSession::query()
                ->where('session_token', $sessionToken)
                ->where('status', 'active')
                ->with(['table' => fn($q) => $q->withoutGlobalScopes(), 'table.restaurant'])
                ->first();
                
            if ($tableSession && $tableSession->table && $tableSession->table->restaurant) {
                return $this->cached = $tableSession->table->restaurant;
            }
        }

        // 3. If admin is logged in (and this is not a conflicting customer session), use their restaurant
        if (auth()->check() && auth()->user()->restaurant_id) {
            return $this->cached = auth()->user()->restaurant;
        }

        // 4. No valid context found - throw instead of leaking another tenant's data
        throw new \RuntimeException('No restaurant context found. Cannot resolve tenant.');
    }

    public function set(Restaurant $restaurant): void
    {
        $this->cached = $restaurant;
    }

    public function forget(): void
    {
        $this->cached = null;
    }
}
