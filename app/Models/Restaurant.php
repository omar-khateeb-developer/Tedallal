<?php

namespace App\Models;

use App\Enums\RestaurantStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Restaurant extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'slug',
        'type',
        'windows_count',
        'logo',
        'phone',
        'address',
        'currency',
        'status',
        'navbar_image',
        'features',
    ];

    protected $attributes = [
        'currency' => 'SYP',
        'type' => 'postpaid',
    ];

    protected $appends = [
        'logo',
        'navbar_image',
    ];

    protected function casts(): array
    {
        return [
            'status' => RestaurantStatus::class,
            'features' => 'array',
            'windows_count' => 'integer',
        ];
    }

    public function isPrepaid(): bool
    {
        return ($this->type ?? 'postpaid') === 'prepaid';
    }

    public function isPostpaid(): bool
    {
        return ($this->type ?? 'postpaid') === 'postpaid';
    }

    protected function logo(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: function (?string $value) {
                if (! $value) return null;
                if (str_starts_with($value, 'http')) {
                    $value = parse_url($value, PHP_URL_PATH);
                }
                return '/' . ltrim($value, '/');
            },
        );
    }

    protected function navbarImage(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: function (?string $value) {
                if (! $value) return null;
                if (str_starts_with($value, 'http')) {
                    $value = parse_url($value, PHP_URL_PATH);
                }
                return '/' . ltrim($value, '/');
            },
        );
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function tables(): HasMany
    {
        return $this->hasMany(RestaurantTable::class);
    }

    public function menuCategories(): HasMany
    {
        return $this->hasMany(MenuCategory::class);
    }

    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function isActive(): bool
    {
        return $this->status === RestaurantStatus::Active;
    }

    public function hasAiWaiterFeature(): bool
    {
        return !empty($this->features['ai_waiter']);
    }

    public function hasOffersFeature(): bool
    {
        return !empty($this->features['offers']);
    }

    public function hasAnalyticsFeature(): bool
    {
        return !empty($this->features['analytics']);
    }
}
