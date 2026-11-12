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

    public function customers(): HasMany
    {
        return $this->hasMany(RestaurantCustomer::class);
    }

    public function shouldAutoPrintOnReady(): bool
    {
        return (bool) (($this->features ?? [])['auto_print_on_ready'] ?? false);
    }

    public function shouldRequireWheelWhatsapp(): bool
    {
        if (! $this->hasWhatsappRemindersFeature()) {
            return false;
        }
        return (bool) (($this->features ?? [])['wheel_require_whatsapp'] ?? true);
    }

    public function shouldRevealWheelCodeAfterSave(): bool
    {
        return (bool) (($this->features ?? [])['wheel_reveal_code_after_save'] ?? false);
    }

    /**
     * Resolve structured loyalty milestone settings for this restaurant.
     *
     * @return array{enabled: bool, target_visits: int, reward_type: string, reward_value: float, menu_item_id: ?int, reward_label: string, monetary_value: float}
     */
    public function loyaltyConfig(): array
    {
        $f = $this->features ?? [];
        $enabled = $this->hasWhatsappRemindersFeature() && (bool) ($f['loyalty_enabled'] ?? false);
        $targetVisits = max(2, min(50, (int) ($f['loyalty_target_visits'] ?? 5)));
        $rewardType = in_array($f['loyalty_reward_type'] ?? '', ['percentage', 'fixed', 'free_item'], true)
            ? $f['loyalty_reward_type']
            : 'percentage';
        $rewardValue = (float) ($f['loyalty_reward_value'] ?? 50);
        $menuItemId = ! empty($f['loyalty_menu_item_id']) ? (int) $f['loyalty_menu_item_id'] : null;

        $currency = ($this->currency === 'SYP' || strtoupper((string) $this->currency) === 'SYP')
            ? 'ل.س'
            : ($this->currency ?? 'ل.س');

        $rewardLabel = '';
        $monetaryValue = $rewardValue;

        if ($rewardType === 'free_item' && $menuItemId) {
            $item = MenuItem::withoutGlobalScopes()
                ->where('restaurant_id', $this->id)
                ->find($menuItemId);
            if ($item) {
                $monetaryValue = (float) $item->price;
                $cleanPrice = ($monetaryValue == (int) $monetaryValue) ? number_format($monetaryValue, 0) : number_format($monetaryValue, 2);
                $rewardLabel = "طبق مجاني: {$item->name} (بقيمة {$cleanPrice} {$currency})";
            } else {
                $rewardType = 'percentage';
                $rewardValue = 50;
                $monetaryValue = 50;
                $rewardLabel = 'خصم %50 على الطلب القادم';
            }
        } elseif ($rewardType === 'fixed') {
            $cleanVal = ($rewardValue == (int) $rewardValue) ? number_format($rewardValue, 0) : number_format($rewardValue, 2);
            $rewardLabel = "قسيمة خصم بقيمة {$cleanVal} {$currency}";
        } else {
            $cleanVal = ($rewardValue == (int) $rewardValue) ? (int) $rewardValue : $rewardValue;
            $rewardLabel = "خصم %{$cleanVal} على الطلب القادم";
        }

        return [
            'enabled' => $enabled,
            'target_visits' => $targetVisits,
            'reward_type' => $rewardType,
            'reward_value' => $rewardValue,
            'menu_item_id' => $menuItemId,
            'reward_label' => $rewardLabel,
            'monetary_value' => $monetaryValue,
        ];
    }
}
