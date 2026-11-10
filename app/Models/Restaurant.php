<?php

namespace App\Models;

use App\Enums\RestaurantPlan;
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
        'plan',
        'windows_count',
        'logo',
        'phone',
        'address',
        'currency',
        'status',
        'subscription_expires_at',
        'navbar_image',
        'features',
    ];

    protected $attributes = [
        'currency' => 'SYP',
        'type' => 'postpaid',
        'plan' => 'basic',
    ];

    protected $appends = [
        'logo',
        'navbar_image',
        'plan_label',
        'effective_features',
        'is_expired',
        'days_remaining',
    ];

    protected function casts(): array
    {
        return [
            'status' => RestaurantStatus::class,
            'plan' => RestaurantPlan::class,
            'features' => 'array',
            'windows_count' => 'integer',
            'subscription_expires_at' => 'datetime',
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
                if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
                    return $value;
                }
                $clean = ltrim(parse_url($value, PHP_URL_PATH) ?? '', '/');
                $publicFile = public_path($clean);
                if (file_exists($publicFile) && is_file($publicFile)) {
                    return '/' . $clean;
                }
                return null;
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

    public function isBasic(): bool
    {
        return $this->plan === RestaurantPlan::Basic;
    }

    public function isPro(): bool
    {
        return $this->plan === RestaurantPlan::Pro;
    }

    public function isMax(): bool
    {
        return $this->plan === RestaurantPlan::Max;
    }

    public function isExpired(): bool
    {
        if (! $this->subscription_expires_at) {
            return false;
        }
        return $this->subscription_expires_at->isPast();
    }

    public function isSubscriptionActive(): bool
    {
        return $this->isActive() && ! $this->isExpired();
    }

    public function daysRemaining(): ?int
    {
        if (! $this->subscription_expires_at) {
            return null;
        }
        if ($this->subscription_expires_at->isPast()) {
            return 0;
        }
        return (int) now()->diffInDays($this->subscription_expires_at, false);
    }

    public function getPlanLabelAttribute(): string
    {
        return $this->planLabel();
    }

    public function planLabel(): string
    {
        return $this->plan?->label() ?? 'الأساسية (Basic)';
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->isExpired();
    }

    public function getDaysRemainingAttribute(): ?int
    {
        return $this->daysRemaining();
    }

    public function getEffectiveFeaturesAttribute(): array
    {
        return $this->computedFeatures();
    }

    public function computedFeatures(): array
    {
        $planDefaults = ($this->plan instanceof RestaurantPlan)
            ? $this->plan->features()
            : (RestaurantPlan::tryFrom((string) $this->plan)?->features() ?? RestaurantPlan::Basic->features());

        $custom = is_array($this->features) ? $this->features : [];
        return array_merge($planDefaults, $custom);
    }

    public function hasFeature(string $feature): bool
    {
        if ($this->isExpired()) {
            return false;
        }
        $features = $this->computedFeatures();
        return ! empty($features[$feature]);
    }

    public function hasAiWaiterFeature(): bool
    {
        return $this->hasFeature('ai_waiter');
    }

    public function hasOffersFeature(): bool
    {
        return $this->hasFeature('offers');
    }

    public function hasAnalyticsFeature(): bool
    {
        return $this->hasFeature('analytics');
    }

    public function hasWhatsappRemindersFeature(): bool
    {
        return $this->hasFeature('whatsapp_reminders');
    }

    public function hasMenuEngineeringFeature(): bool
    {
        return $this->hasFeature('menu_engineering');
    }
}
