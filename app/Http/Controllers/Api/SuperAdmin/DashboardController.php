<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Restaurant;
use App\Models\User;

class DashboardController extends ApiController
{
    public function stats()
    {
        $totalRestaurants = Restaurant::count();
        $activeRestaurants = Restaurant::where('status', 'active')->count();
        $totalUsers = User::count();

        return $this->success([
            'total_restaurants' => $totalRestaurants,
            'active_restaurants' => $activeRestaurants,
            'total_users' => $totalUsers,
        ]);
    }
}
