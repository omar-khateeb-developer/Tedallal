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

        $codes = PromoCode::latest()->get();
        return $this->success($codes);
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

    public function update(UpdatePromoCodeRequest $request, PromoCode $promoCode)
    {
        $restaurant = auth()->user()->restaurant;

        if ($promoCode->restaurant_id !== $restaurant->id) {
            return $this->error('Unauthorized', 403);
        }

        $validated = $request->validated();

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
