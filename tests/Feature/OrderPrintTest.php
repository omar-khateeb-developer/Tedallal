<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\User;
use App\Services\TableSessionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderPrintTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_can_view_order_print_receipt(): void
    {
        $admin = User::where('email', 'admin@bait-alsham.test')->firstOrFail();
        $restaurant = Restaurant::find($admin->restaurant_id);

        $table = RestaurantTable::where('restaurant_id', $restaurant->id)->firstOrFail();
        $session = app(TableSessionService::class)->resolveOrCreate($table);

        $order = Order::create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'table_session_id' => $session->id,
            'order_number' => 'ORD-TEST-99',
            'status' => 'pending',
            'subtotal' => 50.00,
            'total' => 50.00,
            'customer_note' => 'بدون شطة يرجى الاستعجال',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'item_name_snapshot' => 'وجبة شاورما دجاج',
            'unit_price' => 25.00,
            'quantity' => 2,
            'subtotal' => 50.00,
            'note' => 'زيادة ثوم وبطاطا مقرمشة',
        ]);

        $response = $this->actingAs($admin)->get("/admin/orders/{$order->id}/print");

        $response->assertOk();
        $response->assertSee('ORD-TEST-99');
        $response->assertSee('بدون شطة يرجى الاستعجال');
        $response->assertSee('وجبة شاورما دجاج');
        $response->assertSee('زيادة ثوم وبطاطا مقرمشة');
        $response->assertSee($restaurant->name);
    }

    public function test_staff_with_manage_orders_can_view_order_print_receipt(): void
    {
        $staff = User::where('email', 'cashier@bait-alsham.test')->firstOrFail();
        $restaurant = Restaurant::find($staff->restaurant_id);
        $table = RestaurantTable::where('restaurant_id', $restaurant->id)->firstOrFail();
        $session = app(TableSessionService::class)->resolveOrCreate($table);

        $order = Order::create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'table_session_id' => $session->id,
            'order_number' => 'ORD-STAFF-01',
            'status' => 'accepted',
            'subtotal' => 30.00,
            'total' => 30.00,
            'customer_note' => 'ملاحظة خاصة بالزبون',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'item_name_snapshot' => 'عصير برتقال طازج',
            'unit_price' => 15.00,
            'quantity' => 2,
            'subtotal' => 30.00,
            'note' => 'بدون سكر',
        ]);

        $response = $this->actingAs($staff)->get("/admin/orders/{$order->id}/print");

        $response->assertOk();
        $response->assertSee('ORD-STAFF-01');
        $response->assertSee('ملاحظة خاصة بالزبون');
        $response->assertSee('عصير برتقال طازج');
        $response->assertSee('بدون سكر');
    }
}
