<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Mail\PreSaleConsultationSubmitted;
use App\Models\ConsultationLead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class ConsultationController extends Controller
{
    /**
     * Display the pre-sale consultation and system assessment form.
     */
    public function index(Request $request): Response
    {
        $detectedCountry = $request->header('CF-IPCountry')
            ?? $request->header('X-Country-Code')
            ?? $request->server('HTTP_CF_IPCOUNTRY')
            ?? $request->server('GEOIP_COUNTRY_CODE')
            ?? null;

        if ($detectedCountry && in_array(strtoupper($detectedCountry), ['XX', 'T1', 'UNKNOWN', ''])) {
            $detectedCountry = null;
        }

        return Inertia::render('Consultation/Index', [
            'detectedCountry' => $detectedCountry ? strtoupper(trim($detectedCountry)) : null,
        ]);
    }

    /**
     * Store the submitted consultation questionnaire and notify the admin.
     */
    public function store(Request $request)
    {
        $validated = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:150'],
            'restaurant_name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:50'],
            'city' => ['nullable', 'string', 'max:100'],
            'service_type' => ['required', 'string', 'max:100'],
            'payment_model' => ['required', 'string', 'max:100'],
            'kitchen_setup' => ['required', 'string', 'max:100'],
            'desired_features' => ['required', 'array', 'min:1'],
            'desired_features.*' => ['string'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'name.required' => 'يرجى إدخال اسمك الكريم أو اسم المسؤول.',
            'restaurant_name.required' => 'يرجى تدوين اسم المطعم أو علامتك التجارية.',
            'phone.required' => 'يرجى تزويدنا برقم الهاتف أو الواتساب للتواصل.',
            'service_type.required' => 'يرجى تحديد أسلوب استقبال وخدمة الزبائن في مطعمك.',
            'payment_model.required' => 'يرجى تحديد آلية المحاسبة المعتمدة عند الكاشير.',
            'kitchen_setup.required' => 'يرجى اختيار أسلوب توزيع شاشات تحضير المطبخ.',
            'desired_features.required' => 'يرجى اختيار ميزة واحدة على الأقل تود تفعيلها في تدلّل.',
            'desired_features.min' => 'يرجى اختيار ميزة واحدة على الأقل تود تفعيلها في تدلّل.',
        ])->validate();

        $validated['ip_address'] = $request->ip();
        $validated['user_agent'] = substr((string) $request->userAgent(), 0, 500);
        $validated['status'] = 'new';

        $lead = ConsultationLead::create($validated);

        // Send notification email to Omar
        try {
            Mail::to('omar.khateeb.dev@gmail.com')->send(new PreSaleConsultationSubmitted($lead));
        } catch (\Throwable $e) {
            Log::error('Failed to send PreSale consultation notification email: ' . $e->getMessage(), [
                'lead_id' => $lead->id,
                'exception' => $e,
            ]);
        }

        // Generate tailored strategic blueprint
        $recommendations = $this->buildTailoredBlueprint($lead);

        return response()->json([
            'success' => true,
            'message' => 'تم استلام استمارة تقييم مطعمك بنجاح! تم إعداد البنية التوصية وسيتواصل معك مستشارنا التقني فوراً.',
            'lead_id' => $lead->id,
            'restaurant_name' => $lead->restaurant_name,
            'client_name' => $lead->name,
            'blueprint' => $recommendations,
        ]);
    }

    /**
     * Build an intelligent strategic blueprint based on the lead's answers.
     */
    protected function buildTailoredBlueprint(ConsultationLead $lead): array
    {
        $hasAiWaiter = in_array('الويتر الذكي (AI Waiter)', $lead->desired_features);
        $hasMultiKitchen = str_contains($lead->kitchen_setup, 'عدة شاشات') || str_contains($lead->kitchen_setup, 'نوافذ متعددة');
        $isPrepaid = str_contains($lead->payment_model, 'مسبق');

        $highlights = [];

        if ($hasMultiKitchen) {
            $highlights[] = 'توزيع ذكي تلقائي للطلبات على شاشات تحضير متعددة (KDS) لتنظيم الشيف ومنع التكدس والأخطاء.';
        } else {
            $highlights[] = 'شاشة مطبخ مركزية رقمية تربط طلبات الزبائن بالتحضير الفوري بدون فواتير ورقية ضائعة.';
        }

        if ($hasAiWaiter) {
            $highlights[] = 'مساعد ويتر ذكي بالذكاء الاصطناعي مبرمج على قائمة أطباقك لرفع متوسط قيمة الفاتورة (Upselling) وإجابة أسئلة الزبائن.';
        }

        if ($isPrepaid) {
            $highlights[] = 'مسار دفع مسبق فائق السرعة عبر الكاشير لضمان تحصيل الإيراد قبل بدء التجهيز.';
        } else {
            $highlights[] = 'جلسة طاولة متكاملة تسمح للزبائن بتكرار الطلب والإضافة في أي لحظة مع تسديد الفاتورة عند المغادرة.';
        }

        $highlights[] = 'لوحة تحكم وتحليلات حية تتيح لك مراقبة كفاءة المبيعات، ومعدل دوران الطاولات مباشرة من هاتفك.';

        return [
            'recommended_architecture' => 'نظام تدلّل السحابي المخصص لـ ' . $lead->restaurant_name,
            'estimated_turnover_boost' => '30% - 45% تسريع في دورة تجهيز الطلبات',
            'estimated_error_reduction' => 'القضاء على أخطاء الطلبات بنسبة 99%',
            'highlights' => $highlights,
        ];
    }
}
