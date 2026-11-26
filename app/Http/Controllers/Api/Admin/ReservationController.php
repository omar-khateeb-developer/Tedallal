<?php

namespace App\Http\Controllers\Api\Admin;

use App\Events\TableUpdated;
use App\Http\Controllers\Api\ApiController;
use App\Models\TableReservation;
use App\Services\RestaurantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReservationController extends ApiController
{
    public function __construct(
        private readonly RestaurantContext $restaurantContext,
    ) {}

    /**
     * List VIP reservations for the current restaurant.
     */
    public function index(Request $request): JsonResponse
    {
        $restaurant = $this->restaurantContext->current();

        $query = TableReservation::with('table')
            ->where('restaurant_id', $restaurant->id);

        if ($request->filled('date')) {
            $query->whereDate('reservation_date', $request->date);
        } else {
            // Default: today onwards
            $query->whereDate('reservation_date', '>=', now()->toDateString());
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            // Default: exclude unverified expired holds
            $query->where(function ($q) {
                $q->whereIn('status', ['confirmed', 'seated'])
                  ->orWhere(function ($sub) {
                      $sub->where('status', 'pending_verification')
                          ->where('otp_expires_at', '>', now());
                  });
            });
        }

        $reservations = $query->orderBy('reservation_date')
            ->orderBy('time_slot')
            ->get()
            ->map(fn(TableReservation $r) => [
                'id' => $r->id,
                'uuid' => $r->uuid,
                'code' => $r->getFormattedCode(),
                'guest_name' => $r->guest_name,
                'guest_phone' => $r->guest_phone,
                'reservation_date' => $r->reservation_date->format('Y-m-d'),
                'time_slot' => $r->time_slot,
                'guests_count' => $r->guests_count,
                'special_requests' => $r->special_requests,
                'status' => $r->status,
                'table_id' => $r->table_id,
                'table_name' => $r->table?->name,
                'table_zone' => $r->table?->zone,
                'created_at' => $r->created_at->toIso8601String(),
            ]);

        return $this->success($reservations);
    }

    /**
     * Update status of a VIP reservation (e.g. seated, cancelled, completed).
     */
    public function updateStatus(Request $request, TableReservation $reservation): JsonResponse
    {
        $restaurant = $this->restaurantContext->current();

        if ($reservation->restaurant_id !== $restaurant->id) {
            abort(403, 'هذا الحجز لا ينتمي لمطعمك.');
        }

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:confirmed,seated,cancelled,completed,no_show'],
            'staff_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $reservation->update([
            'status' => $validated['status'],
            'confirmed_by_staff_id' => $request->user()?->id,
            'staff_notes' => $validated['staff_notes'] ?? $reservation->staff_notes,
        ]);

        if ($validated['status'] === 'seated' && $reservation->table) {
            $reservation->table->update([
                'operational_stage' => 'seated',
                'stage_updated_at' => now(),
                'session_started_at' => $reservation->table->session_started_at ?? now(),
                'current_guest_name' => $reservation->guest_name,
            ]);
            TableUpdated::dispatchSafe($reservation->table);
        }

        \App\Services\ActivityLogger::log('update_vip_reservation', "قام بتحديث حالة حجز VIP ({$reservation->getFormattedCode()}) إلى: {$validated['status']}");

        return $this->success([
            'message' => 'تم تحديث حالة الحجز بنجاح.',
            'reservation' => $reservation->fresh(['table']),
        ]);
    }
}
