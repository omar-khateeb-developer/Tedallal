<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\ConsultationLead;
use App\Models\Restaurant;
use App\Models\Setting;
use App\Services\AiConsultantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $incomingSecret = $request->header('X-Gateway-Secret');
        $expectedSecret = (string) config('whatsapp.gateway_secret', 'tedallal_wa_gateway_2026_secure_key');

        if (! $incomingSecret || ! hash_equals($expectedSecret, $incomingSecret)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $event = (string) $request->input('event');
        $sessionId = (string) $request->input('session_id', '');
        $restaurantId = $request->input('restaurant_id');

        $isPlatform = ($sessionId === 'platform' || $event === 'platform_message.received' || empty($restaurantId));

        Log::info("WhatsApp webhook received event '{$event}' for " . ($isPlatform ? 'Platform Owner' : "restaurant {$restaurantId}"));

        // =========================================================================
        // 1. معالجة أحداث واتساب إدارة المنصة الخاص بصاحب المنصة (Super Admin)
        // =========================================================================
        if ($isPlatform) {
            if ($event === 'session.connected') {
                $phone = (string) $request->input('phone');
                Setting::set('platform_whatsapp_status', 'connected');
                Setting::set('platform_whatsapp_phone', $phone);
                Setting::set('platform_whatsapp_connected_at', now()->toIso8601String());

                return response()->json(['success' => true]);
            }

            if ($event === 'session.disconnected') {
                Setting::set('platform_whatsapp_status', 'disconnected');
                Setting::set('platform_whatsapp_phone', null);
                Setting::set('platform_whatsapp_connected_at', null);

                return response()->json(['success' => true]);
            }

            if ($event === 'platform_message.received') {
                $text = trim((string) $request->input('text'));
                $from = (string) $request->input('from');
                $senderName = (string) $request->input('sender_name') ?: 'صاحب مطعم (واتساب)';

                if (empty($text) || empty($from)) {
                    return response()->json(['success' => true]);
                }

                // حفظ أو تحديث العميل المحتمل تلقائياً في CRM المنصة
                try {
                    $lead = ConsultationLead::firstOrNew(['phone' => $from]);
                    if (! $lead->exists) {
                        $lead->name = $senderName;
                        $lead->restaurant_name = 'استفسار عبر واتساب المنصة المباشر';
                        $lead->status = 'new';
                        $lead->notes = "[واتساب " . now()->format('Y-m-d H:i') . "]: {$text}";
                        $lead->save();
                    } else {
                        $lead->notes = ($lead->notes ? $lead->notes . "\n---\n" : '') . "[واتساب " . now()->format('Y-m-d H:i') . "]: {$text}";
                        $lead->save();
                    }
                } catch (\Throwable $leadErr) {
                    Log::warning('Failed to auto-save ConsultationLead from WhatsApp: ' . $leadErr->getMessage());
                }

                // فحص تفعيل المساعد التنفيذي الذكي لصاحب المنصة
                $isAiEnabled = Setting::get('platform_whatsapp_ai_enabled', '1') === '1';

                if ($isAiEnabled) {
                    try {
                        $cacheKey = "platform_wa_chat_{$from}";
                        $history = (array) Cache::get($cacheKey, []);

                        $aiConsultant = app(AiConsultantService::class);
                        $result = $aiConsultant->consult($text, $history);
                        $replyText = $result['reply'] ?? null;

                        if ($replyText) {
                            $history[] = ['role' => 'user', 'content' => $text];
                            $history[] = ['role' => 'model', 'content' => $replyText];
                            if (count($history) > 10) {
                                $history = array_slice($history, -10);
                            }
                            Cache::put($cacheKey, $history, now()->addHours(24));

                            return response()->json([
                                'success' => true,
                                'auto_reply_text' => $replyText,
                            ]);
                        }
                    } catch (\Throwable $aiErr) {
                        Log::error('AI Consultant WhatsApp Auto-Reply Error: ' . $aiErr->getMessage());
                    }
                }

                return response()->json(['success' => true]);
            }

            return response()->json(['success' => true]);
        }

        // =========================================================================
        // 2. معالجة أحداث واتساب المطاعم الفردية المشتركة في المنظومة
        // =========================================================================
        $restaurant = Restaurant::find($restaurantId);
        if (! $restaurant) {
            return response()->json(['error' => 'Restaurant not found'], 404);
        }

        if ($event === 'session.connected') {
            $phone = (string) $request->input('phone');
            $restaurant->update([
                'whatsapp_status' => 'connected',
                'whatsapp_phone' => $phone ?: $restaurant->whatsapp_phone,
                'whatsapp_connected_at' => now(),
            ]);

            return response()->json(['success' => true]);
        }

        if ($event === 'session.disconnected') {
            $restaurant->update([
                'whatsapp_status' => 'disconnected',
                'whatsapp_phone' => null,
                'whatsapp_connected_at' => null,
            ]);

            return response()->json(['success' => true]);
        }

        if ($event === 'message.received') {
            $text = (string) $request->input('text');
            $from = (string) $request->input('from');

            // إذا كان المطعم مفعلاً للرد التلقائي
            if ($restaurant->isWhatsappAutoReplyActive()) {
                $reply = $restaurant->whatsapp_auto_reply_message;
                $reply = str_replace(
                    ['{restaurant_name}', '{menu_url}'],
                    [$restaurant->name, url("/m/{$restaurant->slug}")],
                    $reply
                );

                return response()->json([
                    'success' => true,
                    'auto_reply_text' => $reply,
                ]);
            }
        }

        return response()->json(['success' => true]);
    }
}
