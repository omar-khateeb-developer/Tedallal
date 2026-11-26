<?php

namespace App\Events;

use App\Http\Resources\RestaurantTableResource;
use App\Models\RestaurantTable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TableUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>  $tablePayload
     */
    public function __construct(
        public readonly int $restaurantId,
        public readonly array $tablePayload,
        public readonly bool $deleted = false,
    ) {}

    public static function dispatchSafe(?RestaurantTable $table): void
    {
        if (! $table) {
            return;
        }

        try {
            $fresh = RestaurantTable::withoutGlobalScopes()
                ->with(['activeSession.orders' => fn ($q) => $q->latest('id')->withCount('items')])
                ->find($table->id);

            if (! $fresh) {
                return;
            }

            broadcast(new self(
                (int) $fresh->restaurant_id,
                (new RestaurantTableResource($fresh))->resolve(),
                false,
            ));
        } catch (\Throwable $e) {
            Log::warning('Broadcast failed for TableUpdated: '.$e->getMessage(), [
                'table_id' => $table->id,
            ]);
        }
    }

    public static function dispatchDeletedSafe(int $restaurantId, int $tableId): void
    {
        try {
            broadcast(new self($restaurantId, ['id' => $tableId], true));
        } catch (\Throwable $e) {
            Log::warning('Broadcast failed for TableUpdated (deleted): '.$e->getMessage(), [
                'table_id' => $tableId,
            ]);
        }
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('restaurant.'.$this->restaurantId.'.orders'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'table.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'table' => $this->tablePayload,
            'deleted' => $this->deleted,
        ];
    }
}
