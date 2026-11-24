<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Services\RestaurantContext;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly RestaurantContext $restaurantContext,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Dashboard');
    }

    public function orders(): Response
    {
        return Inertia::render('Admin/Orders');
    }

    public function tables(): Response
    {
        $restaurant = $this->restaurantContext->current();

        $tables = RestaurantTable::query()
            ->where('restaurant_id', $restaurant->id)
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Tables', [
            'tables' => \App\Http\Resources\RestaurantTableResource::collection($tables)->resolve(),
            'restaurant' => $restaurant,
        ]);
    }

    public function floorPlan(): Response
    {
        $restaurant = $this->restaurantContext->current();

        $tables = RestaurantTable::query()
            ->where('restaurant_id', $restaurant->id)
            ->dineIn()
            ->with(['activeSession'])
            ->orderBy('name')
            ->get();

        $zones = RestaurantTable::query()
            ->where('restaurant_id', $restaurant->id)
            ->dineIn()
            ->whereNotNull('zone')
            ->distinct()
            ->pluck('zone')
            ->toArray();

        $defaultZones = ['main', 'vip', 'terrace'];
        $allZones = array_values(array_unique(array_merge($defaultZones, $zones)));

        $todayReservations = \App\Models\TableReservation::where('restaurant_id', $restaurant->id)
            ->where('reservation_date', now()->toDateString())
            ->whereIn('status', ['confirmed', 'seated'])
            ->get(['id', 'uuid', 'table_id', 'guest_name', 'time_slot', 'guests_count', 'status']);

        $todayWaitlist = \App\Models\WaitlistEntry::with(['assignedTable'])
            ->where('restaurant_id', $restaurant->id)
            ->whereDate('created_at', now()->toDateString())
            ->orderByRaw("CASE WHEN status = 'waiting' THEN 1 WHEN status = 'notified' THEN 2 ELSE 3 END")
            ->orderBy('queue_number')
            ->get()
            ->map(fn($e) => [
                'id' => $e->id,
                'uuid' => $e->uuid,
                'guest_name' => $e->guest_name,
                'guest_phone' => $e->guest_phone,
                'party_size' => $e->party_size,
                'seating_preference' => $e->seating_preference,
                'status' => $e->status,
                'queue_number' => $e->queue_number,
                'estimated_wait_minutes' => $e->estimated_wait_minutes,
                'waiting_minutes' => max(0, (int) $e->created_at->diffInMinutes(now())),
                'notified_at' => $e->notified_at?->toIso8601String(),
                'assigned_table' => $e->assignedTable ? [
                    'id' => $e->assignedTable->id,
                    'name' => $e->assignedTable->name,
                ] : null,
                'notes' => $e->notes,
                'tracking_url' => $e->getTrackingUrl(),
            ]);

        return Inertia::render('Admin/FloorPlan', [
            'tables' => \App\Http\Resources\RestaurantTableResource::collection($tables)->resolve(),
            'zones' => $allZones,
            'todayReservations' => $todayReservations,
            'todayWaitlist' => $todayWaitlist,
            'restaurant' => $restaurant,
            'isFeatureLocked' => ! $restaurant->hasFloorPlanFeature(),
        ]);
    }

    public function menuCategories(): Response
    {
        return Inertia::render('Admin/MenuCategories');
    }

    public function menuItems(): Response
    {
        return Inertia::render('Admin/MenuItems');
    }

    public function settings(): Response
    {
        return Inertia::render('Admin/Settings');
    }

    public function users(): Response
    {
        return Inertia::render('Admin/Users');
    }
}
