<?php

namespace Tests\Feature;

use App\Enums\SessionStatus;
use App\Events\TableUpdated;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\TableReservation;
use App\Models\User;
use App\Services\TableSessionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TableQrFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_valid_qr_resolves_table(): void
    {
        $table = RestaurantTable::query()->first();

        $response = $this->getJson("/api/public/tables/{$table->public_token}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', $table->name);
    }

    public function test_invalid_token_returns_404(): void
    {
        $response = $this->getJson('/api/public/tables/invalid-token-xyz');

        $response->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_creating_table_generates_qr_code(): void
    {
        $admin = \App\Models\User::where('email', 'admin@al-diwan.test')->first();

        $response = $this->actingAs($admin)->postJson('/api/admin/tables', [
            'name' => 'طاولة جديدة للتحقق',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true);

        $tableId = $response->json('data.id');
        $token = $response->json('data.public_token');
        $table = RestaurantTable::withoutGlobalScopes()->find($tableId);

        $this->assertNotNull($table);
        $this->assertTrue(\Illuminate\Support\Facades\Storage::disk('public')->exists("qr/{$table->restaurant_id}/{$token}.png"));
    }

    public function test_regenerating_table_token_replaces_qr_code(): void
    {
        $admin = \App\Models\User::where('email', 'admin@al-diwan.test')->first();
        $table = RestaurantTable::withoutGlobalScopes()->where('restaurant_id', $admin->restaurant_id)->first();
        $oldToken = $table->public_token;

        $response = $this->actingAs($admin)->postJson("/api/admin/tables/{$table->id}/regenerate-token");

        $response->assertOk()
            ->assertJsonPath('success', true);

        $newToken = $response->json('data.public_token');
        $this->assertNotEquals($oldToken, $newToken);

        $this->assertFalse(\Illuminate\Support\Facades\Storage::disk('public')->exists("qr/{$table->restaurant_id}/{$oldToken}.png"));
        $this->assertTrue(\Illuminate\Support\Facades\Storage::disk('public')->exists("qr/{$table->restaurant_id}/{$newToken}.png"));
    }

    public function test_fallback_route_serves_missing_qr_code(): void
    {
        $table = RestaurantTable::query()->first();
        $path = "qr/{$table->restaurant_id}/{$table->public_token}.png";

        // Delete from storage if it exists
        \Illuminate\Support\Facades\Storage::disk('public')->delete($path);

        $response = $this->get("/storage/{$path}");

        $response->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        $this->assertTrue(\Illuminate\Support\Facades\Storage::disk('public')->exists($path));
    }

    public function test_deleting_table_succeeds_without_active_session_and_rejects_active_session(): void
    {
        Event::fake([TableUpdated::class]);

        $admin = User::where('email', 'admin@al-diwan.test')->firstOrFail();

        $createRes = $this->actingAs($admin)->postJson('/api/admin/tables', [
            'name' => 'طاولة للحذف',
        ])->assertCreated();

        $table = RestaurantTable::withoutGlobalScopes()->findOrFail($createRes->json('data.id'));
        $qrPath = "qr/{$table->restaurant_id}/{$table->public_token}.png";
        $this->assertTrue(Storage::disk('public')->exists($qrPath));

        // 1. Delete unused table succeeds (was 500 before tableSessions() -> sessions() fix)
        $this->actingAs($admin)->deleteJson("/api/admin/tables/{$table->id}")->assertOk();
        $this->assertDatabaseMissing('restaurant_tables', ['id' => $table->id]);
        $this->assertFalse(Storage::disk('public')->exists($qrPath));

        // 2. Table with active session cannot be deleted (422)
        $busyTable = RestaurantTable::factory()->create([
            'restaurant_id' => $admin->restaurant_id,
            'table_type' => RestaurantTable::TYPE_DINE_IN,
        ]);
        app(TableSessionService::class)->resolveOrCreate($busyTable);

        $this->actingAs($admin)->deleteJson("/api/admin/tables/{$busyTable->id}")->assertStatus(422);
        $this->assertDatabaseHas('restaurant_tables', ['id' => $busyTable->id]);
    }

    public function test_qr_scan_updates_dine_in_table_stage_broadcasts_and_handles_cleaning_and_vip_context(): void
    {
        Event::fake([TableUpdated::class]);

        $restaurant = Restaurant::factory()->create(['status' => 'active']);
        $table = RestaurantTable::factory()->create([
            'restaurant_id' => $restaurant->id,
            'table_type' => RestaurantTable::TYPE_DINE_IN,
            'status' => 'active',
            'operational_stage' => 'available',
        ]);

        // 1. First QR scan opens session, transitions table to seated, and broadcasts TableUpdated
        $visit = $this->get("/t/{$table->public_token}")->assertOk();
        $this->assertNull($visit->viewData('page')['props']['tableNotice']);
        $this->assertSame('seated', $table->fresh()->operational_stage);
        Event::assertDispatched(TableUpdated::class);

        // 2. Cleaning table closes leftover session, starts clean session, and shows cleaning notice
        $oldSession = $table->fresh()->activeSession;
        $table->update(['operational_stage' => 'cleaning']);

        $cleaningVisit = $this->get("/t/{$table->public_token}")->assertOk();
        $this->assertSame('cleaning', $cleaningVisit->viewData('page')['props']['tableNotice']['type']);
        $this->assertSame(SessionStatus::Closed, $oldSession->fresh()->status);
        $this->assertSame('seated', $table->fresh()->operational_stage);

        // 3. Upcoming VIP reservation within 120m triggers vip_reserved notice
        TableReservation::create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'guest_name' => 'ضيف VIP',
            'guest_phone' => '0944112233',
            'reservation_date' => now()->toDateString(),
            'time_slot' => now()->addMinutes(30)->format('H:i'),
            'guests_count' => 4,
            'status' => 'confirmed',
        ]);

        $vipVisit = $this->get("/t/{$table->public_token}")->assertOk();
        $this->assertSame('vip_reserved', $vipVisit->viewData('page')['props']['tableNotice']['type']);
    }
}

