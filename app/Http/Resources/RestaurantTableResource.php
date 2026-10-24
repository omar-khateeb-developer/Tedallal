<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\RestaurantTable */
class RestaurantTableResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $qrPath = "qr/{$this->restaurant_id}/{$this->public_token}.png";
        if (! \Illuminate\Support\Facades\Storage::disk('public')->exists($qrPath)) {
            app(\App\Services\QrCodeService::class)->generateForTable($this->resource);
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'public_token' => $this->public_token,
            'status' => $this->status,
            'public_url' => $this->publicUrl(),
            'qr_url' => "/storage/{$qrPath}",
            'restaurant' => new RestaurantResource($this->whenLoaded('restaurant')),
        ];
    }
}
