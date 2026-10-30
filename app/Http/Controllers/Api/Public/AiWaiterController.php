<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\ApiController;
use App\Http\Middleware\ValidateTableSession;
use App\Services\AiWaiterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AiWaiterController extends ApiController
{
    public function __construct(private readonly AiWaiterService $aiWaiterService)
    {
    }

    /**
     * فحص ما إذا كانت ميزة الويتر الذكي مفعلة لهذا المطعم.
     */
    public function status(Request $request): JsonResponse
    {
        $session = ValidateTableSession::sessionFrom($request);
        $restaurant = $session->table->restaurant;

        return $this->success([
            'enabled' => $restaurant->hasAiWaiterFeature(),
            'restaurant_name' => $restaurant->name,
            'currency' => $restaurant->currency ?? 'SYP',
        ]);
    }

    /**
     * إرسال رسالة إلى الويتر الذكي وتلقي الرد والتوصيات.
     */
    public function chat(Request $request): JsonResponse
    {
        $session = ValidateTableSession::sessionFrom($request);
        $restaurant = $session->table->restaurant;

        if (!$restaurant->hasAiWaiterFeature()) {
            return $this->error('ميزة الويتر الذكي غير مفعلة لهذا المطعم من قبل الإدارة.', 403);
        }

        $request->validate([
            'message' => ['required', 'string', 'max:400'],
            'history' => ['nullable', 'array', 'max:8'],
        ]);

        // حماية الجلسة من الاستنزاف (حد أقصى 20 استفساراً لكل جلسة طاولة)
        $sessionKey = "ai_waiter_count_{$session->id}";
        $usageCount = (int) Cache::get($sessionKey, 0);
        if ($usageCount >= 20) {
            return $this->error('لقد بلغت الحد الأقصى للاستفسارات المتاحة لجلسة هذه الطاولة. نتشرف بخدمتكم دائماً!', 429);
        }
        Cache::put($sessionKey, $usageCount + 1, now()->addHours(3));

        $userMessage = trim($request->input('message'));
        $history = $request->input('history', []);

        $result = $this->aiWaiterService->chat($restaurant, $userMessage, $history);

        return $this->success($result);
    }
}
