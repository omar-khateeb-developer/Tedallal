<?php

namespace App\Models;

use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RestaurantCustomer extends Model
{
    use HasFactory, HasTenant;

    protected $fillable = [
        'restaurant_id',
        'name',
        'phone',
        'visits_count',
        'notes',
        'last_visit_at',
    ];

    protected function casts(): array
    {
        return [
            'visits_count' => 'integer',
            'last_visit_at' => 'datetime',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function promoCodes(): HasMany
    {
        return $this->hasMany(PromoCode::class, 'customer_id')->latest();
    }

    public function wheelTokens(): HasMany
    {
        return $this->hasMany(OrderWheelToken::class, 'customer_id')->latest();
    }
}

