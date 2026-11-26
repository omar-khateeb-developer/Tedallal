<?php

namespace App\Services;

use App\Models\Restaurant;
use App\Models\Setting;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppGatewayService
{
    private string $baseUrl;
    private string $secret;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('whatsapp.gateway_url', 'http://127.0.0.1:8082'), '/');
        $this->secret = (string) config('whatsapp.gateway_secret', '');
        $this->timeout = (int) config('whatsapp.timeout', 15);
    }

    /**
     * Check if the WhatsApp microservice is reachable and healthy.
     */
    public function isAvailable(): bool
    {
        try {
            $response = Http::timeout(3)->get("{$this->baseUrl}/health");
            return $response->successful() && ($response->json('status') === 'UP');
        } catch (\Throwable $e) {
            Log::debug('WhatsApp gateway health check failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get the live status and QR code (if scanning) for the restaurant's session.
     *
     * @return array{status: string, qr_code: ?string, phone: ?string, connected_at: ?string, gateway_reachable?: bool}
     */
    public function getSessionStatus(Restaurant $restaurant): array
    {
        try {
            $response = Http::connectTimeout(2)->timeout(min($this->timeout, 5))
                ->withHeaders(['X-Gateway-Secret' => $this->secret])
                ->get("{$this->baseUrl}/api/session/{$restaurant->id}/status");

            if ($response->successful()) {
                $data = $response->json();
                $this->syncLocalState($restaurant, $data);
                return [
                    'status' => $data['status'] ?? 'disconnected',
                    'qr_code' => $data['qr_code'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'connected_at' => $data['connected_at'] ?? null,
                    'gateway_reachable' => true,
                ];
            }
        } catch (\Throwable $e) {
            Log::warning("Failed to fetch WhatsApp status for restaurant {$restaurant->id}: {$e->getMessage()}");
        }

        return [
            'status' => $restaurant->whatsapp_status ?: 'disconnected',
            'qr_code' => null,
            'phone' => $restaurant->whatsapp_phone,
            'connected_at' => $restaurant->whatsapp_connected_at?->toIso8601String(),
            'gateway_reachable' => false,
        ];
    }

    /**
     * Get and synchronize the live status of the Platform Owner's official WhatsApp session.
     *
     * @return array{status: string, qr_code: ?string, phone: ?string, connected_at: ?string, gateway_reachable: bool}
     */
    public function getPlatformSessionStatus(): array
    {
        try {
            $response = Http::connectTimeout(2)->timeout(min($this->timeout, 5))
                ->withHeaders(['X-Gateway-Secret' => $this->secret])
                ->get("{$this->baseUrl}/api/session/platform/status");

            if ($response->successful()) {
                $data = $response->json();
                $status = (string) ($data['status'] ?? 'disconnected');
                Setting::set('platform_whatsapp_status', $status);

                if ($status === 'connected') {
                    if (! empty($data['phone'])) {
                        Setting::set('platform_whatsapp_phone', (string) $data['phone']);
                    }
                    if (! empty($data['connected_at'])) {
                        Setting::set('platform_whatsapp_connected_at', (string) $data['connected_at']);
                    }
                } elseif ($status === 'disconnected') {
                    Setting::set('platform_whatsapp_phone', null);
                    Setting::set('platform_whatsapp_connected_at', null);
                }

                return [
                    'status' => $status,
                    'qr_code' => $data['qr_code'] ?? null,
                    'phone' => $data['phone'] ?? Setting::get('platform_whatsapp_phone'),
                    'connected_at' => $data['connected_at'] ?? Setting::get('platform_whatsapp_connected_at'),
                    'gateway_reachable' => true,
                ];
            }
        } catch (\Throwable $e) {
            Log::debug('Failed to fetch platform WhatsApp status: ' . $e->getMessage());
        }

        return [
            'status' => (string) Setting::get('platform_whatsapp_status', 'disconnected'),
            'qr_code' => null,
            'phone' => Setting::get('platform_whatsapp_phone'),
            'connected_at' => Setting::get('platform_whatsapp_connected_at'),
            'gateway_reachable' => false,
        ];
    }

    /**
     * Request the gateway to start the session and generate a QR code.
     */
    public function startSession(Restaurant $restaurant): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders(['X-Gateway-Secret' => $this->secret])
                ->post("{$this->baseUrl}/api/session/{$restaurant->id}/start");

            if ($response->successful()) {
                $data = $response->json();
                $this->syncLocalState($restaurant, $data);
                return [
                    'success' => true,
                    'status' => $data['status'] ?? 'scan_qr',
                    'qr_code' => $data['qr_code'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'connected_at' => $data['connected_at'] ?? null,
                ];
            }

            return [
                'success' => false,
                'message' => $response->json('message') ?? 'تعذر بدء جلسة الواتساب',
            ];
        } catch (\Throwable $e) {
            Log::error("Failed to start WhatsApp session for restaurant {$restaurant->id}: {$e->getMessage()}");
            return [
                'success' => false,
                'message' => 'بوابة الواتساب غير متاحة حالياً على السيرفر.',
            ];
        }
    }

    /**
     * Disconnect and logout the WhatsApp session.
     */
    public function logoutSession(Restaurant $restaurant): array
    {
        try {
            Http::timeout($this->timeout)
                ->withHeaders(['X-Gateway-Secret' => $this->secret])
                ->post("{$this->baseUrl}/api/session/{$restaurant->id}/logout");
        } catch (\Throwable $e) {
            Log::warning("Error sending logout request to gateway for restaurant {$restaurant->id}: {$e->getMessage()}");
        }

        $restaurant->update([
            'whatsapp_status' => 'disconnected',
            'whatsapp_phone' => null,
            'whatsapp_connected_at' => null,
        ]);

        return ['success' => true];
    }

    /**
     * Send a background text message to a customer's WhatsApp number via the restaurant's session.
     *
     * @return array{success: bool, message_id?: ?string, error_code?: ?string, message?: string}
     */
    public function sendMessage(
        Restaurant $restaurant,
        string $phone,
        string $message,
        bool $simulateTyping = true
    ): array {
        $normalizedPhone = PhoneNormalizer::toWhatsAppDigits($phone);
        if ($normalizedPhone === '') {
            return [
                'success' => false,
                'error_code' => 'invalid_phone',
                'message' => 'رقم الهاتف غير صالح للإرسال عبر واتساب.',
            ];
        }

        // Self-heal stale local DB state by consulting live gateway if local says disconnected
        if (! $restaurant->isWhatsappConnected()) {
            $live = $this->getSessionStatus($restaurant);
            $liveStatus = $live['status'] ?? 'disconnected';
            if (! in_array($liveStatus, ['connected', 'connecting'], true)) {
                return [
                    'success' => false,
                    'error_code' => $live['gateway_reachable'] ? 'session_not_connected' : 'gateway_unreachable',
                    'message' => 'رقم واتساب المطعم غير متصل بالخدمة.',
                ];
            }
        }

        try {
            $response = Http::connectTimeout(3)->timeout($this->timeout)
                ->withHeaders(['X-Gateway-Secret' => $this->secret])
                ->post("{$this->baseUrl}/api/messages/send", [
                    'restaurant_id' => $restaurant->id,
                    'phone' => $normalizedPhone,
                    'text' => $message,
                    'simulate_typing' => $simulateTyping,
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message_id' => $response->json('message_id'),
                ];
            }

            $errorCode = $response->json('error_code');
            $errMsg = $response->json('message') ?? 'تعذر إرسال الرسالة عبر الواتساب';

            // Keep local DB synced if gateway reports this session is not connected
            if ($response->status() === 400 || $errorCode === 'session_not_connected') {
                $errorCode = 'session_not_connected';
                if ($restaurant->whatsapp_status === 'connected') {
                    $restaurant->update(['whatsapp_status' => 'disconnected']);
                }
            }

            Log::warning("WhatsApp send failed for restaurant {$restaurant->id}: {$errMsg}", [
                'http_status' => $response->status(),
                'error_code' => $errorCode,
            ]);

            return [
                'success' => false,
                'error_code' => $errorCode ?: 'send_failed',
                'message' => $errMsg,
            ];
        } catch (\Throwable $e) {
            Log::error("Exception in WhatsApp send for restaurant {$restaurant->id}: {$e->getMessage()}");
            return [
                'success' => false,
                'error_code' => 'gateway_unreachable',
                'message' => 'خطأ في الاتصال ببوابة الواتساب.',
            ];
        }
    }

    /**
     * Send an administrative / fallback transactional WhatsApp message from the Platform Owner's official session.
     *
     * @return array{success: bool, message_id?: ?string, error_code?: ?string, message?: string}
     */
    public function sendPlatformMessage(string $phone, string $message, bool $simulateTyping = true): array
    {
        $normalizedPhone = PhoneNormalizer::toWhatsAppDigits($phone);
        if ($normalizedPhone === '') {
            return [
                'success' => false,
                'error_code' => 'invalid_phone',
                'message' => 'رقم الهاتف غير صالح للإرسال عبر واتساب.',
            ];
        }

        try {
            $response = Http::connectTimeout(3)->timeout($this->timeout)
                ->withHeaders(['X-Gateway-Secret' => $this->secret])
                ->post("{$this->baseUrl}/api/messages/send", [
                    'session_id' => 'platform',
                    'phone' => $normalizedPhone,
                    'text' => $message,
                    'simulate_typing' => $simulateTyping,
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message_id' => $response->json('message_id'),
                ];
            }

            $errorCode = $response->json('error_code');
            $errMsg = $response->json('message') ?? 'تعذر إرسال رسالة منصة تدلّل عبر الواتساب';

            if ($response->status() === 400 || $errorCode === 'session_not_connected') {
                $errorCode = 'session_not_connected';
                Setting::set('platform_whatsapp_status', 'disconnected');
            }

            Log::warning("Platform WhatsApp send failed to {$normalizedPhone}: {$errMsg}", [
                'http_status' => $response->status(),
                'error_code' => $errorCode,
            ]);

            return [
                'success' => false,
                'error_code' => $errorCode ?: 'send_failed',
                'message' => $errMsg,
            ];
        } catch (\Throwable $e) {
            Log::error("Exception in Platform WhatsApp send to {$normalizedPhone}: {$e->getMessage()}");
            return [
                'success' => false,
                'error_code' => 'gateway_unreachable',
                'message' => 'خطأ في الاتصال ببوابة الواتساب للمنصة.',
            ];
        }
    }

    /**
     * Send high-priority transactional messages (OTP, VIP ticket, waitlist) cleanly:
     * 1) Tries the restaurant's own WhatsApp session first (syncing live state if needed, zero typing delay).
     * 2) Automatically falls back to the Platform Owner's official WhatsApp session if the restaurant session is down/disconnected.
     *
     * @return array{success: bool, channel: ?string, message_id?: ?string, error_code?: ?string, message?: ?string}
     */
    public function sendTransactionalWithFallback(Restaurant $restaurant, string $phone, string $message): array
    {
        $normalizedPhone = PhoneNormalizer::toWhatsAppDigits($phone);
        if ($normalizedPhone === '') {
            return [
                'success' => false,
                'channel' => null,
                'error_code' => 'invalid_phone',
                'message' => 'رقم الهاتف غير صالح للإرسال عبر واتساب.',
            ];
        }

        // Tier 1: Try the restaurant's own WhatsApp session
        $restaurantRes = $this->sendMessage($restaurant, $normalizedPhone, $message, false);
        if (! empty($restaurantRes['success'])) {
            return [
                'success' => true,
                'channel' => 'restaurant',
                'message_id' => $restaurantRes['message_id'] ?? null,
                'error_code' => null,
                'message' => null,
            ];
        }

        // Do not retry platform fallback if gateway explicitly verified the recipient has no WhatsApp account
        if (($restaurantRes['error_code'] ?? null) === 'not_on_whatsapp') {
            return [
                'success' => false,
                'channel' => null,
                'error_code' => 'not_on_whatsapp',
                'message' => $restaurantRes['message'] ?? 'الرقم المدخل غير مسجل في تطبيق واتساب.',
            ];
        }

        // Skip redundant network hit if the gateway microservice itself is unreachable
        if (($restaurantRes['error_code'] ?? null) === 'gateway_unreachable') {
            Log::warning("Transactional WhatsApp aborted: gateway unreachable for restaurant {$restaurant->id}");
            return [
                'success' => false,
                'channel' => null,
                'error_code' => 'gateway_unreachable',
                'message' => 'خدمة بوابة الواتساب غير متاحة حالياً.',
            ];
        }

        // Tier 2: Automatic fallback to Platform Owner's official WhatsApp session
        $platformLive = $this->getPlatformSessionStatus();
        $platformStatus = $platformLive['status'] ?? 'disconnected';

        if (in_array($platformStatus, ['connected', 'connecting'], true)) {
            $platformRes = $this->sendPlatformMessage($normalizedPhone, $message, false);
            if (! empty($platformRes['success'])) {
                Log::info("Transactional WhatsApp delivered via platform fallback for restaurant {$restaurant->id}");
                return [
                    'success' => true,
                    'channel' => 'platform',
                    'message_id' => $platformRes['message_id'] ?? null,
                    'error_code' => null,
                    'message' => null,
                ];
            }

            if (($platformRes['error_code'] ?? null) === 'not_on_whatsapp') {
                return [
                    'success' => false,
                    'channel' => null,
                    'error_code' => 'not_on_whatsapp',
                    'message' => $platformRes['message'] ?? 'الرقم المدخل غير مسجل في تطبيق واتساب.',
                ];
            }
        }

        Log::warning("Transactional WhatsApp failed on both restaurant ({$restaurant->id}) and platform channels", [
            'restaurant_status' => $restaurant->whatsapp_status,
            'restaurant_error' => $restaurantRes['error_code'] ?? null,
            'platform_status' => $platformStatus,
        ]);

        return [
            'success' => false,
            'channel' => null,
            'error_code' => 'whatsapp_disconnected',
            'message' => 'جلسات الواتساب غير متصلة حالياً لإرسال رمز التحقق.',
        ];
    }

    /**
     * Synchronize local restaurant record with gateway session state.
     */
    private function syncLocalState(Restaurant $restaurant, array $data): void
    {
        $status = $data['status'] ?? null;
        if (! $status) return;

        $updates = [];
        if ($status === 'connected') {
            Cache::forget("restaurant_whatsapp_otp_alert_{$restaurant->id}");
            if ($restaurant->whatsapp_status !== 'connected') {
                $updates['whatsapp_status'] = 'connected';
                $updates['whatsapp_phone'] = $data['phone'] ?? $restaurant->whatsapp_phone;
                $updates['whatsapp_connected_at'] = now();
            }
        } elseif ($status === 'disconnected') {
            if ($restaurant->whatsapp_status !== 'disconnected') {
                $updates['whatsapp_status'] = 'disconnected';
                $updates['whatsapp_phone'] = null;
                $updates['whatsapp_connected_at'] = null;
            }
        } else {
            if ($restaurant->whatsapp_status !== $status) {
                $updates['whatsapp_status'] = $status;
            }
        }

        if (! empty($updates)) {
            $restaurant->update($updates);
        }
    }
}
