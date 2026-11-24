<?php

namespace App\Services;

use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\TableSession;

final class RestaurantContext
{
    private ?Restaurant $cached = null;
    private ?int $cachedRequestObjectId = null;
    private ?int $cachedAuthUserId = null;
    private ?string $cachedSessionToken = null;

    public function current(): Restaurant
    {
        // 0. Return immediately only if cache still belongs to this exact request, session, and user
        if (! $this->hasStaleCache()) {
            return $this->cached;
        }

        $this->forget();

        // 1. If table_session attribute was already validated and attached by ValidateTableSession middleware
        if (request()->attributes->has('table_session')) {
            /** @var TableSession|null $session */
            $session = request()->attributes->get('table_session');
            if ($session) {
                // If relation is already loaded in memory, use it directly
                if ($session->relationLoaded('table') && $session->table?->relationLoaded('restaurant') && $session->table->restaurant) {
                    return $this->remember($session->table->restaurant);
                }

                // If not loaded, query table directly without scopes to prevent any recursion with TenantScope
                if ($session->table_id) {
                    $table = RestaurantTable::withoutGlobalScopes()
                        ->with('restaurant')
                        ->find($session->table_id);
                    if ($table && $table->restaurant) {
                        $session->setRelation('table', $table);
                        return $this->remember($table->restaurant);
                    }
                }
            }
        }

        // 2. If it is a public customer request, check X-Session-Token header first (takes precedence over stale cookie)
        $sessionToken = $this->currentSessionToken();
        if ($sessionToken) {
            $tableSession = TableSession::query()
                ->where('session_token', $sessionToken)
                ->where('status', 'active')
                ->with(['table' => fn($q) => $q->withoutGlobalScopes(), 'table.restaurant'])
                ->first();
                
            if ($tableSession && $tableSession->table && $tableSession->table->restaurant) {
                return $this->remember($tableSession->table->restaurant);
            }
        }

        // 3. If admin is logged in (and this is not a conflicting customer session), use their restaurant
        if (auth()->check() && auth()->user()->restaurant_id) {
            return $this->remember(auth()->user()->restaurant);
        }

        // 4. No valid context found - throw instead of leaking another tenant's data
        throw new \RuntimeException('No restaurant context found. Cannot resolve tenant.');
    }

    public function set(Restaurant $restaurant): void
    {
        $this->remember($restaurant);
    }

    public function forget(): void
    {
        $this->cached = null;
        $this->cachedRequestObjectId = null;
        $this->cachedAuthUserId = null;
        $this->cachedSessionToken = null;
    }

    private function remember(Restaurant $restaurant): Restaurant
    {
        $this->cached = $restaurant;
        $this->cachedRequestObjectId = $this->currentRequestObjectId();
        $this->cachedAuthUserId = auth()->check() ? auth()->id() : null;
        $this->cachedSessionToken = $this->currentSessionToken();

        return $this->cached;
    }

    private function hasStaleCache(): bool
    {
        if ($this->cached === null) {
            return true;
        }

        if ($this->cachedRequestObjectId !== $this->currentRequestObjectId()) {
            return true;
        }

        $sessionToken = $this->currentSessionToken();
        if ($sessionToken !== null && $sessionToken !== $this->cachedSessionToken) {
            return true;
        }

        if (! request()->attributes->has('table_session') && $sessionToken === null) {
            $authUserId = auth()->check() ? auth()->id() : null;
            if ($authUserId !== $this->cachedAuthUserId) {
                return true;
            }
        }

        return false;
    }

    private function currentRequestObjectId(): ?int
    {
        return app()->bound('request') ? spl_object_id(request()) : null;
    }

    private function currentSessionToken(): ?string
    {
        if (! app()->bound('request')) {
            return null;
        }

        $token = request()->header('X-Session-Token') ?? request()->cookie('tedallal_session');

        return is_string($token) && $token !== '' ? $token : null;
    }
}
