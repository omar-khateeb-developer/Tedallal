<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Api\ApiController;
use App\Models\PromoCode;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromoTestingController extends ApiController
{
    /**
     * حالة وضع التجريب الحالية.
     */
    public function status(): JsonResponse
    {
        return $this->success([
            'testing_mode' => Setting::isPromoTestingModeEnabled(),
        ]);
    }

    /**
     * تفعيل أو إيقاف وضع التجريب.
     */
    public function toggle(Request $request): JsonResponse
    {
        $request->validate([
            'enabled' => 'required|boolean',
        ]);

        $enabled = $request->boolean('enabled');
        Setting::set('promo_testing_mode', $enabled ? '1' : '0');

        return $this->success([
            'testing_mode' => $enabled,
            'message' => $enabled
                ? 'تم تفعيل وضع التجريب وفحص الكوبونات. سيتم الآن إظهار أسباب الرفض التفصيلية في نوافذ الطلب.'
                : 'تم إيقاف وضع التجريب. عادت رسائل الكوبونات إلى الوضع الافتراضي للزبائن.',
        ]);
    }

    /**
     * أداة تشخيص وفحص أي كوبون مباشرة لمدير المنصة.
     */
    public function diagnose(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
            'restaurant_id' => 'required|exists:restaurants,id',
            'cart_total' => 'nullable|numeric|min:0',
        ]);

        $restaurant = Restaurant::findOrFail($request->restaurant_id);
        $cartTotal = $request->filled('cart_total') ? (float)$request->cart_total : 100.0;
        $currency = $restaurant->currency ?? 'SYP';

        $hasOffersFeature = !empty($restaurant->features['offers']);

        $promo = PromoCode::where('restaurant_id', $restaurant->id)
            ->where('code', trim($request->code))
            ->first();

        // طاولة تجريبية لأخذ رابط السلة المباشر
        $sampleTable = RestaurantTable::where('restaurant_id', $restaurant->id)->first();
        $testUrl = $sampleTable ? url("/t/{$sampleTable->public_token}/cart") : null;

        if (!$hasOffersFeature) {
            return $this->success([
                'exists' => (bool)$promo,
                'has_offers_feature' => false,
                'diagnostics' => [
                    'valid' => false,
                    'reason_code' => 'feature_disabled',
                    'reason_message' => "ميزة العروض والكوبونات غير مفعلة لهذا المطعم ({$restaurant->name}) في باقة المطعم.",
                    'can_force_activate' => false,
                ],
                'restaurant' => [
                    'id' => $restaurant->id,
                    'name' => $restaurant->name,
                    'currency' => $currency,
                ],
                'test_url' => $testUrl,
            ]);
        }

        if (!$promo) {
            return $this->success([
                'exists' => false,
                'has_offers_feature' => true,
                'diagnostics' => [
                    'valid' => false,
                    'reason_code' => 'not_found',
                    'reason_message' => "الكود ({$request->code}) غير موجود إطلاقاً في سجل كوبونات مطعم: {$restaurant->name}.",
                    'can_force_activate' => false,
                ],
                'restaurant' => [
                    'id' => $restaurant->id,
                    'name' => $restaurant->name,
                    'currency' => $currency,
                ],
                'test_url' => $testUrl,
            ]);
        }

        $diagnostics = $promo->getValidationDiagnostics($cartTotal, $currency);

        return $this->success([
            'exists' => true,
            'has_offers_feature' => true,
            'diagnostics' => $diagnostics,
            'promo' => [
                'id' => $promo->id,
                'code' => $promo->code,
                'discount_type' => $promo->discount_type,
                'discount_value' => (float)$promo->discount_value,
                'min_order_value' => (float)$promo->min_order_value,
                'is_active' => (bool)$promo->is_active,
                'is_single_use' => (bool)$promo->is_single_use,
                'times_used' => (int)$promo->times_used,
                'starts_at' => $promo->starts_at?->format('Y-m-d h:i A'),
                'expires_at' => $promo->expires_at?->format('Y-m-d h:i A'),
            ],
            'restaurant' => [
                'id' => $restaurant->id,
                'name' => $restaurant->name,
                'currency' => $currency,
            ],
            'test_url' => $testUrl,
        ]);
    }

    /**
     * تفعيل الكوبون فورياً للاستخدام في التجريب (تجاوز فترة الانتظار ساعتين).
     */
    public function forceActivate(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
            'restaurant_id' => 'required|exists:restaurants,id',
        ]);

        $promo = PromoCode::where('restaurant_id', $request->restaurant_id)
            ->where('code', trim($request->code))
            ->firstOrFail();

        $promo->update([
            'starts_at' => now()->subMinute(),
            'is_active' => true,
        ]);

        return $this->success([
            'message' => "تم تفعيل الكوبون ({$promo->code}) فوراً بنجاح! يمكنك الآن تطبيقه وتجربته فوراً.",
            'promo' => $promo->fresh(),
        ]);
    }
}

