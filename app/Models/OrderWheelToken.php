<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderWheelToken extends Model
{
    protected $fillable = [
        'restaurant_id',
        'order_id',
        'token',
        'is_claimed',
        'claimed_at',
        'won_promo_code_id',
        'generated_promo_code_id',
        'expires_at',
    ];

    protected $casts = [
        'is_claimed' => 'boolean',
        'claimed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function wonPromoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class, 'won_promo_code_id');
    }

    public function generatedPromoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class, 'generated_promo_code_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
