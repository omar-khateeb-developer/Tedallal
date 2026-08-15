<?php

namespace App\Policies;

use App\Models\MenuCategory;
use App\Models\User;

class MenuCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, MenuCategory $category): bool
    {
        return $user->isAdmin() && $user->restaurant_id === $category->restaurant_id;
    }

    public function delete(User $user, MenuCategory $category): bool
    {
        return $user->isAdmin() && $user->restaurant_id === $category->restaurant_id;
    }
}
