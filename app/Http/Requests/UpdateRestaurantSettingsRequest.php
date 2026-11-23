<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRestaurantSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'windows_count' => ['nullable', 'integer', 'min:1', 'max:50'],
            'navbar_image' => ['nullable', 'image', 'max:2048'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'auto_print_on_ready' => ['nullable', 'boolean'],
            'accounting_mapping' => ['nullable', 'array'],
            'accounting_mapping.cash_account_code' => ['nullable', 'string', 'max:50'],
            'accounting_mapping.cash_account_name' => ['nullable', 'string', 'max:100'],
            'accounting_mapping.dine_in_sales_code' => ['nullable', 'string', 'max:50'],
            'accounting_mapping.dine_in_sales_name' => ['nullable', 'string', 'max:100'],
            'accounting_mapping.takeaway_sales_code' => ['nullable', 'string', 'max:50'],
            'accounting_mapping.takeaway_sales_name' => ['nullable', 'string', 'max:100'],
            'accounting_mapping.discount_account_code' => ['nullable', 'string', 'max:50'],
            'accounting_mapping.discount_account_name' => ['nullable', 'string', 'max:100'],
            'accounting_mapping.tax_account_code' => ['nullable', 'string', 'max:50'],
            'accounting_mapping.tax_account_name' => ['nullable', 'string', 'max:100'],
        ];
    }
}
