<?php

namespace App\Events;

use App\Models\TableServiceCall;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TableServiceAttended implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public TableServiceCall $serviceCall)
    {
        $this->serviceCall->loadMissing(['table']);
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
        return 'table.service_attended';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'service_call_id' => $this->serviceCall->id,
            'table_id' => $this->serviceCall->table_id,
            'status' => $this->serviceCall->status,
            'attended_at' => $this->serviceCall->attended_at?->toIso8601String(),
            'table' => [
                'id' => $this->serviceCall->table_id,
                'name' => $this->serviceCall->table?->name,
                'operational_stage' => $this->serviceCall->table?->operational_stage,
            ],
        ];
    }
}
