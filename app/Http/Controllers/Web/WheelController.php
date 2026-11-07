<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\OrderWheelToken;
use App\Models\PromoCode;
use App\Models\Restaurant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class WheelController extends Controller
{
    /**
     * Standard attractive slice colors (Rich jewel tones with high contrast)
     */
    private const SLICE_COLORS = [
        '#D4AF37', // Royal Gold
        '#059669', // Emerald
        '#E11D48', // Ruby
        '#7C3AED', // Purple
        '#0284C7', // Sky Azure
        '#D97706', // Warm Amber
        '#1E293B', // Dark Obsidian
        '#BE185D', // Magenta
    ];

    /**
     * Default fallback rewards if restaurant has not configured custom promo codes
     */
    private const DEFAULT_REWARDS = [
        ['label' => 'خصم %15', 'type' => 'percentage', 'value' => 15, 'icon' => '🔥'],
        ['label' => 'مشروب ضيافة', 'type' => 'percentage', 'value' => 10, 'icon' => '☕'],
        ['label' => 'خصم %20', 'type' => 'percentage', 'value' => 20, 'icon' => '🎉'],
        ['label' => 'حلى مجاني', 'type' => 'percentage', 'value' => 12, 'icon' => '🍰'],
        ['label' => 'خصم %10', 'type' => 'percentage', 'value' => 10, 'icon' => '⭐'],
        ['label' => 'خصم %25', 'type' => 'percentage', 'value' => 25, 'icon' => '👑'],
        ['label' => 'خصم %5', 'type' => 'percentage', 'value' => 5, 'icon' => '🎁'],
        ['label' => 'مقبلات مجانية', 'type' => 'percentage', 'value' => 15, 'icon' => '🥗'],
    ];

    /**
     * Display the lucky wheel page for the given order token or demo mode.
     */
    public function show(Request $request, string $token): Response
    {
        // 1. Handle Demo Mode (Immediate interactive testing)
        if ($token === 'demo') {
            return $this->renderDemoWheel($request);
        }

        // 2. Fetch real wheel token
        $wheelToken = OrderWheelToken::where('token', $token)
            ->with(['restaurant', 'order', 'wonPromoCode', 'generatedPromoCode'])
            ->firstOrFail();

        $restaurant = $wheelToken->restaurant;

        // Allow resetting token if requested by admin/developer in debug mode or query
        if ($request->has('reset') && (auth()->check() || config('app.debug'))) {
            $wheelToken->update([
                'is_claimed' => false,
                'claimed_at' => null,
                'won_promo_code_id' => null,
                'generated_promo_code_id' => null,
            ]);
            $wheelToken->refresh();
        }

        $isExpired = $wheelToken->isExpired();
        $isClaimed = (bool) $wheelToken->is_claimed;

        $claimedVoucher = null;
        if ($isClaimed && $wheelToken->generatedPromoCode) {
            $gen = $wheelToken->generatedPromoCode;
            $claimedVoucher = [
                'code' => $gen->code,
                'discount_value' => (float) $gen->discount_value,
                'discount_type' => $gen->discount_type,
                'discount_text' => $this->formatDiscountLabel(
                    $gen->discount_type,
                    $gen->discount_value,
                    $restaurant->currency
                ),
                'starts_at' => $gen->starts_at ? $gen->starts_at->format('Y-m-d h:i A') : null,
                'expires_at' => $gen->expires_at ? $gen->expires_at->format('Y-m-d') : null,
                'min_order_value' => (float) $gen->min_order_value,
                'is_used' => $gen->times_used > 0 || !$gen->is_active,
            ];
        }

        // Build active slices for the wheel
        $slices = $this->buildSlicesForRestaurant($restaurant);

        // Find winning slice index if already claimed
        $wonSliceIndex = null;
        if ($isClaimed && $wheelToken->won_promo_code_id) {
            foreach ($slices as $idx => $s) {
                if ($s['id'] === $wheelToken->won_promo_code_id) {
                    $wonSliceIndex = $idx;
                    break;
                }
            }
        }

        $cleanCurrency = $this->normalizeCurrency($restaurant->currency);

        $table = $wheelToken->order?->table;
        $menuUrl = $table?->public_token ? route('customer.menu', ['token' => $table->public_token]) : null;
        $orderUrl = ($table?->public_token && $wheelToken->order?->order_number)
            ? route('customer.order', ['token' => $table->public_token, 'orderNumber' => $wheelToken->order->order_number])
            : null;

        return Inertia::render('Wheel', [
            'token' => $token,
            'is_demo' => false,
            'restaurant' => [
                'name' => $restaurant->name,
                'logo' => $restaurant->logo,
                'currency' => $cleanCurrency,
                'menu_url' => $menuUrl,
                'order_url' => $orderUrl,
            ],
            'order_number' => $wheelToken->order?->order_number,
            'is_claimed' => $isClaimed,
            'is_expired' => $isExpired,
            'claimed_voucher' => $claimedVoucher,
            'won_slice_index' => $wonSliceIndex,
            'slices' => $slices,
        ]);
    }

    /**
     * Spin the wheel and generate a unique single-use voucher for the next visit.
     */
    public function spin(Request $request, string $token): JsonResponse
    {
        // 1. Handle Demo Spin
        if ($token === 'demo') {
            $slices = $this->getDefaultSlices('ل.س');
            $winningIndex = random_int(0, count($slices) - 1);
            $wonSlice = $slices[$winningIndex];

            return response()->json([
                'success' => true,
                'won_id' => $wonSlice['id'],
                'won_index' => $winningIndex,
                'voucher' => [
                    'code' => 'DEMO-' . strtoupper(Str::random(6)),
                    'discount_text' => $wonSlice['label'],
                    'starts_at' => now()->format('Y-m-d h:i A'),
                    'expires_at' => now()->addDays(14)->format('Y-m-d'),
                    'min_order_value' => 0,
                    'is_demo' => true,
                ],
            ]);
        }

        // 2. Real Token Spin Transaction
        return DB::transaction(function () use ($token) {
            $wheelToken = OrderWheelToken::where('token', $token)
                ->lockForUpdate()
                ->firstOrFail();

            if ($wheelToken->is_claimed) {
                return response()->json([
                    'success' => false,
                    'message' => 'لقد تم تدوير عجلة الحظ مسبقاً لهذا الطلب.',
                ], 422);
            }

            if ($wheelToken->isExpired()) {
                return response()->json([
                    'success' => false,
                    'message' => 'عذراً، انتهت صلاحية رابط عجلة الحظ لهذا الطلب.',
                ], 422);
            }

            $restaurant = $wheelToken->restaurant;
            $slices = $this->buildSlicesForRestaurant($restaurant);

            if (empty($slices)) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا توجد جوائز متاحة حالياً في العجلة.',
                ], 422);
            }

            // Pick a winning slice randomly
            $winningIndex = random_int(0, count($slices) - 1);
            $wonSlice = $slices[$winningIndex];

            // Resolve or ensure PromoCode in database
            $wonOffer = PromoCode::where('restaurant_id', $restaurant->id)
                ->where('id', $wonSlice['id'])
                ->first();

            if (!$wonOffer) {
                // If this was a default virtual slice, create a base promo code for it
                $wonOffer = PromoCode::create([
                    'restaurant_id' => $restaurant->id,
                    'code' => 'BASE-' . strtoupper(Str::random(6)),
                    'discount_type' => $wonSlice['discount_type'],
                    'discount_value' => $wonSlice['discount_value'],
                    'min_order_value' => 0,
                    'is_active' => true,
                    'is_single_use' => false,
                ]);
            }

            // Generate unique voucher code for next visit
            do {
                $uniqueCode = 'LUCK-' . strtoupper(Str::random(6));
            } while (PromoCode::where('restaurant_id', $restaurant->id)->where('code', $uniqueCode)->exists());

            // Next visit validity: begins in 2 hours, valid for 14 days
            $startsAt = now()->addHours(2);
            $expiresAt = now()->addDays(14);

            $generatedPromo = PromoCode::create([
                'restaurant_id' => $restaurant->id,
                'code' => $uniqueCode,
                'discount_type' => $wonOffer->discount_type,
                'discount_value' => $wonOffer->discount_value,
                'min_order_value' => $wonOffer->min_order_value ?? 0,
                'is_active' => true,
                'starts_at' => $startsAt,
                'is_single_use' => true,
                'times_used' => 0,
                'expires_at' => $expiresAt,
            ]);

            $wheelToken->update([
                'is_claimed' => true,
                'claimed_at' => now(),
                'won_promo_code_id' => $wonOffer->id,
                'generated_promo_code_id' => $generatedPromo->id,
            ]);

            return response()->json([
                'success' => true,
                'won_id' => $wonOffer->id,
                'won_index' => $winningIndex,
                'voucher' => [
                    'code' => $generatedPromo->code,
                    'discount_text' => $this->formatDiscountLabel(
                        $wonOffer->discount_type,
                        $wonOffer->discount_value,
                        $restaurant->currency
                    ),
                    'starts_at' => $startsAt->format('Y-m-d h:i A'),
                    'expires_at' => $expiresAt->format('Y-m-d'),
                    'min_order_value' => (float) ($wonOffer->min_order_value ?? 0),
                ],
            ]);
        });
    }

    /**
     * Reset an order wheel token (for administrative testing).
     */
    public function reset(string $token): JsonResponse
    {
        $wheelToken = OrderWheelToken::where('token', $token)->firstOrFail();
        $wheelToken->update([
            'is_claimed' => false,
            'claimed_at' => null,
            'won_promo_code_id' => null,
            'generated_promo_code_id' => null,
        ]);

        return response()->json(['success' => true, 'message' => 'تمت إعادة تعيين العجلة للتجربة بنجاح']);
    }

    /**
     * Render the wheel in demo mode.
     */
    private function renderDemoWheel(Request $request): Response
    {
        $restaurant = Restaurant::first();
        $currency = $this->normalizeCurrency($restaurant?->currency ?? 'ل.س');

        $firstTable = $restaurant ? RestaurantTable::where('restaurant_id', $restaurant->id)->first() : null;
        $menuUrl = $firstTable ? route('customer.menu', ['token' => $firstTable->public_token]) : null;

        return Inertia::render('Wheel', [
            'token' => 'demo',
            'is_demo' => true,
            'restaurant' => [
                'name' => $restaurant?->name ?? 'مطعم تدلّل الفاخر',
                'logo' => $restaurant?->logo ?? '/images/tedallal-golden-crop.png',
                'currency' => $currency,
                'menu_url' => $menuUrl,
                'order_url' => null,
            ],
            'order_number' => 'DEMO-88',
            'is_claimed' => false,
            'is_expired' => false,
            'claimed_voucher' => null,
            'won_slice_index' => null,
            'slices' => $this->getDefaultSlices($currency),
        ]);
    }

    /**
     * Build the 8-slice wheel collection for a restaurant.
     */
    private function buildSlicesForRestaurant(Restaurant $restaurant): array
    {
        $activeOffers = PromoCode::where('restaurant_id', $restaurant->id)
            ->where('is_active', true)
            ->where('is_single_use', false)
            ->orderBy('id')
            ->get();

        if ($activeOffers->isEmpty()) {
            return $this->getDefaultSlices($restaurant->currency);
        }

        $colors = self::SLICE_COLORS;
        $rawCount = $activeOffers->count();
        $expandedOffers = collect();

        // Ensure 8 slices by repeating if fewer than 8
        $targetCount = 8;
        $repeatTimes = (int) ceil($targetCount / max(1, $rawCount));
        for ($i = 0; $i < $repeatTimes; $i++) {
            $expandedOffers = $expandedOffers->concat($activeOffers);
        }
        $expandedOffers = $expandedOffers->take($targetCount);

        $icons = ['🎁', '⭐', '🎉', '☕', '🍰', '👑', '🔥', '🥗'];

        return $expandedOffers->values()->map(function ($offer, $index) use ($restaurant, $colors, $icons) {
            $discountText = $this->formatDiscountLabel(
                $offer->discount_type,
                $offer->discount_value,
                $restaurant->currency
            );

            return [
                'id' => $offer->id,
                'code' => $offer->code,
                'label' => $discountText,
                'discount_value' => (float) $offer->discount_value,
                'discount_type' => $offer->discount_type,
                'color' => $colors[$index % count($colors)],
                'icon' => $icons[$index % count($icons)],
            ];
        })->toArray();
    }

    /**
     * Get default attractive slices with icons and colors.
     */
    private function getDefaultSlices(string $currency): array
    {
        $cleanCurrency = $this->normalizeCurrency($currency);
        $colors = self::SLICE_COLORS;
        $rewards = [
            ['label' => 'خصم 15%', 'type' => 'percentage', 'value' => 15, 'icon' => '🔥'],
            ['label' => 'مشروب ضيافة', 'type' => 'percentage', 'value' => 10, 'icon' => '☕'],
            ['label' => 'خصم 20%', 'type' => 'percentage', 'value' => 20, 'icon' => '🎉'],
            ['label' => 'حلى مجاني', 'type' => 'percentage', 'value' => 12, 'icon' => '🍰'],
            ['label' => 'خصم 10%', 'type' => 'percentage', 'value' => 10, 'icon' => '⭐'],
            ['label' => 'خصم 25%', 'type' => 'percentage', 'value' => 25, 'icon' => '👑'],
            ['label' => 'خصم 5%', 'type' => 'percentage', 'value' => 5, 'icon' => '🎁'],
            ['label' => 'مقبلات مجانية', 'type' => 'percentage', 'value' => 15, 'icon' => '🥗'],
        ];

        $slices = [];
        foreach ($rewards as $index => $reward) {
            $slices[] = [
                'id' => $index + 1000,
                'code' => 'PROMO-' . ($index + 1),
                'label' => $reward['label'],
                'discount_value' => (float) $reward['value'],
                'discount_type' => $reward['type'],
                'color' => $colors[$index % count($colors)],
                'icon' => $reward['icon'],
            ];
        }

        return $slices;
    }

    /**
     * Format discount label cleanly without trailing zero decimals (.00),
     * and display as "خصم بقيمة X ل.س" or "خصم X%".
     */
    private function formatDiscountLabel(string $type, float|string|int $value, ?string $currency): string
    {
        $floatVal = (float) $value;
        // Strip unnecessary decimals like .00 for integer amounts (especially Syrian currency)
        $formattedNum = ($floatVal == (int) $floatVal)
            ? (string) (int) $floatVal
            : rtrim(rtrim(number_format($floatVal, 2, '.', ''), '0'), '.');

        $cleanCurrency = $this->normalizeCurrency($currency);

        if ($type === 'percentage') {
            return "خصم بقيمة {$formattedNum}%";
        }

        return "خصم بقيمة {$formattedNum} {$cleanCurrency}";
    }

    private function normalizeCurrency(?string $currency): string
    {
        if (!$currency || strtoupper(trim($currency)) === 'SYP') {
            return 'ل.س';
        }
        return $currency;
    }
}
