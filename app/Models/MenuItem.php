<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\HasTenant;

class MenuItem extends Model
{
    use HasFactory, HasTenant;
    protected $fillable = [
        'restaurant_id',
        'category_id',
        'name',
        'description',
        'image',
        'price',
        'sort_order',
        'is_available',
        'is_active',
    ];

    protected $appends = ['image'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'sort_order' => 'integer',
            'is_available' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected function image(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: function (?string $value) {
                if (! $value) return null;
                if (str_starts_with($value, 'http')) {
                    $value = parse_url($value, PHP_URL_PATH);
                }
                return asset($value);
            },
        );
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'category_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function isOrderable(): bool
    {
        return $this->is_active && $this->is_available;
    }

    public function scopeOrderable($query)
    {
        return $query->where('is_active', true)->where('is_available', true);
    }
}
