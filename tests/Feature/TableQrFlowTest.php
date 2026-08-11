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
}
