<?php

namespace App\Http\Resources;

use App\Models\RestaurantTable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\RestaurantTable */
class RestaurantTableResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $qrPath = "qr/{$this->restaurant_id}/{$this->public_token}.png";
        $isPublicRequest = $request->is('api/public/*');

        $activeSession = null;
        $latestOrder = null;

        if (! $isPublicRequest) {
            $activeSession = $this->activeSession;
            if ($activeSession) {
                $order = $activeSession->relationLoaded('orders')
                    ? $activeSession->orders->sortByDesc('id')->first()
                    : $activeSession->orders()->withCount('items')->latest('id')->first();

                if ($order) {
                    $latestOrder = [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'status' => $order->status->value,
                        'total' => (float) $order->total,
                        'items_count' => $order->items_count ?? ($order->relationLoaded('items') ? $order->items->count() : $order->items()->count()),
                        'created_at' => $order->created_at?->toIso8601String(),
                    ];
                }
            }
        }

        $stage = $this->operational_stage ?: 'available';
        if ($stage === 'available' && $activeSession) {
            $stage = 'seated';
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'public_token' => $this->public_token,
            'status' => $this->status,
            'table_type' => $this->table_type ?? RestaurantTable::TYPE_DINE_IN,
            'pos_x' => (float) ($this->pos_x ?? 50),
            'pos_y' => (float) ($this->pos_y ?? 50),
            'shape' => $this->shape ?? 'circle',
            'rotation' => (int) ($this->rotation ?? 0),
            'capacity' => (int) ($this->capacity ?? 4),
            'zone' => $this->zone ?? 'main',
            'width' => (float) ($this->width ?? 90),
            'height' => (float) ($this->height ?? 90),
            'iot_device_id' => $this->iot_device_id,
            'operational_stage' => $stage,
            'stage_updated_at' => $this->stage_updated_at?->toIso8601String(),
            'session_started_at' => $isPublicRequest ? null : $this->session_started_at?->toIso8601String(),
            'bill_requested_at' => $isPublicRequest ? null : $this->bill_requested_at?->toIso8601String(),
            'current_guest_name' => $isPublicRequest ? null : $this->current_guest_name,
            'turn_minutes' => $this->getTurnMinutes(),
            'stage_minutes' => $this->getStageMinutes(),
            'bill_wait_minutes' => $this->getBillWaitMinutes(),
            'active_session' => $activeSession ? [
                'id' => $activeSession->id,
                'started_at' => $activeSession->started_at?->toIso8601String(),
            ] : null,
            'active_order' => $latestOrder,
            'public_url' => $this->publicUrl(),
            'qr_url' => "/storage/{$qrPath}",
            'restaurant' => new RestaurantResource($this->whenLoaded('restaurant')),
        ];
    }
}
