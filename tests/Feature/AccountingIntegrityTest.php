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
use App\Models\User;
use App\Services\OrderStatusTransitionService;
use App\Services\TableSessionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountingIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_analytics_endpoint_isolates_items_categories_and_hours_per_tenant(): void
    {
        $restA = Restaurant::factory()->create(['features' => ['analytics' => true]]);
        $restB = Restaurant::factory()->create(['features' => ['analytics' => true]]);

        $tableA = RestaurantTable::factory()->create(['restaurant_id' => $restA->id]);
        $tableB = RestaurantTable::factory()->create(['restaurant_id' => $restB->id]);
        $sessA = app(TableSessionService::class)->resolveOrCreate($tableA);
        $sessB = app(TableSessionService::class)->resolveOrCreate($tableB);

        $catA = MenuCategory::factory()->create(['restaurant_id' => $restA->id, 'name' => 'قسم أ']);
        $catB = MenuCategory::factory()->create(['restaurant_id' => $restB->id, 'name' => 'قسم ب سري']);

        $itemA = MenuItem::factory()->create(['restaurant_id' => $restA->id, 'category_id' => $catA->id, 'name' => 'طبق أ', 'price' => 100]);
        $itemB = MenuItem::factory()->create(['restaurant_id' => $restB->id, 'category_id' => $catB->id, 'name' => 'طبق ب للمنافس', 'price' => 900]);

        $orderA = Order::create([
            'restaurant_id' => $restA->id,
            'table_id' => $tableA->id,
            'table_session_id' => $sessA->id,
            'order_number' => 'ORD-TENANT-A',
            'status' => OrderStatus::Served,
            'subtotal' => '100.00',
            'discount_amount' => '0.00',
            'total' => '100.00',
        ]);
        $orderA->items()->create([
            'menu_item_id' => $itemA->id,
            'item_name_snapshot' => $itemA->name,
            'unit_price' => '100.00',
            'quantity' => 1,
            'subtotal' => '100.00',
        ]);

        $orderB = Order::create([
            'restaurant_id' => $restB->id,
            'table_id' => $tableB->id,
            'table_session_id' => $sessB->id,
            'order_number' => 'ORD-TENANT-B',
            'status' => OrderStatus::Served,
            'subtotal' => '900.00',
            'discount_amount' => '0.00',
            'total' => '900.00',
        ]);
        $orderB->items()->create([
            'menu_item_id' => $itemB->id,
            'item_name_snapshot' => $itemB->name,
            'unit_price' => '900.00',
            'quantity' => 1,
            'subtotal' => '900.00',
        ]);

        $adminA = User::factory()->create(['restaurant_id' => $restA->id, 'status' => UserStatus::Active]);
        $adminA->assignRole(UserRole::Admin->value);
        Sanctum::actingAs($adminA);

        $res = $this->getJson('/api/admin/analytics?period=all');
        $res->assertOk();

        $topNames = collect($res->json('data.top_items'))->pluck('name')->all();
        $catNames = collect($res->json('data.category_breakdown'))->pluck('name')->all();
        $hourlySum = collect($res->json('data.hourly_distribution'))->sum('total_orders');

        $this->assertEquals(100.0, (float) $res->json('data.total_revenue'));
        $this->assertContains('طبق أ', $topNames);
        $this->assertNotContains('طبق ب للمنافس', $topNames);
        $this->assertContains('قسم أ', $catNames);
        $this->assertNotContains('قسم ب سري', $catNames);
        $this->assertSame(1, $hourlySum);
    }

    public function test_dashboard_shift_report_and_sales_report_reconcile_and_exclude_unpaid_pending_or_paid_cancelled_orders(): void
    {
        $restaurant = Restaurant::factory()->create(['type' => 'prepaid', 'currency' => 'SYP']);
        $table = RestaurantTable::factory()->create(['restaurant_id' => $restaurant->id]);
        $session = app(TableSessionService::class)->resolveOrCreate($table);

        $admin = User::factory()->create(['restaurant_id' => $restaurant->id, 'status' => UserStatus::Active]);
        $admin->assignRole(UserRole::Admin->value);

        // 1. Served order with discount: 180 - 27 = 153
        Order::create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'table_session_id' => $session->id,
            'order_number' => 'ORD-REC-01',
            'status' => OrderStatus::Served,
            'paid_at' => now(),
            'subtotal' => '180.00',
            'discount_amount' => '27.00',
            'total' => '153.00',
            'service_type' => 'takeaway',
        ]);

        // 2. Preparing paid order: 50
        Order::create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'table_session_id' => $session->id,
            'order_number' => 'ORD-REC-02',
            'status' => OrderStatus::Preparing,
            'paid_at' => now(),
            'subtotal' => '50.00',
            'discount_amount' => '0.00',
            'total' => '50.00',
            'service_type' => 'dine_in',
        ]);

        // 3. Cancelled order THAT PREVIOUSLY HAD paid_at (must be excluded from Z-report & revenue!)
        Order::create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'table_session_id' => $session->id,
            'order_number' => 'ORD-REC-03',
            'status' => OrderStatus::Cancelled,
            'paid_at' => now(),
            'cancelled_at' => now(),
            'subtotal' => '80.00',
            'discount_amount' => '0.00',
            'total' => '80.00',
        ]);

        // 4. Unpaid pending order (must NOT count as revenue yet)
        Order::create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'table_session_id' => $session->id,
            'order_number' => 'ORD-REC-04',
            'status' => OrderStatus::Pending,
            'paid_at' => null,
            'subtotal' => '200.00',
            'discount_amount' => '0.00',
            'total' => '200.00',
        ]);

        Sanctum::actingAs($admin);

        // Dashboard KPI
        $dashRes = $this->getJson('/api/admin/dashboard/stats');
        $dashRes->assertOk();
        $this->assertSame('203.00', $dashRes->json('data.today_revenue'));

        // Official Sales Report
        $salesRes = $this->getJson('/api/admin/reports/sales?view=daily&year=' . now()->year . '&month=' . now()->month);
        $salesRes->assertOk();
        $this->assertSame('230.00', $salesRes->json('data.summary.gross_sales'));
        $this->assertSame('27.00', $salesRes->json('data.summary.total_discounts'));
        $this->assertSame('203.00', $salesRes->json('data.summary.net_sales'));

        // Accounting Journal Voucher
        $jvRes = $this->getJson('/api/admin/reports/accounting-voucher?date=' . now()->toDateString());
        $jvRes->assertOk();
        $this->assertTrue($jvRes->json('data.is_balanced'));
        $this->assertSame('230.00', $jvRes->json('data.total_debit'));
        $this->assertSame('230.00', $jvRes->json('data.total_credit'));

        // Shift Report (Z-Report)
        $shiftRes = $this->actingAs($admin)->get('/admin/orders/shift-report?date=' . now()->toDateString());
        $shiftRes->assertOk();
        $shiftRes->assertViewHas('totalSales', '203.00');
        $shiftRes->assertViewHas('totalSubtotal', '230.00');
        $shiftRes->assertViewHas('totalDiscount', '27.00');
        $shiftRes->assertViewHas('paidOrdersCount', 2);
        $shiftRes->assertViewHas('cancelledOrdersCount', 1);
    }

    public function test_cancelling_order_reclaims_both_single_use_and_multi_use_promo_codes(): void
    {
        $restaurant = Restaurant::factory()->create(['type' => 'postpaid']);
        $table = RestaurantTable::factory()->create(['restaurant_id' => $restaurant->id]);
        $session = app(TableSessionService::class)->resolveOrCreate($table);
        $admin = User::factory()->create(['restaurant_id' => $restaurant->id, 'status' => UserStatus::Active]);
        $admin->assignRole(UserRole::Admin->value);

        $singlePromo = PromoCode::withoutGlobalScopes()->create([
            'restaurant_id' => $restaurant->id,
            'code' => 'SINGLE-99',
            'source' => 'lucky_wheel',
            'discount_type' => 'percentage',
            'discount_value' => 15,
            'min_order_value' => 0,
            'is_active' => false,
            'is_single_use' => true,
            'times_used' => 1,
        ]);

        $multiPromo = PromoCode::withoutGlobalScopes()->create([
            'restaurant_id' => $restaurant->id,
            'code' => 'MULTI-20',
            'source' => 'admin',
            'discount_type' => 'fixed',
            'discount_value' => 10,
            'min_order_value' => 0,
            'is_active' => true,
            'is_single_use' => false,
            'times_used' => 3,
        ]);

        $order1 = Order::create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'table_session_id' => $session->id,
            'order_number' => 'ORD-CNL-1',
            'status' => OrderStatus::Preparing,
            'subtotal' => '100.00',
            'discount_amount' => '15.00',
            'total' => '85.00',
            'promo_code_id' => $singlePromo->id,
        ]);

        $order2 = Order::create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'table_session_id' => $session->id,
            'order_number' => 'ORD-CNL-2',
            'status' => OrderStatus::Pending,
            'subtotal' => '100.00',
            'discount_amount' => '10.00',
            'total' => '90.00',
            'promo_code_id' => $multiPromo->id,
        ]);

        $service = app(OrderStatusTransitionService::class);
        $service->transition($order1, OrderStatus::Cancelled, $admin);
        $service->transition($order2, OrderStatus::Cancelled, $admin);

        $singleFresh = $singlePromo->fresh();
        $multiFresh = $multiPromo->fresh();

        $this->assertSame(0, $singleFresh->times_used);
        $this->assertTrue($singleFresh->is_active);
        $this->assertSame(2, $multiFresh->times_used);
    }

    public function test_decimal_promo_calculation_preserves_exact_bigdecimal_math_and_resource_exposes_discount(): void
    {
        $restaurant = Restaurant::factory()->create(['status' => 'active', 'features' => ['offers' => true]]);
        $table = RestaurantTable::factory()->create([
            'restaurant_id' => $restaurant->id,
            'table_type' => RestaurantTable::TYPE_TAKEAWAY,
        ]);
        $category = MenuCategory::factory()->create(['restaurant_id' => $restaurant->id]);
        $item = MenuItem::factory()->create([
            'restaurant_id' => $restaurant->id,
            'category_id' => $category->id,
            'price' => '18.00',
            'is_active' => true,
            'is_available' => true,
        ]);

        PromoCode::withoutGlobalScopes()->create([
            'restaurant_id' => $restaurant->id,
            'code' => 'LUCK-DEC15',
            'source' => 'lucky_wheel',
            'discount_type' => 'percentage',
            'discount_value' => 15,
            'min_order_value' => 0,
            'is_active' => true,
            'starts_at' => now()->subHour(),
            'expires_at' => now()->addDays(7),
            'is_single_use' => true,
            'times_used' => 0,
        ]);

        $session = app(TableSessionService::class)->resolveOrCreate($table);

        $this->withHeader('X-Session-Token', $session->session_token)
            ->postJson('/api/public/cart/items', ['menu_item_id' => $item->id, 'quantity' => 1])
            ->assertCreated();

        $orderRes = $this->withHeader('X-Session-Token', $session->session_token)
            ->postJson('/api/public/orders', ['promo_code' => 'LUCK-DEC15']);

        $orderRes->assertCreated()
            ->assertJsonPath('data.subtotal', '18.00')
            ->assertJsonPath('data.discount_amount', '2.70')
            ->assertJsonPath('data.promo_code', 'LUCK-DEC15')
            ->assertJsonPath('data.total', '15.30');

        $admin = User::factory()->create(['restaurant_id' => $restaurant->id, 'status' => UserStatus::Active]);
        $admin->assignRole(UserRole::Admin->value);
        $orderId = $orderRes->json('data.id');

        // مطابقة الفاتورة المطبوعة للأرقام الدقيقة بدلاً من التقريب الخاطئ
        $printRes = $this->actingAs($admin)->get("/admin/orders/{$orderId}/print");
        $printRes->assertOk();
        $printRes->assertSee('LUCK-DEC15');
        $printRes->assertSee('-2.7');
        $printRes->assertSee('15.3');
    }

    public function test_analytics_insights_and_menu_engineering_recognize_prepaid_paid_orders(): void
    {
        $restaurant = Restaurant::factory()->create([
            'type' => 'prepaid',
            'currency' => 'SYP',
            'features' => ['analytics' => true, 'menu_engineering' => true],
        ]);
        $table = RestaurantTable::factory()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'طاولة 5',
        ]);
        $session = app(TableSessionService::class)->resolveOrCreate($table);
        $category = MenuCategory::factory()->create(['restaurant_id' => $restaurant->id, 'name' => 'مشويات']);

        $servedItem = MenuItem::factory()->create([
            'restaurant_id' => $restaurant->id,
            'category_id' => $category->id,
            'name' => 'طبق مقدم',
            'price' => 100,
            'is_active' => true,
            'is_available' => true,
        ]);
        $prepaidItem = MenuItem::factory()->create([
            'restaurant_id' => $restaurant->id,
            'category_id' => $category->id,
            'name' => 'طبق تحضير مدفوع',
            'price' => 50,
            'is_active' => true,
            'is_available' => true,
        ]);
        $cancelledItem = MenuItem::factory()->create([
            'restaurant_id' => $restaurant->id,
            'category_id' => $category->id,
            'name' => 'طبق ملغي مدفوع',
            'price' => 80,
            'is_active' => true,
            'is_available' => true,
        ]);
        $pendingItem = MenuItem::factory()->create([
            'restaurant_id' => $restaurant->id,
            'category_id' => $category->id,
            'name' => 'طبق غير مدفوع',
            'price' => 200,
            'is_active' => true,
            'is_available' => true,
        ]);

        // 1. Served order (100.00)
        $orderServed = Order::create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'table_session_id' => $session->id,
            'order_number' => 'ORD-AN-1',
            'status' => OrderStatus::Served,
            'paid_at' => now(),
            'subtotal' => '100.00',
            'discount_amount' => '0.00',
            'total' => '100.00',
        ]);
        $orderServed->items()->create([
            'menu_item_id' => $servedItem->id,
            'item_name_snapshot' => $servedItem->name,
            'unit_price' => '100.00',
            'quantity' => 1,
            'subtotal' => '100.00',
        ]);

        // 2. Prepaid preparing paid order (50.00) -> MUST be recognized
        $orderPrepaid = Order::create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'table_session_id' => $session->id,
            'order_number' => 'ORD-AN-2',
            'status' => OrderStatus::Preparing,
            'paid_at' => now(),
            'subtotal' => '50.00',
            'discount_amount' => '0.00',
            'total' => '50.00',
        ]);
        $orderPrepaid->items()->create([
            'menu_item_id' => $prepaidItem->id,
            'item_name_snapshot' => $prepaidItem->name,
            'unit_price' => '50.00',
            'quantity' => 1,
            'subtotal' => '50.00',
        ]);

        // 3. Cancelled order with paid_at (80.00) -> MUST be excluded
        $orderCancelled = Order::create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'table_session_id' => $session->id,
            'order_number' => 'ORD-AN-3',
            'status' => OrderStatus::Cancelled,
            'paid_at' => now(),
            'cancelled_at' => now(),
            'subtotal' => '80.00',
            'discount_amount' => '0.00',
            'total' => '80.00',
        ]);
        $orderCancelled->items()->create([
            'menu_item_id' => $cancelledItem->id,
            'item_name_snapshot' => $cancelledItem->name,
            'unit_price' => '80.00',
            'quantity' => 1,
            'subtotal' => '80.00',
        ]);

        // 4. Pending unpaid order (200.00) -> MUST be excluded
        $orderPending = Order::create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'table_session_id' => $session->id,
            'order_number' => 'ORD-AN-4',
            'status' => OrderStatus::Pending,
            'paid_at' => null,
            'subtotal' => '200.00',
            'discount_amount' => '0.00',
            'total' => '200.00',
        ]);
        $orderPending->items()->create([
            'menu_item_id' => $pendingItem->id,
            'item_name_snapshot' => $pendingItem->name,
            'unit_price' => '200.00',
            'quantity' => 1,
            'subtotal' => '200.00',
        ]);

        $admin = User::factory()->create(['restaurant_id' => $restaurant->id, 'status' => UserStatus::Active]);
        $admin->assignRole(UserRole::Admin->value);
        Sanctum::actingAs($admin);

        // Analytics endpoint
        $analyticsRes = $this->getJson('/api/admin/analytics?period=all');
        $analyticsRes->assertOk();
        $this->assertEquals(150.0, (float) $analyticsRes->json('data.total_revenue'));
        $this->assertSame(4, (int) $analyticsRes->json('data.total_orders'));
        $this->assertEquals(75.0, (float) $analyticsRes->json('data.average_order_value'));
        $this->assertEquals(25.0, (float) $analyticsRes->json('data.cancellation_rate'));

        $topNames = collect($analyticsRes->json('data.top_items'))->pluck('name')->all();
        $this->assertContains('طبق مقدم', $topNames);
        $this->assertContains('طبق تحضير مدفوع', $topNames);
        $this->assertNotContains('طبق ملغي مدفوع', $topNames);
        $this->assertNotContains('طبق غير مدفوع', $topNames);
        $this->assertSame(2, collect($analyticsRes->json('data.hourly_distribution'))->sum('total_orders'));
        $this->assertEquals(150.0, (float) $analyticsRes->json('data.table_performance.0.total_revenue'));

        // Menu Engineering endpoint (previously crashed on unimported Auth facade & ignored prepaid paid orders)
        $menuEngRes = $this->getJson('/api/admin/analytics/menu-engineering?period=all');
        $menuEngRes->assertOk()->assertJsonPath('data.locked', false);
        $allAnalyzed = collect([
            ...($menuEngRes->json('data.stars') ?? []),
            ...($menuEngRes->json('data.plowhorses') ?? []),
            ...($menuEngRes->json('data.puzzles') ?? []),
            ...($menuEngRes->json('data.dogs') ?? []),
        ])->keyBy('id');
        $this->assertSame(1, $allAnalyzed->get($servedItem->id)['total_sold']);
        $this->assertSame(1, $allAnalyzed->get($prepaidItem->id)['total_sold']);
        $this->assertSame(0, $allAnalyzed->get($cancelledItem->id)['total_sold']);
        $this->assertSame(0, $allAnalyzed->get($pendingItem->id)['total_sold']);
        $this->assertSame(2, collect($menuEngRes->json('data.heatmap'))->sum('count'));

        // Insights endpoint
        $insightsRes = $this->getJson('/api/admin/insights');
        $insightsRes->assertOk();
        $insightsList = collect($insightsRes->json('data'));
        $slowInsight = $insightsList->firstWhere('title', 'أصناف بطيئة الحركة');
        $this->assertNotNull($slowInsight);
        $this->assertStringContainsString('طبق تحضير مدفوع', $slowInsight['description']);
        $this->assertNotNull($insightsList->firstWhere('title', 'أوقات الذروة المربحة'));
    }
}
