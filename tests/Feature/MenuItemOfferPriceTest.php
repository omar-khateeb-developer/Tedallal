<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\User;
use App\Services\TableSessionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuItemOfferPriceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_can_create_and_update_strikethrough_compare_at_price_and_reject_invalid_values(): void
    {
        $admin = User::role(UserRole::Admin->value)->firstOrFail();
        $category = MenuCategory::where('restaurant_id', $admin->restaurant_id)->firstOrFail();

        // 1. Reject compare_at_price <= price on create
        $this->actingAs($admin)->postJson('/api/admin/menu/items', [
            'category_id' => $category->id,
            'name' => 'برغر عرض غير منطقي',
            'price' => 50000,
            'compare_at_price' => 45000,
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['compare_at_price']);

        // 2. Create item with valid compare_at_price
        $createRes = $this->actingAs($admin)->postJson('/api/admin/menu/items', [
            'category_id' => $category->id,
            'name' => 'شاورما عربي دبل عرض اليوم',
            'price' => 35000,
            'compare_at_price' => 50000,
        ])->assertCreated();

        $createRes->assertJsonPath('data.price', '35000.00')
            ->assertJsonPath('data.compare_at_price', '50000.00')
            ->assertJsonPath('data.discount_percent', 30);

        $itemId = $createRes->json('data.id');

        // 3. Partial PATCH compare_at_price <= existing price is rejected
        $this->actingAs($admin)->patchJson("/api/admin/menu/items/{$itemId}", [
            'compare_at_price' => 30000,
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['compare_at_price']);

        // 4. Raising price above compare_at_price without new compare_at_price clears stale offer price
        $this->actingAs($admin)->patchJson("/api/admin/menu/items/{$itemId}", [
            'price' => 55000,
        ])->assertOk()
          ->assertJsonPath('data.compare_at_price', null)
          ->assertJsonPath('data.discount_percent', null);

        // 5. Re-apply offer and clear explicitly with null
        $this->actingAs($admin)->patchJson("/api/admin/menu/items/{$itemId}", [
            'price' => 35000,
            'compare_at_price' => 50000,
        ])->assertOk()
          ->assertJsonPath('data.discount_percent', 30);

        // 6. Guest order still bills at the actual price (35000), never compare_at_price (50000)
        $restaurant = Restaurant::findOrFail($admin->restaurant_id);
        $table = RestaurantTable::where('restaurant_id', $restaurant->id)->firstOrFail();
        $session = app(TableSessionService::class)->resolveOrCreate($table);

        $this->withHeader('X-Session-Token', $session->session_token)
            ->postJson('/api/public/cart/items', [
                'menu_item_id' => $itemId,
                'quantity' => 2,
            ])->assertSuccessful();

        $orderRes = $this->withHeader('X-Session-Token', $session->session_token)
            ->postJson('/api/public/orders', ['service_type' => 'dine_in'])
            ->assertCreated();

        $this->assertEquals(70000.0, (float) $orderRes->json('data.total'));

        // 7. Clear compare_at_price cleanly
        $this->actingAs($admin)->patchJson("/api/admin/menu/items/{$itemId}", [
            'compare_at_price' => null,
        ])->assertOk()
          ->assertJsonPath('data.compare_at_price', null)
          ->assertJsonPath('data.discount_percent', null);
    }
}
