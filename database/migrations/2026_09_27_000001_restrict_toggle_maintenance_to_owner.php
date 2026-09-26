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
        // 1. سحب صلاحية إيقاف النظام من دور super_admin لكي لا ترثها كل حسابات المدراء
        $superAdminRole = Role::where('name', 'super_admin')->first();
        $permissionWeb = Permission::where('name', 'toggle_maintenance')->where('guard_name', 'web')->first();
        $permissionSanctum = Permission::where('name', 'toggle_maintenance')->where('guard_name', 'sanctum')->first();

        if ($superAdminRole) {
            if ($permissionWeb && $superAdminRole->hasPermissionTo($permissionWeb)) {
                $superAdminRole->revokePermissionTo($permissionWeb);
            }
            if ($permissionSanctum && $superAdminRole->hasPermissionTo($permissionSanctum)) {
                $superAdminRole->revokePermissionTo($permissionSanctum);
            }
        }

        // 2. منح الصلاحية بشكل مباشر وحصري لحساب المالك الرئيسي super@tedallal.test
        $superUser = User::where('email', 'super@tedallal.test')->first();
        if ($superUser) {
            if ($permissionWeb) {
                $superUser->givePermissionTo($permissionWeb);
            }
            if ($permissionSanctum) {
                $superUser->givePermissionTo($permissionSanctum);
            }
        }

        // 3. التأكد من سحب أي صلاحية إيقاف للنظام من admin@tedallal.test إن وُجد
        $adminUser = User::where('email', 'admin@tedallal.test')->first();
        if ($adminUser) {
            if ($permissionWeb && $adminUser->hasPermissionTo($permissionWeb)) {
                $adminUser->revokePermissionTo($permissionWeb);
            }
            if ($permissionSanctum && $adminUser->hasPermissionTo($permissionSanctum)) {
                $adminUser->revokePermissionTo($permissionSanctum);
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $superAdminRole = Role::where('name', 'super_admin')->first();
        $permissionWeb = Permission::where('name', 'toggle_maintenance')->where('guard_name', 'web')->first();

        if ($superAdminRole && $permissionWeb && ! $superAdminRole->hasPermissionTo($permissionWeb)) {
            $superAdminRole->givePermissionTo($permissionWeb);
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
