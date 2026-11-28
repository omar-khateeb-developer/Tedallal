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
        $admin = User::role(UserRole::Admin->value)->first();

        $response = $this->actingAs($admin)->patchJson('/api/admin/restaurant', [
            'name' => 'مطعم محدّث',
            'phone' => '+963511111111',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'مطعم محدّث')
            ->assertJsonPath('data.phone', '+963511111111');
    }

    public function test_staff_cannot_update_restaurant_settings(): void
    {
        $staff = User::role(UserRole::Staff->value)->first();

        $response = $this->actingAs($staff)->patchJson('/api/admin/restaurant', [
            'name' => 'محاولة تغيير',
        ]);

        $response->assertForbidden();
    }

    public function test_admin_can_configure_auto_print_trigger_modes(): void
    {
        $admin = User::role(UserRole::Admin->value)->first();

        // Default is off
        $this->actingAs($admin)->getJson('/api/admin/restaurant')
            ->assertOk()
            ->assertJsonPath('data.auto_print_trigger', 'off')
            ->assertJsonPath('data.auto_print_on_ready', false);

        // Set to on_served (auto-print on kitchen/order delivery)
        $this->actingAs($admin)->patchJson('/api/admin/restaurant', [
            'auto_print_trigger' => 'on_served',
        ])->assertOk()
          ->assertJsonPath('data.auto_print_trigger', 'on_served')
          ->assertJsonPath('data.auto_print_on_ready', false);

        // Set to on_ready (auto-print when ready)
        $this->actingAs($admin)->patchJson('/api/admin/restaurant', [
            'auto_print_trigger' => 'on_ready',
        ])->assertOk()
          ->assertJsonPath('data.auto_print_trigger', 'on_ready')
          ->assertJsonPath('data.auto_print_on_ready', true);

        // Disable via off
        $this->actingAs($admin)->patchJson('/api/admin/restaurant', [
            'auto_print_trigger' => 'off',
        ])->assertOk()
          ->assertJsonPath('data.auto_print_trigger', 'off')
          ->assertJsonPath('data.auto_print_on_ready', false);

        // Invalid value is rejected
        $this->actingAs($admin)->patchJson('/api/admin/restaurant', [
            'auto_print_trigger' => 'invalid_mode',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['auto_print_trigger']);
    }

    public function test_staff_cannot_update_auto_print_trigger(): void
    {
        $staff = User::role(UserRole::Staff->value)->first();

        $this->actingAs($staff)->patchJson('/api/admin/restaurant', [
            'auto_print_trigger' => 'on_served',
        ])->assertForbidden();
    }
}
