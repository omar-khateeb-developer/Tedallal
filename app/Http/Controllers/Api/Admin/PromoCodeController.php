<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\StorePromoCodeRequest;
use App\Http\Requests\UpdatePromoCodeRequest;
use Illuminate\Http\Request;
use App\Models\PromoCode;

class PromoCodeController extends ApiController
{
    public function index(Request $request)
    {
        $restaurant = auth()->user()->restaurant;
        
        if (empty($restaurant->features['offers'])) {
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
        
        if (empty($restaurant->features['offers'])) {
            return $this->error('Feature Locked', 403);
        }

        $validated = $request->validated();

        $validated['restaurant_id'] = $restaurant->id;
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
