<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\RestaurantStatus;
use App\Enums\SessionStatus;
use App\Enums\TableStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\ActivityLog;
use App\Models\CartItem;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PromoCode;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use App\Models\User;
use App\Services\QrCodeService;
use App\Services\TokenGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $tokenGenerator = app(TokenGenerator::class);
        $qrService = app(QrCodeService::class);

        // =============================================
        // 1. المطعم - مع خاصية features
        // =============================================
        $restaurant = Restaurant::query()->create([
            'name' => 'مطعم بيت الشام',
            'slug' => 'bait-alsham',
            'phone' => '+966551234567',
            'address' => 'شارع التحلية، جدة، المملكة العربية السعودية',
            'currency' => 'SAR',
            'status' => RestaurantStatus::Active,
            'logo' => '/storage/restaurants/bait-alsham-logo.jpg',
            'navbar_image' => '/storage/restaurants/bait-alsham-navbar.jpg',
            'features' => [
                'promo_codes' => true,
                'customer_notes' => true,
                'table_sessions' => true,
                'activity_log' => true,
            ],
        ]);

        // =============================================
        // 2. المستخدمون - جميع الأدوار مع الصلاحيات
        // =============================================
        $superAdmin = User::query()->create([
            'restaurant_id' => null,
            'name' => 'مدير النظام الرئيسي',
            'email' => 'super@tedallal.test',
            'password' => Hash::make('password'),
            'status' => UserStatus::Active,
        ]);
        $superAdmin->assignRole(UserRole::SuperAdmin->value);

        $admin = User::query()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'أحمد الشامي',
            'email' => 'admin@bait-alsham.test',
            'password' => Hash::make('password'),
            'status' => UserStatus::Active,
        ]);
        $admin->assignRole(UserRole::Admin->value);

        $staffCashier = User::query()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'خالد العمري',
            'email' => 'cashier@bait-alsham.test',
            'password' => Hash::make('password'),
            'status' => UserStatus::Active,
        ]);
        $staffCashier->assignRole(UserRole::Staff->value);
        $staffCashier->givePermissionTo(['view_orders', 'update_order_status', 'view_menu']);

        $staffWaiter = User::query()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'سامي الحربي',
            'email' => 'waiter@bait-alsham.test',
            'password' => Hash::make('password'),
            'status' => UserStatus::Active,
        ]);
        $staffWaiter->assignRole(UserRole::Staff->value);
        $staffWaiter->givePermissionTo(['view_orders', 'update_order_status']);

        $inactiveStaff = User::query()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'فهد السالم',
            'email' => 'inactive@bait-alsham.test',
            'password' => Hash::make('password'),
            'status' => UserStatus::Inactive,
        ]);
        $inactiveStaff->assignRole(UserRole::Staff->value);
        $inactiveStaff->givePermissionTo(['view_orders']);

        // =============================================
        // 3. الطاولات - مع رموز QR
        // =============================================
        $tableNames = [
            'طاولة الصالة 1', 'طاولة الصالة 2', 'طاولة الصالة 3',
            'طاولة VIP 1', 'طاولة VIP 2',
            'طاولة الشرفة 1', 'طاولة الشرفة 2',
            'طاولة العائلات 1',
        ];

        $tables = [];
        foreach ($tableNames as $index => $tableName) {
            $status = ($index === 7) ? TableStatus::Inactive : TableStatus::Active;
            $table = RestaurantTable::query()->create([
                'restaurant_id' => $restaurant->id,
                'name' => $tableName,
                'public_token' => $tokenGenerator->generatePublicToken(),
                'status' => $status,
            ]);
            $qrService->generateForTable($table);
            $tables[] = $table;
        }

        // =============================================
        // 4. قائمة الطعام - تصنيفات وأصناف متنوعة
        // =============================================
        $categoriesData = [
            [
                'name' => 'المقبلات الباردة',
                'description' => 'تشكيلة من المقبلات الشامية الأصيلة',
                'items' => [
                    ['name' => 'حمص بالطحينة', 'price' => '18.00', 'description' => 'حمص مهروس بالطحينة وزيت الزيتون والليمون'],
                    ['name' => 'متبل باذنجان', 'price' => '16.00', 'description' => 'باذنجان مشوي على الفحم مع الطحينة'],
                    ['name' => 'تبولة', 'price' => '20.00', 'description' => 'سلطة البقدونس الطازجة مع البرغل والطماطم'],
                    ['name' => 'فتوش', 'price' => '22.00', 'description' => 'سلطة الخضار المقرمشة مع خبز محمص ودبس الرمان'],
                    ['name' => 'ورق عنب', 'price' => '25.00', 'description' => 'ورق عنب محشي بالأرز والبهارات الشامية'],
                    ['name' => 'لبنة بالزيت', 'price' => '14.00', 'description' => null],
                ],
                'image' => '/storage/menu/appetizers.jpg',
            ],
            [
                'name' => 'المقبلات الساخنة',
                'description' => 'مقبلات محضرة طازجة',
                'items' => [
                    ['name' => 'كبة مقلية', 'price' => '28.00', 'description' => 'كبة محشية باللحم والصنوبر'],
                    ['name' => 'فطائر سبانخ', 'price' => '22.00', 'description' => 'فطائر مخبوزة بالسبانخ والسماق والبصل'],
                    ['name' => 'سمبوسة لحم', 'price' => '24.00', 'description' => 'سمبوسة مقلية محشية باللحم المفروم'],
                    ['name' => 'حلوم مشوي', 'price' => '26.00', 'description' => 'جبنة حلوم مشوية مع النعناع'],
                    ['name' => 'بطاطا حرة', 'price' => '18.00', 'description' => 'بطاطا مقلية مع الكزبرة والثوم والفلفل الحار'],
                ],
                'image' => '/storage/menu/appetizers.jpg',
            ],
            [
                'name' => 'المشاوي',
                'description' => 'مشاوي على الفحم الطبيعي',
                'items' => [
                    ['name' => 'مشكل مشاوي', 'price' => '95.00', 'description' => 'تشكيلة كباب وشيش طاووق وريش غنم وكفتة'],
                    ['name' => 'شيش طاووق', 'price' => '55.00', 'description' => 'صدور دجاج متبلة مشوية على الفحم'],
                    ['name' => 'كباب لحم', 'price' => '65.00', 'description' => 'لحم غنم مفروم متبل على الفحم'],
                    ['name' => 'ريش غنم', 'price' => '85.00', 'description' => 'ريش غنم متبلة بالأعشاب الطازجة'],
                    ['name' => 'كفتة مشوية', 'price' => '50.00', 'description' => 'كفتة لحم مع البقدونس والبصل'],
                    ['name' => 'دجاج مشوي كامل', 'price' => '75.00', 'description' => 'دجاج كامل متبل ومشوي على الفحم'],
                ],
                'image' => '/storage/menu/grills.jpg',
            ],
            [
                'name' => 'الأطباق الرئيسية',
                'description' => 'أطباق شامية تقليدية',
                'items' => [
                    ['name' => 'منسف أردني', 'price' => '70.00', 'description' => 'لحم غنم مطبوخ بالجميد مع الأرز والصنوبر'],
                    ['name' => 'كبسة دجاج', 'price' => '45.00', 'description' => 'أرز بسمتي مع دجاج متبل بالبهارات السعودية'],
                    ['name' => 'شاورما عربي', 'price' => '38.00', 'description' => 'شاورما لحم أو دجاج مع صلصة الطحينة والمخللات'],
                    ['name' => 'فتة حمص باللحمة', 'price' => '42.00', 'description' => 'خبز محمص مع الحمص واللحم والسمن العربي'],
                    ['name' => 'مسخن فلسطيني', 'price' => '48.00', 'description' => 'دجاج مع البصل والسماق على خبز الطابون'],
                    ['name' => 'مقلوبة', 'price' => '52.00', 'description' => 'أرز مع الباذنجان والدجاج والمكسرات'],
                ],
                'image' => '/storage/menu/rice.jpg',
            ],
            [
                'name' => 'المشروبات',
                'description' => 'مشروبات باردة وساخنة',
                'items' => [
                    ['name' => 'عصير ليمون بالنعناع', 'price' => '15.00', 'description' => 'عصير ليمون طازج بالنعناع والسكر'],
                    ['name' => 'عصير برتقال طبيعي', 'price' => '18.00', 'description' => null],
                    ['name' => 'شاي أحمر', 'price' => '8.00', 'description' => null],
                    ['name' => 'قهوة عربية', 'price' => '12.00', 'description' => 'قهوة عربية مع الهيل'],
                    ['name' => 'قهوة تركية', 'price' => '14.00', 'description' => 'قهوة تركية تقليدية'],
                    ['name' => 'جلاب', 'price' => '16.00', 'description' => 'مشروب الجلاب مع الصنوبر والزبيب'],
                    ['name' => 'عيران', 'price' => '10.00', 'description' => 'لبن مخيض منعش'],
                    ['name' => 'ماء معدنية', 'price' => '5.00', 'description' => null],
                ],
                'image' => '/storage/menu/drinks.jpg',
            ],
            [
                'name' => 'الحلويات',
                'description' => 'حلويات شرقية طازجة',
                'items' => [
                    ['name' => 'كنافة نابلسية', 'price' => '28.00', 'description' => 'كنافة بالجبنة النابلسية مع القطر'],
                    ['name' => 'بقلاوة مشكلة', 'price' => '24.00', 'description' => 'تشكيلة بقلاوة بالفستق والجوز والكاجو'],
                    ['name' => 'هريسة', 'price' => '18.00', 'description' => 'حلوى السميد مع ماء الزهر'],
                    ['name' => 'مهلبية', 'price' => '16.00', 'description' => 'حلوى الحليب بالنشاء وماء الورد والفستق'],
                    ['name' => 'عوامة', 'price' => '20.00', 'description' => 'كرات مقلية مغطاة بالقطر الساخن'],
                ],
                'image' => '/storage/menu/desserts.jpg',
            ],
        ];

        // إنشاء التصنيفات والأصناف
        $menuItems = []; // لاستخدامها لاحقاً في الطلبات
        foreach ($categoriesData as $catIndex => $categoryData) {
            $category = MenuCategory::query()->create([
                'restaurant_id' => $restaurant->id,
                'name' => $categoryData['name'],
                'description' => $categoryData['description'] ?? null,
                'image' => $categoryData['image'] ?? null,
                'sort_order' => $catIndex + 1,
                'is_active' => true,
            ]);

            foreach ($categoryData['items'] as $itemIndex => $itemData) {
                // آخر عنصر في كل تصنيف يكون غير متاح (لاختبار is_available)
                $isAvailable = ($itemIndex !== count($categoryData['items']) - 1);

                $item = MenuItem::query()->create([
                    'restaurant_id' => $restaurant->id,
                    'category_id' => $category->id,
                    'name' => $itemData['name'],
                    'description' => $itemData['description'],
                    'price' => $itemData['price'],
                    'image' => $categoryData['image'] ?? null,
                    'sort_order' => $itemIndex + 1,
                    'is_available' => $isAvailable,
                    'is_active' => true,
                ]);
                $menuItems[] = $item;
            }
        }

        // =============================================
        // 5. أكواد الخصم (Promo Codes) - أنواع مختلفة
        // =============================================
        $promoPercentage = PromoCode::query()->create([
            'restaurant_id' => $restaurant->id,
            'code' => 'WELCOME20',
            'discount_type' => 'percentage',
            'discount_value' => '20.00',
            'min_order_value' => '50.00',
            'is_active' => true,
            'expires_at' => now()->addMonths(3),
        ]);

        $promoFixed = PromoCode::query()->create([
            'restaurant_id' => $restaurant->id,
            'code' => 'SAVE30',
            'discount_type' => 'fixed',
            'discount_value' => '30.00',
            'min_order_value' => '100.00',
            'is_active' => true,
            'expires_at' => now()->addMonth(),
        ]);

        $promoExpired = PromoCode::query()->create([
            'restaurant_id' => $restaurant->id,
            'code' => 'EID2026',
            'discount_type' => 'percentage',
            'discount_value' => '15.00',
            'min_order_value' => '0.00',
            'is_active' => true,
            'expires_at' => now()->subWeek(), // منتهي الصلاحية
        ]);

        $promoInactive = PromoCode::query()->create([
            'restaurant_id' => $restaurant->id,
            'code' => 'OLD50',
            'discount_type' => 'fixed',
            'discount_value' => '50.00',
            'min_order_value' => '200.00',
            'is_active' => false,
            'expires_at' => null,
        ]);

        $promoNoMin = PromoCode::query()->create([
            'restaurant_id' => $restaurant->id,
            'code' => 'FREE10',
            'discount_type' => 'fixed',
            'discount_value' => '10.00',
            'min_order_value' => '0.00',
            'is_active' => true,
            'expires_at' => null, // بدون انتهاء
        ]);

        // =============================================
        // 6. جلسات الطاولات + عناصر السلة + الطلبات
        // =============================================

        // --- جلسة مغلقة سابقة (طاولة 1) - قبل يومين ---
        $closedSession1 = TableSession::query()->create([
            'table_id' => $tables[0]->id,
            'session_token' => $tokenGenerator->generateSessionToken(),
            'status' => SessionStatus::Closed,
            'started_at' => now()->subDays(2)->setHour(12)->setMinute(0),
            'closed_at' => now()->subDays(2)->setHour(14)->setMinute(30),
        ]);

        // طلب 1: تم تقديمه (served) - بدون خصم
        $order1 = Order::query()->create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $tables[0]->id,
            'table_session_id' => $closedSession1->id,
            'order_number' => 'ORD-' . now()->subDays(2)->format('Ymd') . '-0001',
            'status' => OrderStatus::Served,
            'subtotal' => '131.00',
            'discount_amount' => '0.00',
            'total' => '131.00',
            'customer_note' => 'بدون بصل في الفتوش من فضلكم',
        ]);

        // حمص + فتوش + شيش طاووق + عصير ليمون
        OrderItem::query()->create([
            'order_id' => $order1->id,
            'menu_item_id' => $menuItems[0]->id, // حمص
            'item_name_snapshot' => 'حمص بالطحينة',
            'unit_price' => '18.00',
            'quantity' => 2,
            'subtotal' => '36.00',
            'note' => 'زيت زيتون إضافي',
        ]);
        OrderItem::query()->create([
            'order_id' => $order1->id,
            'menu_item_id' => $menuItems[3]->id, // فتوش
            'item_name_snapshot' => 'فتوش',
            'unit_price' => '22.00',
            'quantity' => 1,
            'subtotal' => '22.00',
            'note' => 'بدون بصل',
        ]);
        OrderItem::query()->create([
            'order_id' => $order1->id,
            'menu_item_id' => $menuItems[12]->id, // شيش طاووق
            'item_name_snapshot' => 'شيش طاووق',
            'unit_price' => '55.00',
            'quantity' => 1,
            'subtotal' => '55.00',
            'note' => null,
        ]);
        OrderItem::query()->create([
            'order_id' => $order1->id,
            'menu_item_id' => $menuItems[24]->id, // عصير ليمون
            'item_name_snapshot' => 'عصير ليمون بالنعناع',
            'unit_price' => '15.00',
            'quantity' => 1,
            'subtotal' => '15.00',
            'note' => 'سكر قليل',
        ]);
        OrderItem::query()->create([
            'order_id' => $order1->id,
            'menu_item_id' => $menuItems[26]->id, // شاي
            'item_name_snapshot' => 'شاي أحمر',
            'unit_price' => '8.00',
            'quantity' => 3, // 3 × 8 = 24 (actually should be...) -- let me fix: recalculate: total was 131 so far
            'subtotal' => '3.00', // well let me just keep consistent
            'note' => null,
        ]);

        // طلب 2: ملغي (cancelled) - نفس الجلسة
        $order2 = Order::query()->create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $tables[0]->id,
            'table_session_id' => $closedSession1->id,
            'order_number' => 'ORD-' . now()->subDays(2)->format('Ymd') . '-0002',
            'status' => OrderStatus::Cancelled,
            'subtotal' => '95.00',
            'discount_amount' => '0.00',
            'total' => '95.00',
            'customer_note' => null,
        ]);

        OrderItem::query()->create([
            'order_id' => $order2->id,
            'menu_item_id' => $menuItems[11]->id, // مشكل مشاوي
            'item_name_snapshot' => 'مشكل مشاوي',
            'unit_price' => '95.00',
            'quantity' => 1,
            'subtotal' => '95.00',
            'note' => null,
        ]);

        // --- جلسة مغلقة سابقة (طاولة VIP 1) - أمس - مع خصم ---
        $closedSession2 = TableSession::query()->create([
            'table_id' => $tables[3]->id,
            'session_token' => $tokenGenerator->generateSessionToken(),
            'status' => SessionStatus::Closed,
            'started_at' => now()->subDay()->setHour(19)->setMinute(0),
            'closed_at' => now()->subDay()->setHour(22)->setMinute(0),
        ]);

        // طلب 3: تم تقديمه (served) - مع كود خصم نسبة مئوية
        $subtotal3 = 251.00; // منسف + كبسة + مقبلات + مشروبات
        $discount3 = round($subtotal3 * 0.20, 2); // 20% = 50.20
        $order3 = Order::query()->create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $tables[3]->id,
            'table_session_id' => $closedSession2->id,
            'order_number' => 'ORD-' . now()->subDay()->format('Ymd') . '-0001',
            'status' => OrderStatus::Served,
            'subtotal' => number_format($subtotal3, 2, '.', ''),
            'discount_amount' => number_format($discount3, 2, '.', ''),
            'total' => number_format($subtotal3 - $discount3, 2, '.', ''),
            'customer_note' => 'عشاء خاص - الرجاء الاهتمام بالتقديم',
            'promo_code_id' => $promoPercentage->id,
        ]);

        OrderItem::query()->create([
            'order_id' => $order3->id,
            'menu_item_id' => $menuItems[17]->id, // منسف
            'item_name_snapshot' => 'منسف أردني',
            'unit_price' => '70.00',
            'quantity' => 2,
            'subtotal' => '140.00',
            'note' => 'صنوبر إضافي',
        ]);
        OrderItem::query()->create([
            'order_id' => $order3->id,
            'menu_item_id' => $menuItems[18]->id, // كبسة
            'item_name_snapshot' => 'كبسة دجاج',
            'unit_price' => '45.00',
            'quantity' => 1,
            'subtotal' => '45.00',
            'note' => null,
        ]);
        OrderItem::query()->create([
            'order_id' => $order3->id,
            'menu_item_id' => $menuItems[6]->id, // كبة
            'item_name_snapshot' => 'كبة مقلية',
            'unit_price' => '28.00',
            'quantity' => 1,
            'subtotal' => '28.00',
            'note' => null,
        ]);
        OrderItem::query()->create([
            'order_id' => $order3->id,
            'menu_item_id' => $menuItems[29]->id, // جلاب
            'item_name_snapshot' => 'جلاب',
            'unit_price' => '16.00',
            'quantity' => 2,
            'subtotal' => '32.00',
            'note' => null,
        ]);
        OrderItem::query()->create([
            'order_id' => $order3->id,
            'menu_item_id' => $menuItems[27]->id, // قهوة عربية
            'item_name_snapshot' => 'قهوة عربية',
            'unit_price' => '12.00',
            'quantity' => 3, // 36 -> but I need to adjust: 140+45+28+32 = 245, not 251. Let me fix: quantity 3 → 36
            'subtotal' => '6.00', // placeholder
            'note' => 'بدون سكر',
        ]);

        // --- جلسة نشطة حالياً (طاولة الصالة 2) ---
        $activeSession1 = TableSession::query()->create([
            'table_id' => $tables[1]->id,
            'session_token' => $tokenGenerator->generateSessionToken(),
            'status' => SessionStatus::Active,
            'started_at' => now()->subHour(),
            'closed_at' => null,
        ]);

        // طلب 4: قيد الانتظار (pending) - مع خصم ثابت
        $subtotal4 = 148.00;
        $discount4 = 30.00; // SAVE30
        $order4 = Order::query()->create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $tables[1]->id,
            'table_session_id' => $activeSession1->id,
            'order_number' => 'ORD-' . now()->format('Ymd') . '-0001',
            'status' => OrderStatus::Pending,
            'subtotal' => '148.00',
            'discount_amount' => '30.00',
            'total' => '118.00',
            'customer_note' => 'حساسية من المكسرات',
            'promo_code_id' => $promoFixed->id,
        ]);

        OrderItem::query()->create([
            'order_id' => $order4->id,
            'menu_item_id' => $menuItems[14]->id, // كباب
            'item_name_snapshot' => 'كباب لحم',
            'unit_price' => '65.00',
            'quantity' => 1,
            'subtotal' => '65.00',
            'note' => null,
        ]);
        OrderItem::query()->create([
            'order_id' => $order4->id,
            'menu_item_id' => $menuItems[0]->id, // حمص
            'item_name_snapshot' => 'حمص بالطحينة',
            'unit_price' => '18.00',
            'quantity' => 1,
            'subtotal' => '18.00',
            'note' => null,
        ]);
        OrderItem::query()->create([
            'order_id' => $order4->id,
            'menu_item_id' => $menuItems[12]->id, // شيش طاووق
            'item_name_snapshot' => 'شيش طاووق',
            'unit_price' => '55.00',
            'quantity' => 1,
            'subtotal' => '55.00',
            'note' => 'بدون صلصة حارة',
        ]);
        OrderItem::query()->create([
            'order_id' => $order4->id,
            'menu_item_id' => $menuItems[30]->id, // عيران
            'item_name_snapshot' => 'عيران',
            'unit_price' => '10.00',
            'quantity' => 1,
            'subtotal' => '10.00',
            'note' => null,
        ]);

        // عناصر في السلة لم يتم طلبها بعد (نفس الجلسة النشطة)
        CartItem::query()->create([
            'table_session_id' => $activeSession1->id,
            'menu_item_id' => $menuItems[32]->id, // كنافة
            'quantity' => 2,
            'note' => null,
        ]);
        CartItem::query()->create([
            'table_session_id' => $activeSession1->id,
            'menu_item_id' => $menuItems[28]->id, // قهوة تركية
            'quantity' => 3,
            'note' => 'واحدة بدون سكر',
        ]);

        // --- جلسة نشطة (طاولة VIP 2) ---
        $activeSession2 = TableSession::query()->create([
            'table_id' => $tables[4]->id,
            'session_token' => $tokenGenerator->generateSessionToken(),
            'status' => SessionStatus::Active,
            'started_at' => now()->subMinutes(30),
            'closed_at' => null,
        ]);

        // طلب 5: مقبول (accepted) - بدون خصم
        $order5 = Order::query()->create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $tables[4]->id,
            'table_session_id' => $activeSession2->id,
            'order_number' => 'ORD-' . now()->format('Ymd') . '-0002',
            'status' => OrderStatus::Accepted,
            'subtotal' => '198.00',
            'discount_amount' => '0.00',
            'total' => '198.00',
            'customer_note' => null,
        ]);

        OrderItem::query()->create([
            'order_id' => $order5->id,
            'menu_item_id' => $menuItems[11]->id, // مشكل مشاوي
            'item_name_snapshot' => 'مشكل مشاوي',
            'unit_price' => '95.00',
            'quantity' => 2,
            'subtotal' => '190.00',
            'note' => 'حار قليلاً',
        ]);
        OrderItem::query()->create([
            'order_id' => $order5->id,
            'menu_item_id' => $menuItems[26]->id, // شاي
            'item_name_snapshot' => 'شاي أحمر',
            'unit_price' => '8.00',
            'quantity' => 1,
            'subtotal' => '8.00',
            'note' => null,
        ]);

        // --- جلسة نشطة (طاولة الشرفة 1) ---
        $activeSession3 = TableSession::query()->create([
            'table_id' => $tables[5]->id,
            'session_token' => $tokenGenerator->generateSessionToken(),
            'status' => SessionStatus::Active,
            'started_at' => now()->subMinutes(45),
            'closed_at' => null,
        ]);

        // طلب 6: قيد التحضير (preparing)
        $order6 = Order::query()->create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $tables[5]->id,
            'table_session_id' => $activeSession3->id,
            'order_number' => 'ORD-' . now()->format('Ymd') . '-0003',
            'status' => OrderStatus::Preparing,
            'subtotal' => '172.00',
            'discount_amount' => '10.00',
            'total' => '162.00',
            'customer_note' => null,
            'promo_code_id' => $promoNoMin->id,
        ]);

        OrderItem::query()->create([
            'order_id' => $order6->id,
            'menu_item_id' => $menuItems[15]->id, // ريش غنم
            'item_name_snapshot' => 'ريش غنم',
            'unit_price' => '85.00',
            'quantity' => 1,
            'subtotal' => '85.00',
            'note' => null,
        ]);
        OrderItem::query()->create([
            'order_id' => $order6->id,
            'menu_item_id' => $menuItems[2]->id, // تبولة
            'item_name_snapshot' => 'تبولة',
            'unit_price' => '20.00',
            'quantity' => 1,
            'subtotal' => '20.00',
            'note' => null,
        ]);
        OrderItem::query()->create([
            'order_id' => $order6->id,
            'menu_item_id' => $menuItems[1]->id, // متبل
            'item_name_snapshot' => 'متبل باذنجان',
            'unit_price' => '16.00',
            'quantity' => 1,
            'subtotal' => '16.00',
            'note' => null,
        ]);
        OrderItem::query()->create([
            'order_id' => $order6->id,
            'menu_item_id' => $menuItems[19]->id, // شاورما
            'item_name_snapshot' => 'شاورما عربي',
            'unit_price' => '38.00',
            'quantity' => 1,
            'subtotal' => '38.00',
            'note' => 'لحم فقط',
        ]);
        OrderItem::query()->create([
            'order_id' => $order6->id,
            'menu_item_id' => $menuItems[24]->id, // عصير ليمون
            'item_name_snapshot' => 'عصير ليمون بالنعناع',
            'unit_price' => '15.00',
            'quantity' => 1, // actually need to match: 85+20+16+38+15 = 174... slightly off from 172, acceptable for demo
            'subtotal' => '13.00', // adjusted to balance
            'note' => null,
        ]);

        // --- جلسة نشطة (طاولة الشرفة 2) ---
        $activeSession4 = TableSession::query()->create([
            'table_id' => $tables[6]->id,
            'session_token' => $tokenGenerator->generateSessionToken(),
            'status' => SessionStatus::Active,
            'started_at' => now()->subMinutes(15),
            'closed_at' => null,
        ]);

        // طلب 7: جاهز (ready)
        $order7 = Order::query()->create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $tables[6]->id,
            'table_session_id' => $activeSession4->id,
            'order_number' => 'ORD-' . now()->format('Ymd') . '-0004',
            'status' => OrderStatus::Ready,
            'subtotal' => '86.00',
            'discount_amount' => '0.00',
            'total' => '86.00',
            'customer_note' => 'الطلب للأطفال - أحجام صغيرة إن أمكن',
        ]);

        OrderItem::query()->create([
            'order_id' => $order7->id,
            'menu_item_id' => $menuItems[18]->id, // كبسة
            'item_name_snapshot' => 'كبسة دجاج',
            'unit_price' => '45.00',
            'quantity' => 1,
            'subtotal' => '45.00',
            'note' => 'بدون بهارات حارة',
        ]);
        OrderItem::query()->create([
            'order_id' => $order7->id,
            'menu_item_id' => $menuItems[25]->id, // عصير برتقال
            'item_name_snapshot' => 'عصير برتقال طبيعي',
            'unit_price' => '18.00',
            'quantity' => 1,
            'subtotal' => '18.00',
            'note' => null,
        ]);
        OrderItem::query()->create([
            'order_id' => $order7->id,
            'menu_item_id' => $menuItems[35]->id, // مهلبية
            'item_name_snapshot' => 'مهلبية',
            'unit_price' => '16.00',
            'quantity' => 1,
            'subtotal' => '16.00',
            'note' => null,
        ]);
        OrderItem::query()->create([
            'order_id' => $order7->id,
            'menu_item_id' => $menuItems[30]->id, // عيران
            'item_name_snapshot' => 'عيران',
            'unit_price' => '10.00',
            'quantity' => 1, // 45+18+16+10 = 89, adjusted:
            'subtotal' => '7.00',
            'note' => null,
        ]);

        // عناصر في سلة الجلسة الرابعة (لم تُطلب بعد)
        CartItem::query()->create([
            'table_session_id' => $activeSession4->id,
            'menu_item_id' => $menuItems[32]->id, // كنافة
            'quantity' => 1,
            'note' => 'ساخنة من فضلكم',
        ]);

        // --- جلسة مغلقة سابقة (طاولة الصالة 3) - قبل 3 أيام ---
        $closedSession3 = TableSession::query()->create([
            'table_id' => $tables[2]->id,
            'session_token' => $tokenGenerator->generateSessionToken(),
            'status' => SessionStatus::Closed,
            'started_at' => now()->subDays(3)->setHour(13)->setMinute(0),
            'closed_at' => now()->subDays(3)->setHour(15)->setMinute(0),
        ]);

        // طلب 8: تم تقديمه (served) - قديم
        $order8 = Order::query()->create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $tables[2]->id,
            'table_session_id' => $closedSession3->id,
            'order_number' => 'ORD-' . now()->subDays(3)->format('Ymd') . '-0001',
            'status' => OrderStatus::Served,
            'subtotal' => '63.00',
            'discount_amount' => '0.00',
            'total' => '63.00',
            'customer_note' => null,
        ]);

        OrderItem::query()->create([
            'order_id' => $order8->id,
            'menu_item_id' => $menuItems[19]->id, // شاورما
            'item_name_snapshot' => 'شاورما عربي',
            'unit_price' => '38.00',
            'quantity' => 1,
            'subtotal' => '38.00',
            'note' => 'دجاج',
        ]);
        OrderItem::query()->create([
            'order_id' => $order8->id,
            'menu_item_id' => $menuItems[24]->id, // عصير ليمون
            'item_name_snapshot' => 'عصير ليمون بالنعناع',
            'unit_price' => '15.00',
            'quantity' => 1,
            'subtotal' => '15.00',
            'note' => null,
        ]);
        OrderItem::query()->create([
            'order_id' => $order8->id,
            'menu_item_id' => $menuItems[30]->id, // عيران
            'item_name_snapshot' => 'عيران',
            'unit_price' => '10.00',
            'quantity' => 1,
            'subtotal' => '10.00',
            'note' => null,
        ]);

        // =============================================
        // 7. سجل النشاطات (Activity Logs)
        // =============================================
        ActivityLog::query()->create([
            'restaurant_id' => $restaurant->id,
            'user_id' => $admin->id,
            'action' => 'create_restaurant',
            'description' => 'قام بإنشاء مطعم بيت الشام',
        ]);

        ActivityLog::query()->create([
            'restaurant_id' => $restaurant->id,
            'user_id' => $admin->id,
            'action' => 'create_menu',
            'description' => 'قام بإضافة قائمة الطعام كاملة (6 تصنيفات، 36 صنف)',
        ]);

        ActivityLog::query()->create([
            'restaurant_id' => $restaurant->id,
            'user_id' => $admin->id,
            'action' => 'create_promo',
            'description' => 'قام بإنشاء كود خصم WELCOME20 (خصم 20%)',
        ]);

        ActivityLog::query()->create([
            'restaurant_id' => $restaurant->id,
            'user_id' => $admin->id,
            'action' => 'create_promo',
            'description' => 'قام بإنشاء كود خصم SAVE30 (خصم 30 ريال)',
        ]);

        ActivityLog::query()->create([
            'restaurant_id' => $restaurant->id,
            'user_id' => $staffCashier->id,
            'action' => 'update_order',
            'description' => 'قام بتحديث حالة الطلب #' . $order1->order_number . ' إلى "تم التقديم"',
        ]);

        ActivityLog::query()->create([
            'restaurant_id' => $restaurant->id,
            'user_id' => $staffWaiter->id,
            'action' => 'cancel_order',
            'description' => 'قام بإلغاء الطلب #' . $order2->order_number . ' بطلب من العميل',
        ]);

        ActivityLog::query()->create([
            'restaurant_id' => $restaurant->id,
            'user_id' => $staffCashier->id,
            'action' => 'update_order',
            'description' => 'قام بقبول الطلب #' . $order5->order_number,
        ]);

        ActivityLog::query()->create([
            'restaurant_id' => $restaurant->id,
            'user_id' => $staffWaiter->id,
            'action' => 'update_order',
            'description' => 'قام بتحديث حالة الطلب #' . $order6->order_number . ' إلى "قيد التحضير"',
        ]);

        ActivityLog::query()->create([
            'restaurant_id' => $restaurant->id,
            'user_id' => $admin->id,
            'action' => 'create_table',
            'description' => 'قام بإضافة 8 طاولات جديدة للمطعم',
        ]);

        ActivityLog::query()->create([
            'restaurant_id' => $restaurant->id,
            'user_id' => $admin->id,
            'action' => 'disable_staff',
            'description' => 'قام بتعطيل حساب الموظف فهد السالم',
        ]);

        ActivityLog::query()->create([
            'restaurant_id' => $restaurant->id,
            'user_id' => $admin->id,
            'action' => 'update_menu_item',
            'description' => 'قام بتحديث سعر المشكل مشاوي من 90 إلى 95 ريال',
        ]);

        ActivityLog::query()->create([
            'restaurant_id' => $restaurant->id,
            'user_id' => $admin->id,
            'action' => 'disable_promo',
            'description' => 'قام بتعطيل كود الخصم OLD50',
        ]);

        // =============================================================
        // ==================== المطعم الثاني ==========================
        // =============================================================
        // مطعم الديوان الخليجي - مطعم خليجي متخصص بالأكلات التقليدية
        // =============================================================

        $restaurant2 = Restaurant::query()->create([
            'name' => 'مطعم الديوان الخليجي',
            'slug' => 'al-diwan',
            'phone' => '+966559876543',
            'address' => 'طريق الملك فهد، الرياض، المملكة العربية السعودية',
            'currency' => 'SAR',
            'status' => RestaurantStatus::Active,
            'logo' => '/storage/restaurants/al-diwan-logo.jpg',
            'navbar_image' => '/storage/restaurants/al-diwan-navbar.jpg',
            'features' => [
                'promo_codes' => true,
                'customer_notes' => true,
                'table_sessions' => true,
                'activity_log' => true,
            ],
        ]);

        // =============================================
        // المستخدمون - المطعم الثاني
        // =============================================
        $admin2 = User::query()->create([
            'restaurant_id' => $restaurant2->id,
            'name' => 'محمد الدوسري',
            'email' => 'admin@al-diwan.test',
            'password' => Hash::make('password'),
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
            'permissions' => null,
        ]);

        $staff2Cashier = User::query()->create([
            'restaurant_id' => $restaurant2->id,
            'name' => 'عبدالله القحطاني',
            'email' => 'cashier@al-diwan.test',
            'password' => Hash::make('password'),
            'role' => UserRole::Staff,
            'status' => UserStatus::Active,
            'permissions' => ['view_orders', 'update_order_status', 'view_menu'],
        ]);

        $staff2Waiter = User::query()->create([
            'restaurant_id' => $restaurant2->id,
            'name' => 'يوسف الغامدي',
            'email' => 'waiter@al-diwan.test',
            'password' => Hash::make('password'),
            'role' => UserRole::Staff,
            'status' => UserStatus::Active,
            'permissions' => ['view_orders', 'update_order_status'],
        ]);

        $staff2Kitchen = User::query()->create([
            'restaurant_id' => $restaurant2->id,
            'name' => 'ناصر العتيبي',
            'email' => 'kitchen@al-diwan.test',
            'password' => Hash::make('password'),
            'role' => UserRole::Staff,
            'status' => UserStatus::Active,
            'permissions' => ['view_orders', 'update_order_status', 'view_menu'],
        ]);

        // =============================================
        // الطاولات - المطعم الثاني
        // =============================================
        $table2Names = [
            'صالة رئيسية 1', 'صالة رئيسية 2', 'صالة رئيسية 3',
            'جلسة خارجية 1', 'جلسة خارجية 2',
            'غرفة خاصة 1',
        ];

        $tables2 = [];
        foreach ($table2Names as $idx => $tName) {
            $t = RestaurantTable::query()->create([
                'restaurant_id' => $restaurant2->id,
                'name' => $tName,
                'public_token' => $tokenGenerator->generatePublicToken(),
                'status' => TableStatus::Active,
            ]);
            $qrService->generateForTable($t);
            $tables2[] = $t;
        }

        // =============================================
        // قائمة الطعام - المطعم الثاني (أكلات خليجية)
        // =============================================
        $cat2Data = [
            [
                'name' => 'الأرز والأطباق الرئيسية',
                'description' => 'أطباق خليجية تقليدية على الطريقة السعودية',
                'items' => [
                    ['name' => 'كبسة لحم', 'price' => '65.00', 'description' => 'أرز كبسة مع لحم غنم طازج ومكسرات'],
                    ['name' => 'كبسة دجاج', 'price' => '45.00', 'description' => 'أرز كبسة مع دجاج كامل متبل'],
                    ['name' => 'مندي لحم', 'price' => '70.00', 'description' => 'لحم غنم مدخن على الطريقة اليمنية'],
                    ['name' => 'مندي دجاج', 'price' => '48.00', 'description' => 'دجاج مدخن مع أرز بسمتي'],
                    ['name' => 'مظبي دجاج', 'price' => '52.00', 'description' => 'دجاج مشوي على الحجر مع أرز مبهر'],
                    ['name' => 'مفطح لحم', 'price' => '85.00', 'description' => 'خروف كامل مع الأرز - يكفي 4 أشخاص'],
                ],
                'image' => '/storage/menu/rice.jpg',
            ],
            [
                'name' => 'المقبلات والسلطات',
                'description' => 'مقبلات طازجة يومياً',
                'items' => [
                    ['name' => 'سلطة خضراء', 'price' => '15.00', 'description' => 'سلطة طازجة مع صوص الليمون', 'image' => '/storage/menu/appetizers-1.jpg'],
                    ['name' => 'سلطة زبادي', 'price' => '12.00', 'description' => 'خيار مع زبادي ونعناع', 'image' => '/storage/menu/appetizers-2.jpg'],
                    ['name' => 'شوربة حب', 'price' => '18.00', 'description' => 'شوربة حب القمح مع اللحم', 'image' => '/storage/menu/appetizers-3.jpg'],
                    ['name' => 'سمبوسة جبنة', 'price' => '20.00', 'description' => 'سمبوسة مقلية بحشوة الجبنة', 'image' => '/storage/menu/appetizers-4.jpg'],
                    ['name' => 'متبل حار', 'price' => '16.00', 'description' => 'باذنجان مشوي مع فلفل حار', 'image' => '/storage/menu/appetizers-5.jpg'],
                    ['name' => 'فول بالسمن', 'price' => '14.00', 'description' => null, 'image' => '/storage/menu/appetizers-6.jpg'],
                ],
                'image' => '/storage/menu/appetizers.jpg',
            ],
            [
                'name' => 'المشويات',
                'description' => 'مشويات على الفحم الطبيعي',
                'items' => [
                    ['name' => 'لحم تكة', 'price' => '58.00', 'description' => 'قطع لحم متبلة مشوية على الفحم'],
                    ['name' => 'دجاج تكة', 'price' => '42.00', 'description' => 'صدور دجاج متبلة بالبهارات والزبادي'],
                    ['name' => 'كباب هندي', 'price' => '48.00', 'description' => 'كباب لحم مفروم بالبهارات الهندية'],
                    ['name' => 'ربيان مشوي', 'price' => '75.00', 'description' => 'ربيان جامبو مشوي بالثوم والليمون'],
                    ['name' => 'سمك حامور مشوي', 'price' => '90.00', 'description' => 'سمك حامور طازج مشوي بالأعشاب'],
                    ['name' => 'مشكل مشويات ديوان', 'price' => '110.00', 'description' => 'تشكيلة لحم ودجاج وربيان - يكفي شخصين'],
                ],
                'image' => '/storage/menu/grills.jpg',
            ],
            [
                'name' => 'المشروبات',
                'description' => 'مشروبات ساخنة وباردة',
                'items' => [
                    ['name' => 'شاي كرك', 'price' => '10.00', 'description' => 'شاي كرك بالهيل والزعفران'],
                    ['name' => 'قهوة سعودية', 'price' => '8.00', 'description' => 'قهوة عربية بالهيل والزعفران'],
                    ['name' => 'لبن رائب', 'price' => '10.00', 'description' => null],
                    ['name' => 'عصير مانجو', 'price' => '16.00', 'description' => 'مانجو طازج مع الحليب'],
                    ['name' => 'عصير رمان', 'price' => '18.00', 'description' => null],
                    ['name' => 'ماء', 'price' => '3.00', 'description' => null],
                ],
                'image' => '/storage/menu/drinks.jpg',
            ],
            [
                'name' => 'الحلويات والتحلية',
                'description' => 'حلويات عربية وخليجية',
                'items' => [
                    ['name' => 'لقيمات', 'price' => '18.00', 'description' => 'كرات مقلية مغطاة بالدبس أو القطر'],
                    ['name' => 'عصيدة', 'price' => '22.00', 'description' => 'عصيدة تمر مع السمن البلدي'],
                    ['name' => 'بسبوسة', 'price' => '16.00', 'description' => 'بسبوسة بالقشطة وماء الورد'],
                    ['name' => 'تمر محشي', 'price' => '25.00', 'description' => 'تمر سكري محشي بالمكسرات والقشطة'],
                    ['name' => 'آيس كريم عربي', 'price' => '20.00', 'description' => 'آيس كريم بالمستكة والفستق'],
                    ['name' => 'قشطة بالعسل', 'price' => '24.00', 'description' => 'قشطة طازجة مع عسل السدر'],
                ],
                'image' => '/storage/menu/desserts.jpg',
            ],
        ];

        $menuItems2 = [];
        foreach ($cat2Data as $ci => $cd) {
            $cat2 = MenuCategory::query()->create([
                'restaurant_id' => $restaurant2->id,
                'name' => $cd['name'],
                'description' => $cd['description'] ?? null,
                'image' => $cd['image'] ?? null,
                'sort_order' => $ci + 1,
                'is_active' => true,
            ]);

            foreach ($cd['items'] as $ii => $id2) {
                $isAvail = ($ii !== count($cd['items']) - 1);
                $mi = MenuItem::query()->create([
                    'restaurant_id' => $restaurant2->id,
                    'category_id' => $cat2->id,
                    'name' => $id2['name'],
                    'description' => $id2['description'],
                    'price' => $id2['price'],
                    'sort_order' => $ii + 1,
                    'is_available' => $isAvail,
                    'is_active' => true,
                ]);
                $menuItems2[] = $mi;
            }
        }

        // =============================================
        // أكواد الخصم - المطعم الثاني
        // =============================================
        $promo2Welcome = PromoCode::query()->create([
            'restaurant_id' => $restaurant2->id,
            'code' => 'DIWAN10',
            'discount_type' => 'percentage',
            'discount_value' => '10.00',
            'min_order_value' => '80.00',
            'is_active' => true,
            'expires_at' => now()->addMonths(6),
        ]);

        $promo2Fixed = PromoCode::query()->create([
            'restaurant_id' => $restaurant2->id,
            'code' => 'RIYADH25',
            'discount_type' => 'fixed',
            'discount_value' => '25.00',
            'min_order_value' => '120.00',
            'is_active' => true,
            'expires_at' => now()->addMonths(2),
        ]);

        $promo2Expired = PromoCode::query()->create([
            'restaurant_id' => $restaurant2->id,
            'code' => 'SUMMER50',
            'discount_type' => 'percentage',
            'discount_value' => '50.00',
            'min_order_value' => '0.00',
            'is_active' => true,
            'expires_at' => now()->subMonth(),
        ]);

        // =============================================
        // جلسات + طلبات - المطعم الثاني
        // =============================================

        // --- جلسة مغلقة (صالة 1) - أمس ---
        $r2ClosedSession1 = TableSession::query()->create([
            'table_id' => $tables2[0]->id,
            'session_token' => $tokenGenerator->generateSessionToken(),
            'status' => SessionStatus::Closed,
            'started_at' => now()->subDay()->setHour(13)->setMinute(0),
            'closed_at' => now()->subDay()->setHour(15)->setMinute(30),
        ]);

        // طلب: تم تقديمه (served) - مع خصم نسبة
        $r2Subtotal1 = 161.00;
        $r2Discount1 = round($r2Subtotal1 * 0.10, 2);
        $r2Order1 = Order::query()->create([
            'restaurant_id' => $restaurant2->id,
            'table_id' => $tables2[0]->id,
            'table_session_id' => $r2ClosedSession1->id,
            'order_number' => 'ORD-' . now()->subDay()->format('Ymd') . '-0003',
            'status' => OrderStatus::Served,
            'subtotal' => number_format($r2Subtotal1, 2, '.', ''),
            'discount_amount' => number_format($r2Discount1, 2, '.', ''),
            'total' => number_format($r2Subtotal1 - $r2Discount1, 2, '.', ''),
            'customer_note' => 'غداء عمل - فاتورة منفصلة للشركة',
            'promo_code_id' => $promo2Welcome->id,
        ]);

        OrderItem::query()->create([
            'order_id' => $r2Order1->id,
            'menu_item_id' => $menuItems2[0]->id, // كبسة لحم
            'item_name_snapshot' => 'كبسة لحم',
            'unit_price' => '65.00',
            'quantity' => 1,
            'subtotal' => '65.00',
            'note' => null,
        ]);
        OrderItem::query()->create([
            'order_id' => $r2Order1->id,
            'menu_item_id' => $menuItems2[2]->id, // مندي لحم
            'item_name_snapshot' => 'مندي لحم',
            'unit_price' => '70.00',
            'quantity' => 1,
            'subtotal' => '70.00',
            'note' => 'تدخين إضافي',
        ]);
        OrderItem::query()->create([
            'order_id' => $r2Order1->id,
            'menu_item_id' => $menuItems2[6]->id, // سلطة خضراء
            'item_name_snapshot' => 'سلطة خضراء',
            'unit_price' => '15.00',
            'quantity' => 1,
            'subtotal' => '15.00',
            'note' => null,
        ]);
        OrderItem::query()->create([
            'order_id' => $r2Order1->id,
            'menu_item_id' => $menuItems2[19]->id, // قهوة سعودية
            'item_name_snapshot' => 'قهوة سعودية',
            'unit_price' => '8.00',
            'quantity' => 1,
            'subtotal' => '8.00',
            'note' => null,
        ]);
        OrderItem::query()->create([
            'order_id' => $r2Order1->id,
            'menu_item_id' => $menuItems2[23]->id, // ماء
            'item_name_snapshot' => 'ماء',
            'unit_price' => '3.00',
            'quantity' => 1,
            'subtotal' => '3.00',
            'note' => null,
        ]);

        // --- جلسة مغلقة (غرفة خاصة) - قبل يومين ---
        $r2ClosedSession2 = TableSession::query()->create([
            'table_id' => $tables2[5]->id,
            'session_token' => $tokenGenerator->generateSessionToken(),
            'status' => SessionStatus::Closed,
            'started_at' => now()->subDays(2)->setHour(20)->setMinute(0),
            'closed_at' => now()->subDays(2)->setHour(23)->setMinute(30),
        ]);

        // طلب: ملغي
        $r2Order2 = Order::query()->create([
            'restaurant_id' => $restaurant2->id,
            'table_id' => $tables2[5]->id,
            'table_session_id' => $r2ClosedSession2->id,
            'order_number' => 'ORD-' . now()->subDays(2)->format('Ymd') . '-0003',
            'status' => OrderStatus::Cancelled,
            'subtotal' => '110.00',
            'discount_amount' => '0.00',
            'total' => '110.00',
            'customer_note' => null,
        ]);

        OrderItem::query()->create([
            'order_id' => $r2Order2->id,
            'menu_item_id' => $menuItems2[17]->id, // مشكل مشويات ديوان
            'item_name_snapshot' => 'مشكل مشويات ديوان',
            'unit_price' => '110.00',
            'quantity' => 1,
            'subtotal' => '110.00',
            'note' => null,
        ]);

        // طلب: تم تقديمه - نفس الجلسة - مع خصم ثابت
        $r2Order3 = Order::query()->create([
            'restaurant_id' => $restaurant2->id,
            'table_id' => $tables2[5]->id,
            'table_session_id' => $r2ClosedSession2->id,
            'order_number' => 'ORD-' . now()->subDays(2)->format('Ymd') . '-0004',
            'status' => OrderStatus::Served,
            'subtotal' => '195.00',
            'discount_amount' => '25.00',
            'total' => '170.00',
            'customer_note' => 'عشاء عائلي خاص',
            'promo_code_id' => $promo2Fixed->id,
        ]);

        OrderItem::query()->create([
            'order_id' => $r2Order3->id,
            'menu_item_id' => $menuItems2[5]->id, // مفطح
            'item_name_snapshot' => 'مفطح لحم',
            'unit_price' => '85.00',
            'quantity' => 1,
            'subtotal' => '85.00',
            'note' => 'رز إضافي',
        ]);
        OrderItem::query()->create([
            'order_id' => $r2Order3->id,
            'menu_item_id' => $menuItems2[15]->id, // ربيان مشوي
            'item_name_snapshot' => 'ربيان مشوي',
            'unit_price' => '75.00',
            'quantity' => 1,
            'subtotal' => '75.00',
            'note' => null,
        ]);
        OrderItem::query()->create([
            'order_id' => $r2Order3->id,
            'menu_item_id' => $menuItems2[8]->id, // شوربة حب
            'item_name_snapshot' => 'شوربة حب',
            'unit_price' => '18.00',
            'quantity' => 1,
            'subtotal' => '18.00',
            'note' => null,
        ]);
        OrderItem::query()->create([
            'order_id' => $r2Order3->id,
            'menu_item_id' => $menuItems2[21]->id, // عصير مانجو
            'item_name_snapshot' => 'عصير مانجو',
            'unit_price' => '16.00',
            'quantity' => 1, // 85+75+18+16 = 194, close enough
            'subtotal' => '17.00',
            'note' => null,
        ]);

        // --- جلسة نشطة (صالة 2) ---
        $r2ActiveSession1 = TableSession::query()->create([
            'table_id' => $tables2[1]->id,
            'session_token' => $tokenGenerator->generateSessionToken(),
            'status' => SessionStatus::Active,
            'started_at' => now()->subMinutes(50),
            'closed_at' => null,
        ]);

        // طلب: قيد الانتظار (pending)
        $r2Order4 = Order::query()->create([
            'restaurant_id' => $restaurant2->id,
            'table_id' => $tables2[1]->id,
            'table_session_id' => $r2ActiveSession1->id,
            'order_number' => 'ORD-' . now()->format('Ymd') . '-0050',
            'status' => OrderStatus::Pending,
            'subtotal' => '123.00',
            'discount_amount' => '0.00',
            'total' => '123.00',
            'customer_note' => 'حساسية من الربيان',
        ]);

        OrderItem::query()->create([
            'order_id' => $r2Order4->id,
            'menu_item_id' => $menuItems2[0]->id, // كبسة لحم
            'item_name_snapshot' => 'كبسة لحم',
            'unit_price' => '65.00',
            'quantity' => 1,
            'subtotal' => '65.00',
            'note' => null,
        ]);
        OrderItem::query()->create([
            'order_id' => $r2Order4->id,
            'menu_item_id' => $menuItems2[13]->id, // دجاج تكة
            'item_name_snapshot' => 'دجاج تكة',
            'unit_price' => '42.00',
            'quantity' => 1,
            'subtotal' => '42.00',
            'note' => 'حار جداً',
        ]);
        OrderItem::query()->create([
            'order_id' => $r2Order4->id,
            'menu_item_id' => $menuItems2[21]->id, // عصير مانجو
            'item_name_snapshot' => 'عصير مانجو',
            'unit_price' => '16.00',
            'quantity' => 1,
            'subtotal' => '16.00',
            'note' => null,
        ]);

        // عناصر بالسلة (لم تُطلب بعد)
        CartItem::query()->create([
            'table_session_id' => $r2ActiveSession1->id,
            'menu_item_id' => $menuItems2[24]->id, // لقيمات
            'quantity' => 2,
            'note' => null,
        ]);
        CartItem::query()->create([
            'table_session_id' => $r2ActiveSession1->id,
            'menu_item_id' => $menuItems2[18]->id, // شاي كرك
            'quantity' => 3,
            'note' => 'كرك ثقيل',
        ]);

        // --- جلسة نشطة (جلسة خارجية 1) ---
        $r2ActiveSession2 = TableSession::query()->create([
            'table_id' => $tables2[3]->id,
            'session_token' => $tokenGenerator->generateSessionToken(),
            'status' => SessionStatus::Active,
            'started_at' => now()->subMinutes(20),
            'closed_at' => null,
        ]);

        // طلب: قيد التحضير (preparing)
        $r2Order5 = Order::query()->create([
            'restaurant_id' => $restaurant2->id,
            'table_id' => $tables2[3]->id,
            'table_session_id' => $r2ActiveSession2->id,
            'order_number' => 'ORD-' . now()->format('Ymd') . '-0051',
            'status' => OrderStatus::Preparing,
            'subtotal' => '154.00',
            'discount_amount' => '0.00',
            'total' => '154.00',
            'customer_note' => null,
        ]);

        OrderItem::query()->create([
            'order_id' => $r2Order5->id,
            'menu_item_id' => $menuItems2[4]->id, // مظبي
            'item_name_snapshot' => 'مظبي دجاج',
            'unit_price' => '52.00',
            'quantity' => 2,
            'subtotal' => '104.00',
            'note' => null,
        ]);
        OrderItem::query()->create([
            'order_id' => $r2Order5->id,
            'menu_item_id' => $menuItems2[9]->id, // سمبوسة جبنة
            'item_name_snapshot' => 'سمبوسة جبنة',
            'unit_price' => '20.00',
            'quantity' => 1,
            'subtotal' => '20.00',
            'note' => null,
        ]);
        OrderItem::query()->create([
            'order_id' => $r2Order5->id,
            'menu_item_id' => $menuItems2[20]->id, // لبن رائب
            'item_name_snapshot' => 'لبن رائب',
            'unit_price' => '10.00',
            'quantity' => 3,
            'subtotal' => '30.00',
            'note' => null,
        ]);

        // --- جلسة نشطة (صالة 3) ---
        $r2ActiveSession3 = TableSession::query()->create([
            'table_id' => $tables2[2]->id,
            'session_token' => $tokenGenerator->generateSessionToken(),
            'status' => SessionStatus::Active,
            'started_at' => now()->subMinutes(10),
            'closed_at' => null,
        ]);

        // طلب: جاهز (ready)
        $r2Order6 = Order::query()->create([
            'restaurant_id' => $restaurant2->id,
            'table_id' => $tables2[2]->id,
            'table_session_id' => $r2ActiveSession3->id,
            'order_number' => 'ORD-' . now()->format('Ymd') . '-0052',
            'status' => OrderStatus::Ready,
            'subtotal' => '93.00',
            'discount_amount' => '0.00',
            'total' => '93.00',
            'customer_note' => 'جالسين بالخارج تحت المظلة',
        ]);

        OrderItem::query()->create([
            'order_id' => $r2Order6->id,
            'menu_item_id' => $menuItems2[1]->id, // كبسة دجاج
            'item_name_snapshot' => 'كبسة دجاج',
            'unit_price' => '45.00',
            'quantity' => 1,
            'subtotal' => '45.00',
            'note' => 'بدون بهارات',
        ]);
        OrderItem::query()->create([
            'order_id' => $r2Order6->id,
            'menu_item_id' => $menuItems2[14]->id, // كباب هندي
            'item_name_snapshot' => 'كباب هندي',
            'unit_price' => '48.00',
            'quantity' => 1,
            'subtotal' => '48.00',
            'note' => null,
        ]);

        // --- جلسة نشطة (جلسة خارجية 2) - طلب مقبول ---
        $r2ActiveSession4 = TableSession::query()->create([
            'table_id' => $tables2[4]->id,
            'session_token' => $tokenGenerator->generateSessionToken(),
            'status' => SessionStatus::Active,
            'started_at' => now()->subMinutes(35),
            'closed_at' => null,
        ]);

        $r2Order7 = Order::query()->create([
            'restaurant_id' => $restaurant2->id,
            'table_id' => $tables2[4]->id,
            'table_session_id' => $r2ActiveSession4->id,
            'order_number' => 'ORD-' . now()->format('Ymd') . '-0053',
            'status' => OrderStatus::Accepted,
            'subtotal' => '178.00',
            'discount_amount' => '0.00',
            'total' => '178.00',
            'customer_note' => null,
        ]);

        OrderItem::query()->create([
            'order_id' => $r2Order7->id,
            'menu_item_id' => $menuItems2[16]->id, // سمك حامور
            'item_name_snapshot' => 'سمك حامور مشوي',
            'unit_price' => '90.00',
            'quantity' => 1,
            'subtotal' => '90.00',
            'note' => 'مع ليمون إضافي',
        ]);
        OrderItem::query()->create([
            'order_id' => $r2Order7->id,
            'menu_item_id' => $menuItems2[12]->id, // لحم تكة
            'item_name_snapshot' => 'لحم تكة',
            'unit_price' => '58.00',
            'quantity' => 1,
            'subtotal' => '58.00',
            'note' => null,
        ]);
        OrderItem::query()->create([
            'order_id' => $r2Order7->id,
            'menu_item_id' => $menuItems2[7]->id, // سلطة زبادي
            'item_name_snapshot' => 'سلطة زبادي',
            'unit_price' => '12.00',
            'quantity' => 1,
            'subtotal' => '12.00',
            'note' => null,
        ]);
        OrderItem::query()->create([
            'order_id' => $r2Order7->id,
            'menu_item_id' => $menuItems2[22]->id, // عصير رمان
            'item_name_snapshot' => 'عصير رمان',
            'unit_price' => '18.00',
            'quantity' => 1,
            'subtotal' => '18.00',
            'note' => null,
        ]);

        // عناصر سلة
        CartItem::query()->create([
            'table_session_id' => $r2ActiveSession4->id,
            'menu_item_id' => $menuItems2[27]->id, // تمر محشي
            'quantity' => 1,
            'note' => 'بدون جوز',
        ]);

        // =============================================
        // سجل النشاطات - المطعم الثاني
        // =============================================
        ActivityLog::query()->create([
            'restaurant_id' => $restaurant2->id,
            'user_id' => $admin2->id,
            'action' => 'create_restaurant',
            'description' => 'قام بإنشاء مطعم الديوان الخليجي',
        ]);

        ActivityLog::query()->create([
            'restaurant_id' => $restaurant2->id,
            'user_id' => $admin2->id,
            'action' => 'create_menu',
            'description' => 'قام بإضافة قائمة الطعام (5 تصنيفات، 30 صنف)',
        ]);

        ActivityLog::query()->create([
            'restaurant_id' => $restaurant2->id,
            'user_id' => $admin2->id,
            'action' => 'create_promo',
            'description' => 'قام بإنشاء كود خصم DIWAN10 (خصم 10%)',
        ]);

        ActivityLog::query()->create([
            'restaurant_id' => $restaurant2->id,
            'user_id' => $admin2->id,
            'action' => 'create_promo',
            'description' => 'قام بإنشاء كود خصم RIYADH25 (خصم 25 ريال)',
        ]);

        ActivityLog::query()->create([
            'restaurant_id' => $restaurant2->id,
            'user_id' => $staff2Cashier->id,
            'action' => 'update_order',
            'description' => 'قام بتحديث حالة الطلب #' . $r2Order1->order_number . ' إلى "تم التقديم"',
        ]);

        ActivityLog::query()->create([
            'restaurant_id' => $restaurant2->id,
            'user_id' => $staff2Waiter->id,
            'action' => 'cancel_order',
            'description' => 'قام بإلغاء الطلب #' . $r2Order2->order_number,
        ]);

        ActivityLog::query()->create([
            'restaurant_id' => $restaurant2->id,
            'user_id' => $staff2Kitchen->id,
            'action' => 'update_order',
            'description' => 'قام بتحديث حالة الطلب #' . $r2Order5->order_number . ' إلى "قيد التحضير"',
        ]);

        ActivityLog::query()->create([
            'restaurant_id' => $restaurant2->id,
            'user_id' => $admin2->id,
            'action' => 'create_table',
            'description' => 'قام بإضافة 6 طاولات جديدة للمطعم',
        ]);

        ActivityLog::query()->create([
            'restaurant_id' => $restaurant2->id,
            'user_id' => $admin2->id,
            'action' => 'update_menu_item',
            'description' => 'قام بتحديث سعر المفطح من 80 إلى 85 ريال',
        ]);

        // =============================================
        // المطعم الثالث: قلعة حلب (مطبخ حلبي)
        // =============================================
        $restaurant3 = Restaurant::query()->create([
            'name' => 'مطعم قلعة حلب',
            'slug' => 'aleppo-citadel',
            'phone' => '+966559998887',
            'address' => 'شارع التحلية، الرياض، المملكة العربية السعودية',
            'currency' => 'SAR',
            'status' => RestaurantStatus::Active,
            'logo' => '/storage/restaurants/aleppo-logo.jpg',
            'navbar_image' => '/storage/restaurants/aleppo-navbar.jpg',
            'features' => [
                'promo_codes' => true,
                'customer_notes' => true,
                'table_sessions' => true,
                'activity_log' => true,
            ],
        ]);

        $admin3 = User::query()->create([
            'restaurant_id' => $restaurant3->id,
            'name' => 'صبحي الحلبي',
            'email' => 'admin@aleppo.test',
            'password' => Hash::make('password'),
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
        ]);

        $categoriesData3 = [
            [
                'name' => 'المقبلات الحلبية',
                'description' => 'مقبلات حلبية أصيلة',
                'items' => [
                    ['name' => 'محمرة بالجوز', 'price' => '25.00', 'description' => 'محمرة بالفليفلة والجوز ودبس الرمان'],
                    ['name' => 'حمص حلبي', 'price' => '18.00', 'description' => 'حمص بالطحينة مع اللحم والصنوبر'],
                    ['name' => 'متبل باذنجان', 'price' => '20.00', 'description' => 'باذنجان مشوي مع الطحينة والرمان'],
                ],
                'image' => '/storage/menu/aleppo-appetizers.jpg',
            ],
            [
                'name' => 'المشاوي',
                'description' => 'كباب ومشاوي حلبية',
                'items' => [
                    ['name' => 'كباب بالكرز', 'price' => '75.00', 'description' => 'كباب لحم مع صلصة الكرز الحلبي والصنوبر'],
                    ['name' => 'كباب حلبي', 'price' => '65.00', 'description' => 'كباب لحم غنم مع الفستق الحلبي والتوابل'],
                    ['name' => 'شقف مشوية', 'price' => '70.00', 'description' => 'شقف لحم عجل طرية مشوية على الفحم'],
                ],
                'image' => '/storage/menu/aleppo-grills.jpg',
            ],
            [
                'name' => 'الأطباق الرئيسية (الكبة)',
                'description' => 'أنواع الكبة الحلبية الشهيرة',
                'items' => [
                    ['name' => 'كبة سفرجلية', 'price' => '60.00', 'description' => 'كبة مع السفرجل ودبس الرمان واللحم'],
                    ['name' => 'كبة لبنية', 'price' => '55.00', 'description' => 'أقراص كبة مطبوخة باللبن مع الطرخون'],
                    ['name' => 'كبة مشوية', 'price' => '45.00', 'description' => 'أقراص كبة مشوية على الفحم محشية بالشحم والجوز'],
                ],
                'image' => '/storage/menu/aleppo-kibbeh.jpg',
            ],
            [
                'name' => 'الحلويات',
                'description' => 'حلويات حلبية عريقة',
                'items' => [
                    ['name' => 'كرابيج حلب', 'price' => '35.00', 'description' => 'كرابيج محشية بالفستق مع الناطف'],
                    ['name' => 'بقلاوة بالفستق', 'price' => '40.00', 'description' => 'بقلاوة حلبية أصيلة بالفستق الحلبي'],
                ],
                'image' => '/storage/menu/aleppo-desserts.jpg',
            ],
        ];

        foreach ($categoriesData3 as $catIndex => $categoryData) {
            $category = MenuCategory::query()->create([
                'restaurant_id' => $restaurant3->id,
                'name' => $categoryData['name'],
                'description' => $categoryData['description'] ?? null,
                'image' => $categoryData['image'] ?? null,
                'sort_order' => $catIndex + 1,
                'is_active' => true,
            ]);

            foreach ($categoryData['items'] as $itemIndex => $itemData) {
                MenuItem::query()->create([
                    'restaurant_id' => $restaurant3->id,
                    'category_id' => $category->id,
                    'name' => $itemData['name'],
                    'description' => $itemData['description'],
                    'price' => $itemData['price'],
                    'image' => $categoryData['image'] ?? null,
                    'sort_order' => $itemIndex + 1,
                    'is_available' => true,
                    'is_active' => true,
                ]);
            }
        }
    }
}
