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
            'pos_x' => (float) ($this->pos_x ?? 50),
            'pos_y' => (float) ($this->pos_y ?? 50),
            'shape' => $this->shape ?? 'circle',
            'rotation' => (int) ($this->rotation ?? 0),
            'capacity' => (int) ($this->capacity ?? 4),
            'zone' => $this->zone ?? 'main',
            'width' => (float) ($this->width ?? 90),
            'height' => (float) ($this->height ?? 90),
            'iot_device_id' => $this->iot_device_id,
            'public_url' => $this->publicUrl(),
            'qr_url' => "/storage/{$qrPath}",
            'restaurant' => new RestaurantResource($this->whenLoaded('restaurant')),
        ];
    }
}
