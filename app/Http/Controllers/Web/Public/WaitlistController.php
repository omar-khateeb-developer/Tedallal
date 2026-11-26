<?php

namespace App\Http\Controllers\Web\Public;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\WaitlistEntry;
use App\Services\WaitlistNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WaitlistController extends Controller
{
    public function __construct(
        private readonly WaitlistNotificationService $notificationService,
    ) {}

    /**
     * Show live digital waitlist tracker for the customer.
     */
    public function show(string $uuid): Response
    {
        $entry = WaitlistEntry::with(['restaurant', 'assignedTable'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        $restaurant = $entry->restaurant;
        $queuePosition = $entry->getQueuePosition();

        return Inertia::render('Public/WaitlistStatus', [
            'entry' => [
                'id' => $entry->id,
                'uuid' => $entry->uuid,
                'guest_name' => $entry->guest_name,
                'queue_number' => $entry->queue_number,
                'party_size' => $entry->party_size,
                'status' => $entry->status,
                'seating_preference' => $entry->seating_preference,
                'estimated_wait_minutes' => $entry->estimated_wait_minutes,
                'queue_position' => $queuePosition,
                'notified_at' => $entry->notified_at?->toIso8601String(),
                'assigned_table' => $entry->assignedTable?->name,
                'created_at' => $entry->created_at->toIso8601String(),
            ],
            'restaurant' => [
                'id' => $restaurant->id,
                'name' => $restaurant->name,
                'slug' => $restaurant->slug,
                'logo_url' => $restaurant->logo_url,
                'currency' => $restaurant->currency ?? 'ل.س',
            ],
        ]);
    }

    /**
     * Live poll JSON endpoint for the tracking page.
     */
    public function liveStatus(string $uuid): JsonResponse
    {
        $entry = WaitlistEntry::with(['assignedTable'])
            ->where('uuid', $uuid)
            ->first();

        if (! $entry) {
            return response()->json(['error' => 'السجل غير موجود'], 404);
        }

        return response()->json([
            'status' => $entry->status,
            'queue_position' => $entry->getQueuePosition(),
            'assigned_table' => $entry->assignedTable?->name,
            'notified_at' => $entry->notified_at?->toIso8601String(),
        ]);
    }

    /**
     * Allow customer to cancel their place in line.
     */
    public function cancel(string $uuid): JsonResponse
    {
        $entry = WaitlistEntry::where('uuid', $uuid)->firstOrFail();

        if (in_array($entry->status, ['waiting', 'notified'])) {
            $entry->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);
        }

        return response()->json(['success' => true, 'status' => 'cancelled']);
    }

    /**
     * Public walk-in join waitlist form.
     */
    public function joinPage(string $slug): Response
    {
        $restaurant = Restaurant::where('slug', $slug)
            ->where('status', 'active')
            ->firstOrFail();

        $activeWaitingCount = WaitlistEntry::where('restaurant_id', $restaurant->id)
            ->where('status', 'waiting')
            ->whereDate('created_at', now()->toDateString())
            ->count();

        return Inertia::render('Public/WaitlistJoin', [
            'restaurant' => [
                'id' => $restaurant->id,
                'name' => $restaurant->name,
                'slug' => $restaurant->slug,
                'logo_url' => $restaurant->logo_url,
                'active_waiting_count' => $activeWaitingCount,
            ],
        ]);
    }

    /**
     * Store walk-in guest self registration.
     */
    public function storePublic(Request $request, string $slug): JsonResponse
    {
        $restaurant = Restaurant::where('slug', $slug)
            ->where('status', 'active')
            ->firstOrFail();

        $validated = $request->validate([
            'guest_name' => ['required', 'string', 'max:120'],
            'guest_phone' => ['required', 'string', 'max:25'],
            'party_size' => ['required', 'integer', 'min:1', 'max:20'],
            'seating_preference' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string', 'max:300'],
        ]);

        if (! \App\Support\PhoneNormalizer::isValidWhatsAppCandidate($validated['guest_phone'])) {
            return response()->json([
                'success' => false,
                'message' => 'يرجى إدخال رقم هاتف / واتساب صحيح (مثال: 0933123456 أو +963933123456).',
            ], 422);
        }

        $cleanPhone = \App\Support\PhoneNormalizer::toStorageFormat($validated['guest_phone']);

        $entry = \Illuminate\Support\Facades\DB::transaction(function () use ($restaurant, $validated, $cleanPhone) {
            $lastQueueNumber = WaitlistEntry::where('restaurant_id', $restaurant->id)
                ->whereDate('created_at', now()->toDateString())
                ->lockForUpdate()
                ->max('queue_number') ?? 0;

            return WaitlistEntry::create([
                'restaurant_id' => $restaurant->id,
                'guest_name' => $validated['guest_name'],
                'guest_phone' => $cleanPhone,
                'party_size' => $validated['party_size'],
                'seating_preference' => $validated['seating_preference'] ?? 'any',
                'estimated_wait_minutes' => 15,
                'notes' => $validated['notes'] ?? null,
                'queue_number' => $lastQueueNumber + 1,
                'status' => 'waiting',
            ]);
        });

        try {
            $this->notificationService->sendJoinedNotification($entry);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Public waitlist WA notification error: " . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'redirect_url' => $entry->getTrackingUrl(),
            'entry' => $entry,
        ]);
    }
}
