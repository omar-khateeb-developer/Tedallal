<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Api\ApiController;
use App\Models\ConsultationLead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ConsultationLeadController extends ApiController
{
    public function index(Request $request)
    {
        $query = ConsultationLead::query()->latest();

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('restaurant_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%");
            });
        }

        $leads = $query->paginate($request->integer('per_page', 25));

        $stats = [
            'total' => ConsultationLead::count(),
            'new' => ConsultationLead::where('status', 'new')->count(),
            'contacted' => ConsultationLead::where('status', 'contacted')->count(),
            'qualified' => ConsultationLead::where('status', 'qualified')->count(),
            'closed_won' => ConsultationLead::where('status', 'closed_won')->count(),
            'closed_lost' => ConsultationLead::where('status', 'closed_lost')->count(),
        ];

        $items = collect($leads->items())->map(function ($lead) {
            $arr = $lead->toArray();
            $arr['ai_paused'] = Cache::has("platform_wa_paused_{$lead->phone}");
            return $arr;
        });

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => [
                'current_page' => $leads->currentPage(),
                'last_page' => $leads->lastPage(),
                'total' => $leads->total(),
            ],
            'stats' => $stats,
        ]);
    }

    public function update(Request $request, ConsultationLead $lead)
    {
        $validated = $request->validate([
            'status' => 'sometimes|required|string|in:new,contacted,qualified,closed_won,closed_lost',
            'notes' => 'sometimes|nullable|string|max:5000',
        ]);

        $lead->update($validated);

        $res = $lead->fresh()->toArray();
        $res['ai_paused'] = Cache::has("platform_wa_paused_{$lead->phone}");

        return $this->success($res);
    }

    /**
     * تفعيل أو إيقاف المساعد الذكي لمحادثة هذا العميل (Human Takeover Toggle)
     */
    public function toggleAi(ConsultationLead $lead): JsonResponse
    {
        $key = "platform_wa_paused_{$lead->phone}";
        if (Cache::has($key)) {
            Cache::forget($key);
            $paused = false;
        } else {
            Cache::put($key, true, now()->addHours(24));
            $paused = true;
        }

        return $this->success([
            'ai_paused' => $paused,
            'message' => $paused
                ? 'تم إيقاف المساعد الذكي لهذا العميل مؤقتاً لتتمكن من التحدث معه بحرية.'
                : 'تمت إعادة تفويض المساعد الذكي للرد على هذا العميل تلقائياً.',
        ]);
    }

    public function destroy(ConsultationLead $lead)
    {
        $lead->delete();

        return $this->success(['message' => 'تم حذف طلب الاستشارة بنجاح']);
    }

    /**
     * تدشين مساحة المطعم وتوليد حساب المالك بنقرة زر مع إرسال بيانات الدخول عبر الواتساب فوراً.
     */
    public function provision(Request $request, ConsultationLead $lead): JsonResponse
    {
        $cleanPhone = preg_replace('/[^0-9]/', '', (string)$lead->phone);
        if (empty($cleanPhone)) {
            return response()->json([
                'success' => false,
                'message' => 'رقم هاتف العميل غير صالح للتدشين.',
            ], 422);
        }

        // 1. توليد اسم نطاق فرعي وحيد للمطعم (Slug)
        $baseSlug = \Illuminate\Support\Str::slug($lead->restaurant_name) ?: 'restaurant';
        $slug = $baseSlug . '-' . rand(100, 999);
        while (\App\Models\Restaurant::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . rand(1000, 9999);
        }

        // 2. إنشاء سجل المطعم
        $isPrepaid = str_contains($lead->payment_model ?? '', 'مسبق');
        $restaurant = \App\Models\Restaurant::create([
            'name' => $lead->restaurant_name,
            'slug' => $slug,
            'phone' => $lead->phone,
            'address' => $lead->city ?: 'دمشق',
            'type' => $isPrepaid ? 'prepaid' : 'postpaid',
            'plan' => 'pro',
            'currency' => 'SYP',
            'status' => 'active',
            'subscription_expires_at' => now()->addDays(30),
        ]);

        // 3. إنشاء حساب المدير / المالك
        $tempPassword = 'Tedallal@' . rand(1000, 9999);
        $userEmail = 'owner_' . $cleanPhone . '@tedallal.tech';
        $existingUser = \App\Models\User::where('email', $userEmail)->first();
        if ($existingUser) {
            $userEmail = 'owner_' . $cleanPhone . '_' . rand(10, 99) . '@tedallal.tech';
        }

        $user = \App\Models\User::create([
            'restaurant_id' => $restaurant->id,
            'name' => $lead->name ?: 'المدير التنفيذي',
            'email' => $userEmail,
            'password' => \Illuminate\Support\Facades\Hash::make($tempPassword),
            'status' => \App\Enums\UserStatus::Active,
        ]);
        $user->assignRole(\App\Enums\UserRole::Admin->value);

        // 4. إنشاء 5 طاولات افتراضية مع رموز QR
        $tokenGen = app(\App\Services\TokenGenerator::class);
        $qrService = app(\App\Services\QrCodeService::class);
        for ($i = 1; $i <= 5; $i++) {
            $table = \App\Models\RestaurantTable::create([
                'restaurant_id' => $restaurant->id,
                'name' => 'طاولة ' . $i,
                'public_token' => $tokenGen->generatePublicToken(),
                'status' => 'active',
            ]);
            try {
                $qrService->generateForTable($table);
            } catch (\Throwable $e) {}
        }

        // 5. إنشاء أقسام وأطباق منيو أولية جاهزة للعمل
        $catMain = \App\Models\MenuCategory::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'الأطباق الرئيسية',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        \App\Models\MenuItem::create([
            'restaurant_id' => $restaurant->id,
            'menu_category_id' => $catMain->id,
            'name' => 'طبق شاورما عربي دبل',
            'description' => 'شاورما دجاج مميزة مع صوص الثومية والبطاطا المقرمشة ومخلل الخيار',
            'price' => 38000,
            'is_available' => true,
        ]);
        \App\Models\MenuItem::create([
            'restaurant_id' => $restaurant->id,
            'menu_category_id' => $catMain->id,
            'name' => 'برغر لحم أنجوس كلاسيك',
            'description' => 'لحم أنجوس مشوي مع جبنة الشيدر وصلصة تَدلّل الخاصة في خبز البريوش الطازج',
            'price' => 45000,
            'is_available' => true,
        ]);

        $catDrinks = \App\Models\MenuCategory::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'المشروبات والعصائر',
            'sort_order' => 2,
            'is_active' => true,
        ]);
        \App\Models\MenuItem::create([
            'restaurant_id' => $restaurant->id,
            'menu_category_id' => $catDrinks->id,
            'name' => 'عصير برتقال طبيعي طازج',
            'description' => 'عصير برتقال طبيعي طازج معصور على الطلب',
            'price' => 18000,
            'is_available' => true,
        ]);

        // 6. تحديث حالة الاستشارة
        $lead->status = 'closed_won';
        $lead->notes = trim(($lead->notes ? $lead->notes . "\n" : '') . "✅ تم تدشين المنشأة بنجاح (المطعم: {$restaurant->name} | النطاق: {$restaurant->slug}) بتاريخ " . now()->format('Y-m-d H:i'));
        $lead->save();

        // 7. إرسال رسالة ترحيب وبيانات الدخول عبر الواتساب فوراً لصاحب المطعم
        $loginUrl = url('/login');
        $menuUrl = url("/r/{$restaurant->slug}");
        $waMsg = "👑 *مبروك! تم تدشين مساحتكم السحابية في منظومة «تَدلّل» بنجاح!* ✨\n\n"
            . "🏢 *المطعم:* {$restaurant->name}\n\n"
            . "🔐 *بيانات الدخول للوحة التحكم الإدارية:*\n"
            . "• الرابط: {$loginUrl}\n"
            . "• اسم المستخدم: {$userEmail}\n"
            . "• كلمة المرور المؤقتة: {$tempPassword}\n\n"
            . "📱 *رابط منيو الزبائن الذكي لمطعمكم:*\n"
            . "{$menuUrl}\n\n"
            . "🚀 تم تجهيز الطاولات الخمس الأولى وقائمة الأطباق الأولية لك. فريقنا التقني جاهز لمساعدتكم في أي خطوة!";

        $waSent = false;
        try {
            $res = app(\App\Services\WhatsAppGatewayService::class)->sendPlatformMessage($lead->phone, $waMsg, false);
            $waSent = !empty($res['success']);
        } catch (\Throwable $waErr) {
            \Illuminate\Support\Facades\Log::warning('Provisioning WhatsApp send error: ' . $waErr->getMessage());
        }

        return $this->success([
            'restaurant' => [
                'id' => $restaurant->id,
                'name' => $restaurant->name,
                'slug' => $restaurant->slug,
                'menu_url' => $menuUrl,
            ],
            'credentials' => [
                'login_url' => $loginUrl,
                'email' => $userEmail,
                'password' => $tempPassword,
            ],
            'whatsapp_sent' => $waSent,
            'lead' => $lead->fresh(),
            'message' => 'تم تدشين المنشأة وتوليد حساب المالك وإرسال بيانات الدخول بالواتساب بنجاح!',
        ]);
    }

    /**
     * توليد «بطاقة الاتصال الذكية» المخصصة لهذا العميل (Smart Prospect Pitch Card)
     * تلخص لصاحب المنصة: الافتتاحية المخصصة، سؤال التشخيص الحاسم، الركن الذهبي الأنسب،
     * سلاح المحاكي التفاعلي، وطرق الرد على الاعتراضات بنقرة واحدة.
     */
    public function pitchCard(ConsultationLead $lead): JsonResponse
    {
        $name = $lead->name ?: 'صاحب المطعم';
        $restaurantName = $lead->restaurant_name ?: 'مطعمكم';
        $city = $lead->city ?: 'مدينتكم';
        $serviceType = $lead->service_type ?: 'غير محدد';
        $tables = $lead->tables_count ?: 10;
        $features = (array) ($lead->desired_features ?: []);

        // تحديد الركن الذهبي الأنسب وفق طبيعة التشغيل
        $isSalesFocus = str_contains($serviceType, 'كافيه') || str_contains($serviceType, 'سريع') || str_contains($serviceType, 'سناك') || $tables <= 12;
        $isProfitFocus = str_contains($serviceType, 'عائلات') || str_contains($serviceType, 'فاخر') || str_contains($serviceType, 'شامل') || in_array('شاشات مطبخ (KDS)', $features);
        $isPeaceOfMindFocus = str_contains($serviceType, 'فروع') || str_contains($serviceType, 'سحابي') || in_array('إدارة فروع متعددة', $features);

        if ($isPeaceOfMindFocus) {
            $primaryPillar = [
                'id' => 'peace_of_mind',
                'name' => 'ركن راحة البال والتحكم عن بُعد (Peace of Mind)',
                'badge' => 'راحة البال',
                'color' => 'blue',
                'why' => 'لأنه يدير عمليات متعددة أو فروعاً ويحتاج للمتابعة دون البقاء في الصالة طوال اليوم.',
                'hook_headline' => 'تقرير الإيراد الليلي على واتسابك بسريرك عند منتصف الليل ومتابعة كافة الفروع من هاتفك.',
                'talking_points' => [
                    'رسالة واتساب تلقائية بتمام 00:30 تلخص صافي المبيعات، عدد الطلبات، ونقدية الصندوق لكل فرع.',
                    'لوحة تحكم مركزية لمقارنة إيرادات الفروع والصالات لحظة بلحظة ومن أي مكان في العالم.',
                    'سجل صلاحيات دقيق للموظفين لمنع أي تجاوزات أو تعديل غير مصرح به على الفواتير.',
                ],
            ];
            $recommendedPlan = 'باقة السلاسل والفروع (Enterprise) - 65$ شهرياً';
        } elseif ($isProfitFocus) {
            $primaryPillar = [
                'id' => 'profit_shield',
                'name' => 'ركن درع الأرباح وحماية الصندوق (Profit Shield)',
                'badge' => 'حماية الأرباح',
                'color' => 'purple',
                'why' => 'لأن الصالة والمطبخ لديهم بحاجة لتنظيم ومنع ضياع الطلبات أو تسريب نقدية الكاشير.',
                'hook_headline' => 'إلغاء فوضى أوراق المطبخ بالـ KDS ومطابقة الوردية الحازمة بـ Z-Reports مع 0% عمولة.',
                'talking_points' => [
                    'شاشات المطبخ الذكية (KDS) تمنع خروج أي طبق دون أمر مسجل وتلغي أخطاء وتأخير الطلبات.',
                    'تقارير الوردية (Z-Reports) تكشف الفواتير المعدلة أو الملغاة فوراً وتطابق نقدية الصندوق.',
                    'نموذج 0% عمولة واشتراك شهري ثابت عادل بدلاً من عمولات تطبيقات التوصيل الباهظة (20-30%).',
                ],
            ];
            $recommendedPlan = 'الباقة الاحترافية (Pro) - 35$ شهرياً';
        } else {
            $primaryPillar = [
                'id' => 'sales_booster',
                'name' => 'ركن مضاعفة المبيعات وحجم الفاتورة (Sales Booster)',
                'badge' => 'زيادة المبيعات',
                'color' => 'amber',
                'why' => 'لأن الكافيهات ومطاعم الخدمة السريعة تربح من سرعة دوران الطاولات وزيادة قيمة الفاتورة.',
                'hook_headline' => 'الويتر الذكي لزيادة الفاتورة 25% وعجلة المكافآت التفاعلية لجمع داتا عملاء حقيقية للواتساب.',
                'talking_points' => [
                    'الويتر الذكي (AI Waiter) يقترح أطباقاً متناسقة ومشروبات جانبية ويزيد متوسط الفاتورة بنسبة 20-35%.',
                    'منيو 4K تفاعلي بالـ QR يتيح للزبون الطلب الفوري ونداء الويتر بنقرة زر دون تلويح.',
                    'عجلة المكافآت التفاعلية تجمع أرقام هواتف الزبائن لإعادة استهدافهم عبر واتساب بدون وسيط.',
                ],
            ];
            $recommendedPlan = ($tables <= 12) ? 'باقة الانطلاق (Starter) - 15$ شهرياً' : 'الباقة الاحترافية (Pro) - 35$ شهرياً';
        }

        $openingScript = "«مرحباً أستاذ {$name}، يعطيك ألف عافية. معك عمر خطيب من إدارة منصة «تَدلّل» للضيافة الفاخرة. اطلعت على استمارة التدشين الخاصة بمطعم «{$restaurantName}» في {$city}، وأحببت التواصل معك شخصياً لنقدم لكم خطة تشغيلية ترفع كفاءة صالتكم ومطبخكم.»";

        $diagnosticScript = "«أستاذ {$name}، قبل أن أحدثك عن أي ميزة برمجية، نحن في تَدلّل نرتكز على 3 أركان ذهبية فقط: (1) مضاعفة المبيعات ومعدل تكرار الزوار، (2) حماية الأرباح ووقف هدر المطبخ والكاشير، (3) راحة البال ومتابعة الصالة والإيراد عن بُعد بسريرك. أيٌّ من هذه الأركان الثلاثة يمثل الهاجس أو الأولوية القصوى لديك اليوم؟»";

        $demoPitchScript = "«أستاذ {$name}، لا أريدك أن تأخذ كلامي على عاتق الوعد؛ افتح معي الآن عبر هاتفك رابط المحاكي التفاعلي الحي: https://tedallal.tech/demo .. اطلب أي طبق، وشاهد كيف يصل في ثانية واحدة لشاشة المطبخ والكاشير بصوت تنبيه وطباعة الفاتورة أمامك مباشرة!»";

        $objectionHandlers = [
            [
                'objection' => '«ليس لدي ميزانية لشراء أجهزة كاشير أو شاشات جديدة باهظة»',
                'response' => '«منظومة تَدلّل سحابية بالكامل، لا نلزمك بشراء أي جهاز خاص! تعمل فوراً على أي موبايل، تابلت، كمبيوتر، أو شاشة تلفزيون متوفرة لديك حالياً، وتتصل بطابعاتك الحرارية بنقرة زر.»',
            ],
            [
                'objection' => '«أخاف أن يجد العمال والويترز صعوبة في استخدامه»',
                'response' => '«الواجهات مصممة بلمسة عربية راقية وبسيطة كأي تطبيق سوشال ميديا، والأهم أننا نمنحك فترة تدشين وتدريب ميداني 7 أيام كاملة مجاناً لمطعمك للتأكد من سلاسة كل شيء قبل تثبيت أي اشتراك.»',
            ],
            [
                'objection' => '«نحن نعمل حالياً على نظام محلي أو فودكس، ما الفرق؟»',
                'response' => '«تَدلّل تمنحك 0% عمولة، وتجمع المنيو، الـ KDS، الكاشير، وبوابة واتساب تسويقية مدمجة بدون اشتراكات وسيطة، وبأقل من ثلث تكلفة الأنظمة الأخرى مع دعم مباشر من مهندسينا.»',
            ],
        ];

        return $this->success([
            'lead_summary' => [
                'name' => $name,
                'restaurant_name' => $restaurantName,
                'city' => $city,
                'phone' => $lead->phone,
                'service_type' => $serviceType,
                'tables' => $tables,
            ],
            'opening_script' => $openingScript,
            'diagnostic_script' => $diagnosticScript,
            'primary_pillar' => $primaryPillar,
            'recommended_plan' => $recommendedPlan,
            'demo_pitch_script' => $demoPitchScript,
            'demo_url' => 'https://tedallal.tech/demo',
            'pilot_offer' => 'فترة تدشين وتشغيل اختباري مدعومة بالكامل 7 أيام داخل مطعمك (Pilot Phase) مجاناً.',
            'objection_handlers' => $objectionHandlers,
        ]);
    }
}

