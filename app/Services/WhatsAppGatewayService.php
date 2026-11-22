<?php

namespace App\Services;

use App\Models\Restaurant;
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
        $this->secret = (string) config('whatsapp.gateway_secret', 'tedallal_wa_gateway_2026_secure_key');
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
     * @return array{status: string, qr_code: ?string, phone: ?string, connected_at: ?string}
     */
    public function getSessionStatus(Restaurant $restaurant): array
    {
        try {
            $response = Http::timeout($this->timeout)
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
     * Send a background text message to a customer's Syrian WhatsApp number.
     *
     * @return array{success: bool, message_id?: string, message?: string}
     */
    public function sendMessage(
        Restaurant $restaurant,
        string $phone,
        string $message,
        bool $simulateTyping = true
    ): array {
        if (! $restaurant->isWhatsappConnected()) {
            return [
                'success' => false,
                'message' => 'رقم واتساب المطعم غير متصل بالخدمة.',
            ];
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders(['X-Gateway-Secret' => $this->secret])
                ->post("{$this->baseUrl}/api/messages/send", [
                    'restaurant_id' => $restaurant->id,
                    'phone' => $phone,
                    'text' => $message,
                    'simulate_typing' => $simulateTyping,
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message_id' => $response->json('message_id'),
                ];
            }

            $errMsg = $response->json('message') ?? 'تعذر إرسال الرسالة عبر الواتساب';
            Log::warning("WhatsApp send failed for restaurant {$restaurant->id}: {$errMsg}");

            return [
                'success' => false,
                'message' => $errMsg,
            ];
        } catch (\Throwable $e) {
            Log::error("Exception in WhatsApp send for restaurant {$restaurant->id}: {$e->getMessage()}");
            return [
                'success' => false,
                'message' => 'خطأ في الاتصال ببوابة الواتساب.',
            ];
        }
    }

    /**
     * Send an administrative / alert WhatsApp message from the Platform Owner's official session.
     *
     * @return array{success: bool, message_id?: string, message?: string}
     */
    public function sendPlatformMessage(string $phone, string $message, bool $simulateTyping = true): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders(['X-Gateway-Secret' => $this->secret])
                ->post("{$this->baseUrl}/api/messages/send", [
                    'session_id' => 'platform',
                    'phone' => $phone,
                    'text' => $message,
                    'simulate_typing' => $simulateTyping,
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message_id' => $response->json('message_id'),
                ];
            }

            $errMsg = $response->json('message') ?? 'تعذر إرسال رسالة منصة تدلّل عبر الواتساب';
            Log::warning("Platform WhatsApp send failed to {$phone}: {$errMsg}");

            return [
                'success' => false,
                'message' => $errMsg,
            ];
        } catch (\Throwable $e) {
            Log::error("Exception in Platform WhatsApp send to {$phone}: {$e->getMessage()}");
            return [
                'success' => false,
                'message' => 'خطأ في الاتصال ببوابة الواتساب للمنصة.',
            ];
        }
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

