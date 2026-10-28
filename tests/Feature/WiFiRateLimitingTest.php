<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Services\TableSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class WiFiRateLimitingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clearResolvedInstances();
    }

    public function test_two_different_sessions_on_same_ip_do_not_block_each_other(): void
    {
        $restaurant = Restaurant::factory()->create();
        $tableA = RestaurantTable::factory()->create(['restaurant_id' => $restaurant->id, 'name' => 'Table A']);
        $tableB = RestaurantTable::factory()->create(['restaurant_id' => $restaurant->id, 'name' => 'Table B']);

        $sessionService = app(TableSessionService::class);
        $sessionA = $sessionService->resolveOrCreate($tableA);
        $sessionB = $sessionService->resolveOrCreate($tableB);

        // Shared Wi-Fi IP address
        $sharedIp = '185.120.45.10';

        // Session A sends 15 attempts (exhausting its own 15/min limit)
        for ($i = 0; $i < 15; $i++) {
            $response = $this->withServerVariables(['REMOTE_ADDR' => $sharedIp])
                ->withHeader('X-Session-Token', $sessionA->session_token)
                ->withCookie('tedallal_session', $sessionA->session_token)
                ->postJson('/api/public/orders');

            // Even if the cart is empty (422), rate limiter tracks the request
            $this->assertNotEquals(429, $response->status(), "Attempt $i should not be rate limited");
        }

        // 16th attempt from Session A on same IP should be rate limited (429)
        $blockedResponse = $this->withServerVariables(['REMOTE_ADDR' => $sharedIp])
            ->withHeader('X-Session-Token', $sessionA->session_token)
            ->withCookie('tedallal_session', $sessionA->session_token)
            ->postJson('/api/public/orders');

        $blockedResponse->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'تم تجاوز الحد المسموح من الطلبات لهذه الطاولة، يرجى الانتظار دقيقة والمحاولة مجدداً.');

        // Crucial Wi-Fi check: Session B on the EXACT SAME IP should still be allowed and NOT blocked!
        $sessionBResponse = $this->withServerVariables(['REMOTE_ADDR' => $sharedIp])
            ->withHeader('X-Session-Token', $sessionB->session_token)
            ->withCookie('tedallal_session', $sessionB->session_token)
            ->postJson('/api/public/orders');

        $this->assertNotEquals(429, $sessionBResponse->status());
    }
}
