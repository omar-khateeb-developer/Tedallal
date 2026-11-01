<?php

namespace App\Enums;

enum RestaurantPlan: string
{
    case Basic = 'basic';
    case Pro = 'pro';
    case Max = 'max';

    public function label(): string
    {
        return match ($this) {
            self::Basic => 'الأساسية (Basic)',
            self::Pro => 'المتقدمة (Pro)',
            self::Max => 'الشاملة (Max)',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Basic => 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-300 border border-slate-300 dark:border-slate-700',
            self::Pro => 'bg-amber-100 text-amber-900 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-300 dark:border-amber-700/60',
            self::Max => 'bg-purple-100 text-purple-900 dark:bg-purple-950/60 dark:text-purple-300 border border-purple-300 dark:border-purple-700/60',
        };
    }

    public function features(): array
    {
        return match ($this) {
            self::Basic => [
                'analytics' => false,
                'offers' => false,
                'ai_waiter' => false,
                'custom_branding' => false,
            ],
            self::Pro => [
                'analytics' => true,
                'offers' => true,
                'ai_waiter' => false,
                'custom_branding' => false,
            ],
            self::Max => [
                'analytics' => true,
                'offers' => true,
                'ai_waiter' => true,
                'custom_branding' => true,
            ],
        };
    }
}

