<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\MenuCategory;
use App\Models\Order;
use App\Models\OrderWheelToken;
use App\Models\PromoCode;
use App\Models\Restaurant;
use App\Models\RestaurantCustomer;
use App\Models\RestaurantTable;
use App\Models\User;
use App\Services\CustomerCrmService;
use App\Services\WhatsAppGatewayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomerCrmAndWhatsAppValidationTest extends TestCase
{
    use RefreshDatabase;

    private CustomerCrmService $crmService;
    private Restaurant $restaurant;
    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crmService = app(CustomerCrmService::class);

        // Setup a restaurant with Max plan (has all features enabled)
        $this->restaurant = Restaurant::create([
            'name' => 'مطعم الياسمين الدمشقي',
            'slug' => 'al-yasmeen',
            'type' => 'postpaid',
            'plan' => 'max',
            'currency' => 'SYP',
            'status' => 'active',
            'features' => [
                'whatsapp_reminders' => true,
                'wheel_require_whatsapp' => true,
                'wheel_reveal_code_after_save' => false,
                'loyalty_enabled' => true,
                'loyalty_target_visits' => 5,
                'loyalty_reward_type' => 'percentage',
                'loyalty_reward_value' => 50,
            ],
        ]);

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->adminUser = User::create([
            'name' => 'مدير المطعم',
            'email' => 'admin@yasmeen.com',
            'password' => bcrypt('password123'),
            'status' => \App\Enums\UserStatus::Active,
            'restaurant_id' => $this->restaurant->id,
        ]);

        $this->adminUser->assignRole($role);
    }

    // =========================================================================
    // 1. Name Normalization & Boundary Validations
    // =========================================================================

    public function test_valid_arabic_and_english_names_pass_validation(): void
    {
        $validNames = [
            'محمد أحمد علي',
            'سارة العلي',
            'John Doe',
            'Alex Smith',
            'عبد الرحمن الكردي',
        ];

        foreach ($validNames as $name) {
            $normalized = $this->crmService->validateAndNormalizeName($name);
            $this->assertNotEmpty($normalized);
            $this->assertEquals(trim(preg_replace('/\s+/u', ' ', $name)), $normalized);
        }
    }

    public function test_name_fails_when_too_short_or_too_long(): void
    {
        // < 3 characters
        $this->expectException(ValidationException::class);
        $this->crmService->validateAndNormalizeName('ab');
    }

    public function test_name_fails_when_exceeding_50_characters(): void
    {
        $longName = str_repeat('محمد ', 15); // > 50 chars
        $this->expectException(ValidationException::class);
        $this->crmService->validateAndNormalizeName($longName);
    }

    public function test_name_fails_when_containing_digits(): void
    {
        $this->expectException(ValidationException::class);
        $this->crmService->validateAndNormalizeName('أحمد123');
    }

    public function test_name_fails_when_containing_symbols(): void
    {
        $this->expectException(ValidationException::class);
        $this->crmService->validateAndNormalizeName('سارة@#');
    }

    public function test_name_fails_when_using_blocked_fake_names(): void
    {
        $blocked = ['test', 'testing', 'asdf', 'admin', 'زبون', 'فلان', 'مجهول', 'ضيف', 'لا يوجد'];

        foreach ($blocked as $fake) {
            try {
                $this->crmService->validateAndNormalizeName($fake);
                $this->fail("Fake name '{$fake}' should have been rejected.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('name', $e->errors());
            }
        }
    }

    public function test_name_fails_when_having_three_or_more_repeated_characters(): void
    {
        $repeated = ['أحمددددد', 'ببببب', 'aaaaa', 'سسسارة'];

        foreach ($repeated as $rep) {
            try {
                $this->crmService->validateAndNormalizeName($rep);
                $this->fail("Repeated name '{$rep}' should have been rejected.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('name', $e->errors());
            }
        }
    }

    // =========================================================================
    // 2. Phone Normalization & Boundary Validations
    // =========================================================================

    public function test_valid_syrian_mobile_numbers_pass_and_normalize_correctly(): void
    {
        $validPhones = [
            '0934567890',
            '0991234567',
            '0944112233',
            '0955998877',
        ];

        foreach ($validPhones as $phone) {
            $normalized = $this->crmService->validateAndNormalizePhone($phone);
            $this->assertEquals($phone, $normalized);

            // Test WhatsApp international conversion
            $wa = $this->crmService->toWhatsAppDigits($phone);
            $this->assertEquals('963' . substr($phone, 1), $wa);
        }
    }

    public function test_arabic_indic_digits_are_converted_to_western_digits(): void
    {
        // 0934567890 in Arabic-Indic numerals
        $arabicIndic = '٠٩٣٤٥٦٧٨٩٠';
        $normalized = $this->crmService->validateAndNormalizePhone($arabicIndic);
        $this->assertEquals('0934567890', $normalized);
    }

    public function test_phone_fails_when_not_starting_with_09(): void
    {
        $invalidPrefixes = ['0812345678', '0712345678', '0512345678', '0112345678'];

        foreach ($invalidPrefixes as $phone) {
            try {
                $this->crmService->validateAndNormalizePhone($phone);
                $this->fail("Phone '{$phone}' not starting with 09 should be rejected.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('phone', $e->errors());
            }
        }
    }

    public function test_phone_fails_when_length_is_not_ten_digits(): void
    {
        $invalidLengths = ['093456789', '093456789012', '09'];

        foreach ($invalidLengths as $phone) {
            try {
                $this->crmService->validateAndNormalizePhone($phone);
                $this->fail("Phone '{$phone}' with wrong length should be rejected.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('phone', $e->errors());
            }
        }
    }

    public function test_phone_fails_when_sequential_or_repeated_fake_numbers(): void
    {
        $fakePhones = ['0912345678', '0987654321', '0911111111', '0999999999'];

        foreach ($fakePhones as $fake) {
            try {
                $this->crmService->validateAndNormalizePhone($fake);
                $this->fail("Fake phone '{$fake}' should be rejected.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('phone', $e->errors());
            }
        }
    }

    // =========================================================================
    // 3. CRM Settings Validation
    // =========================================================================

    public function test_crm_settings_validation_rejects_percentage_over_100(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/crm-settings', [
                'wheel_require_whatsapp' => true,
                'wheel_reveal_code_after_save' => false,
                'loyalty_enabled' => true,
                'loyalty_target_visits' => 5,
                'loyalty_reward_type' => 'percentage',
                'loyalty_reward_value' => 150, // Invalid: > 100%
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'النسبة المئوية للخصم يجب ألا تتجاوز 100%.',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'النسبة المئوية للخصم يجب ألا تتجاوز 100%.',
            ]);
    }

    public function test_crm_settings_validation_rejects_target_visits_out_of_range(): void
    {
        // Target visits must be between 2 and 50
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/crm-settings', [
                'wheel_require_whatsapp' => true,
                'wheel_reveal_code_after_save' => false,
                'loyalty_enabled' => true,
                'loyalty_target_visits' => 1, // Invalid: min is 2
                'loyalty_reward_type' => 'percentage',
                'loyalty_reward_value' => 50,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['loyalty_target_visits']);
    }

    public function test_crm_settings_validation_rejects_free_item_without_valid_menu_item(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/crm-settings', [
                'wheel_require_whatsapp' => true,
                'wheel_reveal_code_after_save' => false,
                'loyalty_enabled' => true,
                'loyalty_target_visits' => 5,
                'loyalty_reward_type' => 'free_item',
                'loyalty_reward_value' => null,
                'loyalty_menu_item_id' => 99999, // Non-existent menuItem
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'يرجى اختيار صنف صالح من قائمة مطعمك للجائزة المجانية.',
            ]);
    }

    // =========================================================================
    // 4. WhatsApp Automation Settings Validation
    // =========================================================================

    public function test_whatsapp_settings_updates_and_validates_successfully(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/whatsapp/settings', [
                'whatsapp_auto_reply_enabled' => true,
                'whatsapp_auto_reply_message' => 'أهلاً بك في مطعمنا! تصفح المنيو من هنا: {menu_url}',
                'whatsapp_expiring_reminders_enabled' => true,
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'auto_reply_enabled' => true,
                    'auto_reply_message' => 'أهلاً بك في مطعمنا! تصفح المنيو من هنا: {menu_url}',
                    'expiring_reminders_enabled' => true,
                ],
            ]);

        $this->restaurant->refresh();
        $this->assertTrue((bool) $this->restaurant->whatsapp_auto_reply_enabled);
        $this->assertEquals('أهلاً بك في مطعمنا! تصفح المنيو من هنا: {menu_url}', $this->restaurant->whatsapp_auto_reply_message);
        $this->assertTrue((bool) $this->restaurant->whatsapp_expiring_reminders_enabled);
    }

    // =========================================================================
    // 5. WhatsApp Test Message Validation
    // =========================================================================

    public function test_whatsapp_send_test_validates_phone_correctly(): void
    {
        // Invalid phone
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/whatsapp/send-test', [
                'phone' => '12345',
            ]);

        $response->assertStatus(422);
    }

    // =========================================================================
    // 6. WhatsApp Webhook Authentication & Security Validation
    // =========================================================================

    public function test_whatsapp_webhook_rejects_unauthorized_requests(): void
    {
        // Without header
        $response = $this->postJson('/api/whatsapp/webhook', [
            'event' => 'session.connected',
            'restaurant_id' => $this->restaurant->id,
            'phone' => '963934567890',
        ]);

        $response->assertStatus(401);

        // With wrong secret
        $response = $this->withHeaders(['X-Gateway-Secret' => 'wrong_secret'])
            ->postJson('/api/whatsapp/webhook', [
                'event' => 'session.connected',
                'restaurant_id' => $this->restaurant->id,
                'phone' => '963934567890',
            ]);

        $response->assertStatus(401);
    }

    public function test_whatsapp_webhook_handles_session_connected_and_disconnected(): void
    {
        $secret = config('whatsapp.gateway_secret', 'tedallal_wa_gateway_2026_secure_key');

        // Test connected event
        $res = $this->withHeaders(['X-Gateway-Secret' => $secret])
            ->postJson('/api/whatsapp/webhook', [
                'event' => 'session.connected',
                'restaurant_id' => $this->restaurant->id,
                'phone' => '963934567890',
            ]);

        $res->assertOk();
        $this->restaurant->refresh();
        $this->assertEquals('connected', $this->restaurant->whatsapp_status);
        $this->assertEquals('963934567890', $this->restaurant->whatsapp_phone);

        // Test disconnected event
        $res = $this->withHeaders(['X-Gateway-Secret' => $secret])
            ->postJson('/api/whatsapp/webhook', [
                'event' => 'session.disconnected',
                'restaurant_id' => $this->restaurant->id,
            ]);

        $res->assertOk();
        $this->restaurant->refresh();
        $this->assertEquals('disconnected', $this->restaurant->whatsapp_status);
        $this->assertNull($this->restaurant->whatsapp_phone);
    }

    public function test_whatsapp_webhook_auto_reply_substitutes_placeholders(): void
    {
        $secret = config('whatsapp.gateway_secret', 'tedallal_wa_gateway_2026_secure_key');

        $this->restaurant->update([
            'whatsapp_auto_reply_enabled' => true,
            'whatsapp_auto_reply_message' => 'مرحباً بك في {restaurant_name}! رابط المنيو: {menu_url}',
        ]);

        $res = $this->withHeaders(['X-Gateway-Secret' => $secret])
            ->postJson('/api/whatsapp/webhook', [
                'event' => 'message.received',
                'restaurant_id' => $this->restaurant->id,
                'from' => '963934567890',
                'text' => 'مرحبا، ما هي أوقات الدوام؟',
            ]);

        $res->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $autoReply = $res->json('auto_reply_text');
        $this->assertStringContainsString('مطعم الياسمين الدمشقي', $autoReply);
        $this->assertStringContainsString('/m/al-yasmeen', $autoReply);
    }

    // =========================================================================
    // 7. Wheel Claim & Auto-Dispatch Integration
    // =========================================================================

    public function test_wheel_claim_with_connected_whatsapp_auto_sends_in_background(): void
    {
        // Mark restaurant whatsapp connected
        $this->restaurant->update([
            'whatsapp_status' => 'connected',
            'whatsapp_phone' => '963934567890',
            'whatsapp_connected_at' => now(),
        ]);

        // Create promo code
        $promo = PromoCode::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'DEMO-WHEEL-10',
            'source' => 'wheel',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'min_order_value' => 0,
            'is_active' => true,
            'is_single_use' => true,
            'times_used' => 0,
            'expires_at' => now()->addDays(14),
        ]);

        $claimRef = Crypt::encryptString((string) $promo->id);

        // Mock gateway response
        Http::fake([
            '*/api/messages/send' => Http::response([
                'success' => true,
                'message_id' => 'MSG_TEST_123',
            ], 200),
        ]);

        $response = $this->postJson('/wheel/demo/claim-whatsapp', [
            'name' => 'طارق الأحمد',
            'phone' => '0934567890',
            'claim_ref' => $claimRef,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'auto_sent' => true,
            ]);

        // Customer created in DB
        $customer = RestaurantCustomer::where('restaurant_id', $this->restaurant->id)
            ->where('phone', '0934567890')
            ->first();

        $this->assertNotNull($customer);
        $this->assertEquals('طارق الأحمد', $customer->name);
        $this->assertEquals(1, $customer->visits_count);

        // Promo linked to customer
        $promo->refresh();
        $this->assertEquals($customer->id, $promo->customer_id);
    }

    public function test_wheel_claim_with_disconnected_whatsapp_falls_back_cleanly(): void
    {
        // Mark restaurant whatsapp disconnected
        $this->restaurant->update([
            'whatsapp_status' => 'disconnected',
            'whatsapp_phone' => null,
        ]);

        $promo = PromoCode::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'DEMO-FALLBACK',
            'source' => 'wheel',
            'discount_type' => 'percentage',
            'discount_value' => 15,
            'min_order_value' => 0,
            'is_active' => true,
            'is_single_use' => true,
            'times_used' => 0,
            'expires_at' => now()->addDays(14),
        ]);

        $claimRef = Crypt::encryptString((string) $promo->id);

        $response = $this->postJson('/wheel/demo/claim-whatsapp', [
            'name' => 'ماهر الشامي',
            'phone' => '0991234567',
            'claim_ref' => $claimRef,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'auto_sent' => false,
            ]);

        $this->assertNotEmpty($response->json('whatsapp_url'));
    }

    public function test_customer_returning_visits_count_increments_and_does_not_duplicate(): void
    {
        // First visit
        $promo1 = PromoCode::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'WHEEL-VISIT-1',
            'source' => 'wheel',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'min_order_value' => 0,
            'is_active' => true,
            'is_single_use' => true,
            'times_used' => 0,
            'expires_at' => now()->addDays(14),
        ]);

        $this->crmService->recordWheelCustomerClaim(
            $this->restaurant,
            'سامر النجار',
            '0944556677',
            $promo1,
            null,
            'خصم 10%'
        );

        $customer = RestaurantCustomer::where('restaurant_id', $this->restaurant->id)
            ->where('phone', '0944556677')
            ->first();

        $this->assertEquals(1, $customer->visits_count);

        // Second visit with same phone
        $promo2 = PromoCode::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'WHEEL-VISIT-2',
            'source' => 'wheel',
            'discount_type' => 'percentage',
            'discount_value' => 20,
            'min_order_value' => 0,
            'is_active' => true,
            'is_single_use' => true,
            'times_used' => 0,
            'expires_at' => now()->addDays(14),
        ]);

        $res2 = $this->crmService->recordWheelCustomerClaim(
            $this->restaurant,
            'سامر النجار',
            '0944556677',
            $promo2,
            null,
            'خصم 20%'
        );

        $customer->refresh();
        $this->assertEquals(2, $customer->visits_count);

        // Total customer records in DB must remain 1 (no duplicate creation)
        $this->assertEquals(1, RestaurantCustomer::where('restaurant_id', $this->restaurant->id)->where('phone', '0944556677')->count());

        // Promo 2 correctly linked
        $this->assertEquals($customer->id, $promo2->fresh()->customer_id);

        // Message should mention visit #2 and remaining visits for grand prize
        $this->assertStringContainsString('الزيارة رقم (2)', $res2['whatsapp_message']);
    }
}
