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
        $expectedSecret = (string) config('whatsapp.gateway_secret');

        if (empty($expectedSecret) || empty($incomingSecret) || ! hash_equals($expectedSecret, $incomingSecret)) {
            Log::warning('Unauthorized WhatsApp webhook attempt rejected', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
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

            // =========================================================================
            // أ) صاحب المنصة رد بنفسه من هاتفه على العميل (التدخل البشري التلقائي Human Takeover)
            // =========================================================================
            if ($event === 'platform_message.sent_by_owner') {
                $to = (string) $request->input('to');
                $ownerText = trim((string) $request->input('text'));

                if (! empty($to)) {
                    // إيقاف الرد الآلي مؤقتاً لهذا الرقم لمدة 12 ساعة ليتمكن صاحب المنصة من التحدث معه بحرية
                    Cache::put("platform_wa_paused_{$to}", true, now()->addHours(12));

                    try {
                        $lead = ConsultationLead::where('phone', $to)->first();
                        if ($lead) {
                            $lead->status = 'contacted';
                            if (! empty($ownerText)) {
                                $lead->notes = ($lead->notes ? $lead->notes . "\n---\n" : '') . "[صاحب المنصة " . now()->format('Y-m-d H:i') . "]: {$ownerText}";
                            }
                            $lead->save();
                        }
                    } catch (\Throwable $leadErr) {
                        Log::warning('Failed to update ConsultationLead on owner reply: ' . $leadErr->getMessage());
                    }
                }

                return response()->json(['success' => true]);
            }

            // =========================================================================
            // ب) صاحب المنصة أعاد تفويض المساعد الذكي لمحادثة العميل
            // =========================================================================
            if ($event === 'platform_message.owner_resumed_ai') {
                $to = (string) $request->input('to');
                if (! empty($to)) {
                    Cache::forget("platform_wa_paused_{$to}");
                    try {
                        $lead = ConsultationLead::where('phone', $to)->first();
                        if ($lead) {
                            $lead->notes = ($lead->notes ? $lead->notes . "\n---\n" : '') . "[إشعار " . now()->format('Y-m-d H:i') . "]: تمت إعادة تفعيل المساعد الذكي تلقائياً.";
                            $lead->save();
                        }
                    } catch (\Throwable $e) {}
                }

                return response()->json(['success' => true]);
            }

            // =========================================================================
            // ج) رسالة واردة من زبون / صاحب مطعم إلى واتساب المنصة
            // =========================================================================
            if ($event === 'platform_message.received') {
                $text = trim((string) $request->input('text'));
                $from = (string) $request->input('from');
                $senderName = (string) ($request->input('sender_name') ?: $request->input('name') ?: $request->input('pushname') ?: $request->input('notify_name'));

                if (empty($text) || empty($from)) {
                    return response()->json(['success' => true]);
                }

                // كشف ما إذا كان المرسل هو رقم هاتف صاحب المنصة الشخصي الفعلي
                $ownerPhone = (string) Setting::get('platform_owner_phone', '963934578117');
                $cleanFrom = preg_replace('/[^0-9]/', '', (string) $from);
                $cleanOwner = preg_replace('/[^0-9]/', '', (string) $ownerPhone);

                if (! empty($cleanOwner) && (str_ends_with($cleanFrom, substr($cleanOwner, -9)) || $cleanFrom === $cleanOwner)) {
                    Cache::put("platform_wa_paused_{$from}", true, now()->addHours(24));
                    Log::info("Platform WhatsApp message received from real verified owner {$from}. Auto-reply paused.");
                    return response()->json([
                        'success' => true,
                        'owner_message' => true,
                    ]);
                }

                // حفظ أو تحديث بيانات العميل (الهاتف والاسم) تلقائياً في CRM المنصة
                try {
                    $rawFrom = trim((string) $from);
                    $targetPhone = $cleanFrom ?: $rawFrom;

                    $lead = ConsultationLead::where(function ($q) use ($rawFrom, $cleanFrom) {
                        $q->where('phone', $cleanFrom)->orWhere('phone', $rawFrom);
                        if (strlen($cleanFrom) >= 9) {
                            $q->orWhere('phone', 'like', '%' . substr($cleanFrom, -9));
                        }
                    })->first();

                    $extractedName = $this->extractSenderName($text);
                    $extractedRest = $this->extractRestaurantName($text);
                    $extractedCity = $this->extractCityName($text);

                    $targetName = $extractedName ?: (!empty($senderName) && $senderName !== 'صاحب مطعم (واتساب)' ? $senderName : null);

                    if (! $lead) {
                        $lead = new ConsultationLead();
                        $lead->phone = $targetPhone;
                        $lead->name = $targetName ?: 'عميل واتساب محتمل';
                        $lead->restaurant_name = $extractedRest ?: 'استفسار عبر واتساب المنصة المباشر';
                        $lead->city = $extractedCity;
                        $lead->status = 'new';
                        $lead->notes = "[واتساب " . now()->format('Y-m-d H:i') . "]: {$text}";
                        $lead->save();
                    } else {
                        // تحديث الاسم إذا توفر اسم حقيقي
                        if ($targetName && (empty($lead->name) || in_array($lead->name, ['صاحب مطعم (واتساب)', 'عميل واتساب محتمل', 'غير محدد', '']))) {
                            $lead->name = $targetName;
                        }
                        // تحديث اسم المطعم إذا توفر
                        if ($extractedRest && ($lead->restaurant_name === 'استفسار عبر واتساب المنصة المباشر' || empty($lead->restaurant_name))) {
                            $lead->restaurant_name = $extractedRest;
                        }
                        // تحديث المدينة إذا توفرت
                        if ($extractedCity && empty($lead->city)) {
                            $lead->city = $extractedCity;
                        }
                        $lead->notes = ($lead->notes ? $lead->notes . "\n---\n" : '') . "[واتساب " . now()->format('Y-m-d H:i') . "]: {$text}";
                        $lead->save();
                    }
                } catch (\Throwable $leadErr) {
                    Log::warning('Failed to auto-save ConsultationLead from WhatsApp: ' . $leadErr->getMessage());
                }

                // الكشف إذا كانت المحادثة مستلمة بشرياً من قبل صاحب المنصة
                if (Cache::has("platform_wa_paused_{$from}")) {
                    // إذا طلب العميل صراحة إعادة التحدث مع الذكاء الاصطناعي
                    if (preg_match('/^(#تفعيل|#ذكاء|#تشغيل|\/resume|\/ai)$/i', $text)) {
                        Cache::forget("platform_wa_paused_{$from}");
                    } else {
                        Log::info("Platform WhatsApp auto-reply skipped for {$from}: Human takeover is active.");
                        return response()->json([
                            'success' => true,
                            'human_takeover' => true,
                        ]);
                    }
                }

                // الكشف الذكي عما إذا كان الزبون يطلب التحدث مع شخص / صاحب المنصة بشرياً (بالعربية أو الإنجليزية)
                $wantsHuman = preg_match('/(بدي|اريد|أريد|ممكن|حابب).*(اتكلم|احكي|اتواصل|أحكي|أكلم|أحدث).*(انسان|إنسان|شخص|بشري|صاحب|مسؤول|ادارة|إدارة|مدير)/u', $text)
                    || preg_match('/(want|need|like|can i)\s+to\s+(talk|speak|contact|reach).*(human|person|agent|representative|manager|owner|team|support)/i', $text);

                if ($wantsHuman) {
                    Cache::put("platform_wa_paused_{$from}", true, now()->addHours(12));

                    $hasArabic = (bool) preg_match('/[\x{0600}-\x{06FF}]/u', $text);
                    $hasEnglish = (bool) preg_match('/[a-zA-Z]/', $text);
                    $isEng = $hasEnglish && (! $hasArabic || (preg_match_all('/[a-zA-Z]/', $text) > preg_match_all('/[\x{0600}-\x{06FF}]/u', $text)));

                    $handoverReply = $isEng
                        ? "Tedallal's executive management has been notified 🔔\n\nA representative will review your message and follow up with you directly in this chat shortly. Welcome to Tedallal! ✨"
                        : "تم إشعار إدارة منصة «تدلّل» وصاحب المنصة فوراً 🔔\n\nوسيقوم الأستاذ بمتابعة محادثتك شخصياً عبر هذا الشات في أقرب وقت. أهلاً بك دائماً ✨";

                    // إرسال تنبيه واتساب فوري لصاحب المنصة على رقمه الخاص
                    try {
                        $ownerPhone = Setting::get('platform_owner_phone', '963934578117');
                        $cleanFrom = preg_replace('/[^0-9]/', '', (string) $from);
                        $alertMsg = "🔔 *طلب محادثة مباشرة مع الإدارة في منصة تَدلّل!* 👤\n\n"
                            . "📞 *رقم العميل:* {$from}\n"
                            . "💬 *رسالة العميل:* \"{$text}\"\n\n"
                            . "🔗 *رابط محادثته فوراً:*\n"
                            . "https://wa.me/{$cleanFrom}";

                        app(\App\Services\WhatsAppGatewayService::class)->sendPlatformMessage($ownerPhone, $alertMsg, false);
                    } catch (\Throwable $alertErr) {
                        Log::warning('Failed to send owner alert for human handover: ' . $alertErr->getMessage());
                    }

                    return response()->json([
                        'success' => true,
                        'auto_reply_text' => $handoverReply,
                    ]);
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
                            $replyText = AiConsultantService::sanitizeText($replyText);
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
            $senderName = (string) ($request->input('sender_name') ?: $request->input('name') ?: $request->input('pushname') ?: $request->input('notify_name'));

            // حفظ بيانات الزبون (الهاتف والاسم) فوراً في سجل عملاء المطعم CRM
            if (! empty($from) && $restaurant) {
                try {
                    $cleanFrom = preg_replace('/[^0-9]/', '', (string) $from);
                    $customer = \App\Models\RestaurantCustomer::withoutGlobalScopes()
                        ->where('restaurant_id', $restaurant->id)
                        ->where(function ($q) use ($from, $cleanFrom) {
                            $q->where('phone', $from)->orWhere('phone', $cleanFrom);
                            if (strlen($cleanFrom) >= 9) {
                                $q->orWhere('phone', 'like', '%' . substr($cleanFrom, -9));
                            }
                        })
                        ->first();

                    $extractedName = $this->extractSenderName($text);
                    $targetName = $extractedName ?: (!empty($senderName) && $senderName !== 'عميل واتساب' ? $senderName : null);

                    if (! $customer) {
                        \App\Models\RestaurantCustomer::withoutGlobalScopes()->create([
                            'restaurant_id' => $restaurant->id,
                            'name' => $targetName ?: 'عميل واتساب',
                            'phone' => $cleanFrom ?: $from,
                            'visits_count' => 1,
                            'total_spent' => 0,
                            'last_visit_at' => now(),
                        ]);
                    } else {
                        $updates = ['last_visit_at' => now()];
                        if ($targetName && (empty($customer->name) || $customer->name === 'عميل واتساب')) {
                            $updates['name'] = $targetName;
                        }
                        $customer->update($updates);
                    }
                } catch (\Throwable $custErr) {
                    Log::warning('Failed to auto-save RestaurantCustomer on inbound message: ' . $custErr->getMessage());
                }
            }

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

    /**
     * استخراج اسم العميل بذكاء إذا ذكره في رسالته (بالعربية أو الإنجليزية).
     */
    protected function extractSenderName(string $text): ?string
    {
        // 1. الأنماط العربية: "معك فلان", "اسمي فلان", "معكم فلان", "أنا فلان"
        if (preg_match('/(?:اسمي|معك|معكم|أنا|انا|محدثكم|أخوكم|اخوكم)\s+([^\n\r,\.!\?]{2,40})/ui', $text, $matches)) {
            $candidate = trim($matches[1]);
            // إزالة الكلمات الزائدة التابعة مثل "من مطعم..." أو "صاحب مطعم..." أو "بدي..."
            $candidate = preg_replace('/\s+(من|صاحب|مدير|شيف|مطعم|كافيه|مقهى|بدي|حابب|اريد|أريد|استفسر|أسأل).*/ui', '', $candidate);
            $candidate = trim($candidate, " \t\n\r\0\x0B-–—:؛،.");
            if (mb_strlen($candidate) >= 2 && mb_strlen($candidate) <= 35) {
                return $candidate;
            }
        }

        // 2. English patterns: "my name is X", "i am X", "this is X"
        if (preg_match('/(?:my name is|i am|i\'m|this is)\s+([a-zA-Z\s]{2,35})/i', $text, $matches)) {
            $candidate = trim($matches[1]);
            $candidate = preg_replace('/\s+(from|owner|manager|chef|calling|texting|asking|looking).*/i', '', $candidate);
            $candidate = trim($candidate);
            if (strlen($candidate) >= 2 && strlen($candidate) <= 35) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * استخراج اسم المطعم أو المنشأة إذا ورد في رسالة العميل.
     */
    protected function extractRestaurantName(string $text): ?string
    {
        if (preg_match('/(?:مطعم|كافيه|مقهى|سلسلة|فندق|شاورما|مشاوي|بيتزا)\s+([^\n\r,\.!\?]{2,40})/ui', $text, $matches)) {
            $candidate = trim($matches[0]);
            $candidate = preg_replace('/\s+(في|بمدينة|بـ|عندنا|عندي|حابب|بدي|اريد|أريد).*/ui', '', $candidate);
            $candidate = trim($candidate, " \t\n\r\0\x0B-–—:؛،.");
            if (mb_strlen($candidate) >= 3 && mb_strlen($candidate) <= 50) {
                return $candidate;
            }
        }
        return null;
    }

    /**
     * استخراج اسم المدينة إذا وردت في رسالة العميل.
     */
    protected function extractCityName(string $text): ?string
    {
        if (preg_match('/(?:في|من|بمدينة|مدينة)\s+(دمشق|حلب|حمص|اللاذقية|طرطوس|حماة|السويداء|درعا|الرياض|جدة|الدمام|مكة|المدينة|دبي|أبوظبي|الدوحة|مسقط|الكويت|عمان|بيروت|القاهرة)/ui', $text, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }
}
