<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WaitlistEntry extends Model
{
    use HasFactory;

    protected $table = 'waitlist_entries';

    protected $fillable = [
        'uuid',
        'restaurant_id',
        'assigned_table_id',
        'guest_name',
        'guest_phone',
        'party_size',
        'seating_preference',
        'status',
        'queue_number',
        'estimated_wait_minutes',
        'notes',
        'notified_at',
        'seated_at',
        'cancelled_at',
    ];

    protected $casts = [
        'party_size' => 'integer',
        'queue_number' => 'integer',
        'estimated_wait_minutes' => 'integer',
        'notified_at' => 'datetime',
        'seated_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (WaitlistEntry $entry) {
            if (empty($entry->uuid)) {
                $entry->uuid = (string) Str::uuid();
            }
        });
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function assignedTable(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'assigned_table_id')->withoutGlobalScopes();
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['waiting', 'notified'])
            ->whereDate('created_at', now()->toDateString());
    }

    /**
     * Calculate how many parties are waiting ahead of this entry.
     */
    public function getQueuePosition(): int
    {
        if ($this->status !== 'waiting') {
            return 0;
        }

        return static::where('restaurant_id', $this->restaurant_id)
            ->where('status', 'waiting')
            ->whereDate('created_at', $this->created_at->toDateString())
            ->where('id', '<', $this->id)
            ->count() + 1;
    }

    /**
     * Get the public URL for the guest to monitor their place in line.
     */
    public function getTrackingUrl(): string
    {
        return url('/w/' . $this->uuid);
    }
}
