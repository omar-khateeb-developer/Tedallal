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

class TestRestaurantSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('بدء إنشاء بيانات مطعم التجربة...');

        // 1. Create Restaurant
        $restaurant = Restaurant::create([
            'name'     => 'مطعم التجربة الشاملة',
            'slug'     => 'test-restaurant-' . Str::random(5),
            'currency' => 'SYP',
            'status'   => RestaurantStatus::Active,
        ]);
        $this->command->info("المطعم: {$restaurant->name} (ID: {$restaurant->id})");

        // 2. Admin user
        $adminEmail = 'test-admin-' . Str::random(4) . '@tedallal.com';
        $admin = User::create([
            'name'          => 'مدير التجربة',
            'email'         => $adminEmail,
            'password'      => Hash::make('password'),
            'restaurant_id' => $restaurant->id,
        ]);
        $admin->assignRole('admin');
        $this->command->info("المدير: {$adminEmail} / password");

        // 3. Chef user
        $chefEmail = 'test-chef-' . Str::random(4) . '@tedallal.com';
        $chef = User::create([
            'name'          => 'شيف التجربة',
            'email'         => $chefEmail,
            'password'      => Hash::make('password'),
            'restaurant_id' => $restaurant->id,
        ]);
        $chef->assignRole('staff');
        $this->command->info("الشيف: {$chefEmail} / password");

        // 4. Tables
        $table1 = RestaurantTable::create(['restaurant_id' => $restaurant->id, 'name' => 'طاولة رقم 1', 'public_token' => Str::random(32)]);
        $table2 = RestaurantTable::create(['restaurant_id' => $restaurant->id, 'name' => 'طاولة رقم 2', 'public_token' => Str::random(32)]);
        $table3 = RestaurantTable::create(['restaurant_id' => $restaurant->id, 'name' => 'طاولة رقم 3', 'public_token' => Str::random(32)]);
        $this->command->info('تم إنشاء 3 طاولات');

        // 5. Menu Categories
        $catDrinks   = MenuCategory::create(['restaurant_id' => $restaurant->id, 'name' => 'مشروبات باردة', 'is_active' => true]);
        $catMeals    = MenuCategory::create(['restaurant_id' => $restaurant->id, 'name' => 'وجبات رئيسية', 'is_active' => true]);
        $catDesserts = MenuCategory::create(['restaurant_id' => $restaurant->id, 'name' => 'حلويات',        'is_active' => true]);

        // 6. Menu Items  (category_id is the correct column name)
        $juice   = MenuItem::create(['restaurant_id' => $restaurant->id, 'category_id' => $catDrinks->id,   'name' => 'عصير برتقال طازج', 'price' => 15000, 'is_active' => true]);
        $water   = MenuItem::create(['restaurant_id' => $restaurant->id, 'category_id' => $catDrinks->id,   'name' => 'ماء معدني',         'price' =>  5000, 'is_active' => true]);
        $burger  = MenuItem::create(['restaurant_id' => $restaurant->id, 'category_id' => $catMeals->id,    'name' => 'برغر لحم مزدوج',    'price' => 45000, 'is_active' => true]);
        $chicken = MenuItem::create(['restaurant_id' => $restaurant->id, 'category_id' => $catMeals->id,    'name' => 'دجاج مشوي',         'price' => 35000, 'is_active' => true]);
        $cake    = MenuItem::create(['restaurant_id' => $restaurant->id, 'category_id' => $catDesserts->id, 'name' => 'كيك الشوكولاتة',    'price' => 20000, 'is_active' => true]);
        $this->command->info('تم إنشاء 5 عناصر في القائمة');

        // 7. Table Sessions
        $session1 = TableSession::create(['table_id' => $table1->id, 'session_token' => Str::random(64), 'status' => SessionStatus::Active, 'started_at' => now()->subMinutes(30)]);
        $session2 = TableSession::create(['table_id' => $table2->id, 'session_token' => Str::random(64), 'status' => SessionStatus::Active, 'started_at' => now()->subMinutes(45)]);
        $session3 = TableSession::create(['table_id' => $table3->id, 'session_token' => Str::random(64), 'status' => SessionStatus::Active, 'started_at' => now()->subMinutes(10)]);
        $this->command->info('تم إنشاء 3 جلسات للطاولات');

        // 8. Orders with different statuses for testing
        $order1 = Order::create(['restaurant_id' => $restaurant->id, 'table_id' => $table1->id, 'table_session_id' => $session1->id, 'order_number' => 'TEST-001', 'status' => OrderStatus::Pending,   'subtotal' => 60000, 'total' => 60000]);
        OrderItem::create(['order_id' => $order1->id, 'menu_item_id' => $juice->id,  'item_name_snapshot' => $juice->name,  'unit_price' => 15000, 'quantity' => 1, 'subtotal' => 15000]);
        OrderItem::create(['order_id' => $order1->id, 'menu_item_id' => $burger->id, 'item_name_snapshot' => $burger->name, 'unit_price' => 45000, 'quantity' => 1, 'subtotal' => 45000]);

        $order2 = Order::create(['restaurant_id' => $restaurant->id, 'table_id' => $table2->id, 'table_session_id' => $session2->id, 'order_number' => 'TEST-002', 'status' => OrderStatus::Accepted,  'subtotal' => 40000, 'total' => 40000]);
        OrderItem::create(['order_id' => $order2->id, 'menu_item_id' => $chicken->id, 'item_name_snapshot' => $chicken->name, 'unit_price' => 35000, 'quantity' => 1, 'subtotal' => 35000]);
        OrderItem::create(['order_id' => $order2->id, 'menu_item_id' => $water->id,   'item_name_snapshot' => $water->name,   'unit_price' =>  5000, 'quantity' => 1, 'subtotal' =>  5000]);

        $order3 = Order::create(['restaurant_id' => $restaurant->id, 'table_id' => $table3->id, 'table_session_id' => $session3->id, 'order_number' => 'TEST-003', 'status' => OrderStatus::Preparing, 'subtotal' => 70000, 'total' => 70000]);
        OrderItem::create(['order_id' => $order3->id, 'menu_item_id' => $burger->id, 'item_name_snapshot' => $burger->name, 'unit_price' => 45000, 'quantity' => 1, 'subtotal' => 45000]);
        OrderItem::create(['order_id' => $order3->id, 'menu_item_id' => $cake->id,   'item_name_snapshot' => $cake->name,   'unit_price' => 20000, 'quantity' => 1, 'subtotal' => 20000]);
        OrderItem::create(['order_id' => $order3->id, 'menu_item_id' => $water->id,  'item_name_snapshot' => $water->name,  'unit_price' =>  5000, 'quantity' => 1, 'subtotal' =>  5000]);

        $this->command->info('تم إنشاء 3 طلبات (pending / accepted / preparing)');
        $this->command->newLine();
        $this->command->line('المدير: ' . $adminEmail . ' / password');
        $this->command->line('الشيف: ' . $chefEmail . ' / password');
        $this->command->line('الطلبات: TEST-001 (pending) | TEST-002 (accepted) | TEST-003 (preparing)');
    }
}