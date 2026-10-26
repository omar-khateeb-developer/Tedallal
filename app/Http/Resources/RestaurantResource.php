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
        ];
    }
}
