<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RestaurantController extends Controller
{
    public function index()
    {
        return Restaurant::latest()->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:1000',
            'currency' => 'required|string|max:3',
            'manager_name' => 'required|string|max:255',
            'manager_email' => 'required|string|email|max:255|unique:users,email',
            'manager_password' => 'nullable|string|min:6',
        ]);

        $restaurantData = [
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'address' => $validated['address'],
            'currency' => $validated['currency'],
            'slug' => Str::slug($validated['name']) . '-' . rand(1000, 9999),
            'status' => 'active',
        ];

        $restaurant = Restaurant::create($restaurantData);

        \App\Models\User::create([
            'restaurant_id' => $restaurant->id,
            'name' => $validated['manager_name'],
            'email' => $validated['manager_email'],
            'password' => \Illuminate\Support\Facades\Hash::make($validated['manager_password'] ?: 'password'),
            'role' => \App\Enums\UserRole::Admin,
            'status' => \App\Enums\UserStatus::Active,
        ]);

        return response()->json($restaurant, 201);
    }

    public function update(Request $request, Restaurant $restaurant)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:1000',
            'currency' => 'sometimes|required|string|max:3',
            'status' => 'sometimes|required|string|in:active,inactive',
            'features' => 'sometimes|nullable|array',
        ]);

        // Merge features with existing ones instead of replacing
        if (isset($validated['features'])) {
            $existingFeatures = is_array($restaurant->features) ? $restaurant->features : [];
            $validated['features'] = array_merge($existingFeatures, $validated['features']);
        }

        $restaurant->update($validated);

        return response()->json($restaurant->fresh());
    }
}
