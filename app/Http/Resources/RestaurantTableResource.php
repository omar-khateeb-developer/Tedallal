<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\RestaurantTable */
class RestaurantTableResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'public_token' => $this->public_token,
            'status' => $this->status,
            'public_url' => $this->publicUrl(),
            'qr_url' => asset("storage/qr/{$this->restaurant_id}/{$this->public_token}.png"),
            'restaurant' => new RestaurantResource($this->whenLoaded('restaurant')),
        ];
    }
}
