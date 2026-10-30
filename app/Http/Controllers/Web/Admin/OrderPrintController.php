<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PromoCode;
use App\Services\QrCodeService;
use App\Services\RestaurantContext;
use Illuminate\Support\Str;
use Illuminate\View\View;

class OrderPrintController extends Controller
{
    public function __construct(
        private readonly RestaurantContext $restaurantContext,
        private readonly QrCodeService $qrCodeService,
    ) {}

    public function __invoke(Order $order): View
    {
        $restaurant = $this->restaurantContext->current();

        // Ensure the order belongs to the current restaurant
        if ($order->restaurant_id !== $restaurant->id) {
            abort(404);
        }

        $order->load(['items', 'table', 'wheelToken']);

        $wheelQrBase64 = null;
        $wheelUrl = null;

        // Only generate lucky wheel QR if offers feature is enabled and restaurant has active coupons
        if (!empty($restaurant->features['offers'])) {
            $hasActiveOffers = PromoCode::where('restaurant_id', $restaurant->id)
                ->where('is_active', true)
                ->where('is_single_use', false)
                ->exists();

            if ($hasActiveOffers) {
                $wheelToken = $order->wheelToken;
                if (!$wheelToken) {
                    $wheelToken = $order->wheelToken()->create([
                        'restaurant_id' => $restaurant->id,
                        'token' => Str::random(32),
                        'expires_at' => now()->addDays(14),
                    ]);
                }

                $wheelUrl = url("/wheel/{$wheelToken->token}");
                $wheelQrBase64 = $this->qrCodeService->generateBase64ForUrl($wheelUrl, 180);
            }
        }

        return view('admin.orders.print-invoice', [
            'restaurant' => $restaurant,
            'order' => $order,
            'wheelQrBase64' => $wheelQrBase64,
            'wheelUrl' => $wheelUrl,
        ]);
    }
}
