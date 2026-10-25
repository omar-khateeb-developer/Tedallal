<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\SessionStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\MenuCategory;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_security_headers_are_present_in_responses(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_inactive_user_cannot_login(): void
    {
        $restaurant = Restaurant::first();
        $user = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'password' => bcrypt('password123'),
            'status' => UserStatus::Inactive,
        ]);
        $user->assignRole(UserRole::Admin->value);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_inactive_staff_user_is_blocked_by_permission_middleware(): void
    {
        $restaurant = Restaurant::first();
        $user = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'status' => UserStatus::Inactive,
        ]);
        $user->assignRole(UserRole::Staff->value);
        $user->givePermissionTo('manage_orders');

        $response = $this->actingAs($user)->get('/staff/orders');
        $response->assertForbidden();
    }

    public function test_primary_super_admin_cannot_be_deleted(): void
    {
        $super = User::where('email', 'super@tedallal.test')->first();
        if (! $super) {
            $super = User::factory()->create([
                'email' => 'super@tedallal.test',
                'status' => UserStatus::Active,
            ]);
            $super->assignRole(UserRole::SuperAdmin->value);
        }

        // Attempt deletion via ProfileController
        $response = $this->actingAs($super)->delete('/profile', [
            'password' => 'password',
        ]);
        $response->assertSessionHasErrors('user');
        $this->assertDatabaseHas('users', ['email' => 'super@tedallal.test']);
    }

    public function test_customer_cannot_view_order_of_another_table(): void
    {
        $restaurant = Restaurant::first();
        $table1 = RestaurantTable::factory()->create(['restaurant_id' => $restaurant->id]);
        $table2 = RestaurantTable::factory()->create(['restaurant_id' => $restaurant->id]);

        $session = TableSession::create([
            'table_id' => $table1->id,
            'session_token' => 'sess_tok_security_test',
            'status' => SessionStatus::Active,
            'started_at' => now(),
        ]);

        $order = Order::create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table1->id,
            'table_session_id' => $session->id,
            'order_number' => 'ORD-20261026-9999',
            'status' => OrderStatus::Pending,
            'subtotal' => '25.00',
            'total' => '25.00',
        ]);

        // Attempt accessing table1's order via table2's token
        $response = $this->get("/t/{$table2->public_token}/orders/{$order->order_number}");
        $response->assertNotFound();
    }

    public function test_menu_item_cannot_be_assigned_to_category_of_another_restaurant(): void
    {
        $admin1 = User::role(UserRole::Admin->value)->first();
        $restaurant2 = Restaurant::factory()->create();

        $category2 = MenuCategory::factory()->create(['restaurant_id' => $restaurant2->id]);

        $response = $this->actingAs($admin1)->postJson('/api/admin/menu/items', [
            'category_id' => $category2->id,
            'name' => 'Burger',
            'price' => 15.5,
        ]);

        $response->assertUnprocessable();
    }
}
