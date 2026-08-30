<?php

namespace Database\Seeders;

use App\Enums\RestaurantStatus;
use App\Enums\TableStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\User;
use App\Services\QrCodeService;
use App\Services\TokenGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $restaurant = Restaurant::query()->create([
            'name' => 'مطعم تدلّل',
            'slug' => 'tedallal',
            'phone' => '+966500000000',
            'address' => 'الرياض، المملكة العربية السعودية',
            'currency' => 'SAR',
            'status' => RestaurantStatus::Active,
        ]);

        User::query()->create([
            'restaurant_id' => null,
            'name' => 'مدير النظام (Super Admin)',
            'email' => 'super@tedallal.test',
            'password' => Hash::make('password'),
            'role' => UserRole::SuperAdmin,
            'status' => UserStatus::Active,
        ]);
        //edited to test ci/cd
        User::query()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'مدير النظام',
            'email' => 'admin@tedallal.test',
            'password' => Hash::make('password'),
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
        ]);

        User::query()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'موظف المطعم',
            'email' => 'staff@tedallal.test',
            'password' => Hash::make('password'),
            'role' => UserRole::Staff,
            'status' => UserStatus::Active,
        ]);

        $tokenGenerator = app(TokenGenerator::class);
        $qrService = app(QrCodeService::class);

        foreach (['طاولة 1', 'طاولة 2', 'طاولة 3', 'طاولة 4', 'طاولة 5'] as $tableName) {
            $table = RestaurantTable::query()->create([
                'restaurant_id' => $restaurant->id,
                'name' => $tableName,
                'public_token' => $tokenGenerator->generatePublicToken(),
                'status' => TableStatus::Active,
            ]);
            $qrService->generateForTable($table);
        }

        $categories = [
            ['name' => 'مشروبات', 'items' => [
                ['name' => 'قهوة عربية', 'price' => '12.00', 'description' => 'قهوة عربية أصيلة'],
                ['name' => 'عصير برتقال طازج', 'price' => '18.00', 'description' => null],
                ['name' => 'ماء', 'price' => '3.00', 'description' => null],
            ]],
            ['name' => 'مقبلات', 'items' => [
                ['name' => 'حمص', 'price' => '15.00', 'description' => 'حمص بالطحينة'],
                ['name' => 'متبل', 'price' => '14.00', 'description' => 'باذنجان مشوي'],
                ['name' => 'سلطة فتوش', 'price' => '22.00', 'description' => null],
            ]],
            ['name' => 'أطباق رئيسية', 'items' => [
                ['name' => 'كبسة دجاج', 'price' => '45.00', 'description' => 'كبسة دجاج سعودية'],
                ['name' => 'مandi لحم', 'price' => '55.00', 'description' => 'مandi لحم تقليدي'],
                ['name' => 'شاورما', 'price' => '28.00', 'description' => 'شاورما دجاج'],
            ]],
            ['name' => 'حلويات', 'items' => [
                ['name' => 'كنافة', 'price' => '20.00', 'description' => null],
                ['name' => 'بقلاوة', 'price' => '18.00', 'description' => 'تشكيلة بقلاوة'],
            ]],
        ];

        foreach ($categories as $index => $categoryData) {
            $category = MenuCategory::query()->create([
                'restaurant_id' => $restaurant->id,
                'name' => $categoryData['name'],
                'sort_order' => $index + 1,
                'is_active' => true,
            ]);

            foreach ($categoryData['items'] as $itemIndex => $itemData) {
                MenuItem::query()->create([
                    'restaurant_id' => $restaurant->id,
                    'category_id' => $category->id,
                    'name' => $itemData['name'],
                    'description' => $itemData['description'],
                    'price' => $itemData['price'],
                    'sort_order' => $itemIndex + 1,
                    'is_available' => true,
                    'is_active' => true,
                ]);
            }
        }
    }
}
