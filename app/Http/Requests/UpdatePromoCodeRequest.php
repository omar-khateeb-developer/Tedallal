<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
        $promoCodeId = $this->route('promoCode') ? $this->route('promoCode')->id : 'NULL';
        return [
            'code' => 'required|string|max:50|unique:promo_codes,code,' . $promoCodeId . ',id,restaurant_id,' . $restaurantId,
            'discount_type' => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0',
            'min_order_value' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
            'expires_at' => 'nullable|date',
        ];
    }
}
