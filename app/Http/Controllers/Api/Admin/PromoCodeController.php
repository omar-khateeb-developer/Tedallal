<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
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

        $codes = PromoCode::where('restaurant_id', $restaurant->id)->latest()->get();
        return $this->success($codes);
    }

    public function store(Request $request)
    {
        $restaurant = auth()->user()->restaurant;
        
        if (empty($restaurant->features['offers'])) {
            return $this->error('Feature Locked', 403);
        }

        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:promo_codes,code,NULL,id,restaurant_id,' . $restaurant->id,
            'discount_type' => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0',
            'min_order_value' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
            'expires_at' => 'nullable|date',
        ]);

        $validated['restaurant_id'] = $restaurant->id;
        $promoCode = PromoCode::create($validated);

        return $this->success($promoCode, 201);
    }

    public function update(Request $request, PromoCode $promoCode)
    {
        $restaurant = auth()->user()->restaurant;

        if ($promoCode->restaurant_id !== $restaurant->id) {
            return $this->error('Unauthorized', 403);
        }

        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:promo_codes,code,' . $promoCode->id . ',id,restaurant_id,' . $restaurant->id,
            'discount_type' => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0',
            'min_order_value' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
            'expires_at' => 'nullable|date',
        ]);

        $promoCode->update($validated);

        return $this->success($promoCode);
    }

    public function destroy(PromoCode $promoCode)
    {
        $restaurant = auth()->user()->restaurant;

        if ($promoCode->restaurant_id !== $restaurant->id) {
            return $this->error('Unauthorized', 403);
        }

        $promoCode->delete();
        return $this->success([]);
    }
}
