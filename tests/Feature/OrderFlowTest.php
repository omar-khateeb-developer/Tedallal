<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use App\Services\TableSessionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function createSessionForTable(): TableSession
    {
        $table = RestaurantTable::query()->first();

        return app(TableSessionService::class)->resolveOrCreate($table);
    }

    public function test_unavailable_item_rejected(): void
    {
        $session = $this->createSessionForTable();
        $item = MenuItem::query()->first();
        $item->update(['is_available' => false]);

        $response = $this->withHeader('X-Session-Token', $session->session_token)
            ->postJson('/api/public/cart/items', ['menu_item_id' => $item->id, 'quantity' => 1]);

        $response->assertStatus(422);
    }

    public function test_price_snapshot_preserved_after_menu_change(): void
    {
        $session = $this->createSessionForTable();
        $item = MenuItem::query()->first();
        $originalPrice = (string) $item->price;

        $this->withHeader('X-Session-Token', $session->session_token)
            ->postJson('/api/public/cart/items', ['menu_item_id' => $item->id, 'quantity' => 1]);

        $orderResponse = $this->withHeader('X-Session-Token', $session->session_token)
            ->postJson('/api/public/orders');

        $orderResponse->assertCreated();
        $orderNumber = $orderResponse->json('data.order_number');

        $item->update(['price' => '999.99']);

        $order = Order::query()->where('order_number', $orderNumber)->first();
        $this->assertSame($originalPrice, (string) $order->items->first()->unit_price);
    }

    public function test_order_creation_is_atomic(): void
    {
        $session = $this->createSessionForTable();
        $items = MenuItem::query()->take(2)->get();

        foreach ($items as $item) {
            $this->withHeader('X-Session-Token', $session->session_token)
                ->postJson('/api/public/cart/items', ['menu_item_id' => $item->id, 'quantity' => 1]);
        }

        MenuItem::query()->update(['is_available' => false]);

        $beforeCount = Order::query()->count();

        $this->withHeader('X-Session-Token', $session->session_token)
            ->postJson('/api/public/orders')
            ->assertStatus(422);

        $this->assertSame($beforeCount, Order::query()->count());
    }
}
