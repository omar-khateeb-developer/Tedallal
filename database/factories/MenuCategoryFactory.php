<?php

namespace Database\Factories;

use App\Models\MenuCategory;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MenuCategory> */
class MenuCategoryFactory extends Factory
{
    protected $model = MenuCategory::class;

    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'name' => fake()->word(),
            'description' => fake()->optional()->sentence(),
            'sort_order' => fake()->numberBetween(1, 20),
            'is_active' => true,
        ];
    }
}
