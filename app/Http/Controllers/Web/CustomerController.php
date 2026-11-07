<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Services\TableSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function __construct(
        private readonly TableSessionService $tableSessionService,
    ) {}

    public function table(Request $request, string $token): Response
    {
        $table = RestaurantTable::query()
            ->with('restaurant')
            ->where('public_token', $token)
            ->where('status', 'active')
            ->firstOrFail();

        $session = $this->tableSessionService->resolveOrCreate($table);

        cookie()->queue(cookie(
            'tedallal_session',
            $session->session_token,
            60 * 4,
            '/',
            null,
            app()->isProduction(),
            true,
            false,
            'Lax'
        ));

        // CRITICAL: scope directly by restaurant_id from the resolved table,
        // bypassing TenantScope which cannot read the cookie yet on first visit.
        $categories = MenuCategory::withoutGlobalScopes()
            ->where('restaurant_id', $table->restaurant_id)
            ->active()
            ->orderBy('sort_order')
            ->with(['activeItems' => fn ($q) => $q->orderBy('sort_order')])
            ->get();

        $activeOrders = $session->orders()
            ->whereNotIn('status', ['served', 'cancelled'])
            ->select('id', 'order_number', 'status', 'total')
            ->get();

        $cleanCurrency = (!$table->restaurant->currency || strtoupper($table->restaurant->currency) === 'SYP') ? 'ل.س' : $table->restaurant->currency;

        $restaurantData = array_merge($table->restaurant->toArray(), [
            'currency' => $cleanCurrency,
        ]);

        return Inertia::render('Customer/Menu', [
            'restaurant' => $restaurantData,
            'table' => $table,
            'categories' => $categories,
            'sessionToken' => $session->session_token,
            'openCart' => $request->boolean('cart'),
            'activeOrders' => $activeOrders,
        ]);
    }

    public function cart(string $token): RedirectResponse
    {
        return redirect()->route('customer.menu', ['token' => $token, 'cart' => 1]);
    }

    public function order(string $token, string $orderNumber): Response
    {
        $table = RestaurantTable::query()
            ->with('restaurant')
            ->where('public_token', $token)
            ->firstOrFail();

        // Ensure the order exists and belongs to this table and restaurant
        $order = Order::withoutGlobalScopes()
            ->with(['items', 'table', 'tableSession', 'restaurant'])
            ->where('restaurant_id', $table->restaurant_id)
            ->where('table_id', $table->id)
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        // Ensure the session cookie is valid for subsequent requests/polling
        if ($order->tableSession) {
            cookie()->queue(cookie(
                'tedallal_session',
                $order->tableSession->session_token,
                60 * 4,
                '/',
                null,
                app()->isProduction(),
                true,
                false,
                'Lax'
            ));
        }

        // Check or generate wheel token for gamification
        $wheelToken = $order->wheelToken;
        if (!$wheelToken) {
            $wheelToken = $order->wheelToken()->create([
                'restaurant_id' => $table->restaurant_id,
                'token' => \Illuminate\Support\Str::random(32),
                'expires_at' => now()->addDays(14),
            ]);
        }

        $cleanCurrency = (!$table->restaurant->currency || strtoupper($table->restaurant->currency) === 'SYP') ? 'ل.س' : $table->restaurant->currency;
        $restaurantData = array_merge($table->restaurant->toArray(), [
            'currency' => $cleanCurrency,
        ]);

        return Inertia::render('Customer/OrderStatus', [
            'restaurant' => $restaurantData,
            'table' => $table,
            'orderNumber' => $orderNumber,
            'publicToken' => $token,
            'wheelToken' => $wheelToken->token,
            'isWheelClaimed' => (bool) $wheelToken->is_claimed,
            'initialOrder' => (new \App\Http\Resources\OrderResource($order))->resolve(),
            'sessionToken' => $order->tableSession?->session_token,
        ]);
    }
}
