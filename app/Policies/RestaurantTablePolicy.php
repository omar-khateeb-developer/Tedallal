<?php

namespace App\Policies;

use App\Models\RestaurantTable;
use App\Models\User;

class RestaurantTablePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, RestaurantTable $table): bool
    {
        return $user->isAdmin() && $user->restaurant_id === $table->restaurant_id;
    }

    public function delete(User $user, RestaurantTable $table): bool
    {
        return $user->isAdmin() && $user->restaurant_id === $table->restaurant_id;
    }
}
