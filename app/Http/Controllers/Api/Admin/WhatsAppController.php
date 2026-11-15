<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\PromoCode;
use App\Models\RestaurantCustomer;
use App\Services\CustomerCrmService;
use App\Services\RestaurantContext;
use App\Services\WhatsAppGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppController extends ApiController
{
    public function __construct(
        private readonly RestaurantContext $restaurantContext,
        private readonly WhatsAppGatewayService $gatewayService,
        private readonly CustomerCrmService $crmService,
    ) {}

    /**
     * Get live WhatsApp session status, pairing QR, and configuration.
     */
    public function status(Request $request): JsonResponse
    {
        $restaurant = $this->restaurantContext->current();

        if (! $restaurant->hasWhatsappRemindersFeature()) {
            return response()->json([
                'success' => false,
                'feature_locked' => true,
                'message' => 'ميزة بوابة الواتساب و CRM الزبائن متاحة للباقة الشاملة (Max) أو عند تفعيلها لمطعمك.',
            ], 403);
        }

        $session = $this->gatewayService->getSessionStatus($restaurant);

        return $this->success([
            'status' => $session['status'],
            'qr_code' => $session['qr_code'],
            'phone' => $session['phone'] ?: $restaurant->whatsapp_phone,
            'connected_at' => $session['connected_at'] ?: $restaurant->whatsapp_connected_at?->format('Y-m-d h:i A'),
            'auto_reply_enabled' => (bool) $restaurant->whatsapp_auto_reply_enabled,
            'auto_reply_message' => $restaurant->whatsapp_auto_reply_message,
            'expiring_reminders_enabled' => (bool) $restaurant->whatsapp_expiring_reminders_enabled,
        ]);
    }

    /**
     * Start connection process and fetch fresh QR code.
     */
    public function connect(Request $request): JsonResponse
    {
        $restaurant = $this->restaurantContext->current();

        if (! $restaurant->hasWhatsappRemindersFeature()) {
            return response()->json([
                'success' => false,
                'message' => 'هذه الميزة غير مفعلة لمطعمك.',
            ], 403);
        }

        $result = $this->gatewayService->startSession($restaurant);

        if (! empty($result['success'])) {
            return $this->success([
                'status' => $result['status'],
                'qr_code' => $result['qr_code'],
                'phone' => $result['phone'],
                'connected_at' => $result['connected_at'],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message'] ?? 'تعذر بدء جلسة الربط مع الواتساب.',
        ], 500);
    }

    /**
     * Disconnect and unlink restaurant WhatsApp.
     */
    public function disconnect(Request $request): JsonResponse
    {
        $restaurant = $this->restaurantContext->current();

        $this->gatewayService->logoutSession($restaurant);

        return $this->success([
            'status' => 'disconnected',
            'message' => 'تم فصل جلسة الواتساب بنجاح.',
        ]);
    }

    /**
     * Update restaurant WhatsApp automation settings (Auto-reply, Expiring alerts).
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $restaurant = $this->restaurantContext->current();

        $validated = $request->validate([
            'whatsapp_auto_reply_enabled' => ['required', 'boolean'],
            'whatsapp_auto_reply_message' => ['nullable', 'string', 'max:1000'],
            'whatsapp_expiring_reminders_enabled' => ['required', 'boolean'],
        ]);

        $restaurant->update($validated);
        $this->restaurantContext->forget();

        return $this->success([
            'auto_reply_enabled' => (bool) $restaurant->whatsapp_auto_reply_enabled,
            'auto_reply_message' => $restaurant->whatsapp_auto_reply_message,
            'expiring_reminders_enabled' => (bool) $restaurant->whatsapp_expiring_reminders_enabled,
        ]);
    }

    /**
     * Send a test WhatsApp message to verify the connection.
     */
    public function sendTest(Request $request): JsonResponse
    {
        $restaurant = $this->restaurantContext->current();

        $request->validate([
            'phone' => ['required', 'string'],
        ]);

        $phone = $this->crmService->validateAndNormalizePhone((string) $request->input('phone'));

        $testMsg = "🎉 رسالة تجريبية ناجحة من منصة «تدلّل»!\n\n"
            . "تم ربط رقم واتساب مطعم «{$restaurant->name}» بنجاح وهو جاهز لإرسال كوبونات عجلة الحظ وتذكيرات الزبائن تلقائياً في الخلفية ✨";

        $res = $this->gatewayService->sendMessage($restaurant, $phone, $testMsg, true);

        if (! empty($res['success'])) {
            return $this->success([
                'message' => 'تم إرسال الرسالة التجريبية بنجاح إلى هاتفك.',
                'message_id' => $res['message_id'] ?? null,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $res['message'] ?? 'تعذر إرسال الرسالة التجريبية.',
        ], 422);
    }

    /**
     * Send direct background WhatsApp reminder to a specific customer without opening wa.me.
     */
    public function sendDirectReminder(Request $request, int $customerId): JsonResponse
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

        $payload = $this->crmService->buildReminderPayload($restaurant, $customer, $promo);

        // Try direct background dispatch if gateway is connected
        $sentDirectly = false;
        $errorMsg = null;

        if ($restaurant->isWhatsappConnected()) {
            $sendRes = $this->gatewayService->sendMessage($restaurant, $customer->phone, $payload['message'], true);
            if (! empty($sendRes['success'])) {
                $sentDirectly = true;
            } else {
                $errorMsg = $sendRes['message'] ?? null;
            }
        }

        $promo->update([
            'reminded_at' => now(),
            'reminders_count' => (int) $promo->reminders_count + 1,
        ]);

        return $this->success([
            'sent_directly' => $sentDirectly,
            'error_message' => $errorMsg,
            'reminded_at' => $promo->reminded_at->format('Y-m-d h:i A'),
            'reminders_count' => $promo->reminders_count,
            'whatsapp_url' => $payload['whatsapp_url'],
            'whatsapp_message' => $payload['message'],
        ]);
    }
}

