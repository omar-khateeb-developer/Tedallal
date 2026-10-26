<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\HasTenant;

class Order extends Model
{
    use HasTenant;

    protected $fillable = [
        'restaurant_id',
        'table_id',
        'table_session_id',
        'order_number',
        'status',
        'service_type',
        'pickup_window',
        'paid_at',
        'paid_by',
        'subtotal',
        'total',
        'customer_note',
        'promo_code_id',
        'discount_amount',
        'accepted_at',
        'preparing_at',
        'ready_at',
        'served_at',
        'cancelled_at',
    ];

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    protected function casts(): array
    {
        return [
            'status'        => OrderStatus::class,
            'pickup_window' => 'integer',
            'subtotal'      => 'decimal:2',
            'total'         => 'decimal:2',
            'paid_at'       => 'datetime',
            'accepted_at'   => 'datetime',
            'preparing_at'  => 'datetime',
            'ready_at'      => 'datetime',
            'served_at'     => 'datetime',
            'cancelled_at'  => 'datetime',
        ];
    }

    /** تسجيل وقت الحالة تلقائياً عند التغيير */
    public static function statusTimestampFor(OrderStatus $status): ?string
    {
        return match ($status) {
            OrderStatus::Accepted   => 'accepted_at',
            OrderStatus::Preparing  => 'preparing_at',
            OrderStatus::Ready      => 'ready_at',
            OrderStatus::Served     => 'served_at',
            OrderStatus::Cancelled  => 'cancelled_at',
            default                 => null,
        };
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'table_id');
    }

    public function tableSession(): BelongsTo
    {
        return $this->belongsTo(TableSession::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
