<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Restaurant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserRolesAndChefWindowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_creating_chef_requires_pickup_window(): void
    {
        $restaurant = Restaurant::factory()->create([
            'type' => 'prepaid',
            'windows_count' => 4,
        ]);

        $admin = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'status' => UserStatus::Active,
        ]);
        $admin->assignRole(UserRole::Admin->value);
        Sanctum::actingAs($admin);

        // محاولة إنشاء شيف بدون تحديد النافذة يجب أن تفشل
        $response = $this->postJson('/api/admin/users', [
            'name' => 'الشيف سامي',
            'email' => 'chef.sami@test.com',
            'password' => 'secret123',
            'role' => UserRole::Chef->value,
            'default_window' => null,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['default_window']);
    }

    public function test_creating_chef_with_valid_window_succeeds_and_multiple_chefs_can_share_window(): void
    {
        $restaurant = Restaurant::factory()->create([
            'type' => 'prepaid',
            'windows_count' => 3,
        ]);

        $admin = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'status' => UserStatus::Active,
        ]);
        $admin->assignRole(UserRole::Admin->value);
        Sanctum::actingAs($admin);

        // 1. إنشاء الشيف الأول على نافذة رقم 2
        $response1 = $this->postJson('/api/admin/users', [
            'name' => 'الشيف أحمد',
            'email' => 'chef.ahmad@test.com',
            'password' => 'secret123',
            'role' => UserRole::Chef->value,
            'default_window' => 2,
        ]);

        $response1->assertCreated();
        $chef1 = User::where('email', 'chef.ahmad@test.com')->first();
        $this->assertNotNull($chef1);
        $this->assertTrue($chef1->isChef());
        $this->assertSame(2, $chef1->default_window);
        $this->assertTrue($chef1->hasPermissionTo('manage_orders'));

        // 2. إنشاء الشيف الثاني على نفس النافذة رقم 2 (النافذة يمكن أن يوجد فيها أكثر من شيف)
        $response2 = $this->postJson('/api/admin/users', [
            'name' => 'الشيف عمر',
            'email' => 'chef.omar@test.com',
            'password' => 'secret123',
            'role' => UserRole::Chef->value,
            'default_window' => 2,
        ]);

        $response2->assertCreated();
        $chef2 = User::where('email', 'chef.omar@test.com')->first();
        $this->assertNotNull($chef2);
        $this->assertTrue($chef2->isChef());
        $this->assertSame(2, $chef2->default_window);

        // التأكد من أن الاثنين مسجلان ويعملان على نفس النافذة رقم 2
        $this->assertSame($chef1->default_window, $chef2->default_window);
    }

    public function test_creating_cashier_and_supervisor_roles_succeeds(): void
    {
        $restaurant = Restaurant::factory()->create([
            'type' => 'prepaid',
            'windows_count' => 4,
        ]);

        $admin = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'status' => UserStatus::Active,
        ]);
        $admin->assignRole(UserRole::Admin->value);
        Sanctum::actingAs($admin);

        // إنشاء كاشير
        $cashierRes = $this->postJson('/api/admin/users', [
            'name' => 'الكاشير سارة',
            'email' => 'cashier.sara@test.com',
            'password' => 'secret123',
            'role' => UserRole::Cashier->value,
        ]);

        $cashierRes->assertCreated();
        $cashier = User::where('email', 'cashier.sara@test.com')->first();
        $this->assertNotNull($cashier);
        $this->assertTrue($cashier->isCashier());
        $this->assertTrue($cashier->hasPermissionTo('manage_orders'));
        $this->assertNull($cashier->default_window);

        // إنشاء مشرف
        $supervisorRes = $this->postJson('/api/admin/users', [
            'name' => 'المشرف خالد',
            'email' => 'supervisor.khaled@test.com',
            'password' => 'secret123',
            'role' => UserRole::Supervisor->value,
        ]);

        $supervisorRes->assertCreated();
        $supervisor = User::where('email', 'supervisor.khaled@test.com')->first();
        $this->assertNotNull($supervisor);
        $this->assertTrue($supervisor->isSupervisor());
        $this->assertTrue($supervisor->hasPermissionTo('manage_orders'));
        $this->assertTrue($supervisor->hasPermissionTo('manage_menu'));
    }

    public function test_smart_login_redirection_based_on_role(): void
    {
        $restaurant = Restaurant::factory()->create();

        // 1. الكاشير يُوجه إلى شاشة الكاشير والطلبات
        $cashier = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'status' => UserStatus::Active,
        ]);
        $cashier->assignRole(UserRole::Cashier->value);
        $this->actingAs($cashier);
        $this->get('/dashboard')->assertRedirect(route('admin.orders'));

        // 2. الشيف يُوجه إلى شاشة المطبخ KDS
        $chef = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'status' => UserStatus::Active,
            'default_window' => 1,
        ]);
        $chef->assignRole(UserRole::Chef->value);
        $this->actingAs($chef);
        $this->get('/dashboard')->assertRedirect(route('staff.orders'));

        // 3. مدير المطعم يُوجه إلى لوحة التحكم الرئيسية
        $admin = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'status' => UserStatus::Active,
        ]);
        $admin->assignRole(UserRole::Admin->value);
        $this->actingAs($admin);
        $this->get('/dashboard')->assertRedirect(route('admin.dashboard'));
    }
}
