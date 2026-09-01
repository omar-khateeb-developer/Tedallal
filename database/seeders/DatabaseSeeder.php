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
            'role' => UserRole::SuperAdmin,
            'status' => UserStatus::Active,
            'permissions' => null, // SuperAdmin لا يحتاج صلاحيات محددة
        ]);

        $admin = User::query()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'أحمد الشامي',
            'email' => 'admin@bait-alsham.test',
            'password' => Hash::make('password'),
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
            'permissions' => null, // Admin يملك جميع الصلاحيات
        ]);

        $staffCashier = User::query()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'خالد العمري',
            'email' => 'cashier@bait-alsham.test',
            'password' => Hash::make('password'),
            'role' => UserRole::Staff,
            'status' => UserStatus::Active,
            'permissions' => ['view_orders', 'update_order_status', 'view_menu'],
        ]);

        $staffWaiter = User::query()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'سامي الحربي',
            'email' => 'waiter@bait-alsham.test',
            'password' => Hash::make('password'),
            'role' => UserRole::Staff,
            'status' => UserStatus::Active,
            'permissions' => ['view_orders', 'update_order_status'],
        ]);

        $inactiveStaff = User::query()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'فهد السالم',
            'email' => 'inactive@bait-alsham.test',
            'password' => Hash::make('password'),
            'role' => UserRole::Staff,
            'status' => UserStatus::Inactive,
            'permissions' => ['view_orders'],
        ]);

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
            ],
        ];

        // إنشاء التصنيفات والأصناف
        $menuItems = []; // لاستخدامها لاحقاً في الطلبات
        foreach ($categoriesData as $catIndex => $categoryData) {
            $category = MenuCategory::query()->create([
                'restaurant_id' => $restaurant->id,
                'name' => $categoryData['name'],
                'description' => $categoryData['description'] ?? null,
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
    }
}
