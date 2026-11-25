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
        return \App\Support\PhoneNormalizer::toWhatsAppDigits($normalizedPhone);
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
            $restaurantTargetPhone = $restaurant->whatsapp_phone ?: $restaurant->phone;
            $targetPhone = $restaurantTargetPhone ?: $phone;
            $waUrl = 'https://wa.me/' . $this->toWhatsAppDigits($targetPhone) . '?text=' . rawurlencode($waMessage);

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

        $greeting = $this->getRandomVoucherGreeting($customer->name, $restaurant->name);

        $msg = "{$greeting}\n\n"
            . "🎯 الجائزة: {$discountLabel}\n"
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

        // Direct 1-click menu link with auto-applied coupon
        $baseUrl = rtrim((string) config('app.url', 'https://tedallal.tech'), '/');
        $menuUrl = "{$baseUrl}/m/{$restaurant->slug}?coupon={$promo->code}";
        $msg .= "\n📲 للطلب وتطبيق الخصم فوراً في السلة:\n{$menuUrl}\n";

        $visits = (int) $customer->visits_count;
        if ($loyalty['enabled']) {
            $target = (int) $loyalty['target_visits'];
            if ($loyaltyVoucher) {
                $msg .= "\n🏆 مفاجأة الولاء الكبرى! لأنها الزيارة رقم ({$visits}) لحضرتك في مطعمنا، يسعدنا تتويجك بالجائزة الكبرى: {$loyalty['reward_label']}!\n"
                    . "🎗️ كود جائزة الولاء: {$loyaltyVoucher->code} (صالح لغاية {$loyaltyVoucher->expires_at?->format('Y-m-d')})\n";
            } elseif ($visits >= 2) {
                $remaining = $target - ($visits % $target);
                $msg .= "\n🌟 أهلاً بحضرتك مجدداً! هذه الزيارة رقم ({$visits}) لدينا — تبقى لحضرتك {$remaining} زيارات فقط للحصول على الجائزة الكبرى: {$loyalty['reward_label']}!\n";
            } else {
                $remaining = $target - 1;
                $msg .= "\n✨ هذه الزيارة رقم (1) لحضرتك في برنامج ولاء «{$restaurant->name}» — اجمع {$remaining} زيارات أخرى للحصول على الجائزة الكبرى: {$loyalty['reward_label']}!\n";
            }
        } elseif ($visits >= 2) {
            $msg .= "\n🌟 يسعدنا تجدد زيارة حضرتك! هذه الزيارة رقم ({$visits}) في مطعمنا «{$restaurant->name}».\n";
        }

        $msg .= "\n✨ نتطلع لخدمتكم دائماً بكل حب!\n⚡ Powered by: www.tedallal.tech";

        return $msg;
    }

    private function getRandomVoucherGreeting(string $customerName, string $restaurantName): string
    {
        $templates = [
            "🎁 أهلاً بحضرتك {$customerName}! ألف مبروك الفوز معنا في مطعمنا «{$restaurantName}» 🎉",
            "✨ أهلاً بحضرتك {$customerName}! يسعدنا تهنئة حضرتك بالفوز اليوم في مطعم «{$restaurantName}» 🌟",
            "🎉 أهلاً بحضرتك {$customerName}! لقد فزت معنا بهدية مميزة في مطعمنا «{$restaurantName}» 🥳",
            "🌟 أهلاً بحضرتك {$customerName}! نبارك لحضرتك الحصول على الهدية المميزة من «{$restaurantName}» 🎁",
        ];
        return $templates[array_rand($templates)];
    }

    private function getRandomReminderGreeting(string $customerName, string $restaurantName): string
    {
        $templates = [
            "أهلاً بحضرتك {$customerName} 👋 نود تذكير حضرتك من مطعمنا «{$restaurantName}» بأن لديك هدية خاصة منتظرة! ✨",
            "أهلاً بحضرتك {$customerName} 🌸 من مطعم «{$restaurantName}» نذكّرك بخصمك الرائع قبل انتهاء المدة!",
            "أهلاً بحضرتك {$customerName} 🌟 لا تنسَ أن لدى حضرتك خصماً مميزاً في مطعم «{$restaurantName}» يسعدنا تشريفكم!",
        ];
        return $templates[array_rand($templates)];
    }

    /**
     * Build the WhatsApp reminder message and deep link for the restaurant manager's CRM.
     */
    public function buildReminderPayload(Restaurant $restaurant, RestaurantCustomer $customer, PromoCode $promo): array
    {
        $discountLabel = $this->formatPromoLabel($promo, $restaurant);
        $expiresText = $promo->expires_at ? $promo->expires_at->format('Y-m-d') : 'قريباً';

        $greeting = $this->getRandomReminderGreeting($customer->name, $restaurant->name);

        $baseUrl = rtrim((string) config('app.url', 'https://tedallal.tech'), '/');
        $menuUrl = "{$baseUrl}/m/{$restaurant->slug}?coupon={$promo->code}";

        $message = "{$greeting}\n\n"
            . "🎯 الهدية: {$discountLabel}\n"
            . "🎟️ كود الخصم: {$promo->code}\n"
            . "📅 ينتهي بتاريخ: {$expiresText}\n"
            . "📲 رابط تطبيق الخصم مباشرة في المنيو:\n{$menuUrl}\n";

        $loyalty = $restaurant->loyaltyConfig();
        if ($loyalty['enabled']) {
            $visits = (int) $customer->visits_count;
            $target = (int) $loyalty['target_visits'];
            $rem = $visits % $target === 0 ? 0 : ($target - ($visits % $target));
            if ($rem > 0) {
                $message .= "\n🌟 رصيد حضرتك الحالي: {$visits} زيارة (متبقي {$rem} زيارات للحصول على: {$loyalty['reward_label']}).\n";
            }
        }

        $message .= "\nيسعدنا تشريفكم في زيارتكم القادمة للاستفادة من الهدية قبل انتهائها ✨\n⚡ Powered by: www.tedallal.tech";

        $url = 'https://wa.me/' . $this->toWhatsAppDigits($customer->phone) . '?text=' . rawurlencode($message);

        return [
            'message' => $message,
            'whatsapp_url' => $url,
        ];
    }

    /**
     * Build 24-hour expiration reminder message (FOMO Alert).
     */
    public function buildExpiringSoonMessage(Restaurant $restaurant, RestaurantCustomer $customer, PromoCode $promo): string
    {
        $discountLabel = $this->formatPromoLabel($promo, $restaurant);
        $expiresText = $promo->expires_at ? $promo->expires_at->format('Y-m-d') : 'غداً';

        $baseUrl = rtrim((string) config('app.url', 'https://tedallal.tech'), '/');
        $menuUrl = "{$baseUrl}/m/{$restaurant->slug}?coupon={$promo->code}";

        return "⏳ أهلاً بحضرتك {$customer->name}! تنبيه ينتهي قريباً ✨\n\n"
            . "نود تذكير حضرتك بأن الخصم الخاص ({$discountLabel}) من مطعم «{$restaurant->name}» سينتهي غداً بتاريخ: {$expiresText}!\n\n"
            . "🎟️ كود الخصم الخاص بك: {$promo->code}\n"
            . "📲 اطلب واستفد من الخصم فوراً من الرابط:\n{$menuUrl}\n\n"
            . "يسعدنا جداً تشريف حضرتك أو طلبك اليوم قبل انتهاء الصلاحية 🎉";
    }

    /**
     * Classify customer into a smart CRM segment based on RFM principles.
     *
     * @return array{segment: string, segment_label: string, segment_tagline: string, days_since_last_visit: int}
     */
    public function classifySegment(RestaurantCustomer $customer): array
    {
        $lastVisit = $customer->last_visit_at ?? $customer->created_at;
        $daysSince = $lastVisit ? (int) $lastVisit->diffInDays(now()) : 999;
        $visits = (int) $customer->visits_count;

        if ($visits >= 5) {
            $segment = 'vip';
            $label = 'عميل مميز VIP 🌟';
            $tagline = "زبون دائم (+{$visits} زيارات)";
        } elseif ($daysSince >= 20 && $visits >= 1) {
            $segment = 'at_risk';
            $label = 'في خطر الانقطاع ⏳';
            $tagline = "لم يزر المطعم منذ {$daysSince} يوماً";
        } elseif ($visits <= 1) {
            $segment = 'new_lead';
            $label = 'عميل جديد 🎁';
            $tagline = "زيارة واحدة (فرصة للتحويل لزبون دائم)";
        } else {
            $segment = 'regular';
            $label = 'عميل متكرر 🔁';
            $tagline = "{$visits} زيارات مسجلة";
        }

        return [
            'segment' => $segment,
            'segment_label' => $label,
            'segment_tagline' => $tagline,
            'days_since_last_visit' => $daysSince,
        ];
    }

    /**
     * Create an exclusive retention promo code for a customer.
     */
    public function createRetentionPromo(
        Restaurant $restaurant,
        RestaurantCustomer $customer,
        string $campaignType,
        string $discountType = 'percentage',
        float $discountValue = 15,
        int $validDays = 7
    ): PromoCode {
        $prefix = match ($campaignType) {
            'win_back' => 'BACK-',
            'vip_reward' => 'VIP-',
            'welcome_lead' => 'HI-',
            default => 'GIFT-',
        };

        do {
            $code = $prefix . strtoupper(Str::random(5));
        } while (PromoCode::withoutGlobalScopes()->where('restaurant_id', $restaurant->id)->where('code', $code)->exists());

        return PromoCode::withoutGlobalScopes()->create([
            'restaurant_id' => $restaurant->id,
            'customer_id' => $customer->id,
            'code' => $code,
            'source' => 'crm_retention',
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'min_order_value' => 0,
            'is_active' => true,
            'starts_at' => now()->subMinute(),
            'expires_at' => now()->addDays(max(1, $validDays)),
            'is_single_use' => true,
            'times_used' => 0,
        ]);
    }

    /**
     * Build WhatsApp payload for customer retention campaign.
     *
     * @return array{message: string, whatsapp_url: string}
     */
    public function buildCampaignPayload(
        Restaurant $restaurant,
        RestaurantCustomer $customer,
        string $campaignType,
        ?PromoCode $promo = null,
        ?string $customMessage = null
    ): array {
        $baseUrl = rtrim((string) config('app.url', 'https://tedallal.tech'), '/');
        $cleanName = $customer->name;
        $restaurantName = $restaurant->name;

        if ($campaignType === 'custom' && ! empty($customMessage)) {
            $message = trim($customMessage);
            if ($promo) {
                $discountLabel = $this->formatPromoLabel($promo, $restaurant);
                $menuUrl = "{$baseUrl}/m/{$restaurant->slug}?coupon={$promo->code}";
                $expiresText = $promo->expires_at ? $promo->expires_at->format('Y-m-d') : 'قريباً';
                $message .= "\n\n🎯 هديتك الخاصة: {$discountLabel}\n"
                    . "🎟️ كود الخصم: {$promo->code}\n"
                    . "📅 صالح لغاية: {$expiresText}\n"
                    . "📲 للطلب وتطبيق الخصم فوراً:\n{$menuUrl}";
            }
        } elseif ($campaignType === 'vip_reward') {
            $message = "🌟 أهلاً بحضرتك {$cleanName}! ✨\n\n"
                . "تقديراً لولائك الدائم وتشريف حضرتك المتكرر لمطعم «{$restaurantName}»، يسعدنا تقديم هذه الهدية الخاصة:\n";
            if ($promo) {
                $discountLabel = $this->formatPromoLabel($promo, $restaurant);
                $menuUrl = "{$baseUrl}/m/{$restaurant->slug}?coupon={$promo->code}";
                $expiresText = $promo->expires_at ? $promo->expires_at->format('Y-m-d') : 'قريباً';
                $message .= "🎯 الهدية: {$discountLabel}\n"
                    . "🎟️ كود الخصم الحصري: {$promo->code}\n"
                    . "📅 صالح لغاية: {$expiresText}\n"
                    . "📲 للطلب وتطبيق الخصم مباشرة في المنيو:\n{$menuUrl}\n\n";
            }
            $message .= "يسعدنا ويشرفنا دائماً وجود حضرتك بيننا، ونتطلع لاستقبالكم في أقرب وقت! 👑";
        } elseif ($campaignType === 'win_back') {
            $message = "🌸 أهلاً بحضرتك {$cleanName} في مطعمنا «{$restaurantName}»! 👋\n\n"
                . "مرّ وقت منذ آخر زيارة تشرّفنا بها، ويسعدنا إهداء حضرتك كود خصم خاص للترحيب بكم مجدداً:\n";
            if ($promo) {
                $discountLabel = $this->formatPromoLabel($promo, $restaurant);
                $menuUrl = "{$baseUrl}/m/{$restaurant->slug}?coupon={$promo->code}";
                $expiresText = $promo->expires_at ? $promo->expires_at->format('Y-m-d') : 'قريباً';
                $message .= "🎯 كود العودة: {$discountLabel}\n"
                    . "🎟️ الكود: {$promo->code}\n"
                    . "📅 صالح لغاية: {$expiresText}\n"
                    . "📲 للطلب المباشر أو زيارتنا:\n{$menuUrl}\n\n";
            }
            $message .= "نسعد دائماً بتشريف حضرتك وأشهى الأطباق جاهزة لذوقكم الرفيع ✨";
        } else { // welcome_lead / default
            $message = "🎉 أهلاً بحضرتك {$cleanName} في عائلة «{$restaurantName}»! ✨\n\n"
                . "سعدنا جداً بزيارة حضرتك الأولى لنا، ويسعدنا تقديم كود خصم خاص للزيارة القادمة:\n";
            if ($promo) {
                $discountLabel = $this->formatPromoLabel($promo, $restaurant);
                $menuUrl = "{$baseUrl}/m/{$restaurant->slug}?coupon={$promo->code}";
                $expiresText = $promo->expires_at ? $promo->expires_at->format('Y-m-d') : 'قريباً';
                $message .= "🎯 الهدية: {$discountLabel}\n"
                    . "🎟️ كود الخصم: {$promo->code}\n"
                    . "📅 صالح لغاية: {$expiresText}\n"
                    . "📲 رابط المنيو وتطبيق الخصم:\n{$menuUrl}\n\n";
            }
            $message .= "نتطلع لخدمة حضرتك مجدداً بأعلى معايير الضيافة! 🍽️";
        }

        $url = 'https://wa.me/' . $this->toWhatsAppDigits($customer->phone) . '?text=' . rawurlencode($message);

        return [
            'message' => $message,
            'whatsapp_url' => $url,
        ];
    }
}

