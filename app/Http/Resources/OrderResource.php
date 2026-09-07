<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Order */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'order_number'  => $this->order_number,
            'status'        => $this->status,
            'status_label'  => $this->status->label(),
            'subtotal'      => $this->subtotal,
            'total'         => $this->total,
            'customer_note' => $this->customer_note,
            'created_at'    => $this->created_at?->toIso8601String(),
            'accepted_at'   => $this->accepted_at?->toIso8601String(),
            'preparing_at'  => $this->preparing_at?->toIso8601String(),
            'ready_at'      => $this->ready_at?->toIso8601String(),
            'served_at'     => $this->served_at?->toIso8601String(),
            'cancelled_at'  => $this->cancelled_at?->toIso8601String(),
            'table'         => new RestaurantTableResource($this->whenLoaded('table')),
            'items'         => OrderItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
