<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\User;
use App\Services\TableSessionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AiWaiterFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_super_admin_can_toggle_ai_waiter_feature_for_restaurant(): void
    {
        $superAdmin = User::where('email', 'super@tedallal.test')->firstOrFail();
        $restaurant = Restaurant::firstOrFail();

        // Initially disable
        $restaurant->features = ['ai_waiter' => false];
        $restaurant->save();

        $this->assertFalse($restaurant->fresh()->hasAiWaiterFeature());

        // Toggle on via SuperAdmin API
        $response = $this->actingAs($superAdmin)
            ->patchJson("/api/super-admin/restaurants/{$restaurant->id}", [
                'features' => ['ai_waiter' => true],
            ]);

        $response->assertOk();
        $this->assertTrue($restaurant->fresh()->hasAiWaiterFeature());

        // Toggle off via SuperAdmin API
        $response = $this->actingAs($superAdmin)
            ->patchJson("/api/super-admin/restaurants/{$restaurant->id}", [
                'features' => ['ai_waiter' => false],
            ]);

        $response->assertOk();
        $this->assertFalse($restaurant->fresh()->hasAiWaiterFeature());
    }

    public function test_ai_waiter_chat_returns_403_when_feature_is_disabled(): void
    {
        $restaurant = Restaurant::firstOrFail();
        $features = $restaurant->features ?? [];
        $features['ai_waiter'] = false;
        $restaurant->features = $features;
        $restaurant->save();

        $table = RestaurantTable::where('restaurant_id', $restaurant->id)->firstOrFail();
        $session = app(TableSessionService::class)->resolveOrCreate($table);

        $response = $this->withHeaders(['X-Session-Token' => $session->session_token])
            ->postJson('/api/public/ai-waiter/chat', [
                'message' => 'اقترح لي وجبة خفيفة',
            ]);

        $response->assertStatus(403);
        $response->assertJsonFragment([
            'success' => false,
        ]);
    }

    public function test_ai_waiter_status_endpoint_returns_correct_flag(): void
    {
        $restaurant = Restaurant::firstOrFail();
        $table = RestaurantTable::where('restaurant_id', $restaurant->id)->firstOrFail();
        $session = app(TableSessionService::class)->resolveOrCreate($table);

        // Disabled state
        $restaurant->features = ['ai_waiter' => false];
        $restaurant->save();

        $response = $this->withHeaders(['X-Session-Token' => $session->session_token])
            ->getJson('/api/public/ai-waiter/status');

        $response->assertOk();
        $response->assertJsonPath('data.enabled', false);

        // Enabled state
        $restaurant->features = ['ai_waiter' => true];
        $restaurant->save();

        $response = $this->withHeaders(['X-Session-Token' => $session->session_token])
            ->getJson('/api/public/ai-waiter/status');

        $response->assertOk();
        $response->assertJsonPath('data.enabled', true);
    }

    public function test_ai_waiter_chat_works_with_smart_fallback_when_enabled(): void
    {
        $restaurant = Restaurant::firstOrFail();
        $restaurant->features = ['ai_waiter' => true];
        $restaurant->save();

        $table = RestaurantTable::where('restaurant_id', $restaurant->id)->firstOrFail();
        $session = app(TableSessionService::class)->resolveOrCreate($table);

        $response = $this->withHeaders(['X-Session-Token' => $session->session_token])
            ->postJson('/api/public/ai-waiter/chat', [
                'message' => 'أريد وجبة عائلية غنية أو مشاوي مشكلة',
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertNotEmpty($response->json('data.reply'));
        $this->assertIsArray($response->json('data.recommended_items'));
    }

    public function test_ai_waiter_chat_rate_limits_after_exceeding_session_quota(): void
    {
        $restaurant = Restaurant::firstOrFail();
        $restaurant->features = ['ai_waiter' => true];
        $restaurant->save();

        $table = RestaurantTable::where('restaurant_id', $restaurant->id)->firstOrFail();
        $session = app(TableSessionService::class)->resolveOrCreate($table);

        // Simulate 20 usage counts already reached
        $sessionKey = "ai_waiter_count_{$session->id}";
        Cache::put($sessionKey, 20, now()->addHours(1));

        $response = $this->withHeaders(['X-Session-Token' => $session->session_token])
            ->postJson('/api/public/ai-waiter/chat', [
                'message' => 'مرحبا',
            ]);

        $response->assertStatus(429);
        $response->assertJsonFragment([
            'success' => false,
        ]);
    }
}
