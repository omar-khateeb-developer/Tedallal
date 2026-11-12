<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\MenuItem;
use App\Models\PromoCode;
use App\Models\RestaurantCustomer;
use App\Services\CustomerCrmService;
use App\Services\RestaurantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerCrmController extends ApiController
{
    public function __construct(
        private readonly RestaurantContext $restaurantContext,
        private readonly CustomerCrmService $crmService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $restaurant = $this->restaurantContext->current();

        if (! $restaurant->hasWhatsappRemindersFeature()) {
            return response()->json([
                'success' => false,
                'feature_locked' => true,
                'message' => 'نظام الـ CRM وتذكيرات الواتساب متاح حصرياً للباقة الشاملة (Max) أو عند تفعيله لمطعمك.',
            ], 403);
        }

        $loyalty = $restaurant->loyaltyConfig();
        $targetVisits = (int) $loyalty['target_visits'];

        $allCustomers = RestaurantCustomer::withoutGlobalScopes()
            ->where('restaurant_id', $restaurant->id)
            ->with(['promoCodes' => fn ($q) => $q->withoutGlobalScopes()->with('freeMenuItem')->latest()])
            ->latest('last_visit_at')
            ->latest('id')
            ->get();

        // Compute summary KPIs cleanly
        $totalCustomers = $allCustomers->count();
        $returningCustomers = $allCustomers->where('visits_count', '>=', 2)->count();
        $activeCouponsCount = 0;
        $needsReminderCount = 0;
        $loyaltyEligibleCount = 0;

        $mapped = $allCustomers->map(function (RestaurantCustomer $customer) use (
            $restaurant,
            $loyalty,
            $targetVisits,
            &$activeCouponsCount,
            &$needsReminderCount,
            &$loyaltyEligibleCount
        ) {
            $codes = $customer->promoCodes->map(function (PromoCode $promo) use ($restaurant, $customer, &$activeCouponsCount, &$needsReminderCount) {
                $isUsed = ($promo->is_single_use && $promo->times_used > 0);
                $isExpired = $promo->expires_at && $promo->expires_at->isPast();
                $isFuture = !$promo->isDemoCode() && $promo->starts_at && $promo->starts_at->isFuture();
                $isUsable = $promo->is_active && !$isUsed && !$isExpired;

                if ($isUsable) {
                    $activeCouponsCount++;
                    if (! $promo->reminded_at) {
                        $needsReminderCount++;
                    }
                }

                $status = 'active';
                if ($isUsed) {
                    $status = 'used';
                } elseif ($isExpired) {
                    $status = 'expired';
                } elseif (! $promo->is_active) {
                    $status = 'inactive';
                } elseif ($isFuture) {
                    $status = 'scheduled';
                }

                $reminderPayload = $isUsable
                    ? $this->crmService->buildReminderPayload($restaurant, $customer, $promo)
                    : null;

                return [
                    'id' => $promo->id,
                    'code' => $promo->code,
                    'source' => $promo->source ?? 'wheel',
                    'discount_label' => $this->crmService->formatPromoLabel($promo, $restaurant),
                    'discount_value' => (float) $promo->discount_value,
                    'discount_type' => $promo->discount_type,
                    'starts_at' => $promo->starts_at?->format('Y-m-d h:i A'),
                    'expires_at' => $promo->expires_at?->format('Y-m-d'),
                    'status' => $status,
                    'is_usable' => $isUsable,
                    'reminded_at' => $promo->reminded_at?->format('Y-m-d h:i A'),
                    'reminders_count' => (int) ($promo->reminders_count ?? 0),
                    'whatsapp_url' => $reminderPayload['whatsapp_url'] ?? null,
                    'whatsapp_message' => $reminderPayload['message'] ?? null,
                ];
            });

            $hasUsablePromo = $codes->contains('is_usable', true);
            $hasUnremindedPromo = $codes->contains(fn ($c) => $c['is_usable'] && ! $c['reminded_at']);
            $hasActiveLoyaltyPromo = $codes->contains(fn ($c) => $c['source'] === 'loyalty' && $c['is_usable']);

            if ($hasActiveLoyaltyPromo || ($loyalty['enabled'] && $customer->visits_count >= $targetVisits)) {
                $loyaltyEligibleCount++;
            }

            $visits = (int) $customer->visits_count;
            $remainingVisits = $loyalty['enabled']
                ? ($visits % $targetVisits === 0 ? 0 : ($targetVisits - ($visits % $targetVisits)))
                : null;

            return [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'visits_count' => $visits,
                'notes' => $customer->notes,
                'last_visit_at' => $customer->last_visit_at?->format('Y-m-d h:i A') ?? $customer->created_at?->format('Y-m-d h:i A'),
                'has_usable_promo' => $hasUsablePromo,
                'needs_reminder' => $hasUnremindedPromo,
                'has_active_loyalty_promo' => $hasActiveLoyaltyPromo,
                'remaining_visits_for_loyalty' => $remainingVisits,
                'promo_codes' => $codes->values(),
            ];
        });

        // Apply optional filter & search
        $filter = $request->query('filter', 'all');
        $search = trim((string) $request->query('search', ''));

        $filtered = $mapped->filter(function ($row) use ($filter, $search) {
            if ($filter === 'needs_reminder' && ! $row['needs_reminder']) {
                return false;
            }
            if ($filter === 'has_active' && ! $row['has_usable_promo']) {
                return false;
            }
            if ($filter === 'returning' && $row['visits_count'] < 2) {
                return false;
            }
            if ($filter === 'loyalty' && ! $row['has_active_loyalty_promo'] && $row['remaining_visits_for_loyalty'] !== 0) {
                return false;
            }
            if ($search !== '') {
                $q = mb_strtolower($search, 'UTF-8');
                $inName = str_contains(mb_strtolower($row['name'], 'UTF-8'), $q);
                $inPhone = str_contains($row['phone'], $search);
                $inCodes = collect($row['promo_codes'])->contains(fn ($c) => str_contains(strtolower($c['code']), strtolower($search)));
                if (! $inName && ! $inPhone && ! $inCodes) {
                    return false;
                }
            }
            return true;
        })->values();

        // Active menu items for selecting a "Free Menu Item" as grand loyalty prize
        $menuItems = MenuItem::withoutGlobalScopes()
            ->where('restaurant_id', $restaurant->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'price']);

        return $this->success([
            'customers' => $filtered,
            'stats' => [
                'total_customers' => $totalCustomers,
                'returning_customers' => $returningCustomers,
                'active_coupons' => $activeCouponsCount,
                'needs_reminder' => $needsReminderCount,
                'loyalty_eligible' => $loyaltyEligibleCount,
            ],
            'crm_settings' => [
                'wheel_require_whatsapp' => $restaurant->shouldRequireWheelWhatsapp(),
                'wheel_reveal_code_after_save' => $restaurant->shouldRevealWheelCodeAfterSave(),
                'loyalty' => $loyalty,
            ],
            'menu_items' => $menuItems,
            'currency' => $this->crmService->normalizeCurrency($restaurant->currency),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $restaurant = $this->restaurantContext->current();

        $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'phone' => ['required', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $name = $this->crmService->validateAndNormalizeName((string) $request->input('name'));
        $phone = $this->crmService->validateAndNormalizePhone((string) $request->input('phone'));

        $existing = RestaurantCustomer::withoutGlobalScopes()
            ->where('restaurant_id', $restaurant->id)
            ->where('phone', $phone)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => "رقم الهاتف ({$phone}) مسجل مسبقاً في مطعمك باسم «{$existing->name}».",
            ], 422);
        }

        $customer = RestaurantCustomer::withoutGlobalScopes()->create([
            'restaurant_id' => $restaurant->id,
            'name' => $name,
            'phone' => $phone,
            'visits_count' => 1,
            'notes' => $request->input('notes'),
            'last_visit_at' => now(),
        ]);

        return $this->success($customer, 201);
    }

    public function update(Request $request, int $customerId): JsonResponse
    {
        $restaurant = $this->restaurantContext->current();

        $customer = RestaurantCustomer::withoutGlobalScopes()
            ->where('restaurant_id', $restaurant->id)
            ->findOrFail($customerId);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if (isset($validated['name'])) {
            $validated['name'] = $this->crmService->validateAndNormalizeName($validated['name']);
        }

        $customer->update($validated);

        return $this->success($customer->fresh());
    }

    public function destroy(int $customerId): JsonResponse
    {
        $restaurant = $this->restaurantContext->current();

        $customer = RestaurantCustomer::withoutGlobalScopes()
            ->where('restaurant_id', $restaurant->id)
            ->findOrFail($customerId);

        $customer->delete();

        return $this->success(['deleted' => true]);
    }

    public function markReminded(Request $request, int $customerId): JsonResponse
    {
        $restaurant = $this->restaurantContext->current();

        $customer = RestaurantCustomer::withoutGlobalScopes()
            ->where('restaurant_id', $restaurant->id)
            ->findOrFail($customerId);

        $request->validate([
            'promo_code_id' => ['required', 'integer'],
        ]);

        $promo = PromoCode::withoutGlobalScopes()
            ->where('restaurant_id', $restaurant->id)
            ->where('customer_id', $customer->id)
            ->findOrFail($request->integer('promo_code_id'));

        $promo->update([
            'reminded_at' => now(),
            'reminders_count' => (int) $promo->reminders_count + 1,
        ]);

        $payload = $this->crmService->buildReminderPayload($restaurant, $customer, $promo);

        return $this->success([
            'reminded_at' => $promo->reminded_at->format('Y-m-d h:i A'),
            'reminders_count' => $promo->reminders_count,
            'whatsapp_url' => $payload['whatsapp_url'],
            'whatsapp_message' => $payload['message'],
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $restaurant = $this->restaurantContext->current();

        if (! $restaurant->hasWhatsappRemindersFeature()) {
            return response()->json([
                'success' => false,
                'message' => 'هذه الميزة غير مفعلة لهذا المطعم.',
            ], 403);
        }

        $validated = $request->validate([
            'wheel_require_whatsapp' => ['required', 'boolean'],
            'wheel_reveal_code_after_save' => ['required', 'boolean'],
            'loyalty_enabled' => ['required', 'boolean'],
            'loyalty_target_visits' => ['required', 'integer', 'min:2', 'max:50'],
            'loyalty_reward_type' => ['required', 'string', 'in:percentage,fixed,free_item'],
            'loyalty_reward_value' => ['nullable', 'numeric', 'min:1'],
            'loyalty_menu_item_id' => ['nullable', 'integer'],
        ]);

        if ($validated['loyalty_reward_type'] === 'percentage' && (float) ($validated['loyalty_reward_value'] ?? 0) > 100) {
            return response()->json([
                'success' => false,
                'message' => 'النسبة المئوية للخصم يجب ألا تتجاوز 100%.',
            ], 422);
        }

        if ($validated['loyalty_reward_type'] === 'free_item') {
            $menuItem = MenuItem::withoutGlobalScopes()
                ->where('restaurant_id', $restaurant->id)
                ->find($validated['loyalty_menu_item_id'] ?? 0);
            if (! $menuItem) {
                return response()->json([
                    'success' => false,
                    'message' => 'يرجى اختيار صنف صالح من قائمة مطعمك للجائزة المجانية.',
                ], 422);
            }
            $validated['loyalty_reward_value'] = (float) $menuItem->price;
        }

        $features = is_array($restaurant->features) ? $restaurant->features : [];
        $features['wheel_require_whatsapp'] = (bool) $validated['wheel_require_whatsapp'];
        $features['wheel_reveal_code_after_save'] = (bool) $validated['wheel_reveal_code_after_save'];
        $features['loyalty_enabled'] = (bool) $validated['loyalty_enabled'];
        $features['loyalty_target_visits'] = (int) $validated['loyalty_target_visits'];
        $features['loyalty_reward_type'] = $validated['loyalty_reward_type'];
        $features['loyalty_reward_value'] = (float) ($validated['loyalty_reward_value'] ?? 50);
        $features['loyalty_menu_item_id'] = $validated['loyalty_reward_type'] === 'free_item'
            ? (int) $validated['loyalty_menu_item_id']
            : null;

        $restaurant->update(['features' => $features]);
        $this->restaurantContext->forget();

        return $this->success([
            'wheel_require_whatsapp' => $restaurant->shouldRequireWheelWhatsapp(),
            'wheel_reveal_code_after_save' => $restaurant->shouldRevealWheelCodeAfterSave(),
            'loyalty' => $restaurant->fresh()->loyaltyConfig(),
        ]);
    }
}

