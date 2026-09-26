<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. حذف أي permission قديم بـ guard sanctum لتفادي خطأ GuardDoesNotMatch
        Permission::where('name', 'toggle_maintenance')->where('guard_name', 'sanctum')->delete();

        // 2. جلب permission بالـ guard الافتراضي web
        $permissionWeb = Permission::firstOrCreate([
            'name'       => 'toggle_maintenance',
            'guard_name' => 'web',
        ]);

        // 3. سحب صلاحية إيقاف النظام من دور super_admin لكي لا ترثها كل حسابات المدراء
        $superAdminRole = Role::where('name', 'super_admin')->where('guard_name', 'web')->first();
        if ($superAdminRole && $superAdminRole->hasPermissionTo($permissionWeb)) {
            $superAdminRole->revokePermissionTo($permissionWeb);
        }

        // 4. منح الصلاحية بشكل مباشر وحصري لحساب المالك الرئيسي super@tedallal.test
        $superUser = User::where('email', 'super@tedallal.test')->first();
        if ($superUser && ! $superUser->hasPermissionTo($permissionWeb)) {
            $superUser->givePermissionTo($permissionWeb);
        }

        // 5. إنشاء حساب admin@tedallal.test إن لم يكن موجوداً، ومنحه دور super_admin بدون صلاحية الصيانة
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@tedallal.test'],
            [
                'name'     => 'مدير النظام',
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
                'status'   => \App\Enums\UserStatus::Active,
            ]
        );
        if (! $adminUser->hasRole('super_admin')) {
            $adminUser->assignRole('super_admin');
        }
        if ($adminUser->hasPermissionTo($permissionWeb)) {
            $adminUser->revokePermissionTo($permissionWeb);
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $superAdminRole = Role::where('name', 'super_admin')->where('guard_name', 'web')->first();
        $permissionWeb = Permission::where('name', 'toggle_maintenance')->where('guard_name', 'web')->first();

        if ($superAdminRole && $permissionWeb && ! $superAdminRole->hasPermissionTo($permissionWeb)) {
            $superAdminRole->givePermissionTo($permissionWeb);
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
