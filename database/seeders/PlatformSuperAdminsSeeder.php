<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PlatformSuperAdminsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. إعادة ضبط الكاش للتصاريح
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 2. ضمان وجود الصلاحية الحصرية لوضع الصيانة
        $togglePermission = Permission::query()->firstOrCreate(
            ['name' => 'toggle_maintenance', 'guard_name' => 'web']
        );

        // 3. ضمان وجود دور super_admin
        Role::query()->firstOrCreate(
            ['name' => UserRole::SuperAdmin->value, 'guard_name' => 'web']
        );

        // 4. التأكد من حالة وضع الصيانة الافتراضية
        Setting::set('maintenance_mode', Setting::get('maintenance_mode', '0'));

        // 5. حساب المالك الرئيسي (Platform Owner): super@tedallal.test
        $superAdmin = User::query()->firstOrNew(['email' => 'super@tedallal.test']);
        $superAdmin->name = 'مدير النظام الرئيسي';
        $superAdmin->restaurant_id = null;
        $superAdmin->default_window = null;
        $superAdmin->status = UserStatus::Active;
        if (! $superAdmin->exists) {
            $superAdmin->password = Hash::make('password');
        }
        $superAdmin->save();

        $superAdmin->syncRoles([UserRole::SuperAdmin->value]);
        $superAdmin->syncPermissions([$togglePermission]);

        // 6. حساب مدير النظام والتشغيل (Platform Admin): admin@tedallal.test
        $systemAdmin = User::query()->firstOrNew(['email' => 'admin@tedallal.test']);
        $systemAdmin->name = 'مدير النظام';
        $systemAdmin->restaurant_id = null;
        $systemAdmin->default_window = null;
        $systemAdmin->status = UserStatus::Active;
        if (! $systemAdmin->exists) {
            $systemAdmin->password = Hash::make('password');
        }
        $systemAdmin->save();

        // تجريد مدير النظام من أي دور مطعم فرعي، وقصره على super_admin فقط
        $systemAdmin->syncRoles([UserRole::SuperAdmin->value]);
        // حظر صلاحية الصيانة وأي صلاحية أخرى خاصة بالمطاعم
        $systemAdmin->syncPermissions([]);

        // 7. نسيان كاش التصاريح مجدداً لتطبيق التغييرات فوراً
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command?->info('✅ تم تهيئة وتطهير حسابات إدارة منصة تدلّل بنجاح:');
        $this->command?->info('   👑 المالك الرئيسي: super@tedallal.test (super_admin + toggle_maintenance)');
        $this->command?->info('   🛠️ مدير النظام:   admin@tedallal.test (super_admin حصراً وبلا أي مطعم)');
    }
}
