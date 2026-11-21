<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PlatformWhatsAppController extends ApiController
{
    private string $baseUrl;
    private string $secret;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('whatsapp.gateway_url', 'http://127.0.0.1:8082'), '/');
        $this->secret = (string) config('whatsapp.gateway_secret', 'tedallal_wa_gateway_2026_secure_key');
    }

    /**
     * Get live status and pairing QR code for the Platform Owner's WhatsApp session.
     */
    public function status(): JsonResponse
    {
        $liveStatus = 'disconnected';
        $qrCode = null;
        $phone = Setting::get('platform_whatsapp_phone');
        $connectedAt = Setting::get('platform_whatsapp_connected_at');

        try {
            $response = Http::timeout(5)
                ->withHeaders(['X-Gateway-Secret' => $this->secret])
                ->get("{$this->baseUrl}/api/session/platform/status");

            if ($response->successful()) {
                $data = $response->json();
                $liveStatus = $data['status'] ?? 'disconnected';
                $qrCode = $data['qr_code'] ?? null;
                if (! empty($data['phone'])) {
                    $phone = $data['phone'];
                    Setting::set('platform_whatsapp_phone', $phone);
                }
                if (! empty($data['connected_at'])) {
                    $connectedAt = $data['connected_at'];
                    Setting::set('platform_whatsapp_connected_at', $connectedAt);
                }
                Setting::set('platform_whatsapp_status', $liveStatus);
            }
        } catch (\Throwable $e) {
            Log::debug('Failed to fetch platform WhatsApp status: ' . $e->getMessage());
            $liveStatus = Setting::get('platform_whatsapp_status', 'disconnected');
        }

        return $this->success([
            'status' => $liveStatus,
            'qr_code' => $qrCode,
            'phone' => $phone,
            'connected_at' => $connectedAt,
            'ai_enabled' => Setting::get('platform_whatsapp_ai_enabled', '1') === '1',
            'custom_instructions' => (string) Setting::get('platform_ai_custom_instructions', ''),
        ]);
    }

    /**
     * Start connection process and generate pairing QR code for the Platform Owner.
     */
    public function connect(): JsonResponse
    {
        try {
            $response = Http::timeout(15)
                ->withHeaders(['X-Gateway-Secret' => $this->secret])
                ->post("{$this->baseUrl}/api/session/platform/start");

            if ($response->successful()) {
                $data = $response->json();
                return $this->success([
                    'status' => $data['status'] ?? 'scan_qr',
                    'qr_code' => $data['qr_code'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'connected_at' => $data['connected_at'] ?? null,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $response->json('message') ?? 'تعذر بدء جلسة واتساب إدارة المنصة.',
            ], 500);
        } catch (\Throwable $e) {
            Log::error('Failed to start platform WhatsApp session: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'بوابة الواتساب غير متاحة حالياً على السيرفر.',
            ], 503);
        }
    }

    /**
     * Disconnect Platform Owner WhatsApp session.
     */
    public function disconnect(): JsonResponse
    {
        try {
            Http::timeout(10)
                ->withHeaders(['X-Gateway-Secret' => $this->secret])
                ->post("{$this->baseUrl}/api/session/platform/logout");
        } catch (\Throwable $e) {
            Log::warning('Platform WhatsApp logout warning: ' . $e->getMessage());
        }

        Setting::set('platform_whatsapp_status', 'disconnected');
        Setting::set('platform_whatsapp_phone', null);
        Setting::set('platform_whatsapp_connected_at', null);

        return $this->success([
            'status' => 'disconnected',
            'message' => 'تم فصل جلسة واتساب إدارة المنصة بنجاح.',
        ]);
    }

    /**
     * Toggle or update AI Consultant auto-reply setting for the Platform Owner.
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ai_enabled' => ['sometimes', 'boolean'],
            'custom_instructions' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ]);

        if (array_key_exists('ai_enabled', $validated)) {
            Setting::set('platform_whatsapp_ai_enabled', $validated['ai_enabled'] ? '1' : '0');
        }

        if (array_key_exists('custom_instructions', $validated)) {
            Setting::set('platform_ai_custom_instructions', (string) $validated['custom_instructions']);
        }

        return $this->success([
            'ai_enabled' => Setting::get('platform_whatsapp_ai_enabled', '1') === '1',
            'custom_instructions' => (string) Setting::get('platform_ai_custom_instructions', ''),
            'message' => 'تم حفظ وتحديث إعدادات وتلقين المساعد الذكي بنجاح.',
        ]);
    }

    /**
     * Send test message from Platform Owner WhatsApp to verify connection.
     */
    public function sendTest(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => ['required', 'string'],
        ]);

        $phone = trim((string) $request->input('phone'));
        $testMsg = "👑 رسالة تجريبية ناجحة من منصة «تدلّل»!\n\n"
            . "تم ربط رقم واتساب إدارة المنصة بنجاح. المساعد التنفيذي الذكي جاهز الآن لاستقبال أصحاب المطاعم والرد على استفساراتهم باحترافية ✨";

        try {
            $response = Http::timeout(15)
                ->withHeaders(['X-Gateway-Secret' => $this->secret])
                ->post("{$this->baseUrl}/api/messages/send", [
                    'session_id' => 'platform',
                    'phone' => $phone,
                    'text' => $testMsg,
                    'simulate_typing' => true,
                ]);

            if ($response->successful()) {
                return $this->success([
                    'message' => 'تم إرسال الرسالة التجريبية بنجاح من رقم إدارة المنصة.',
                    'message_id' => $response->json('message_id'),
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $response->json('message') ?? 'تعذر إرسال الرسالة التجريبية.',
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'تعذر الاتصال ببوابة الواتساب: ' . $e->getMessage(),
            ], 500);
        }
    }
}
