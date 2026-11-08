<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\StorePromoCodeRequest;
use App\Http\Requests\UpdatePromoCodeRequest;
use Illuminate\Http\Request;
use App\Models\PromoCode;
use Illuminate\Support\Str;

class PromoCodeController extends ApiController
{
    public function index(Request $request)
    {
        $restaurant = auth()->user()->restaurant;
        
        if (!$restaurant->hasOffersFeature()) {
            return $this->error('Feature Locked', 403);
        }

        $query = PromoCode::latest();

        if ($request->query('type') === 'wheel') {
            $query->where('is_single_use', true);
        } elseif ($request->query('type') === 'all') {
            // return all
        } else {
            // default: master coupons
            $query->where('is_single_use', false);
        }

        $codes = $query->get();

        $stats = [
            'active_master_count' => PromoCode::where('restaurant_id', $restaurant->id)->where('is_single_use', false)->where('is_active', true)->count(),
            'total_wheel_vouchers' => PromoCode::where('restaurant_id', $restaurant->id)->where('is_single_use', true)->count(),
            'used_wheel_vouchers' => PromoCode::where('restaurant_id', $restaurant->id)->where('is_single_use', true)->where('times_used', '>', 0)->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $codes,
            'stats' => $stats,
        ]);
    }

    public function store(StorePromoCodeRequest $request)
    {
        $restaurant = auth()->user()->restaurant;
        
        if (!$restaurant->hasOffersFeature()) {
            return $this->error('Feature Locked', 403);
        }

        $validated = $request->validated();
        $validated['restaurant_id'] = $restaurant->id;

        if (empty($validated['code'])) {
            do {
                $validated['code'] = 'OFFER-' . strtoupper(Str::random(6));
            } while (PromoCode::withoutGlobalScopes()->where('restaurant_id', $restaurant->id)->where('code', $validated['code'])->exists());
        }

        // تطبيق حد الأمان الذكي للحد الأدنى للفاتورة إذا لم يحدده المدير
        if (!isset($validated['min_order_value']) || $validated['min_order_value'] === null || $validated['min_order_value'] === '') {
            if ($validated['discount_type'] === 'fixed') {
                // للخصم الثابت: حد الأمان الافتراضي هو 3 أضعاف قيمة الخصم لمنع الفواتير الصفرية
                $validated['min_order_value'] = round((float) $validated['discount_value'] * 3, 2);
            } else {
                $validated['min_order_value'] = 0;
            }
        }

        $promoCode = PromoCode::create($validated);

        return $this->success($promoCode, 201);
    }

    public function update(UpdatePromoCodeRequest $request, $promoCode)
    {
        $restaurant = auth()->user()->restaurant;

        if (!($promoCode instanceof PromoCode) || !$promoCode->exists) {
            $id = (is_object($promoCode) && !empty($promoCode->id))
                ? $promoCode->id
                : ($request->route('promoCode') ?? $request->route('promo_code'));
            $promoCode = PromoCode::where('restaurant_id', $restaurant->id)->findOrFail($id);
        } elseif ($promoCode->restaurant_id !== $restaurant->id) {
            return $this->error('Unauthorized', 403);
        }

        $validated = $request->validated();

        if (array_key_exists('min_order_value', $validated) && ($validated['min_order_value'] === null || $validated['min_order_value'] === '')) {
            $type = $validated['discount_type'] ?? $promoCode->discount_type;
            $value = $validated['discount_value'] ?? $promoCode->discount_value;
            $validated['min_order_value'] = ($type === 'fixed') ? round((float) $value * 3, 2) : 0;
        }

        $promoCode->update($validated);

        return $this->success($promoCode);
    }

    public function destroy($promoCode)
    {
        $restaurant = auth()->user()->restaurant;

        if (!($promoCode instanceof PromoCode) || !$promoCode->exists) {
            $id = (is_object($promoCode) && !empty($promoCode->id))
                ? $promoCode->id
                : (request()->route('promoCode') ?? request()->route('promo_code'));
            $promoCode = PromoCode::where('restaurant_id', $restaurant->id)->findOrFail($id);
        } elseif ($promoCode->restaurant_id !== $restaurant->id) {
            return $this->error('Unauthorized', 403);
        }

        $promoCode->delete();
        return $this->success([]);
    }
}
