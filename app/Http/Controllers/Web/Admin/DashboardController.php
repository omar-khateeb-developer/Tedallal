<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Services\RestaurantContext;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly RestaurantContext $restaurantContext,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Dashboard');
    }

    public function orders(): Response
    {
        return Inertia::render('Admin/Orders');
    }

    public function tables(): Response
    {
        $restaurant = $this->restaurantContext->current();

        $tables = RestaurantTable::query()
            ->where('restaurant_id', $restaurant->id)
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Tables', [
            'tables' => $tables,
            'restaurant' => $restaurant,
        ]);
    }

    public function menuCategories(): Response
    {
        return Inertia::render('Admin/MenuCategories');
    }

    public function menuItems(): Response
    {
        return Inertia::render('Admin/MenuItems');
    }

    public function settings(): Response
    {
        return Inertia::render('Admin/Settings');
    }

    public function users(): Response
    {
        return Inertia::render('Admin/Users');
    }
}
