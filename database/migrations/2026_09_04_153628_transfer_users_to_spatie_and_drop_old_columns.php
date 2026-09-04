<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. تعريف الأدوار الافتراضية
        $roles = ['super_admin', 'admin', 'staff'];
        foreach ($roles as $r) {
            Role::firstOrCreate(['name' => $r]);
        }

        // 2. تعريف الصلاحيات الافتراضية
        $permissions = [
            'view_orders', 'update_order_status', 'manage_orders', 
            'view_menu', 'manage_menu', 'manage_tables', 'manage_users'
        ];
        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p]);
        }

        // 3. نقل المستخدمين الحاليين (لا نستخدم TenantScope هنا لأننا نحتاج الجميع)
        $users = User::all();
        foreach ($users as $user) {
            // إضافة الدور
            if ($user->role) {
                // كان الـ role محفوظ كقيمة من Enum، مثال: 'admin'
                $roleName = is_string($user->role) ? $user->role : $user->role->value;
                if ($roleName) {
                    $user->assignRole($roleName);
                }
            }

            // إضافة الصلاحيات (إن وجدت)
            if (!empty($user->permissions) && is_array($user->permissions)) {
                foreach ($user->permissions as $perm) {
                    Permission::firstOrCreate(['name' => $perm]);
                    $user->givePermissionTo($perm);
                }
            }
        }

        // 4. حذف الأعمدة القديمة
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
            $table->dropColumn('permissions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('staff');
            $table->json('permissions')->nullable();
        });
    }
};
