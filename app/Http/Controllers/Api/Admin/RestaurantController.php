<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\RestaurantResource;
use App\Http\Requests\UpdateRestaurantSettingsRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderWheelToken;
use App\Models\TableSession;
use App\Models\WaitlistEntry;
use App\Services\RestaurantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RestaurantController extends ApiController
{
    public function __construct(
        private readonly RestaurantContext $restaurantContext,
    ) {}

    public function show()
    {
        return $this->success(new RestaurantResource($this->restaurantContext->current()));
    }

    public function update(UpdateRestaurantSettingsRequest $request)
    {
        $restaurant = $this->restaurantContext->current();
        $validated = $request->validated();

        if ($request->hasFile('navbar_image')) {
            $path = $request->file('navbar_image')->store('restaurants', 'public');
            $validated['navbar_image'] = '/storage/' . $path;
        }

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('restaurants', 'public');
            $validated['logo'] = '/storage/' . $path;
        }

        if ($request->has('auto_print_trigger')) {
            $features = $restaurant->features ?? [];
            $trigger = (string) $request->input('auto_print_trigger');
            $features['auto_print_trigger'] = $trigger;
            $features['auto_print_on_ready'] = $trigger === 'on_ready';
            $validated['features'] = $features;
            unset($validated['auto_print_trigger'], $validated['auto_print_on_ready']);
        } elseif ($request->has('auto_print_on_ready')) {
            $features = $restaurant->features ?? [];
            $onReady = (bool) $request->boolean('auto_print_on_ready');
            $features['auto_print_on_ready'] = $onReady;
            $features['auto_print_trigger'] = $onReady ? 'on_ready' : 'off';
            $validated['features'] = $features;
            unset($validated['auto_print_on_ready']);
        }

        if ($request->has('accounting_mapping')) {
            $features = $validated['features'] ?? ($restaurant->features ?? []);
            $features['accounting_mapping'] = array_merge(
                (array) ($features['accounting_mapping'] ?? []),
                (array) ($request->input('accounting_mapping') ?? [])
            );
            $validated['features'] = $features;
            unset($validated['accounting_mapping']);
        }

        $restaurant->update($validated);
        $this->restaurantContext->forget();

        return $this->success(new RestaurantResource($restaurant->fresh()));
    }

    /**
     * Safe clean-slate reset of test/sandbox data (Orders, Sessions, Wheel tokens, Waitlist)
     * for pilot venue launch readiness, while strictly keeping menu items, tables, and settings.
     */
    public function resetTestData(Request $request)
    {
        $restaurant = $this->restaurantContext->current();
        $user = $request->user();

        // Require admin / super_admin permission
        if (! $user || (! $user->isAdmin() && ! $user->isSuperAdmin())) {
            return response()->json([
                'success' => false,
                'message' => 'عذراً، ميزة تصفير بيانات التجارب محصورة بمدير المطعم فقط.',
            ], 403);
        }

        $request->validate([
            'password' => ['required', 'string'],
            'confirmation' => ['required', 'string', 'in:تصفير بيانات التجارب'],
        ]);

        if (! Hash::check($request->input('password'), $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'كلمة المرور غير صحيحة.',
            ], 422);
        }

        return DB::transaction(function () use ($restaurant) {
            $restaurantId = $restaurant->id;

            // 1. Delete Order Items via orders
            $orderIds = Order::withoutGlobalScopes()
                ->where('restaurant_id', $restaurantId)
                ->pluck('id');

            if ($orderIds->isNotEmpty()) {
                OrderItem::withoutGlobalScopes()
                    ->whereIn('order_id', $orderIds)
                    ->delete();
            }

            $deletedOrdersCount = Order::withoutGlobalScopes()
                ->where('restaurant_id', $restaurantId)
                ->delete();

            // 2. Clear cart items & table sessions (table_sessions belongs to restaurant_tables)
            $tableIds = \App\Models\RestaurantTable::withoutGlobalScopes()
                ->where('restaurant_id', $restaurantId)
                ->pluck('id');

            $sessionIds = $tableIds->isNotEmpty()
                ? TableSession::withoutGlobalScopes()->whereIn('table_id', $tableIds)->pluck('id')
                : collect();

            if ($sessionIds->isNotEmpty()) {
                \App\Models\CartItem::withoutGlobalScopes()
                    ->whereIn('table_session_id', $sessionIds)
                    ->delete();

                $resetSessionsCount = TableSession::withoutGlobalScopes()
                    ->whereIn('id', $sessionIds)
                    ->delete();
            } else {
                $resetSessionsCount = 0;
            }

            // 3. Clear wheel tokens (both test and live)
            $clearedTokensCount = OrderWheelToken::withoutGlobalScopes()
                ->where('restaurant_id', $restaurantId)
                ->delete();

            // 4. Clear test waitlist entries, service calls & VIP reservations
            $clearedWaitlistCount = WaitlistEntry::withoutGlobalScopes()
                ->where('restaurant_id', $restaurantId)
                ->delete();

            \App\Models\TableServiceCall::withoutGlobalScopes()
                ->where('restaurant_id', $restaurantId)
                ->delete();

            \App\Models\TableReservation::withoutGlobalScopes()
                ->where('restaurant_id', $restaurantId)
                ->delete();

            \App\Models\RestaurantTable::withoutGlobalScopes()
                ->where('restaurant_id', $restaurantId)
                ->update([
                    'operational_stage' => 'available',
                    'stage_updated_at' => null,
                    'session_started_at' => null,
                    'bill_requested_at' => null,
                    'current_guest_name' => null,
                ]);

            return $this->success([
                'message' => 'تم تصفير جميع طلبات التجارب والجلسات بنجاح. مطعمك جاهز تماماً للافتتاح الرسمي!',
                'deleted_orders' => $deletedOrdersCount,
                'reset_sessions' => $resetSessionsCount,
                'cleared_tokens' => $clearedTokensCount,
                'cleared_waitlist' => $clearedWaitlistCount,
            ]);
        });
    }
}
