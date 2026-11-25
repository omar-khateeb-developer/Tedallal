<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\RestaurantPlan;
use App\Enums\RestaurantStatus;
use App\Enums\SessionStatus;
use App\Enums\TableStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\ActivityLog;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderWheelToken;
use App\Models\PromoCode;
use App\Models\Restaurant;
use App\Models\RestaurantCustomer;
use App\Models\RestaurantTable;
use App\Models\TableReservation;
use App\Models\TableServiceCall;
use App\Models\TableSession;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Services\QrCodeService;
use App\Services\TokenGenerator;
use App\Support\MoneyCalculator;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class FlagshipRestaurantsSeeder extends Seeder
{
    private TokenGenerator $tokenGenerator;
    private QrCodeService $qrService;

    public function run(): void
    {
        $this->tokenGenerator = app(TokenGenerator::class);
        $this->qrService = app(QrCodeService::class);

        $this->ensureRolesAndPermissions();

        $this->seedJamraCraftPrepaidFlagship();
        $this->seedDarAlYasminPostpaidVipFlagship();

        $this->command?->info('تم زرع المطعمين المتكاملين (جمرة آند كرافت Prepaid + دار الياسمين Postpaid VIP) بكامل الميزات والبيانات والصور.');
    }

    private function ensureRolesAndPermissions(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (UserRole::cases() as $roleEnum) {
            $role = Role::firstOrCreate(['name' => $roleEnum->value, 'guard_name' => 'web']);
            foreach ($roleEnum->defaultPermissions() as $permName) {
                Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
            }
            if ($roleEnum !== UserRole::SuperAdmin) {
                $role->syncPermissions($roleEnum->defaultPermissions());
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * المطعم الأول: مطعم جمرة آند كرافت (Prepaid الشامل — 3 نوافذ تحضير + كامل الميزات)
     */
    private function seedJamraCraftPrepaidFlagship(): void
    {
        $restaurant = Restaurant::query()->updateOrCreate(
            ['slug' => 'jamra-craft'],
            [
                'name' => 'مطعم جمرة آند كرافت',
                'type' => 'prepaid',
                'windows_count' => 3,
                'phone' => '+963944880111',
                'address' => 'دمشق - أوتوستراد المزة، بناء النخبة التجاري',
                'currency' => 'SYP',
                'status' => RestaurantStatus::Active,
                'plan' => RestaurantPlan::Max,
                'subscription_expires_at' => now()->addYear(),
                'logo' => '/images/restaurants/jamra-craft/logo.jpg',
                'navbar_image' => '/images/restaurants/jamra-craft/navbar.jpg',
                'whatsapp_auto_reply_enabled' => true,
                'whatsapp_auto_reply_message' => "أهلاً بك في مطعم جمرة آند كرافت 🔥 يمكنك استعراض قائمتنا، الطلب السريع عبر نوافذ التحضير، أو تجربة عجلة الحظ عبر منصة تدلل!",
                'whatsapp_expiring_reminders_enabled' => true,
                'features' => [
                    'analytics' => true,
                    'offers' => true,
                    'ai_waiter' => true,
                    'custom_branding' => true,
                    'whatsapp_reminders' => true,
                    'whatsapp_ai_reply' => true,
                    'menu_engineering' => true,
                    'floor_plan' => true,
                    'promo_codes' => true,
                    'customer_notes' => true,
                    'table_sessions' => true,
                    'activity_log' => true,
                    'kds' => true,
                    'auto_print_on_ready' => true,
                    'wheel_require_whatsapp' => true,
                    'wheel_reveal_code_after_save' => true,
                    'loyalty_enabled' => true,
                    'loyalty_target_visits' => 5,
                    'loyalty_reward_type' => 'free_item',
                    'loyalty_reward_value' => 185,
                    'loyalty_menu_item_id' => null,
                    'accounting_mapping' => [
                        'cash_account_code' => '10105-JAMRA',
                        'cash_account_name' => 'صندوق الكاشير المركزي - جمرة آند كرافت',
                        'dine_in_sales_code' => '40101-DINE',
                        'dine_in_sales_name' => 'مبيعات صالة مطعم جمرة',
                        'takeaway_sales_code' => '40102-TAKE',
                        'takeaway_sales_name' => 'مبيعات السفري والطلبات السريعة',
                        'discount_account_code' => '40201-DISC',
                        'discount_account_name' => 'خصومات وعروض عجلة الحظ',
                        'tax_account_code' => '20301-TAX',
                        'tax_account_name' => 'ضريبة الخدمات والرسوم',
                    ],
                ],
            ]
        );

        // الطاقم الكامل (8 حسابات — كلمة المرور: password)
        $admin = $this->seedStaff($restaurant->id, 'admin@jamra.test', 'طارق الجمراني (مدير المطعم)', UserRole::Admin);
        $supervisor = $this->seedStaff($restaurant->id, 'supervisor@jamra.test', 'ريم العظمة (مشرف التشغيل والجودة)', UserRole::Supervisor);
        $cashier = $this->seedStaff($restaurant->id, 'cashier@jamra.test', 'باسل الحمصي (كاشير الدفع المسبق)', UserRole::Cashier);
        $chef1 = $this->seedStaff($restaurant->id, 'chef1@jamra.test', 'الشيف مازن الحديد (نافذة 1 - البرغر والشواء)', UserRole::Chef, 1);
        $chef2 = $this->seedStaff($restaurant->id, 'chef2@jamra.test', 'الشيف ليلى الدالاتي (نافذة 2 - البيتزا والباستا)', UserRole::Chef, 2);
        $chef3 = $this->seedStaff($restaurant->id, 'chef3@jamra.test', 'الشيف نديم الحلواني (نافذة 3 - المقبلات والحلى)', UserRole::Chef, 3);
        $waiter = $this->seedStaff($restaurant->id, 'waiter@jamra.test', 'سامر المصري (مضيف صالة جمرة)', UserRole::Waiter);
        $this->seedStaff($restaurant->id, 'staff@jamra.test', 'كريم زيدان (مسؤول التغليف والسفري)', UserRole::Staff);

        // المخطط المعماري للطاولات + كاونتر السفري
        $tables = $this->seedTables($restaurant->id, [
            ['name' => 'طاولة الصالة J1', 'type' => RestaurantTable::TYPE_DINE_IN, 'zone' => 'main', 'shape' => 'circle', 'cap' => 2, 'x' => 90, 'y' => 100, 'stage' => 'available'],
            ['name' => 'طاولة الصالة J2', 'type' => RestaurantTable::TYPE_DINE_IN, 'zone' => 'main', 'shape' => 'circle', 'cap' => 4, 'x' => 285, 'y' => 100, 'stage' => 'preparing', 'guest' => 'مجموعة الشباب - أ. نادر'],
            ['name' => 'طاولة الصالة J3', 'type' => RestaurantTable::TYPE_DINE_IN, 'zone' => 'main', 'shape' => 'rectangle', 'cap' => 4, 'x' => 480, 'y' => 100, 'stage' => 'available'],
            ['name' => 'طاولة الصالة J4', 'type' => RestaurantTable::TYPE_DINE_IN, 'zone' => 'main', 'shape' => 'rectangle', 'cap' => 6, 'x' => 675, 'y' => 100, 'stage' => 'available'],
            ['name' => 'تراس المزة T1', 'type' => RestaurantTable::TYPE_DINE_IN, 'zone' => 'terrace', 'shape' => 'circle', 'cap' => 4, 'x' => 90, 'y' => 275, 'stage' => 'served', 'guest' => 'لينا وعائلتها'],
            ['name' => 'تراس المزة T2', 'type' => RestaurantTable::TYPE_DINE_IN, 'zone' => 'terrace', 'shape' => 'circle', 'cap' => 4, 'x' => 285, 'y' => 275, 'stage' => 'available'],
            ['name' => 'جلسة العائلات F1', 'type' => RestaurantTable::TYPE_DINE_IN, 'zone' => 'family', 'shape' => 'booth', 'cap' => 6, 'x' => 480, 'y' => 275, 'stage' => 'available'],
            ['name' => 'جلسة العائلات F2', 'type' => RestaurantTable::TYPE_DINE_IN, 'zone' => 'family', 'shape' => 'booth', 'cap' => 8, 'x' => 675, 'y' => 275, 'stage' => 'available'],
            ['name' => 'جناح جمرة الخاص V1', 'type' => RestaurantTable::TYPE_DINE_IN, 'zone' => 'vip', 'shape' => 'vip', 'cap' => 8, 'x' => 920, 'y' => 150, 'stage' => 'available'],
            ['name' => 'كاونتر السفري - جمرة', 'type' => RestaurantTable::TYPE_TAKEAWAY, 'zone' => 'main', 'shape' => 'rectangle', 'cap' => 1, 'x' => 0, 'y' => 0, 'stage' => 'available'],
        ]);

        // التصنيفات والأصناف مع الصور المولّدة عالية الدقة
        $items = $this->seedMenu($restaurant->id, [
            [
                'name' => 'برغر اللحم المدخن والواغيو',
                'description' => 'سماش برغر واغيو وبريسكت مدخن 12 ساعة بخبز البريوش الطازج',
                'image' => '/images/restaurants/jamra-craft/truffle_burger.jpg',
                'items' => [
                    ['sku' => 'JMR-001', 'name' => 'دبل سماش ترافل برغر', 'price' => '185.00', 'desc' => 'شريحتا لحم واغيو مشويتان مع جبنة شيدر معتقة وصلصة الكمأة السوداء', 'img' => '/images/restaurants/jamra-craft/truffle_burger.jpg'],
                    ['sku' => 'JMR-002', 'name' => 'برغر جمرة كلاسيك المدخن', 'price' => '145.00', 'desc' => 'لحم أنغوس مشوي على الفحم مع بصل مكرمل وصوص جمرة السري', 'img' => '/images/restaurants/jamra-craft/classic_burger.jpg'],
                    ['sku' => 'JMR-003', 'name' => 'برغر بريسكت البقر المسحب', 'price' => '195.00', 'desc' => 'بريسكت مدخن على خشب الجوز 12 ساعة مع مخلل حار وكول سلو', 'img' => '/images/restaurants/jamra-craft/brisket_burger.jpg'],
                    ['sku' => 'JMR-004', 'name' => 'برغر دجاج كريسبي بالعسل الحار', 'price' => '130.00', 'desc' => 'صدر دجاج مقرمش متبل بالعسل الطبيعي وصلصة الماسترد المدخنة', 'img' => '/images/restaurants/jamra-craft/crispy_chicken.jpg'],
                ],
            ],
            [
                'name' => 'ستيك وأضلاع التدخين',
                'description' => 'قطعيات لحم فاخرة مشوية على الحطب ومدخنة ببطء',
                'image' => '/images/restaurants/jamra-craft/ribeye_steak.jpg',
                'items' => [
                    ['sku' => 'JMR-005', 'name' => 'ستيك ريب آي على الحطب', 'price' => '360.00', 'desc' => 'ستيك ريب آي فاخر مشوي بزبدة الأعشاب والثوم المشوي وملح البحر', 'img' => '/images/restaurants/jamra-craft/ribeye_steak.jpg'],
                    ['sku' => 'JMR-006', 'name' => 'أضلاع لحم مدخنة 12 ساعة', 'price' => '410.00', 'desc' => 'أضلاع تكساس مدخنة بقشرة بهارات غنية وتتبيلة الباربكيو المدخن', 'img' => '/images/restaurants/jamra-craft/smoked_ribs.jpg'],
                    ['sku' => 'JMR-007', 'name' => 'كفتة حطب بجبنة القشقوان', 'price' => '175.00', 'desc' => 'أسياخ كفتة لحم مشوية على الجمر محشية بقشقوان ذائب', 'img' => '/images/restaurants/jamra-craft/kofta_kashkawan.jpg'],
                    ['sku' => 'JMR-008', 'name' => 'نصف دجاج مشوي بالأعشاب والتوابل', 'price' => '140.00', 'desc' => 'نصف دجاجة متبلة 24 ساعة بالثوم والليمون وإكليل الجبل على الجمر', 'img' => '/images/restaurants/jamra-craft/tandoori_chicken.jpg'],
                ],
            ],
            [
                'name' => 'بيتزا الحجر والباستا',
                'description' => 'عجينة مخمرة 48 ساعة في فرن الحجر مع أطباق الباستا الغنية',
                'image' => '/images/restaurants/jamra-craft/burrata_pizza.jpg',
                'items' => [
                    ['sku' => 'JMR-009', 'name' => 'بيتزا بوراتا وبيستو الريحان', 'price' => '165.00', 'desc' => 'جبنة بوراتا كريمية طازجة مع طماطم سان مارزانو وصوص بيستو منزلي', 'img' => '/images/restaurants/jamra-craft/burrata_pizza.jpg'],
                    ['sku' => 'JMR-010', 'name' => 'بيتزا بريسكت وبصل مكرمل', 'price' => '190.00', 'desc' => 'عجينة حجرية مقرمشة مغطاة ببريسكت مدخن وجرجير طازج وباربكيو', 'img' => '/images/restaurants/jamra-craft/brisket_pizza.jpg'],
                    ['sku' => 'JMR-011', 'name' => 'فيتوتشيني فطر الكمأة الكريمي', 'price' => '155.00', 'desc' => 'باستا إيطالية بصلصة الفطر البري وزيت الكمأة والبارميزان المعتق', 'img' => '/images/restaurants/jamra-craft/truffle_pasta.jpg'],
                    ['sku' => 'JMR-012', 'name' => 'ريزوتو الزعفران والدجاج المشوي', 'price' => '160.00', 'desc' => 'أرز أربوريو كريمي بالزعفران الفاخر مع شرائح دجاج مشوية', 'img' => '/images/restaurants/jamra-craft/saffron_risotto.jpg'],
                ],
            ],
            [
                'name' => 'المقبلات والبطاطا المميزة',
                'description' => 'مقبلات تشاركية ساخنة وسلطات عصرية طازجة',
                'image' => '/images/restaurants/jamra-craft/truffle_fries.jpg',
                'items' => [
                    ['sku' => 'JMR-013', 'name' => 'بطاطا الترافل بالبارميزان', 'price' => '75.00', 'desc' => 'بطاطا مقرمشة برشة بارميزان وبقدونس مع صوص الترافل الكريمي', 'img' => '/images/restaurants/jamra-craft/truffle_fries.jpg'],
                    ['sku' => 'JMR-014', 'name' => 'أجنحة دجاج مدخنة بالباربكيو', 'price' => '95.00', 'desc' => 'أجنحة دجاج مدخنة ومغطاة بصلصة هيكوري باربكيو والسمسم المحمص', 'img' => '/images/restaurants/jamra-craft/bbq_wings.jpg'],
                    ['sku' => 'JMR-015', 'name' => 'سلطة كينوا بالأفوكادو والرمان', 'price' => '85.00', 'desc' => 'كينوا عضوية مع أفوكادو طازج وحب الرمان وصلصة الليمون بالعسل', 'img' => '/images/restaurants/jamra-craft/quinoa_salad.jpg'],
                    ['sku' => 'JMR-016', 'name' => 'أصابع موتزاريلا ذهبية', 'price' => '70.00', 'desc' => 'جبنة موتزاريلا مقرمشة بالأعشاب الإيطالية مع صوص مارينارا', 'img' => '/images/restaurants/jamra-craft/mozzarella_sticks.jpg'],
                ],
            ],
            [
                'name' => 'الحلويات والموكتيلات',
                'description' => 'حلويات فاخرة ومشروبات باردة محضرة عند الطلب',
                'image' => '/images/restaurants/jamra-craft/san_sebastian.jpg',
                'items' => [
                    ['sku' => 'JMR-017', 'name' => 'تشيزكيك سان سيباستيان بالشوكولا', 'price' => '110.00', 'desc' => 'تشيزكيك محروقة بقوام كريمي مع صوص شوكولا بلجيكية دافئة', 'img' => '/images/restaurants/jamra-craft/san_sebastian.jpg'],
                    ['sku' => 'JMR-018', 'name' => 'مولتن شوكولا بركانية مع آيس كريم', 'price' => '105.00', 'desc' => 'كيكة شوكولا دافئة محشية غناش غني مع آيس كريم فانيليا مدغشقر', 'img' => '/images/restaurants/jamra-craft/molten_lava.jpg'],
                    ['sku' => 'JMR-019', 'name' => 'موكتيل باشن فروت ونعناع', 'price' => '55.00', 'desc' => 'باشن فروت استوائي طازج مع أوراق النعناع وثلج مجروش', 'img' => '/images/restaurants/jamra-craft/passion_mocktail.jpg'],
                    ['sku' => 'JMR-020', 'name' => 'آيسد لاتيه كراميل مملح', 'price' => '60.00', 'desc' => 'إسبريسو مختص مزدوج مع حليب بارد وصوص كراميل مملح منزلي', 'img' => '/images/restaurants/jamra-craft/iced_caramel_latte.jpg'],
                ],
            ],
        ]);

        // ربط جائزة الولاء بالطبق المميز
        $truffleBurger = $items['JMR-001'];
        $features = $restaurant->features;
        $features['loyalty_menu_item_id'] = $truffleBurger->id;
        $features['loyalty_reward_value'] = (float) $truffleBurger->price;
        $restaurant->update(['features' => $features]);

        // عملاء الولاء (CRM)
        $customers = $this->seedCustomers($restaurant->id, [
            ['name' => 'نادر خوري', 'phone' => '963944100201', 'visits' => 5, 'notes' => 'يفضل دبل سماش برغر مع ترافل إضافي'],
            ['name' => 'لينا الشهابي', 'phone' => '963944100202', 'visits' => 4, 'notes' => 'عميلة دائمة — تحب بيتزا بوراتا وموكتيل باشن'],
            ['name' => 'غيث السالم', 'phone' => '963944100203', 'visits' => 3, 'notes' => 'يطلب سفري غالباً وقت الظهيرة'],
            ['name' => 'ميار العلي', 'phone' => '963944100204', 'visits' => 2, 'notes' => 'جناح VIP في عطلة نهاية الأسبوع'],
            ['name' => 'علاء المنصور', 'phone' => '963944100205', 'visits' => 6, 'notes' => 'عميل VIP وصل لهدف الولاء الخامس'],
            ['name' => 'رؤى حسام', 'phone' => '963944100206', 'visits' => 1, 'notes' => 'تواصلت عبر عجلة الحظ'],
        ]);

        // أكواد الخصم والعروض الترويجية
        $promos = $this->seedPromoCodes($restaurant->id, [
            ['code' => 'JAMRA15', 'type' => 'percentage', 'val' => '15.00', 'min' => '150.00', 'source' => 'manual', 'single' => false, 'exp' => now()->addMonths(4)],
            ['code' => 'SMOKED50', 'type' => 'fixed', 'val' => '50.00', 'min' => '250.00', 'source' => 'manual', 'single' => false, 'exp' => now()->addMonths(3)],
            ['code' => 'FREE-BURGER-VIP', 'type' => 'free_item', 'val' => '185.00', 'min' => '200.00', 'source' => 'loyalty', 'single' => true, 'customer_id' => $customers[4]->id, 'free_item_id' => $truffleBurger->id, 'exp' => now()->addDays(20)],
            ['code' => 'WHL-JMR77', 'type' => 'percentage', 'val' => '20.00', 'min' => '100.00', 'source' => 'wheel', 'single' => true, 'customer_id' => $customers[0]->id, 'exp' => now()->addDays(2)],
            ['code' => 'LOYAL-JMR05', 'type' => 'percentage', 'val' => '25.00', 'min' => '150.00', 'source' => 'loyalty', 'single' => true, 'customer_id' => $customers[1]->id, 'exp' => now()->addDays(15)],
        ]);

        // جلسات نشطة وتاريخية + 15 طلب (10 تاريخية عبر 14 يوم + 5 اليوم عبر جميع الحالات والنوافذ)
        $activeSessionJ2 = $this->ensureTableSession($tables['طاولة الصالة J2'], SessionStatus::Active, now()->subMinutes(28));
        $activeSessionT1 = $this->ensureTableSession($tables['تراس المزة T1'], SessionStatus::Active, now()->subMinutes(42));
        $takeawayTable = $tables['كاونتر السفري - جمرة'];

        // طلبات اليوم (5 طلبات: معلق للكاشير + تحضير نافذة 1 + جاهز نافذة 2 + مكتمل نافذة 3 + ملغي)
        $this->seedOrderWithItems($restaurant->id, 'JMR-LIVE-01', $tables['طاولة الصالة J1'], null, OrderStatus::Pending, 'dine_in', null, null, null, now()->subMinutes(4), [
            [$items['JMR-002'], 2],
            [$items['JMR-013'], 1],
        ], null, 'بانتظار الدفع المسبق عند الكاشير');

        $this->seedOrderWithItems($restaurant->id, 'JMR-LIVE-02', $tables['طاولة الصالة J2'], $activeSessionJ2, OrderStatus::Preparing, 'dine_in', 1, $cashier->id, now()->subMinutes(22), now()->subMinutes(25), [
            [$items['JMR-001'], 2],
            [$items['JMR-006'], 1],
        ], null, 'تحضير نافذة 1 — بدون مخلل لبرغر واحد');

        $this->seedOrderWithItems($restaurant->id, 'JMR-LIVE-03', $takeawayTable, null, OrderStatus::Ready, 'takeaway', 2, $cashier->id, now()->subMinutes(18), now()->subMinutes(20), [
            [$items['JMR-009'], 1],
            [$items['JMR-011'], 1],
        ], null, 'سفري جاهز للاستلام من نافذة 2');

        $servedTodayOrder = $this->seedOrderWithItems($restaurant->id, 'JMR-LIVE-04', $tables['تراس المزة T1'], $activeSessionT1, OrderStatus::Served, 'dine_in', 3, $cashier->id, now()->subMinutes(40), now()->subMinutes(41), [
            [$items['JMR-017'], 2],
            [$items['JMR-019'], 2],
        ], $promos['JAMRA15'], 'طلب حلى وموكتيلات تم تسليمه من نافذة 3');

        $this->seedOrderWithItems($restaurant->id, 'JMR-LIVE-05', $takeawayTable, null, OrderStatus::Cancelled, 'takeaway', null, null, null, now()->subHours(2), [
            [$items['JMR-016'], 1],
        ], null, 'إلغاء بناءً على طلب الزبون قبل السداد');

        // 10 طلبات تاريخية عبر آخر 14 يوماً لإغناء التحليلات وتقارير المبيعات وهندسة المنيو
        $historicalPlans = [
            ['num' => 'JMR-HIST-01', 'days' => 1, 'hr' => 20, 'type' => 'dine_in', 'win' => 1, 'table' => 'طاولة الصالة J4', 'promo' => null, 'lines' => [['JMR-005', 2], ['JMR-013', 2], ['JMR-019', 2]]],
            ['num' => 'JMR-HIST-02', 'days' => 2, 'hr' => 14, 'type' => 'takeaway', 'win' => 2, 'table' => 'كاونتر السفري - جمرة', 'promo' => 'JAMRA15', 'lines' => [['JMR-009', 2], ['JMR-010', 1], ['JMR-020', 2]]],
            ['num' => 'JMR-HIST-03', 'days' => 3, 'hr' => 21, 'type' => 'dine_in', 'win' => 1, 'table' => 'جناح جمرة الخاص V1', 'promo' => 'SMOKED50', 'lines' => [['JMR-006', 2], ['JMR-001', 3], ['JMR-017', 2]]],
            ['num' => 'JMR-HIST-04', 'days' => 4, 'hr' => 19, 'type' => 'dine_in', 'win' => 1, 'table' => 'جلسة العائلات F1', 'promo' => null, 'lines' => [['JMR-003', 2], ['JMR-007', 2], ['JMR-014', 2]]],
            ['num' => 'JMR-HIST-05', 'days' => 6, 'hr' => 20, 'type' => 'takeaway', 'win' => 3, 'table' => 'كاونتر السفري - جمرة', 'promo' => null, 'lines' => [['JMR-018', 2], ['JMR-015', 1], ['JMR-020', 2]]],
            ['num' => 'JMR-HIST-06', 'days' => 7, 'hr' => 13, 'type' => 'dine_in', 'win' => 2, 'table' => 'تراس المزة T2', 'promo' => null, 'lines' => [['JMR-011', 2], ['JMR-012', 1], ['JMR-019', 3]]],
            ['num' => 'JMR-HIST-07', 'days' => 9, 'hr' => 21, 'type' => 'dine_in', 'win' => 1, 'table' => 'جلسة العائلات F2', 'promo' => 'JAMRA15', 'lines' => [['JMR-005', 2], ['JMR-006', 1], ['JMR-001', 2], ['JMR-013', 2]]],
            ['num' => 'JMR-HIST-08', 'days' => 10, 'hr' => 15, 'type' => 'takeaway', 'win' => 1, 'table' => 'كاونتر السفري - جمرة', 'promo' => null, 'lines' => [['JMR-002', 3], ['JMR-004', 2]]],
            ['num' => 'JMR-HIST-09', 'days' => 12, 'hr' => 20, 'type' => 'dine_in', 'win' => 2, 'table' => 'طاولة الصالة J3', 'promo' => null, 'lines' => [['JMR-010', 2], ['JMR-014', 1], ['JMR-017', 1]]],
            ['num' => 'JMR-HIST-10', 'days' => 14, 'hr' => 19, 'type' => 'dine_in', 'win' => 1, 'table' => 'جناح جمرة الخاص V1', 'promo' => 'SMOKED50', 'lines' => [['JMR-001', 4], ['JMR-008', 2], ['JMR-018', 2]]],
        ];

        $histOrders = [];
        foreach ($historicalPlans as $plan) {
            $ts = Carbon::now()->subDays($plan['days'])->setTime($plan['hr'], 15);
            $tableObj = $tables[$plan['table']];
            $closedSess = TableSession::query()->firstOrCreate(
                ['session_token' => 'SESS-' . $plan['num']],
                ['table_id' => $tableObj->id, 'status' => SessionStatus::Closed, 'started_at' => $ts->copy()->subMinutes(45), 'closed_at' => $ts]
            );
            $orderLines = array_map(fn ($l) => [$items[$l[0]], $l[1]], $plan['lines']);
            $promoObj = $plan['promo'] ? ($promos[$plan['promo']] ?? null) : null;
            $histOrders[] = $this->seedOrderWithItems($restaurant->id, $plan['num'], $tableObj, $closedSess, OrderStatus::Served, $plan['type'], $plan['win'], $cashier->id, $ts->copy()->subMinutes(40), $ts->copy()->subMinutes(42), $orderLines, $promoObj, null);
        }

        // عجلة الحظ (3 تذاكر مرتبطة بطلبات مستقلة: 2 جاهزة للتجربة + 1 مستخدمة)
        OrderWheelToken::query()->firstOrCreate(
            ['token' => 'JMR-WHEEL-FREE-01'],
            ['restaurant_id' => $restaurant->id, 'order_id' => $servedTodayOrder->id, 'is_claimed' => false, 'expires_at' => now()->addDays(14)]
        );
        OrderWheelToken::query()->firstOrCreate(
            ['token' => 'JMR-WHEEL-FREE-02'],
            ['restaurant_id' => $restaurant->id, 'order_id' => $histOrders[0]->id, 'is_claimed' => false, 'expires_at' => now()->addDays(14)]
        );
        OrderWheelToken::query()->firstOrCreate(
            ['token' => 'JMR-WHEEL-CLAIMED-01'],
            [
                'restaurant_id' => $restaurant->id,
                'order_id' => $histOrders[1]->id,
                'customer_id' => $customers[0]->id,
                'customer_name' => $customers[0]->name,
                'customer_phone' => $customers[0]->phone,
                'is_claimed' => true,
                'claimed_at' => now()->subDays(1),
                'won_promo_code_id' => $promos['WHL-JMR77']->id,
                'generated_promo_code_id' => $promos['WHL-JMR77']->id,
                'expires_at' => now()->addDays(7),
            ]
        );

        // قائمة الانتظار الذكية (3 إدخالات لليوم)
        $this->seedWaitlist($restaurant->id, [
            ['uuid' => 'jmr-wl-001', 'name' => 'أحمد الحسن', 'phone' => '963944551101', 'size' => 4, 'pref' => 'main', 'status' => 'waiting', 'q' => 101, 'wait' => 15, 'table_id' => null],
            ['uuid' => 'jmr-wl-002', 'name' => 'زينب طه', 'phone' => '963944551102', 'size' => 2, 'pref' => 'terrace', 'status' => 'notified', 'q' => 102, 'wait' => 5, 'table_id' => $tables['تراس المزة T2']->id, 'notified_at' => now()->subMinutes(3)],
            ['uuid' => 'jmr-wl-003', 'name' => 'عائلة م. مازن', 'phone' => '963944551103', 'size' => 6, 'pref' => 'family', 'status' => 'seated', 'q' => 103, 'wait' => 0, 'table_id' => $tables['جلسة العائلات F1']->id, 'seated_at' => now()->subMinutes(20)],
        ]);

        // حجز مسبق متاح أيضاً لكون الباقة Max بكل الميزات
        $this->seedReservations($restaurant->id, $admin->id, [
            ['uuid' => 'jmr-res-001', 'table_id' => $tables['جناح جمرة الخاص V1']->id, 'name' => 'شركة النخبة للتقنية', 'phone' => '963944771199', 'date' => now()->toDateString(), 'slot' => '21:00', 'guests' => 8, 'status' => 'confirmed', 'req' => 'عشاء عمل رسمي - تحضير طاولة الجناح الخاص', 'notes' => 'حجز مؤكد من الإدارة'],
            ['uuid' => 'jmr-res-002', 'table_id' => $tables['جلسة العائلات F2']->id, 'name' => 'عائلة أبو فادي', 'phone' => '963944771188', 'date' => now()->addDay()->toDateString(), 'slot' => '19:00', 'guests' => 6, 'status' => 'confirmed', 'req' => 'جلسة عائلية مسائية', 'notes' => null],
        ]);

        // نداءات الخدمة النشطة والمكتملة
        TableServiceCall::query()->firstOrCreate(
            ['restaurant_id' => $restaurant->id, 'table_id' => $tables['طاولة الصالة J2']->id, 'type' => 'waiter_call', 'status' => 'pending'],
            ['reason' => 'طلب ماء أو ثلج', 'notes' => 'دورق ماء مثلج مع شرائح ليمون']
        );
        TableServiceCall::query()->firstOrCreate(
            ['restaurant_id' => $restaurant->id, 'table_id' => $tables['تراس المزة T1']->id, 'type' => 'waiter_call', 'status' => 'attended'],
            ['reason' => 'طلب أدوات طعام وشوك', 'attended_at' => now()->subMinutes(12), 'attended_by' => $waiter->id]
        );

        // سجل النشاطات
        $this->seedActivityLogs($restaurant->id, [
            ['user' => $admin->id, 'action' => 'create_restaurant', 'desc' => 'تفعيل مطعم جمرة آند كرافت (Prepaid — 3 نوافذ — باقة Max كاملة)'],
            ['user' => $admin->id, 'action' => 'update_promo', 'desc' => 'إطلاق كود خصم JAMRA15 ومكافأة الولاء التلقائية للزيارة الخامسة'],
            ['user' => $cashier->id, 'action' => 'confirm_payment', 'desc' => 'تأكيد سداد الطلب JMR-LIVE-02 وتحويله إلى نافذة التحضير 1'],
            ['user' => $chef2->id, 'action' => 'order_ready', 'desc' => 'إتمام تجهيز الطلب السفري JMR-LIVE-03 عند النافذة 2'],
            ['user' => $supervisor->id, 'action' => 'shift_audit', 'desc' => 'مراجعة جدول نوافذ المطبخ ومؤشرات هندسة القائمة'],
        ]);
    }

    /**
     * المطعم الثاني: مطعم دار الياسمين الدمشقي (Postpaid لاحق الدفع + حجز طاولات VIP مسبق متكامل)
     */
    private function seedDarAlYasminPostpaidVipFlagship(): void
    {
        $restaurant = Restaurant::query()->updateOrCreate(
            ['slug' => 'dar-alyasmin'],
            [
                'name' => 'مطعم دار الياسمين الدمشقي',
                'type' => 'postpaid',
                'windows_count' => 1,
                'phone' => '+963933770222',
                'address' => 'دمشق القديمة - باب توما، حارة الياسمين التراثية',
                'currency' => 'SYP',
                'status' => RestaurantStatus::Active,
                'plan' => RestaurantPlan::Max,
                'subscription_expires_at' => now()->addYear(),
                'logo' => '/images/restaurants/dar-alyasmin/logo.jpg',
                'navbar_image' => '/images/restaurants/dar-alyasmin/navbar.jpg',
                'whatsapp_auto_reply_enabled' => true,
                'whatsapp_auto_reply_message' => "أهلاً بكم في مطعم دار الياسمين الدمشقي 🌿 يسعدنا استقبال حجوزاتكم المسبقة لطاولات الصالة وأجنحة كبار الشخصيات VIP عبر منصة تدلل!",
                'whatsapp_expiring_reminders_enabled' => true,
                'features' => [
                    'analytics' => true,
                    'offers' => true,
                    'ai_waiter' => true,
                    'custom_branding' => true,
                    'whatsapp_reminders' => true,
                    'whatsapp_ai_reply' => true,
                    'menu_engineering' => true,
                    'floor_plan' => true,
                    'promo_codes' => true,
                    'customer_notes' => true,
                    'table_sessions' => true,
                    'activity_log' => true,
                    'kds' => true,
                    'auto_print_on_ready' => false,
                    'wheel_require_whatsapp' => true,
                    'wheel_reveal_code_after_save' => true,
                    'loyalty_enabled' => true,
                    'loyalty_target_visits' => 4,
                    'loyalty_reward_type' => 'percentage',
                    'loyalty_reward_value' => 25,
                    'loyalty_menu_item_id' => null,
                    'accounting_mapping' => [
                        'cash_account_code' => '10108-YASMIN',
                        'cash_account_name' => 'الصندوق الرئيسي - دار الياسمين الدمشقي',
                        'dine_in_sales_code' => '40101-HALL',
                        'dine_in_sales_name' => 'إيرادات الصالة وأجنحة VIP',
                        'takeaway_sales_code' => '40102-TOGO',
                        'takeaway_sales_name' => 'مبيعات السفري والضيافة',
                        'discount_account_code' => '40201-DISC',
                        'discount_account_name' => 'حسميات الضيافة والولاء',
                        'tax_account_code' => '20301-SRV',
                        'tax_account_name' => 'رسوم الخدمة والضرائب',
                    ],
                ],
            ]
        );

        // طاقم دار الياسمين الكامل (6 حسابات — كلمة المرور: password)
        $admin = $this->seedStaff($restaurant->id, 'admin@yasmin.test', 'هدى الدمشقية (مدير دار الياسمين)', UserRole::Admin);
        $supervisor = $this->seedStaff($restaurant->id, 'supervisor@yasmin.test', 'وليد الأيوبي (مشرف الصالة والحجوزات)', UserRole::Supervisor);
        $cashier = $this->seedStaff($restaurant->id, 'cashier@yasmin.test', 'رنا الحلبي (كاشير الصالة والفواتير)', UserRole::Cashier);
        $chef = $this->seedStaff($restaurant->id, 'chef@yasmin.test', 'الشيف عدنان الشامي (رئيس الطهاة)', UserRole::Chef, 1);
        $waiter1 = $this->seedStaff($restaurant->id, 'waiter@yasmin.test', 'فراس البيطار (مضيف أجنحة VIP والصالة)', UserRole::Waiter);
        $this->seedStaff($restaurant->id, 'waiter2@yasmin.test', 'نوار القاسمي (مضيف شرفة الياسمين)', UserRole::Waiter);

        // خريطة الصالة الهندسية لحجز VIP المسبق (توزيع رحب خالٍ من التداخل: >= 175px أفقي و >= 175px عمودي)
        $tables = $this->seedTables($restaurant->id, [
            // الصالة الدمشقية الداخلية (main)
            ['name' => 'طاولة النافورة 1', 'type' => RestaurantTable::TYPE_DINE_IN, 'zone' => 'main', 'shape' => 'circle', 'cap' => 2, 'x' => 90, 'y' => 95, 'stage' => 'available'],
            ['name' => 'طاولة النافورة 2', 'type' => RestaurantTable::TYPE_DINE_IN, 'zone' => 'main', 'shape' => 'circle', 'cap' => 4, 'x' => 285, 'y' => 95, 'stage' => 'preparing', 'guest' => 'د. حسام الشهابي'],
            ['name' => 'طاولة الإيوان 3', 'type' => RestaurantTable::TYPE_DINE_IN, 'zone' => 'main', 'shape' => 'rectangle', 'cap' => 4, 'x' => 480, 'y' => 95, 'stage' => 'available'],
            ['name' => 'طاولة الإيوان 4', 'type' => RestaurantTable::TYPE_DINE_IN, 'zone' => 'main', 'shape' => 'rectangle', 'cap' => 6, 'x' => 675, 'y' => 95, 'stage' => 'cleaning'],
            // حديقة الياسمين الخارجية (terrace)
            ['name' => 'شرفة الياسمين 1', 'type' => RestaurantTable::TYPE_DINE_IN, 'zone' => 'terrace', 'shape' => 'circle', 'cap' => 4, 'x' => 90, 'y' => 270, 'stage' => 'available'],
            ['name' => 'شرفة الياسمين 2', 'type' => RestaurantTable::TYPE_DINE_IN, 'zone' => 'terrace', 'shape' => 'circle', 'cap' => 4, 'x' => 285, 'y' => 270, 'stage' => 'served', 'guest' => 'سارة المنصور وضيوفها'],
            ['name' => 'شرفة الياسمين 3', 'type' => RestaurantTable::TYPE_DINE_IN, 'zone' => 'terrace', 'shape' => 'rectangle', 'cap' => 6, 'x' => 480, 'y' => 270, 'stage' => 'bill_requested', 'guest' => 'طاولة المهندسين', 'bill_req' => true],
            // ركن العائلات (family)
            ['name' => 'الركن العائلي التراثي', 'type' => RestaurantTable::TYPE_DINE_IN, 'zone' => 'family', 'shape' => 'booth', 'cap' => 8, 'x' => 675, 'y' => 270, 'stage' => 'available'],
            // أجنحة كبار الشخصيات (vip) داخل الصندوق الأيمن على المخطط
            ['name' => 'جناح الياسمين الملكي VIP 1', 'type' => RestaurantTable::TYPE_DINE_IN, 'zone' => 'vip', 'shape' => 'vip', 'cap' => 6, 'x' => 925, 'y' => 130, 'stage' => 'available'],
            ['name' => 'الديوان الشامي الخاص VIP 2', 'type' => RestaurantTable::TYPE_DINE_IN, 'zone' => 'vip', 'shape' => 'vip', 'cap' => 10, 'x' => 925, 'y' => 315, 'stage' => 'available'],
            // كاونتر السفري السريع
            ['name' => 'نقطة السفري - دار الياسمين', 'type' => RestaurantTable::TYPE_TAKEAWAY, 'zone' => 'main', 'shape' => 'rectangle', 'cap' => 1, 'x' => 0, 'y' => 0, 'stage' => 'available'],
        ]);

        // التصنيفات والأصناف الشامية الفاخرة (19 صنفاً بـ SKUs فريدة وصور مخصصة)
        $items = $this->seedMenu($restaurant->id, [
            [
                'name' => 'المازة الباردة والسلطات الملكية',
                'description' => 'مقبلات دمشقية باردة بزيت الزيتون البكر والرمان والصنوبر',
                'image' => '/images/restaurants/dar-alyasmin/tabbouleh.jpg',
                'items' => [
                    ['sku' => 'YSM-001', 'name' => 'تبولة الرمان والصنوبر', 'price' => '75.00', 'desc' => 'بقدونس مفروم ناعماً مع حب الرمان الطازج والصنوبر المحمص وزيت الزيتون', 'img' => '/images/restaurants/dar-alyasmin/tabbouleh.jpg'],
                    ['sku' => 'YSM-002', 'name' => 'حمص بالكمأة والصنوبر', 'price' => '85.00', 'desc' => 'حمص كريمي بالطحينة الشامية مع شرائح الكمأة وزيت الزيتون البكر', 'img' => '/images/restaurants/dar-alyasmin/truffle_hummus.jpg'],
                    ['sku' => 'YSM-003', 'name' => 'متبل شمندر وبابا غنوج', 'price' => '68.00', 'desc' => 'تشكيلة متبل الباذنجان المشوي على الحطب مع الشمندر والطحينة', 'img' => '/images/restaurants/dar-alyasmin/beetroot_mutabbal.jpg'],
                    ['sku' => 'YSM-004', 'name' => 'محمرة دمشقية بالجوز', 'price' => '72.00', 'desc' => 'فليفلة حلبة معتقة مع الجوز المطحون ودبس الرمان الأصيل', 'img' => '/images/restaurants/dar-alyasmin/muhammara.jpg'],
                ],
            ],
            [
                'name' => 'المازة الساخنة والمخبوزات',
                'description' => 'مقبلات فرن ومقليات تقليدية محضرة لحظة الطلب',
                'image' => '/images/restaurants/dar-alyasmin/kibbeh.jpg',
                'items' => [
                    ['sku' => 'YSM-005', 'name' => 'كبة دراويش مقرمشة', 'price' => '98.00', 'desc' => 'أقراص كبة شامية محشية بلحم الخاروف والجوز والصنوبر ودبس الرمان', 'img' => '/images/restaurants/dar-alyasmin/kibbeh.jpg'],
                    ['sku' => 'YSM-006', 'name' => 'رقاقات بالجبنة وحبة البركة', 'price' => '70.00', 'desc' => 'عجينة رقاق هشة محشية بخليط الأجبان البيضاء وحبة البركة والنعناع', 'img' => '/images/restaurants/dar-alyasmin/cheese_rolls.jpg'],
                    ['sku' => 'YSM-007', 'name' => 'لحم بعجين دمشقي بالسمن', 'price' => '92.00', 'desc' => 'صفيحة دمشقية رقيقة باللحم المتبل والصنوبر ودبس الرمان في فرن الحجر', 'img' => '/images/restaurants/dar-alyasmin/lahm_bi_ajin.jpg'],
                    ['sku' => 'YSM-008', 'name' => 'فخار سجق بدبس الرمان', 'price' => '115.00', 'desc' => 'سجق بيتي مطهو بالفخار مع الطماطم الكرزية والليمون ودبس الرمان', 'img' => '/images/restaurants/dar-alyasmin/sujuk_claypot.jpg'],
                ],
            ],
            [
                'name' => 'أطباق الدار والمشاوي الخاصة',
                'description' => 'أفخم المشاوي الشامية وأطباق الولائم التراثية',
                'image' => '/images/restaurants/dar-alyasmin/armenian_kebab.jpg',
                'items' => [
                    ['sku' => 'YSM-009', 'name' => 'كباب أرمني بالباذنجان المشوي', 'price' => '230.00', 'desc' => 'أسياخ كباب لحم مشوي مع شرائح باذنجان مدخنة وفستق ودبس رمان', 'img' => '/images/restaurants/dar-alyasmin/armenian_kebab.jpg'],
                    ['sku' => 'YSM-010', 'name' => 'شقف فتيلة مع مشروم مشوي', 'price' => '275.00', 'desc' => 'مكعبات فتيلة لحم غنم طرية مشوية على الفحم مع خضار مشوية', 'img' => '/images/restaurants/dar-alyasmin/shish_fillet.jpg'],
                    ['sku' => 'YSM-011', 'name' => 'فريكة ملكية بالموزات والمكسرات', 'price' => '295.00', 'desc' => 'فريكة خضراء مدخنة مطهية بالمرق مع موزات الغنم الطرية واللوز والصنوبر', 'img' => '/images/restaurants/dar-alyasmin/freekeh_lamb.jpg'],
                    ['sku' => 'YSM-012', 'name' => 'أوزي شامي باللحم والبازلاء', 'price' => '210.00', 'desc' => 'صُرَر الأوزي المقرمشة المحشية بالأرز الشامي واللحم والمكسرات المحمصة', 'img' => '/images/restaurants/dar-alyasmin/damascene_ozi.jpg'],
                ],
            ],
            [
                'name' => 'طواجن الفخار والطبخ الدمشقي',
                'description' => 'طبخ تقليدي على نار هادئة في أواني الفخار التراثية',
                'image' => '/images/restaurants/dar-alyasmin/clay_pot_lamb.jpg',
                'items' => [
                    ['sku' => 'YSM-013', 'name' => 'فخارة لحم بالعجين والزعفران', 'price' => '320.00', 'desc' => 'موزات لحم وخضار بالزعفران مطهوة داخل فخارة مغلقة بعجينة مخبوزة', 'img' => '/images/restaurants/dar-alyasmin/clay_pot_lamb.jpg'],
                    ['sku' => 'YSM-014', 'name' => 'صيادية سمك ساحلية', 'price' => '260.00', 'desc' => 'فيليه سمك مشوي فوق أرز الصيادية المكرمل مع اللوز وصوص الطحينة بالكمون', 'img' => '/images/restaurants/dar-alyasmin/sayadieh_fish.jpg'],
                    ['sku' => 'YSM-015', 'name' => 'ششبرك باللبن والكزبرة والثوم', 'price' => '175.00', 'desc' => 'عجينة محشية لحم وصنوبر مطهية باللبن المطبوخ مع قدحة الثوم والكزبرة', 'img' => '/images/restaurants/dar-alyasmin/shish_barak.jpg'],
                ],
            ],
            [
                'name' => 'الحلويات والضيافة الشامية',
                'description' => 'حلويات عربية بالقشطة والفستق مع أرقى مشروبات الضيافة الدمشقية',
                'image' => '/images/restaurants/dar-alyasmin/madlouqa.jpg',
                'items' => [
                    ['sku' => 'YSM-016', 'name' => 'مدلوقة بالقشطة والفستق الحلبي', 'price' => '95.00', 'desc' => 'كنافة خضراء مطحونة بالفستق مغطاة بقشطة بلدية وزهر الليمون', 'img' => '/images/restaurants/dar-alyasmin/madlouqa.jpg'],
                    ['sku' => 'YSM-017', 'name' => 'حلاوة الجبن الدمشقية', 'price' => '88.00', 'desc' => 'لفائف حلاوة الجبن الطرية محشية بالقشطة الطازجة ومزينة بالفستق وزهر البرتقال', 'img' => '/images/restaurants/dar-alyasmin/halawet_jibn.jpg'],
                    ['sku' => 'YSM-018', 'name' => 'إبريق شاي الياسمين الفاخر', 'price' => '45.00', 'desc' => 'إبريق زجاجي ساخن من الشاي الأخضر والأسود المعطر بأزهار الياسمين الدمشقي', 'img' => '/images/restaurants/dar-alyasmin/jasmine_tea.jpg'],
                    ['sku' => 'YSM-019', 'name' => 'موهيتو التوت الشامي بالثلج', 'price' => '52.00', 'desc' => 'شراب التوت الشامي المركز مع الليمون وأوراق النعناع الطازجة', 'img' => '/images/restaurants/dar-alyasmin/berry_mojito.jpg'],
                ],
            ],
        ]);

        // عملاء دار الياسمين VIP
        $customers = $this->seedCustomers($restaurant->id, [
            ['name' => 'سارة المنصور', 'phone' => '963933112201', 'visits' => 7, 'notes' => 'عميلة VIP — تفضل شرفة الياسمين 2'],
            ['name' => 'د. حسام الشهابي', 'phone' => '963933112202', 'visits' => 5, 'notes' => 'يحجز دائماً طاولة النافورة لأربع أشخاص'],
            ['name' => 'لؤي الخالدي', 'phone' => '963933112203', 'visits' => 4, 'notes' => 'حجوزات مجالس VIP للعشاء الرسمي'],
            ['name' => 'هبة المهايني', 'phone' => '963933112204', 'visits' => 3, 'notes' => 'تحب الششبرك والمدلوقة'],
            ['name' => 'م. باسل العظم', 'phone' => '963933112205', 'visits' => 6, 'notes' => 'غداء عمل دوري'],
            ['name' => 'ندى القوتلي', 'phone' => '963933112206', 'visits' => 2, 'notes' => 'حجزت عبر نظام الحجز المسبق VIP'],
        ]);

        // أكواد الخصم
        $promos = $this->seedPromoCodes($restaurant->id, [
            ['code' => 'YASMIN20', 'type' => 'percentage', 'val' => '20.00', 'min' => '200.00', 'source' => 'manual', 'single' => false, 'exp' => now()->addMonths(6)],
            ['code' => 'DAMASCUS50', 'type' => 'fixed', 'val' => '50.00', 'min' => '300.00', 'source' => 'manual', 'single' => false, 'exp' => now()->addMonths(4)],
            ['code' => 'VIP-GARDEN', 'type' => 'percentage', 'val' => '15.00', 'min' => '150.00', 'source' => 'manual', 'single' => false, 'exp' => now()->addMonths(3)],
            ['code' => 'LOYAL-YSM04', 'type' => 'percentage', 'val' => '25.00', 'min' => '150.00', 'source' => 'loyalty', 'single' => true, 'customer_id' => $customers[2]->id, 'exp' => now()->addDays(25)],
            ['code' => 'WHL-YSM88', 'type' => 'percentage', 'val' => '18.00', 'min' => '100.00', 'source' => 'wheel', 'single' => true, 'customer_id' => $customers[0]->id, 'exp' => now()->addDays(3)],
        ]);

        // حجوزات الطاولات المسبقة VIP (6 حجوزات: مؤكدة، جالس الآن، ومستقبلية، مع ترك الساعة 19:00 اليوم متاحة لمعظم الطاولات!)
        $this->seedReservations($restaurant->id, $admin->id, [
            [
                'uuid' => 'ysm-res-001',
                'table_id' => $tables['شرفة الياسمين 2']->id,
                'name' => 'سارة المنصور وضيوفها',
                'phone' => '963933112201',
                'date' => now()->toDateString(),
                'slot' => '17:00',
                'guests' => 4,
                'status' => 'seated',
                'req' => 'إطلالة مباشرة على نافورة الياسمين في الحديقة',
                'notes' => 'تم استقبال الضيوف وإجلاسهم عند الموعد',
            ],
            [
                'uuid' => 'ysm-res-002',
                'table_id' => $tables['طاولة النافورة 2']->id,
                'name' => 'د. حسام الشهابي',
                'phone' => '963933112202',
                'date' => now()->toDateString(),
                'slot' => '20:00',
                'guests' => 4,
                'status' => 'confirmed',
                'req' => 'طاولة هادئة بجانب النافورة مع ضيافة شاي الياسمين',
                'notes' => 'تم تأكيد الحجز برمز OTP واتساب',
            ],
            [
                'uuid' => 'ysm-res-003',
                'table_id' => $tables['الديوان الشامي الخاص VIP 2']->id,
                'name' => 'أ. لؤي الخالدي',
                'phone' => '963933112203',
                'date' => now()->toDateString(),
                'slot' => '21:00',
                'guests' => 8,
                'status' => 'confirmed',
                'req' => 'عشاء عائلي فاخر في الديوان الخاص مع تجهيز ضيافة عربية',
                'notes' => 'تجهيز فخارة اللحم مسبقاً عند الوصول',
            ],
            [
                'uuid' => 'ysm-res-004',
                'table_id' => $tables['جناح الياسمين الملكي VIP 1']->id,
                'name' => 'م. باسل العظم',
                'phone' => '963933112205',
                'date' => now()->addDay()->toDateString(),
                'slot' => '19:00',
                'guests' => 6,
                'status' => 'confirmed',
                'req' => 'اجتماع عشاء استثماري خاص داخل جناح VIP 1',
                'notes' => null,
            ],
            [
                'uuid' => 'ysm-res-005',
                'table_id' => $tables['طاولة الإيوان 3']->id,
                'name' => 'ندى القوتلي',
                'phone' => '963933112206',
                'date' => now()->addDay()->toDateString(),
                'slot' => '20:00',
                'guests' => 4,
                'status' => 'confirmed',
                'req' => 'احتفال تخرج عائلي',
                'notes' => null,
            ],
            [
                'uuid' => 'ysm-res-006',
                'table_id' => $tables['طاولة النافورة 1']->id,
                'name' => 'زياد الشعار',
                'phone' => '963933112299',
                'date' => now()->subDay()->toDateString(),
                'slot' => '19:00',
                'guests' => 2,
                'status' => 'completed',
                'req' => 'عشاء هادئ',
                'notes' => 'تم إغلاق الجلسة بنجاح',
            ],
        ]);

        // طابور الانتظار الرقمي اليوم (4 ضيوف)
        $this->seedWaitlist($restaurant->id, [
            ['uuid' => 'ysm-wl-001', 'name' => 'طلال السالم', 'phone' => '963933998801', 'size' => 4, 'pref' => 'terrace', 'status' => 'waiting', 'q' => 1, 'wait' => 15, 'table_id' => null],
            ['uuid' => 'ysm-wl-002', 'name' => 'هبة المهايني', 'phone' => '963933998802', 'size' => 2, 'pref' => 'main', 'status' => 'waiting', 'q' => 2, 'wait' => 20, 'table_id' => null],
            ['uuid' => 'ysm-wl-003', 'name' => 'وسام الكيلاني', 'phone' => '963933998803', 'size' => 4, 'pref' => 'main', 'status' => 'notified', 'q' => 3, 'wait' => 5, 'table_id' => $tables['طاولة الإيوان 3']->id, 'notified_at' => now()->subMinutes(4)],
            ['uuid' => 'ysm-wl-004', 'name' => 'سارة المنصور', 'phone' => '963933112201', 'size' => 4, 'pref' => 'terrace', 'status' => 'seated', 'q' => 4, 'wait' => 0, 'table_id' => $tables['شرفة الياسمين 2']->id, 'seated_at' => now()->subMinutes(45)],
        ]);

        // جلسات لاحق الدفع النشطة اليوم (طلبات متعددة داخل نفس الجلسة قبل المحاسبة!)
        $sessN2 = $this->ensureTableSession($tables['طاولة النافورة 2'], SessionStatus::Active, now()->subMinutes(50));
        $sessT2 = $this->ensureTableSession($tables['شرفة الياسمين 2'], SessionStatus::Active, now()->subMinutes(45));
        $sessT3 = $this->ensureTableSession($tables['شرفة الياسمين 3'], SessionStatus::Active, now()->subMinutes(75));
        $takeawayTable = $tables['نقطة السفري - دار الياسمين'];

        // جولة 1 على طاولة النافورة 2 (مازة تم تقديمها — لاحق الدفع غير مسدد بعد)
        $this->seedOrderWithItems($restaurant->id, 'YSM-LIVE-01', $tables['طاولة النافورة 2'], $sessN2, OrderStatus::Served, 'dine_in', null, null, null, now()->subMinutes(42), [
            [$items['YSM-002'], 1],
            [$items['YSM-001'], 1],
            [$items['YSM-005'], 1],
        ], null, 'الدفعة الأولى (المازة) — جلسة لاحق دفع');

        // جولة 2 على نفس طاولة النافورة 2 (الأطباق الرئيسية قيد التحضير في المطبخ)
        $this->seedOrderWithItems($restaurant->id, 'YSM-LIVE-02', $tables['طاولة النافورة 2'], $sessN2, OrderStatus::Preparing, 'dine_in', 1, null, null, now()->subMinutes(14), [
            [$items['YSM-011'], 1],
            [$items['YSM-009'], 1],
        ], null, 'الأطباق الرئيسية على نفس فاتورة الجلسة');

        // طلب شرفة الياسمين 2 (تم تقديمه وبانتظار إغلاق الحساب لاحقاً)
        $this->seedOrderWithItems($restaurant->id, 'YSM-LIVE-03', $tables['شرفة الياسمين 2'], $sessT2, OrderStatus::Served, 'dine_in', 1, null, null, now()->subMinutes(35), [
            [$items['YSM-013'], 1],
            [$items['YSM-016'], 2],
            [$items['YSM-018'], 1],
        ], null, 'حجز مسبق — طاولة مشغولة وتناول الطعام مستمر');

        // طلب شرفة الياسمين 3 (الزبون طلب الحساب بالبطاقة)
        $this->seedOrderWithItems($restaurant->id, 'YSM-LIVE-04', $tables['شرفة الياسمين 3'], $sessT3, OrderStatus::Served, 'dine_in', 1, null, null, now()->subMinutes(65), [
            [$items['YSM-010'], 2],
            [$items['YSM-014'], 1],
            [$items['YSM-019'], 3],
        ], null, 'بانتظار الكاشير لإغلاق الفاتورة بالبطاقة');

        // طلب غداء مكتمل ومدفوع اليوم
        $servedTodayPaid = $this->seedOrderWithItems($restaurant->id, 'YSM-LIVE-05', $tables['طاولة النافورة 1'], null, OrderStatus::Served, 'dine_in', 1, $cashier->id, now()->subHours(2), now()->subHours(3), [
            [$items['YSM-012'], 2],
            [$items['YSM-004'], 1],
            [$items['YSM-017'], 2],
        ], $promos['YASMIN20'], 'تم السداد عند الكاشير بعد الوجبة');

        // 10 طلبات تاريخية عبر آخر 14 يوماً
        $historicalPlans = [
            ['num' => 'YSM-HIST-01', 'days' => 1, 'hr' => 20, 'type' => 'dine_in', 'table' => 'جناح الياسمين الملكي VIP 1', 'promo' => null, 'lines' => [['YSM-013', 2], ['YSM-011', 2], ['YSM-016', 3]]],
            ['num' => 'YSM-HIST-02', 'days' => 2, 'hr' => 21, 'type' => 'dine_in', 'table' => 'الديوان الشامي الخاص VIP 2', 'promo' => 'YASMIN20', 'lines' => [['YSM-009', 3], ['YSM-010', 2], ['YSM-005', 3], ['YSM-018', 2]]],
            ['num' => 'YSM-HIST-03', 'days' => 3, 'hr' => 15, 'type' => 'takeaway', 'table' => 'نقطة السفري - دار الياسمين', 'promo' => null, 'lines' => [['YSM-012', 2], ['YSM-007', 3]]],
            ['num' => 'YSM-HIST-04', 'days' => 5, 'hr' => 19, 'type' => 'dine_in', 'table' => 'شرفة الياسمين 1', 'promo' => 'DAMASCUS50', 'lines' => [['YSM-015', 2], ['YSM-002', 2], ['YSM-017', 2]]],
            ['num' => 'YSM-HIST-05', 'days' => 6, 'hr' => 20, 'type' => 'dine_in', 'table' => 'الركن العائلي التراثي', 'promo' => null, 'lines' => [['YSM-011', 2], ['YSM-014', 2], ['YSM-006', 2]]],
            ['num' => 'YSM-HIST-06', 'days' => 8, 'hr' => 21, 'type' => 'dine_in', 'table' => 'طاولة الإيوان 3', 'promo' => 'VIP-GARDEN', 'lines' => [['YSM-010', 2], ['YSM-003', 2], ['YSM-019', 2]]],
            ['num' => 'YSM-HIST-07', 'days' => 10, 'hr' => 14, 'type' => 'takeaway', 'table' => 'نقطة السفري - دار الياسمين', 'promo' => null, 'lines' => [['YSM-008', 2], ['YSM-007', 2]]],
            ['num' => 'YSM-HIST-08', 'days' => 11, 'hr' => 20, 'type' => 'dine_in', 'table' => 'جناح الياسمين الملكي VIP 1', 'promo' => null, 'lines' => [['YSM-013', 2], ['YSM-009', 2], ['YSM-016', 2]]],
            ['num' => 'YSM-HIST-09', 'days' => 13, 'hr' => 19, 'type' => 'dine_in', 'table' => 'طاولة النافورة 2', 'promo' => 'YASMIN20', 'lines' => [['YSM-015', 2], ['YSM-001', 2], ['YSM-004', 1]]],
            ['num' => 'YSM-HIST-10', 'days' => 14, 'hr' => 20, 'type' => 'dine_in', 'table' => 'شرفة الياسمين 3', 'promo' => null, 'lines' => [['YSM-014', 2], ['YSM-012', 2], ['YSM-018', 2]]],
        ];

        $histOrders = [];
        foreach ($historicalPlans as $plan) {
            $ts = Carbon::now()->subDays($plan['days'])->setTime($plan['hr'], 30);
            $tableObj = $tables[$plan['table']];
            $closedSess = TableSession::query()->firstOrCreate(
                ['session_token' => 'SESS-' . $plan['num']],
                ['table_id' => $tableObj->id, 'status' => SessionStatus::Closed, 'started_at' => $ts->copy()->subMinutes(70), 'closed_at' => $ts]
            );
            $orderLines = array_map(fn ($l) => [$items[$l[0]], $l[1]], $plan['lines']);
            $promoObj = $plan['promo'] ? ($promos[$plan['promo']] ?? null) : null;
            $histOrders[] = $this->seedOrderWithItems($restaurant->id, $plan['num'], $tableObj, $closedSess, OrderStatus::Served, $plan['type'], 1, $cashier->id, $ts, $ts->copy()->subMinutes(60), $orderLines, $promoObj, null);
        }

        // نداءات خدمة نشطة للصالة والمشرفين (طلب ماء + طلب فاتورة)
        TableServiceCall::query()->firstOrCreate(
            ['restaurant_id' => $restaurant->id, 'table_id' => $tables['شرفة الياسمين 2']->id, 'type' => 'waiter_call', 'status' => 'pending'],
            ['reason' => 'طلب ماء أو ثلج', 'notes' => 'إضافة إبريق شاي ياسمين مع ضيافة']
        );
        TableServiceCall::query()->firstOrCreate(
            ['restaurant_id' => $restaurant->id, 'table_id' => $tables['شرفة الياسمين 3']->id, 'type' => 'bill_request', 'status' => 'pending'],
            ['payment_method' => 'card', 'notes' => 'الدفع بالبطاقة المصرفية عند الطاولة']
        );
        TableServiceCall::query()->firstOrCreate(
            ['restaurant_id' => $restaurant->id, 'table_id' => $tables['طاولة النافورة 2']->id, 'type' => 'waiter_call', 'status' => 'attended'],
            ['reason' => 'تنظيف أو مسح الطاولة', 'attended_at' => now()->subMinutes(18), 'attended_by' => $waiter1->id]
        );

        // تذاكر عجلة الحظ (مربوطة بطلبات مستقلة)
        OrderWheelToken::query()->firstOrCreate(
            ['token' => 'YSM-WHEEL-VIP-01'],
            ['restaurant_id' => $restaurant->id, 'order_id' => $servedTodayPaid->id, 'is_claimed' => false, 'expires_at' => now()->addDays(14)]
        );
        OrderWheelToken::query()->firstOrCreate(
            ['token' => 'YSM-WHEEL-VIP-02'],
            ['restaurant_id' => $restaurant->id, 'order_id' => $histOrders[0]->id, 'is_claimed' => false, 'expires_at' => now()->addDays(14)]
        );
        OrderWheelToken::query()->firstOrCreate(
            ['token' => 'YSM-WHEEL-CLAIMED-01'],
            [
                'restaurant_id' => $restaurant->id,
                'order_id' => $histOrders[1]->id,
                'customer_id' => $customers[0]->id,
                'customer_name' => $customers[0]->name,
                'customer_phone' => $customers[0]->phone,
                'is_claimed' => true,
                'claimed_at' => now()->subHours(5),
                'won_promo_code_id' => $promos['WHL-YSM88']->id,
                'generated_promo_code_id' => $promos['WHL-YSM88']->id,
                'expires_at' => now()->addDays(7),
            ]
        );

        // سجل النشاطات
        $this->seedActivityLogs($restaurant->id, [
            ['user' => $admin->id, 'action' => 'create_restaurant', 'desc' => 'تأسيس مطعم دار الياسمين الدمشقي (Postpaid + الحجز المسبق VIP + باقة Max)'],
            ['user' => $supervisor->id, 'action' => 'seat_vip_reservation', 'desc' => 'تسجيل جلوس حجز سارة المنصور وضيوفها على شرفة الياسمين 2'],
            ['user' => $waiter1->id, 'action' => 'attend_service_call', 'desc' => 'خدمة طاولة النافورة 2 واستقبال طلب الأطباق الرئيسية YSM-LIVE-02'],
            ['user' => $chef->id, 'action' => 'kitchen_prep', 'desc' => 'بدء تجهيز الفريكة الملكية والكباب الأرمني لطاولة النافورة 2'],
            ['user' => $cashier->id, 'action' => 'checkout_session', 'desc' => 'تحصيل فاتورة طلب الغداء YSM-LIVE-05 وتطبيق كود YASMIN20'],
        ]);
    }

    private function seedStaff(int $restaurantId, string $email, string $name, UserRole $role, ?int $defaultWindow = null): User
    {
        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'restaurant_id' => $restaurantId,
                'name' => $name,
                'password' => Hash::make('password'),
                'status' => UserStatus::Active,
                'default_window' => $defaultWindow,
            ]
        );
        $user->syncRoles([$role->value]);
        $user->syncPermissions($role->defaultPermissions());

        return $user;
    }

    /**
     * @param array<int, array<string, mixed>> $specs
     * @return array<string, RestaurantTable>
     */
    private function seedTables(int $restaurantId, array $specs): array
    {
        $map = [];
        foreach ($specs as $spec) {
            $table = RestaurantTable::query()->firstOrCreate(
                ['restaurant_id' => $restaurantId, 'name' => $spec['name']],
                [
                    'public_token' => $this->tokenGenerator->generatePublicToken(),
                    'status' => TableStatus::Active,
                    'table_type' => $spec['type'],
                    'zone' => $spec['zone'],
                    'shape' => $spec['shape'],
                    'capacity' => $spec['cap'],
                    'pos_x' => $spec['x'],
                    'pos_y' => $spec['y'],
                    'rotation' => 0,
                    'operational_stage' => $spec['stage'] ?? 'available',
                    'stage_updated_at' => ($spec['stage'] ?? 'available') !== 'available' ? now()->subMinutes(15) : null,
                    'session_started_at' => ! empty($spec['guest']) ? now()->subMinutes(35) : null,
                    'bill_requested_at' => ! empty($spec['bill_req']) ? now()->subMinutes(5) : null,
                    'current_guest_name' => $spec['guest'] ?? null,
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

            $map[$spec['name']] = $table;
        }

        return $map;
    }

    /**
     * @param array<int, array<string, mixed>> $categoriesData
     * @return array<string, MenuItem>
     */
    private function seedMenu(int $restaurantId, array $categoriesData): array
    {
        $itemsBySku = [];
        foreach ($categoriesData as $catIdx => $catSpec) {
            $category = MenuCategory::query()->updateOrCreate(
                ['restaurant_id' => $restaurantId, 'name' => $catSpec['name']],
                [
                    'description' => $catSpec['description'],
                    'image' => $catSpec['image'],
                    'sort_order' => $catIdx + 1,
                    'is_active' => true,
                ]
            );

            foreach ($catSpec['items'] as $itemIdx => $itemSpec) {
                $item = MenuItem::query()->updateOrCreate(
                    ['restaurant_id' => $restaurantId, 'external_sku' => $itemSpec['sku']],
                    [
                        'category_id' => $category->id,
                        'name' => $itemSpec['name'],
                        'description' => $itemSpec['desc'],
                        'image' => $itemSpec['img'],
                        'price' => $itemSpec['price'],
                        'sort_order' => $itemIdx + 1,
                        'is_available' => true,
                        'is_active' => true,
                    ]
                );
                $itemsBySku[$itemSpec['sku']] = $item;
            }
        }

        return $itemsBySku;
    }

    /**
     * @param array<int, array<string, mixed>> $customersData
     * @return array<int, RestaurantCustomer>
     */
    private function seedCustomers(int $restaurantId, array $customersData): array
    {
        $list = [];
        foreach ($customersData as $idx => $c) {
            $list[] = RestaurantCustomer::query()->updateOrCreate(
                ['restaurant_id' => $restaurantId, 'phone' => $c['phone']],
                [
                    'name' => $c['name'],
                    'visits_count' => $c['visits'],
                    'notes' => $c['notes'],
                    'last_visit_at' => now()->subDays($idx),
                ]
            );
        }

        return $list;
    }

    /**
     * @param array<int, array<string, mixed>> $promoData
     * @return array<string, PromoCode>
     */
    private function seedPromoCodes(int $restaurantId, array $promoData): array
    {
        $map = [];
        foreach ($promoData as $p) {
            $map[$p['code']] = PromoCode::query()->updateOrCreate(
                ['restaurant_id' => $restaurantId, 'code' => $p['code']],
                [
                    'discount_type' => $p['type'],
                    'discount_value' => $p['val'],
                    'min_order_value' => $p['min'],
                    'source' => $p['source'],
                    'is_single_use' => $p['single'],
                    'customer_id' => $p['customer_id'] ?? null,
                    'free_menu_item_id' => $p['free_item_id'] ?? null,
                    'is_active' => true,
                    'starts_at' => now()->subDays(10),
                    'expires_at' => $p['exp'],
                ]
            );
        }

        return $map;
    }

    private function ensureTableSession(RestaurantTable $table, SessionStatus $status, Carbon $startedAt): TableSession
    {
        return TableSession::query()->firstOrCreate(
            ['table_id' => $table->id, 'status' => $status],
            [
                'session_token' => $this->tokenGenerator->generateSessionToken(),
                'started_at' => $startedAt,
            ]
        );
    }

    /**
     * @param array<int, array{0: MenuItem, 1: int}> $lines
     */
    private function seedOrderWithItems(
        int $restaurantId,
        string $orderNumber,
        RestaurantTable $table,
        ?TableSession $session,
        OrderStatus $status,
        string $serviceType,
        ?int $pickupWindow,
        ?int $paidBy,
        ?Carbon $paidAt,
        Carbon $createdAt,
        array $lines,
        ?PromoCode $promoCode = null,
        ?string $note = null,
    ): Order {
        $existing = Order::withoutGlobalScopes()->where('order_number', $orderNumber)->first();
        if ($existing) {
            return $existing;
        }

        if (! $session) {
            $sessStatus = $status->isTerminal() ? SessionStatus::Closed : SessionStatus::Active;
            $session = TableSession::query()->firstOrCreate(
                ['session_token' => 'SESS-' . $orderNumber],
                [
                    'table_id' => $table->id,
                    'status' => $sessStatus,
                    'started_at' => $createdAt,
                    'closed_at' => $sessStatus === SessionStatus::Closed ? $createdAt->copy()->addMinutes(25) : null,
                ]
            );
        }

        $subtotal = '0.00';
        $preparedLines = [];
        foreach ($lines as [$menuItem, $qty]) {
            $unit = number_format((float) $menuItem->price, 2, '.', '');
            $lineSub = MoneyCalculator::multiply($unit, $qty);
            $subtotal = MoneyCalculator::add($subtotal, $lineSub);
            $preparedLines[] = [
                'menu_item_id' => $menuItem->id,
                'name' => $menuItem->name,
                'unit_price' => $unit,
                'quantity' => $qty,
                'subtotal' => $lineSub,
            ];
        }

        $discount = '0.00';
        if ($promoCode) {
            if ($promoCode->discount_type === 'percentage') {
                $discount = MoneyCalculator::percentage($subtotal, number_format((float) $promoCode->discount_value, 2, '.', ''));
            } elseif ($promoCode->discount_type === 'fixed') {
                $discount = MoneyCalculator::min($subtotal, number_format((float) $promoCode->discount_value, 2, '.', ''));
            }
        }
        $total = MoneyCalculator::subtract($subtotal, $discount);

        $order = new Order([
            'restaurant_id' => $restaurantId,
            'table_id' => $table->id,
            'table_session_id' => $session?->id,
            'order_number' => $orderNumber,
            'status' => $status,
            'service_type' => $serviceType,
            'pickup_window' => $pickupWindow,
            'paid_at' => $paidAt,
            'paid_by' => $paidBy,
            'promo_code_id' => $promoCode?->id,
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'total' => $total,
            'customer_note' => $note,
            'accepted_at' => in_array($status, [OrderStatus::Accepted, OrderStatus::Preparing, OrderStatus::Ready, OrderStatus::Served], true) ? $createdAt->copy()->addMinutes(2) : null,
            'preparing_at' => in_array($status, [OrderStatus::Preparing, OrderStatus::Ready, OrderStatus::Served], true) ? $createdAt->copy()->addMinutes(4) : null,
            'ready_at' => in_array($status, [OrderStatus::Ready, OrderStatus::Served], true) ? $createdAt->copy()->addMinutes(16) : null,
            'served_at' => $status === OrderStatus::Served ? $createdAt->copy()->addMinutes(20) : null,
            'cancelled_at' => $status === OrderStatus::Cancelled ? $createdAt->copy()->addMinutes(3) : null,
        ]);
        $order->created_at = $createdAt;
        $order->updated_at = $paidAt ?? $createdAt;
        $order->save(['timestamps' => false]);

        foreach ($preparedLines as $line) {
            OrderItem::create([
                'order_id' => $order->id,
                'menu_item_id' => $line['menu_item_id'],
                'item_name_snapshot' => $line['name'],
                'unit_price' => $line['unit_price'],
                'quantity' => $line['quantity'],
                'subtotal' => $line['subtotal'],
            ]);
        }

        return $order;
    }

    /**
     * @param array<int, array<string, mixed>> $entries
     */
    private function seedWaitlist(int $restaurantId, array $entries): void
    {
        foreach ($entries as $e) {
            WaitlistEntry::query()->firstOrCreate(
                ['uuid' => $e['uuid']],
                [
                    'restaurant_id' => $restaurantId,
                    'assigned_table_id' => $e['table_id'],
                    'guest_name' => $e['name'],
                    'guest_phone' => $e['phone'],
                    'party_size' => $e['size'],
                    'seating_preference' => $e['pref'],
                    'status' => $e['status'],
                    'queue_number' => $e['q'],
                    'estimated_wait_minutes' => $e['wait'],
                    'notified_at' => $e['notified_at'] ?? null,
                    'seated_at' => $e['seated_at'] ?? null,
                ]
            );
        }
    }

    /**
     * @param array<int, array<string, mixed>> $reservations
     */
    private function seedReservations(int $restaurantId, int $staffId, array $reservations): void
    {
        foreach ($reservations as $r) {
            TableReservation::query()->firstOrCreate(
                ['uuid' => $r['uuid']],
                [
                    'restaurant_id' => $restaurantId,
                    'table_id' => $r['table_id'],
                    'guest_name' => $r['name'],
                    'guest_phone' => $r['phone'],
                    'reservation_date' => $r['date'],
                    'time_slot' => $r['slot'],
                    'guests_count' => $r['guests'],
                    'special_requests' => $r['req'],
                    'status' => $r['status'],
                    'otp_verified_at' => now()->subHours(2),
                    'deposit_amount' => 0,
                    'deposit_status' => 'none',
                    'confirmed_by_staff_id' => $staffId,
                    'staff_notes' => $r['notes'],
                ]
            );
        }
    }

    /**
     * @param array<int, array{user: int, action: string, desc: string}> $logs
     */
    private function seedActivityLogs(int $restaurantId, array $logs): void
    {
        foreach ($logs as $l) {
            ActivityLog::query()->firstOrCreate(
                ['restaurant_id' => $restaurantId, 'action' => $l['action'], 'description' => $l['desc']],
                ['user_id' => $l['user']]
            );
        }
    }
}
