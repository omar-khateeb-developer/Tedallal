<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TableReservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'restaurant_id',
        'table_id',
        'guest_name',
        'guest_phone',
        'guest_email',
        'reservation_date',
        'time_slot',
        'guests_count',
        'special_requests',
        'status',
        'otp_code',
        'otp_expires_at',
        'otp_verified_at',
        'deposit_amount',
        'deposit_status',
        'confirmed_by_staff_id',
        'staff_notes',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'reservation_date' => 'date',
        'guests_count' => 'integer',
        'deposit_amount' => 'decimal:2',
        'otp_expires_at' => 'datetime',
        'otp_verified_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (TableReservation $reservation) {
            if (empty($reservation->uuid)) {
                $reservation->uuid = (string) Str::uuid();
            }
        });
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'table_id')->withoutGlobalScopes();
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_staff_id');
    }

    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereIn('status', ['confirmed', 'seated'])
              ->orWhere(function ($sub) {
                  $sub->where('status', 'pending_verification')
                      ->where('otp_expires_at', '>', now());
              });
        });
    }

    public function scopeForDate($query, $date)
    {
        return $query->where('reservation_date', $date);
    }

    public function isExpired(): bool
    {
        if ($this->status === 'pending_verification') {
            return $this->otp_expires_at && $this->otp_expires_at->isPast();
        }
        return false;
    }

    public function getFormattedCode(): string
    {
        return 'VIP-' . strtoupper(substr(str_replace('-', '', $this->uuid), 0, 8));
    }
}
