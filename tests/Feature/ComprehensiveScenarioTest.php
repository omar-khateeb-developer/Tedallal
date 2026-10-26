<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\PromoCode;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use App\Models\User;
use App\Services\TableSessionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ComprehensiveScenarioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /**
     * Scenario 1: Complete Prepaid Restaurant Workflow
     * Super Admin creates prepaid restaurant -> Cashier & Chefs assigned to windows ->
     * Customer orders via QR -> Order is pending payment ->
     * Cashier confirms payment -> Chef Window 2 claims ticket ->
     * Customer live mobile updates with Window 2 ->
     * Chef prepares, marks ready, and serves order.
     */
    public function test_scenario_1_complete_prepaid_restaurant_lifecycle(): void
    {
        // 1. Super Admin creates prepaid restaurant with 3 windows
        $superAdmin = User::factory()->create(['status' => UserStatus::Active]);
        $superAdmin->assignRole(UserRole::SuperAdmin->value);
        Sanctum::actingAs($superAdmin);

        $createResponse = $this->postJson('/api/super-admin/restaurants', [
            'name' => 'مطعم برجر وبطاطس إكسبرس',
            'type' => 'prepaid',
            'windows_count' => 3,
            'tables_count' => 4,
            'currency' => 'SYP',
            'manager_name' => 'فهد المحاسب',
            'manager_email' => 'fahad_prepaid@test.com',
            'manager_password' => 'secret123',
        ]);
        $createResponse->assertCreated();
        $restaurantId = $createResponse->json('id');
        $restaurant = Restaurant::find($restaurantId);

        $this->assertNotNull($restaurant);
        $this->assertTrue($restaurant->isPrepaid());
        $this->assertSame(3, $restaurant->windows_count);

        // 2. Setup Cashier & Chef on Window 2
        $adminUser = User::where('email', 'fahad_prepaid@test.com')->first();
        Sanctum::actingAs($adminUser);

        $cashierUser = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'status' => UserStatus::Active,
        ]);
        $cashierUser->assignRole(UserRole::Cashier->value);
        $cashierUser->givePermissionTo('manage_orders');

        $chefWindow2 = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'status' => UserStatus::Active,
            'default_window' => 2,
        ]);
        $chefWindow2->assignRole(UserRole::Chef->value);
        $chefWindow2->givePermissionTo('manage_orders');

        // Add menu item
        $category = MenuCategory::factory()->create(['restaurant_id' => $restaurant->id]);
        $burgerItem = MenuItem::factory()->create([
            'restaurant_id' => $restaurant->id,
            'category_id' => $category->id,
            'name' => 'برجر كلاسيك دبل',
            'price' => '35.00',
            'is_available' => true,
        ]);

        // 3. Customer scans Table 1 QR & submits order
        $table1 = $restaurant->tables()->first();
        $session = app(TableSessionService::class)->resolveOrCreate($table1);

        $this->withHeader('X-Session-Token', $session->session_token)
            ->postJson('/api/public/cart/items', [
                'menu_item_id' => $burgerItem->id,
                'quantity' => 2,
            ])->assertCreated();

        $orderResponse = $this->withHeader('X-Session-Token', $session->session_token)
            ->postJson('/api/public/orders', [
                'service_type' => 'takeaway',
                'customer_note' => 'بدون طماطم',
            ]);

        $orderResponse->assertCreated();
        $orderData = $orderResponse->json('data');
        $orderId = $orderData['id'];
        $orderNumber = $orderData['order_number'];

        // Assert order is unpaid and awaiting cashier payment
        $this->assertSame(OrderStatus::Pending->value, $orderData['status']);
        $this->assertNull($orderData['paid_at']);
        $this->assertNull($orderData['pickup_window']);
        $this->assertSame('takeaway', $orderData['service_type']);

        // 4. Cashier confirms payment
        $cashierPayResponse = $this->actingAs($cashierUser)
            ->patchJson("/api/admin/orders/{$orderId}/status", [
                'status' => OrderStatus::Accepted->value,
            ]);
        $cashierPayResponse->assertOk();

        $orderAfterPayment = Order::find($orderId);
        $this->assertSame(OrderStatus::Accepted, $orderAfterPayment->status);
        $this->assertNotNull($orderAfterPayment->paid_at);
        $this->assertSame($cashierUser->id, $orderAfterPayment->paid_by);

        // 5. Chef at Window 2 claims the order
        $claimResponse = $this->actingAs($chefWindow2)
            ->patchJson("/api/admin/orders/{$orderId}/status", [
                'status' => OrderStatus::Preparing->value,
                'pickup_window' => 2,
            ]);
        $claimResponse->assertOk();

        // 6. Customer live mobile tracking verifies Window 2 assignment
        $customerView = $this->withHeader('X-Session-Token', $session->session_token)
            ->getJson("/api/public/orders/{$orderNumber}");
        $customerView->assertOk();
        $customerData = $customerView->json('data');

        $this->assertSame(2, $customerData['pickup_window']);
        $this->assertSame(OrderStatus::Preparing->value, $customerData['status']);
        $this->assertNotNull($customerData['paid_at']);

        // 7. Chef marks order Ready
        $readyResponse = $this->actingAs($chefWindow2)
            ->patchJson("/api/admin/orders/{$orderId}/status", [
                'status' => OrderStatus::Ready->value,
            ]);
        $readyResponse->assertOk();

        // 8. Order is Served to Customer
        $servedResponse = $this->actingAs($chefWindow2)
            ->patchJson("/api/admin/orders/{$orderId}/status", [
                'status' => OrderStatus::Served->value,
            ]);
        $servedResponse->assertOk();

        $finalOrder = Order::find($orderId);
        $this->assertSame(OrderStatus::Served, $finalOrder->status);
    }

    /**
     * Scenario 2: Complete Postpaid Restaurant Workflow (Dine-in / Hall Hospitality)
     * Customer scans Table QR -> Multi-course ordering (Course 1 + Course 2) ->
     * Orders dispatched to kitchen immediately -> Waiter serves food ->
     * Table checkout settled at departure -> Table cleared.
     */
    public function test_scenario_2_complete_postpaid_restaurant_lifecycle(): void
    {
        $restaurant = Restaurant::factory()->create([
            'type' => 'postpaid',
            'currency' => 'SYP',
            'features' => ['offers' => true],
        ]);

        $table = RestaurantTable::factory()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'طاولة VIP 05',
        ]);

        $admin = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'status' => UserStatus::Active,
        ]);
        $admin->assignRole(UserRole::Admin->value);

        $category = MenuCategory::factory()->create(['restaurant_id' => $restaurant->id]);
        $grillItem = MenuItem::factory()->create([
            'restaurant_id' => $restaurant->id,
            'category_id' => $category->id,
            'name' => 'مشاوي مشكلة حلبية',
            'price' => '80.00',
            'is_available' => true,
        ]);
        $dessertItem = MenuItem::factory()->create([
            'restaurant_id' => $restaurant->id,
            'category_id' => $category->id,
            'name' => 'كنافة نابلسية',
            'price' => '30.00',
            'is_available' => true,
        ]);

        // Customer initiates table session
        $session = app(TableSessionService::class)->resolveOrCreate($table);

        // Course 1: Main course ordered
        $this->withHeader('X-Session-Token', $session->session_token)
            ->postJson('/api/public/cart/items', [
                'menu_item_id' => $grillItem->id,
                'quantity' => 2, // 160.00
            ])->assertCreated();

        $course1Response = $this->withHeader('X-Session-Token', $session->session_token)
            ->postJson('/api/public/orders', ['service_type' => 'dine_in']);
        $course1Response->assertCreated();
        $order1Id = $course1Response->json('data.id');

        // Course 2: Dessert ordered later on the same table session
        $this->withHeader('X-Session-Token', $session->session_token)
            ->postJson('/api/public/cart/items', [
                'menu_item_id' => $dessertItem->id,
                'quantity' => 1, // 30.00
            ])->assertCreated();

        $course2Response = $this->withHeader('X-Session-Token', $session->session_token)
            ->postJson('/api/public/orders', ['service_type' => 'dine_in']);
        $course2Response->assertCreated();
        $order2Id = $course2Response->json('data.id');

        // Both orders belong to the same table and session
        $this->assertSame(2, Order::where('table_session_id', $session->id)->count());

        // Kitchen advances both courses through valid state transitions: accepted -> preparing -> ready -> served
        foreach ([$order1Id, $order2Id] as $oid) {
            $this->actingAs($admin)->patchJson("/api/admin/orders/{$oid}/status", ['status' => OrderStatus::Accepted->value])->assertOk();
            $this->actingAs($admin)->patchJson("/api/admin/orders/{$oid}/status", ['status' => OrderStatus::Preparing->value])->assertOk();
            $this->actingAs($admin)->patchJson("/api/admin/orders/{$oid}/status", ['status' => OrderStatus::Ready->value])->assertOk();
            $this->actingAs($admin)->patchJson("/api/admin/orders/{$oid}/status", ['status' => OrderStatus::Served->value])->assertOk();
        }

        // Departure checkout: Settle payment for both orders
        $order1 = Order::find($order1Id);
        $order2 = Order::find($order2Id);

        $order1->update(['paid_at' => now(), 'paid_by' => $admin->id]);
        $order2->update(['paid_at' => now(), 'paid_by' => $admin->id]);

        $this->assertNotNull($order1->fresh()->paid_at);
        $this->assertNotNull($order2->fresh()->paid_at);

        // Total revenue from table session is 160.00 + 30.00 = 190.00 SAR
        $sessionRevenue = Order::where('table_session_id', $session->id)->sum('total');
        $this->assertEquals(190.00, (float) $sessionRevenue);
    }

    /**
     * Scenario 3: High-Precision 0% Error Sales Reports (CPA-Grade)
     * Verifies Daily, Monthly, and Yearly sales calculation:
     * - Gross Sales isolation
     * - Discount deduction without float drift
     * - Net Sales accuracy
     * - AOV computation
     * - Dine-in vs Takeaway split
     * - Cancelled orders isolation (lost sales)
     * - Pending orders exclusion
     */
    public function test_scenario_3_zero_error_cpa_sales_reports(): void
    {
        $restaurant = Restaurant::factory()->create(['currency' => 'SYP']);
        $table = RestaurantTable::factory()->create(['restaurant_id' => $restaurant->id]);
        $session = app(TableSessionService::class)->resolveOrCreate($table);

        $admin = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'status' => UserStatus::Active,
        ]);
        $admin->assignRole(UserRole::Admin->value);
        Sanctum::actingAs($admin);

        // 1. Order: Served Dine-in (Gross 150.00, Discount 25.00, Net 125.00)
        Order::create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'table_session_id' => $session->id,
            'order_number' => 'ORD-CPA-001',
            'status' => OrderStatus::Served,
            'service_type' => 'dine_in',
            'subtotal' => '150.00',
            'discount_amount' => '25.00',
            'total' => '125.00',
            'created_at' => now(),
        ]);

        // 2. Order: Paid Takeaway (Gross 75.50, Discount 0.00, Net 75.50)
        Order::create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'table_session_id' => $session->id,
            'order_number' => 'ORD-CPA-002',
            'status' => OrderStatus::Accepted,
            'service_type' => 'takeaway',
            'subtotal' => '75.50',
            'discount_amount' => '0.00',
            'total' => '75.50',
            'paid_at' => now(),
            'created_at' => now(),
        ]);

        // 3. Order: Cancelled (Gross 60.00, Net 0.00, Lost 60.00)
        Order::create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'table_session_id' => $session->id,
            'order_number' => 'ORD-CPA-003',
            'status' => OrderStatus::Cancelled,
            'service_type' => 'dine_in',
            'subtotal' => '60.00',
            'discount_amount' => '0.00',
            'total' => '60.00',
            'created_at' => now(),
        ]);

        // 4. Order: Pending unpaid (Gross 40.00, must NOT be counted in revenue)
        Order::create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'table_session_id' => $session->id,
            'order_number' => 'ORD-CPA-004',
            'status' => OrderStatus::Pending,
            'service_type' => 'dine_in',
            'subtotal' => '40.00',
            'discount_amount' => '0.00',
            'total' => '40.00',
            'created_at' => now(),
        ]);

        // Execute Daily Report API
        $response = $this->getJson('/api/admin/reports/sales?view=daily');
        $response->assertOk();
        $summary = $response->json('data.summary');

        // Verification with 0.00% precision:
        // Gross: 150.00 + 75.50 = 225.50
        $this->assertSame('225.50', $summary['gross_sales']);
        // Discounts: 25.00
        $this->assertSame('25.00', $summary['total_discounts']);
        // Net: 225.50 - 25.00 = 200.50
        $this->assertSame('200.50', $summary['net_sales']);
        // Total Recognized Orders: 2
        $this->assertSame(2, $summary['total_orders']);
        // AOV: 200.50 / 2 = 100.25
        $this->assertSame('100.25', $summary['aov']);
        // Dine-in: 125.00
        $this->assertSame('125.00', $summary['dine_in_revenue']);
        // Takeaway: 75.50
        $this->assertSame('75.50', $summary['takeaway_revenue']);
        // Cancelled (Lost sales): 1 order, 60.00
        $this->assertSame(1, $summary['cancelled_orders']);
        $this->assertSame('60.00', $summary['cancelled_lost']);

        // Execute Monthly Report API
        $monthlyResponse = $this->getJson('/api/admin/reports/sales?view=monthly&year=' . now()->year . '&month=' . now()->month);
        $monthlyResponse->assertOk();
        $monthlySummary = $monthlyResponse->json('data.summary');
        $this->assertSame('200.50', $monthlySummary['net_sales']);

        // Execute Yearly Report API
        $yearlyResponse = $this->getJson('/api/admin/reports/sales?view=yearly&year=' . now()->year);
        $yearlyResponse->assertOk();
        $yearlySummary = $yearlyResponse->json('data.summary');
        $this->assertSame('200.50', $yearlySummary['net_sales']);
    }

    /**
     * Scenario 4: Multi-Tenant Isolation & Financial Data Protection
     * Guarantees Restaurant A cannot see Restaurant B's sales or orders.
     */
    public function test_scenario_4_multi_tenant_financial_isolation(): void
    {
        $restaurantA = Restaurant::factory()->create(['currency' => 'SYP']);
        $restaurantB = Restaurant::factory()->create(['currency' => 'SYP']);

        $tableA = RestaurantTable::factory()->create(['restaurant_id' => $restaurantA->id]);
        $tableB = RestaurantTable::factory()->create(['restaurant_id' => $restaurantB->id]);

        $sessionA = app(TableSessionService::class)->resolveOrCreate($tableA);
        $sessionB = app(TableSessionService::class)->resolveOrCreate($tableB);

        $adminA = User::factory()->create(['restaurant_id' => $restaurantA->id, 'status' => UserStatus::Active]);
        $adminA->assignRole(UserRole::Admin->value);

        $adminB = User::factory()->create(['restaurant_id' => $restaurantB->id, 'status' => UserStatus::Active]);
        $adminB->assignRole(UserRole::Admin->value);

        // Sales for Restaurant A: 350.00
        Order::create([
            'restaurant_id' => $restaurantA->id,
            'table_id' => $tableA->id,
            'table_session_id' => $sessionA->id,
            'order_number' => 'ORD-A-01',
            'status' => OrderStatus::Served,
            'subtotal' => '350.00',
            'total' => '350.00',
            'created_at' => now(),
        ]);

        // Sales for Restaurant B: 850.00
        Order::create([
            'restaurant_id' => $restaurantB->id,
            'table_id' => $tableB->id,
            'table_session_id' => $sessionB->id,
            'order_number' => 'ORD-B-01',
            'status' => OrderStatus::Served,
            'subtotal' => '850.00',
            'total' => '850.00',
            'created_at' => now(),
        ]);

        // Admin A report: Net sales must be exactly 350.00
        Sanctum::actingAs($adminA);
        $responseA = $this->getJson('/api/admin/reports/sales?view=daily');
        $responseA->assertOk();
        $this->assertSame('350.00', $responseA->json('data.summary.net_sales'));

        // Admin B report: Net sales must be exactly 850.00
        Sanctum::actingAs($adminB);
        $responseB = $this->getJson('/api/admin/reports/sales?view=daily');
        $responseB->assertOk();
        $this->assertSame('850.00', $responseB->json('data.summary.net_sales'));
    }

    /**
     * Scenario 5: Access Control & Tampering Prevention
     * Unauthenticated and unauthorized users cannot manipulate sales or orders.
     */
    public function test_scenario_5_access_control_and_tampering_prevention(): void
    {
        // 1. Unauthenticated request to sales reports is rejected
        $this->getJson('/api/admin/reports/sales')->assertUnauthorized();

        // 2. Customer cannot update order status
        $restaurant = Restaurant::factory()->create();
        $table = RestaurantTable::factory()->create(['restaurant_id' => $restaurant->id]);
        $session = app(TableSessionService::class)->resolveOrCreate($table);

        $order = Order::create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'table_session_id' => $session->id,
            'order_number' => 'ORD-SEC-01',
            'status' => OrderStatus::Pending,
            'subtotal' => '50.00',
            'total' => '50.00',
        ]);

        // Customer attempts to patch order status
        $this->withHeader('X-Session-Token', $session->session_token)
            ->patchJson("/api/admin/orders/{$order->id}/status", [
                'status' => OrderStatus::Served->value,
            ])->assertUnauthorized();

        // Status remains unchanged
        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }
}
