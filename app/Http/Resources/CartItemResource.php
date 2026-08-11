<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CartItem */
class CartItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'menu_item_id' => $this->menu_item_id,
            'quantity' => $this->quantity,
            'note' => $this->note,
            'menu_item' => new MenuItemResource($this->whenLoaded('menuItem')),
        ];
    }
}
