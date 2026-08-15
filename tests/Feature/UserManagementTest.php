<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_can_create_staff_user(): void
    {
        $admin = User::query()->where('role', UserRole::Admin)->first();

        $response = $this->actingAs($admin)->postJson('/api/admin/users', [
            'name' => 'موظف جديد',
            'email' => 'newstaff@tedallal.test',
            'password' => 'password123',
            'role' => 'staff',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.email', 'newstaff@tedallal.test');
    }

    public function test_staff_cannot_manage_users(): void
    {
        $staff = User::query()->where('role', UserRole::Staff)->first();

        $this->actingAs($staff)->getJson('/api/admin/users')->assertForbidden();
    }
}
