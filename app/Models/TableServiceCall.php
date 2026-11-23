<?php

namespace App\Models;

use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TableServiceCall extends Model
{
    use HasFactory, HasTenant;

    protected $table = 'table_service_calls';

    protected $fillable = [
        'restaurant_id',
        'table_id',
        'type',
        'status',
        'payment_method',
        'reason',
        'notes',
        'attended_at',
        'attended_by',
    ];

    protected function casts(): array
    {
        return [
            'attended_at' => 'datetime',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'table_id');
    }

    public function attendedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attended_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function markAsAttended(?int $userId = null): void
    {
        $this->update([
            'status' => 'attended',
            'attended_at' => now(),
            'attended_by' => $userId,
        ]);
    }
}
