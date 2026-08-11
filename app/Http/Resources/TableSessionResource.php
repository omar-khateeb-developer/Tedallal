<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\TableSession */
class TableSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'session_token' => $this->session_token,
            'status' => $this->status,
            'started_at' => $this->started_at?->toIso8601String(),
            'table' => new RestaurantTableResource($this->whenLoaded('table')),
        ];
    }
}
