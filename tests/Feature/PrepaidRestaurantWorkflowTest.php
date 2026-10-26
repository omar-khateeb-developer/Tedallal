<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use App\Models\User;
use App\Services\TableSessionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

use Laravel\Sanctum\Sanctum;

class PrepaidRestaurantWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_super_admin_can_create_prepaid_restaurant_with_windows_and_tables(): void
    {
        $superAdmin = User::factory()->create([
            'status' => \App\Enums\UserStatus::Active,
        ]);
        $superAdmin->assignRole(UserRole::SuperAdmin->value);
        Sanctum::actingAs($superAdmin);

        $response = $this->postJson('/api/super-admin/restaurants', [
            'name' => 'مطعم برجر السريع',
            'type' => 'prepaid',
            'windows_count' => 4,
            'tables_count' => 6,
            'currency' => 'SAR',
            'manager_name' => 'مدير المطعم',
            'manager_email' => 'manager_prepaid@test.com',
            'manager_password' => 'secret123',
        ]);

        $response->assertCreated();
        $restaurantId = $response->json('id');

        $restaurant = Restaurant::find($restaurantId);
        $this->assertNotNull($restaurant);
        $this->assertTrue($restaurant->isPrepaid());
        $this->assertSame(4, $restaurant->windows_count);

        // Verify that 6 tables were created
        $this->assertSame(6, $restaurant->tables()->count());
    }

    public function test_prepaid_order_lifecycle_from_cashier_payment_to_window_pickup(): void
    {
        // 1. Setup a prepaid restaurant
        $restaurant = Restaurant::factory()->create([
            'type' => 'prepaid',
            'windows_count' => 3,
        ]);

        $table = RestaurantTable::factory()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'طاولة 1',
        ]);

        $category = \App\Models\MenuCategory::factory()->create(['restaurant_id' => $restaurant->id]);
        $menuItem = MenuItem::factory()->create([
            'restaurant_id' => $restaurant->id,
            'category_id' => $category->id,
            'price' => '25.00',
            'is_available' => true,
        ]);

        $staffUser = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'status' => \App\Enums\UserStatus::Active,
        ]);
        $staffUser->assignRole(UserRole::Admin->value);
        Sanctum::actingAs($staffUser);

        // 2. Customer creates order via QR
        $session = app(TableSessionService::class)->resolveOrCreate($table);

        $this->withHeader('X-Session-Token', $session->session_token)
            ->postJson('/api/public/cart/items', [
                'menu_item_id' => $menuItem->id,
                'quantity' => 2,
            ])
            ->assertCreated();

        $orderResponse = $this->withHeader('X-Session-Token', $session->session_token)
            ->postJson('/api/public/orders', [
                'service_type' => 'takeaway',
                'customer_note' => 'بدون مخلل',
            ]);

        $orderResponse->assertCreated();
        $orderData = $orderResponse->json('data');
        $this->assertSame(OrderStatus::Pending->value, $orderData['status']);
        $this->assertSame('takeaway', $orderData['service_type']);
        $this->assertSame('prepaid', $orderData['restaurant_type']);
        $this->assertNull($orderData['pickup_window']);
        $this->assertNull($orderData['paid_at']);

        $orderId = $orderData['id'];
        $orderNumber = $orderData['order_number'];

        // 3. Cashier confirms payment (Transition to Accepted)
        $cashierResponse = $this->actingAs($staffUser)
            ->patchJson("/api/admin/orders/{$orderId}/status", [
                'status' => OrderStatus::Accepted->value,
            ]);

        $cashierResponse->assertOk();
        $updatedOrder = Order::find($orderId);
        $this->assertSame(OrderStatus::Accepted, $updatedOrder->status);
        $this->assertNotNull($updatedOrder->paid_at);
        $this->assertSame($staffUser->id, $updatedOrder->paid_by);

        // 4. Window 2 claims the order (Transition to Preparing with pickup_window = 2)
        $windowResponse = $this->actingAs($staffUser)
            ->patchJson("/api/admin/orders/{$orderId}/status", [
                'status' => OrderStatus::Preparing->value,
                'pickup_window' => 2,
            ]);

        $windowResponse->assertOk();
        $updatedOrder->refresh();
        $this->assertSame(OrderStatus::Preparing, $updatedOrder->status);
        $this->assertSame(2, $updatedOrder->pickup_window);

        // 5. Customer public order view returns assigned pickup_window
        $publicShowResponse = $this->withHeader('X-Session-Token', $session->session_token)
            ->getJson("/api/public/orders/{$orderNumber}");

        $publicShowResponse->assertOk();
        $publicData = $publicShowResponse->json('data');
        $this->assertSame(2, $publicData['pickup_window']);
        $this->assertSame('takeaway', $publicData['service_type']);
        $this->assertNotNull($publicData['paid_at']);
        $this->assertSame('prepaid', $publicData['restaurant_type']);

        // 6. Window 2 marks order Ready
        $readyResponse = $this->actingAs($staffUser)
            ->patchJson("/api/admin/orders/{$orderId}/status", [
                'status' => OrderStatus::Ready->value,
            ]);

        $readyResponse->assertOk();
        $updatedOrder->refresh();
        $this->assertSame(OrderStatus::Ready, $updatedOrder->status);
        $this->assertSame(2, $updatedOrder->pickup_window);

        // 7. Order is served to customer
        $servedResponse = $this->actingAs($staffUser)
            ->patchJson("/api/admin/orders/{$orderId}/status", [
                'status' => OrderStatus::Served->value,
            ]);

        $servedResponse->assertOk();
        $updatedOrder->refresh();
        $this->assertSame(OrderStatus::Served, $updatedOrder->status);
    }
}
