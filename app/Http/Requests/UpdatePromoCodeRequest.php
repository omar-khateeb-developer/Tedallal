<?php

namespace App\Http\Requests;

use App\Models\PromoCode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePromoCodeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $restaurantId = auth()->user()->restaurant_id;
        $promoCode = $this->route('promoCode') ?? $this->route('promo_code');
        $promoCodeId = $promoCode instanceof PromoCode ? $promoCode->id : $promoCode;

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('promo_codes', 'code')
                    ->where('restaurant_id', $restaurantId)
                    ->ignore($promoCodeId),
            ],
            'discount_type' => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0',
            'min_order_value' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
            'expires_at' => 'nullable|date',
        ];
    }
}
