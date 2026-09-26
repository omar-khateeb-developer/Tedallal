<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * يتحكم في وضع صيانة المنصة.
 *
 * الوصول: super_admin role + toggle_maintenance permission
 * (كلاهما مُطبَّق في route middleware + تحقق داخلي)
 */
class MaintenanceController extends ApiController
{
    /**
     * الحصول على حالة وضع الصيانة الحالي.
     */
    public function status(): JsonResponse
    {
        $this->authorizeToggleMaintenance();

        return $this->success([
            'maintenance_mode' => Setting::isMaintenanceModeEnabled(),
        ]);
    }

    /**
     * تفعيل أو إيقاف وضع الصيانة.
     * يُوثَّق تلقائياً في activity log عند الاستخدام الحقيقي.
     */
    public function toggle(Request $request): JsonResponse
    {
        $this->authorizeToggleMaintenance();

        $request->validate([
            'enabled' => 'required|boolean',
        ]);

        $enabled = $request->boolean('enabled');

        Setting::set('maintenance_mode', $enabled ? '1' : '0');

        // تفريغ cache الصلاحيات لأن وضع الصيانة يؤثر على الـ middleware
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        return $this->success([
            'maintenance_mode' => $enabled,
            'message'          => $enabled
                ? 'تم تفعيل وضع الصيانة. الموقع الآن غير متاح للعموم.'
                : 'تم إيقاف وضع الصيانة. الموقع يعمل بشكل طبيعي.',
            'message_en' => $enabled
                ? 'Maintenance mode enabled. The site is now offline for public.'
                : 'Maintenance mode disabled. The site is back online.',
        ]);
    }

    /**
     * يتحقق أن المستخدم الحالي يملك صلاحية toggle_maintenance.
     * طبقة أمان ثانية فوق الـ route middleware.
     */
    private function authorizeToggleMaintenance(): void
    {
        $user = request()->user();

        if (! $user || ! $user->hasPermissionTo('toggle_maintenance')) {
            abort(403, 'ليس لديك صلاحية التحكم في وضع الصيانة.');
        }
    }
}
