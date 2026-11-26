<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\RestaurantTable;
use App\Models\WaitlistEntry;
use App\Services\ActivityLogger;
use App\Services\RestaurantContext;
use App\Services\WaitlistNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WaitlistController extends ApiController
{
    public function __construct(
        private readonly RestaurantContext $restaurantContext,
        private readonly WaitlistNotificationService $notificationService,
    ) {}

    /**
     * List waitlist entries for the current restaurant today.
     */
    public function index(Request $request): JsonResponse
    {
        $restaurant = $this->restaurantContext->current();

        $query = WaitlistEntry::with(['assignedTable'])
            ->where('restaurant_id', $restaurant->id)
            ->whereDate('created_at', now()->toDateString());

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            // Default: active queue (waiting and notified) first, then recently seated/cancelled
            $query->orderByRaw("CASE WHEN status = 'waiting' THEN 1 WHEN status = 'notified' THEN 2 ELSE 3 END")
                  ->orderBy('queue_number');
        }

        $entries = $query->get()->map(function (WaitlistEntry $entry) {
            return [
                'id' => $entry->id,
                'uuid' => $entry->uuid,
                'guest_name' => $entry->guest_name,
                'guest_phone' => $entry->guest_phone,
                'party_size' => $entry->party_size,
                'seating_preference' => $entry->seating_preference,
                'status' => $entry->status,
                'queue_number' => $entry->queue_number,
                'estimated_wait_minutes' => $entry->estimated_wait_minutes,
                'waiting_minutes' => max(0, (int) $entry->created_at->diffInMinutes(now())),
                'notified_at' => $entry->notified_at?->toIso8601String(),
                'seated_at' => $entry->seated_at?->toIso8601String(),
                'notes' => $entry->notes,
                'assigned_table' => $entry->assignedTable ? [
                    'id' => $entry->assignedTable->id,
                    'name' => $entry->assignedTable->name,
                ] : null,
                'tracking_url' => $entry->getTrackingUrl(),
                'created_at' => $entry->created_at->toIso8601String(),
            ];
        });

        $waitingCount = $entries->where('status', 'waiting')->count();
        $notifiedCount = $entries->where('status', 'notified')->count();

        return $this->success([
            'entries' => $entries,
            'summary' => [
                'waiting_count' => $waitingCount,
                'notified_count' => $notifiedCount,
                'total_today' => $entries->count(),
            ],
        ]);
    }

    /**
     * Add a walk-in guest to the digital waitlist.
     */
    public function store(Request $request): JsonResponse
    {
        $restaurant = $this->restaurantContext->current();

        $validated = $request->validate([
            'guest_name' => ['required', 'string', 'max:120'],
            'guest_phone' => ['required', 'string', 'max:25'],
            'party_size' => ['required', 'integer', 'min:1', 'max:30'],
            'seating_preference' => ['nullable', 'string', 'max:32'],
            'estimated_wait_minutes' => ['nullable', 'integer', 'min:1', 'max:180'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $entry = \Illuminate\Support\Facades\DB::transaction(function () use ($restaurant, $validated) {
            // Lock today's rows for this restaurant to avoid duplicate queue numbers under concurrent requests
            $lastQueueNumber = WaitlistEntry::where('restaurant_id', $restaurant->id)
                ->whereDate('created_at', now()->toDateString())
                ->lockForUpdate()
                ->max('queue_number') ?? 0;

            return WaitlistEntry::create([
                'restaurant_id' => $restaurant->id,
                'guest_name' => $validated['guest_name'],
                'guest_phone' => $validated['guest_phone'],
                'party_size' => $validated['party_size'],
                'seating_preference' => $validated['seating_preference'] ?? 'any',
                'estimated_wait_minutes' => $validated['estimated_wait_minutes'] ?? 15,
                'notes' => $validated['notes'] ?? null,
                'queue_number' => $lastQueueNumber + 1,
                'status' => 'waiting',
            ]);
        });

        // Automatically trigger WhatsApp confirmation if connected
        $waSent = false;
        try {
            $waSent = $this->notificationService->sendJoinedNotification($entry);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Waitlist WA notification failed: " . $e->getMessage());
        }

        ActivityLogger::log('waitlist_add', "تمت إضافة الزبون ({$entry->guest_name}) إلى طابور الانتظار رقم #{$entry->queue_number}");

        return $this->success([
            'message' => 'تمت إضافة الزبون لطابور الانتظار بنجاح' . ($waSent ? ' وإرسال إشعار الواتساب' : ''),
            'entry' => $entry,
            'whatsapp_sent' => $waSent,
        ], 201);
    }

    /**
     * Notify waiting guest that a table is ready (via WhatsApp).
     */
    public function notifyGuest(Request $request, WaitlistEntry $entry): JsonResponse
    {
        $restaurant = $this->restaurantContext->current();
        if ($entry->restaurant_id !== $restaurant->id) {
            abort(403);
        }

        $validated = $request->validate([
            'table_id' => ['nullable', 'integer', 'exists:restaurant_tables,id'],
        ]);

        $table = null;
        if (! empty($validated['table_id'])) {
            $table = RestaurantTable::where('restaurant_id', $restaurant->id)->dineIn()->find($validated['table_id']);
            if ($table) {
                $entry->update(['assigned_table_id' => $table->id]);
            }
        }

        $entry->update([
            'status' => 'notified',
            'notified_at' => now(),
        ]);

        $waSent = false;
        try {
            $waSent = $this->notificationService->sendTableReadyNotification($entry, $table);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Table ready WA notification failed: " . $e->getMessage());
        }

        ActivityLogger::log('waitlist_notify', "تم إشعار الزبون ({$entry->guest_name}) بجاهزية الطاولة");

        return $this->success([
            'message' => 'تم إرسال إشعار الجاهزية للزبون' . ($waSent ? ' عبر الواتساب بنجاح' : ''),
            'entry' => $entry->fresh(['assignedTable']),
            'whatsapp_sent' => $waSent,
        ]);
    }

    /**
     * Seat the guest at a table.
     */
    public function seatGuest(Request $request, WaitlistEntry $entry): JsonResponse
    {
        $restaurant = $this->restaurantContext->current();
        if ($entry->restaurant_id !== $restaurant->id) {
            abort(403);
        }

        $validated = $request->validate([
            'table_id' => ['required', 'integer', 'exists:restaurant_tables,id'],
        ]);

        $table = RestaurantTable::where('restaurant_id', $restaurant->id)->dineIn()->findOrFail($validated['table_id']);

        $stage = $table->operational_stage ?? 'available';
        if ($table->activeSession()->exists() || ! in_array($stage, ['available', 'cleaning'], true)) {
            return $this->error("الطاولة ({$table->name}) مشغولة حالياً ولا يمكن إجلاس زبون جديد عليها قبل إغلاق الجلسة الحالية.", 422);
        }

        $entry->update([
            'status' => 'seated',
            'seated_at' => now(),
            'assigned_table_id' => $table->id,
        ]);

        // Transition table into seated stage
        $table->update([
            'operational_stage' => 'seated',
            'stage_updated_at' => now(),
            'session_started_at' => now(),
            'current_guest_name' => $entry->guest_name,
        ]);

        ActivityLogger::log('waitlist_seated', "تم إجلاس الزبون ({$entry->guest_name}) على الطاولة {$table->name}");

        return $this->success([
            'message' => "تم إجلاس الزبون على الطاولة ({$table->name}) بنجاح",
            'entry' => $entry->fresh(['assignedTable']),
            'table' => new \App\Http\Resources\RestaurantTableResource($table->fresh()),
        ]);
    }

    /**
     * Cancel or mark guest as no-show.
     */
    public function updateStatus(Request $request, WaitlistEntry $entry): JsonResponse
    {
        $restaurant = $this->restaurantContext->current();
        if ($entry->restaurant_id !== $restaurant->id) {
            abort(403);
        }

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:cancelled,no_show,waiting'],
        ]);

        $status = $validated['status'];
        $entry->update([
            'status' => $status,
            'cancelled_at' => in_array($status, ['cancelled', 'no_show']) ? now() : null,
        ]);

        ActivityLogger::log('waitlist_status', "تم تحديث حالة الزبون في الانتظار ({$entry->guest_name}) إلى {$status}");

        return $this->success([
            'message' => 'تم تحديث الحالة بنجاح',
            'entry' => $entry->fresh(),
        ]);
    }
}
