<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\ApiController;
use App\Http\Middleware\ValidateTableSession;
use App\Models\PromoCode;
use App\Models\Setting;
use App\Services\CartService;
use Illuminate\Http\Request;

class PromoCodeController extends ApiController
{
    public function __construct(private readonly CartService $cartService)
    {
    }

    public function validateCode(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string', 'max:50'],
        ]);

        $session = ValidateTableSession::sessionFrom($request);
        $restaurant = $session->table->restaurant;

        $isTestingMode = Setting::isPromoTestingModeEnabled();

        if (empty($restaurant->features['offers'])) {
            return response()->json([
                'success' => false,
                'message' => 'نظام العروض غير مفعل في هذا المطعم.',
                'debug' => $isTestingMode ? [
                    'testing_mode' => true,
                    'reason_code' => 'feature_disabled',
                    'reason_message' => 'ميزة العروض والكوبونات غير مفعلة لهذا المطعم في المنصة.',
                ] : null,
            ], 400);
        }

        $promoCode = PromoCode::where('restaurant_id', $restaurant->id)
            ->where('code', trim($request->code))
            ->first();

        $summary = $this->cartService->summary($session);
        $subtotal = (float)$summary['subtotal'];
        $currency = $restaurant->currency ?? 'SYP';

        if (!$promoCode) {
            $message = "رمز الكوبون المدخل ({$request->code}) غير مسجل في هذا المطعم، يرجى التأكد من كتابة الرمز بشكل مطابق.";

            return response()->json([
                'success' => false,
                'message' => $message,
                'reason_code' => 'not_found',
                'debug' => $isTestingMode ? [
                    'testing_mode' => true,
                    'reason_code' => 'not_found',
                    'reason_message' => "الكود ({$request->code}) غير موجود بقاعدة بيانات مطعم {$restaurant->name}.",
                ] : null,
            ], 400);
        }

        $diagnostics = $promoCode->getValidationDiagnostics($subtotal, $currency);

        if (!$diagnostics['valid']) {
            return response()->json([
                'success' => false,
                'message' => $diagnostics['reason_message'],
                'reason_code' => $diagnostics['reason_code'],
                'debug' => $isTestingMode ? array_merge(['testing_mode' => true], $diagnostics) : [
                    'can_force_activate' => false,
                ],
            ], 400);
        }

        $discount = $promoCode->calculateDiscount($subtotal);

        return $this->success([
            'code' => $promoCode->code,
            'discount_amount' => $discount,
            'discount_type' => $promoCode->discount_type,
            'discount_value' => (float)$promoCode->discount_value,
            'debug' => $isTestingMode ? array_merge(['testing_mode' => true], $diagnostics) : null,
        ]);
    }

    /**
     * تفعيل الكوبون فوراً في وضع التجريب (لإتاحة اختباره المباشر في السلة)
     */
    public function forceActivate(Request $request)
    {
        if (!Setting::isPromoTestingModeEnabled()) {
            return response()->json([
                'success' => false,
                'message' => 'وضع التجريب غير مفعل.',
            ], 403);
        }

        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $session = ValidateTableSession::sessionFrom($request);
        $restaurant = $session->table->restaurant;

        $promo = PromoCode::where('restaurant_id', $restaurant->id)
            ->where('code', trim($request->code))
            ->firstOrFail();

        $promo->update([
            'starts_at' => now()->subMinute(),
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => "تم تفعيل الكوبون ({$promo->code}) فوراً للتجريب! يمكنك تطبيقه الآن.",
        ]);
    }
}
