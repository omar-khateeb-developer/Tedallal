<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_staff_cannot_access_admin_table_management(): void
    {
        $staff = User::role(UserRole::Staff->value)->first();

        $response = $this->actingAs($staff)->getJson('/api/admin/tables');

        $response->assertForbidden();
    }
}
