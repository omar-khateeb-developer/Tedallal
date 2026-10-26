<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_headers_are_present_in_responses(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_inactive_user_cannot_login(): void
    {
        $restaurant = Restaurant::factory()->create();
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
        $restaurant = Restaurant::factory()->create();
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
        $super = User::factory()->create([
            'email' => 'super@tedallal.test',
            'status' => UserStatus::Active,
        ]);
        $super->assignRole(UserRole::SuperAdmin->value);

        // Attempt deletion via ProfileController
        $response = $this->actingAs($super)->delete('/profile', [
            'password' => 'password',
        ]);
        $response->assertSessionHasErrors('user');
        $this->assertDatabaseHas('users', ['email' => 'super@tedallal.test']);
    }

    public function test_customer_cannot_view_order_of_another_table(): void
    {
        $restaurant = Restaurant::factory()->create();
        $table1 = RestaurantTable::factory()->create(['restaurant_id' => $restaurant->id]);
        $table2 = RestaurantTable::factory()->create(['restaurant_id' => $restaurant->id]);

        $order = Order::factory()->create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table1->id,
            'order_number' => 'ORD-20261026-9999',
            'status' => OrderStatus::Pending,
        ]);

        // Attempt accessing table1's order via table2's token
        $response = $this->get("/t/{$table2->public_token}/orders/{$order->order_number}");
        $response->assertNotFound();
    }

    public function test_menu_item_cannot_be_assigned_to_category_of_another_restaurant(): void
    {
        $restaurant1 = Restaurant::factory()->create();
        $restaurant2 = Restaurant::factory()->create();

        $admin1 = User::factory()->create(['restaurant_id' => $restaurant1->id]);
        $admin1->assignRole(UserRole::Admin->value);

        $category2 = MenuCategory::factory()->create(['restaurant_id' => $restaurant2->id]);

        $response = $this->actingAs($admin1)->postJson('/api/admin/menu/items', [
            'category_id' => $category2->id,
            'name' => 'Burger',
            'price' => 15.5,
        ]);

        $response->assertUnprocessable();
    }
}
