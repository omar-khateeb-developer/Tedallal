<?php

namespace App\Services;

use App\Models\Restaurant;
use App\Enums\RestaurantStatus;

final class RestaurantContext
{
    private ?Restaurant $cached = null;

    public function current(): Restaurant
    {
        if ($this->cached) {
            return $this->cached;
        }

        /** @var \App\Models\User $user */
        $user = auth()->user();

        if (! $user || ! $user->restaurant) {
            throw new \RuntimeException('No restaurant associated with current user.');
        }

        $restaurant = $user->restaurant;

        if (! $restaurant->isActive()) {
            throw new \RuntimeException('Restaurant is not active.');
        }

        return $this->cached = $restaurant;
    }

    public function forget(): void
    {
        $this->cached = null;
    }
}
