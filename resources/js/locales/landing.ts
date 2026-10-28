export type Locale = 'ar' | 'en';

export interface LandingTranslation {
    meta: {
        title: string;
        description: string;
    };
    nav: {
        brandSubtitle: string;
        howItWorks: string;
        solutions: string;
        interactiveDemo: string;
        features: string;
        roiCalculator: string;
        comparison: string;
        directory: string;
        faqs: string;
        signIn: string;
        dashboard: string;
        bookDemo: string;
        currencyTitle: string;
        autoDetect: string;
    };
    hero: {
        statusBadge: string;
        version: string;
        titleLine1: string;
        titleLine2: string;
        subtitle: string;
        tryDemoBtn: string;
        bookConsultationBtn: string;
        badgeNoApp: string;
        badgeSockets: string;
        badgeMultiTenant: string;
    };
    solutions: {
        tag: string;
        title: string;
        subtitle: string;
        postpaidTitle: string;
        postpaidBadge: string;
        postpaidDesc: string;
        postpaidPoints: string[];
        prepaidTitle: string;
        prepaidBadge: string;
        prepaidDesc: string;
        prepaidPoints: string[];
    };
    directoryPromo: {
        tag: string;
        title: string;
        subtitle: string;
        badge: string;
        benefit1Title: string;
        benefit1Desc: string;
        benefit2Title: string;
        benefit2Desc: string;
        benefit3Title: string;
        benefit3Desc: string;
    };
    workflow: {
        tag: string;
        title: string;
        subtitle: string;
        togglePostpaid: string;
        togglePrepaid: string;
        postpaid: {
            title: string;
            subtitle: string;
            step1Title: string;
            step1Desc: string;
            step2Title: string;
            step2Desc: string;
            step3Title: string;
            step3Desc: string;
            step4Title: string;
            step4Desc: string;
        };
        prepaid: {
            title: string;
            subtitle: string;
            step1Title: string;
            step1Desc: string;
            step2Title: string;
            step2Desc: string;
            step3Title: string;
            step3Desc: string;
            step4Title: string;
            step4Desc: string;
        };
        step1Title: string;
        step1Desc: string;
        step2Title: string;
        step2Desc: string;
        step3Title: string;
        step3Desc: string;
        step4Title: string;
        step4Desc: string;
    };
    demo: {
        tag: string;
        title: string;
        subtitle: string;
        tabCustomer: string;
        tabKds: string;
        tabQr: string;
        tabAnalytics: string;
        // Customer Tab
        catGrills: string;
        catAppetizers: string;
        catDesserts: string;
        catDrinks: string;
        cartTitle: string;
        sendToKitchenBtn: string;
        // KDS Tab
        kdsHeaderTitle: string;
        kdsHeaderSubtitle: string;
        colNew: string;
        colCooking: string;
        colReady: string;
        btnStartCooking: string;
        btnMarkReady: string;
        btnMarkServed: string;
        // QR & Receipt Tab
        qrCardTitle: string;
        qrCardSubtitle: string;
        qrCardHint: string;
        receiptHeader: string;
        receiptTaxNo: string;
        receiptSubtotal: string;
        receiptDiscount: string;
        receiptTotal: string;
        receiptFooter: string;
        receiptHint: string;
        // Analytics Tab
        analyticsTitle: string;
        analyticsSubtitle: string;
        filterWeekend: string;
        filterWeekday: string;
        chartPeakTitle: string;
        chartTopDishesTitle: string;
        ordersUnit: string;
        aiInsightTitle: string;
        aiInsightText: string;
    };
    features: {
        tag: string;
        title: string;
        subtitle: string;
        f1Title: string;
        f1Desc: string;
        f2Title: string;
        f2Desc: string;
        f3Title: string;
        f3Desc: string;
        f4Title: string;
        f4Desc: string;
        f5Title: string;
        f5Desc: string;
        f6Title: string;
        f6Desc: string;
    };
    roi: {
        tag: string;
        title: string;
        subtitle: string;
        tablesLabel: string;
        tablesUnit: string;
        dailyOrdersLabel: string;
        dailyOrdersUnit: string;
        monthlyOrdersEst: string;
        ordersCountUnit: string;
        avgTimeSavedLabel: string;
        avgTimeSavedVal: string;
        paperSavingsTitle: string;
        paperSavingsNote: string;
        laborHoursTitle: string;
        laborHoursUnit: string;
        laborHoursNote: string;
        revenueBoostTitle: string;
        revenueBoostPerMonth: string;
        revenueBoostNote: string;
    };
    comparison: {
        tag: string;
        title: string;
        subtitle: string;
        colTraditional: string;
        colTedallal: string;
        r1Label: string;
        r1Trad: string;
        r1Ted: string;
        r2Label: string;
        r2Trad: string;
        r2Ted: string;
        r3Label: string;
        r3Trad: string;
        r3Ted: string;
        r4Label: string;
        r4Trad: string;
        r4Ted: string;
        r5Label: string;
        r5Trad: string;
        r5Ted: string;
    };
    testimonials: {
        tag: string;
        title: string;
        subtitle: string;
        t1Text: string;
        t1Author: string;
        t1Role: string;
        t2Text: string;
        t2Author: string;
        t2Role: string;
        t3Text: string;
        t3Author: string;
        t3Role: string;
    };
    faqs: {
        tag: string;
        title: string;
        subtitle: string;
        items: Array<{ q: string; a: string }>;
    };
    modal: {
        badge: string;
        title: string;
        subtitle: string;
        restaurantName: string;
        restaurantNamePlaceholder: string;
        contactPerson: string;
        contactPersonPlaceholder: string;
        phone: string;
        phonePlaceholder: string;
        country: string;
        tablesCount: string;
        tablesOption1: string;
        tablesOption2: string;
        tablesOption3: string;
        tablesOption4: string;
        submitBtn: string;
        successTitle: string;
        successDesc: string;
    };
    footer: {
        brandDesc: string;
        serverStatus: string;
        quickLinksTitle: string;
        portalAccessTitle: string;
        adminPortal: string;
        kdsPortal: string;
        superAdminPortal: string;
        requestAccount: string;
        rights: string;
    };
}

export const translations: Record<Locale, LandingTranslation> = {
    ar: {
        meta: {
            title: 'منصة تدلّل | الحل السحابي والمحاسبي الذكي لإدارة المطاعم والطلب عبر الـ QR',
            description: 'منظومة تدلّل (Tedallal) السحابية المتكاملة لنمو وأتمتة المطاعم والكافيهات. نظام طلب ذكي عبر رموز QR بدون تطبيقات، شاشات مطبخ KDS لحظية، كشوفات مبيعات يومية وشهرية وسنوية بدقة محاسبية 0% خطأ، وحلان مخصصان للصالة والوجبات السريعة.',
        },
        nav: {
            brandSubtitle: 'المنظومة المحاسبية والتشغيلية لأتمتة ونمو المطاعم',
            howItWorks: 'كيف يعمل؟',
            solutions: 'حلول المطاعم',
            interactiveDemo: 'المحاكي التفاعلي',
            features: 'مزايا المنظومة',
            roiCalculator: 'حاسبة الأرباح والتوفير',
            comparison: 'التقليدي vs تدلّل',
            directory: 'دليل المطاعم',
            faqs: 'الأسئلة الشائعة',
            signIn: 'تسجيل الدخول',
            dashboard: 'لوحة التحكم',
            bookDemo: 'طلب استشارة وعرض حي',
            currencyTitle: 'الدولة والعملة المعروضة',
            autoDetect: 'كشف تلقائي ⚡',
        },
        hero: {
            statusBadge: '💼 المنظومة المالية والتشغيلية المعتمدة لنمو أرباح المطاعم',
            version: 'جاهز للتشغيل في 10 دقائق ⚡',
            titleLine1: 'اهندِس أرباح مطعمك بدقة',
            titleLine2: 'واحمِ إيراداتك من الهدر المالي والعمولات',
            subtitle: 'حل سحابي ذكي صُمم بعقلية محاسبية صارمة: وفّر 25% من عمولات تطبيقات التوصيل، احمِ كل قرش من التسريب والسرقات، مع تقارير مبيعات يومية وشهرية وسنوية بدقة 0% خطأ، ومحركين مخصصين لمطاعم الصالة (الدفع اللاحق) والوجبات السريعة (الدفع المسبق).',
            tryDemoBtn: 'جرّب المحاكي التفاعلي الحي ⚡',
            bookConsultationBtn: 'احجز جلسة استشارية وتجربة مجانية',
            badgeNoApp: 'طلب فوري بكاميرا الجوال بدون تطبيقات',
            badgeSockets: 'سجل مبيعات محاسبي يومي وشهري وسنوي 0% خطأ',
            badgeMultiTenant: 'صفر عمولة 0% (أرباح مبيعاتك كاملة 100% لك)',
        },
        solutions: {
            tag: '🎯 حلول مخصصة حسب نموذج عملك',
            title: 'سواء كان مطعم صالة وضيافة أو وجبات سريعة.. تدلّل صُمم لأجلك',
            subtitle: 'نوفر لك المحرك التشغيلي الأنسب لطبيعة مطعمك لتضمن ضبطاً مالياً محكماً وتجربة ضيافة استثنائية.',
            postpaidTitle: 'محرك مطاعم الصالة والضيافة الراقية',
            postpaidBadge: 'نظام الدفع اللاحق (Postpaid)',
            postpaidDesc: 'مصمم للمطاعم العائلية والمقاهي لزيادة متوسط الفاتورة وتسريع تدوير الطاولات ومنع خروج أي طبق دون قيده المالي على حساب الطاولة.',
            postpaidPoints: [
                'حماية الإيراد وضبط المطبخ: ربط كل صنف يطلبه الزبون بحساب الطاولة فوراً، فلا يُطهى أي طبق دون تسجيل مالي دقيق.',
                'زيادة متوسط الفاتورة (AOV) بنسبة 28%: صور شهية واقتراحات ذكية تدفع الزبائن لطلب مقبلات وحلويات ومشروبات إضافية.',
                'تفرغ طاقم الخدمة للضيافة: بدلاً من إضاعة الوقت في تسجيل الأوردرات وركض النوادل، يتفرغ الطاقم للترحيب وخدمة الضيوف.',
                'إقفال مالي سريع عند المغادرة: يطلب الزبون الفاتورة بلمسة واحدة ويسدد نقداً أو بالشبكة مع إغلاق آلي محكم للطاولة.'
            ],
            prepaidTitle: 'محرك الوجبات السريعة ونوافذ الاستلام',
            prepaidBadge: 'نظام الدفع المسبق ونوافذ الاستلام (Prepaid)',
            prepaidDesc: 'هندسة تشغيلية فائقة السرعة تضمن استلام 100% من النقدية قبل الطهي، وتقضي على طوابير الكاشير نهائياً.',
            prepaidPoints: [
                'أمان مالي 100% وتصفير الفاقد: يدفع الزبون حسابه للكاشير أولاً، وفور تأكيد الدفع ينبثق الطلب في المطبخ (صفر وجبات غير مدفوعة).',
                'جوال الزبون هو شاشة النداء والتتبع: لا حاجة لأجهزة نداء باهظة الثمن؛ يرى الزبون مباشرة على شاشة هاتفه رقم النافذة التي استلمت طلبه ويتابع حالة تجهيزه لحظة بلحظة.',
                'تنظيم ذكي لنوافذ المطبخ: عند استلام شيف إحدى النوافذ للطلب، يظهر فوراً لباقي النوافذ أنه محجوز لمنع التكرار وهدر المكونات.',
                'مضاعفة مبيعات ساعات الذروة: خدمة مئات الزبائن في دقائق دون اختناق الكاشير أو تأخير تسليم الوجبات.'
            ],
        },
        directoryPromo: {
            tag: '🚀 تسويق مجاني لمطعمك',
            title: 'دليل تدلّل للمطاعم • زبائن جدد لمطعمك بـ 0% عمولة!',
            subtitle: 'بانضمامك إلى تدلّل، يحصل مطعمك على ظهور ترويجي مميز في دليل المطاعم الموحد لتصل إلى آلاف الزبائن في منطقتك.',
            badge: '0% عمولة مبيعات (وداعاً لاقتطاع تطبيقات التوصيل 25%)',
            benefit1Title: 'استعراض قائمتك وعروضك مجاناً',
            benefit1Desc: 'يتصفح الزوار منيو مطعمك، صور أطباقك اللذيذة، وموقعك الجغرافي لجذبهم لزيارة مطعمك مباشرة.',
            benefit2Title: 'كوبونات وحسومات حصرية',
            benefit2Desc: 'أطلق حملات خصم وكوبونات ترويجية خاصة بمطعمك لجذب زبائن جدد وتشجيعهم على تكرار الزيارة.',
            benefit3Title: 'أرباحك كاملة 100% لك',
            benefit3Desc: 'لا نقتطع أي نسب مئوية من مبيعاتك نهائياً؛ احتفظ بكامل أرباحك وعائد جهدك دون أي شركاء خفيين.'
        },
        workflow: {
            tag: '⚡ دورة عمل مؤتمتة وسريعة',
            title: 'كيف تتدفق تجربة الطلب في مطعمك؟',
            subtitle: 'اختر نموذج تشغيل مطعمك لمشاهدة التسلسل الدقيق لدورة حياة الطلب من جوال الزبون حتى التسليم.',
            togglePostpaid: '🍽️ مطاعم الصالة والضيافة (الدفع اللاحق)',
            togglePrepaid: '⚡ الوجبات السريعة ونوافذ الاستلام (الدفع المسبق)',
            postpaid: {
                title: 'دورة حياة الطلب في مطاعم الصالة والضيافة (الدفع اللاحق)',
                subtitle: 'تجربة ضيافة راقية ومريحة تتيح لزبائنك الطلب على دفعات دون انتظار الويتر وإقفال الفاتورة عند المغادرة.',
                step1Title: 'المسح والطلب الفوري',
                step1Desc: 'يمسح الزبون رمز الـ QR على الطاولة بكاميرا الجوال، ليفتح المنيو الذكي فوراً ويبدأ بالطلب دون انتظار النادل.',
                step2Title: 'وصول فوري للمطبخ والكاشير',
                step2Desc: 'تصل تفاصيل الأوردر آلياً وبأجزاء من الثانية إلى شاشات الطهاة وطابعات البونات مع تقييدها على حساب الطاولة.',
                step3Title: 'شاشات المطبخ الذكية KDS',
                step3Desc: 'تنظيم محطات الطهي مع مؤقتات دقيقة تضمن سرعة تسليم الأطباق ساخنة مع إمكانية طلب أطباق إضافية بسهولة.',
                step4Title: 'الطباعة ودفع الحساب عند المغادرة',
                step4Desc: 'يطلب الزبون الفاتورة بنقرة زر ويسدد الحساب نقداً أو بالشبكة مع الويتر أو الكاشير ليتم إغلاق الطاولة تلقائياً.',
            },
            prepaid: {
                title: 'دورة حياة الطلب في الوجبات السريعة ونوافذ الاستلام (الدفع المسبق)',
                subtitle: 'سرعة فائقة وإلغاء تام لطوابير الكاشير، وتوجيه آلي للأصناف حسب نوافذ التحضير مع تتبع حي للزبون.',
                step1Title: 'الطلب الفوري وتوجيه الزبون للكاشير',
                step1Desc: 'يمسح الزبون الـ QR ويختار وجبته، لتظهر له فوراً رسالة: «يرجى التوجه للكاشير و اخباره برقم طلبك لتدفع الفاتورة ليتم تحضير طلبك».',
                step2Title: 'تأكيد الكاشير وانبثاق الطلب للمطبخ',
                step2Desc: 'يخبر الزبون الكاشير برقم طلبه، وبنقرة زر واحدة يؤكد الكاشير الدفع، لتنبثق تذكرة الطلب فوراً على شاشات نوافذ المطابخ.',
                step3Title: 'استلام النافذة وتنبيه باقي الطهاة',
                step3Desc: 'عندما يضغط الشيف في إحدى النوافذ (مثلاً: نافذة رقم 2) على استلام الطلب، يظهر فوراً لباقي النوافذ أن الطلب تم استلامه لمنع التضارب.',
                step4Title: 'إشعار شاشة الزبون والتتبع المباشر',
                step4Desc: 'يظهر فوراً على شاشة جوال الزبون: «تم استلام طلبك من قِبَل النافذة رقم 2» ثم يظهر تتبع حالة طلب الزبون حتى جاهزية الاستلام.',
            },
            step1Title: 'المسح والطلب الفوري',
            step1Desc: 'يمسح الزبون رمز الـ QR بكاميرا هاتفه، ليفتح المنيو الذكي الفاتح للشهية فوراً بدون أي تطبيق وبدون انتظار الويتر.',
            step2Title: 'وصول فوري للمطبخ والكاشير',
            step2Desc: 'بمجرد تأكيد الطلب، يصل الأوردر إلى شاشات الطهاة وطابعات البونات آلياً في أجزاء من الثانية دون أي أخطاء يدوية.',
            step3Title: 'شاشة المطبخ الذكية KDS',
            step3Desc: 'تنظيم محطات الطهي (جديد، قيد التحضير، جاهز للتقديم) مع مؤقتات دقيقة تضمن سرعة تسليم الوجبة ساخنة.',
            step4Title: 'الطباعة وإقفال الحساب',
            step4Desc: 'طباعة بونات المطبخ والفواتير الضريبية تلقائياً على طابعاتك الحالية مع تحديث لحظي للمبيعات والتقارير.',
        },
        demo: {
            tag: '🎮 تجربة تفاعلية حيّة حقيقية',
            title: 'جرّب المنظومة بنفسك وكأنك في مطعمك الآن',
            subtitle: 'تنقّل بين أركان النظام الأربعة، وأضف عناصر للسلة واشهد تحديث المطبخ والطباعة والتحليلات آنياً.',
            tabCustomer: 'منيو الزبون الذكي (Mobile)',
            tabKds: 'شاشة المطبخ الحية (KDS)',
            tabQr: 'بطاقة الـ QR والطباعة',
            tabAnalytics: 'التقارير وسجل المبيعات (0% خطأ)',
            catGrills: 'المشاوي الحلبية',
            catAppetizers: 'المقبلات والمازة',
            catDesserts: 'الحلويات الشرقية',
            catDrinks: 'المشروبات والعصائر',
            cartTitle: 'سلة الطاولة الحالية',
            sendToKitchenBtn: 'إرسال للمطبخ ⚡',
            kdsHeaderTitle: 'شاشة تنظيم عمليات المطبخ الذكية (KDS Live Flow)',
            kdsHeaderSubtitle: 'تتحدث فوراً عند طلب العميل دون الحاجة لإعادة تحميل الصفحة',
            colNew: 'طلبات جديدة (New)',
            colCooking: 'قيد التحضير (Cooking)',
            colReady: 'جاهز للتقديم (Ready)',
            btnStartCooking: 'بدء التحضير 👨‍🍳',
            btnMarkReady: 'جاهز للتقديم 🍽️',
            btnMarkServed: 'تم التقديم ✅',
            qrCardTitle: 'بطاقة الطاولة الأنيقة (Table Stand)',
            qrCardSubtitle: 'جاهزة للطباعة والتثبيت على طاولات المطعم',
            qrCardHint: 'امسح الـ QR بجوالك لبدء تجربة الطلب الفعلي على طاولة 04',
            receiptHeader: 'فاتورة ضريبية مبسطة',
            receiptTaxNo: 'الرقم الضريبي: 310294857300003',
            receiptSubtotal: 'المجموع الفرعي:',
            receiptDiscount: 'كود خصم (TEDALLAL15):',
            receiptTotal: 'الإجمالي الصافي:',
            receiptFooter: 'شكراً لزيارتكم • صحتين وهنا!',
            receiptHint: 'طباعة متوافقة مع كافة طابعات الكاشير والمطبخ الحرارية القياسية عبر المتصفح مباشرة دون أي إضافات خارجية.',
            analyticsTitle: 'سجل المبيعات المحاسبي والتحليلات التنبؤية (0% نسبة خطأ)',
            analyticsSubtitle: 'كشوفات مالية يومية وشهرية وسنوية معتمدة تفصل إجمالي المبيعات، الخصومات الممنوحة، والصافي المحقق مع كشف ساعات الذروة.',
            filterWeekend: 'عطلة نهاية الأسبوع (خميس - سبت)',
            filterWeekday: 'أيام الأسبوع العادية',
            chartPeakTitle: 'حجم الطلبات الساعي واكتشاف أوقات الذروة',
            chartTopDishesTitle: 'الأصناف الأكثر مبيعاً ونسبة مساهمتها',
            ordersUnit: 'طلب',
            aiInsightTitle: 'اقتراح ذكي من محرك Tedallal Insights:',
            aiInsightText: 'تظهر البيانات ارتفاع طلبات "المقبلات المشكلة" بين الساعة 8 و 10 مساءً بنسبة 45%. نقترح إنشاء عرض كومبو يجمع المشاوي مع المقبلات لرفع متوسط قيمة الفاتورة بمقدار',
        },
        features: {
            tag: '🚀 نمو مبيعات وكفاءة تشغيلية',
            title: 'مزايا حصرية صُممت لزيادة أرباح مطعمك',
            subtitle: 'صُممت تدلّل بأحدث حلول الضيافة السحابية لتضمن تشغيل سلس، أرباح أعلى، وزبائن أكثر سعادة.',
            f1Title: 'تسريع دوران الطاولات 35%',
            f1Desc: 'القضاء على أوقات الانتظار المملة يتيح استقبال زبائن أكثر في أوقات الذروة وزيادة المبيعات اليومية.',
            f2Title: 'رفع متوسط الفاتورة بنسبة 28%',
            f2Desc: 'صور الأطباق الشهية واقتراحات الإضافات والمشروبات التلقائية (Upselling) تدفع الزبون لزيادة مشترياته.',
            f3Title: 'شاشات مطبخ تفاعلية تلغي الفوضى',
            f3Desc: 'وداعاً للبونات الضائعة؛ شاشات منظمة للشيفات حسب محطات الطهي مع مؤقتات دقيقة لتسليم الوجبة ساخنة وبأعلى جودة.',
            f4Title: 'تشغيل فوري على طابعاتك الحالية',
            f4Desc: 'لا تكاليف إضافية لشراء أجهزة؛ النظام متوافق مباشرة مع كافة طابعات الكاشير والمطبخ 80mm المتوفرة لديك.',
            f5Title: 'تسويق مجاني في دليل تدلّل',
            f5Desc: 'ظهور مطعمك وعروضك وكوبوناتك أمام آلاف الزوار في دليل تدلّل الموحد لجلب زبائن جدد بـ 0% عمولة على المبيعات.',
            f6Title: 'سجل وتقارير مبيعات محاسبية 0% خطأ',
            f6Desc: 'كشوفات مبيعات يومية وشهرية وسنوية دقيقة خالية من أخطاء الفاصلة العائمة، تقارن إجمالي المبيعات، الخصومات، وصافي الدخل مع عزل التالف والملغي.',
        },
        roi: {
            tag: '💰 حاسبة العائد والتوفير المالي',
            title: 'كم ستوفر وتربح عند تشغيل «تدلّل» في مطعمك؟',
            subtitle: 'حرّك المؤشرات أدناه وفق حجم مطعمك الحالي، واكتشف فوراً حجم التوفير المالي وزيادة الأرباح الناتجة عن تسريع الخدمة.',
            tablesLabel: 'عدد الطاولات في المطعم:',
            tablesUnit: 'طاولة',
            dailyOrdersLabel: 'متوسط الطلبات اليومية لكل طاولة:',
            dailyOrdersUnit: 'طلبات',
            monthlyOrdersEst: 'إجمالي الطلبات الشهرية المتوقعة:',
            ordersCountUnit: 'طلب',
            avgTimeSavedLabel: 'متوسط الوقت الموفر لكل طاولة:',
            avgTimeSavedVal: '10 دقائق',
            paperSavingsTitle: 'التوفير السنوي في طباعة المنيو الورقي التالف:',
            paperSavingsNote: 'توفير مباشر بإلغاء طباعة القوائم وتعديل الأسعار ورقياً',
            laborHoursTitle: 'الوقت الإجمالي الموفر شهرياً لطاقم العمل:',
            laborHoursUnit: 'ساعة',
            laborHoursNote: 'يتفرغ الطاقم للترحيب والضيافة بدلاً من تسجيل الأوردرات',
            revenueBoostTitle: 'زيادة المبيعات التقديرية وتوفير العمولات:',
            revenueBoostPerMonth: '/ شهرياً',
            revenueBoostNote: 'بفضل تسريع تدوير الطاولات وتوفير 25% من عمولات تطبيقات التوصيل',
        },
        comparison: {
            tag: '⚖️ المقارنة الحاسمة',
            title: 'المطعم التقليدي مقابل مطعم مدعوم بـ «تدلّل»',
            subtitle: 'تعرف على الفارق الجذري في التكاليف والسرعة والرقابة المالية بين الطريقة اليدوية والحل الرقمي الذكي.',
            colTraditional: 'المطاعم بالطريقة التقليدية ❌',
            colTedallal: 'المطاعم مع منصة تدلّل ⚡',
            r1Label: 'طريقة طلب العميل',
            r1Trad: 'انتظار النادل لإحضار المنيو ثم انتظاره مجدداً لتسجيل الطلب (5-15 دقيقة)',
            r1Ted: 'مسح فوري للـ QR وبدء الطلب خلال 10 ثوانٍ دون انتظار أي شخص',
            r2Label: 'تحديث الأسعار والأصناف',
            r2Trad: 'إعادة طباعة ورقية مكلفة وبطيئة، أو وضع ملصقات مشوهة للمنيو',
            r2Ted: 'تعديل فوري من لوحة التحكم بنقرة واحدة ينعكس لجميع الزبائن فوراً',
            r3Label: 'إيصال الطلب للمطبخ',
            r3Trad: 'مشي النادل للكاشير، ثم طباعة بون ورقي قد يضيع أو يتعرض للبلل',
            r3Ted: 'إرسال رقمي فوري لشاشات المطبخ وطابعات البونات في ثانية واحدة بدون أخطاء السماع',
            r4Label: 'الرقابة المالية ومنع التسريب',
            r4Trad: 'فواتير ورقية ضائعة، تضارب في الصندوق، وصعوبة معرفة الوجبات الملغاة أو المهدرة',
            r4Ted: 'تقييد مالي لحظي لكل طلب، وسجل مبيعات يومي وشهري وسنوي بدقة محاسبية 0% خطأ',
            r5Label: 'دقة وتخصيص الطلبات',
            r5Trad: 'أخطاء شائعة في سماع التفضيلات والملاحظات (بدون بصل، صوص إضافي)',
            r5Ted: 'الزبون يحدد بنفسه خياراته وملاحظاته بدقة دون أي مجال للخطأ البشري',
        },
        testimonials: {
            tag: '⭐ شهادات الشركاء والعملاء',
            title: 'ماذا يقول رواد قطاع الضيافة والمطاعم؟',
            subtitle: 'تجارب حقيقية من مدراء وملاك مطاعم وكافيهات قفزت أرقام تشغيلهم بعد اعتماد تدلّل.',
            t1Text: '«قلصنا زمن انتظار العميل لتسجيل الطلب من 12 دقيقة إلى صفر ثانية. شاشات المطبخ KDS في تدلّل نظمت عمل الشيفات تماماً وألغت فوضى الأوراق نهائياً.»',
            t1Author: 'المهندس طارق السعيد',
            t1Role: 'مالك سلسلة مطاعم ومقاهي دمشقية',
            t2Text: '«أفضل استثمار قمنا به هذا العام. وفرنا تكاليف طباعة المنيو الموسمية، وارتفع متوسط قيمة الفاتورة بنسبة 18% بفضل صور الأطباق الاحترافية في منيو الجوال.»',
            t2Author: 'سارة العبدالله',
            t2Role: 'مديرة العمليات والضيافة',
            t3Text: '«السرعة في تدلّل استثنائية. الخدمة السحابية تعمل بثبات فائق حتى في أوقات الذروة المزدحمة. أنصح أي مالك مطعم أو كافيه بالانضمام فوراً.»',
            t3Author: 'الشيف فهد التميمي',
            t3Role: 'الشيف التنفيذي والشريك المؤسس',
        },
        faqs: {
            tag: '💬 الأسئلة الأكثر شيوعاً',
            title: 'كل ما تحتاج معرفته عن منصة تدلّل',
            subtitle: 'إجابات واضحة ومباشرة عن الاستفسارات التشغيلية لمنظومة تدلّل.',
            items: [
                {
                    q: 'هل يحتاج زبائن المطعم إلى تنزيل أي تطبيق لطلب الطعام؟',
                    a: 'لا نهائياً! بمجرد توجيه كاميرا هاتف الزبون (iOS أو Android) إلى رمز الـ QR الموجود على الطاولة، يفتح المنيو التفاعلي مباشرة في المتصفح الافتراضي خلال أقل من ثانيتين.',
                },
                {
                    q: 'كيف تصل طلبات الزبائن إلى المطبخ والكاشير فوراً؟',
                    a: 'بمجرد ضغط الزبون على «تأكيد الطلب»، تُبث بيانات الأوردر سحابياً في أقل من ثانية إلى شاشات المطبخ وطابعات البونات، ليرى الطهاة تفاصيل الوجبة وملاحظاتها الخاصة مباشرة وبدون أي تأخير.',
                },
                {
                    q: 'هل يدعم النظام الربط مع طابعات الفواتير الحرارية في الكاشير والمطبخ؟',
                    a: 'نعم، تدعم تدلّل الطباعة الحرارية المباشرة عبر المتصفح لقياس 80mm القياسي لكافة أنواع الطابعات (Epson, Star, Xprinter وغيرها)، مع تصميم فواتير ضريبية مبسطة وبونات تشغيل للمطبخ.',
                },
                {
                    q: 'ماذا يحدث إذا تضرر كود الـ QR على إحدى الطاولات؟',
                    a: 'بكل تأكيد، تتيح لوحة تحكم المطعم إعادة توليد رمز الـ QR للطاولة بنقرة زر واحدة بشكل فوري وطباعته مجدداً، مع إبقاء سجل الطلبات والمبيعات التاريخية سليماً دون أي تأثر.',
                },
                {
                    q: 'هل يدعم النظام تشغيل فروع متعددة للمطعم؟',
                    a: 'نعم بكل سهولة، تتيح المنصة إدارة جميع فروع مطعمك من مكان واحد، مع تخصيص المنيو والأسعار لكل فرع، ولوحة تحكم مركزية للمالك لمتابعة أداء ومبيعات الفروع لحظة بلحظة.',
                },
            ],
        },
        modal: {
            badge: '⚡ انضم لنخبة المطاعم الذكية',
            title: 'طلب فتح حساب وعرض تجريبي',
            subtitle: 'املأ بيانات مطعمك وسيقوم مستشار حلول الضيافة بالتواصل معك لتجهيز بيئة الاختبار الخاصة بك.',
            restaurantName: 'اسم المطعم أو الكافيه',
            restaurantNamePlaceholder: 'مثال: مطعم شاورما وكباب زمان',
            contactPerson: 'اسم المسؤول / المالك',
            contactPersonPlaceholder: 'مثال: أ. عبد الرحمن الخالدي',
            phone: 'رقم الجوال (مع رمز الدولة)',
            phonePlaceholder: '+966 50 123 4567',
            country: 'الدولة',
            tablesCount: 'عدد الطاولات التقديري',
            tablesOption1: 'أقل من 15 طاولة',
            tablesOption2: '15 - 35 طاولة',
            tablesOption3: '35 - 70 طاولة',
            tablesOption4: 'أكثر من 70 طاولة (فروع متعددة)',
            submitBtn: 'تأكيد إرسال الطلب والتواصل معي 🚀',
            successTitle: 'تم استلام طلبك بنجاح!',
            successDesc: 'شكراً لك. سيتواصل معك مستشار حلول الضيافة لدى «تدلّل» خلال ساعات قليلة عبر الواتساب لتجهيز العرض الحي لحساب مطعمك.',
        },
        footer: {
            brandDesc: 'منظومة سحابية متطورة مصممة خصيصاً للمطاعم والكافيهات لزيادة الأرباح وأتمتة دورة الطلب من الطاولة حتى شاشات المطبخ والطباعة الحرارية.',
            serverStatus: 'الخدمة السحابية تعمل بكفاءة 99.98% على مدار الساعة',
            quickLinksTitle: 'روابط سريعة',
            portalAccessTitle: 'بوابات النظام',
            adminPortal: 'دخول إدارة المطعم (Admin)',
            kdsPortal: 'دخول طاقم المطبخ والخدمة (KDS Staff)',
            superAdminPortal: 'بوابة مالك المنصة (Super Admin)',
            requestAccount: 'طلب فتح حساب مطعم جديد',
            rights: 'جميع الحقوق محفوظة لمنصة تدلّل ©',
        },
    },

    en: {
        meta: {
            title: 'Tedallal Platform | Certified Financial & Cloud Hospitality Automation for Restaurants',
            description: 'Tedallal is the all-in-one cloud operating and financial platform for restaurants and cafés. Smart QR ordering without apps, real-time KDS kitchen screens, daily/monthly/annual sales ledgers with 0% error margin, and dual engines for Dine-in and Fast Casual.',
        },
        nav: {
            brandSubtitle: 'Financial & Operational Cloud Automation for Restaurants',
            howItWorks: 'How It Works',
            solutions: 'Solutions',
            interactiveDemo: 'Live Demo',
            features: 'Platform Benefits',
            roiCalculator: 'ROI Calculator',
            comparison: 'Legacy vs Tedallal',
            directory: 'Dining Directory',
            faqs: 'FAQ',
            signIn: 'Sign In',
            dashboard: 'Dashboard',
            bookDemo: 'Book a Live Demo',
            currencyTitle: 'Display Country & Currency',
            autoDetect: 'Auto-detected ⚡',
        },
        hero: {
            statusBadge: '💼 The Certified Financial & Operating Platform for Restaurant Profit Growth',
            version: 'Live in 10 Minutes ⚡',
            titleLine1: 'Engineer Your Restaurant Profits',
            titleLine2: 'And Eliminate Financial Leaks & Commissions',
            subtitle: 'A cloud hospitality system built with strict accounting rigor: eliminate 25% food delivery app commissions, protect every cent from order leaks, track daily, monthly, and annual sales reports with 0% error margin, and leverage tailored engines for Dine-in (Postpaid) and Fast Casual / Counters (Prepaid).',
            tryDemoBtn: 'Try Live Interactive Demo ⚡',
            bookConsultationBtn: 'Book a Free Demo Consultation',
            badgeNoApp: 'Instant table ordering via phone camera — zero app downloads',
            badgeSockets: 'Certified daily, monthly & annual sales ledger (0% error margin)',
            badgeMultiTenant: '0% sales commission — keep 100% of your earnings',
        },
        solutions: {
            tag: '🎯 Tailored Operating Solutions',
            title: 'Whether a Dine-In Restaurant or Fast-Casual Café.. Built for You',
            subtitle: 'We provide the perfect workflow matching how you serve food, ensuring maximum kitchen efficiency and delighting your guests.',
            postpaidTitle: 'Dine-In & Full-Service Hospitality Engine',
            postpaidBadge: 'Postpaid Hospitality System',
            postpaidDesc: 'Engineered for family dining, lounges, and upscale cafés to boost average ticket value, accelerate table turns, and prevent any dish from leaving the kitchen without financial booking on the table tab.',
            postpaidPoints: [
                'Revenue protection & kitchen sync: every ordered dish is instantly locked to the table ledger before cooking begins.',
                'Boost Average Order Value (AOV) by 28%: irresistible food photography and smart upsell prompts encourage appetizers, desserts, and extra drinks.',
                'Frees waitstaff for authentic guest hospitality: servers focus on welcoming guests rather than frantically punching orders.',
                'Seamless departure reconciliation: guests request their itemized bill in one tap and settle via cash or card with instant automatic table clearance.'
            ],
            prepaidTitle: 'Fast Food & Pickup Counter Engine',
            prepaidBadge: 'Prepaid Counter System',
            prepaidDesc: 'Ultra-fast throughput engineering that guarantees 100% upfront cash collection before preparation begins, entirely eliminating counter queues.',
            prepaidPoints: [
                '100% financial security & zero food waste: guests pay at the cashier first; the instant payment is confirmed, tickets dispatch to kitchen windows (zero unpaid orders).',
                'Customer smartphone is the live tracker: no expensive buzzer hardware; guests view the assigned kitchen prep window on their own screens and track progress live.',
                'Smart multi-window kitchen claiming: when a chef at a window (e.g. Window 2) claims a ticket, all other windows immediately see it is claimed to prevent duplicate cooking.',
                'Double rush-hour throughput: handle hundreds of diners in minutes without cashier congestion or cold food delays.'
            ],
        },
        directoryPromo: {
            tag: '🚀 Free Marketing & Customer Acquisition',
            title: 'Tedallal Restaurant Directory • New Customers at 0% Commission!',
            subtitle: 'When you join Tedallal, your restaurant is featured in our centralized dining directory, connecting you with thousands of hungry diners nearby.',
            badge: '0% sales commission compared to 25% charged by food delivery apps',
            benefit1Title: 'Free exposure for your menu and specials',
            benefit1Desc: 'Diners discover your signature dishes, photos, and location directly to drive foot traffic.',
            benefit2Title: 'Exclusive promo codes and discounts',
            benefit2Desc: 'Launch custom discount codes and seasonal deals to attract new diners and turn them into loyal regulars.',
            benefit3Title: 'Keep 100% of your earnings',
            benefit3Desc: 'We take zero cut from your food sales. All your hard-earned margins remain entirely in your pocket.'
        },
        workflow: {
            tag: '⚡ Fully Automated Operations',
            title: 'How Orders Flow in Your Restaurant',
            subtitle: 'Choose your restaurant operating model to explore the exact order lifecycle from mobile scan to food pickup.',
            togglePostpaid: '🍽️ Dine-In & Hospitality (Postpaid)',
            togglePrepaid: '⚡ Fast Food & Counters (Prepaid)',
            postpaid: {
                title: 'Order Lifecycle in Dine-In & Hospitality Restaurants (Postpaid)',
                subtitle: 'A relaxed dining experience allowing multi-course reordering without waiting for servers, settled upon departure.',
                step1Title: 'Instant QR Scan & Order',
                step1Desc: 'Guests scan the table QR code with their phone camera to browse the digital menu and order without waiting for staff.',
                step2Title: 'Instant Kitchen & Cashier Routing',
                step2Desc: 'Ticket details route instantaneously to chef screens and printers while appending seamlessly to the table bill.',
                step3Title: 'Smart Kitchen KDS Organization',
                step3Desc: 'Dishes are prepared in sync with prep timers, allowing guests to order additional rounds anytime.',
                step4Title: 'Bill Request & Departure Payment',
                step4Desc: 'Guests request their bill in one tap and settle via cash or card with waitstaff or cashier, clearing the table automatically.',
            },
            prepaid: {
                title: 'Order Lifecycle in Fast Food & Pickup Counter Restaurants (Prepaid)',
                subtitle: 'Ultra-fast throughput, complete queue elimination, and smart window routing with live mobile customer tracking.',
                step1Title: 'Instant Mobile Order & Cashier Prompt',
                step1Desc: 'Guests scan the QR code and submit their order, immediately seeing: "Please head to the cashier and share your order number to settle your bill so prep can begin."',
                step2Title: 'Cashier Payment Confirmation & Kitchen Routing',
                step2Desc: 'The guest shares their order number; in a single tap, the cashier confirms payment, instantly firing tickets to kitchen window screens.',
                step3Title: 'Window Claim & Multi-Chef Sync',
                step3Desc: 'When a chef at a window (e.g. Window 2) claims the order, all other windows immediately see it is claimed to prevent double prep.',
                step4Title: 'Live Guest Mobile Tracking & Pickup Notification',
                step4Desc: 'The guest phone updates in real-time: "Your order was received by Window #2" with a live visual tracker until pickup.',
            },
            step1Title: 'Instant QR Scan & Order',
            step1Desc: 'Guests scan the table QR code with their mobile camera to launch the appetizing digital menu with zero app barrier.',
            step2Title: 'Instant Kitchen & Cashier Dispatch',
            step2Desc: 'Upon guest order confirmation, the ticket instantly displays on chef screens and thermal printers with zero manual waiter errors.',
            step3Title: 'Smart Kitchen Display (KDS)',
            step3Desc: 'Intuitive order stages (New, Cooking, Ready) with precise countdown timers ensuring every meal is delivered piping hot.',
            step4Title: 'Thermal Printing & POS Sync',
            step4Desc: 'Automatic kitchen tickets and tax invoices print directly on your existing hardware with live sales updates.',
        },
        demo: {
            tag: '🎮 Live Interactive Simulation',
            title: 'Experience Tedallal Just Like in Your Restaurant',
            subtitle: 'Switch across the 4 core platform modules, add items to your cart, and watch kitchen tickets, printing, and analytics update in real time.',
            tabCustomer: 'Smart Mobile Menu (Guest)',
            tabKds: 'Kitchen Display Screen (KDS)',
            tabQr: 'Table QR & Thermal Print',
            tabAnalytics: 'Sales Ledger & Reports (0% Error)',
            catGrills: 'Signature Grills',
            catAppetizers: 'Appetizers & Mezza',
            catDesserts: 'Artisan Desserts',
            catDrinks: 'Fresh Drinks & Juices',
            cartTitle: 'Current Table Cart',
            sendToKitchenBtn: 'Send to Kitchen ⚡',
            kdsHeaderTitle: 'Smart Kitchen Workflow System (Live KDS Kanban)',
            kdsHeaderSubtitle: 'Updates dynamically upon guest ordering without refreshing the browser',
            colNew: 'New Orders',
            colCooking: 'Preparing (Cooking)',
            colReady: 'Ready to Serve',
            btnStartCooking: 'Start Cooking 👨‍🍳',
            btnMarkReady: 'Mark Ready 🍽️',
            btnMarkServed: 'Served ✅',
            qrCardTitle: 'Elegant Acrylic Table Stand',
            qrCardSubtitle: 'Print-ready acrylic stand for dining tables',
            qrCardHint: 'Scan the QR code with your mobile camera to test the real guest ordering flow on Table 04',
            receiptHeader: 'Simplified Tax Invoice',
            receiptTaxNo: 'VAT ID: 310294857300003',
            receiptSubtotal: 'Subtotal:',
            receiptDiscount: 'Promo Code (TEDALLAL15):',
            receiptTotal: 'Net Total:',
            receiptFooter: 'Thank you for dining with us • Bon Appétit!',
            receiptHint: 'Full native compatibility with standard 80mm POS & thermal receipt printers directly through the browser without extra plugins.',
            analyticsTitle: 'Financial Sales Ledger & Predictive BI (0% Error Margin)',
            analyticsSubtitle: 'Certified daily, monthly, and annual revenue statements breaking down gross sales, discounts, and net income, plus peak hour insights.',
            filterWeekend: 'Weekend Peaks (Thu - Sat)',
            filterWeekday: 'Weekday Regular Hours',
            chartPeakTitle: 'Hourly Order Volume & Peak Hours Discovery',
            chartTopDishesTitle: 'Top Performing Dishes & Sales Share',
            ordersUnit: 'orders',
            aiInsightTitle: 'Smart AI Insight from Tedallal Engine:',
            aiInsightText: 'Data shows a 45% surge in "Mixed Appetizers" between 8:00 PM and 10:00 PM. We recommend launching a dinner combo pairing grills with appetizers to boost average ticket value by',
        },
        features: {
            tag: '🚀 Revenue Growth & Operational Excellence',
            title: 'Engineered Exclusively to Boost Restaurant Profits',
            subtitle: 'Crafted with high-reliability cloud standards to guarantee flawless service during your highest rush hours.',
            f1Title: '35% Faster Table Turnover',
            f1Desc: 'Eliminate order wait times, accommodate more diners during peak hours, and maximize daily revenue.',
            f2Title: 'Boost Average Order Value by 28%',
            f2Desc: 'Appetizing food imagery and automatic upsell prompts encourage diners to add extras and specialty drinks.',
            f3Title: 'Smart Kitchen Display Screens (KDS)',
            f3Desc: 'Eliminate lost paper tickets with streamlined digital kitchen stations and timers for hot, timely food delivery.',
            f4Title: 'Zero Expensive Hardware Upgrades',
            f4Desc: 'Runs seamlessly with any standard 80mm thermal receipt printer and your staff’s existing tablets or smartphones.',
            f5Title: 'Free Growth via Tedallal Directory',
            f5Desc: 'Promote your restaurant, menus, and discount codes to local diners at 0% sales commission.',
            f6Title: '0% Error Financial Sales Reports',
            f6Desc: 'CPA-grade daily, monthly, and yearly sales ledgers isolating gross revenue, discounts, and net realized sales with zero float drift.',
        },
        roi: {
            tag: '💰 Return on Investment Calculator',
            title: 'How Much Will You Save & Earn with Tedallal?',
            subtitle: 'Adjust the sliders to match your restaurant capacity and instantly calculate cost savings and revenue gains from faster table turnover.',
            tablesLabel: 'Total Dining Tables:',
            tablesUnit: 'tables',
            dailyOrdersLabel: 'Average Daily Orders per Table:',
            dailyOrdersUnit: 'orders',
            monthlyOrdersEst: 'Estimated Monthly Orders:',
            ordersCountUnit: 'orders',
            avgTimeSavedLabel: 'Average Labor Time Saved per Table:',
            avgTimeSavedVal: '10 mins',
            paperSavingsTitle: 'Annual Paper Menu Replacement Savings:',
            paperSavingsNote: 'Direct savings by eliminating worn-out printed menus and reprinted price lists',
            laborHoursTitle: 'Total Staff Labor Hours Saved Monthly:',
            laborHoursUnit: 'hours',
            laborHoursNote: 'Waitstaff focus on hospitality and upselling rather than manual order taking',
            revenueBoostTitle: 'Estimated Revenue Lift & Commission Savings:',
            revenueBoostPerMonth: '/ month',
            revenueBoostNote: 'Combines 35% faster table turnover with 25% savings on third-party delivery commissions',
        },
        comparison: {
            tag: '⚖️ The Decisive Shift',
            title: 'Traditional Dining vs Tedallal Powered Operations',
            subtitle: 'Discover the immense contrast in operating costs, turnaround speed, and guest delight between manual legacy workflows and intelligent cloud dining.',
            colTraditional: 'Legacy Manual Workflow ❌',
            colTedallal: 'Powered by Tedallal ⚡',
            r1Label: 'Guest Ordering Method',
            r1Trad: 'Waiting for a server to bring physical menus, then waiting again to place order (5-15 mins)',
            r1Ted: 'Instant QR camera scan; guests start ordering within 10 seconds with zero waiting',
            r2Label: 'Menu & Price Updates',
            r2Trad: 'Costly reprinting delays or tacky price-override stickers across paper menus',
            r2Ted: 'Real-time dashboard updates reflect globally across all dining tables in 1 second',
            r3Label: 'Kitchen Ticket Routing',
            r3Trad: 'Waiters walk back and forth to punch tickets; paper slips risk getting lost or soiled',
            r3Ted: 'Instant digital dispatch to kitchen display screens and thermal printers in 1 second',
            r4Label: 'Financial Control & Leakage',
            r4Trad: 'Lost paper receipts, register discrepancy, and impossible tracking of wasted or stolen orders',
            r4Ted: 'Instant digital ledger, audit-proof discount tracking, and certified sales reports with 0% error margin',
            r5Label: 'Order Accuracy & Notes',
            r5Trad: 'Frequent miscommunication on allergy alerts or special requests (no onions, extra sauce)',
            r5Ted: 'Guests specify exact item customizations and dietary notes with zero human error',
        },
        testimonials: {
            tag: '⭐ Industry Testimonials',
            title: 'Trusted by Leading Hospitality Pioneers',
            subtitle: 'Real stories from restaurant founders and operations directors whose numbers surged after adopting Tedallal.',
            t1Text: '"We slashed guest ordering wait times from 12 minutes to zero. Tedallal Kitchen Display screens brought seamless order to our chefs and completely eliminated messy ticket piles."',
            t1Author: 'Eng. Tarek Al-Saeed',
            t1Role: 'Owner, Damascene Dining Chain',
            t2Text: '"The single best operational investment we made this year. We eradicated seasonal menu reprint costs, and our average order value climbed by 18% thanks to appetizing item photography."',
            t2Author: 'Sarah Al-Abdullah',
            t2Role: 'Director of Hospitality Operations',
            t3Text: '"The speed and reliability of Tedallal are exceptional. It runs smoothly even during our busiest Friday dinner rushes. A must-have for every ambitious restaurateur."',
            t3Author: 'Chef Fahad Al-Tamimi',
            t3Role: 'Executive Chef & Co-Founder',
        },
        faqs: {
            tag: '💬 Frequently Asked Questions',
            title: 'Everything You Need to Know About Tedallal',
            subtitle: 'Clear, direct answers regarding daily restaurant operations and setup.',
            items: [
                {
                    q: 'Do guests need to install an app to order food?',
                    a: 'Never! Guests simply point their iPhone or Android camera at the table QR code, launching the interactive menu directly in their native browser within under two seconds.',
                },
                {
                    q: 'How do guest orders reach the kitchen staff and cashier instantly?',
                    a: 'The moment a guest taps "Confirm Order", details stream to kitchen display screens and POS printers in less than a second, so chefs see custom prep notes without delay.',
                },
                {
                    q: 'Does Tedallal support our existing thermal receipt printers?',
                    a: 'Yes, Tedallal natively supports browser-based silent 80mm thermal printing for all major hardware brands (Epson, Star, Xprinter, etc.), formatted with compliant fiscal tax headers and kitchen tickets.',
                },
                {
                    q: 'What happens if a QR code is damaged or a table is rearranged?',
                    a: 'Restaurant administrators can regenerate unique table QR codes with a single click inside the admin dashboard and reprint them immediately without interrupting historical sales data.',
                },
                {
                    q: 'Can we manage multiple restaurant branches under one account?',
                    a: 'Yes! Tedallal allows you to oversee all your branches from a central dashboard, customize individual menus and pricing per branch, and track live performance anytime.',
                },
            ],
        },
        modal: {
            badge: '⚡ Join Elite Smart Dining Brands',
            title: 'Request a Live Demo & Account Setup',
            subtitle: 'Submit your restaurant details and our hospitality solutions consultant will prepare your dedicated sandbox environment.',
            restaurantName: 'Restaurant or Café Name',
            restaurantNamePlaceholder: 'e.g. Aleppo Shawarma & Grill House',
            contactPerson: 'Owner or General Manager Name',
            contactPersonPlaceholder: 'e.g. Abdulrahman Al-Khalidi',
            phone: 'Mobile Number (with country code)',
            phonePlaceholder: '+966 50 123 4567',
            country: 'Country',
            tablesCount: 'Estimated Number of Tables',
            tablesOption1: 'Less than 15 tables',
            tablesOption2: '15 - 35 tables',
            tablesOption3: '35 - 70 tables',
            tablesOption4: 'Over 70 tables (Multi-branch)',
            submitBtn: 'Confirm & Send Request 🚀',
            successTitle: 'Demo Request Received!',
            successDesc: 'Thank you. A Tedallal hospitality consultant will reach out via WhatsApp shortly to schedule your personalized live demo.',
        },
        footer: {
            brandDesc: 'Advanced cloud platform engineered to increase restaurant revenue and automate orders from table QR scans to kitchen screens and thermal printing.',
            serverStatus: 'Cloud platform operating with 99.98% uptime around the clock',
            quickLinksTitle: 'Quick Links',
            portalAccessTitle: 'System Portals',
            adminPortal: 'Restaurant Admin Portal',
            kdsPortal: 'Kitchen Staff KDS Portal',
            superAdminPortal: 'Super Admin Portal',
            requestAccount: 'Request New Restaurant Account',
            rights: 'All rights reserved © Tedallal Platform',
        },
    },
};
