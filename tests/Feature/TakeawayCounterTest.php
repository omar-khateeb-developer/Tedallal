<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TakeawayCounterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_two_walk_in_customers_scanning_same_takeaway_counter_get_isolated_sessions(): void
    {
        $restaurant = Restaurant::factory()->create(['status' => 'active']);
        $counter = RestaurantTable::factory()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'كاونتر السفري',
            'status' => 'active',
            'table_type' => RestaurantTable::TYPE_TAKEAWAY,
        ]);
        $category = MenuCategory::factory()->create(['restaurant_id' => $restaurant->id]);
        $item = MenuItem::factory()->create([
            'restaurant_id' => $restaurant->id,
            'category_id' => $category->id,
            'is_active' => true,
            'is_available' => true,
        ]);

        // الزبون الأول يمسح باركود نقطة السفري
        $visitA = $this->get("/t/{$counter->public_token}");
        $visitA->assertOk();
        $tokenA = $visitA->viewData('page')['props']['sessionToken'];
        $this->assertNotEmpty($tokenA);

        // الزبون الثاني يمسح نفس الباركود دون كوكي
        $visitB = $this->get("/t/{$counter->public_token}");
        $visitB->assertOk();
        $tokenB = $visitB->viewData('page')['props']['sessionToken'];
        $this->assertNotEmpty($tokenB);

        $this->assertNotSame($tokenA, $tokenB);

        // الزبون الأول يحدّث الصفحة بكوكيه فتُستعاد نفس جلسته
        $visitAAgain = $this->withCookie('tedallal_session', $tokenA)
            ->get("/t/{$counter->public_token}");
        $visitAAgain->assertOk();
        $this->assertSame($tokenA, $visitAAgain->viewData('page')['props']['sessionToken']);

        // الزبون الأول يضيف صنفاً إلى سلته — الزبون الثاني لا يراه إطلاقاً
        $this->withHeader('X-Session-Token', $tokenA)
            ->postJson('/api/public/cart/items', [
                'menu_item_id' => $item->id,
                'quantity' => 2,
            ])
            ->assertCreated();

        $this->withHeader('X-Session-Token', $tokenB)
            ->getJson('/api/public/cart')
            ->assertOk()
            ->assertJsonPath('data.count', 0);
    }

    public function test_dine_in_table_still_shares_session_between_guests(): void
    {
        $restaurant = Restaurant::factory()->create(['status' => 'active']);
        $table = RestaurantTable::factory()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'طاولة صالة 5',
            'status' => 'active',
            'table_type' => RestaurantTable::TYPE_DINE_IN,
        ]);

        $visit1 = $this->get("/t/{$table->public_token}");
        $visit2 = $this->get("/t/{$table->public_token}");

        $token1 = $visit1->viewData('page')['props']['sessionToken'];
        $token2 = $visit2->viewData('page')['props']['sessionToken'];

        $this->assertSame($token1, $token2);
    }

    public function test_takeaway_counter_forces_takeaway_service_type_and_blocks_waiter_or_bill_calls(): void
    {
        $restaurant = Restaurant::factory()->create(['status' => 'active']);
        $counter = RestaurantTable::factory()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'شباك الاستلام',
            'status' => 'active',
            'table_type' => RestaurantTable::TYPE_TAKEAWAY,
            'operational_stage' => 'available',
        ]);
        $category = MenuCategory::factory()->create(['restaurant_id' => $restaurant->id]);
        $item = MenuItem::factory()->create([
            'restaurant_id' => $restaurant->id,
            'category_id' => $category->id,
            'is_active' => true,
            'is_available' => true,
        ]);

        $visit = $this->get("/t/{$counter->public_token}");
        $sessionToken = $visit->viewData('page')['props']['sessionToken'];

        $this->withHeader('X-Session-Token', $sessionToken)
            ->postJson('/api/public/cart/items', [
                'menu_item_id' => $item->id,
                'quantity' => 1,
            ])
            ->assertCreated();

        // حتى لو أرسل العميل service_type = dine_in تُجبره نقطة السفري على takeaway
        $orderRes = $this->withHeader('X-Session-Token', $sessionToken)
            ->postJson('/api/public/orders', [
                'service_type' => 'dine_in',
                'customer_note' => 'بدون مخلل',
            ]);

        $orderRes->assertCreated();
        $order = Order::withoutGlobalScopes()->where('order_number', $orderRes->json('data.order_number'))->firstOrFail();

        $this->assertSame('takeaway', $order->service_type);
        $this->assertSame('available', $counter->fresh()->operational_stage);

        $this->postJson("/api/public/tables/{$counter->public_token}/call-waiter", ['reason' => 'general'])
            ->assertStatus(422);

        $this->postJson("/api/public/tables/{$counter->public_token}/request-bill")
            ->assertStatus(422);
    }

    public function test_admin_can_create_takeaway_counter_and_floorplan_excludes_it(): void
    {
        $restaurant = Restaurant::factory()->create([
            'status' => 'active',
            'features' => ['floor_plan' => true],
        ]);
        $admin = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'status' => UserStatus::Active,
        ]);
        $admin->assignRole(UserRole::Admin->value);

        Sanctum::actingAs($admin);

        $createRes = $this->postJson('/api/admin/tables', [
            'name' => 'كاونتر سفري سريع',
            'table_type' => 'takeaway',
        ]);

        $createRes->assertCreated()
            ->assertJsonPath('data.table_type', 'takeaway');

        RestaurantTable::factory()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'طاولة صالة A1',
            'status' => 'active',
            'table_type' => RestaurantTable::TYPE_DINE_IN,
        ]);

        $floorRes = $this->actingAs($admin)->get('/admin/floor-plan');
        $floorRes->assertOk();
        $floorNames = collect($floorRes->viewData('page')['props']['tables'])->pluck('name')->all();

        $this->assertContains('طاولة صالة A1', $floorNames);
        $this->assertNotContains('كاونتر سفري سريع', $floorNames);

        $bookingRes = $this->get("/r/{$restaurant->slug}/vip-booking");
        $bookingRes->assertOk();
        $bookingNames = collect($bookingRes->viewData('page')['props']['tables'])->pluck('name')->all();

        $this->assertContains('طاولة صالة A1', $bookingNames);
        $this->assertNotContains('كاونتر سفري سريع', $bookingNames);
    }

    public function test_restaurant_slug_menu_and_alias_routes_resolve_isolated_takeaway_sessions(): void
    {
        $restaurant = Restaurant::factory()->create([
            'name' => 'مطعم بيت الشام',
            'slug' => 'bait-alsham-test',
            'status' => 'active',
        ]);

        // زيارة الرابط المرسل في الواتساب /m/{slug}?coupon=...
        $resA = $this->get('/m/bait-alsham-test?coupon=LUCK-ZL3WLA');
        $resA->assertOk();
        $propsA = $resA->viewData('page')['props'];
        $this->assertSame('takeaway', $propsA['table']['table_type']);
        $this->assertSame('الطلب السفري المباشر', $propsA['table']['name']);

        // زيارة أخرى عبر /r/{slug} يجب أن تعيد استخدام نفس نقطة السفري لكن بجلسة مستقلة
        $resB = $this->get('/r/bait-alsham-test');
        $resB->assertOk();
        $propsB = $resB->viewData('page')['props'];
        $this->assertSame($propsA['table']['id'], $propsB['table']['id']);
        $this->assertNotSame($propsA['sessionToken'], $propsB['sessionToken']);

        $this->assertSame(
            1,
            RestaurantTable::withoutGlobalScopes()
                ->where('restaurant_id', $restaurant->id)
                ->where('table_type', RestaurantTable::TYPE_TAKEAWAY)
                ->count()
        );

        // رابط لمطعم غير موجود يرد 404
        $this->get('/m/unknown-restaurant-slug')->assertNotFound();
    }
}
