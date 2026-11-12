<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasTenant;

class PromoCode extends Model
{
    use HasTenant;
    protected $fillable = [
        'restaurant_id',
        'customer_id',
        'code',
        'source',
        'discount_type',
        'discount_value',
        'min_order_value',
        'free_menu_item_id',
        'is_active',
        'starts_at',
        'is_single_use',
        'times_used',
        'expires_at',
        'reminded_at',
        'reminders_count',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_single_use' => 'boolean',
        'times_used' => 'integer',
        'reminders_count' => 'integer',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'reminded_at' => 'datetime',
        'discount_value' => 'decimal:2',
        'min_order_value' => 'decimal:2',
    ];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function customer()
    {
        return $this->belongsTo(RestaurantCustomer::class, 'customer_id');
    }

    public function freeMenuItem()
    {
        return $this->belongsTo(MenuItem::class, 'free_menu_item_id')->withoutGlobalScopes();
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function isLoyaltyCode(): bool
    {
        return $this->source === 'loyalty' || str_starts_with($this->code, 'LOYAL-');
    }

    public function isDemoCode(): bool
    {
        return str_starts_with($this->code, 'DEMO-');
    }

    public function isValid(?float $orderTotal = null): bool
    {
        // 1. التحقق أولاً وقبل أي فحص زمني: إذا كان الكوبون أحادي الاستخدام، هل هو مستهلك أو تالف أو تم استخدامه مسبقاً؟
        if ($this->is_single_use) {
            $isUsedInActiveOrder = Order::withoutGlobalScopes()
                ->where('promo_code_id', $this->id)
                ->where('status', '!=', \App\Enums\OrderStatus::Cancelled)
                ->exists();

            if ($this->times_used >= 1 || $isUsedInActiveOrder || !$this->is_active) {
                return false;
            }
        }

        if (!$this->is_active) {
            return false;
        }

        // الكوبونات التجريبية تفعل فوراً بدون أي انتظار
        if (!$this->isDemoCode() && $this->starts_at && $this->starts_at->isFuture()) {
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

    /**
     * تشخيص تفصيلي لسبب رفض الكوبون (لحالة التجريب والديباج)
     */
    public function getValidationDiagnostics(?float $orderTotal = null, string $currency = 'SYP'): array
    {
        $now = now();
        $isFuture = !$this->isDemoCode() && $this->starts_at && $this->starts_at->isFuture();
        $isPast = $this->expires_at && $this->expires_at->isPast();

        // 1. التحقق من الكوبونات الفردية وأحادية الاستخدام (قسائم عجلة الحظ): هل الكوبون مُستهلك مسبقاً؟
        $isSingleUsed = false;
        if ($this->is_single_use) {
            $isUsedInActiveOrder = Order::withoutGlobalScopes()
                ->where('promo_code_id', $this->id)
                ->where('status', '!=', \App\Enums\OrderStatus::Cancelled)
                ->exists();

            $isSingleUsed = $this->times_used >= 1 || !$this->is_active || $isUsedInActiveOrder;
        }

        $isInactive = !$this->is_active && !$isSingleUsed;
        $isMinNotMet = $orderTotal !== null && $orderTotal < (float)$this->min_order_value;

        $valid = !$isInactive && !$isFuture && !$isPast && !$isSingleUsed && !$isMinNotMet;

        $reasonCode = 'valid';
        $reasonMessage = 'الكوبون صالح وجاهز للتطبيق.';
        $canForceActivate = false;

        // الفحص الصارم: إذا كان الكوبون مستهلكاً مسبقاً يظهر فوراً بدون إدخال الزبون في دوامة الانتظار
        if ($isSingleUsed) {
            $reasonCode = 'already_used';
            $reasonMessage = "عذراً، هذا الكوبون مُستهلك (تالف) وقد تم استخدامه مسبقاً! الرمز صالح لمرة واحدة فقط.";
        } elseif ($isInactive) {
            $reasonCode = 'inactive';
            $reasonMessage = 'هذا الكوبون معطل وموقوف حالياً من قبل إدارة المطعم.';
        } elseif ($isFuture) {
            $reasonCode = 'future_start';
            $diffMinutes = $now->diffInMinutes($this->starts_at);
            $hours = intdiv($diffMinutes, 60);
            $mins = $diffMinutes % 60;
            $timeStr = ($hours > 0 ? "{$hours} ساعة و " : '') . "{$mins} دقيقة";
            $formattedStart = $this->starts_at->format('Y-m-d h:i A');

            $reasonMessage = "✓ تم التحقق بنجاح: الكوبون سليم وغير مستخدم مسبقاً ومحفوظ لحسابك! الكوبون مخصص لزيارتك القادمة حيث يبدأ تفعيله بتاريخ {$formattedStart} (متبقي حوالي {$timeStr}). نتشرف بزيارتك القادمة!";
            $canForceActivate = true;
        } elseif ($isPast) {
            $reasonCode = 'expired';
            $formattedEnd = $this->expires_at->format('Y-m-d h:i A');
            $reasonMessage = "انتهت صلاحية هذا الكوبون بتاريخ {$formattedEnd}.";
        } elseif ($isMinNotMet) {
            $reasonCode = 'min_order_not_met';
            $diff = round((float)$this->min_order_value - (float)$orderTotal, 2);
            $cleanMin = ((float)$this->min_order_value == (int)$this->min_order_value) ? (int)$this->min_order_value : $this->min_order_value;
            $cleanTotal = ($orderTotal == (int)$orderTotal) ? (int)$orderTotal : $orderTotal;
            $cleanDiff = ($diff == (int)$diff) ? (int)$diff : $diff;
            $cleanCurrency = ($currency === 'SYP' || strtoupper($currency) === 'SYP') ? 'ل.س' : $currency;
            $reasonMessage = "🏷️ الحد الأدنى لتطبيق هذا الخصم هو {$cleanMin} {$cleanCurrency} ومجموع طلبك الحالي {$cleanTotal} {$cleanCurrency}. أضف أصنافاً بقيمة {$cleanDiff} {$cleanCurrency} لتفعيل الخصم!";
        }

        return [
            'valid' => $valid,
            'reason_code' => $reasonCode,
            'reason_message' => $reasonMessage,
            'can_force_activate' => $canForceActivate,
            'details' => [
                'code' => $this->code,
                'is_active' => (bool)$this->is_active,
                'starts_at' => $this->starts_at?->toIso8601String(),
                'starts_at_formatted' => $this->starts_at?->format('Y-m-d h:i A'),
                'is_future' => $isFuture,
                'expires_at' => $this->expires_at?->toIso8601String(),
                'expires_at_formatted' => $this->expires_at?->format('Y-m-d h:i A'),
                'is_single_use' => (bool)$this->is_single_use,
                'times_used' => (int)$this->times_used,
                'min_order_value' => (float)$this->min_order_value,
                'discount_type' => $this->discount_type,
                'discount_value' => (float)$this->discount_value,
                'current_total' => $orderTotal !== null ? (float)$orderTotal : null,
            ],
        ];
    }

    public function calculateDiscount(float $orderTotal): float
    {
        if (!$this->isValid($orderTotal) || $orderTotal <= 0) {
            return 0.0;
        }

        if ($this->discount_type === 'fixed') {
            return min((float) $this->discount_value, $orderTotal);
        }

        // Percentage discount capped at 100% and strictly non-negative
        $percent = min(max((float) $this->discount_value, 0.0), 100.0);
        $discount = round($orderTotal * ($percent / 100), 2);

        return min($discount, $orderTotal);
    }
}
