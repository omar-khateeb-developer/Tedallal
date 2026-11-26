<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use App\Models\Order;
use App\Models\PromoCode;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\TableReservation;
use App\Services\CustomerCrmService;
use App\Services\TableSessionService;
use App\Services\TokenGenerator;
use App\Support\MoneyCalculator;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function __construct(
        private readonly TableSessionService $tableSessionService,
        private readonly TokenGenerator $tokenGenerator,
        private readonly CustomerCrmService $customerCrmService,
    ) {}

    public function table(Request $request, string $token): Response|\Illuminate\Http\Response
    {
        $cleanToken = trim($token);

        $table = RestaurantTable::withoutGlobalScopes()
            ->with('restaurant')
            ->whereToken($cleanToken)
            ->first();

        if (! $table) {
            return response()->view('customer.table-not-found', [
                'token' => $cleanToken,
                'reason' => 'not_found',
            ], 404);
        }

        if (! $table->isActive()) {
            return response()->view('customer.table-not-found', [
                'table' => $table,
                'token' => $cleanToken,
                'restaurant' => $table->restaurant,
                'reason' => 'inactive',
            ], 200);
        }

        if (! $table->restaurant || ! $table->restaurant->isActive()) {
            return response()->view('customer.table-not-found', [
                'table' => $table,
                'token' => $cleanToken,
                'restaurant' => $table->restaurant,
                'reason' => 'restaurant_closed',
            ], 200);
        }

        return $this->renderMenuResponse($request, $table);
    }

    /**
     * Direct restaurant public menu entry (/m/{slug}, /r/{slug}) used by WhatsApp coupons,
     * CRM campaigns, and marketing links. Always resolves an isolated Takeaway QR point so
     * external customers never collide with dining-room table sessions.
     */
    public function restaurantMenu(Request $request, string $slug): Response|\Illuminate\Http\Response
    {
        $cleanSlug = trim($slug);

        $restaurant = Restaurant::withoutGlobalScopes()
            ->where('slug', $cleanSlug)
            ->first();

        if (! $restaurant) {
            return response()->view('customer.table-not-found', [
                'token' => $cleanSlug,
                'reason' => 'not_found',
            ], 404);
        }

        if (! $restaurant->isActive()) {
            return response()->view('customer.table-not-found', [
                'token' => $cleanSlug,
                'restaurant' => $restaurant,
                'reason' => 'restaurant_closed',
            ], 200);
        }

        $takeawayPoint = RestaurantTable::withoutGlobalScopes()
            ->where('restaurant_id', $restaurant->id)
            ->where('table_type', RestaurantTable::TYPE_TAKEAWAY)
            ->where('status', 'active')
            ->orderBy('id')
            ->first();

        if (! $takeawayPoint) {
            $takeawayPoint = DB::transaction(function () use ($restaurant) {
                $existing = RestaurantTable::withoutGlobalScopes()
                    ->where('restaurant_id', $restaurant->id)
                    ->where('table_type', RestaurantTable::TYPE_TAKEAWAY)
                    ->where('status', 'active')
                    ->lockForUpdate()
                    ->orderBy('id')
                    ->first();

                if ($existing) {
                    return $existing;
                }

                return RestaurantTable::query()->create([
                    'restaurant_id' => $restaurant->id,
                    'name' => 'الطلب السفري المباشر',
                    'public_token' => $this->tokenGenerator->generatePublicToken(),
                    'status' => 'active',
                    'table_type' => RestaurantTable::TYPE_TAKEAWAY,
                ]);
            });
        }

        $takeawayPoint->setRelation('restaurant', $restaurant);

        return $this->renderMenuResponse($request, $takeawayPoint);
    }

    private function resolveTableNotice(RestaurantTable $table): ?array
    {
        if ($table->isTakeaway()) {
            return null;
        }

        $upcomingReservation = TableReservation::withoutGlobalScopes()
            ->where('restaurant_id', $table->restaurant_id)
            ->where('table_id', $table->id)
            ->whereDate('reservation_date', now()->toDateString())
            ->where('status', 'confirmed')
            ->orderBy('time_slot')
            ->first();

        if ($upcomingReservation && $upcomingReservation->time_slot) {
            try {
                $slotTime = Carbon::createFromFormat('H:i', trim($upcomingReservation->time_slot));
                $diffMinutes = now()->diffInMinutes($slotTime, false);
                if ($diffMinutes >= -20 && $diffMinutes <= 120) {
                    return [
                        'type' => 'vip_reserved',
                        'title' => 'تنبيه حجز VIP على هذه الطاولة',
                        'message' => "هذه الطاولة محجوزة باسم ضيف VIP الساعة {$upcomingReservation->time_slot} — نرحب بكم ونتمنى التنسيق مع الاستقبال عند الحاجة.",
                    ];
                }
            } catch (\Throwable) {
            }
        }

        if ($table->operational_stage === 'cleaning') {
            return [
                'type' => 'cleaning',
                'title' => 'جاري تجهيز طاولتكم الآن',
                'message' => 'أهلاً بكم! يجري حالياً تجهيز وتنظيف الطاولة، يمكنكم تصفح المنيو وبدء طلبكم بكل راحة.',
            ];
        }

        return null;
    }

    private function renderMenuResponse(Request $request, RestaurantTable $table): Response
    {
        $tableNotice = $this->resolveTableNotice($table);

        $session = $this->tableSessionService->resolveOrCreate(
            $table,
            $request->cookie('tedallal_session')
        );

        cookie()->queue(cookie(
            'tedallal_session',
            $session->session_token,
            60 * 4,
            '/',
            null,
            app()->isProduction(),
            true,
            false,
            'Lax'
        ));

        // CRITICAL: scope directly by restaurant_id from the resolved table,
        // bypassing TenantScope which cannot read the cookie yet on first visit.
        $categories = MenuCategory::withoutGlobalScopes()
            ->where('restaurant_id', $table->restaurant_id)
            ->active()
            ->orderBy('sort_order')
            ->with(['activeItems' => fn ($q) => $q->withoutGlobalScopes()->where('restaurant_id', $table->restaurant_id)->orderBy('sort_order')])
            ->get();

        $activeOrders = $session->orders()
            ->whereNotIn('status', ['served', 'cancelled'])
            ->select('id', 'order_number', 'status', 'total')
            ->get();

        $cleanCurrency = MoneyCalculator::displayCurrency($table->restaurant->currency);

        $restaurantData = array_merge($table->restaurant->toArray(), [
            'currency' => $cleanCurrency,
        ]);

        $urlPromoData = null;
        $rawCoupon = trim((string) ($request->query('coupon') ?: $request->query('promo') ?: ''));
        if ($rawCoupon !== '') {
            $cleanCode = strtoupper($rawCoupon);
            $promo = PromoCode::withoutGlobalScopes()
                ->with('freeMenuItem')
                ->where('restaurant_id', $table->restaurant_id)
                ->where(function ($q) use ($rawCoupon, $cleanCode) {
                    $q->where('code', $rawCoupon)
                        ->orWhere('code', $cleanCode);
                })
                ->first();

            if ($promo) {
                $diagnostics = $promo->getValidationDiagnostics(null, $cleanCurrency);
                $val = (float) $promo->discount_value;
                $cleanVal = ($val == (int) $val) ? (int) $val : $val;
                $discountLabel = ($promo->discount_type === 'percentage' && ! $promo->free_menu_item_id)
                    ? "خصم {$cleanVal}%"
                    : $this->customerCrmService->formatPromoLabel($promo, $table->restaurant);

                $urlPromoData = [
                    'code' => $promo->code,
                    'discount_label' => $discountLabel,
                    'discount_type' => $promo->discount_type,
                    'discount_value' => $val,
                    'min_order_value' => (float) $promo->min_order_value,
                    'valid' => (bool) $diagnostics['valid'],
                    'reason_code' => $diagnostics['reason_code'],
                    'reason_message' => $diagnostics['reason_message'],
                    'expires_at' => $promo->expires_at?->format('Y-m-d'),
                ];
            }
        }

        return Inertia::render('Customer/Menu', [
            'restaurant' => $restaurantData,
            'table' => $table,
            'categories' => $categories,
            'sessionToken' => $session->session_token,
            'openCart' => $request->boolean('cart'),
            'activeOrders' => $activeOrders,
            'urlPromo' => $urlPromoData,
            'tableNotice' => $tableNotice,
        ]);
    }

    public function cart(string $token): RedirectResponse
    {
        return redirect()->route('customer.menu', ['token' => $token, 'cart' => 1]);
    }

    public function order(Request $request, string $token, string $orderNumber): Response
    {
        $table = RestaurantTable::withoutGlobalScopes()
            ->with('restaurant')
            ->whereToken($token)
            ->firstOrFail();

        // Ensure the order exists and belongs to this table and restaurant
        $order = Order::withoutGlobalScopes()
            ->with(['items', 'table', 'tableSession', 'restaurant', 'promoCode'])
            ->where('restaurant_id', $table->restaurant_id)
            ->where('table_id', $table->id)
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        $cookieToken = (string) ($request->cookie('tedallal_session') ?? '');
        $expectedSessionToken = (string) ($order->tableSession?->session_token ?? '');
        $isSessionOwner = $cookieToken !== '' && $expectedSessionToken !== '' && hash_equals($expectedSessionToken, $cookieToken);

        $authUser = $request->user('web');
        $isRestaurantStaff = $authUser && (
            $authUser->isSuperAdmin()
            || (int) $authUser->restaurant_id === (int) $table->restaurant_id
        );

        $viewSig = (string) $request->query('view', '');
        $isSignedViewer = $viewSig !== '' && hash_equals($order->trackingSignature(), $viewSig);

        if (! $isSessionOwner && ! $isRestaurantStaff && ! $isSignedViewer) {
            abort(404);
        }

        $isReadOnly = ! $isSessionOwner;
        $wheelToken = null;
        $isWheelClaimed = false;

        // Only issue/expose wheel token & active session details to the legitimate session holder
        if ($isSessionOwner) {
            $wheelTokenModel = $order->wheelToken;
            if (! $wheelTokenModel) {
                $wheelTokenModel = $order->wheelToken()->create([
                    'restaurant_id' => $table->restaurant_id,
                    'token' => \Illuminate\Support\Str::random(32),
                    'expires_at' => now()->addDays(14),
                ]);
            }
            $wheelToken = $wheelTokenModel->token;
            $isWheelClaimed = (bool) $wheelTokenModel->is_claimed;
        }

        $cleanCurrency = MoneyCalculator::displayCurrency($table->restaurant->currency);
        $restaurantData = array_merge($table->restaurant->toArray(), [
            'currency' => $cleanCurrency,
        ]);

        return Inertia::render('Customer/OrderStatus', [
            'restaurant' => $restaurantData,
            'table' => $table,
            'orderNumber' => $orderNumber,
            'publicToken' => $token,
            'wheelToken' => $wheelToken,
            'isWheelClaimed' => $isWheelClaimed,
            'initialOrder' => (new \App\Http\Resources\OrderResource($order))->resolve(),
            'sessionToken' => $isSessionOwner ? $expectedSessionToken : null,
            'broadcastChannel' => $order->broadcastChannelKey(),
            'shareUrl' => route('customer.order', [
                'token' => $token,
                'orderNumber' => $orderNumber,
                'view' => $order->trackingSignature(),
            ]),
            'isReadOnly' => $isReadOnly,
        ]);
    }
}
