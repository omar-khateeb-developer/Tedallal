<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * وضع الصيانة — يُطبَّق على كل الطلبات (web + api).
 *
 * ═══ من يتجاوز الصيانة؟ ═══════════════════════════════════
 *  ✅  أي مستخدم يملك permission "toggle_maintenance"
 *      (حالياً: super_admin role فقط — super@tedallal.test)
 *
 * ═══ المسارات المُعفاة دائماً ══════════════════════════════
 *  ✅  GET|POST /login          → تسجيل الدخول
 *  ✅  POST     /logout         → تسجيل الخروج
 *  ✅  GET|POST /forgot-password
 *  ✅  GET|POST /reset-password
 *  ✅  GET|POST /api/login
 *
 * ═══ من يرى صفحة الصيانة؟ ══════════════════════════════════
 *  🚫  كل مستخدم آخر (مسجّل أو غير مسجّل)
 *  → Web : Maintenance.vue (HTTP 503)
 *  → API : JSON          (HTTP 503)
 */
class CheckMaintenanceMode
{
    /**
     * المسارات المُعفاة من وضع الصيانة (تتطابق مع is() أي بدون اعتبار الـ method).
     */
    private const ALLOWED_PATHS = [
        'login',
        'logout',
        'forgot-password',
        'reset-password',
        'reset-password/*',
        'api/login',
        'api/logout',
    ];

    public function handle(Request $request, Closure $next): mixed
    {
        // ① الصيانة مُعطَّلة → مرور عادي
        if (! Setting::isMaintenanceModeEnabled()) {
            return $next($request);
        }

        // ② مسار مُعفى دائماً (login, logout, forgot-password …)
        if ($request->is(...self::ALLOWED_PATHS)) {
            return $next($request);
        }

        // ③ المستخدم super@tedallal.test حصراً هو المستثنى من وضع الصيانة
        /** @var \App\Models\User|null $user */
        $user = Auth::guard('sanctum')->user() ?? Auth::guard('web')->user() ?? Auth::user();
        if ($user && ($user->email === 'super@tedallal.test' || $user->canBypassMaintenance())) {
            return $next($request);
        }

        // ④ طلب API → JSON 503
        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'maintenance' => true,
                'message'     => 'الموقع تحت الصيانة حالياً. يرجى المحاولة لاحقاً.',
                'message_en'  => 'The site is currently under maintenance. Please try again later.',
            ], 503);
        }

        // ⑤ تنقل داخل تطبيق Inertia → إعادة توجيه كامل لصفحة الصيانة المستقلة
        if ($request->header('X-Inertia')) {
            return response('', 409)->header('X-Inertia-Location', $request->fullUrl());
        }

        // ⑥ تصفح ويب ومسح كود QR على الموبايل → صفحة صيانة مخصصة وسريعة تفتح على أي هاتف فوراً
        return response()->view('maintenance', [
            'isQrScan' => $request->is('t/*'),
        ], 200);
    }
}
