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

    /**
     * تشخيص تفصيلي لسبب رفض الكوبون (لحالة التجريب والديباج)
     */
    public function getValidationDiagnostics(?float $orderTotal = null, string $currency = 'SYP'): array
    {
        $now = now();
        $isFuture = $this->starts_at && $this->starts_at->isFuture();
        $isPast = $this->expires_at && $this->expires_at->isPast();
        $isSingleUsed = $this->is_single_use && $this->times_used >= 1;
        $isMinNotMet = $orderTotal !== null && $orderTotal < (float)$this->min_order_value;
        $isInactive = !$this->is_active;

        $valid = !$isInactive && !$isFuture && !$isPast && !$isSingleUsed && !$isMinNotMet;

        $reasonCode = 'valid';
        $reasonMessage = 'الكوبون صالح وجاهز للتطبيق.';
        $canForceActivate = false;

        if ($isInactive) {
            $reasonCode = 'inactive';
            $reasonMessage = 'هذا الكوبون معطل وموقوف حالياً من قبل إدارة المطعم.';
        } elseif ($isFuture) {
            $reasonCode = 'future_start';
            $diffMinutes = $now->diffInMinutes($this->starts_at);
            $hours = intdiv($diffMinutes, 60);
            $mins = $diffMinutes % 60;
            $timeStr = ($hours > 0 ? "{$hours} ساعة و " : '') . "{$mins} دقيقة";
            $formattedStart = $this->starts_at->format('Y-m-d h:i A');

            $reasonMessage = "🎁 هذا الكوبون مخصص لزيارتك القادمة: يبدأ تفعيله بتاريخ {$formattedStart} (متبقي حوالي {$timeStr}). نتشرف بزيارتك القادمة!";
            $canForceActivate = true;
        } elseif ($isPast) {
            $reasonCode = 'expired';
            $formattedEnd = $this->expires_at->format('Y-m-d h:i A');
            $reasonMessage = "انتهت صلاحية هذا الكوبون بتاريخ {$formattedEnd}.";
        } elseif ($isSingleUsed) {
            $reasonCode = 'already_used';
            $reasonMessage = "✓ تم استخدام هذا الكوبون مسبقاً (صالح للاستخدام لمرة واحدة فقط).";
        } elseif ($isMinNotMet) {
            $reasonCode = 'min_order_not_met';
            $diff = round((float)$this->min_order_value - (float)$orderTotal, 2);
            $reasonMessage = "🏷️ الحد الأدنى لتطبيق هذا الكوبون هو {$this->min_order_value} {$currency} ومجموع سلتك الحالي {$orderTotal} {$currency}. أضف أصنافاً بقيمة {$diff} {$currency} لتفعيل الخصم!";
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
        if (!$this->isValid($orderTotal)) {
            return 0.0;
        }

        if ($this->discount_type === 'fixed') {
            return min($this->discount_value, $orderTotal);
        }

        return round($orderTotal * ($this->discount_value / 100), 2);
    }
}
