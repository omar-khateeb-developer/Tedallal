<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * ينشئ permission اسمه "toggle_maintenance" ويمنحه لـ super_admin role حصراً.
 *
 * هذا يُتيح التحقق المزدوج:
 *   - Role  : super_admin
 *   - Permission: toggle_maintenance
 *
 * فلو احتجنا يوماً منح هذه الصلاحية لجهة أخرى (مثلاً: مشرف نظام)
 * نفعل ذلك عبر permission بدون تغيير الـ role.
 */
return new class extends Migration
{
    public function up(): void
    {
        // إنشاء permission إذا لم يكن موجوداً
        $permission = Permission::firstOrCreate([
            'name'       => 'toggle_maintenance',
            'guard_name' => 'web',
        ]);

        // إضافة نفس الـ permission للـ API guard أيضاً (Sanctum)
        Permission::firstOrCreate([
            'name'       => 'toggle_maintenance',
            'guard_name' => 'sanctum',
        ]);

        // ربط الـ permission بـ super_admin role فقط
        $superAdminRole = Role::where('name', 'super_admin')->first();
        if ($superAdminRole && ! $superAdminRole->hasPermissionTo($permission)) {
            $superAdminRole->givePermissionTo($permission);
        }

        // تفريغ cache الصلاحيات
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permission = Permission::where('name', 'toggle_maintenance')->first();
        if (! $permission) {
            return;
        }

        $superAdminRole = Role::where('name', 'super_admin')->first();
        if ($superAdminRole) {
            $superAdminRole->revokePermissionTo($permission);
        }

        Permission::where('name', 'toggle_maintenance')->delete();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
