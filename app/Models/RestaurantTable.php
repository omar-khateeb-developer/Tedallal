<?php

namespace App\Models;

use App\Enums\TableStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Traits\HasTenant;

class RestaurantTable extends Model
{
    use HasFactory, HasTenant;

    protected $table = 'restaurant_tables';

    protected $fillable = [
        'restaurant_id',
        'name',
        'public_token',
        'status',
        'pos_x',
        'pos_y',
        'shape',
        'rotation',
        'capacity',
        'zone',
        'width',
        'height',
        'iot_device_id',
        'operational_stage',
        'stage_updated_at',
        'session_started_at',
        'bill_requested_at',
        'current_guest_name',
    ];

    protected function casts(): array
    {
        return [
            'status' => TableStatus::class,
            'pos_x' => 'float',
            'pos_y' => 'float',
            'rotation' => 'integer',
            'capacity' => 'integer',
            'width' => 'float',
            'height' => 'float',
            'stage_updated_at' => 'datetime',
            'session_started_at' => 'datetime',
            'bill_requested_at' => 'datetime',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(TableSession::class, 'table_id');
    }

    public function activeSession(): HasOne
    {
        return $this->hasOne(TableSession::class, 'table_id')
            ->where('status', 'active')
            ->latest('started_at');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'table_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(TableReservation::class, 'table_id');
    }

    public function waitlistEntries(): HasMany
    {
        return $this->hasMany(WaitlistEntry::class, 'assigned_table_id');
    }

    public function serviceCalls(): HasMany
    {
        return $this->hasMany(TableServiceCall::class, 'table_id');
    }

    public function activeServiceCall(): HasOne
    {
        return $this->hasOne(TableServiceCall::class, 'table_id')
            ->where('status', 'pending')
            ->latestOfMany();
    }

    public function getTurnMinutes(): int
    {
        if (! $this->session_started_at) {
            return 0;
        }

        return max(0, (int) $this->session_started_at->diffInMinutes(now()));
    }

    public function getStageMinutes(): int
    {
        if (! $this->stage_updated_at) {
            return 0;
        }

        return max(0, (int) $this->stage_updated_at->diffInMinutes(now()));
    }

    public function getBillWaitMinutes(): ?int
    {
        if (! $this->bill_requested_at) {
            return null;
        }

        return max(0, (int) $this->bill_requested_at->diffInMinutes(now()));
    }

    public function isActive(): bool
    {
        return $this->status === TableStatus::Active;
    }

    public function publicUrl(?string $baseUrl = null): string
    {
        if ($baseUrl) {
            return rtrim($baseUrl, '/') . '/t/' . $this->public_token;
        }

        // Priority 1: Configured QR base URL from config or env
        if ($configured = config('tedallal.qr_base_url') ?? env('QR_BASE_URL')) {
            return rtrim($configured, '/') . '/t/' . $this->public_token;
        }

        // Priority 2: In active HTTP web request, dynamically match user's host (e.g. 127.0.0.1:8000 or production domain)
        if (app()->runningInConsole() === false && request()->hasHeader('host')) {
            return request()->getSchemeAndHttpHost() . '/t/' . $this->public_token;
        }

        // Priority 3: Fallback to standard Laravel url helper
        return url('/t/' . $this->public_token);
    }

    /**
     * Scope a query to find a table by public token with case-insensitive and normalized fallback.
     */
    public function scopeWhereToken($query, string $token)
    {
        $clean = trim($token);
        return $query->where(function ($q) use ($clean) {
            $q->where('public_token', $clean)
              ->orWhereRaw('LOWER(public_token) = ?', [strtolower($clean)]);
        });
    }
}
