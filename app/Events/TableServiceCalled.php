<?php

namespace App\Events;

use App\Models\TableServiceCall;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TableServiceCalled implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public TableServiceCall $serviceCall)
    {
        $this->serviceCall->loadMissing(['table', 'restaurant']);
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('restaurant.' . $this->serviceCall->restaurant_id . '.orders'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'table.service_called';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'service_call' => [
                'id' => $this->serviceCall->id,
                'table_id' => $this->serviceCall->table_id,
                'table_name' => $this->serviceCall->table?->name ?? 'طاولة',
                'type' => $this->serviceCall->type,
                'status' => $this->serviceCall->status,
                'payment_method' => $this->serviceCall->payment_method,
                'reason' => $this->serviceCall->reason,
                'notes' => $this->serviceCall->notes,
                'created_at' => $this->serviceCall->created_at?->toIso8601String(),
            ],
            'table' => [
                'id' => $this->serviceCall->table_id,
                'name' => $this->serviceCall->table?->name,
                'operational_stage' => $this->serviceCall->table?->operational_stage,
                'bill_requested_at' => $this->serviceCall->table?->bill_requested_at?->toIso8601String(),
            ],
        ];
    }
}
