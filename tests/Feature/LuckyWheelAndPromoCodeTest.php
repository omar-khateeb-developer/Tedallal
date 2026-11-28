<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderWheelToken;
use App\Models\PromoCode;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\User;
use App\Services\TableSessionService;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LuckyWheelAndPromoCodeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_invoice_generates_wheel_token_and_qr_when_offers_enabled(): void
    {
        $admin = User::where('email', 'admin@bait-alsham.test')->firstOrFail();
        $restaurant = Restaurant::find($admin->restaurant_id);

        // Ensure offers feature is enabled
        $features = $restaurant->features ?? [];
        $features['offers'] = true;
        $restaurant->features = $features;
        $restaurant->save();

        // Create an active master promo code
        PromoCode::create([
            'restaurant_id' => $restaurant->id,
            'code' => 'WELCOME10',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'min_order_value' => 0,
            'is_active' => true,
            'is_single_use' => false,
        ]);

        $table = RestaurantTable::where('restaurant_id', $restaurant->id)->firstOrFail();
        $session = app(TableSessionService::class)->resolveOrCreate($table);

        $order = Order::create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'table_session_id' => $session->id,
            'order_number' => 'ORD-WHEEL-01',
            'status' => 'served',
            'subtotal' => 100,
            'total' => 100,
        ]);

        $response = $this->actingAs($admin)->get("/admin/orders/{$order->id}/print");
        $response->assertOk();
        $response->assertSee('عجلة الحظ');

        // Assert OrderWheelToken created
        $this->assertDatabaseHas('order_wheel_tokens', [
            'order_id' => $order->id,
            'restaurant_id' => $restaurant->id,
            'is_claimed' => false,
        ]);
    }

    public function test_customer_can_view_wheel_page_and_spin_to_win_voucher(): void
    {
        $restaurant = Restaurant::firstOrFail();

        // Master offer
        $masterOffer = PromoCode::create([
            'restaurant_id' => $restaurant->id,
            'code' => 'LUCKY15',
            'discount_type' => 'percentage',
            'discount_value' => 15,
            'min_order_value' => 20,
            'is_active' => true,
            'is_single_use' => false,
        ]);

        $table = RestaurantTable::where('restaurant_id', $restaurant->id)->firstOrFail();
        $session = app(TableSessionService::class)->resolveOrCreate($table);

        $order = Order::create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'table_session_id' => $session->id,
            'order_number' => 'ORD-WHEEL-02',
            'status' => 'served',
            'subtotal' => 50,
            'total' => 50,
        ]);

        $wheelToken = OrderWheelToken::create([
            'restaurant_id' => $restaurant->id,
            'order_id' => $order->id,
            'token' => 'random_token_1234567890abcdef',
            'expires_at' => now()->addDays(14),
        ]);

        // 1. Visit wheel page
        $pageResponse = $this->get("/wheel/{$wheelToken->token}");
        $pageResponse->assertOk();

        // 2. Spin the wheel
        $spinResponse = $this->postJson("/wheel/{$wheelToken->token}/spin");
        $spinResponse->assertOk();
        $spinResponse->assertJson(['success' => true]);

        $voucherCode = $spinResponse->json('voucher.code');
        $this->assertNotEmpty($voucherCode);

        // 3. Verify single-use voucher created in database
        $this->assertDatabaseHas('promo_codes', [
            'restaurant_id' => $restaurant->id,
            'code' => $voucherCode,
            'is_single_use' => true,
            'times_used' => 0,
            'is_active' => true,
        ]);

        // 4. Verify token marked as claimed
        $wheelToken->refresh();
        $this->assertTrue($wheelToken->is_claimed);
        $this->assertNotNull($wheelToken->claimed_at);

        // 5. Spinning again should fail
        $secondSpin = $this->postJson("/wheel/{$wheelToken->token}/spin");
        $secondSpin->assertStatus(422);
    }

    public function test_single_use_voucher_cannot_be_used_before_starts_at_and_deactivates_after_use(): void
    {
        Carbon::setTestNow('2026-10-30 12:00:00');

        $restaurant = Restaurant::firstOrFail();
        $features = $restaurant->features ?? [];
        $features['offers'] = true;
        $restaurant->features = $features;
        $restaurant->save();

        $table = RestaurantTable::where('restaurant_id', $restaurant->id)->firstOrFail();
        $session = app(TableSessionService::class)->resolveOrCreate($table);

        // Create voucher valid from 2 hours later
        $voucher = PromoCode::create([
            'restaurant_id' => $restaurant->id,
            'code' => 'SINGLE-USE-100',
            'discount_type' => 'percentage',
            'discount_value' => 20,
            'min_order_value' => 10,
            'is_active' => true,
            'starts_at' => now()->addHours(2),
            'expires_at' => now()->addDays(14),
            'is_single_use' => true,
            'times_used' => 0,
        ]);

        // Item for cart
        $item = MenuItem::where('restaurant_id', $restaurant->id)->where('is_available', true)->firstOrFail();

        // Add item to cart
        $this->withHeader('X-Session-Token', $session->session_token)
            ->postJson('/api/public/cart/items', [
                'menu_item_id' => $item->id,
                'quantity' => 2,
            ])
            ->assertSuccessful();

        // 1. Try validating promo before starts_at -> Should be invalid (400)
        $promoFail = $this->withHeader('X-Session-Token', $session->session_token)
            ->postJson('/api/public/promo-codes/validate', [
                'code' => 'SINGLE-USE-100',
            ]);
        $promoFail->assertStatus(400);

        // 2. Fast forward time 3 hours into the future
        Carbon::setTestNow('2026-10-30 15:00:00');

        // Validate promo code -> Should succeed now
        $promoSuccess = $this->withHeader('X-Session-Token', $session->session_token)
            ->postJson('/api/public/promo-codes/validate', [
                'code' => 'SINGLE-USE-100',
            ]);
        $promoSuccess->assertOk();

        // 3. Checkout order with promo code
        $orderResponse = $this->withHeader('X-Session-Token', $session->session_token)
            ->postJson('/api/public/orders', [
                'customer_note' => 'خصم العجلة',
                'promo_code' => 'SINGLE-USE-100',
            ]);
        $orderResponse->assertCreated();

        // 4. Verify voucher is now used and deactivated
        $voucher->refresh();
        $this->assertEquals(1, $voucher->times_used);
        $this->assertFalse($voucher->is_active);

        // 5. Try reusing voucher on a new cart/session -> Should fail
        $newSession = app(TableSessionService::class)->resolveOrCreate($table);
        $this->withHeader('X-Session-Token', $newSession->session_token)
            ->postJson('/api/public/cart/items', [
                'menu_item_id' => $item->id,
                'quantity' => 1,
            ])
            ->assertSuccessful();

        $reuseResponse = $this->withHeader('X-Session-Token', $newSession->session_token)
            ->postJson('/api/public/promo-codes/validate', [
                'code' => 'SINGLE-USE-100',
            ]);
        $reuseResponse->assertStatus(400);

        Carbon::setTestNow();
    }

    public function test_admin_can_toggle_promo_code_active_status_without_server_error(): void
    {
        $admin = User::where('email', 'admin@bait-alsham.test')->firstOrFail();
        $restaurant = Restaurant::find($admin->restaurant_id);

        $features = $restaurant->features ?? [];
        $features['offers'] = true;
        $restaurant->features = $features;
        $restaurant->save();

        $promo = PromoCode::where('restaurant_id', $restaurant->id)->firstOrFail();
        $this->assertTrue((bool) $promo->is_active);

        $response = $this->actingAs($admin)->patchJson("/api/admin/promo-codes/{$promo->id}", [
            'code' => $promo->code,
            'discount_type' => $promo->discount_type,
            'discount_value' => $promo->discount_value,
            'is_active' => false,
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $promo->refresh();
        $this->assertFalse((bool) $promo->is_active);
    }

    public function test_wheel_page_expands_slices_to_at_least_six_when_few_coupons_exist(): void
    {
        $admin = User::where('email', 'admin@bait-alsham.test')->firstOrFail();
        $restaurant = Restaurant::find($admin->restaurant_id);

        $table = RestaurantTable::where('restaurant_id', $restaurant->id)->firstOrFail();
        $session = app(TableSessionService::class)->resolveOrCreate($table);

        $order = Order::create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'table_session_id' => $session->id,
            'order_number' => 'ORD-WHEEL-03',
            'status' => 'served',
            'subtotal' => 60,
            'total' => 60,
        ]);

        $wheelToken = OrderWheelToken::create([
            'restaurant_id' => $restaurant->id,
            'order_id' => $order->id,
            'token' => 'random_token_expansion_test',
            'expires_at' => now()->addDays(7),
        ]);

        // Keep exactly 2 active master coupons for this restaurant
        PromoCode::where('restaurant_id', $restaurant->id)->delete();
        PromoCode::create([
            'restaurant_id' => $restaurant->id,
            'code' => 'COUPON1',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'is_active' => true,
            'is_single_use' => false,
        ]);
        PromoCode::create([
            'restaurant_id' => $restaurant->id,
            'code' => 'COUPON2',
            'discount_type' => 'percentage',
            'discount_value' => 20,
            'is_active' => true,
            'is_single_use' => false,
        ]);

        $response = $this->get("/wheel/{$wheelToken->token}");
        $response->assertOk();

        // Check Inertia slices prop has at least 6 slices
        $response->assertInertia(fn ($page) => $page
            ->component('Wheel')
            ->has('slices', 8)
        );
    }

    public function test_super_admin_can_toggle_promo_testing_mode_and_diagnose_coupon(): void
    {
        $superAdmin = User::where('email', 'super@tedallal.test')->firstOrFail();
        $restaurant = Restaurant::firstOrFail();

        $features = $restaurant->features ?? [];
        $features['offers'] = true;
        $restaurant->features = $features;
        $restaurant->save();

        // 1. Toggle promo testing ON
        $toggleResp = $this->actingAs($superAdmin, 'sanctum')->postJson('/api/super-admin/promo-testing/toggle', [
            'enabled' => true,
        ]);
        $toggleResp->assertOk();
        $toggleResp->assertJsonPath('data.testing_mode', true);
        $this->assertTrue(\App\Models\Setting::isPromoTestingModeEnabled());

        // 2. Create coupon starting in 2 hours
        $voucher = PromoCode::create([
            'restaurant_id' => $restaurant->id,
            'code' => 'DIAG-TEST-99',
            'discount_type' => 'percentage',
            'discount_value' => 25,
            'min_order_value' => 50,
            'is_active' => true,
            'starts_at' => now()->addHours(2),
            'is_single_use' => true,
            'times_used' => 0,
        ]);

        // 3. Diagnose coupon
        $diagResp = $this->actingAs($superAdmin, 'sanctum')->postJson('/api/super-admin/promo-testing/diagnose', [
            'code' => 'DIAG-TEST-99',
            'restaurant_id' => $restaurant->id,
            'cart_total' => 100,
        ]);

        $diagResp->assertOk();
        $diagResp->assertJsonPath('data.diagnostics.valid', false);
        $diagResp->assertJsonPath('data.diagnostics.reason_code', 'future_start');
        $diagResp->assertJsonPath('data.diagnostics.can_force_activate', true);

        // 4. Force activate coupon from SuperAdmin
        $activateResp = $this->actingAs($superAdmin, 'sanctum')->postJson('/api/super-admin/promo-testing/force-activate', [
            'code' => 'DIAG-TEST-99',
            'restaurant_id' => $restaurant->id,
        ]);
        $activateResp->assertOk();

        $voucher->refresh();
        $this->assertTrue($voucher->starts_at->isPast());
    }

    public function test_public_checkout_shows_detailed_reason_and_allows_force_activate_in_testing_mode(): void
    {
        $restaurant = Restaurant::firstOrFail();
        $features = $restaurant->features ?? [];
        $features['offers'] = true;
        $restaurant->features = $features;
        $restaurant->save();

        $table = RestaurantTable::where('restaurant_id', $restaurant->id)->firstOrFail();
        $session = app(TableSessionService::class)->resolveOrCreate($table);

        $item = MenuItem::where('restaurant_id', $restaurant->id)->where('is_available', true)->firstOrFail();
        $this->withHeader('X-Session-Token', $session->session_token)
            ->postJson('/api/public/cart/items', [
                'menu_item_id' => $item->id,
                'quantity' => 2,
            ])
            ->assertSuccessful();

        // Create coupon starting in 2 hours
        $voucher = PromoCode::create([
            'restaurant_id' => $restaurant->id,
            'code' => 'FUTURE-WHEEL-1',
            'discount_type' => 'percentage',
            'discount_value' => 15,
            'min_order_value' => 10,
            'is_active' => true,
            'starts_at' => now()->addHours(2),
            'is_single_use' => true,
            'times_used' => 0,
        ]);

        // When testing mode is OFF - Customer still receives informative guidance
        \App\Models\Setting::set('promo_testing_mode', '0');
        $failOff = $this->withHeader('X-Session-Token', $session->session_token)
            ->postJson('/api/public/promo-codes/validate', [
                'code' => 'FUTURE-WHEEL-1',
            ]);
        $failOff->assertStatus(400);
        $this->assertStringContainsString('الكوبون مخصص لزيارتك القادمة', $failOff->json('message'));

        // When testing mode is ON - Debug controls available
        \App\Models\Setting::set('promo_testing_mode', '1');
        $failOn = $this->withHeader('X-Session-Token', $session->session_token)
            ->postJson('/api/public/promo-codes/validate', [
                'code' => 'FUTURE-WHEEL-1',
            ]);
        $failOn->assertStatus(400);
        $this->assertStringContainsString('الكوبون مخصص لزيارتك القادمة', $failOn->json('message'));
        $failOn->assertJsonPath('debug.reason_code', 'future_start');
        $failOn->assertJsonPath('debug.can_force_activate', true);

        // Force activate via public endpoint in testing mode
        $forceResp = $this->withHeader('X-Session-Token', $session->session_token)
            ->postJson('/api/public/promo-codes/force-activate', [
                'code' => 'FUTURE-WHEEL-1',
            ]);
        $forceResp->assertOk();

        // Validate again -> Now succeeds!
        $successResp = $this->withHeader('X-Session-Token', $session->session_token)
            ->postJson('/api/public/promo-codes/validate', [
                'code' => 'FUTURE-WHEEL-1',
            ]);
        $successResp->assertOk();
        $successResp->assertJsonPath('data.code', 'FUTURE-WHEEL-1');
    }

    public function test_restaurant_manager_can_configure_wheel_activation_delay_between_immediate_and_delayed(): void
    {
        Carbon::setTestNow('2026-10-30 12:00:00');

        $admin = User::where('email', 'admin@bait-alsham.test')->firstOrFail();
        $restaurant = Restaurant::findOrFail($admin->restaurant_id);

        $features = $restaurant->features ?? [];
        $features['offers'] = true;
        $features['whatsapp_reminders'] = true;
        $features['wheel_require_whatsapp'] = false;
        $restaurant->update(['features' => $features]);

        PromoCode::where('restaurant_id', $restaurant->id)->update(['is_active' => false]);

        PromoCode::create([
            'restaurant_id' => $restaurant->id,
            'code' => 'WHEEL-DELAY-TEST',
            'discount_type' => 'percentage',
            'discount_value' => 15,
            'min_order_value' => 0,
            'is_active' => true,
            'is_single_use' => false,
        ]);

        $table = RestaurantTable::where('restaurant_id', $restaurant->id)->firstOrFail();
        $session = app(TableSessionService::class)->resolveOrCreate($table);
        $item = MenuItem::where('restaurant_id', $restaurant->id)->where('is_available', true)->firstOrFail();
        $this->withHeader('X-Session-Token', $session->session_token)
            ->postJson('/api/public/cart/items', [
                'menu_item_id' => $item->id,
                'quantity' => 5,
            ])
            ->assertSuccessful();

        $order = Order::create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'table_session_id' => $session->id,
            'order_number' => 'ORD-DELAY-01',
            'status' => 'served',
            'subtotal' => 100,
            'total' => 100,
        ]);

        // 1. Default is 2 hours delay -> spun voucher is not valid immediately in cart
        $this->assertSame(2, $restaurant->wheelActivationDelayHours());

        $delayedToken = OrderWheelToken::create([
            'restaurant_id' => $restaurant->id,
            'order_id' => $order->id,
            'token' => 'wheel_delay_2h_token_123456',
            'expires_at' => now()->addDays(14),
        ]);

        $delayedSpin = $this->postJson("/wheel/{$delayedToken->token}/spin")->assertOk();
        $delayedSpin->assertJsonPath('voucher.is_immediate', false);
        $delayedCode = $delayedSpin->json('voucher.code');

        $this->withHeader('X-Session-Token', $session->session_token)
            ->postJson('/api/public/promo-codes/validate', ['code' => $delayedCode])
            ->assertStatus(400);

        // 2. Manager switches setting to 0 hours (immediate activation)
        $this->actingAs($admin)->postJson('/api/admin/crm-settings', [
            'wheel_require_whatsapp' => false,
            'wheel_reveal_code_after_save' => false,
            'wheel_activation_delay_hours' => 0,
            'loyalty_enabled' => false,
            'loyalty_target_visits' => 5,
            'loyalty_reward_type' => 'percentage',
            'loyalty_reward_value' => 15,
        ])->assertOk()
          ->assertJsonPath('data.wheel_activation_delay_hours', 0);

        $this->assertSame(0, $restaurant->fresh()->wheelActivationDelayHours());

        $secondOrder = Order::create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'table_session_id' => $session->id,
            'order_number' => 'ORD-DELAY-02',
            'status' => 'served',
            'subtotal' => 100,
            'total' => 100,
        ]);

        $immediateToken = OrderWheelToken::create([
            'restaurant_id' => $restaurant->id,
            'order_id' => $secondOrder->id,
            'token' => 'wheel_delay_0h_token_123456',
            'expires_at' => now()->addDays(14),
        ]);

        $immediateSpin = $this->postJson("/wheel/{$immediateToken->token}/spin")->assertOk();
        $immediateSpin->assertJsonPath('voucher.is_immediate', true);
        $immediateSpin->assertJsonPath('voucher.starts_at', null);
        $immediateCode = $immediateSpin->json('voucher.code');

        // Immediately usable in the same session!
        $this->withHeader('X-Session-Token', $session->session_token)
            ->postJson('/api/public/promo-codes/validate', ['code' => $immediateCode])
            ->assertOk()
            ->assertJsonPath('data.code', $immediateCode);

        Carbon::setTestNow();
    }
}
