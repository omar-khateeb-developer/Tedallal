<?php

namespace Database\Factories;

use App\Enums\TableStatus;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Services\TokenGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RestaurantTable> */
class RestaurantTableFactory extends Factory
{
    protected $model = RestaurantTable::class;

    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'name' => 'طاولة '.fake()->numberBetween(1, 99),
            'public_token' => app(TokenGenerator::class)->generatePublicToken(),
            'status' => TableStatus::Active,
        ];
    }
}
