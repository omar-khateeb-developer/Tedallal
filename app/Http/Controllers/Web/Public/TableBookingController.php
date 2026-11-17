<?php

namespace App\Http\Controllers\Web\Public;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\TableReservation;
use App\Services\WhatsAppGatewayService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TableBookingController extends Controller
{
    public function __construct(
        private readonly WhatsAppGatewayService $whatsAppGateway
    ) {}

    /**
     * Show the public visual VIP table booking page.
     */
    public function show(string $slug): Response
    {
        $restaurant = Restaurant::where('slug', $slug)
            ->where('status', 'active')
            ->firstOrFail();

        // Check if restaurant has the Floor Plan feature unlocked
        $isFeatureAvailable = $restaurant->hasFloorPlanFeature();

        $tables = $restaurant->tables()
            ->withoutGlobalScopes()
            ->where('status', 'active')
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'pos_x',
                'pos_y',
                'shape',
                'rotation',
                'capacity',
                'zone',
                'width',
                'height',
                'iot_device_id',
            ]);

        // Default time slots
        $timeSlots = [
            '13:00', '14:30', '16:00', '17:30',
            '19:00', '20:30', '22:00', '23:30',
        ];

        return Inertia::render('Customer/VipTableBooking', [
            'restaurant' => [
                'id' => $restaurant->id,
                'name' => $restaurant->name,
                'slug' => $restaurant->slug,
                'logo' => $restaurant->logo,
                'phone' => $restaurant->phone,
                'address' => $restaurant->address,
                'currency' => $restaurant->currency,
            ],
            'tables' => $tables,
            'timeSlots' => $timeSlots,
            'isFeatureAvailable' => $isFeatureAvailable,
            'minDate' => now()->toDateString(),
            'maxDate' => now()->addDays(14)->toDateString(),
        ]);
    }

    /**
     * Check table availability for a specific date and time slot.
     * Zero-PII: returns only busy table IDs.
     */
    public function getAvailability(string $slug, Request $request): JsonResponse
    {
        $restaurant = Restaurant::where('slug', $slug)
            ->where('status', 'active')
            ->firstOrFail();

        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', 'before_or_equal:' . now()->addDays(14)->toDateString()],
            'time_slot' => ['required', 'string', 'max:10'],
        ]);

        $date = $validated['date'];
        $timeSlot = $validated['time_slot'];

        // Busy tables via confirmed or pending OTP reservations
        $busyTableIds = TableReservation::where('restaurant_id', $restaurant->id)
            ->whereDate('reservation_date', $date)
            ->where('time_slot', $timeSlot)
            ->where(function ($q) {
                $q->whereIn('status', ['confirmed', 'seated'])
                  ->orWhere(function ($sub) {
                      $sub->where('status', 'pending_verification')
                          ->where('otp_expires_at', '>', now());
                  });
            })
            ->pluck('table_id')
            ->all();

        // If booking for today within immediate time, check currently seated tables
        $activeSessionTableIds = [];
        if ($date === now()->toDateString()) {
            $activeSessionTableIds = RestaurantTable::withoutGlobalScopes()
                ->where('restaurant_id', $restaurant->id)
                ->whereHas('activeSession')
                ->pluck('id')
                ->all();
        }

        $allUnavailableIds = array_values(array_unique(array_merge($busyTableIds, $activeSessionTableIds)));

        return response()->json([
            'success' => true,
            'date' => $date,
            'time_slot' => $timeSlot,
            'unavailable_table_ids' => $allUnavailableIds,
        ]);
    }

    /**
     * Request temporary table hold and dispatch verification OTP.
     * Protected by rate limiting and pessimistic transaction locking.
     */
    public function requestHold(string $slug, Request $request): JsonResponse
    {
        $restaurant = Restaurant::where('slug', $slug)
            ->where('status', 'active')
            ->firstOrFail();

        if (! $restaurant->hasFloorPlanFeature()) {
            return response()->json([
                'success' => false,
                'message' => 'عذراً، ميزة الحجز المسبق عبر المخطط غير مفعلة لهذا المطعم.',
            ], 403);
        }

        // Anti-bot honeypot field
        if ($request->filled('vip_bot_trap')) {
            return response()->json(['success' => false, 'message' => 'طلب غير صالح.'], 400);
        }

        $validated = $request->validate([
            'table_id' => ['required', 'integer'],
            'guest_name' => ['required', 'string', 'min:3', 'max:80'],
            'guest_phone' => ['required', 'string', 'min:8', 'max:25'],
            'guest_email' => ['nullable', 'email', 'max:100'],
            'reservation_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', 'before_or_equal:' . now()->addDays(14)->toDateString()],
            'time_slot' => ['required', 'string', 'max:10'],
            'guests_count' => ['required', 'integer', 'min:1', 'max:30'],
            'special_requests' => ['nullable', 'string', 'max:500'],
        ]);

        $table = $restaurant->tables()
            ->withoutGlobalScopes()
            ->where('status', 'active')
            ->findOrFail($validated['table_id']);

        // Format clean phone number
        $cleanPhone = preg_replace('/[^\d\+]/', '', $validated['guest_phone']);

        /** @var TableReservation $reservation */
        $reservation = null;

        DB::transaction(function () use ($restaurant, $table, $validated, $cleanPhone, $request, &$reservation) {
            // Pessimistic Concurrency Lock (Eliminates Double Booking Race Conditions)
            $isConflict = TableReservation::where('table_id', $table->id)
                ->whereDate('reservation_date', $validated['reservation_date'])
                ->where('time_slot', $validated['time_slot'])
                ->where(function ($q) {
                    $q->whereIn('status', ['confirmed', 'seated'])
                      ->orWhere(function ($sub) {
                          $sub->where('status', 'pending_verification')
                              ->where('otp_expires_at', '>', now());
                      });
                })
                ->lockForUpdate()
                ->exists();

            if ($isConflict) {
                abort(409, 'عذراً، هذه الطاولة محجوزة أو قيد التأكيد للتو في نفس التوقيت. يرجى اختيار طاولة أو توقيت آخر.');
            }

            // Check if customer already has more than 2 pending holds today (anti-spam)
            $existingCustomerPending = TableReservation::where('guest_phone', $cleanPhone)
                ->whereDate('reservation_date', $validated['reservation_date'])
                ->where('status', 'pending_verification')
                ->where('otp_expires_at', '>', now())
                ->count();

            if ($existingCustomerPending >= 2) {
                abort(429, 'لديك طلب حجز قيد التحقق حالياً. يرجى إتمام التحقق أو الانتظار.');
            }

            $otpCode = (string) rand(1000, 9999);

            $reservation = TableReservation::create([
                'uuid' => (string) Str::uuid(),
                'restaurant_id' => $restaurant->id,
                'table_id' => $table->id,
                'guest_name' => strip_tags(trim($validated['guest_name'])),
                'guest_phone' => $cleanPhone,
                'guest_email' => $validated['guest_email'] ?? null,
                'reservation_date' => $validated['reservation_date'],
                'time_slot' => $validated['time_slot'],
                'guests_count' => $validated['guests_count'],
                'special_requests' => strip_tags(trim($validated['special_requests'] ?? '')),
                'status' => 'pending_verification',
                'otp_code' => $otpCode,
                'otp_expires_at' => now()->addMinutes(10),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
            ]);
        });

        // Dispatch OTP via WhatsApp if connected
        $otpSentViaWhatsapp = false;
        if ($restaurant->isWhatsappConnected()) {
            $msg = "👑 كود التحقق لحجز طاولة VIP في {$restaurant->name} هو: *{$reservation->otp_code}*\nصالح لمدة 10 دقائق.\nشكراً لثقتكم!";
            $waRes = $this->whatsAppGateway->sendMessage($restaurant, $cleanPhone, $msg);
            $otpSentViaWhatsapp = $waRes['success'] ?? false;
        }

        // Mask phone: 0933***123
        $maskedPhone = substr($cleanPhone, 0, 4) . '****' . substr($cleanPhone, -3);

        return response()->json([
            'success' => true,
            'message' => 'تم حجز الطاولة مؤقتاً. يرجى إدخال رمز التحقق لتأكيد الحجز.',
            'uuid' => $reservation->uuid,
            'expires_in_seconds' => 600,
            'phone_masked' => $maskedPhone,
            'sent_via_whatsapp' => $otpSentViaWhatsapp,
            // For local / development testing convenience
            'dev_otp' => app()->environment('local') ? $reservation->otp_code : null,
        ]);
    }

    /**
     * Verify OTP code and confirm the VIP reservation.
     */
    public function verifyOtp(string $slug, Request $request): JsonResponse
    {
        $restaurant = Restaurant::where('slug', $slug)
            ->where('status', 'active')
            ->firstOrFail();

        $validated = $request->validate([
            'uuid' => ['required', 'string', 'uuid'],
            'otp_code' => ['required', 'string', 'size:4'],
        ]);

        $reservation = TableReservation::where('uuid', $validated['uuid'])
            ->where('restaurant_id', $restaurant->id)
            ->firstOrFail();

        if ($reservation->status === 'confirmed') {
            return response()->json([
                'success' => true,
                'message' => 'الحجز مؤكد مسبقاً!',
                'booking_code' => $reservation->getFormattedCode(),
                'ticket_url' => route('public.booking.ticket', ['slug' => $restaurant->slug, 'uuid' => $reservation->uuid]),
            ]);
        }

        if ($reservation->isExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'انتهت صلاحية رمز التحقق (10 دقائق). يرجى تقديم طلب حجز جديد.',
            ], 422);
        }

        if ($reservation->otp_code !== trim($validated['otp_code'])) {
            return response()->json([
                'success' => false,
                'message' => 'رمز التحقق غير صحيح. يرجى التأكد وإعادة المحاولة.',
            ], 422);
        }

        $reservation->update([
            'status' => 'confirmed',
            'otp_verified_at' => now(),
        ]);

        // Send confirmation WhatsApp message
        if ($restaurant->isWhatsappConnected()) {
            $msg = "✨ مرحباً بك {$reservation->guest_name} 👑\nتم تأكيد حجز طاولة VIP بنجاح في *{$restaurant->name}*!\n\n📅 التاريخ: {$reservation->reservation_date->format('Y-m-d')}\n⏰ التوقيت: {$reservation->time_slot}\n🍽️ الطاولة: {$reservation->table->name} ({$reservation->table->zone})\n👥 عدد الضيوف: {$reservation->guests_count}\n🎫 رمز الحجز: *{$reservation->getFormattedCode()}*\n\nنتطلع لاستقبالكم وتقديم تجربة استثنائية تليق بكم!";
            $this->whatsAppGateway->sendMessage($restaurant, $reservation->guest_phone, $msg);
        }

        \App\Services\ActivityLogger::log('vip_reservation_confirmed', "تم تأكيد حجز VIP جديد: {$reservation->getFormattedCode()} للطاولة {$reservation->table->name}");

        return response()->json([
            'success' => true,
            'message' => 'تم تأكيد حجز طاولة VIP بنجاح! نتشرف بحضوركم.',
            'booking_code' => $reservation->getFormattedCode(),
            'ticket_url' => route('public.booking.ticket', ['slug' => $restaurant->slug, 'uuid' => $reservation->uuid]),
            'reservation' => [
                'code' => $reservation->getFormattedCode(),
                'guest_name' => $reservation->guest_name,
                'table_name' => $reservation->table->name,
                'zone' => $reservation->table->zone,
                'date' => $reservation->reservation_date->format('Y-m-d'),
                'time_slot' => $reservation->time_slot,
                'guests_count' => $reservation->guests_count,
            ],
        ]);
    }

    /**
     * Show digital VIP Pass / Ticket.
     */
    public function showTicket(string $slug, string $uuid): Response
    {
        $restaurant = Restaurant::where('slug', $slug)
            ->where('status', 'active')
            ->firstOrFail();

        $reservation = TableReservation::with('table')
            ->where('uuid', $uuid)
            ->where('restaurant_id', $restaurant->id)
            ->firstOrFail();

        return Inertia::render('Customer/VipBookingTicket', [
            'restaurant' => [
                'name' => $restaurant->name,
                'slug' => $restaurant->slug,
                'logo' => $restaurant->logo,
                'phone' => $restaurant->phone,
                'address' => $restaurant->address,
            ],
            'reservation' => [
                'uuid' => $reservation->uuid,
                'code' => $reservation->getFormattedCode(),
                'status' => $reservation->status,
                'guest_name' => $reservation->guest_name,
                'guest_phone' => $reservation->guest_phone,
                'table_name' => $reservation->table?->name ?? 'طاولة خاصة',
                'table_shape' => $reservation->table?->shape ?? 'circle',
                'zone' => $reservation->table?->zone ?? 'main',
                'date' => $reservation->reservation_date->format('Y-m-d'),
                'time_slot' => $reservation->time_slot,
                'guests_count' => $reservation->guests_count,
                'special_requests' => $reservation->special_requests,
                'created_at' => $reservation->created_at->toIso8601String(),
            ],
        ]);
    }
}
