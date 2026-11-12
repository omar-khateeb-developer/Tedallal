<?php

namespace App\Services;

use App\Models\OrderWheelToken;
use App\Models\PromoCode;
use App\Models\Restaurant;
use App\Models\RestaurantCustomer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CustomerCrmService
{
    private const BLOCKED_FAKE_NAMES = [
        'test', 'testing', 'asdf', 'qwer', 'zxcv', 'admin', 'user', 'guest',
        'customer', 'client', 'none', 'null', 'na', 'anonymous', 'demo',
        'تجربة', 'تيست', 'تست', 'مستخدم', 'زبون', 'عميل', 'ضيف', 'بدون',
        'لايوجد', 'لا يوجد', 'غير معروف', 'مجهول', 'فلان', 'اسم',
    ];

    private const BLOCKED_SEQUENTIAL_PHONES = [
        '0912345678',
        '0987654321',
        '0923456789',
        '0901234567',
        '0998765432',
    ];

    /**
     * Validate and clean a customer's real name.
     *
     * @throws ValidationException
     */
    public function validateAndNormalizeName(string $rawName): string
    {
        $clean = preg_replace('/\s+/u', ' ', trim($rawName)) ?? '';
        $length = mb_strlen($clean, 'UTF-8');

        if ($length < 3 || $length > 50) {
            throw ValidationException::withMessages([
                'name' => ['يرجى إدخال اسم حقيقي يتكون من 3 أحرف على الأقل (حد أقصى 50 حرفاً).'],
            ]);
        }

        // Letters (Arabic/English) and spaces only — reject numbers and symbols
        if (! preg_match('/^[\p{Arabic}a-zA-Z\s]+$/u', $clean)) {
            throw ValidationException::withMessages([
                'name' => ['الاسم يجب أن يحتوي على أحرف عربية أو إنجليزية فقط بدون أرقام أو رموز.'],
            ]);
        }

        // Reject 3+ repeated identical consecutive characters (e.g., "ببب", "aaa")
        $noSpaces = str_replace(' ', '', mb_strtolower($clean, 'UTF-8'));
        if (preg_match('/(.)\1{2,}/u', $noSpaces)) {
            throw ValidationException::withMessages([
                'name' => ['يرجى كتابة اسمك الحقيقي بدون تكرار عشوائي للأحرف.'],
            ]);
        }

        $lowerClean = mb_strtolower($clean, 'UTF-8');
        if (in_array($lowerClean, self::BLOCKED_FAKE_NAMES, true)) {
            throw ValidationException::withMessages([
                'name' => ['يرجى كتابة اسمك الحقيقي لتوثيق هديتك باسمك في المطعم.'],
            ]);
        }

        return $clean;
    }

    /**
     * Validate and normalize a Syrian mobile number starting with 09.
     *
     * @throws ValidationException
     */
    public function validateAndNormalizePhone(string $rawPhone): string
    {
        // Convert Arabic/Persian numerals to standard ASCII digits
        $converted = strtr($rawPhone, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);

        // Strip spaces, dashes, parentheses
        $digits = preg_replace('/[^0-9]/', '', $converted) ?? '';

        if (! preg_match('/^09[1-9]\d{7}$/', $digits)) {
            throw ValidationException::withMessages([
                'phone' => ['يرجى إدخال رقم واتساب صحيح يبدأ بـ 09 ومكوّن من 10 أرقام (مثال: 0934567890).'],
            ]);
        }

        // Reject repeated digits after 09 (e.g. 0911111111, 0999999999)
        if (preg_match('/^09(\d)\1{7}$/', $digits) || in_array($digits, self::BLOCKED_SEQUENTIAL_PHONES, true)) {
            throw ValidationException::withMessages([
                'phone' => ['يرجى إدخال رقم هاتف حقيقي فعال لتصلك رسالة الكوبون عليه.'],
            ]);
        }

        return $digits;
    }

    /**
     * Convert a normalized 09XXXXXXXX number to international WhatsApp format (9639XXXXXXXX).
     */
    public function toWhatsAppDigits(string $normalizedPhone): string
    {
        if (str_starts_with($normalizedPhone, '09') && strlen($normalizedPhone) === 10) {
            return '963' . substr($normalizedPhone, 1);
        }

        return ltrim($normalizedPhone, '+');
    }

    /**
     * Format a human-readable discount description.
     */
    public function formatPromoLabel(PromoCode $promo, Restaurant $restaurant): string
    {
        if ($promo->free_menu_item_id && $promo->freeMenuItem) {
            $price = (float) $promo->discount_value;
            $cleanPrice = ($price == (int) $price) ? number_format($price, 0) : number_format($price, 2);
            $currency = $this->normalizeCurrency($restaurant->currency);
            return "طبق مجاني: {$promo->freeMenuItem->name} (بقيمة {$cleanPrice} {$currency})";
        }

        $val = (float) $promo->discount_value;
        $cleanVal = ($val == (int) $val) ? (int) $val : $val;

        if ($promo->discount_type === 'percentage') {
            return "خصم %{$cleanVal}";
        }

        $currency = $this->normalizeCurrency($restaurant->currency);
        return "خصم {$cleanVal} {$currency}";
    }

    public function normalizeCurrency(?string $currency): string
    {
        if (! $currency || strtoupper(trim($currency)) === 'SYP') {
            return 'ل.س';
        }

        return $currency;
    }

    /**
     * Save or update a customer in the restaurant's isolated CRM, link the wheel voucher,
     * evaluate loyalty milestones, and generate the formatted WhatsApp delivery payload.
     */
    public function recordWheelCustomerClaim(
        Restaurant $restaurant,
        string $rawName,
        string $rawPhone,
        PromoCode $promo,
        ?OrderWheelToken $wheelToken = null,
        ?string $discountLabelOverride = null
    ): array {
        $name = $this->validateAndNormalizeName($rawName);
        $phone = $this->validateAndNormalizePhone($rawPhone);

        return DB::transaction(function () use ($restaurant, $name, $phone, $promo, $wheelToken, $discountLabelOverride) {
            $alreadyClaimedForToken = $wheelToken && $wheelToken->customer_id !== null;

            $customer = RestaurantCustomer::withoutGlobalScopes()
                ->where('restaurant_id', $restaurant->id)
                ->where('phone', $phone)
                ->lockForUpdate()
                ->first();

            if (! $customer) {
                $customer = RestaurantCustomer::withoutGlobalScopes()->create([
                    'restaurant_id' => $restaurant->id,
                    'name' => $name,
                    'phone' => $phone,
                    'visits_count' => 1,
                    'last_visit_at' => now(),
                ]);
            } elseif (! $alreadyClaimedForToken) {
                $customer->update([
                    'name' => $name,
                    'visits_count' => $customer->visits_count + 1,
                    'last_visit_at' => now(),
                ]);
            } else {
                $customer->update(['name' => $name]);
            }

            // Link voucher to customer
            $promo->update([
                'customer_id' => $customer->id,
                'source' => $promo->isDemoCode() ? 'demo_wheel' : ($promo->source === 'manual' ? 'wheel' : $promo->source),
            ]);

            if ($wheelToken) {
                $wheelToken->update([
                    'customer_id' => $customer->id,
                    'customer_name' => $name,
                    'customer_phone' => $phone,
                ]);
            }

            $loyalty = $restaurant->loyaltyConfig();
            $loyaltyVoucher = null;
            $visits = (int) $customer->visits_count;
            $target = (int) $loyalty['target_visits'];

            // Check if customer hit a loyalty milestone (excluding demo wheel tokens unless configured)
            $isDemo = $promo->isDemoCode();
            if (! $isDemo && $loyalty['enabled'] && $visits >= $target && ($visits % $target === 0)) {
                $loyaltyVoucher = $this->generateLoyaltyMilestonePromo($restaurant, $customer, $loyalty);
            }

            $discountLabel = $discountLabelOverride ?: $this->formatPromoLabel($promo, $restaurant);
            $waMessage = $this->buildWheelClaimMessage($restaurant, $customer, $promo, $discountLabel, $loyalty, $loyaltyVoucher);
            $waUrl = 'https://wa.me/' . $this->toWhatsAppDigits($phone) . '?text=' . rawurlencode($waMessage);

            $remaining = $loyalty['enabled']
                ? ($visits % $target === 0 ? $target : ($target - ($visits % $target)))
                : 0;

            return [
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                    'visits_count' => $visits,
                ],
                'loyalty' => [
                    'enabled' => $loyalty['enabled'],
                    'target_visits' => $target,
                    'remaining_visits' => $remaining,
                    'reward_label' => $loyalty['reward_label'],
                    'milestone_reached' => $loyaltyVoucher !== null,
                    'loyalty_voucher' => $loyaltyVoucher ? [
                        'code' => $loyaltyVoucher->code,
                        'reward_label' => $loyalty['reward_label'],
                        'expires_at' => $loyaltyVoucher->expires_at?->format('Y-m-d'),
                    ] : null,
                ],
                'whatsapp_message' => $waMessage,
                'whatsapp_url' => $waUrl,
            ];
        });
    }

    private function generateLoyaltyMilestonePromo(Restaurant $restaurant, RestaurantCustomer $customer, array $loyalty): PromoCode
    {
        do {
            $code = 'LOYAL-' . strtoupper(Str::random(6));
        } while (PromoCode::withoutGlobalScopes()->where('restaurant_id', $restaurant->id)->where('code', $code)->exists());

        $discountType = $loyalty['reward_type'] === 'percentage' ? 'percentage' : 'fixed';
        $discountValue = (float) $loyalty['monetary_value'];
        $freeMenuItemId = $loyalty['reward_type'] === 'free_item' ? $loyalty['menu_item_id'] : null;

        return PromoCode::withoutGlobalScopes()->create([
            'restaurant_id' => $restaurant->id,
            'customer_id' => $customer->id,
            'code' => $code,
            'source' => 'loyalty',
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'min_order_value' => 0,
            'free_menu_item_id' => $freeMenuItemId,
            'is_active' => true,
            'starts_at' => now()->subMinute(),
            'expires_at' => now()->addDays(30),
            'is_single_use' => true,
            'times_used' => 0,
        ]);
    }

    private function buildWheelClaimMessage(
        Restaurant $restaurant,
        RestaurantCustomer $customer,
        PromoCode $promo,
        string $discountLabel,
        array $loyalty,
        ?PromoCode $loyaltyVoucher
    ): string {
        $isDemo = $promo->isDemoCode();
        $startsText = $isDemo
            ? 'مفعّل فوراً للتجربة'
            : ($promo->starts_at ? $promo->starts_at->format('Y-m-d h:i A') : 'الآن');
        $expiresText = $promo->expires_at ? $promo->expires_at->format('Y-m-d') : 'مفتوح';

        $msg = "🎁 مبروك {$customer->name}! لقد ربحت {$discountLabel} من مطعمنا «{$restaurant->name}».\n\n"
            . "🎟️ كود الخصم: {$promo->code}\n"
            . "⏳ يبدأ من: {$startsText}\n"
            . "📅 صالح لغاية تاريخ: {$expiresText}\n";

        if ((float) $promo->min_order_value > 0) {
            $cleanMin = ((float) $promo->min_order_value == (int) $promo->min_order_value)
                ? (int) $promo->min_order_value
                : $promo->min_order_value;
            $currency = $this->normalizeCurrency($restaurant->currency);
            $msg .= "🛒 الحد الأدنى للطلب: {$cleanMin} {$currency}\n";
        }

        $visits = (int) $customer->visits_count;
        if ($loyalty['enabled']) {
            $target = (int) $loyalty['target_visits'];
            if ($loyaltyVoucher) {
                $msg .= "\n🏆 مفاجأة الولاء الكبرى! لأنها زيارتك رقم ({$visits}) في مطعمنا، لقد استحققت الجائزة الكبرى: {$loyalty['reward_label']}!\n"
                    . "🎖️ كود جائزة الولاء: {$loyaltyVoucher->code} (صالح لغاية {$loyaltyVoucher->expires_at?->format('Y-m-d')})\n";
            } elseif ($visits >= 2) {
                $remaining = $target - ($visits % $target);
                $msg .= "\n🌟 أهلاً بعودتك مجدداً! هذه زيارتك رقم ({$visits}) لدينا — تبقى لك {$remaining} زيارات فقط لتحصل على الجائزة الكبرى: {$loyalty['reward_label']}!\n";
            } else {
                $remaining = $target - 1;
                $msg .= "\n✨ هذه زيارتك رقم (1) في برنامج ولاء «{$restaurant->name}» — اجمع {$remaining} زيارات أخرى لتحصل على الجائزة الكبرى: {$loyalty['reward_label']}!\n";
            }
        } elseif ($visits >= 2) {
            $msg .= "\n🌟 يسعدنا تجدد زيارتك! هذه زيارتك رقم ({$visits}) في مطعمنا «{$restaurant->name}».\n";
        }

        $msg .= "\n✨ نتطلع لخدمتكم دائماً بكل حب!";

        return $msg;
    }

    /**
     * Build the WhatsApp reminder message and deep link for the restaurant manager's CRM.
     */
    public function buildReminderPayload(Restaurant $restaurant, RestaurantCustomer $customer, PromoCode $promo): array
    {
        $discountLabel = $this->formatPromoLabel($promo, $restaurant);
        $expiresText = $promo->expires_at ? $promo->expires_at->format('Y-m-d') : 'قريباً';

        $message = "مرحباً {$customer->name} 👋\n"
            . "نذكّرك من مطعمنا «{$restaurant->name}» بأن لديك {$discountLabel} عبر الكود: {$promo->code}\n"
            . "📅 ينتهي بتاريخ: {$expiresText}\n";

        $loyalty = $restaurant->loyaltyConfig();
        if ($loyalty['enabled']) {
            $visits = (int) $customer->visits_count;
            $target = (int) $loyalty['target_visits'];
            $rem = $visits % $target === 0 ? 0 : ($target - ($visits % $target));
            if ($rem > 0) {
                $message .= "🌟 رصيدك الحالي: {$visits} زيارة (متبقي {$rem} زيارات للحصول على: {$loyalty['reward_label']}).\n";
            }
        }

        $message .= "\nيسعدنا تشريفكم في زيارتكم القادمة للاستفادة من الهدية قبل انتهائها ✨";

        $url = 'https://wa.me/' . $this->toWhatsAppDigits($customer->phone) . '?text=' . rawurlencode($message);

        return [
            'message' => $message,
            'whatsapp_url' => $url,
        ];
    }
}

