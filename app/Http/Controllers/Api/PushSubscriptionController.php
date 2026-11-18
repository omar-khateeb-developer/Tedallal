<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Notifications\TedallalPushNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PushSubscriptionController extends Controller
{
    /**
     * Store or update a user's push subscription.
     */
    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => 'required|string',
            'keys.auth' => 'required|string',
            'keys.p256dh' => 'required|string',
        ]);

        $user = $request->user();

        if ($user && method_exists($user, 'updatePushSubscription')) {
            $user->updatePushSubscription(
                $validated['endpoint'],
                $validated['keys']['p256dh'],
                $validated['keys']['auth']
            );

            Log::info("Push subscription saved for user ID: {$user->id}");

            return response()->json([
                'success' => true,
                'message' => 'Push subscription saved successfully.',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'User not authenticated or missing push trait.',
        ], 401);
    }

    /**
     * Delete a push subscription.
     */
    public function unsubscribe(Request $request): JsonResponse
    {
        $request->validate([
            'endpoint' => 'required|string',
        ]);

        $user = $request->user();

        if ($user && method_exists($user, 'deletePushSubscription')) {
            $user->deletePushSubscription($request->endpoint);

            return response()->json([
                'success' => true,
                'message' => 'Push subscription removed successfully.',
            ]);
        }

        return response()->json(['success' => false], 400);
    }

    /**
     * Send an immediate test notification to verify delivery on closed device.
     */
    public function test(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        try {
            $user->notify(new TedallalPushNotification(
                title: '👑 تجربة إشعار تدلّل الملكي',
                body: 'تهانينا! نظام الإشعارات الفورية في الخلفية يعمل بنجاح فائق على جهازك، حتى عند قفل الهاتف.',
                url: '/admin/dashboard',
                data: [
                    'type' => 'test_ping',
                    'timestamp' => now()->toIso8601String(),
                ]
            ));

            return response()->json([
                'success' => true,
                'message' => 'Test notification dispatched to your registered devices.',
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to dispatch test push notification: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Return public VAPID key.
     */
    public function publicKey(): JsonResponse
    {
        return response()->json([
            'publicKey' => config('webpush.vapid.public_key') ?: env('VAPID_PUBLIC_KEY', ''),
        ]);
    }
}
