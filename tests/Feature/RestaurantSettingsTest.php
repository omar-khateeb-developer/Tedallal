<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestaurantSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_can_update_restaurant_settings(): void
    {
        $admin = User::query()->where('role', UserRole::Admin)->first();

        $response = $this->actingAs($admin)->patchJson('/api/admin/restaurant', [
            'name' => 'مطعم محدّث',
            'phone' => '+966511111111',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'مطعم محدّث')
            ->assertJsonPath('data.phone', '+966511111111');
    }

    public function test_staff_cannot_update_restaurant_settings(): void
    {
        $staff = User::query()->where('role', UserRole::Staff)->first();

        $response = $this->actingAs($staff)->patchJson('/api/admin/restaurant', [
            'name' => 'محاولة تغيير',
        ]);

        $response->assertForbidden();
    }
}
