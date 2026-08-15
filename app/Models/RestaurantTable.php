<?php

namespace App\Models;

use App\Enums\TableStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RestaurantTable extends Model
{
    use HasFactory;

    protected $table = 'restaurant_tables';

    protected $fillable = [
        'restaurant_id',
        'name',
        'public_token',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => TableStatus::class,
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

    public function isActive(): bool
    {
        return $this->status === TableStatus::Active;
    }

    public function publicUrl(): string
    {
        return url('/t/'.$this->public_token);
    }
}
