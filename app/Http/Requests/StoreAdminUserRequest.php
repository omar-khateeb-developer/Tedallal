<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

use App\Enums\UserRole;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreAdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $restaurant = app(\App\Services\RestaurantContext::class)->current();
        $maxWindows = max(1, (int) ($restaurant?->windows_count ?? 50));

        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
            'role' => ['required', Rule::in([
                UserRole::Admin->value,
                UserRole::Cashier->value,
                UserRole::Chef->value,
                UserRole::Supervisor->value,
                UserRole::Waiter->value,
                UserRole::Staff->value,
            ])],
            'default_window' => [
                Rule::requiredIf(fn () => $this->input('role') === UserRole::Chef->value),
                'nullable',
                'integer',
                'min:1',
                'max:' . $maxWindows,
            ],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ];
    }

    public function messages(): array
    {
        return [
            'default_window.required' => 'يجب تحديد رقم النافذة التابع لها الشيف.',
            'default_window.min' => 'رقم النافذة يجب أن يكون 1 على الأقل.',
            'default_window.max' => 'رقم النافذة يتجاوز عدد النوافذ المتاحة في المطعم.',
        ];
    }
}
