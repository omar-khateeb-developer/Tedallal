<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('default_window')->nullable()->after('status')->comment('رقم الكوة الافتراضية للشيف');
        });

        // إنشاء أدوار Spatie الجديدة
        $roles = [
            'super_admin',
            'admin',
            'cashier',
            'chef',
            'supervisor',
            'waiter',
            'staff',
        ];

        foreach ($roles as $roleName) {
            \Spatie\Permission\Models\Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        // إسناد الصلاحيات الافتراضية لكل دور
        $rolePermissions = [
            'cashier' => ['manage_orders'],
            'chef' => ['manage_orders', 'view_orders', 'update_order_status'],
            'supervisor' => ['manage_orders', 'manage_tables', 'manage_menu'],
            'waiter' => ['manage_orders', 'manage_tables'],
        ];

        foreach ($rolePermissions as $roleName => $perms) {
            $role = \Spatie\Permission\Models\Role::where('name', $roleName)->first();
            if ($role) {
                foreach ($perms as $permName) {
                    \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
                }
                $role->syncPermissions($perms);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('default_window');
        });
    }
};
