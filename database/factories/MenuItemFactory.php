<?php

namespace Database\Factories;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MenuItem> */
class MenuItemFactory extends Factory
{
    protected $model = MenuItem::class;

    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'category_id' => MenuCategory::factory(),
            'name' => fake()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'price' => fake()->randomFloat(2, 5, 100),
            'sort_order' => fake()->numberBetween(1, 20),
            'is_available' => true,
            'is_active' => true,
        ];
    }
}
