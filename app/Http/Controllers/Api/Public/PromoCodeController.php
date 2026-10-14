<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\ApiController;
use App\Http\Middleware\ValidateTableSession;
use Illuminate\Http\Request;
use App\Models\PromoCode;
use App\Services\CartService;

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

        if (! $session || ! $session->table || ! $session->table->restaurant) {
            return $this->error('جلسة الطاولة غير صالحة.', 401);
        }

        $restaurant = $session->table->restaurant;

        if (empty($restaurant->features['offers'])) {
            return $this->error('نظام العروض غير مفعل في هذا المطعم.', 400);
        }

        $promoCode = PromoCode::where('restaurant_id', $restaurant->id)
            ->where('code', $request->code)
            ->first();

        $summary = $this->cartService->summary($session);
        $subtotal = $summary['subtotal'];

        if (!$promoCode || !$promoCode->isValid((float)$subtotal)) {
            return $this->error('الكوبون غير صالح أو لا يغطي الحد الأدنى للطلب.', 400);
        }

        $discount = $promoCode->calculateDiscount((float)$subtotal);

        return $this->success([
            'code' => $promoCode->code,
            'discount_amount' => $discount,
            'discount_type' => $promoCode->discount_type,
            'discount_value' => (float)$promoCode->discount_value,
        ]);
    }
}
