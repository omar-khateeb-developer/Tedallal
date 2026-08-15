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

        $categories = MenuCategory::query()
            ->where('restaurant_id', $table->restaurant_id)
            ->active()
            ->orderBy('sort_order')
            ->with(['activeItems' => fn ($q) => $q->orderBy('sort_order')])
            ->get();

        return Inertia::render('Customer/Menu', [
            'restaurant' => $table->restaurant,
            'table' => $table,
            'categories' => $categories,
            'sessionToken' => $session->session_token,
            'openCart' => $request->boolean('cart'),
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

        return Inertia::render('Customer/OrderStatus', [
            'restaurant' => $table->restaurant,
            'table' => $table,
            'orderNumber' => $orderNumber,
            'publicToken' => $token,
        ]);
    }
}
