<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\OrderWheelToken;
use App\Models\PromoCode;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
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
    public function show(Request $request, string $token): mixed
    {
        try {
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

        $orderErrorMessage = null;
        $order = $wheelToken->order;
        if ($order && $order->status === \App\Enums\OrderStatus::Cancelled) {
            $orderErrorMessage = 'عذراً، تم إلغاء الطلب المرتبط بهذه الفاتورة، ولا يمكن استخدام عجلة الحظ.';
        } elseif ($restaurant->isPrepaid() && $order && $order->status === \App\Enums\OrderStatus::Pending && !$order->paid_at) {
            $orderErrorMessage = 'يرجى سداد قيمة الفاتورة لدى المحاسب (الكاشير) أولاً لتفعيل دوران عجلة الحظ.';
        }

        $table = $wheelToken->order?->table;
        $menuUrl = $table?->public_token ? route('customer.menu', ['token' => $table->public_token]) : null;
        $orderUrl = ($table?->public_token && $wheelToken->order?->order_number)
            ? route('customer.order', ['token' => $table->public_token, 'orderNumber' => $wheelToken->order->order_number])
            : null;

        return Inertia::render('Wheel', [
            'token' => $token,
            'is_demo' => false,
            'restaurant_id' => $restaurant->id,
            'has_whatsapp_feature' => $restaurant->hasFeature('whatsapp_reminders'),
            'restaurant' => [
                'id' => $restaurant->id,
                'name' => $restaurant->name,
                'logo' => $restaurant->logo,
                'currency' => $cleanCurrency,
                'menu_url' => $menuUrl,
                'order_url' => $orderUrl,
            ],
            'order_number' => $wheelToken->order?->order_number,
            'is_claimed' => $isClaimed,
            'is_expired' => $isExpired,
            'order_error_message' => $orderErrorMessage,
            'claimed_voucher' => $claimedVoucher,
            'won_slice_index' => $wonSliceIndex,
            'slices' => $slices,
        ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('WheelController show error: ' . $e->getMessage(), [
                'exception' => $e,
            ]);

            if ($token === 'demo' || $request->has('debug') || config('app.debug')) {
                return response()->json([
                    'status' => 'error',
                    'class' => get_class($e),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ], 500);
            }

            throw $e;
        }
    }

    /**
     * Spin the wheel and generate a unique single-use voucher for the next visit.
     */
    public function spin(Request $request, string $token): JsonResponse
    {
        // 1. Handle Demo Spin
        if ($token === 'demo') {
            $restaurantId = $request->input('restaurant_id') ?? $request->query('restaurant_id');
            $restaurant = $restaurantId ? Restaurant::find($restaurantId) : null;
            if (!$restaurant) {
                $restaurant = auth()->user()?->restaurant ?? Restaurant::first();
            }

            $slices = $restaurant ? $this->buildSlicesForRestaurant($restaurant) : $this->getDefaultSlices('ل.س');
            $winningIndex = $this->pickWeightedSliceIndex($slices);
            $wonSlice = $slices[$winningIndex];

            // Generate a real persisted demo promo code so the restaurant manager/customer can test it in cart immediately
            $demoCode = 'DEMO-' . strtoupper(Str::random(6));

            $discountType = $wonSlice['discount_type'] ?? 'percentage';
            $discountValue = (float) ($wonSlice['discount_value'] ?? 15);
            $minOrderValue = (float) ($wonSlice['min_order_value'] ?? 0);

            if ($restaurant) {
                PromoCode::create([
                    'restaurant_id' => $restaurant->id,
                    'code' => $demoCode,
                    'discount_type' => $discountType,
                    'discount_value' => $discountValue,
                    'min_order_value' => $minOrderValue,
                    'is_active' => true,
                    'starts_at' => now()->subHours(2), // Immediately active for testing
                    'expires_at' => now()->addDays(14),
                    'is_single_use' => true,
                    'times_used' => 0,
                ]);
            }

            return response()->json([
                'success' => true,
                'won_id' => $wonSlice['id'],
                'won_index' => $winningIndex,
                'voucher' => [
                    'code' => $demoCode,
                    'discount_text' => $wonSlice['label'],
                    'starts_at' => 'مفعّل فوراً (الآن)',
                    'expires_at' => now()->addDays(14)->format('Y-m-d'),
                    'min_order_value' => $minOrderValue,
                    'is_demo' => true,
                ],
            ]);
        }

        // 2. Real Token Spin Transaction
        return DB::transaction(function () use ($token) {
            $wheelToken = OrderWheelToken::where('token', $token)
                ->with(['order', 'restaurant'])
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

            $order = $wheelToken->order;
            if ($order && $order->status === \App\Enums\OrderStatus::Cancelled) {
                return response()->json([
                    'success' => false,
                    'message' => 'عذراً، تم إلغاء الطلب المرتبط بهذا الرمز، ولا يمكن تدوير عجلة الحظ.',
                ], 422);
            }

            $restaurant = $wheelToken->restaurant;

            if ($restaurant->isPrepaid() && $order && $order->status === \App\Enums\OrderStatus::Pending && !$order->paid_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'يرجى سداد قيمة الفاتورة لدى المحاسب (الكاشير) أولاً لتفعيل دوران عجلة الحظ.',
                ], 422);
            }

            $slices = $this->buildSlicesForRestaurant($restaurant);

            if (empty($slices)) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا توجد جوائز متاحة حالياً في العجلة.',
                ], 422);
            }

            // Pick a winning slice using weighted probability distribution
            $winningIndex = $this->pickWeightedSliceIndex($slices);
            $wonSlice = $slices[$winningIndex];

            // Resolve or ensure PromoCode in database
            $wonOffer = PromoCode::withoutGlobalScopes()
                ->where('restaurant_id', $restaurant->id)
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
            } while (PromoCode::withoutGlobalScopes()->where('restaurant_id', $restaurant->id)->where('code', $uniqueCode)->exists());

            // Next visit validity: begins in 2 hours, valid for 14 days
            $startsAt = now()->addHours(2);
            $expiresAt = now()->addDays(14);

            // Minimum order value: use manager's setting or smart safety default (3x discount)
            $minOrderValue = (float) ($wonOffer->min_order_value ?? 0);
            if ($minOrderValue <= 0 && $wonOffer->discount_type === 'fixed') {
                $minOrderValue = round((float) $wonOffer->discount_value * 3, 2);
            }

            $generatedPromo = PromoCode::create([
                'restaurant_id' => $restaurant->id,
                'code' => $uniqueCode,
                'discount_type' => $wonOffer->discount_type,
                'discount_value' => $wonOffer->discount_value,
                'min_order_value' => $minOrderValue,
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
    public function reset(Request $request, string $token): JsonResponse
    {
        $wheelToken = OrderWheelToken::where('token', $token)->firstOrFail();

        $user = auth()->user();
        if (!$user || (!$user->isSuperAdmin() && (int) $user->restaurant_id !== (int) $wheelToken->restaurant_id)) {
            return response()->json(['success' => false, 'message' => 'غير مصرح.'], 403);
        }

        // Deactivate previously generated voucher if any to prevent abuse
        if ($wheelToken->generated_promo_code_id) {
            PromoCode::withoutGlobalScopes()
                ->where('id', $wheelToken->generated_promo_code_id)
                ->update(['is_active' => false]);
        }

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
        $restaurantId = $request->query('restaurant_id') ?? $request->input('restaurant_id');
        $restaurant = $restaurantId ? Restaurant::find($restaurantId) : null;
        if (!$restaurant) {
            $restaurant = auth()->user()?->restaurant ?? Restaurant::first();
        }
        if (!$restaurant) {
            abort(404);
        }
        $currency = $this->normalizeCurrency($restaurant->currency ?? 'ل.س');

        $firstTable = null;
        try {
            $firstTable = RestaurantTable::withoutGlobalScopes()->where('restaurant_id', $restaurant->id)->first();
        } catch (\Throwable $e) {
            // Silently fallback if table query is unresolvable
        }
        $menuUrl = ($firstTable && $firstTable->public_token) ? route('customer.menu', ['token' => $firstTable->public_token]) : null;

        $slices = $this->buildSlicesForRestaurant($restaurant);

        return Inertia::render('Wheel', [
            'token' => 'demo',
            'is_demo' => true,
            'restaurant_id' => $restaurant->id,
            'has_whatsapp_feature' => $restaurant->hasFeature('whatsapp_reminders'),
            'restaurant' => [
                'id' => $restaurant->id,
                'name' => $restaurant->name ?? 'مطعم تدلّل الفاخر',
                'logo' => $restaurant->logo ?? '/images/tedallal-golden-crop.png',
                'currency' => $currency,
                'menu_url' => $menuUrl,
                'order_url' => null,
            ],
            'order_number' => 'DEMO-88',
            'is_claimed' => false,
            'is_expired' => false,
            'claimed_voucher' => null,
            'won_slice_index' => null,
            'slices' => $slices,
        ]);
    }

    /**
     * Build the 8-slice wheel collection for a restaurant.
     */
    private function buildSlicesForRestaurant(Restaurant $restaurant): array
    {
        $activeOffers = PromoCode::withoutGlobalScopes()
            ->where('restaurant_id', $restaurant->id)
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
                'min_order_value' => (float) $offer->min_order_value,
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

    /**
     * Pick a winning slice using weighted probability distribution.
     * Higher probability for smaller discounts to ensure safe unit economics.
     */
    private function pickWeightedSliceIndex(array $slices): int
    {
        $count = count($slices);
        if ($count <= 1) {
            return 0;
        }

        $weights = [];
        foreach ($slices as $idx => $slice) {
            $val = (float) ($slice['discount_value'] ?? 10);
            $type = $slice['discount_type'] ?? 'percentage';

            if ($type === 'percentage') {
                if ($val <= 10) {
                    $weights[$idx] = 45; // 45% pool
                } elseif ($val <= 15) {
                    $weights[$idx] = 30; // 30% pool
                } elseif ($val <= 20) {
                    $weights[$idx] = 18; // 18% pool
                } else {
                    $weights[$idx] = 7;  // 7% pool (rare grand prize)
                }
            } else {
                // Fixed discounts
                $weights[$idx] = 25;
            }
        }

        $totalWeight = array_sum($weights);
        $rand = random_int(1, max(1, $totalWeight));
        $acc = 0;

        foreach ($weights as $idx => $w) {
            $acc += $w;
            if ($rand <= $acc) {
                return $idx;
            }
        }

        return 0;
    }

    private function normalizeCurrency(?string $currency): string
    {
        if (!$currency || strtoupper(trim($currency)) === 'SYP') {
            return 'ل.س';
        }
        return $currency;
    }
}
