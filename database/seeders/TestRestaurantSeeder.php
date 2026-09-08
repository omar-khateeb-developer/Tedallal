<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\RestaurantStatus;
use App\Enums\SessionStatus;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Enums\UserStatus;

class TestRestaurantSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('حذف البيانات القديمة...');
        OrderItem::query()->delete();
        Order::query()->delete();
        TableSession::query()->delete();
        MenuItem::query()->delete();
        MenuCategory::query()->delete();
        RestaurantTable::query()->delete();
        User::query()->delete();
        Restaurant::query()->delete();

        $this->command->info('إنشاء مدير النظام العام...');
        $this->createSuperAdmin();

        $this->command->info('إنشاء المطعم الأول: مطعم الديوان الخليجي...');
        $this->createDiwanRestaurant();

        $this->command->info('إنشاء المطعم الثاني: مقهى وبرغر ستيشن...');
        $this->createBurgerStation();

        $this->command->info('تم إنشاء المطاعم بنجاح!');
    }

    private function createSuperAdmin(): void
    {
        $superAdmin = User::create([
            'name'          => 'مدير النظام',
            'email'         => 'admin@tedallal.test',
            'password'      => 'password', // will be hashed automatically
            'status'        => UserStatus::Active,
        ]);
        $superAdmin->assignRole('super_admin');
        $this->command->info("تم إنشاء مدير النظام العام. الدخول: admin@tedallal.test / password");
    }

    private function createDiwanRestaurant(): void
    {
        $restaurant = Restaurant::create([
            'name'     => 'مطعم الديوان الخليجي',
            'slug'     => 'diwan-alkhaleej',
            'currency' => 'SAR',
            'status'   => RestaurantStatus::Active,
        ]);

        $adminEmail = 'admin@diwan.com';
        $admin = User::create([
            'name'          => 'مدير الديوان',
            'email'         => $adminEmail,
            'password'      => 'password',
            'restaurant_id' => $restaurant->id,
            'status'        => UserStatus::Active,
        ]);
        $admin->assignRole('admin');

        $chefEmail = 'chef@diwan.com';
        $chef = User::create([
            'name'          => 'شيف الديوان',
            'email'         => $chefEmail,
            'password'      => 'password',
            'restaurant_id' => $restaurant->id,
            'status'        => UserStatus::Active,
        ]);
        $chef->assignRole('staff');
        $chef->givePermissionTo(['manage_orders']);

        $table1 = RestaurantTable::create(['restaurant_id' => $restaurant->id, 'name' => 'جلسة أرضية 1', 'public_token' => Str::random(32)]);
        $table2 = RestaurantTable::create(['restaurant_id' => $restaurant->id, 'name' => 'طاولة عوائل 1', 'public_token' => Str::random(32)]);

        $catMain = MenuCategory::create(['restaurant_id' => $restaurant->id, 'name' => 'الأرز والأطباق الرئيسية', 'is_active' => true]);
        $catApps = MenuCategory::create(['restaurant_id' => $restaurant->id, 'name' => 'المقبلات والسلطات', 'is_active' => true]);
        $catDrinks = MenuCategory::create(['restaurant_id' => $restaurant->id, 'name' => 'المشروبات الساخنة', 'is_active' => true]);

        MenuItem::create([
            'restaurant_id' => $restaurant->id, 'category_id' => $catMain->id,
            'name' => 'كبسة لحم', 'description' => 'أرز كبسة مع لحم غنم طازج ومكسرات',
            'price' => 130.00, 'is_active' => true,
            'image' => 'https://images.unsplash.com/photo-1565557623262-b51c2513a641?auto=format&fit=crop&w=800&q=80' // rice/meat concept
        ]);

        MenuItem::create([
            'restaurant_id' => $restaurant->id, 'category_id' => $catMain->id,
            'name' => 'مندي دجاج', 'description' => 'دجاج محمر مع أرز المندي المدخن',
            'price' => 45.00, 'is_active' => true,
            'image' => 'https://images.unsplash.com/photo-1626779836932-5bdc1b12bba7?auto=format&fit=crop&w=800&q=80' // chicken rice
        ]);

        MenuItem::create([
            'restaurant_id' => $restaurant->id, 'category_id' => $catApps->id,
            'name' => 'حمص بالطحينة', 'description' => 'حمص بلدي مع زيت الزيتون الصافي',
            'price' => 15.00, 'is_active' => true,
            'image' => 'https://images.unsplash.com/photo-1577906096429-f73c2c312435?auto=format&fit=crop&w=800&q=80' // hummus
        ]);

        MenuItem::create([
            'restaurant_id' => $restaurant->id, 'category_id' => $catDrinks->id,
            'name' => 'قهوة عربية', 'description' => 'دلة قهوة عربية بالهيل والزعفران',
            'price' => 25.00, 'is_active' => true,
            'image' => 'https://images.unsplash.com/photo-1578314675249-a6910f80cc4e?auto=format&fit=crop&w=800&q=80' // arabic coffee
        ]);

        $this->command->info("تم إنشاء الديوان. الدخول: {$adminEmail} / password");
    }

    private function createBurgerStation(): void
    {
        $restaurant = Restaurant::create([
            'name'     => 'برغر ستيشن',
            'slug'     => 'burger-station',
            'currency' => 'USD',
            'status'   => RestaurantStatus::Active,
        ]);

        $adminEmail = 'admin@burger.com';
        $admin = User::create([
            'name'          => 'مدير برغر ستيشن',
            'email'         => $adminEmail,
            'password'      => 'password',
            'restaurant_id' => $restaurant->id,
            'status'        => UserStatus::Active,
        ]);
        $admin->assignRole('admin');

        $table1 = RestaurantTable::create(['restaurant_id' => $restaurant->id, 'name' => 'Table 1', 'public_token' => Str::random(32)]);
        $table2 = RestaurantTable::create(['restaurant_id' => $restaurant->id, 'name' => 'Table 2', 'public_token' => Str::random(32)]);

        $catBurgers = MenuCategory::create(['restaurant_id' => $restaurant->id, 'name' => 'Burgers', 'is_active' => true]);
        $catSides = MenuCategory::create(['restaurant_id' => $restaurant->id, 'name' => 'Sides & Fries', 'is_active' => true]);
        $catDrinks = MenuCategory::create(['restaurant_id' => $restaurant->id, 'name' => 'Drinks', 'is_active' => true]);

        MenuItem::create([
            'restaurant_id' => $restaurant->id, 'category_id' => $catBurgers->id,
            'name' => 'Classic Smash Burger', 'description' => 'Double beef patty, cheddar, secret sauce',
            'price' => 8.50, 'is_active' => true,
            'image' => 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?auto=format&fit=crop&w=800&q=80' // burger
        ]);

        MenuItem::create([
            'restaurant_id' => $restaurant->id, 'category_id' => $catSides->id,
            'name' => 'Cheese Fries', 'description' => 'Crispy fries loaded with melted cheese and jalapenos',
            'price' => 4.50, 'is_active' => true,
            'image' => 'https://images.unsplash.com/photo-1541592106381-b31e9677c0e5?auto=format&fit=crop&w=800&q=80' // fries
        ]);

        MenuItem::create([
            'restaurant_id' => $restaurant->id, 'category_id' => $catDrinks->id,
            'name' => 'Strawberry Milkshake', 'description' => 'Fresh strawberries blended with vanilla ice cream',
            'price' => 5.00, 'is_active' => true,
            'image' => 'https://images.unsplash.com/photo-1572490122747-3968b75cc699?auto=format&fit=crop&w=800&q=80' // milkshake
        ]);

        $this->command->info("تم إنشاء برغر ستيشن. الدخول: {$adminEmail} / password");
    }
}