<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RestaurantController extends ApiController
{
    public function index()
    {
        return $this->success(Restaurant::latest()->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'sometimes|string|in:postpaid,prepaid',
            'windows_count' => 'sometimes|nullable|integer|min:1|max:50',
            'tables_count' => 'sometimes|nullable|integer|min:0|max:200',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:1000',
            'currency' => 'required|string|max:3',
            'manager_name' => 'required|string|max:255',
            'manager_email' => 'required|string|email|max:255|unique:users,email',
            'manager_password' => 'nullable|string|min:6',
        ]);

        $restaurantData = [
            'name' => $validated['name'],
            'type' => $validated['type'] ?? 'postpaid',
            'windows_count' => ($validated['type'] ?? 'postpaid') === 'prepaid' ? (int) ($validated['windows_count'] ?? 1) : 1,
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'currency' => $validated['currency'],
            'slug' => Str::slug($validated['name']) . '-' . rand(1000, 9999),
            'status' => 'active',
        ];

        $restaurant = Restaurant::create($restaurantData);

        // إنشاء الطاولات تلقائياً إذا تم تحديد عددها
        $tablesCount = (int) ($validated['tables_count'] ?? 0);
        if ($tablesCount > 0) {
            $tokenGen = app(\App\Services\TokenGenerator::class);
            $qrService = app(\App\Services\QrCodeService::class);
            for ($i = 1; $i <= $tablesCount; $i++) {
                $table = \App\Models\RestaurantTable::create([
                    'restaurant_id' => $restaurant->id,
                    'name' => 'طاولة ' . $i,
                    'public_token' => $tokenGen->generatePublicToken(),
                    'status' => 'active',
                ]);
                try {
                    $qrService->generateForTable($table);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('QR generation failed for table: ' . $e->getMessage());
                }
            }
        }

        $manager = \App\Models\User::create([
            'restaurant_id' => $restaurant->id,
            'name' => $validated['manager_name'],
            'email' => $validated['manager_email'],
            'password' => \Illuminate\Support\Facades\Hash::make($validated['manager_password'] ?: 'password'),
            'status' => \App\Enums\UserStatus::Active,
        ]);
        $manager->assignRole(\App\Enums\UserRole::Admin->value);

        return response()->json($restaurant, 201);
    }

    public function update(Request $request, Restaurant $restaurant)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|string|in:postpaid,prepaid',
            'windows_count' => 'sometimes|nullable|integer|min:1|max:50',
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
