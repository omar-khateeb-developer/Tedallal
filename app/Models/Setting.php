<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * الحصول على قيمة إعداد معين.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        try {
            return \Illuminate\Support\Facades\Cache::remember("setting.{$key}", 300, function () use ($key, $default) {
                $setting = static::where('key', $key)->first();
                return $setting ? $setting->value : $default;
            });
        } catch (\Throwable) {
            return $default;
        }
    }

    /**
     * تعيين أو تحديث قيمة إعداد معين.
     */
    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        try {
            \Illuminate\Support\Facades\Cache::forget("setting.{$key}");
        } catch (\Throwable) {
            // cache ignore
        }
    }

    /**
     * هل وضع الصيانة مفعّل؟
     */
    public static function isMaintenanceModeEnabled(): bool
    {
        return static::get('maintenance_mode', '0') === '1';
    }

    /**
     * هل وضع التجريب وفحص الكوبونات مفعل لمدير المنصة؟
     */
    public static function isPromoTestingModeEnabled(): bool
    {
        return static::get('promo_testing_mode', '0') === '1';
    }
}
