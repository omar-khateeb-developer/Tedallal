<?php

namespace Tests\Feature;

use App\Enums\RestaurantPlan;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\User;
use App\Models\WaitlistEntry;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WaitlistFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_public_waitlist_join_live_status_and_self_cancellation(): void
    {
        $restaurant = Restaurant::factory()->create([
            'name' => 'مطعم النسيم',
            'slug' => 'naseem-rest',
            'status' => 'active',
            'whatsapp_status' => 'connected',
            'plan' => RestaurantPlan::Max,
        ]);

        Http::fake([
            '*/api/messages/send' => Http::response(['success' => true, 'message_id' => 'wa-wl-1'], 200),
        ]);

        // Public join page loads
        $this->get("/r/{$restaurant->slug}/waitlist")->assertOk();

        // Guest 1 joins with Arabic digits phone number
        $join1 = $this->postJson("/api/public/restaurants/{$restaurant->slug}/waitlist/join", [
            'guest_name' => 'سامي العلي',
            'guest_phone' => '٠٩٦٧١٢٣٥٦٩',
            'party_size' => 4,
            'seating_preference' => 'outdoor',
            'notes' => 'قرب النافذة',
        ]);

        $join1->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('entry.queue_number', 1)
            ->assertJsonPath('entry.status', 'waiting');

        $uuid1 = $join1->json('entry.uuid');
        $this->assertNotEmpty($uuid1);

        // Guest 2 joins right after -> queue_number = 2
        $join2 = $this->postJson("/api/public/restaurants/{$restaurant->slug}/waitlist/join", [
            'guest_name' => 'ليلى حسن',
            'guest_phone' => '0933445566',
            'party_size' => 2,
        ]);

        $join2->assertOk()
            ->assertJsonPath('entry.queue_number', 2);

        $uuid2 = $join2->json('entry.uuid');

        // Public tracking pages & live polling positions
        $this->get("/w/{$uuid1}")->assertOk();

        $this->getJson("/api/public/waitlist/{$uuid1}/status")
            ->assertOk()
            ->assertJson([
                'status' => 'waiting',
                'queue_position' => 1,
                'assigned_table' => null,
            ]);

        $this->getJson("/api/public/waitlist/{$uuid2}/status")
            ->assertOk()
            ->assertJson([
                'status' => 'waiting',
                'queue_position' => 2,
            ]);

        $this->getJson('/api/public/waitlist/non-existent-uuid/status')->assertStatus(404);

        // Guest 1 cancels their own place -> queue position for Guest 2 moves to 1
        $this->postJson("/api/public/waitlist/{$uuid1}/cancel")
            ->assertOk()
            ->assertJson([
                'success' => true,
                'status' => 'cancelled',
            ]);

        $this->getJson("/api/public/waitlist/{$uuid1}/status")
            ->assertOk()
            ->assertJson([
                'status' => 'cancelled',
                'queue_position' => 0,
            ]);

        $this->getJson("/api/public/waitlist/{$uuid2}/status")
            ->assertOk()
            ->assertJson([
                'status' => 'waiting',
                'queue_position' => 1,
            ]);
    }

    public function test_admin_waitlist_lifecycle_notify_seat_status_and_tenant_isolation(): void
    {
        $restA = Restaurant::factory()->create(['status' => 'active', 'whatsapp_status' => 'connected']);
        $restB = Restaurant::factory()->create(['status' => 'active', 'whatsapp_status' => 'connected']);

        $tableA = RestaurantTable::factory()->create([
            'restaurant_id' => $restA->id,
            'name' => 'T-10',
            'table_type' => RestaurantTable::TYPE_DINE_IN,
        ]);
        $tableB = RestaurantTable::factory()->create([
            'restaurant_id' => $restB->id,
            'name' => 'B-99',
            'table_type' => RestaurantTable::TYPE_DINE_IN,
        ]);

        $adminA = User::factory()->create(['restaurant_id' => $restA->id, 'status' => UserStatus::Active]);
        $adminA->assignRole(UserRole::Admin->value);

        $adminB = User::factory()->create(['restaurant_id' => $restB->id, 'status' => UserStatus::Active]);
        $adminB->assignRole(UserRole::Admin->value);

        Http::fake([
            '*/api/messages/send' => Http::response(['success' => true, 'message_id' => 'wa-wl-2'], 200),
        ]);

        // Tenant B already has a waitlist entry that Admin A must never see
        $entryB = WaitlistEntry::create([
            'restaurant_id' => $restB->id,
            'guest_name' => 'ضيف منافس',
            'guest_phone' => '0999888777',
            'party_size' => 5,
            'queue_number' => 1,
            'status' => 'waiting',
        ]);

        Sanctum::actingAs($adminA);

        // Admin A creates walk-in guest
        $storeRes = $this->postJson('/api/admin/waitlist', [
            'guest_name' => 'خالد منصور',
            'guest_phone' => '0967111222',
            'party_size' => 3,
            'seating_preference' => 'indoor',
            'estimated_wait_minutes' => 20,
        ]);

        $storeRes->assertCreated()
            ->assertJsonPath('data.entry.guest_name', 'خالد منصور')
            ->assertJsonPath('data.entry.queue_number', 1)
            ->assertJsonPath('data.whatsapp_sent', true);

        $entryAId = $storeRes->json('data.entry.id');
        $entryAUuid = $storeRes->json('data.entry.uuid');

        // Admin A creates second walk-in guest for no_show transition later
        $store2Res = $this->postJson('/api/admin/waitlist', [
            'guest_name' => 'نور الدين',
            'guest_phone' => '0967333444',
            'party_size' => 2,
        ])->assertCreated();
        $entryA2Id = $store2Res->json('data.entry.id');

        // Index returns only Rest A entries with accurate summary
        $indexRes = $this->getJson('/api/admin/waitlist');
        $indexRes->assertOk()
            ->assertJsonPath('data.summary.waiting_count', 2)
            ->assertJsonPath('data.summary.notified_count', 0)
            ->assertJsonPath('data.summary.total_today', 2);

        $listedIds = collect($indexRes->json('data.entries'))->pluck('id')->all();
        $this->assertContains($entryAId, $listedIds);
        $this->assertNotContains($entryB->id, $listedIds);

        // Notify guest with table assignment
        $notifyRes = $this->patchJson("/api/admin/waitlist/{$entryAId}/notify", [
            'table_id' => $tableA->id,
        ]);
        $notifyRes->assertOk()
            ->assertJsonPath('data.entry.status', 'notified')
            ->assertJsonPath('data.entry.assigned_table.id', $tableA->id)
            ->assertJsonPath('data.whatsapp_sent', true);

        // Public status reflects assigned table & notified stage
        $this->getJson("/api/public/waitlist/{$entryAUuid}/status")
            ->assertOk()
            ->assertJson([
                'status' => 'notified',
                'assigned_table' => 'T-10',
            ]);

        // Cross-tenant seating attempt using Rest B's table must fail cleanly
        $this->patchJson("/api/admin/waitlist/{$entryAId}/seat", [
            'table_id' => $tableB->id,
        ])->assertStatus(404);

        // Seat guest on Rest A's table -> updates both WaitlistEntry and RestaurantTable stage
        $this->patchJson("/api/admin/waitlist/{$entryAId}/seat", [
            'table_id' => $tableA->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.entry.status', 'seated')
            ->assertJsonPath('data.table.operational_stage', 'seated')
            ->assertJsonPath('data.table.current_guest_name', 'خالد منصور');

        // Mark second guest as no_show
        $this->patchJson("/api/admin/waitlist/{$entryA2Id}/status", [
            'status' => 'no_show',
        ])
            ->assertOk()
            ->assertJsonPath('data.entry.status', 'no_show');
        $this->assertNotNull(WaitlistEntry::find($entryA2Id)->cancelled_at);

        // Tenant B Admin MUST receive 403 when attempting to touch Tenant A's waitlist entry
        Sanctum::actingAs($adminB);
        $this->patchJson("/api/admin/waitlist/{$entryAId}/notify")->assertStatus(403);
        $this->patchJson("/api/admin/waitlist/{$entryAId}/seat", ['table_id' => $tableB->id])->assertStatus(403);
        $this->patchJson("/api/admin/waitlist/{$entryAId}/status", ['status' => 'cancelled'])->assertStatus(403);
    }

    public function test_user_without_manage_tables_permission_cannot_access_admin_waitlist(): void
    {
        $restaurant = Restaurant::factory()->create(['status' => 'active']);
        $chefUser = User::factory()->create(['restaurant_id' => $restaurant->id, 'status' => UserStatus::Active]);
        $chefUser->assignRole(UserRole::Chef->value);

        Sanctum::actingAs($chefUser);

        $this->getJson('/api/admin/waitlist')->assertStatus(403);
    }
}
