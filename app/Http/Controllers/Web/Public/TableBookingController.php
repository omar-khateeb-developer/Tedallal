<?php

namespace App\Http\Controllers\Web\Public;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\TableReservation;
use App\Services\WhatsAppGatewayService;
use App\Support\PhoneNormalizer;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
            ->dineIn()
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
                'menu_url' => route('customer.restaurant_menu', ['slug' => $restaurant->slug]),
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
            ->dineIn()
            ->findOrFail($validated['table_id']);

        // Normalize & validate phone number (handles Arabic/Persian numerals, 09..., +963 09..., 00963..., international)
        $rawPhone = (string) $validated['guest_phone'];
        if (! PhoneNormalizer::isValidWhatsAppCandidate($rawPhone)) {
            return response()->json([
                'success' => false,
                'error_code' => 'invalid_phone',
                'message' => 'يرجى إدخال رقم واتساب صحيح (مثال: 0933123456 أو +963933123456).',
            ], 422);
        }

        $cleanPhone = PhoneNormalizer::toStorageFormat($rawPhone);

        /** @var TableReservation $reservation */
        $reservation = null;

        DB::transaction(function () use ($restaurant, $table, $validated, $cleanPhone, $request, &$reservation) {
            // Clean up any stale pending hold by the exact same phone on the exact same slot
            // so retries don't self-lock the guest before verification succeeds.
            TableReservation::where('table_id', $table->id)
                ->whereDate('reservation_date', $validated['reservation_date'])
                ->where('time_slot', $validated['time_slot'])
                ->where('guest_phone', $cleanPhone)
                ->where('status', 'pending_verification')
                ->delete();

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

            $otpCode = (string) random_int(1000, 9999);

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

        // Mask phone: 0967****569
        $maskedPhone = strlen($cleanPhone) >= 7
            ? substr($cleanPhone, 0, 4) . '****' . substr($cleanPhone, -3)
            : $cleanPhone;

        // Dispatch OTP via restaurant WhatsApp, automatically falling back to Platform WhatsApp
        $msg = "👑 أهلاً بحضرتك {$reservation->guest_name}! كود التحقق لحجز طاولة VIP في *{$restaurant->name}* هو: *{$reservation->otp_code}*\nصالح لمدة 10 دقائق.\nشكراً لثقتكم!";
        $waRes = $this->whatsAppGateway->sendTransactionalWithFallback($restaurant, $cleanPhone, $msg);
        $otpSentViaWhatsapp = ! empty($waRes['success']);

        if (! $otpSentViaWhatsapp) {
            if (($waRes['error_code'] ?? null) === 'not_on_whatsapp') {
                $reservation->delete();

                return response()->json([
                    'success' => false,
                    'error_code' => 'not_on_whatsapp',
                    'message' => 'الرقم المدخل غير مسجل في تطبيق واتساب. يرجى التحقق من الرقم مع رمز الدولة وإعادة المحاولة.',
                ], 422);
            }

            $this->recordUnlinkedWhatsappAlertIfNeeded($restaurant, $maskedPhone);

            // Never freeze the table or mislead the guest in production if OTP delivery failed
            if (! app()->environment(['local', 'testing'])) {
                $reservation->delete();

                return response()->json([
                    'success' => false,
                    'whatsapp_unavailable' => true,
                    'reason_unlinked' => ! $restaurant->isWhatsappConnected(),
                    'error_code' => $waRes['error_code'] ?? 'whatsapp_unavailable',
                    'restaurant_phone' => $restaurant->phone,
                    'message' => 'تعذر إرسال رمز التحقق عبر واتساب حالياً لأن خدمة الواتساب غير متصلة. يرجى المحاولة بعد قليل أو التواصل مع إدارة المطعم مباشرة.',
                ], 503);
            }
        }

        return response()->json([
            'success' => true,
            'message' => $otpSentViaWhatsapp
                ? 'تم حجز الطاولة مؤقتاً وإرسال رمز التحقق عبر واتساب.'
                : 'تم حجز الطاولة مؤقتاً.',
            'uuid' => $reservation->uuid,
            'expires_in_seconds' => 600,
            'resend_after_seconds' => 25,
            'phone_masked' => $maskedPhone,
            'sent_via_whatsapp' => $otpSentViaWhatsapp,
            'delivery_channel' => $waRes['channel'] ?? null,
            // For local development testing convenience only
            'dev_otp' => app()->environment('local') ? $reservation->otp_code : null,
        ]);
    }

    /**
     * Resend verification OTP for an active pending reservation hold.
     */
    public function resendOtp(string $slug, Request $request): JsonResponse
    {
        $restaurant = Restaurant::where('slug', $slug)
            ->where('status', 'active')
            ->firstOrFail();

        $validated = $request->validate([
            'uuid' => ['required', 'string', 'uuid'],
        ]);

        $reservation = TableReservation::where('uuid', $validated['uuid'])
            ->where('restaurant_id', $restaurant->id)
            ->firstOrFail();

        if ($reservation->status === 'confirmed') {
            return response()->json([
                'success' => false,
                'message' => 'الحجز مؤكد مسبقاً ولا يحتاج إلى إعادة إرسال.',
            ], 422);
        }

        if ($reservation->status !== 'pending_verification' || $reservation->isExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'انتهت صلاحية هذا الحجز المؤقت. يرجى تقديم طلب حجز جديد.',
            ], 422);
        }

        $cooldownKey = "vip_otp_resend_{$reservation->uuid}";
        if (! Cache::add($cooldownKey, true, now()->addSeconds(25))) {
            return response()->json([
                'success' => false,
                'message' => 'يرجى الانتظار 25 ثانية قبل طلب إعادة إرسال الرمز.',
            ], 429);
        }

        $newOtp = (string) random_int(1000, 9999);
        $msg = "👑 أهلاً بحضرتك {$reservation->guest_name}! كود التحقق الجديد لحجز طاولة VIP في *{$restaurant->name}* هو: *{$newOtp}*\nصالح لمدة 10 دقائق.";
        $waRes = $this->whatsAppGateway->sendTransactionalWithFallback($restaurant, $reservation->guest_phone, $msg);
        $otpSentViaWhatsapp = ! empty($waRes['success']);

        if (! $otpSentViaWhatsapp && ! app()->environment(['local', 'testing'])) {
            Cache::forget($cooldownKey);

            $status = ($waRes['error_code'] ?? null) === 'not_on_whatsapp' ? 422 : 503;
            if ($status === 503) {
                $masked = strlen($reservation->guest_phone) >= 7
                    ? substr($reservation->guest_phone, 0, 4) . '****' . substr($reservation->guest_phone, -3)
                    : $reservation->guest_phone;
                $this->recordUnlinkedWhatsappAlertIfNeeded($restaurant, $masked);
            }

            return response()->json([
                'success' => false,
                'whatsapp_unavailable' => $status === 503,
                'reason_unlinked' => $status === 503 && ! $restaurant->isWhatsappConnected(),
                'error_code' => $waRes['error_code'] ?? 'whatsapp_unavailable',
                'restaurant_phone' => $restaurant->phone,
                'message' => $status === 422
                    ? 'الرقم المدخل غير مسجل في تطبيق واتساب.'
                    : 'تعذر إعادة إرسال رمز التحقق عبر واتساب حالياً. يرجى المحاولة بعد قليل.',
            ], $status);
        }

        $reservation->update([
            'otp_code' => $newOtp,
            'otp_expires_at' => now()->addMinutes(10),
        ]);

        return response()->json([
            'success' => true,
            'message' => $otpSentViaWhatsapp
                ? 'تم إعادة إرسال رمز التحقق بنجاح عبر واتساب.'
                : 'تم تجديد رمز التحقق.',
            'expires_in_seconds' => 600,
            'resend_after_seconds' => 25,
            'sent_via_whatsapp' => $otpSentViaWhatsapp,
            'delivery_channel' => $waRes['channel'] ?? null,
            'dev_otp' => app()->environment('local') ? $newOtp : null,
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

        // Normalize Eastern Arabic numerals in OTP before validation
        if ($request->has('otp_code')) {
            $request->merge([
                'otp_code' => trim(PhoneNormalizer::toAsciiDigits((string) $request->input('otp_code'))),
            ]);
        }

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

        // Send confirmation WhatsApp message (with automatic platform fallback)
        $msg = "✨ أهلاً بحضرتك {$reservation->guest_name} 👑\nتم تأكيد حجز طاولة VIP بنجاح في *{$restaurant->name}*!\n\n📅 التاريخ: {$reservation->reservation_date->format('Y-m-d')}\n⏰ التوقيت: {$reservation->time_slot}\n🍽️ الطاولة: {$reservation->table->name} ({$reservation->table->zone})\n👥 عدد الضيوف: {$reservation->guests_count}\n🎫 رمز الحجز: *{$reservation->getFormattedCode()}*\n\nنتطلع لاستقبال حضرتك وتقديم تجربة استثنائية تليق بكم!";
        $this->whatsAppGateway->sendTransactionalWithFallback($restaurant, $reservation->guest_phone, $msg);

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
                'menu_url' => route('customer.restaurant_menu', ['slug' => $restaurant->slug]),
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

    /**
     * Alert the restaurant manager whenever a VIP OTP fails because the restaurant is not linked to WhatsApp.
     */
    private function recordUnlinkedWhatsappAlertIfNeeded(Restaurant $restaurant, string $maskedPhone): void
    {
        if ($restaurant->isWhatsappConnected()) {
            return;
        }

        $alertMessage = "تعذر إرسال رمز التحقق (OTP) لأحد الزبائن ({$maskedPhone}) عند محاولة حجز طاولة VIP لأن مطعم «{$restaurant->name}» غير مربوط برقم واتساب. يجب ربط المطعم برقم واتساب أولاً.";

        Cache::put("restaurant_whatsapp_otp_alert_{$restaurant->id}", [
            'message' => $alertMessage,
            'phone_masked' => $maskedPhone,
            'at' => now()->toIso8601String(),
        ], now()->addHours(24));

        try {
            ActivityLog::create([
                'restaurant_id' => $restaurant->id,
                'user_id' => null,
                'action' => 'vip_otp_whatsapp_unlinked',
                'description' => mb_substr($alertMessage, 0, 240),
            ]);
        } catch (\Throwable $e) {
            // Do not interrupt booking error response if activity log insert fails
        }
    }
}
