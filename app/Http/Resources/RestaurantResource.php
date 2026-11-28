<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Restaurant */
class RestaurantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'type' => $this->type ?? 'postpaid',
            'windows_count' => (int) ($this->windows_count ?? 1),
            'logo' => $this->logo,
            'navbar_image' => $this->navbar_image,
            'phone' => $this->phone,
            'address' => $this->address,
            'currency' => $this->currency,
            'auto_print_trigger' => $this->autoPrintTrigger(),
            'auto_print_on_ready' => $this->shouldAutoPrintOnReady(),
            'accounting_mapping' => array_merge([
                'cash_account_code' => '10101',
                'cash_account_name' => 'الصندوق الرئيسي',
                'dine_in_sales_code' => '40101',
                'dine_in_sales_name' => 'مبيعات الصالة',
                'takeaway_sales_code' => '40102',
                'takeaway_sales_name' => 'مبيعات السفري',
                'discount_account_code' => '40201',
                'discount_account_name' => 'حسميات وعروض ترويجية',
                'tax_account_code' => '20301',
                'tax_account_name' => 'أمانات الضرائب والرسوم',
            ], (array) (($this->features ?? [])['accounting_mapping'] ?? [])),
        ];
    }
}
