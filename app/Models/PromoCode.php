<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromoCode extends Model
{
    protected $fillable = [
        'restaurant_id',
        'code',
        'discount_type',
        'discount_value',
        'min_order_value',
        'is_active',
        'expires_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
        'discount_value' => 'decimal:2',
        'min_order_value' => 'decimal:2',
    ];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }
    
    public function isValid(?float $orderTotal = null): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        if ($orderTotal !== null && $orderTotal < $this->min_order_value) {
            return false;
        }

        return true;
    }

    public function calculateDiscount(float $orderTotal): float
    {
        if (!$this->isValid($orderTotal)) {
            return 0.0;
        }

        if ($this->discount_type === 'fixed') {
            return min((float) $this->discount_value, $orderTotal);
        }

        $calculated = round($orderTotal * ((float) $this->discount_value / 100), 2);

        return min($calculated, $orderTotal);
    }
}
