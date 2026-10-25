export type Locale = 'ar' | 'en';

export interface LandingTranslation {
    meta: {
        title: string;
        description: string;
    };
    nav: {
        brandSubtitle: string;
        howItWorks: string;
        interactiveDemo: string;
        features: string;
        roiCalculator: string;
        comparison: string;
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
    workflow: {
        tag: string;
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
            title: 'منصة تدلّل | الحل السحابي الذكي لإدارة المطاعم والطلب عبر الـ QR',
            description: 'منصة تدلّل (Tedallal) السحابية المتكاملة للمطاعم والكافيهات. نظام طلب ذكي عبر رموز الاستجابة السريعة QR بدون تطبيقات، شاشة مطبخ حية KDS عبر WebSockets، طباعة حرارية 80mm، وتحليلات أداء متقدمة.',
        },
        nav: {
            brandSubtitle: 'المنظومة السحابية للضيافة الذكية',
            howItWorks: 'كيف يعمل النظام؟',
            interactiveDemo: 'المحاكي التفاعلي',
            features: 'مزايا المنصة',
            roiCalculator: 'حاسبة العائد والوفر',
            comparison: 'التقليدي vs تدلّل',
            faqs: 'الأسئلة الشائعة',
            signIn: 'تسجيل الدخول',
            dashboard: 'لوحة التحكم',
            bookDemo: 'طلب عرض تجريبي',
            currencyTitle: 'الدولة والعملة المعروضة',
            autoDetect: 'كشف تلقائي ⚡',
        },
        hero: {
            statusBadge: 'الجيل الجديد من منصات طلبات المطاعم بنظام Multi-Tenant SaaS',
            version: 'v2.4 Live Reverb',
            titleLine1: 'حوّل تجربة ضيافتك إلى',
            titleLine2: 'سرعة رقمية فائقة',
            subtitle: 'منظومة سحابية متكاملة تُمكّن زبائنك من الطلب فوراً عبر مسح رمز QR من الطاولة دون تنزيل أي تطبيق، مع إرسال الطلبات آنياً لشاشات المطبخ KDS والطباعة الحرارية المباشرة.',
            tryDemoBtn: 'جرّب المحاكي التفاعلي الحي ⚡',
            bookConsultationBtn: 'احجز جلسة استشارية وتجربة مجانية',
            badgeNoApp: 'بدون تطبيقات أو تسجيل',
            badgeSockets: 'تحديثات فورية عبر WebSockets',
            badgeMultiTenant: 'معمارية سحابية متعددة الفروع',
        },
        workflow: {
            tag: '⚡ دورة عمل مؤتمتة بالكامل',
            title: 'كيف تتدفق تجربة الطلب من الطاولة إلى المطبخ؟',
            subtitle: 'هندسة تقنية متكاملة تُقضي على طوابير الانتظار، وتختصر زمن معالجة الطلبات إلى ثوانٍ معدودة.',
            step1Title: 'المسح والطلب الفوري',
            step1Desc: 'يمسح الزبون رمز الـ QR على الطاولة بكاميرا الجوال، ليفتح المنيو الذكي فوراً بسرعة البرق بدون أي تطبيق.',
            step2Title: 'المزامنة الحية عبر Reverb',
            step2Desc: 'بمجرد ضغط الزبون على تأكيد الطلب، يُبث الحدث عبر WebSockets المشفرة إلى طاقم الخدمة والمطبخ في أقل من 200 ميلي ثانية.',
            step3Title: 'شاشة المطبخ الذكية KDS',
            step3Desc: 'تنظيم كانبان تفاعلي للطلبات (جديد، قيد الطهي، جاهز للتقديم) مع تتبع دقيق للزمن وتنبيهات صوتية فورية.',
            step4Title: 'الطبع الحراري والإقفال',
            step4Desc: 'طباعة بونات المطبخ والفواتير الضريبية تلقائياً على طابعات 80mm مع تحديث لحظي للمخزون وتقارير الـ POS.',
        },
        demo: {
            tag: '🎮 تجربة تفاعلية حيّة حقيقية',
            title: 'جرّب المنظومة بنفسك وكأنك في مطعمك الآن',
            subtitle: 'تنقّل بين أركان النظام الأربعة، وأضف عناصر للسلة واشهد تحديث المطبخ والطباعة والتحليلات آنياً.',
            tabCustomer: 'منيو الزبون الذكي (Mobile)',
            tabKds: 'شاشة المطبخ الحية (KDS)',
            tabQr: 'بطاقة الـ QR والطباعة',
            tabAnalytics: 'ذكاء الأعمال والذروة',
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
            analyticsTitle: 'مؤشرات الأداء اللحظية والتحليلات التنبؤية (BI Insights)',
            analyticsSubtitle: 'بيانات حية تساعدك على معرفة أوقات الذروة، وإعادة ضبط جداول الموظفين، وتعظيم متوسط سلة المشتريات.',
            filterWeekend: 'عطلة نهاية الأسبوع (خميس - سبت)',
            filterWeekday: 'أيام الأسبوع العادية',
            chartPeakTitle: 'حجم الطلبات الساعي واكتشاف أوقات الذروة',
            chartTopDishesTitle: 'الأصناف الأكثر مبيعاً ونسبة مساهمتها',
            ordersUnit: 'طلب',
            aiInsightTitle: 'اقتراح ذكي من محرك Tedallal Insights:',
            aiInsightText: 'تظهر البيانات ارتفاع طلبات "المقبلات المشكلة" بين الساعة 8 و 10 مساءً بنسبة 45%. نقترح إنشاء عرض كومبو يجمع المشاوي مع المقبلات لرفع متوسط قيمة الفاتورة بمقدار',
        },
        features: {
            tag: '💎 قدرات تقنية فائقة التطور',
            title: 'منظومة صُممت لتحمّل أعلى أوقات الضغط والذروة',
            subtitle: 'بُنيت تدلّل بأحدث معايير الأداء والأمان السحابي، لتضمن استمرارية أعمالك بلا توقف ولا بطء.',
            f1Title: 'تزامن لحظي عبر WebSockets',
            f1Desc: 'تقنية Laravel Reverb المدمجة تضمن وصول الأوردرات لشاشات الطهاة وطاقم الصالة في أجزاء من الثانية دون إرهاق الخادم.',
            f2Title: 'صفر تنزيل وصفر احتكاك للزبون',
            f2Desc: 'يعمل المنيو مباشرة في متصفح الزبون بمجرد مسح رمز الاستجابة السريعة، مع واجهة فائقة السلاسة تشبه التطبيقات الأصلية.',
            f3Title: 'شاشات مطبخ تفاعلية KDS',
            f3Desc: 'استبدل الفواتير الورقية الفوضوية بشاشات كانبان مخصصة للمطبخ والبار، مع مؤقتات لونية توضح الطلبات المتأخرة فوراً.',
            f4Title: 'طباعة حرارية 80mm متكاملة',
            f4Desc: 'دعم مباشر للطباعة الصامتة والتلقائية للبونات والفواتير الضريبية المتوافقة مع معايير هيئة الزكاة والضريبة والجمارك.',
            f5Title: 'معمارية SaaS متعددة المستأجرين',
            f5Desc: 'تحكم مرن بالميزات والإضافات لكل مطعم بشكل مستقل، مع إدارة اشتراكات مركزية وحماية وضع الصيانة الحصري لمالك المنصة.',
            f6Title: 'تحليلات تنبؤية وقرارات مدعومة بالبيانات',
            f6Desc: 'تقارير يومية وشهرية دقيقة توضح أكثر الأصناف ربحية، متوسط وقت التحضير، وساعات الذروة لتوزيع الكوادر بكفاءة.',
        },
        roi: {
            tag: '💰 حاسبة العائد والوفر المالي',
            title: 'كم ستوفر وتربح عند تشغيل «تدلّل» في مطعمك؟',
            subtitle: 'حرّك المؤشرات أدناه وفق حجم مطعمك الحالي، واكتشف فوراً حجم الوفر المالي وزيادة الأرباح الناتجة عن تسريع الخدمة.',
            tablesLabel: 'عدد الطاولات في المطعم:',
            tablesUnit: 'طاولة',
            dailyOrdersLabel: 'متوسط الطلبات اليومية لكل طاولة:',
            dailyOrdersUnit: 'طلبات',
            monthlyOrdersEst: 'إجمالي الطلبات الشهرية المتوقعة:',
            ordersCountUnit: 'طلب',
            avgTimeSavedLabel: 'متوسط الوقت الموفر لكل طاولة:',
            avgTimeSavedVal: '10 دقائق',
            paperSavingsTitle: 'الوفر السنوي في طباعة المنيو الورقي التالف:',
            paperSavingsNote: 'وفر مباشر بإلغاء طباعة القوائم وتعديل الأسعار ورقياً',
            laborHoursTitle: 'الوقت الإجمالي الموفر شهرياً لطاقم العمل:',
            laborHoursUnit: 'ساعة',
            laborHoursNote: 'يتفرغ الطاقم للترحيب والضيافة بدلاً من تسجيل الأوردرات',
            revenueBoostTitle: 'زيادة المبيعات التقديرية من سرعة تدوير الطاولات:',
            revenueBoostPerMonth: '/ شهرياً',
            revenueBoostNote: 'بفضل استيعاب زبائن أكثر في أوقات الذروة',
        },
        comparison: {
            tag: '⚖️ المقارنة الحاسمة',
            title: 'المطعم التقليدي مقابل مطعم مدعوم بـ «تدلّل»',
            subtitle: 'تعرف على الفارق الجذري في التكاليف والسرعة ورضا الزبائن بين الطريقة اليدوية والحل الرقمي الذكي.',
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
            r3Ted: 'بث رقمي فوري لشاشات المطبخ عبر WebSockets بأقل من 200ms',
            r4Label: 'تكلفة العمالة والصالة',
            r4Trad: 'الحاجة لعدد مضاعف من طاقم الصالة فقط لتدوين وتوصيل الأوردرات',
            r4Ted: 'تقليص جهد تسجيل الطلبات بنسبة 70% وتوجيه الطاقم لجودة الخدمة والترحيب',
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
            t3Text: '«السرعة في تدلّل مذهلة. خوادم Reverb تعمل بثبات خارق حتى في ليالي الجمعة المزدحمة. أنصح أي مالك مطعم أو كافيه بالتحول إلى تدلّل فوراً.»',
            t3Author: 'الشيف فهد التميمي',
            t3Role: 'الشيف التنفيذي والشريك المؤسس',
        },
        faqs: {
            tag: '💬 الأسئلة الأكثر شيوعاً',
            title: 'كل ما تحتاج معرفته عن منصة تدلّل',
            subtitle: 'إجابات واضحة ومباشرة عن الاستفسارات التقنية والتشغيلية للمنصة.',
            items: [
                {
                    q: 'هل يحتاج زبائن المطعم إلى تنزيل أي تطبيق لطلب الطعام؟',
                    a: 'لا نهائياً! بمجرد توجيه كاميرا هاتف الزبون (iOS أو Android) إلى رمز الـ QR الموجود على الطاولة، يفتح المنيو التفاعلي مباشرة في المتصفح الافتراضي خلال أقل من ثانيتين.',
                },
                {
                    q: 'كيف تصل طلبات الزبائن إلى المطبخ والكاشير فوراً؟',
                    a: 'تعتمد تدلّل على محرك Laravel Reverb لبث أحداث الطلب عبر قنوات WebSockets مشفرة. وبمجرد ضغط الزبون على «تأكيد الطلب»، تظهر تفاصيل الأوردر في شاشة المطبخ KDS وتُطبع الفاتورة آلياً في أجزاء من الثانية.',
                },
                {
                    q: 'هل يدعم النظام الربط مع طابعات الفواتير الحرارية في الكاشير والمطبخ؟',
                    a: 'نعم، تدعم تدلّل الطباعة الحرارية المباشرة عبر المتصفح لقياس 80mm القياسي لكافة أنواع الطابعات (Epson, Star, Xprinter وغيرها)، مع تصميم فواتير ضريبية مبسطة وبونات تشغيل للمطبخ.',
                },
                {
                    q: 'ماذا يحدث إذا تضرر كود الـ QR على إحدى الطاولات؟',
                    a: 'بكل تأكيد، تتيح لوحة تحكم المطعم إعادة توليد رمز الـ QR والتوكن السري للطاولة بنقرة زر واحدة (Regenerate QR) بشكل فوري، مع إبقاء سجل الطلبات والمبيعات التاريخية سليماً دون أي تأثر.',
                },
                {
                    q: 'هل يمكن تشغيل النظام على عدة فروع أو مطاعم؟',
                    a: 'نعم، صُممت المنصة وفق معمارية تعدد المستأجرين (Multi-Tenant SaaS) مع لوحة تحكم مركزية للمنصة تتيح تفعيل وإدارة المطاعم، والتحكم بالباقات والميزات الإضافية (Modular Add-ons) بكل مرونة وسلاسة.',
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
            brandDesc: 'منظومة سحابية سعودية متطورة مصممة للمطاعم والكافيهات بنظام SaaS متعدد المستأجرين. أتمتة كاملة لدورة الطلب من الطاولة حتى شاشات المطبخ والطباعة الحرارية.',
            serverStatus: 'كافة الخوادم السحابية ومحرك Reverb يعملان بكفاءة 99.98%',
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
            title: 'Tedallal Platform | Next-Gen Cloud Restaurant Management & QR Ordering',
            description: 'Tedallal is an all-in-one cloud hospitality platform for restaurants and cafes. Smart QR table ordering without apps, real-time WebSocket KDS kitchen screens, 80mm thermal printing, and advanced BI analytics.',
        },
        nav: {
            brandSubtitle: 'Next-Gen Cloud Hospitality Platform',
            howItWorks: 'How It Works',
            interactiveDemo: 'Live Demo',
            features: 'Features',
            roiCalculator: 'ROI Calculator',
            comparison: 'Comparison',
            faqs: 'FAQ',
            signIn: 'Sign In',
            dashboard: 'Dashboard',
            bookDemo: 'Book a Demo',
            currencyTitle: 'Display Country & Currency',
            autoDetect: 'Auto-detected ⚡',
        },
        hero: {
            statusBadge: 'Next-Gen Multi-Tenant Restaurant SaaS Platform',
            version: 'v2.4 Live Reverb',
            titleLine1: 'Elevate Dining with',
            titleLine2: 'Ultra-Fast Digital Ordering',
            subtitle: 'An all-in-one cloud platform empowering guests to order instantly via table QR codes with zero app downloads, streaming tickets in real time to Kitchen Display Systems (KDS) and thermal printers.',
            tryDemoBtn: 'Try Live Interactive Demo ⚡',
            bookConsultationBtn: 'Book a Free Demo Consultation',
            badgeNoApp: 'Zero App Downloads Required',
            badgeSockets: 'Real-Time WebSocket Sync',
            badgeMultiTenant: 'Multi-Branch SaaS Architecture',
        },
        workflow: {
            tag: '⚡ Fully Automated Operations',
            title: 'How Orders Flow Seamlessly from Table to Kitchen',
            subtitle: 'A high-performance architecture eliminating waiting queues and reducing ticket processing to mere seconds.',
            step1Title: 'Instant QR Scan & Order',
            step1Desc: 'Guests scan the table QR code with their mobile camera to launch the lightning-fast digital menu with zero app barrier.',
            step2Title: 'Sub-200ms Reverb Sync',
            step2Desc: 'Upon guest order confirmation, encrypted WebSockets broadcast the ticket to kitchen and service staff in under 200ms.',
            step3Title: 'Smart Kitchen Display (KDS)',
            step3Desc: 'Intuitive Kanban order stages (New, Cooking, Ready) with precise countdown timers and audible sound alerts.',
            step4Title: 'Thermal Printing & POS Sync',
            step4Desc: 'Automatic 80mm kitchen tickets and tax invoices with instant inventory reduction and real-time sales reporting.',
        },
        demo: {
            tag: '🎮 Live Interactive Simulation',
            title: 'Experience Tedallal Just Like in Your Restaurant',
            subtitle: 'Switch across the 4 core platform modules, add items to your cart, and watch kitchen tickets, printing, and analytics update in real time.',
            tabCustomer: 'Smart Mobile Menu (Guest)',
            tabKds: 'Kitchen Display Screen (KDS)',
            tabQr: 'Table QR & Thermal Print',
            tabAnalytics: 'Business Intelligence & BI',
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
            analyticsTitle: 'Live Performance Metrics & Predictive Analytics (BI Insights)',
            analyticsSubtitle: 'Actionable real-time data uncovering peak dining hours, optimizing staff allocation, and driving average check growth.',
            filterWeekend: 'Weekend Peaks (Thu - Sat)',
            filterWeekday: 'Weekday Regular Hours',
            chartPeakTitle: 'Hourly Order Volume & Peak Hours Discovery',
            chartTopDishesTitle: 'Top Performing Dishes & Sales Share',
            ordersUnit: 'orders',
            aiInsightTitle: 'Smart AI Insight from Tedallal Engine:',
            aiInsightText: 'Data shows a 45% surge in "Mixed Appetizers" between 8:00 PM and 10:00 PM. We recommend launching a dinner combo pairing grills with appetizers to boost average ticket value by',
        },
        features: {
            tag: '💎 Enterprise-Grade Engineering',
            title: 'Engineered to Excel Under Maximum Rush Hours',
            subtitle: 'Crafted with cutting-edge cloud infrastructure and high-availability standards to guarantee zero downtime and lightning response times.',
            f1Title: 'Instant WebSockets Synchronization',
            f1Desc: 'Native Laravel Reverb engine streams guest orders to kitchen display systems and front-of-house staff within fractions of a second.',
            f2Title: 'Zero Friction & Zero App Installs',
            f2Desc: 'Guests browse, customize, and order instantly in mobile browsers with fluid 60fps micro-animations matching native mobile apps.',
            f3Title: 'Interactive Kitchen Display (KDS)',
            f3Desc: 'Replace disorganized paper tickets with clean digital Kanban boards featuring live color-coded timers and audio chimes.',
            f4Title: 'Seamless 80mm Thermal Receipt Printing',
            f4Desc: 'Native silent printing for kitchen prep tickets and compliant fiscal tax receipts without relying on third-party drivers.',
            f5Title: 'Multi-Tenant SaaS Architecture',
            f5Desc: 'Granular modular features per restaurant branch, central subscription controls, and dedicated maintenance mode safeguards.',
            f6Title: 'Predictive BI & Real-Time Reporting',
            f6Desc: 'Deep operational clarity uncovering highest-margin dishes, average prep durations, and optimal staffing schedules.',
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
            revenueBoostTitle: 'Estimated Monthly Revenue Lift from Faster Table Turns:',
            revenueBoostPerMonth: '/ month',
            revenueBoostNote: 'Accommodate more guests during rush hours with faster service cycle',
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
            r3Ted: 'Instant digital dispatch to kitchen display screens over WebSockets in sub-200ms',
            r4Label: 'Front-of-House Labor Costs',
            r4Trad: 'Requires inflated floor staffing dedicated solely to scribbling and punching orders',
            r4Ted: 'Cuts order-taking labor by 70%, redirecting staff toward guest warmth and upselling',
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
            t3Text: '"The speed of Tedallal is remarkable. Reverb WebSockets remain rock-solid even during peak Friday dinner rushes. I wholeheartedly recommend it to every ambitious restaurant owner."',
            t3Author: 'Chef Fahad Al-Tamimi',
            t3Role: 'Executive Chef & Co-Founder',
        },
        faqs: {
            tag: '💬 Frequently Asked Questions',
            title: 'Everything You Need to Know About Tedallal',
            subtitle: 'Clear, direct answers regarding technical architecture and daily restaurant operations.',
            items: [
                {
                    q: 'Do guests need to install an app to order food?',
                    a: 'Never! Guests simply point their iPhone or Android camera at the table QR code, launching the interactive menu directly in their native browser within under two seconds.',
                },
                {
                    q: 'How do guest orders stream to kitchen staff and cashier instantaneously?',
                    a: 'Tedallal utilizes Laravel Reverb to broadcast order events via encrypted WebSocket channels. The moment a guest taps "Confirm Order", tickets display on the KDS and print at POS stations in fractions of a second.',
                },
                {
                    q: 'Does Tedallal support POS 80mm thermal receipt printers?',
                    a: 'Yes, Tedallal natively supports browser-based silent 80mm thermal printing for all major hardware brands (Epson, Star, Xprinter, etc.), formatted with compliant fiscal tax headers and kitchen prep dockets.',
                },
                {
                    q: 'What happens if a QR code is damaged or a table is rearranged?',
                    a: 'Restaurant administrators can regenerate unique table QR codes and secret security tokens with a single click inside the admin dashboard without interrupting historical order logs.',
                },
                {
                    q: 'Can the platform scale across multiple restaurant branches and concepts?',
                    a: 'Absolutely. Tedallal is architected from the ground up as a multi-tenant SaaS platform, enabling central branch management, tailored feature add-ons, and centralized administrative controls.',
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
            brandDesc: 'Advanced cloud hospitality SaaS platform engineered for restaurants and cafés. Complete end-to-end automation from table QR scans to kitchen KDS screens and thermal printing.',
            serverStatus: 'Cloud infrastructure & Reverb engine operational at 99.98% uptime',
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
