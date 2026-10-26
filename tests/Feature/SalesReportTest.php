<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SalesReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_can_retrieve_daily_sales_report_with_accurate_math(): void
    {
        $restaurant = Restaurant::factory()->create(['currency' => 'SAR']);
        $table = RestaurantTable::factory()->create(['restaurant_id' => $restaurant->id]);
        $session = app(\App\Services\TableSessionService::class)->resolveOrCreate($table);

        $admin = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'status' => UserStatus::Active,
        ]);
        $admin->assignRole(UserRole::Admin->value);
        Sanctum::actingAs($admin);

        // Order 1: Served (counted in revenue)
        Order::create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'table_session_id' => $session->id,
            'order_number' => 'ORD-101',
            'status' => OrderStatus::Served,
            'subtotal' => 100.00,
            'discount_amount' => 10.00,
            'total' => 90.00,
            'service_type' => 'dine_in',
            'created_at' => now(),
        ]);

        // Order 2: Paid (prepaid, counted in revenue)
        Order::create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'table_session_id' => $session->id,
            'order_number' => 'ORD-102',
            'status' => OrderStatus::Preparing,
            'paid_at' => now(),
            'subtotal' => 50.00,
            'discount_amount' => 0.00,
            'total' => 50.00,
            'service_type' => 'takeaway',
            'created_at' => now(),
        ]);

        // Order 3: Cancelled (MUST NOT be counted in revenue, tracked as lost)
        Order::create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'table_session_id' => $session->id,
            'order_number' => 'ORD-103',
            'status' => OrderStatus::Cancelled,
            'subtotal' => 80.00,
            'discount_amount' => 0.00,
            'total' => 80.00,
            'created_at' => now(),
        ]);

        $response = $this->getJson('/api/admin/reports/sales?view=daily&year=' . now()->year . '&month=' . now()->month);

        $response->assertOk();
        $summary = $response->json('data.summary');

        $this->assertEquals('150.00', $summary['gross_sales']);
        $this->assertEquals('10.00', $summary['total_discounts']);
        $this->assertEquals('140.00', $summary['net_sales']);
        $this->assertEquals(2, $summary['total_orders']);
        $this->assertEquals('70.00', $summary['aov']);
        $this->assertEquals(1, $summary['cancelled_orders']);
        $this->assertEquals('80.00', $summary['cancelled_lost']);
    }

    public function test_sales_report_respects_tenant_isolation(): void
    {
        $restaurant1 = Restaurant::factory()->create();
        $restaurant2 = Restaurant::factory()->create();

        $table1 = RestaurantTable::factory()->create(['restaurant_id' => $restaurant1->id]);
        $session1 = app(\App\Services\TableSessionService::class)->resolveOrCreate($table1);

        $admin1 = User::factory()->create([
            'restaurant_id' => $restaurant1->id,
            'status' => UserStatus::Active,
        ]);
        $admin1->assignRole(UserRole::Admin->value);
        Sanctum::actingAs($admin1);

        // Order for restaurant 2
        $table2 = RestaurantTable::factory()->create(['restaurant_id' => $restaurant2->id]);
        $session2 = app(\App\Services\TableSessionService::class)->resolveOrCreate($table2);
        Order::create([
            'restaurant_id' => $restaurant2->id,
            'table_id' => $table2->id,
            'table_session_id' => $session2->id,
            'order_number' => 'ORD-OTHER-RESTAURANT',
            'status' => OrderStatus::Served,
            'subtotal' => 999.00,
            'discount_amount' => 0.00,
            'total' => 999.00,
            'created_at' => now(),
        ]);

        $response = $this->getJson('/api/admin/reports/sales?view=daily');
        $response->assertOk();
        $summary = $response->json('data.summary');

        // Restaurant 1 should have 0 sales, completely isolated from Restaurant 2
        $this->assertEquals('0.00', $summary['net_sales']);
        $this->assertEquals(0, $summary['total_orders']);
    }
}
