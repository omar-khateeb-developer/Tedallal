<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateAdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user') ? $this->route('user')->id : null;
        $restaurant = app(\App\Services\RestaurantContext::class)->current();
        $maxWindows = max(1, (int) ($restaurant?->windows_count ?? 50));

        $targetUser = $this->route('user');
        $effectiveRole = $this->input('role') ?? $targetUser?->roles->first()?->name;

        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['nullable', Password::defaults()],
            'role' => ['sometimes', Rule::in([
                UserRole::Admin->value,
                UserRole::Cashier->value,
                UserRole::Chef->value,
                UserRole::Supervisor->value,
                UserRole::Waiter->value,
                UserRole::Staff->value,
            ])],
            'default_window' => [
                Rule::requiredIf(fn () => $effectiveRole === UserRole::Chef->value),
                'nullable',
                'integer',
                'min:1',
                'max:' . $maxWindows,
            ],
            'status' => ['sometimes', Rule::in([UserStatus::Active->value, UserStatus::Inactive->value])],
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
