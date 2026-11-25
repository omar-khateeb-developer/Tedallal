<?php

namespace Tests\Feature;

use App\Enums\RestaurantPlan;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\Setting;
use App\Models\TableReservation;
use App\Models\WaitlistEntry;
use App\Services\WaitlistNotificationService;
use App\Support\PhoneNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VipBookingOtpDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;
    private RestaurantTable $table;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::create([
            'name' => 'مطعم بيت الشام',
            'slug' => 'bait-alsham',
            'type' => 'postpaid',
            'plan' => RestaurantPlan::Max,
            'currency' => 'SYP',
            'status' => 'active',
            'phone' => '0933000111',
            'whatsapp_status' => 'connected',
            'features' => [
                'floor_plan' => true,
                'whatsapp_reminders' => true,
            ],
        ]);

        $this->table = RestaurantTable::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'طاولة الصالة 3',
            'capacity' => 4,
            'table_type' => 'dine_in',
            'status' => 'active',
            'public_token' => 'vip-tbl-3-' . uniqid(),
        ]);
    }

    public function test_phone_normalizer_handles_arabic_digits_and_intl_prefixes(): void
    {
        $this->assertSame('963967123569', PhoneNormalizer::toWhatsAppDigits('٠٩٦٧١٢٣٥٦٩'));
        $this->assertSame('963967123569', PhoneNormalizer::toWhatsAppDigits('+963 0967-123-569'));
        $this->assertSame('963967123569', PhoneNormalizer::toWhatsAppDigits('00963967123569'));
        $this->assertSame('963967123569', PhoneNormalizer::toWhatsAppDigits('967123569'));
        $this->assertSame('966551234567', PhoneNormalizer::toWhatsAppDigits('+966 0551234567'));
        $this->assertSame('0967123569', PhoneNormalizer::toStorageFormat('+963 0967123569'));

        $this->assertTrue(PhoneNormalizer::isValidWhatsAppCandidate('٠٩٦٧١٢٣٥٦٩'));
        $this->assertFalse(PhoneNormalizer::isValidWhatsAppCandidate('0911111111'));
        $this->assertFalse(PhoneNormalizer::isValidWhatsAppCandidate('12345'));
    }

    public function test_vip_otp_normalizes_phone_and_dispatches_via_restaurant_whatsapp(): void
    {
        Http::fake([
            '*/api/messages/send' => Http::response([
                'success' => true,
                'message_id' => 'msg-rest-1',
            ], 200),
        ]);

        $res = $this->postJson("/api/public/restaurants/{$this->restaurant->slug}/reservations/hold", [
            'table_id' => $this->table->id,
            'guest_name' => 'أحمد الشامي',
            'guest_phone' => '+٩٦٣ ٠٩٦٧١٢٣٥٦٩',
            'reservation_date' => now()->addDay()->toDateString(),
            'time_slot' => '19:00',
            'guests_count' => 2,
        ]);

        $res->assertOk()
            ->assertJson([
                'success' => true,
                'phone_masked' => '0967****569',
                'sent_via_whatsapp' => true,
                'delivery_channel' => 'restaurant',
            ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/api/messages/send')
                && ($request['restaurant_id'] ?? null) === $this->restaurant->id
                && ($request['phone'] ?? null) === '963967123569'
                && ($request['simulate_typing'] ?? true) === false;
        });
    }

    public function test_vip_otp_auto_heals_stale_db_state_via_live_gateway_status(): void
    {
        // Local DB mistakenly says disconnected, while gateway holds an active connected session
        $this->restaurant->update(['whatsapp_status' => 'disconnected']);

        Http::fake([
            "*/api/session/{$this->restaurant->id}/status" => Http::response([
                'success' => true,
                'status' => 'connected',
                'phone' => '963933999888',
            ], 200),
            '*/api/messages/send' => Http::response([
                'success' => true,
                'message_id' => 'msg-healed-1',
            ], 200),
        ]);

        $res = $this->postJson("/api/public/restaurants/{$this->restaurant->slug}/reservations/hold", [
            'table_id' => $this->table->id,
            'guest_name' => 'زياد الدمشقي',
            'guest_phone' => '0967123569',
            'reservation_date' => now()->addDay()->toDateString(),
            'time_slot' => '20:30',
            'guests_count' => 3,
        ]);

        $res->assertOk()
            ->assertJson([
                'success' => true,
                'sent_via_whatsapp' => true,
                'delivery_channel' => 'restaurant',
            ]);

        $this->assertEquals('connected', $this->restaurant->fresh()->whatsapp_status);
    }

    public function test_vip_otp_automatically_falls_back_to_platform_whatsapp_when_restaurant_disconnected(): void
    {
        $this->restaurant->update(['whatsapp_status' => 'disconnected']);

        Http::fake(function ($request) {
            $url = $request->url();
            if (str_contains($url, "/api/session/{$this->restaurant->id}/status")) {
                return Http::response(['success' => true, 'status' => 'disconnected'], 200);
            }
            if (str_contains($url, '/api/session/platform/status')) {
                return Http::response([
                    'success' => true,
                    'status' => 'connected',
                    'phone' => '963934578117',
                ], 200);
            }
            if (str_contains($url, '/api/messages/send')) {
                $body = $request->data();
                if (($body['session_id'] ?? null) === 'platform') {
                    return Http::response([
                        'success' => true,
                        'message_id' => 'msg-platform-fallback-1',
                    ], 200);
                }
            }

            return Http::response(['success' => false], 400);
        });

        $res = $this->postJson("/api/public/restaurants/{$this->restaurant->slug}/reservations/hold", [
            'table_id' => $this->table->id,
            'guest_name' => 'خالد الحلبي',
            'guest_phone' => '0967123569',
            'reservation_date' => now()->addDay()->toDateString(),
            'time_slot' => '22:00',
            'guests_count' => 4,
        ]);

        $res->assertOk()
            ->assertJson([
                'success' => true,
                'sent_via_whatsapp' => true,
                'delivery_channel' => 'platform',
            ]);

        $this->assertSame('connected', Setting::get('platform_whatsapp_status'));
    }

    public function test_vip_otp_rejects_unregistered_whatsapp_number_and_releases_hold(): void
    {
        Http::fake([
            '*/api/messages/send' => Http::response([
                'success' => false,
                'error_code' => 'not_on_whatsapp',
                'message' => 'رقم الهاتف غير مسجل على واتساب',
            ], 422),
        ]);

        $res = $this->postJson("/api/public/restaurants/{$this->restaurant->slug}/reservations/hold", [
            'table_id' => $this->table->id,
            'guest_name' => 'عمر الخطيب',
            'guest_phone' => '0967123569',
            'reservation_date' => now()->addDay()->toDateString(),
            'time_slot' => '17:30',
            'guests_count' => 2,
        ]);

        $res->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error_code' => 'not_on_whatsapp',
            ]);

        // Crucial: table must NOT remain locked with an undelivered pending hold
        $this->assertSame(0, TableReservation::count());
    }

    public function test_resend_otp_and_arabic_numeral_otp_verification_flow(): void
    {
        Http::fake([
            '*/api/messages/send' => Http::response([
                'success' => true,
                'message_id' => 'msg-ok',
            ], 200),
        ]);

        $holdRes = $this->postJson("/api/public/restaurants/{$this->restaurant->slug}/reservations/hold", [
            'table_id' => $this->table->id,
            'guest_name' => 'سليم العلي',
            'guest_phone' => '0967123569',
            'reservation_date' => now()->addDay()->toDateString(),
            'time_slot' => '16:00',
            'guests_count' => 2,
        ]);
        $holdRes->assertOk();

        $uuid = $holdRes->json('uuid');
        $oldOtp = TableReservation::where('uuid', $uuid)->value('otp_code');

        // Clear cooldown key to test resend endpoint cleanly
        Cache::forget("vip_otp_resend_{$uuid}");

        $resendRes = $this->postJson("/api/public/restaurants/{$this->restaurant->slug}/reservations/resend-otp", [
            'uuid' => $uuid,
        ]);

        $resendRes->assertOk()
            ->assertJson([
                'success' => true,
                'sent_via_whatsapp' => true,
                'delivery_channel' => 'restaurant',
            ]);

        $newOtp = TableReservation::where('uuid', $uuid)->value('otp_code');
        $this->assertNotEmpty($newOtp);
        $this->assertSame(4, strlen($newOtp));

        // Simulate user typing Eastern Arabic digits on mobile keyboard
        $arabicOtp = strtr($newOtp, [
            '0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤',
            '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩',
        ]);

        $verifyRes = $this->postJson("/api/public/restaurants/{$this->restaurant->slug}/reservations/verify-otp", [
            'uuid' => $uuid,
            'otp_code' => $arabicOtp,
        ]);

        $verifyRes->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame('confirmed', TableReservation::where('uuid', $uuid)->value('status'));
        $this->assertNotNull($oldOtp);
    }

    public function test_production_mode_releases_hold_and_returns_503_when_both_whatsapp_channels_disconnected(): void
    {
        app()->detectEnvironment(fn () => 'production');

        $this->restaurant->update(['whatsapp_status' => 'disconnected']);

        Http::fake([
            "*/api/session/{$this->restaurant->id}/status" => Http::response(['status' => 'disconnected'], 200),
            '*/api/session/platform/status' => Http::response(['status' => 'disconnected'], 200),
        ]);

        $res = $this->postJson("/api/public/restaurants/{$this->restaurant->slug}/reservations/hold", [
            'table_id' => $this->table->id,
            'guest_name' => 'رامي الدمشقي',
            'guest_phone' => '0967123569',
            'reservation_date' => now()->addDay()->toDateString(),
            'time_slot' => '14:30',
            'guests_count' => 2,
        ]);

        $res->assertStatus(503)
            ->assertJson([
                'success' => false,
                'whatsapp_unavailable' => true,
                'restaurant_phone' => '0933000111',
            ]);

        // Table hold MUST be deleted when OTP cannot be delivered in production
        $this->assertSame(0, TableReservation::count());

        // Manager urgent alert + ActivityLog MUST be recorded so the restaurant admin knows to connect WhatsApp first
        $alert = Cache::get("restaurant_whatsapp_otp_alert_{$this->restaurant->id}");
        $this->assertIsArray($alert);
        $this->assertStringContainsString('غير مربوط برقم واتساب', $alert['message']);
        $this->assertDatabaseHas('activity_logs', [
            'restaurant_id' => $this->restaurant->id,
            'action' => 'vip_otp_whatsapp_unlinked',
        ]);

        // Webhook session.connected clears the manager alert automatically
        config(['whatsapp.gateway_secret' => 'test-secret']);
        $this->postJson('/api/whatsapp/webhook', [
            'event' => 'session.connected',
            'session_id' => (string) $this->restaurant->id,
            'restaurant_id' => $this->restaurant->id,
            'phone' => '963967000111',
        ], ['X-Gateway-Secret' => 'test-secret'])->assertOk();

        $this->assertNull(Cache::get("restaurant_whatsapp_otp_alert_{$this->restaurant->id}"));
        $this->assertSame('connected', $this->restaurant->fresh()->whatsapp_status);
    }

    public function test_waitlist_notification_falls_back_to_platform_when_restaurant_session_disconnected(): void
    {
        $this->restaurant->update(['whatsapp_status' => 'disconnected']);

        $entry = WaitlistEntry::create([
            'restaurant_id' => $this->restaurant->id,
            'guest_name' => 'طارق',
            'guest_phone' => '٠٩٦٧١٢٣٥٦٩',
            'party_size' => 3,
            'queue_number' => 1,
            'status' => 'waiting',
            'estimated_wait_minutes' => 15,
        ]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), "/api/session/{$this->restaurant->id}/status")) {
                return Http::response(['status' => 'disconnected'], 200);
            }
            if (str_contains($request->url(), '/api/session/platform/status')) {
                return Http::response(['status' => 'connected', 'phone' => '963934578117'], 200);
            }
            if (str_contains($request->url(), '/api/messages/send')) {
                return Http::response(['success' => true, 'message_id' => 'wl-1'], 200);
            }
            return Http::response([], 400);
        });

        $sent = app(WaitlistNotificationService::class)->sendJoinedNotification($entry);
        $this->assertTrue($sent);
    }
}
