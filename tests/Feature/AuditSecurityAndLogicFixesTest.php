<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Events\OrderStatusChanged;
use App\Models\ConsultationLead;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderWheelToken;
use App\Models\PromoCode;
use App\Models\Restaurant;
use App\Models\RestaurantCustomer;
use App\Models\RestaurantTable;
use App\Models\Setting;
use App\Models\TableReservation;
use App\Models\TableServiceCall;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Services\TableSessionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditSecurityAndLogicFixesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_super_admin_can_provision_consultation_lead_without_sql_column_crash(): void
    {
        Http::fake([
            '*/api/messages/send' => Http::response(['success' => true], 200),
        ]);

        $superAdmin = User::where('email', 'super@tedallal.test')->firstOrFail();
        $lead = ConsultationLead::create([
            'name' => 'أحمد الحمصي',
            'restaurant_name' => 'مطعم الريف الدمشقي',
            'phone' => '0933445566',
            'city' => 'دمشق',
            'service_type' => 'صالة وطاولات',
            'payment_model' => 'دفع لاحق',
            'kitchen_setup' => 'شاشة واحدة',
            'desired_features' => ['منيو QR'],
            'status' => 'new',
        ]);

        Sanctum::actingAs($superAdmin);

        $res = $this->postJson("/api/super-admin/leads/{$lead->id}/provision");

        $res->assertOk()->assertJsonPath('success', true);
        $restaurantId = $res->json('data.restaurant.id');
        $this->assertNotNull($restaurantId);

        $this->assertSame(2, MenuCategory::withoutGlobalScopes()->where('restaurant_id', $restaurantId)->count());
        $this->assertSame(3, MenuItem::withoutGlobalScopes()->where('restaurant_id', $restaurantId)->count());
        $this->assertSame('closed_won', $lead->fresh()->status);
    }

    public function test_public_endpoints_do_not_leak_session_token_or_allow_order_session_hijacking(): void
    {
        $restaurant = Restaurant::factory()->create(['status' => 'active']);
        $takeaway = RestaurantTable::factory()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'الطلب السفري المباشر',
            'table_type' => RestaurantTable::TYPE_TAKEAWAY,
            'status' => 'active',
        ]);
        $category = MenuCategory::factory()->create(['restaurant_id' => $restaurant->id]);
        $item = MenuItem::factory()->create([
            'restaurant_id' => $restaurant->id,
            'category_id' => $category->id,
            'is_active' => true,
            'is_available' => true,
            'price' => '100.00',
        ]);

        // Victim A opens takeaway menu & creates order
        $visitA = $this->get("/t/{$takeaway->public_token}");
        $visitA->assertOk();
        $sessionA = $visitA->viewData('page')['props']['sessionToken'];

        $this->withHeader('X-Session-Token', $sessionA)
            ->postJson('/api/public/cart/items', ['menu_item_id' => $item->id, 'quantity' => 1])
            ->assertCreated();

        $orderRes = $this->withHeader('X-Session-Token', $sessionA)
            ->postJson('/api/public/orders', ['service_type' => 'takeaway'])
            ->assertCreated();
        $orderNumber = $orderRes->json('data.order_number');

        // 1. Public table lookup MUST NEVER leak active_session / session_token / current_guest_name
        $tableRes = $this->getJson("/api/public/tables/{$takeaway->public_token}");
        $tableRes->assertOk()
            ->assertJsonPath('data.active_session', null)
            ->assertJsonPath('data.active_order', null)
            ->assertJsonPath('data.current_guest_name', null);

        // 2. Attacker without Victim A's cookie (or with Session B) MUST receive 404
        $visitB = $this->get("/t/{$takeaway->public_token}");
        $sessionB = $visitB->viewData('page')['props']['sessionToken'];

        $this->withCookie('tedallal_session', $sessionB)
            ->get("/t/{$takeaway->public_token}/orders/{$orderNumber}")
            ->assertNotFound();

        $this->get("/t/{$takeaway->public_token}/orders/{$orderNumber}")
            ->assertNotFound();

        // 3. Victim A with matching cookie opens order with wheelToken & broadcastChannel
        $ownerRes = $this->withCookie('tedallal_session', $sessionA)
            ->get("/t/{$takeaway->public_token}/orders/{$orderNumber}");
        $ownerRes->assertOk();
        $ownerProps = $ownerRes->viewData('page')['props'];
        $this->assertNotEmpty($ownerProps['wheelToken']);
        $this->assertSame($sessionA, $ownerProps['sessionToken']);
        $this->assertFalse($ownerProps['isReadOnly']);
        $this->assertNotSame("order.{$orderNumber}", $ownerProps['broadcastChannel']);

        // 4. Read-only signed viewer URL opens cleanly for another session WITHOUT wheelToken / sessionToken / victim cookie
        app('cookie')->flushQueuedCookies();
        $readOnlyRes = $this->withCookie('tedallal_session', $sessionB)->get($ownerProps['shareUrl']);
        $readOnlyRes->assertOk();
        $readOnlyProps = $readOnlyRes->viewData('page')['props'];
        $this->assertNull($readOnlyProps['wheelToken']);
        $this->assertNull($readOnlyProps['sessionToken']);
        $this->assertTrue($readOnlyProps['isReadOnly']);
        $readOnlyRes->assertCookieMissing('tedallal_session');

        // 5. Broadcast channel uses unguessable hashed key instead of sequential order_number
        $orderModel = Order::withoutGlobalScopes()->where('order_number', $orderNumber)->firstOrFail();
        $event = new OrderStatusChanged($orderModel, OrderStatus::Pending, OrderStatus::Preparing);
        $channels = collect($event->broadcastOn())->map(fn ($ch) => $ch->name)->all();
        $this->assertNotContains("order.{$orderNumber}", $channels);
        $this->assertContains($orderModel->broadcastChannelKey(), $channels);

        // 6. Admin table lookup also omits raw session_token
        $admin = User::factory()->create(['restaurant_id' => $restaurant->id, 'status' => UserStatus::Active]);
        $admin->assignRole(UserRole::Admin->value);
        $dineIn = RestaurantTable::factory()->create(['restaurant_id' => $restaurant->id, 'status' => 'active']);
        app(TableSessionService::class)->resolveOrCreate($dineIn);

        $adminTablesRes = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/tables')->assertOk();
        $dineInData = collect($adminTablesRes->json('data'))->firstWhere('id', $dineIn->id);
        $this->assertNotNull($dineInData['active_session']);
        $this->assertArrayNotHasKey('session_token', $dineInData['active_session']);
    }

    public function test_gate_before_does_not_bypass_policies_for_restaurant_admins(): void
    {
        $restA = Restaurant::factory()->create();
        $restB = Restaurant::factory()->create();

        $adminA = User::factory()->create(['restaurant_id' => $restA->id, 'status' => UserStatus::Active]);
        $adminA->assignRole(UserRole::Admin->value);

        $tableA = RestaurantTable::factory()->create(['restaurant_id' => $restA->id]);
        $tableB = RestaurantTable::factory()->create(['restaurant_id' => $restB->id]);

        $this->assertTrue(Gate::forUser($adminA)->allows('update', $tableA));
        $this->assertFalse(Gate::forUser($adminA)->allows('update', $tableB));
    }

    public function test_any_super_admin_can_toggle_and_bypass_maintenance_with_http_503(): void
    {
        $customSuper = User::factory()->create([
            'email' => 'omar@tedallal.tech',
            'restaurant_id' => null,
            'status' => UserStatus::Active,
        ]);
        $customSuper->assignRole(UserRole::SuperAdmin->value);

        $this->assertTrue($customSuper->canToggleMaintenance());
        $this->assertTrue($customSuper->canBypassMaintenance());

        Sanctum::actingAs($customSuper);
        $this->postJson('/api/super-admin/maintenance/toggle', ['enabled' => true])->assertOk();

        try {
            // Public visitor gets 503 during maintenance
            $this->app['auth']->forgetGuards();
            $this->get('/')->assertStatus(503);

            // Non-.test super_admin bypasses maintenance cleanly
            $this->actingAs($customSuper)->get('/')->assertOk();
        } finally {
            Setting::set('maintenance_mode', '0');
        }
    }

    public function test_fast_registration_provisions_restaurant_and_avoids_403(): void
    {
        $res = $this->post('/register', [
            'restaurant_name' => 'مقهى الأندلس',
            'phone' => '0944556677',
            'currency' => 'SYP',
            'type' => 'postpaid',
            'name' => 'رامي الشامي',
            'email' => 'rami@andalus.test',
            'password' => 'SecretPass123!',
            'password_confirmation' => 'SecretPass123!',
        ]);

        $res->assertRedirect(route('dashboard', absolute: false));
        $user = User::where('email', 'rami@andalus.test')->firstOrFail();
        $this->assertNotNull($user->restaurant_id);
        $this->assertTrue($user->isAdmin());
        $this->assertDatabaseHas('restaurants', ['id' => $user->restaurant_id, 'name' => 'مقهى الأندلس']);
        $this->assertDatabaseHas('restaurant_tables', ['restaurant_id' => $user->restaurant_id, 'name' => 'طاولة 1']);

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('admin.dashboard', absolute: false));
    }

    public function test_force_activate_blocks_production_diners_and_already_used_vouchers(): void
    {
        $restaurant = Restaurant::factory()->create(['status' => 'active']);
        $table = RestaurantTable::factory()->create(['restaurant_id' => $restaurant->id, 'status' => 'active']);
        $session = app(TableSessionService::class)->resolveOrCreate($table);

        Setting::set('promo_testing_mode', '1');

        $usedVoucher = PromoCode::create([
            'restaurant_id' => $restaurant->id,
            'code' => 'USED-WHEEL-1',
            'discount_type' => 'percentage',
            'discount_value' => 15,
            'is_active' => false,
            'is_single_use' => true,
            'times_used' => 1,
        ]);

        // Even in testing mode, already consumed single-use voucher cannot be force-activated
        $this->withHeader('X-Session-Token', $session->session_token)
            ->postJson('/api/public/promo-codes/force-activate', ['code' => $usedVoucher->code])
            ->assertStatus(422);

        // In production env, unauthenticated diner gets 403 even if promo_testing_mode=1
        $futureVoucher = PromoCode::create([
            'restaurant_id' => $restaurant->id,
            'code' => 'PROD-FUTURE-1',
            'discount_type' => 'percentage',
            'discount_value' => 15,
            'is_active' => false,
            'starts_at' => now()->addHour(),
            'is_single_use' => false,
            'times_used' => 0,
        ]);

        $originalEnv = app()->environment();
        app()->detectEnvironment(fn () => 'production');
        try {
            $this->withHeader('X-Session-Token', $session->session_token)
                ->postJson('/api/public/promo-codes/force-activate', ['code' => $futureVoucher->code])
                ->assertStatus(403);
        } finally {
            app()->detectEnvironment(fn () => $originalEnv);
            Setting::set('promo_testing_mode', '0');
        }
    }

    public function test_wheel_hides_debug_trace_and_ignores_get_reset_param(): void
    {
        config(['app.debug' => false]);

        $debugRes = $this->get('/wheel/nonexistent-wheel-token?debug=1');
        $debugRes->assertNotFound()
            ->assertDontSee('OrderWheelToken')
            ->assertDontSee('WheelController.php');

        $restaurant = Restaurant::factory()->create();
        $admin = User::factory()->create(['restaurant_id' => $restaurant->id, 'status' => UserStatus::Active]);
        $admin->assignRole(UserRole::Admin->value);
        $table = RestaurantTable::factory()->create(['restaurant_id' => $restaurant->id]);
        $session = app(TableSessionService::class)->resolveOrCreate($table);
        $order = Order::create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'table_session_id' => $session->id,
            'order_number' => 'ORD-RST-01',
            'status' => OrderStatus::Served,
            'subtotal' => 50,
            'total' => 50,
        ]);
        $wheelToken = OrderWheelToken::create([
            'restaurant_id' => $restaurant->id,
            'order_id' => $order->id,
            'token' => 'wheel-reset-guard-token-01',
            'is_claimed' => true,
            'claimed_at' => now(),
            'expires_at' => now()->addDays(7),
        ]);

        $this->actingAs($admin)->get("/wheel/{$wheelToken->token}?reset=1")->assertOk();
        $this->assertTrue((bool) $wheelToken->fresh()->is_claimed);
    }

    public function test_waitlist_phone_validation_occupied_guard_and_full_reset_test_data(): void
    {
        $restaurant = Restaurant::factory()->create(['status' => 'active']);
        $admin = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'status' => UserStatus::Active,
            'password' => \Illuminate\Support\Facades\Hash::make('secret-pass-123'),
        ]);
        $admin->assignRole(UserRole::Admin->value);

        // Public waitlist rejects invalid short phone
        $this->postJson("/api/public/restaurants/{$restaurant->slug}/waitlist/join", [
            'guest_name' => 'أحمد',
            'guest_phone' => '12345',
            'party_size' => 2,
        ])->assertStatus(422);

        // Public waitlist normalizes and accepts valid Syrian phone
        $this->postJson("/api/public/restaurants/{$restaurant->slug}/waitlist/join", [
            'guest_name' => 'أحمد الصالح',
            'guest_phone' => '+963 933 223 344',
            'party_size' => 2,
        ])->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('waitlist_entries', [
            'restaurant_id' => $restaurant->id,
            'guest_phone' => '0933223344',
        ]);

        $table = RestaurantTable::factory()->create([
            'restaurant_id' => $restaurant->id,
            'table_type' => RestaurantTable::TYPE_DINE_IN,
            'operational_stage' => 'seated',
            'current_guest_name' => 'ضيوف سابقون',
        ]);
        app(TableSessionService::class)->resolveOrCreate($table);

        $entry = WaitlistEntry::create([
            'restaurant_id' => $restaurant->id,
            'guest_name' => 'سليم',
            'guest_phone' => '0933123456',
            'party_size' => 3,
            'queue_number' => 2,
            'status' => 'waiting',
        ]);

        Sanctum::actingAs($admin);

        // Cannot seat guest onto table that has an active session
        $this->patchJson("/api/admin/waitlist/{$entry->id}/seat", [
            'table_id' => $table->id,
        ])->assertStatus(422);

        // Create service call and reservation then run reset-test-data
        TableServiceCall::create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'type' => 'waiter_call',
            'status' => 'pending',
        ]);
        TableReservation::create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'guest_name' => 'ضيف VIP',
            'guest_phone' => '0944112233',
            'reservation_date' => now()->toDateString(),
            'time_slot' => '20:00',
            'guests_count' => 4,
            'status' => 'confirmed',
        ]);

        $this->postJson('/api/admin/restaurant/reset-test-data', [
            'password' => 'secret-pass-123',
            'confirmation' => 'تصفير بيانات التجارب',
        ])->assertOk();

        $this->assertSame(0, TableServiceCall::withoutGlobalScopes()->where('restaurant_id', $restaurant->id)->count());
        $this->assertSame(0, TableReservation::withoutGlobalScopes()->where('restaurant_id', $restaurant->id)->count());
        $freshTable = $table->fresh();
        $this->assertSame('available', $freshTable->operational_stage);
        $this->assertNull($freshTable->current_guest_name);
    }

    public function test_sales_report_limits_custom_date_range_and_crm_paginates(): void
    {
        $restaurant = Restaurant::factory()->create([
            'status' => 'active',
            'plan' => 'max',
            'features' => ['whatsapp_reminders' => true],
        ]);
        $admin = User::factory()->create(['restaurant_id' => $restaurant->id, 'status' => UserStatus::Active]);
        $admin->assignRole(UserRole::Admin->value);

        for ($i = 1; $i <= 12; $i++) {
            RestaurantCustomer::create([
                'restaurant_id' => $restaurant->id,
                'name' => "عميل {$i}",
                'phone' => '09330000' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'visits_count' => 1,
            ]);
        }

        Sanctum::actingAs($admin);

        // Custom range > 366 days rejected with 422
        $this->getJson('/api/admin/reports/sales?view=custom&from_date=2023-01-01&to_date=2026-01-01')
            ->assertStatus(422);

        // Customer CRM paginates cleanly with per_page=10
        $crmRes = $this->getJson('/api/admin/customers?per_page=10&page=1')->assertOk();
        $this->assertCount(10, $crmRes->json('data.customers'));
        $this->assertSame(12, $crmRes->json('data.pagination.total'));
        $this->assertSame(2, $crmRes->json('data.pagination.last_page'));
    }

    public function test_supervisor_can_login_and_access_permitted_admin_pages_and_inertia_403_renders_vue_component(): void
    {
        $supervisor = User::where('email', 'supervisor@jamra.test')->firstOrFail();

        // 1. Login flow redirects supervisor cleanly to /dashboard -> /admin/dashboard (no 403!)
        $loginRes = $this->post('/login', [
            'email' => $supervisor->email,
            'password' => 'password',
        ]);
        $loginRes->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertRedirect('/admin/dashboard');

        // 2. Supervisor can open Dashboard, Reports, Offers & backing APIs
        $dashRes = $this->actingAs($supervisor)->get('/admin/dashboard')->assertOk();
        $inertiaVersion = (string) ($dashRes->viewData('page')['version'] ?? '');
        $this->actingAs($supervisor)->get('/admin/reports')->assertOk();
        $this->actingAs($supervisor)->get('/admin/offers')->assertOk();

        $this->actingAs($supervisor, 'sanctum')->getJson('/api/admin/dashboard/stats')->assertOk();
        $this->actingAs($supervisor, 'sanctum')->getJson('/api/admin/reports/sales')->assertOk();
        $this->actingAs($supervisor, 'sanctum')->getJson('/api/admin/promo-codes')->assertOk();

        // 3. Supervisor still gets 403 on admin-only routes (Settings, Users, Analytics, Customers)
        $inertia403 = $this->actingAs($supervisor)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => $inertiaVersion,
            ])
            ->get('/admin/settings');

        $inertia403->assertForbidden();
        $inertia403->assertHeader('X-Inertia', 'true');
        $inertia403->assertJsonPath('component', 'Errors/403');
        $inertia403->assertJsonPath('props.message', 'عذراً، هذه الصلاحية خاصة بمدير المطعم (admin) فقط.');

        $customers403 = $this->actingAs($supervisor)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => $inertiaVersion,
            ])
            ->get('/admin/customers?connect_whatsapp=1');
        $customers403->assertForbidden();
        $customers403->assertJsonPath('component', 'Errors/403');
        $customers403->assertJsonPath('props.message', 'عذراً، هذه الصلاحية خاصة بمدير المطعم (admin) فقط.');

        $this->actingAs($supervisor)->get('/admin/users')->assertForbidden();
        $this->actingAs($supervisor, 'sanctum')->getJson('/api/admin/users')->assertForbidden();
        $this->actingAs($supervisor, 'sanctum')
            ->getJson('/api/admin/customers')
            ->assertForbidden()
            ->assertJsonPath('message', 'عذراً، هذه الصلاحية خاصة بمدير المطعم (admin) فقط.');
        $this->actingAs($supervisor, 'sanctum')
            ->getJson('/api/admin/whatsapp/status')
            ->assertForbidden()
            ->assertJsonPath('message', 'عذراً، هذه الصلاحية خاصة بمدير المطعم (admin) فقط.');
    }
}
