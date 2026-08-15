<?php

namespace App\Policies;

use App\Models\MenuItem;
use App\Models\User;

class MenuItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, MenuItem $item): bool
    {
        return $user->isAdmin() && $user->restaurant_id === $item->restaurant_id;
    }

    public function delete(User $user, MenuItem $item): bool
    {
        return $user->isAdmin() && $user->restaurant_id === $item->restaurant_id;
    }
}
