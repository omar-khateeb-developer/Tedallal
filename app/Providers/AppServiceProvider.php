<?php

namespace App\Providers;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\User;
use App\Policies\MenuCategoryPolicy;
use App\Policies\MenuItemPolicy;
use App\Policies\OrderPolicy;
use App\Policies\RestaurantPolicy;
use App\Policies\RestaurantTablePolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    protected $policies = [
        Restaurant::class => RestaurantPolicy::class,
        RestaurantTable::class => RestaurantTablePolicy::class,
        MenuCategory::class => MenuCategoryPolicy::class,
        MenuItem::class => MenuItemPolicy::class,
        Order::class => OrderPolicy::class,
        User::class => UserPolicy::class,
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        Gate::before(function ($user, $ability) {
            if ($user->isSuperAdmin() || $user->isAdmin()) {
                return true;
            }
            return null;
        });
    }
}
