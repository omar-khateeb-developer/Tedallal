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
            'role' => 'خبير منظومة المطاعم والضيافة الفاخرة',
            'welcome_message' => 'أهلاً بك! أنا مستشار «تَدلّل» الذكي للأعمال والضيافة. نساعد أصحاب المطاعم عبر 3 أركان حاسمة: (1) مضاعفة المبيعات، (2) حماية الأرباح ووقف الهدر، (3) راحة البال والتحكم عن بُعد. ما هو التحدي الأكبر لديك حالياً لنبدأ به؟',
            'starter_prompts' => [
                'كيف تساعدني تدلّل في زيادة مبيعات الطاولات بنسبة 25%؟ (Sales Booster)',
                'كيف أوقف هدر المطبخ وتلاعب الكاشير بدون أجهزة معقدة؟ (Profit Shield)',
                'كيف أستلم تقرير الإيراد اليومي على واتساب بسريري عند منتصف الليل؟ (Peace of Mind)',
                'جرب محاكي المطعم التفاعلي الحي في 60 ثانية (Interactive Live Demo) ⚡',
                'ما هي باقات الاشتراك الرسمية وفترة التدشين والاختبار في مطعمي؟',
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
