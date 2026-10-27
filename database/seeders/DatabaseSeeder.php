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
use App\Models\Setting;
use App\Models\TableSession;
use App\Models\User;
use App\Services\QrCodeService;
use App\Services\TokenGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    protected TokenGenerator $tokenGenerator;
    protected QrCodeService $qrService;

    public function run(): void
    {
        $this->tokenGenerator = app(TokenGenerator::class);
        $this->qrService = app(QrCodeService::class);

        // 1. تجهيز ومزامنة ملفات التخزين والصور لمنع أخطاء 404 وتضارب السيرفرات (VPS)
        $this->ensureStorageAndImages();

        // 2. إعداد الصلاحيات وأدوار Spatie
        $togglePermission = $this->setupRolesAndPermissions();

        // 3. مدراء النظام الرئيسيون
        $this->seedSuperAdmins($togglePermission);

        // 4. المطعم الأول: بيت الشام (Postpaid - دفع لاحق)
        $this->seedRestaurantBaitAlSham();

        // 5. المطعم الثاني: الديوان الخليجي (Prepaid - دفع مسبق مع نافذتين)
        $this->seedRestaurantAlDiwan();

        // 6. المطعم الثالث: قلعة حلب (Prepaid - دفع مسبق مع 3 نوافذ)
        $this->seedRestaurantAleppoCitadel();

        $this->command?->info('✅ تم تنفيذ السيدر بنجاح مع 3 مطاعم (2 Prepaid و 1 Postpaid) وكامل بياناتهم وصورهم بدون أي تعارض.');
    }

    /**
     * ضمان سلامة مجلدات وصور التخزين ومزامنتها بين storage و public لمنع أخطاء 404 على VPS
     */
    protected function ensureStorageAndImages(): void
    {
        // محاولة ربط التخزين إذا لم يكن الرابط الرمزي موجوداً
        try {
            if (! file_exists(public_path('storage'))) {
                Artisan::call('storage:link');
            }
        } catch (\Throwable $e) {
            // يتم تجاهله في بيئات الاستضافة التي لا تدعم symlinks تلقائياً
        }

        $dirs = [
            'restaurants',
            'menu',
            'menu/items',
            'qr',
        ];

        foreach ($dirs as $dir) {
            $storageDir = storage_path('app/public/' . $dir);
            $publicDir = public_path('storage/' . $dir);

            if (! is_dir($storageDir)) {
                @mkdir($storageDir, 0755, true);
            }
            if (! is_dir($publicDir)) {
                @mkdir($publicDir, 0755, true);
            }

            // مزامنة الملفات من storage إلى public إن لم يكن رابطاً رمزياً
            if (is_dir($storageDir)) {
                $files = @scandir($storageDir) ?: [];
                foreach ($files as $file) {
                    if ($file === '.' || $file === '..') continue;
                    $src = $storageDir . '/' . $file;
                    $dst = $publicDir . '/' . $file;
                    if (is_file($src) && ! file_exists($dst)) {
                        @copy($src, $dst);
                    }
                }
            }

            // مزامنة الملفات من public إلى storage
            if (is_dir($publicDir)) {
                $files = @scandir($publicDir) ?: [];
                foreach ($files as $file) {
                    if ($file === '.' || $file === '..') continue;
                    $src = $publicDir . '/' . $file;
                    $dst = $storageDir . '/' . $file;
                    if (is_file($src) && ! file_exists($dst)) {
                        @copy($src, $dst);
                    }
                }
            }
        }
    }

    /**
     * التأكد من وجود ملف الصورة فعلياً وتوليد بديل إذا كان مفقوداً
     */
    protected function ensureImageFile(?string $publicStoragePath, string $fallbackTitle = 'Image'): void
    {
        if (! $publicStoragePath) return;

        $relativePath = ltrim(preg_replace('#^/?storage/#', '', $publicStoragePath), '/');
        $storageFile = storage_path('app/public/' . $relativePath);
        $publicFile = public_path('storage/' . $relativePath);

        if (file_exists($storageFile)) {
            if (! file_exists($publicFile)) {
                @mkdir(dirname($publicFile), 0755, true);
                @copy($storageFile, $publicFile);
            }
            return;
        }

        if (file_exists($publicFile)) {
            if (! file_exists($storageFile)) {
                @mkdir(dirname($storageFile), 0755, true);
                @copy($publicFile, $storageFile);
            }
            return;
        }

        @mkdir(dirname($storageFile), 0755, true);
        @mkdir(dirname($publicFile), 0755, true);

        if (extension_loaded('gd')) {
            $img = @imagecreatetruecolor(400, 300);
            if ($img) {
                $bg = imagecolorallocate($img, 30, 41, 59);
                $textColor = imagecolorallocate($img, 241, 245, 249);
                imagefilledrectangle($img, 0, 0, 400, 300, $bg);
                imagestring($img, 4, 20, 140, substr($fallbackTitle, 0, 30), $textColor);
                imagejpeg($img, $storageFile, 85);
                imagedestroy($img);
                @copy($storageFile, $publicFile);
                return;
            }
        }

        $dummy = base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=');
        @file_put_contents($storageFile, $dummy);
        @file_put_contents($publicFile, $dummy);
    }

    /**
     * إنشاء وتحديث الأدوار والصلاحيات Spatie
     */
    protected function setupRolesAndPermissions(): Permission
    {
        $roles = [
            UserRole::SuperAdmin->value,
            UserRole::Admin->value,
            UserRole::Cashier->value,
            UserRole::Chef->value,
            UserRole::Supervisor->value,
            UserRole::Waiter->value,
            UserRole::Staff->value,
        ];

        foreach ($roles as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        foreach (UserRole::cases() as $roleEnum) {
            $role = Role::where('name', $roleEnum->value)->first();
            if ($role) {
                $perms = $roleEnum->defaultPermissions();
                foreach ($perms as $permName) {
                    Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
                }
                $role->syncPermissions($perms);
            }
        }

        $togglePermission = Permission::firstOrCreate(['name' => 'toggle_maintenance', 'guard_name' => 'web']);
        $superAdminRole = Role::where('name', UserRole::SuperAdmin->value)->first();
        if ($superAdminRole && $superAdminRole->hasPermissionTo($togglePermission)) {
            $superAdminRole->revokePermissionTo($togglePermission);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return $togglePermission;
    }

    /**
     * إنشاء مدراء النظام
     */
    protected function seedSuperAdmins(Permission $togglePermission): void
    {
        Setting::set('maintenance_mode', '0');

        $superAdmin = User::query()->updateOrCreate(
            ['email' => 'super@tedallal.test'],
            [
                'restaurant_id' => null,
                'name'          => 'مدير النظام الرئيسي',
                'password'      => Hash::make('password'),
                'status'        => UserStatus::Active,
                'default_window'=> null,
            ]
        );
        $superAdmin->syncRoles([UserRole::SuperAdmin->value]);
        if (! $superAdmin->hasPermissionTo($togglePermission)) {
            $superAdmin->givePermissionTo($togglePermission);
        }

        $systemAdmin = User::query()->updateOrCreate(
            ['email' => 'admin@tedallal.test'],
            [
                'restaurant_id' => null,
                'name'          => 'مدير النظام',
                'password'      => Hash::make('password'),
                'status'        => UserStatus::Active,
                'default_window'=> null,
            ]
        );
        $systemAdmin->syncRoles([UserRole::SuperAdmin->value]);
        if ($systemAdmin->hasPermissionTo($togglePermission)) {
            $systemAdmin->revokePermissionTo($togglePermission);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * =========================================================================
     * المطعم الأول: مطعم بيت الشام (Postpaid - دفع لاحق بعد الوجبة)
     * =========================================================================
     */
    protected function seedRestaurantBaitAlSham(): void
    {
        $logo = '/storage/restaurants/bait-alsham-logo.jpg';
        $navbar = '/storage/restaurants/bait-alsham-navbar.jpg';
        $this->ensureImageFile($logo, 'Bait Al-Sham Logo');
        $this->ensureImageFile($navbar, 'Bait Al-Sham Navbar');

        $restaurant = Restaurant::query()->updateOrCreate(
            ['slug' => 'bait-alsham'],
            [
                'name'          => 'مطعم بيت الشام',
                'type'          => 'postpaid',
                'windows_count' => 1,
                'phone'         => '+966551234567',
                'address'       => 'شارع التحلية، جدة، المملكة العربية السعودية',
                'currency'      => 'SYP',
                'status'        => RestaurantStatus::Active,
                'logo'          => $logo,
                'navbar_image'  => $navbar,
                'features'      => [
                    'promo_codes'    => true,
                    'customer_notes' => true,
                    'table_sessions' => true,
                    'activity_log'   => true,
                ],
            ]
        );

        // المستخدمون مع الأدوار المحدثة
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@bait-alsham.test'],
            [
                'restaurant_id'  => $restaurant->id,
                'name'           => 'أحمد الشامي (مدير المطعم)',
                'password'       => Hash::make('password'),
                'status'         => UserStatus::Active,
                'default_window' => null,
            ]
        );
        $admin->syncRoles([UserRole::Admin->value]);

        $cashier = User::query()->updateOrCreate(
            ['email' => 'cashier@bait-alsham.test'],
            [
                'restaurant_id'  => $restaurant->id,
                'name'           => 'خالد العمري (كاشير)',
                'password'       => Hash::make('password'),
                'status'         => UserStatus::Active,
                'default_window' => null,
            ]
        );
        $cashier->syncRoles([UserRole::Cashier->value]);
        $cashier->givePermissionTo(['view_orders', 'update_order_status', 'view_menu']);

        $waiter = User::query()->updateOrCreate(
            ['email' => 'waiter@bait-alsham.test'],
            [
                'restaurant_id'  => $restaurant->id,
                'name'           => 'سامي الحربي (مضيف صالة)',
                'password'       => Hash::make('password'),
                'status'         => UserStatus::Active,
                'default_window' => null,
            ]
        );
        $waiter->syncRoles([UserRole::Waiter->value]);
        $waiter->givePermissionTo(['view_orders', 'update_order_status']);

        $chef = User::query()->updateOrCreate(
            ['email' => 'chef@bait-alsham.test'],
            [
                'restaurant_id'  => $restaurant->id,
                'name'           => 'الشيف زياد الشامي',
                'password'       => Hash::make('password'),
                'status'         => UserStatus::Active,
                'default_window' => 1,
            ]
        );
        $chef->syncRoles([UserRole::Chef->value]);
        $chef->givePermissionTo(['view_orders', 'update_order_status']);

        $supervisor = User::query()->updateOrCreate(
            ['email' => 'supervisor@bait-alsham.test'],
            [
                'restaurant_id'  => $restaurant->id,
                'name'           => 'طارق النعيمي (مشرف تشغيل)',
                'password'       => Hash::make('password'),
                'status'         => UserStatus::Active,
                'default_window' => null,
            ]
        );
        $supervisor->syncRoles([UserRole::Supervisor->value]);

        $inactiveStaff = User::query()->updateOrCreate(
            ['email' => 'inactive@bait-alsham.test'],
            [
                'restaurant_id'  => $restaurant->id,
                'name'           => 'فهد السالم (موظف معطل)',
                'password'       => Hash::make('password'),
                'status'         => UserStatus::Inactive,
                'default_window' => null,
            ]
        );
        $inactiveStaff->syncRoles([UserRole::Staff->value]);

        // الطاولات ورموز QR
        $tableNames = [
            'طاولة الصالة 1', 'طاولة الصالة 2', 'طاولة الصالة 3',
            'طاولة VIP 1', 'طاولة VIP 2',
            'طاولة الشرفة 1', 'طاولة الشرفة 2',
            'طاولة العائلات 1',
        ];

        $tables = [];
        foreach ($tableNames as $index => $tableName) {
            $status = ($index === 7) ? TableStatus::Inactive : TableStatus::Active;
            $table = RestaurantTable::query()->firstOrCreate(
                ['restaurant_id' => $restaurant->id, 'name' => $tableName],
                [
                    'public_token' => $this->tokenGenerator->generatePublicToken(),
                    'status'       => $status,
                ]
            );

            if (! $table->public_token) {
                $table->public_token = $this->tokenGenerator->generatePublicToken();
                $table->save();
            }

            $qrPath = "qr/{$table->restaurant_id}/{$table->public_token}.png";
            if (! Storage::disk('public')->exists($qrPath)) {
                $this->qrService->generateForTable($table);
            }
            $tables[] = $table;
        }

        // قائمة الطعام
        $categoriesData = [
            [
                'name'        => 'المقبلات الباردة',
                'description' => 'تشكيلة من المقبلات الشامية الأصيلة',
                'image'       => '/storage/menu/appetizers.jpg',
                'items'       => [
                    ['name' => 'حمص بالطحينة', 'price' => '18.00', 'description' => 'حمص مهروس بالطحينة وزيت الزيتون والليمون', 'image' => '/storage/menu/items/hummus.jpg'],
                    ['name' => 'متبل باذنجان', 'price' => '16.00', 'description' => 'باذنجان مشوي على الفحم مع الطحينة', 'image' => '/storage/menu/items/mutabbal.jpg'],
                    ['name' => 'تبولة', 'price' => '20.00', 'description' => 'سلطة البقدونس الطازجة مع البرغل والطماطم', 'image' => '/storage/menu/items/tabbouleh.jpg'],
                    ['name' => 'فتوش', 'price' => '22.00', 'description' => 'سلطة الخضار المقرمشة مع خبز محمص ودبس الرمان', 'image' => '/storage/menu/items/fattoush.jpg'],
                    ['name' => 'ورق عنب', 'price' => '25.00', 'description' => 'ورق عنب محشي بالأرز والبهارات الشامية', 'image' => '/storage/menu/items/grape_leaves.jpg'],
                    ['name' => 'لبنة بالزيت', 'price' => '14.00', 'description' => 'لبنة بلدية طازجة بزيت الزيتون البكر', 'image' => '/storage/menu/items/labneh.jpg'],
                ],
            ],
            [
                'name'        => 'المقبلات الساخنة',
                'description' => 'مقبلات محضرة طازجة',
                'image'       => '/storage/menu/appetizers.jpg',
                'items'       => [
                    ['name' => 'كبة مقلية', 'price' => '28.00', 'description' => 'كبة محشية باللحم والصنوبر', 'image' => '/storage/menu/items/fried_kibbeh.jpg'],
                    ['name' => 'فطائر سبانخ', 'price' => '22.00', 'description' => 'فطائر مخبوزة بالسبانخ والسماق والبصل', 'image' => '/storage/menu/items/spinach_fatayer.jpg'],
                    ['name' => 'سمبوسة لحم', 'price' => '24.00', 'description' => 'سمبوسة مقلية محشية باللحم المفروم', 'image' => '/storage/menu/items/meat_samosa.jpg'],
                    ['name' => 'حلوم مشوي', 'price' => '26.00', 'description' => 'جبنة حلوم مشوية مع النعناع', 'image' => '/storage/menu/items/grilled_halloumi.jpg'],
                    ['name' => 'بطاطا حرة', 'price' => '18.00', 'description' => 'بطاطا مقلية مع الكزبرة والثوم والفلفل الحار', 'image' => '/storage/menu/items/batata_harra.jpg'],
                ],
            ],
            [
                'name'        => 'المشاوي',
                'description' => 'مشاوي على الفحم الطبيعي',
                'image'       => '/storage/menu/grills.jpg',
                'items'       => [
                    ['name' => 'مشكل مشاوي', 'price' => '95.00', 'description' => 'تشكيلة كباب وشيش طاووق وريش غنم وكفتة', 'image' => '/storage/menu/items/mixed_grill.jpg'],
                    ['name' => 'شيش طاووق', 'price' => '55.00', 'description' => 'صدور دجاج متبلة مشوية على الفحم', 'image' => '/storage/menu/items/shish_tawook.jpg'],
                    ['name' => 'كباب لحم', 'price' => '65.00', 'description' => 'لحم غنم مفروم متبل على الفحم', 'image' => '/storage/menu/items/meat_kebab.jpg'],
                    ['name' => 'ريش غنم', 'price' => '85.00', 'description' => 'ريش غنم متبلة بالأعشاب الطازجة', 'image' => '/storage/menu/items/lamb_chops.jpg'],
                    ['name' => 'كفتة مشوية', 'price' => '50.00', 'description' => 'كفتة لحم مع البقدونس والبصل', 'image' => '/storage/menu/items/grilled_kofta.jpg'],
                    ['name' => 'دجاج مشوي كامل', 'price' => '75.00', 'description' => 'دجاج كامل متبل ومشوي على الفحم', 'image' => '/storage/menu/items/whole_grilled_chicken.jpg'],
                ],
            ],
            [
                'name'        => 'الأطباق الرئيسية',
                'description' => 'أطباق شامية تقليدية',
                'image'       => '/storage/menu/rice.jpg',
                'items'       => [
                    ['name' => 'منسف أردني', 'price' => '70.00', 'description' => 'لحم غنم مطبوخ بالجميد مع الأرز والصنوبر', 'image' => '/storage/menu/items/mansaf.jpg'],
                    ['name' => 'كبسة دجاج', 'price' => '45.00', 'description' => 'أرز بسمتي مع دجاج متبل بالبهارات السعودية', 'image' => '/storage/menu/items/chicken_kabsa.jpg'],
                    ['name' => 'شاورما عربي', 'price' => '38.00', 'description' => 'شاورما لحم أو دجاج مع صلصة الطحينة والمخللات', 'image' => '/storage/menu/items/shawarma_arabi.jpg'],
                    ['name' => 'فتة حمص باللحمة', 'price' => '42.00', 'description' => 'خبز محمص مع الحمص واللحم والسمن العربي', 'image' => '/storage/menu/items/fatteh_hummus.jpg'],
                    ['name' => 'مسخن فلسطيني', 'price' => '48.00', 'description' => 'دجاج مع البصل والسماق على خبز الطابون', 'image' => '/storage/menu/items/musakhan.jpg'],
                    ['name' => 'مقلوبة', 'price' => '52.00', 'description' => 'أرز مع الباذنجان والدجاج والمكسرات', 'image' => '/storage/menu/items/makloubeh.jpg'],
                ],
            ],
            [
                'name'        => 'المشروبات',
                'description' => 'مشروبات باردة وساخنة',
                'image'       => '/storage/menu/drinks.jpg',
                'items'       => [
                    ['name' => 'عصير ليمون بالنعناع', 'price' => '15.00', 'description' => 'عصير ليمون طازج بالنعناع والسكر', 'image' => '/storage/menu/items/lemon_mint.jpg'],
                    ['name' => 'عصير برتقال طبيعي', 'price' => '18.00', 'description' => 'عصير برتقال طازج 100%', 'image' => '/storage/menu/items/orange_juice.jpg'],
                    ['name' => 'شاي أحمر', 'price' => '8.00', 'description' => 'شاي أحمر سيلاني فاخر بالنعناع', 'image' => '/storage/menu/items/red_tea.jpg'],
                    ['name' => 'قهوة عربية', 'price' => '12.00', 'description' => 'قهوة عربية أصيلة مع الهيل والزعفران', 'image' => '/storage/menu/items/arabic_coffee.jpg'],
                    ['name' => 'قهوة تركية', 'price' => '14.00', 'description' => 'قهوة تركية تقليدية مع رغوة غنية', 'image' => '/storage/menu/items/turkish_coffee.jpg'],
                    ['name' => 'جلاب', 'price' => '16.00', 'description' => 'مشروب الجلاب مع الصنوبر والزبيب', 'image' => '/storage/menu/items/jallab.jpg'],
                    ['name' => 'عيران', 'price' => '10.00', 'description' => 'لبن مخيض منعش بالنعناع', 'image' => '/storage/menu/items/ayran.jpg'],
                    ['name' => 'ماء معدنية', 'price' => '5.00', 'description' => 'مياه معدنية طبيعية نقية', 'image' => '/storage/menu/items/mineral_water.jpg'],
                ],
            ],
            [
                'name'        => 'الحلويات',
                'description' => 'حلويات شرقية طازجة',
                'image'       => '/storage/menu/desserts.jpg',
                'items'       => [
                    ['name' => 'كنافة نابلسية', 'price' => '28.00', 'description' => 'كنافة بالجبنة النابلسية مع القطر', 'image' => '/storage/menu/items/knafeh.jpg'],
                    ['name' => 'بقلاوة مشكلة', 'price' => '24.00', 'description' => 'تشكيلة بقلاوة بالفستق والجوز والكاجو', 'image' => '/storage/menu/items/baklava.jpg'],
                    ['name' => 'هريسة', 'price' => '18.00', 'description' => 'حلوى السميد مع اللوز وماء الزهر', 'image' => '/storage/menu/items/harissa.jpg'],
                    ['name' => 'مهلبية', 'price' => '16.00', 'description' => 'حلوى الحليب بالنشاء وماء الورد والفستق', 'image' => '/storage/menu/items/mahalabia.jpg'],
                    ['name' => 'عوامة', 'price' => '20.00', 'description' => 'كرات مقلية مقرمشة مغطاة بالقطر الساخن', 'image' => '/storage/menu/items/awameh.jpg'],
                ],
            ],
        ];

        $menuItems = [];
        foreach ($categoriesData as $catIndex => $categoryData) {
            $this->ensureImageFile($categoryData['image'], $categoryData['name']);

            $category = MenuCategory::query()->updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'name' => $categoryData['name']],
                [
                    'description' => $categoryData['description'] ?? null,
                    'image'       => $categoryData['image'] ?? null,
                    'sort_order'  => $catIndex + 1,
                    'is_active'   => true,
                ]
            );

            foreach ($categoryData['items'] as $itemIndex => $itemData) {
                $this->ensureImageFile($itemData['image'], $itemData['name']);
                $isAvailable = ($itemIndex !== count($categoryData['items']) - 1);

                $item = MenuItem::query()->updateOrCreate(
                    ['restaurant_id' => $restaurant->id, 'name' => $itemData['name']],
                    [
                        'category_id'  => $category->id,
                        'description'  => $itemData['description'],
                        'price'        => $itemData['price'],
                        'image'        => $itemData['image'] ?? $categoryData['image'] ?? null,
                        'sort_order'   => $itemIndex + 1,
                        'is_available' => $isAvailable,
                        'is_active'    => true,
                    ]
                );
                $menuItems[] = $item;
            }
        }

        // أكواد الخصم
        $promoWelcome = PromoCode::query()->updateOrCreate(
            ['restaurant_id' => $restaurant->id, 'code' => 'WELCOME20'],
            [
                'discount_type'   => 'percentage',
                'discount_value'  => '20.00',
                'min_order_value' => '50.00',
                'is_active'       => true,
                'expires_at'      => now()->addMonths(3),
            ]
        );

        $promoFixed = PromoCode::query()->updateOrCreate(
            ['restaurant_id' => $restaurant->id, 'code' => 'SAVE30'],
            [
                'discount_type'   => 'fixed',
                'discount_value'  => '30.00',
                'min_order_value' => '100.00',
                'is_active'       => true,
                'expires_at'      => now()->addMonth(),
            ]
        );

        // جلسات وطلبات (نظام الدفع اللاحق Postpaid)
        $session1 = TableSession::query()->firstOrCreate(
            ['table_id' => $tables[0]->id, 'status' => SessionStatus::Closed],
            [
                'session_token' => $this->tokenGenerator->generateSessionToken(),
                'started_at'    => now()->subDays(2)->setHour(12)->setMinute(0),
                'closed_at'     => now()->subDays(2)->setHour(14)->setMinute(30),
            ]
        );

        $order1 = Order::query()->updateOrCreate(
            ['order_number' => 'ORD-' . now()->subDays(2)->format('Ymd') . '-0001'],
            [
                'restaurant_id'    => $restaurant->id,
                'table_id'         => $tables[0]->id,
                'table_session_id' => $session1->id,
                'status'           => OrderStatus::Served,
                'service_type'     => 'dine_in',
                'pickup_window'    => null,
                'subtotal'         => '131.00',
                'discount_amount'  => '0.00',
                'total'            => '131.00',
                'customer_note'    => 'بدون بصل في الفتوش من فضلكم',
                'paid_at'          => now()->subDays(2)->setHour(14)->setMinute(25),
                'paid_by'          => $cashier->id,
                'served_at'        => now()->subDays(2)->setHour(13)->setMinute(15),
            ]
        );

        OrderItem::query()->firstOrCreate(
            ['order_id' => $order1->id, 'menu_item_id' => $menuItems[0]->id],
            ['item_name_snapshot' => 'حمص بالطحينة', 'unit_price' => '18.00', 'quantity' => 2, 'subtotal' => '36.00']
        );
        OrderItem::query()->firstOrCreate(
            ['order_id' => $order1->id, 'menu_item_id' => $menuItems[3]->id],
            ['item_name_snapshot' => 'فتوش', 'unit_price' => '22.00', 'quantity' => 1, 'subtotal' => '22.00']
        );
        OrderItem::query()->firstOrCreate(
            ['order_id' => $order1->id, 'menu_item_id' => $menuItems[12]->id],
            ['item_name_snapshot' => 'شيش طاووق', 'unit_price' => '55.00', 'quantity' => 1, 'subtotal' => '55.00']
        );

        ActivityLog::query()->firstOrCreate(
            ['restaurant_id' => $restaurant->id, 'action' => 'create_restaurant'],
            ['user_id' => $admin->id, 'description' => 'قام بإنشاء وتفعيل مطعم بيت الشام (Postpaid)']
        );
    }

    /**
     * =========================================================================
     * المطعم الثاني: مطعم الديوان الخليجي (Prepaid - دفع مسبق مع كوتين استلام)
     * =========================================================================
     */
    protected function seedRestaurantAlDiwan(): void
    {
        $logo = '/storage/restaurants/al-diwan-logo.jpg';
        $navbar = '/storage/restaurants/al-diwan-navbar.jpg';
        $this->ensureImageFile($logo, 'Al-Diwan Logo');
        $this->ensureImageFile($navbar, 'Al-Diwan Navbar');

        $restaurant = Restaurant::query()->updateOrCreate(
            ['slug' => 'al-diwan'],
            [
                'name'          => 'مطعم الديوان الخليجي',
                'type'          => 'prepaid', // <--- PREPAID
                'windows_count' => 2,        // <--- كوتين استلام/تحضير
                'phone'         => '+966559876543',
                'address'       => 'طريق الملك فهد، الرياض، المملكة العربية السعودية',
                'currency'      => 'SYP',
                'status'        => RestaurantStatus::Active,
                'logo'          => $logo,
                'navbar_image'  => $navbar,
                'features'      => [
                    'promo_codes'    => true,
                    'customer_notes' => true,
                    'table_sessions' => true,
                    'activity_log'   => true,
                ],
            ]
        );

        // المستخدمون المحدثون مع Spatie ونوافذ الشيفات
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@al-diwan.test'],
            [
                'restaurant_id'  => $restaurant->id,
                'name'           => 'محمد الدوسري (مدير المطعم)',
                'password'       => Hash::make('password'),
                'status'         => UserStatus::Active,
                'default_window' => null,
            ]
        );
        $admin->syncRoles([UserRole::Admin->value]);

        $cashier = User::query()->updateOrCreate(
            ['email' => 'cashier@al-diwan.test'],
            [
                'restaurant_id'  => $restaurant->id,
                'name'           => 'عبدالله القحطاني (كاشير الدفع المسبق)',
                'password'       => Hash::make('password'),
                'status'         => UserStatus::Active,
                'default_window' => null,
            ]
        );
        $cashier->syncRoles([UserRole::Cashier->value]);
        $cashier->givePermissionTo(['manage_orders', 'view_orders', 'update_order_status']);

        // شيف نافذة 1 (الأرز والأطباق الرئيسية)
        $chef1 = User::query()->updateOrCreate(
            ['email' => 'chef1@al-diwan.test'],
            [
                'restaurant_id'  => $restaurant->id,
                'name'           => 'الشيف سلطان (نافذة 1 - الأرز والكبسات)',
                'password'       => Hash::make('password'),
                'status'         => UserStatus::Active,
                'default_window' => 1,
            ]
        );
        $chef1->syncRoles([UserRole::Chef->value]);
        $chef1->givePermissionTo(['manage_orders', 'view_orders', 'update_order_status']);

        // شيف نافذة 2 (المشويات والمشروبات)
        $chef2 = User::query()->updateOrCreate(
            ['email' => 'chef2@al-diwan.test'],
            [
                'restaurant_id'  => $restaurant->id,
                'name'           => 'الشيف بدر (نافذة 2 - المشويات والسناك)',
                'password'       => Hash::make('password'),
                'status'         => UserStatus::Active,
                'default_window' => 2,
            ]
        );
        $chef2->syncRoles([UserRole::Chef->value]);
        $chef2->givePermissionTo(['manage_orders', 'view_orders', 'update_order_status']);

        // حساب الشيف القديم للتوافق
        $kitchen = User::query()->updateOrCreate(
            ['email' => 'kitchen@al-diwan.test'],
            [
                'restaurant_id'  => $restaurant->id,
                'name'           => 'ناصر العتيبي (شيف مساعد)',
                'password'       => Hash::make('password'),
                'status'         => UserStatus::Active,
                'default_window' => 1,
            ]
        );
        $kitchen->syncRoles([UserRole::Chef->value]);

        $supervisor = User::query()->updateOrCreate(
            ['email' => 'supervisor@al-diwan.test'],
            [
                'restaurant_id'  => $restaurant->id,
                'name'           => 'سعود التميمي (مشرف تشغيل)',
                'password'       => Hash::make('password'),
                'status'         => UserStatus::Active,
                'default_window' => null,
            ]
        );
        $supervisor->syncRoles([UserRole::Supervisor->value]);

        $waiter = User::query()->updateOrCreate(
            ['email' => 'waiter@al-diwan.test'],
            [
                'restaurant_id'  => $restaurant->id,
                'name'           => 'يوسف الغامدي (مضيف صالة)',
                'password'       => Hash::make('password'),
                'status'         => UserStatus::Active,
                'default_window' => null,
            ]
        );
        $waiter->syncRoles([UserRole::Waiter->value]);

        // الطاولات
        $tableNames = [
            'صالة رئيسية 1', 'صالة رئيسية 2', 'صالة رئيسية 3',
            'جلسة خارجية 1', 'جلسة خارجية 2',
            'غرفة خاصة 1',
        ];

        $tables = [];
        foreach ($tableNames as $tName) {
            $t = RestaurantTable::query()->firstOrCreate(
                ['restaurant_id' => $restaurant->id, 'name' => $tName],
                [
                    'public_token' => $this->tokenGenerator->generatePublicToken(),
                    'status'       => TableStatus::Active,
                ]
            );

            if (! $t->public_token) {
                $t->public_token = $this->tokenGenerator->generatePublicToken();
                $t->save();
            }

            $qrPath = "qr/{$t->restaurant_id}/{$t->public_token}.png";
            if (! Storage::disk('public')->exists($qrPath)) {
                $this->qrService->generateForTable($t);
            }
            $tables[] = $t;
        }

        // قائمة الطعام الخليجية
        $catData = [
            [
                'name'        => 'الأرز والأطباق الرئيسية',
                'description' => 'أطباق خليجية تقليدية على الطريقة السعودية',
                'image'       => '/storage/menu/rice.jpg',
                'items'       => [
                    ['name' => 'كبسة لحم', 'price' => '65.00', 'description' => 'أرز كبسة مع لحم غنم طازج ومكسرات', 'image' => '/storage/menu/items/lamb_kabsa.jpg'],
                    ['name' => 'كبسة دجاج', 'price' => '45.00', 'description' => 'أرز كبسة مع دجاج كامل متبل', 'image' => '/storage/menu/items/chicken_kabsa.jpg'],
                    ['name' => 'مندي لحم', 'price' => '70.00', 'description' => 'لحم غنم مدخن على الطريقة اليمنية', 'image' => '/storage/menu/items/lamb_mandi.jpg'],
                    ['name' => 'مندي دجاج', 'price' => '48.00', 'description' => 'دجاج مدخن مع أرز بسمتي', 'image' => '/storage/menu/items/chicken_mandi.jpg'],
                    ['name' => 'مظبي دجاج', 'price' => '52.00', 'description' => 'دجاج مشوي على الحجر مع أرز مبهر', 'image' => '/storage/menu/items/chicken_madhbi.jpg'],
                    ['name' => 'مفطح لحم', 'price' => '85.00', 'description' => 'خروف كامل مع الأرز - يكفي 4 أشخاص', 'image' => '/storage/menu/items/mufattah.jpg'],
                ],
            ],
            [
                'name'        => 'المقبلات والسلطات',
                'description' => 'مقبلات طازجة يومياً',
                'image'       => '/storage/menu/appetizers.jpg',
                'items'       => [
                    ['name' => 'سلطة خضراء', 'price' => '15.00', 'description' => 'سلطة طازجة مع صوص الليمون', 'image' => '/storage/menu/items/green_salad.jpg'],
                    ['name' => 'سلطة زبادي', 'price' => '12.00', 'description' => 'خيار مع زبادي ونعناع', 'image' => '/storage/menu/items/yogurt_salad.jpg'],
                    ['name' => 'شوربة حب', 'price' => '18.00', 'description' => 'شوربة حب القمح مع اللحم', 'image' => '/storage/menu/items/wheat_soup.jpg'],
                    ['name' => 'سمبوسة جبنة', 'price' => '20.00', 'description' => 'سمبوسة مقلية بحشوة الجبنة', 'image' => '/storage/menu/items/cheese_samosa.jpg'],
                    ['name' => 'متبل حار', 'price' => '16.00', 'description' => 'باذنجان مشوي مع فلفل حار', 'image' => '/storage/menu/items/mutabbal.jpg'],
                    ['name' => 'فول بالسمن', 'price' => '14.00', 'description' => 'فول مدمس بالسمن البري', 'image' => '/storage/menu/items/foul.jpg'],
                ],
            ],
            [
                'name'        => 'المشويات',
                'description' => 'مشويات على الفحم الطبيعي',
                'image'       => '/storage/menu/grills.jpg',
                'items'       => [
                    ['name' => 'لحم تكة', 'price' => '58.00', 'description' => 'قطع لحم متبلة مشوية على الفحم', 'image' => '/storage/menu/items/meat_tikka.jpg'],
                    ['name' => 'دجاج تكة', 'price' => '42.00', 'description' => 'صدور دجاج متبلة بالبهارات والزبادي', 'image' => '/storage/menu/items/chicken_tikka.jpg'],
                    ['name' => 'كباب لحم', 'price' => '48.00', 'description' => 'كباب لحم مفروم بالبهارات الخليجية', 'image' => '/storage/menu/items/meat_kebab.jpg'],
                    ['name' => 'ربيان مشوي', 'price' => '75.00', 'description' => 'ربيان جامبو مشوي بالثوم والليمون', 'image' => '/storage/menu/items/grilled_shrimp.jpg'],
                    ['name' => 'سمك هامور مشوي', 'price' => '90.00', 'description' => 'سمك هامور طازج مشوي بالأعشاب', 'image' => '/storage/menu/items/grilled_fish.jpg'],
                    ['name' => 'مشكل مشويات ديوان', 'price' => '110.00', 'description' => 'تشكيلة لحم ودجاج وربيان - يكفي شخصين', 'image' => '/storage/menu/items/mixed_grill.jpg'],
                ],
            ],
            [
                'name'        => 'المشروبات',
                'description' => 'مشروبات ساخنة وباردة',
                'image'       => '/storage/menu/drinks.jpg',
                'items'       => [
                    ['name' => 'شاي كرك', 'price' => '10.00', 'description' => 'شاي كرك بالهيل والزعفران', 'image' => '/storage/menu/items/karak_tea.jpg'],
                    ['name' => 'قهوة سعودية', 'price' => '8.00', 'description' => 'قهوة عربية بالهيل والزعفران', 'image' => '/storage/menu/items/arabic_coffee.jpg'],
                    ['name' => 'لبن رائب', 'price' => '10.00', 'description' => 'لبن رائب طازج مبرد', 'image' => '/storage/menu/items/ayran.jpg'],
                    ['name' => 'عصير مانجو', 'price' => '16.00', 'description' => 'مانجو طازج مع الحليب', 'image' => '/storage/menu/items/mango_juice.jpg'],
                    ['name' => 'عصير رمان', 'price' => '18.00', 'description' => 'عصير رمان طازج طبيعي', 'image' => '/storage/menu/items/pomegranate_juice.jpg'],
                    ['name' => 'مياه معدنية', 'price' => '3.00', 'description' => 'مياه شرب نقية', 'image' => '/storage/menu/items/mineral_water.jpg'],
                ],
            ],
            [
                'name'        => 'الحلويات والتحلية',
                'description' => 'حلويات عربية وخليجية',
                'image'       => '/storage/menu/desserts.jpg',
                'items'       => [
                    ['name' => 'لقيمات', 'price' => '18.00', 'description' => 'كرات مقلية مغطاة بالدبس أو القطر والسمسم', 'image' => '/storage/menu/items/awameh.jpg'],
                    ['name' => 'عصيدة تمر', 'price' => '22.00', 'description' => 'عصيدة تمر مع السمن البلدي', 'image' => '/storage/menu/items/harissa.jpg'],
                    ['name' => 'بسبوسة بالقشطة', 'price' => '16.00', 'description' => 'بسبوسة بالقشطة وماء الورد', 'image' => '/storage/menu/items/harissa.jpg'],
                    ['name' => 'تمر محشي', 'price' => '25.00', 'description' => 'تمر سكري محشي بالمكسرات والقشطة', 'image' => '/storage/menu/items/stuffed_dates.jpg'],
                    ['name' => 'آيس كريم عربي', 'price' => '20.00', 'description' => 'آيس كريم بالمستكة والفستق', 'image' => '/storage/menu/items/arabic_icecream.jpg'],
                    ['name' => 'قشطة بالعسل', 'price' => '24.00', 'description' => 'قشطة طازجة مع عسل السدر', 'image' => '/storage/menu/items/cream_honey.jpg'],
                ],
            ],
        ];

        $menuItems = [];
        foreach ($catData as $ci => $cd) {
            $this->ensureImageFile($cd['image'], $cd['name']);

            $cat = MenuCategory::query()->updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'name' => $cd['name']],
                [
                    'description' => $cd['description'] ?? null,
                    'image'       => $cd['image'] ?? null,
                    'sort_order'  => $ci + 1,
                    'is_active'   => true,
                ]
            );

            foreach ($cd['items'] as $ii => $id) {
                $this->ensureImageFile($id['image'], $id['name']);
                $isAvail = ($ii !== count($cd['items']) - 1);

                $item = MenuItem::query()->updateOrCreate(
                    ['restaurant_id' => $restaurant->id, 'name' => $id['name']],
                    [
                        'category_id'  => $cat->id,
                        'description'  => $id['description'],
                        'price'        => $id['price'],
                        'image'        => $id['image'] ?? $cd['image'] ?? null,
                        'sort_order'   => $ii + 1,
                        'is_available' => $isAvail,
                        'is_active'    => true,
                    ]
                );
                $menuItems[] = $item;
            }
        }

        // أكواد الخصم
        PromoCode::query()->updateOrCreate(
            ['restaurant_id' => $restaurant->id, 'code' => 'DIWAN10'],
            [
                'discount_type'   => 'percentage',
                'discount_value'  => '10.00',
                'min_order_value' => '80.00',
                'is_active'       => true,
                'expires_at'      => now()->addMonths(6),
            ]
        );

        PromoCode::query()->updateOrCreate(
            ['restaurant_id' => $restaurant->id, 'code' => 'RIYADH25'],
            [
                'discount_type'   => 'fixed',
                'discount_value'  => '25.00',
                'min_order_value' => '120.00',
                'is_active'       => true,
                'expires_at'      => now()->addMonths(2),
            ]
        );

        // جلسات وطلبات (نظام الدفع المسبق Prepaid مع نوافذ الاستلام)
        $session = TableSession::query()->firstOrCreate(
            ['table_id' => $tables[0]->id, 'status' => SessionStatus::Closed],
            [
                'session_token' => $this->tokenGenerator->generateSessionToken(),
                'started_at'    => now()->subDay()->setHour(13)->setMinute(0),
                'closed_at'     => now()->subDay()->setHour(15)->setMinute(0),
            ]
        );

        // طلب مدفوع مسبقاً ومكتمل من نافذة 1
        $order1 = Order::query()->updateOrCreate(
            ['order_number' => 'ORD-' . now()->subDay()->format('Ymd') . '-0003'],
            [
                'restaurant_id'    => $restaurant->id,
                'table_id'         => $tables[0]->id,
                'table_session_id' => $session->id,
                'status'           => OrderStatus::Served,
                'service_type'     => 'dine_in',
                'pickup_window'    => 1, // تم تسليمه من نافذة 1
                'paid_at'          => now()->subDay()->setHour(13)->setMinute(5),
                'paid_by'          => $cashier->id,
                'subtotal'         => '144.90',
                'discount_amount'  => '16.10',
                'total'            => '128.80',
                'customer_note'    => 'تم الدفع مسبقاً عند الكاشير',
                'accepted_at'      => now()->subDay()->setHour(13)->setMinute(5),
                'preparing_at'     => now()->subDay()->setHour(13)->setMinute(10),
                'ready_at'         => now()->subDay()->setHour(13)->setMinute(25),
                'served_at'        => now()->subDay()->setHour(13)->setMinute(30),
            ]
        );

        OrderItem::query()->firstOrCreate(
            ['order_id' => $order1->id, 'menu_item_id' => $menuItems[0]->id],
            ['item_name_snapshot' => 'كبسة لحم', 'unit_price' => '65.00', 'quantity' => 1, 'subtotal' => '65.00']
        );
        OrderItem::query()->firstOrCreate(
            ['order_id' => $order1->id, 'menu_item_id' => $menuItems[2]->id],
            ['item_name_snapshot' => 'مندي لحم', 'unit_price' => '70.00', 'quantity' => 1, 'subtotal' => '70.00']
        );

        // طلب سفري جاهز للاستلام عند نافذة 2
        $activeSession = TableSession::query()->firstOrCreate(
            ['table_id' => $tables[1]->id, 'status' => SessionStatus::Active],
            [
                'session_token' => $this->tokenGenerator->generateSessionToken(),
                'started_at'    => now()->subMinutes(30),
            ]
        );

        $order2 = Order::query()->updateOrCreate(
            ['order_number' => 'ORD-' . now()->format('Ymd') . '-0081'],
            [
                'restaurant_id'    => $restaurant->id,
                'table_id'         => $tables[1]->id,
                'table_session_id' => $activeSession->id,
                'status'           => OrderStatus::Ready,
                'service_type'     => 'takeaway', // سفري
                'pickup_window'    => 2,          // نافذة 2 المشويات
                'paid_at'          => now()->subMinutes(25),
                'paid_by'          => $cashier->id,
                'subtotal'         => '100.00',
                'discount_amount'  => '0.00',
                'total'            => '100.00',
                'customer_note'    => 'استلام سفري نافذة 2',
                'accepted_at'      => now()->subMinutes(25),
                'preparing_at'     => now()->subMinutes(20),
                'ready_at'         => now()->subMinutes(5),
            ]
        );

        OrderItem::query()->firstOrCreate(
            ['order_id' => $order2->id, 'menu_item_id' => $menuItems[12]->id],
            ['item_name_snapshot' => 'لحم تكة', 'unit_price' => '58.00', 'quantity' => 1, 'subtotal' => '58.00']
        );
        OrderItem::query()->firstOrCreate(
            ['order_id' => $order2->id, 'menu_item_id' => $menuItems[13]->id],
            ['item_name_snapshot' => 'دجاج تكة', 'unit_price' => '42.00', 'quantity' => 1, 'subtotal' => '42.00']
        );

        // طلب جديد ينتظر تأكيد الدفع عند الكاشير
        $orderPending = Order::query()->updateOrCreate(
            ['order_number' => 'ORD-' . now()->format('Ymd') . '-0082'],
            [
                'restaurant_id'    => $restaurant->id,
                'table_id'         => $tables[1]->id,
                'table_session_id' => $activeSession->id,
                'status'           => OrderStatus::Pending,
                'service_type'     => 'dine_in',
                'pickup_window'    => null,
                'paid_at'          => null,
                'paid_by'          => null,
                'subtotal'         => '52.00',
                'discount_amount'  => '0.00',
                'total'            => '52.00',
                'customer_note'    => 'بانتظار السداد عند الكاشير للبدء بالتحضير',
            ]
        );

        OrderItem::query()->firstOrCreate(
            ['order_id' => $orderPending->id, 'menu_item_id' => $menuItems[4]->id],
            ['item_name_snapshot' => 'مظبي دجاج', 'unit_price' => '52.00', 'quantity' => 1, 'subtotal' => '52.00']
        );

        ActivityLog::query()->firstOrCreate(
            ['restaurant_id' => $restaurant->id, 'action' => 'create_restaurant'],
            ['user_id' => $admin->id, 'description' => 'قام بإنشاء وتفعيل مطعم الديوان الخليجي (Prepaid - نافذتان)']
        );
    }

    /**
     * =========================================================================
     * المطعم الثالث: مطعم قلعة حلب (Prepaid - دفع مسبق مع 3 نوافذ تسليم)
     * =========================================================================
     */
    protected function seedRestaurantAleppoCitadel(): void
    {
        $logo = '/storage/restaurants/aleppo-logo.jpg';
        $navbar = '/storage/restaurants/aleppo-navbar.jpg';
        $this->ensureImageFile($logo, 'Aleppo Logo');
        $this->ensureImageFile($navbar, 'Aleppo Navbar');

        $restaurant = Restaurant::query()->updateOrCreate(
            ['slug' => 'aleppo-citadel'],
            [
                'name'          => 'مطعم قلعة حلب',
                'type'          => 'prepaid', // <--- PREPAID
                'windows_count' => 3,        // <--- 3 نوافذ تسليم
                'phone'         => '+966559998887',
                'address'       => 'شارع التحلية، الرياض، المملكة العربية السعودية',
                'currency'      => 'SYP',
                'status'        => RestaurantStatus::Active,
                'logo'          => $logo,
                'navbar_image'  => $navbar,
                'features'      => [
                    'promo_codes'    => true,
                    'customer_notes' => true,
                    'table_sessions' => true,
                    'activity_log'   => true,
                ],
            ]
        );

        // المستخدمون مع كامل الطاقم والأدوار المحدثة والنوافذ
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@aleppo.test'],
            [
                'restaurant_id'  => $restaurant->id,
                'name'           => 'صبحي الحلبي (مدير المطعم)',
                'password'       => Hash::make('password'),
                'status'         => UserStatus::Active,
                'default_window' => null,
            ]
        );
        $admin->syncRoles([UserRole::Admin->value]);

        $cashier = User::query()->updateOrCreate(
            ['email' => 'cashier@aleppo.test'],
            [
                'restaurant_id'  => $restaurant->id,
                'name'           => 'عمر الحلبي (كاشير الدفع المسبق)',
                'password'       => Hash::make('password'),
                'status'         => UserStatus::Active,
                'default_window' => null,
            ]
        );
        $cashier->syncRoles([UserRole::Cashier->value]);
        $cashier->givePermissionTo(['manage_orders', 'view_orders', 'update_order_status']);

        // شيف نافذة 1: الكباب والمشاوي
        $chef1 = User::query()->updateOrCreate(
            ['email' => 'chef1@aleppo.test'],
            [
                'restaurant_id'  => $restaurant->id,
                'name'           => 'الشيف رضوان (نافذة 1 - الكباب والمشاوي)',
                'password'       => Hash::make('password'),
                'status'         => UserStatus::Active,
                'default_window' => 1,
            ]
        );
        $chef1->syncRoles([UserRole::Chef->value]);
        $chef1->givePermissionTo(['manage_orders', 'view_orders', 'update_order_status']);

        // شيف نافذة 2: الكبب والأطباق الساخنة
        $chef2 = User::query()->updateOrCreate(
            ['email' => 'chef2@aleppo.test'],
            [
                'restaurant_id'  => $restaurant->id,
                'name'           => 'الشيف مأمون (نافذة 2 - الكبب الحلبية)',
                'password'       => Hash::make('password'),
                'status'         => UserStatus::Active,
                'default_window' => 2,
            ]
        );
        $chef2->syncRoles([UserRole::Chef->value]);
        $chef2->givePermissionTo(['manage_orders', 'view_orders', 'update_order_status']);

        // شيف نافذة 3: المقبلات والحلويات والسفري
        $chef3 = User::query()->updateOrCreate(
            ['email' => 'chef3@aleppo.test'],
            [
                'restaurant_id'  => $restaurant->id,
                'name'           => 'الشيف حسان (نافذة 3 - المقبلات والسفري)',
                'password'       => Hash::make('password'),
                'status'         => UserStatus::Active,
                'default_window' => 3,
            ]
        );
        $chef3->syncRoles([UserRole::Chef->value]);
        $chef3->givePermissionTo(['manage_orders', 'view_orders', 'update_order_status']);

        $supervisor = User::query()->updateOrCreate(
            ['email' => 'supervisor@aleppo.test'],
            [
                'restaurant_id'  => $restaurant->id,
                'name'           => 'بشير الحلبي (مشرف تشغيل)',
                'password'       => Hash::make('password'),
                'status'         => UserStatus::Active,
                'default_window' => null,
            ]
        );
        $supervisor->syncRoles([UserRole::Supervisor->value]);

        $waiter = User::query()->updateOrCreate(
            ['email' => 'waiter@aleppo.test'],
            [
                'restaurant_id'  => $restaurant->id,
                'name'           => 'محمود الحلبي (مضيف صالة)',
                'password'       => Hash::make('password'),
                'status'         => UserStatus::Active,
                'default_window' => null,
            ]
        );
        $waiter->syncRoles([UserRole::Waiter->value]);

        // الطاولات
        $tableNames = [
            'طاولة القلعة 1', 'طاولة القلعة 2', 'طاولة القلعة 3',
            'جلسة الباب الأحمر 1', 'جلسة الباب الأحمر 2',
            'ركن التراث 1',
        ];

        $tables = [];
        foreach ($tableNames as $tName) {
            $t = RestaurantTable::query()->firstOrCreate(
                ['restaurant_id' => $restaurant->id, 'name' => $tName],
                [
                    'public_token' => $this->tokenGenerator->generatePublicToken(),
                    'status'       => TableStatus::Active,
                ]
            );

            if (! $t->public_token) {
                $t->public_token = $this->tokenGenerator->generatePublicToken();
                $t->save();
            }

            $qrPath = "qr/{$t->restaurant_id}/{$t->public_token}.png";
            if (! Storage::disk('public')->exists($qrPath)) {
                $this->qrService->generateForTable($t);
            }
            $tables[] = $t;
        }

        // قائمة الطعام الحلبية
        $categoriesData3 = [
            [
                'name'        => 'المقبلات الحلبية',
                'description' => 'مقبلات حلبية أصيلة',
                'image'       => '/storage/menu/aleppo-appetizers.jpg',
                'items'       => [
                    ['name' => 'محمرة بالجوز', 'price' => '25.00', 'description' => 'محمرة بالفليفلة والجوز ودبس الرمان', 'image' => '/storage/menu/items/muhammara.jpg'],
                    ['name' => 'حمص حلبي', 'price' => '18.00', 'description' => 'حمص بالطحينة مع اللحم والصنوبر', 'image' => '/storage/menu/items/hummus.jpg'],
                    ['name' => 'متبل باذنجان حلبي', 'price' => '20.00', 'description' => 'باذنجان مشوي مع الطحينة والرمان', 'image' => '/storage/menu/items/mutabbal.jpg'],
                    ['name' => 'فتة حمص بالسمن العربي', 'price' => '32.00', 'description' => 'فتة حمص بالخبز المقرمش والسمن واللحم', 'image' => '/storage/menu/items/fatteh_hummus.jpg'],
                ],
            ],
            [
                'name'        => 'المشاوي والكباب الحلبي',
                'description' => 'كباب ومشاوي حلبية على الفحم',
                'image'       => '/storage/menu/aleppo-grills.jpg',
                'items'       => [
                    ['name' => 'كباب بالكرز', 'price' => '75.00', 'description' => 'كباب لحم مع صلصة الكرز الحلبي والصنوبر', 'image' => '/storage/menu/items/cherry_kebab.jpg'],
                    ['name' => 'كباب حلبي', 'price' => '65.00', 'description' => 'كباب لحم غنم مع الفستق الحلبي والتوابل', 'image' => '/storage/menu/items/aleppo_kebab.jpg'],
                    ['name' => 'شقف مشوية', 'price' => '70.00', 'description' => 'شقف لحم عجل طرية مشوية على الفحم', 'image' => '/storage/menu/items/lamb_chops.jpg'],
                    ['name' => 'شيش طاووق حلبي', 'price' => '52.00', 'description' => 'قطع دجاج متبلة بالثوم ودبس الفليفلة', 'image' => '/storage/menu/items/shish_tawook.jpg'],
                ],
            ],
            [
                'name'        => 'الكبب الحلبية العريقة',
                'description' => 'أنواع الكبة الحلبية الشهيرة',
                'image'       => '/storage/menu/aleppo-kibbeh.jpg',
                'items'       => [
                    ['name' => 'كبة سفرجلية', 'price' => '60.00', 'description' => 'كبة مع السفرجل ودبس الرمان واللحم', 'image' => '/storage/menu/items/kibbeh_safarjaliyeh.jpg'],
                    ['name' => 'كبة لبنية', 'price' => '55.00', 'description' => 'أقراص كبة مطبوخة باللبن مع الطرخون', 'image' => '/storage/menu/items/kibbeh_labniyeh.jpg'],
                    ['name' => 'كبة مشوية', 'price' => '45.00', 'description' => 'أقراص كبة مشوية على الفحم محشية بالشحم والجوز', 'image' => '/storage/menu/items/grilled_kibbeh.jpg'],
                    ['name' => 'كبة مقلية حلبية', 'price' => '38.00', 'description' => 'كبة مقلية مقرمشة محشية باللحم والصنوبر', 'image' => '/storage/menu/items/fried_kibbeh.jpg'],
                ],
            ],
            [
                'name'        => 'الحلويات الحلبية',
                'description' => 'حلويات حلبية عريقة بالفستق الحلبي',
                'image'       => '/storage/menu/aleppo-desserts.jpg',
                'items'       => [
                    ['name' => 'كرابيج حلب', 'price' => '35.00', 'description' => 'كرابيج محشية بالفستق مع الناطف الحلبي', 'image' => '/storage/menu/items/karabij.jpg'],
                    ['name' => 'بقلاوة بالفستق', 'price' => '40.00', 'description' => 'بقلاوة حلبية أصيلة بالسمن العربي والفستق', 'image' => '/storage/menu/items/baklava.jpg'],
                    ['name' => 'كنافة بالقشطة', 'price' => '30.00', 'description' => 'كنافة ذهبية محشية بالقشطة البلدية', 'image' => '/storage/menu/items/knafeh.jpg'],
                    ['name' => 'عوامة مقرمشة', 'price' => '22.00', 'description' => 'كرات مقلية ذهبية بالقطر المعطر', 'image' => '/storage/menu/items/awameh.jpg'],
                ],
            ],
            [
                'name'        => 'المشروبات',
                'description' => 'مشروبات شامية منعشة وساخنة',
                'image'       => '/storage/menu/drinks.jpg',
                'items'       => [
                    ['name' => 'عيران حلبي', 'price' => '12.00', 'description' => 'لبن رائب منعش مخفوق بالنعناع والثوم الخفيف', 'image' => '/storage/menu/items/ayran.jpg'],
                    ['name' => 'ليمون ونعناع', 'price' => '15.00', 'description' => 'عصير ليمون طازج مثلج مع أوراق النعناع', 'image' => '/storage/menu/items/lemon_mint.jpg'],
                    ['name' => 'قهوة تركية بالهيل', 'price' => '14.00', 'description' => 'قهوة تركية غنية بالرغوة والهيل الحلبي', 'image' => '/storage/menu/items/turkish_coffee.jpg'],
                    ['name' => 'شاي بالنعناع', 'price' => '8.00', 'description' => 'شاي أحمر مخدر مع أوراق النعناع البلدي', 'image' => '/storage/menu/items/red_tea.jpg'],
                ],
            ],
        ];

        $menuItems = [];
        foreach ($categoriesData3 as $catIndex => $categoryData) {
            $this->ensureImageFile($categoryData['image'], $categoryData['name']);

            $category = MenuCategory::query()->updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'name' => $categoryData['name']],
                [
                    'description' => $categoryData['description'] ?? null,
                    'image'       => $categoryData['image'] ?? null,
                    'sort_order'  => $catIndex + 1,
                    'is_active'   => true,
                ]
            );

            foreach ($categoryData['items'] as $itemIndex => $itemData) {
                $this->ensureImageFile($itemData['image'], $itemData['name']);
                $item = MenuItem::query()->updateOrCreate(
                    ['restaurant_id' => $restaurant->id, 'name' => $itemData['name']],
                    [
                        'category_id'  => $category->id,
                        'description'  => $itemData['description'],
                        'price'        => $itemData['price'],
                        'image'        => $itemData['image'] ?? $categoryData['image'] ?? null,
                        'sort_order'   => $itemIndex + 1,
                        'is_available' => true,
                        'is_active'    => true,
                    ]
                );
                $menuItems[] = $item;
            }
        }

        // أكواد الخصم
        PromoCode::query()->updateOrCreate(
            ['restaurant_id' => $restaurant->id, 'code' => 'HALAB15'],
            [
                'discount_type'   => 'percentage',
                'discount_value'  => '15.00',
                'min_order_value' => '60.00',
                'is_active'       => true,
                'expires_at'      => now()->addMonths(4),
            ]
        );

        PromoCode::query()->updateOrCreate(
            ['restaurant_id' => $restaurant->id, 'code' => 'CITADEL25'],
            [
                'discount_type'   => 'fixed',
                'discount_value'  => '25.00',
                'min_order_value' => '100.00',
                'is_active'       => true,
                'expires_at'      => now()->addMonths(2),
            ]
        );

        // جلسات وطلبات مدفوعة مسبقاً موزعة على النوافذ الثلاثة
        $session = TableSession::query()->firstOrCreate(
            ['table_id' => $tables[0]->id, 'status' => SessionStatus::Active],
            [
                'session_token' => $this->tokenGenerator->generateSessionToken(),
                'started_at'    => now()->subMinutes(40),
            ]
        );

        // طلب 1: قيد التحضير عند نافذة 1 (المشاوي والكباب)
        $order1 = Order::query()->updateOrCreate(
            ['order_number' => 'ORD-' . now()->format('Ymd') . '-0091'],
            [
                'restaurant_id'    => $restaurant->id,
                'table_id'         => $tables[0]->id,
                'table_session_id' => $session->id,
                'status'           => OrderStatus::Preparing,
                'service_type'     => 'dine_in',
                'pickup_window'    => 1, // نافذة 1
                'paid_at'          => now()->subMinutes(35),
                'paid_by'          => $cashier->id,
                'subtotal'         => '140.00',
                'discount_amount'  => '0.00',
                'total'            => '140.00',
                'customer_note'    => 'مدفوع مسبقاً - تحضير نافذة 1',
                'accepted_at'      => now()->subMinutes(35),
                'preparing_at'     => now()->subMinutes(30),
            ]
        );

        OrderItem::query()->firstOrCreate(
            ['order_id' => $order1->id, 'menu_item_id' => $menuItems[4]->id], // كباب بالكرز
            ['item_name_snapshot' => 'كباب بالكرز', 'unit_price' => '75.00', 'quantity' => 1, 'subtotal' => '75.00']
        );
        OrderItem::query()->firstOrCreate(
            ['order_id' => $order1->id, 'menu_item_id' => $menuItems[5]->id], // كباب حلبي
            ['item_name_snapshot' => 'كباب حلبي', 'unit_price' => '65.00', 'quantity' => 1, 'subtotal' => '65.00']
        );

        // طلب 2: جاهز للاستلام عند نافذة 2 (الكبب)
        $order2 = Order::query()->updateOrCreate(
            ['order_number' => 'ORD-' . now()->format('Ymd') . '-0092'],
            [
                'restaurant_id'    => $restaurant->id,
                'table_id'         => $tables[0]->id,
                'table_session_id' => $session->id,
                'status'           => OrderStatus::Ready,
                'service_type'     => 'takeaway', // سفري
                'pickup_window'    => 2,          // نافذة 2
                'paid_at'          => now()->subMinutes(25),
                'paid_by'          => $cashier->id,
                'subtotal'         => '115.00',
                'discount_amount'  => '0.00',
                'total'            => '115.00',
                'customer_note'    => 'سفري استلام نافذة 2 الكبب',
                'accepted_at'      => now()->subMinutes(25),
                'preparing_at'     => now()->subMinutes(20),
                'ready_at'         => now()->subMinutes(2),
            ]
        );

        OrderItem::query()->firstOrCreate(
            ['order_id' => $order2->id, 'menu_item_id' => $menuItems[8]->id], // سفرجلية
            ['item_name_snapshot' => 'كبة سفرجلية', 'unit_price' => '60.00', 'quantity' => 1, 'subtotal' => '60.00']
        );
        OrderItem::query()->firstOrCreate(
            ['order_id' => $order2->id, 'menu_item_id' => $menuItems[9]->id], // لبنية
            ['item_name_snapshot' => 'كبة لبنية', 'unit_price' => '55.00', 'quantity' => 1, 'subtotal' => '55.00']
        );

        // طلب 3: تم تسليمه من نافذة 3 (الحلويات والمقبلات)
        $order3 = Order::query()->updateOrCreate(
            ['order_number' => 'ORD-' . now()->format('Ymd') . '-0093'],
            [
                'restaurant_id'    => $restaurant->id,
                'table_id'         => $tables[1]->id,
                'table_session_id' => $session->id,
                'status'           => OrderStatus::Served,
                'service_type'     => 'dine_in',
                'pickup_window'    => 3, // نافذة 3
                'paid_at'          => now()->subMinutes(50),
                'paid_by'          => $cashier->id,
                'subtotal'         => '75.00',
                'discount_amount'  => '0.00',
                'total'            => '75.00',
                'customer_note'    => 'تم الاستلام من نافذة 3',
                'accepted_at'      => now()->subMinutes(50),
                'preparing_at'     => now()->subMinutes(45),
                'ready_at'         => now()->subMinutes(35),
                'served_at'        => now()->subMinutes(30),
            ]
        );

        OrderItem::query()->firstOrCreate(
            ['order_id' => $order3->id, 'menu_item_id' => $menuItems[12]->id], // كرابيج
            ['item_name_snapshot' => 'كرابيج حلب', 'unit_price' => '35.00', 'quantity' => 1, 'subtotal' => '35.00']
        );
        OrderItem::query()->firstOrCreate(
            ['order_id' => $order3->id, 'menu_item_id' => $menuItems[13]->id], // بقلاوة
            ['item_name_snapshot' => 'بقلاوة بالفستق', 'unit_price' => '40.00', 'quantity' => 1, 'subtotal' => '40.00']
        );

        // طلب 4: معلق ينتظر الدفع عند الكاشير
        $orderPending = Order::query()->updateOrCreate(
            ['order_number' => 'ORD-' . now()->format('Ymd') . '-0094'],
            [
                'restaurant_id'    => $restaurant->id,
                'table_id'         => $tables[2]->id,
                'table_session_id' => $session->id,
                'status'           => OrderStatus::Pending,
                'service_type'     => 'takeaway',
                'pickup_window'    => null,
                'paid_at'          => null,
                'paid_by'          => null,
                'subtotal'         => '25.00',
                'discount_amount'  => '0.00',
                'total'            => '25.00',
                'customer_note'    => 'طلب جديد بانتظار سداد الكاشير',
            ]
        );

        OrderItem::query()->firstOrCreate(
            ['order_id' => $orderPending->id, 'menu_item_id' => $menuItems[0]->id], // محمرة
            ['item_name_snapshot' => 'محمرة بالجوز', 'unit_price' => '25.00', 'quantity' => 1, 'subtotal' => '25.00']
        );

        ActivityLog::query()->firstOrCreate(
            ['restaurant_id' => $restaurant->id, 'action' => 'create_restaurant'],
            ['user_id' => $admin->id, 'description' => 'قام بإنشاء وتفعيل مطعم قلعة حلب (Prepaid - 3 نوافذ تسليم)']
        );
    }
}
