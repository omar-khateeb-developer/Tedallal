<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        $event = $request->input('event');
        $restaurantId = $request->integer('restaurant_id');

        $restaurant = Restaurant::find($restaurantId);
        if (! $restaurant) {
            return response()->json(['error' => 'Restaurant not found'], 404);
        }

        Log::info("WhatsApp webhook received event '{$event}' for restaurant {$restaurantId}");

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

            // If the restaurant has auto-reply configured, reply back
            if ($restaurant->isWhatsappAutoReplyActive()) {
                $reply = $restaurant->whatsapp_auto_reply_message;
                // Replace potential placeholders
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

