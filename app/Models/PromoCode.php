<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasTenant;

class PromoCode extends Model
{
    use HasTenant;
    protected $fillable = [
        'restaurant_id',
        'code',
        'discount_type',
        'discount_value',
        'min_order_value',
        'is_active',
        'starts_at',
        'is_single_use',
        'times_used',
        'expires_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_single_use' => 'boolean',
        'times_used' => 'integer',
        'starts_at' => 'datetime',
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

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->is_single_use && $this->times_used >= 1) {
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
            return min($this->discount_value, $orderTotal);
        }

        return round($orderTotal * ($this->discount_value / 100), 2);
    }
}
