<?php

namespace Tests\Feature;

use App\Models\RestaurantTable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}

