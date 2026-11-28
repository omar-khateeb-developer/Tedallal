<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMenuItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $restaurantId = $this->user()?->restaurant_id;

        return [
            'category_id' => [
                'sometimes',
                Rule::exists('menu_categories', 'id')->where(fn ($q) => $q->where('restaurant_id', $restaurantId)),
            ],
            'name' => ['sometimes', 'string', 'max:150'],
            'external_sku' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:10240'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_available' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator) {
            if (! $this->exists('compare_at_price')) {
                return;
            }

            $rawCompareAt = $this->input('compare_at_price');
            if ($rawCompareAt === null || $rawCompareAt === '') {
                return;
            }

            /** @var \App\Models\MenuItem|null $item */
            $item = $this->route('item');
            $effectivePrice = $this->filled('price')
                ? (float) $this->input('price')
                : (float) ($item?->price ?? 0);

            if ((float) $rawCompareAt <= $effectivePrice) {
                $validator->errors()->add(
                    'compare_at_price',
                    'السعر قبل الخصم (المشطوب) يجب أن يكون أكبر من السعر الفعلي الحالي.'
                );
            }
        });
    }
}
