<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\ApiController;
use App\Services\AiConsultantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiConsultantController extends ApiController
{
    public function __construct(private readonly AiConsultantService $consultantService)
    {
    }

    /**
     * فحص حالة مستشار تدلّل للأعمال وجلب الأسئلة السريعة والترحيب.
     */
    public function status(): JsonResponse
    {
        return $this->success([
            'enabled' => true,
            'name' => 'مستشار تدلّل الذكي للأعمال',
            'role' => 'خبير منظومة المطاعم والضيافة',
            'welcome_message' => 'أهلاً بك! أنا مستشار تدلّل الذكي للأعمال والضيافة. كيف يمكنني مساعدتك اليوم في مضاعفة أرباح مطعمك أو استكشاف أي ميزة في المنظومة؟',
            'starter_prompts' => [
                'كيف تزيد تدلّل مبيعات وأرباح مطعمي وتلغي الهدر؟',
                'هل أحتاج لشراء أجهزة كاشير مخصصة ومكلفة؟',
                'كيف تعمل ميزة عجلة الحظ ورسائل الواتساب الذاتية؟',
                'ما الفرق بين تدلّل وتطبيقات التوصيل ذات العمولات؟',
                'هل تدلّل مناسبة لكافيه صغير أو عربة طعام؟',
            ],
        ]);
    }

    /**
     * معالجة استفسار الزائر وتقديم الرد الاستشاري الذكي.
     */
    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'message' => ['required', 'string', 'min:1', 'max:1000'],
            'history' => ['sometimes', 'array', 'max:10'],
            'history.*.role' => ['required_with:history', 'string', 'in:user,model,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:1000'],
        ], [
            'message.required' => 'يرجى كتابة رسالتك أو استفسارك.',
            'message.max' => 'الرسالة طويلة جداً، يرجى الاختصار في حدود 1000 حرف.',
        ]);

        $message = trim((string)$request->input('message'));
        $history = (array)$request->input('history', []);

        $result = $this->consultantService->consult($message, $history);

        return $this->success($result);
    }
}
