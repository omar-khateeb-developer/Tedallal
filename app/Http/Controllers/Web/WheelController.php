<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\OrderWheelToken;
use App\Models\PromoCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class WheelController extends Controller
{
    /**
     * Display the lucky wheel page for the given order token.
     */
    public function show(string $token): Response
    {
        $wheelToken = OrderWheelToken::where('token', $token)
            ->with(['restaurant', 'order', 'wonPromoCode', 'generatedPromoCode'])
            ->firstOrFail();

        $restaurant = $wheelToken->restaurant;

        $isExpired = $wheelToken->isExpired();
        $isClaimed = $wheelToken->is_claimed;

        $claimedVoucher = null;
        if ($isClaimed && $wheelToken->generatedPromoCode) {
            $gen = $wheelToken->generatedPromoCode;
            $claimedVoucher = [
                'code' => $gen->code,
                'discount_value' => (float) $gen->discount_value,
                'discount_type' => $gen->discount_type,
                'discount_text' => $gen->discount_type === 'percentage'
                    ? "%{$gen->discount_value}"
                    : "{$gen->discount_value} {$restaurant->currency}",
                'starts_at' => $gen->starts_at ? $gen->starts_at->format('Y-m-d h:i A') : null,
                'expires_at' => $gen->expires_at ? $gen->expires_at->format('Y-m-d') : null,
                'min_order_value' => (float) $gen->min_order_value,
                'is_used' => $gen->times_used > 0 || !$gen->is_active,
            ];
        }

        // Active master promo codes for the wheel slices
        $activeOffers = PromoCode::where('restaurant_id', $restaurant->id)
            ->where('is_active', true)
            ->where('is_single_use', false)
            ->orderBy('id')
            ->get();

        $colors = [
            '#f59e0b', '#10b981', '#3b82f6', '#ec4899',
            '#8b5cf6', '#14b8a6', '#f97316', '#6366f1'
        ];

        $slices = $activeOffers->map(function ($offer, $index) use ($restaurant, $colors) {
            $discountText = $offer->discount_type === 'percentage'
                ? "خصم %{$offer->discount_value}"
                : "خصم {$offer->discount_value} {$restaurant->currency}";

            return [
                'id' => $offer->id,
                'code' => $offer->code,
                'label' => $discountText,
                'discount_value' => (float) $offer->discount_value,
                'discount_type' => $offer->discount_type,
                'color' => $colors[$index % count($colors)],
            ];
        })->values()->toArray();

        return Inertia::render('Wheel', [
            'token' => $token,
            'restaurant' => [
                'name' => $restaurant->name,
                'logo' => $restaurant->logo,
                'currency' => $restaurant->currency,
            ],
            'order_number' => $wheelToken->order?->order_number,
            'is_claimed' => $isClaimed,
            'is_expired' => $isExpired,
            'claimed_voucher' => $claimedVoucher,
            'slices' => $slices,
        ]);
    }

    /**
     * Spin the wheel and generate a unique single-use voucher for the next visit.
     */
    public function spin(Request $request, string $token): JsonResponse
    {
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

            $activeOffers = PromoCode::where('restaurant_id', $restaurant->id)
                ->where('is_active', true)
                ->where('is_single_use', false)
                ->get();

            if ($activeOffers->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا توجد عروض نشطة متاحة حالياً.',
                ], 422);
            }

            // Pick a winning coupon
            $wonOffer = $activeOffers->random();

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
                'min_order_value' => $wonOffer->min_order_value,
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
                'voucher' => [
                    'code' => $generatedPromo->code,
                    'discount_text' => $wonOffer->discount_type === 'percentage'
                        ? "%{$wonOffer->discount_value}"
                        : "{$wonOffer->discount_value} {$restaurant->currency}",
                    'starts_at' => $startsAt->format('Y-m-d h:i A'),
                    'expires_at' => $expiresAt->format('Y-m-d'),
                    'min_order_value' => (float) $wonOffer->min_order_value,
                ],
            ]);
        });
    }
}
