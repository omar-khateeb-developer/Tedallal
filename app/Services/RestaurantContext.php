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

        $restaurant = Restaurant::query()
            ->where('status', RestaurantStatus::Active)
            ->first();

        if (! $restaurant) {
            throw new \RuntimeException('No active restaurant configured.');
        }

        return $this->cached = $restaurant;
    }

    public function forget(): void
    {
        $this->cached = null;
    }
}
