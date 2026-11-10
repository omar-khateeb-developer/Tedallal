<?php

namespace Database\Seeders;

use App\Enums\RestaurantPlan;
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
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

class BazerkanRestaurantSeeder extends Seeder
{
    public function run(): void
    {
        $tokenGenerator = app(TokenGenerator::class);
        $qrService = app(QrCodeService::class);

        $this->command?->info('🚀 جاري استيراد مطاعم بازركان (Bazerkan)...');

        // 1. إنشاء أو تحديث مطعم بازركان
        $logo = '/storage/restaurants/bazerkan-logo.png';

        $restaurant = Restaurant::query()->updateOrCreate(
            ['slug' => 'bazerkan'],
            [
                'name'          => 'مطاعم بازركان',
                'type'          => 'postpaid',
                'windows_count' => 1,
                'phone'         => '+963982506666',
                'address'       => 'مجمع بازركان وجهتكم الأولى للطعام والترفيه - ريف دمشق، سوريا',
                'currency'      => 'SYP',
                'status'        => RestaurantStatus::Active,
                'plan'          => RestaurantPlan::Pro,
                'logo'          => $logo,
                'features'      => [
                    'promo_codes'    => true,
                    'customer_notes' => true,
                    'table_sessions' => true,
                    'activity_log'   => true,
                    'analytics'      => true,
                    'offers'         => true,
                    'kds'            => true,
                ],
            ]
        );

        $this->command?->info("✅ تم تثبيت بيانات المطعم: {$restaurant->name} (ID: {$restaurant->id})");

        // 2. مستخدمو إدارة بازركان
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@bazerkan.test'],
            [
                'restaurant_id'  => $restaurant->id,
                'name'           => 'مدير مطاعم بازركان',
                'password'       => Hash::make('password'),
                'status'         => UserStatus::Active,
                'default_window' => null,
            ]
        );
        $admin->syncRoles([UserRole::Admin->value]);

        $cashier = User::query()->updateOrCreate(
            ['email' => 'cashier@bazerkan.test'],
            [
                'restaurant_id'  => $restaurant->id,
                'name'           => 'كاشير مطاعم بازركان',
                'password'       => Hash::make('password'),
                'status'         => UserStatus::Active,
                'default_window' => null,
            ]
        );
        $cashier->syncRoles([UserRole::Cashier->value]);

        $chef = User::query()->updateOrCreate(
            ['email' => 'chef@bazerkan.test'],
            [
                'restaurant_id'  => $restaurant->id,
                'name'           => 'شيف مطبخ بازركان',
                'password'       => Hash::make('password'),
                'status'         => UserStatus::Active,
                'default_window' => null,
            ]
        );
        $chef->syncRoles([UserRole::Chef->value]);

        $this->command?->info('✅ تم إنشاء مستخدمي الإدارة، الكاشير، وشاشة المطبخ KDS (كلمة المرور: password).');

        // 3. استيراد التصنيفات والأصناف من البيانات المستخلصة
        $dataFile = database_path('seeders/data/bazerkan.json');
        if (! file_exists($dataFile)) {
            $dataFile = 'C:/Users/ASUS/.gemini/antigravity/brain/d252c7a9-d1be-4051-9046-25eeac8a68b9/bazerkan_with_images.json';
        }
        if (! file_exists($dataFile)) {
            $dataFile = 'C:/Users/ASUS/.gemini/antigravity/brain/d252c7a9-d1be-4051-9046-25eeac8a68b9/bazerkan_parsed.json';
        }

        $categoriesData = json_decode(file_get_contents($dataFile), true) ?: [];

        $categorySort = 1;
        $totalItemsCount = 0;

        foreach ($categoriesData as $catId => $cat) {
            $categoryName = trim($cat['name']);
            if (empty($categoryName) || $catId === 'all') {
                continue;
            }

            $category = MenuCategory::query()->updateOrCreate(
                [
                    'restaurant_id' => $restaurant->id,
                    'name'          => $categoryName,
                ],
                [
                    'description'   => "أشهى تشكيلة {$categoryName} من مطاعم بازركان",
                    'sort_order'    => $categorySort++,
                    'is_active'     => true,
                ]
            );

            $itemSort = 1;
            foreach ($cat['items'] as $itemData) {
                $itemName = trim($itemData['name']);
                if (empty($itemName)) continue;

                $desc = ! empty($itemData['description']) ? trim($itemData['description']) : null;
                $price = (float) ($itemData['price'] ?? 0);
                $localImage = $itemData['local_image'] ?? null;

                MenuItem::query()->updateOrCreate(
                    [
                        'restaurant_id' => $restaurant->id,
                        'category_id'   => $category->id,
                        'name'          => $itemName,
                    ],
                    [
                        'description'   => $desc,
                        'price'         => $price,
                        'image'         => $localImage,
                        'sort_order'    => $itemSort++,
                        'is_available'  => true,
                        'is_active'     => true,
                    ]
                );

                $totalItemsCount++;
            }

            $this->command?->line("  📁 تصنيف [{$categoryName}]: " . count($cat['items']) . " صنف");
        }

        $this->command?->info("✅ تم استيراد {$totalItemsCount} صنفاً بنجاح عبر " . (count($categoriesData) - 1) . " تصنيفاً.");

        // 4. إنشاء طاولات بازركان مع رموز الـ QR
        $tableNames = [
            'طاولة الصالة 1', 'طاولة الصالة 2', 'طاولة الصالة 3', 'طاولة الصالة 4',
            'طاولة العائلات 1', 'طاولة العائلات 2',
            'طاولة VIP 1', 'طاولة التراس 1',
        ];

        foreach ($tableNames as $tName) {
            $table = RestaurantTable::query()->firstOrCreate(
                ['restaurant_id' => $restaurant->id, 'name' => $tName],
                [
                    'public_token' => $tokenGenerator->generatePublicToken(),
                    'status'       => TableStatus::Active,
                ]
            );

            if (! $table->public_token) {
                $table->public_token = $tokenGenerator->generatePublicToken();
                $table->save();
            }

            // توليد رمز الـ QR
            $qrPath = "qr/{$table->restaurant_id}/{$table->public_token}.png";
            if (! Storage::disk('public')->exists($qrPath)) {
                $qrService->generateForTable($table);
            }
        }

        $this->command?->info('✅ تم إنشاء طاولات بازركان وتوليد رموز الـ QR Code بنجاح.');
        $firstTable = RestaurantTable::where('restaurant_id', $restaurant->id)->first();
        if ($firstTable) {
            $this->command?->info("🔗 رابط طاولة تجريبية لبازركان: " . url('/t/' . $firstTable->public_token));
        }
    }
}
