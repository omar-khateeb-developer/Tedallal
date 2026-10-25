<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';

interface Props {
    canLogin?: boolean;
    canRegister?: boolean;
}

withDefaults(defineProps<Props>(), {
    canLogin: true,
    canRegister: true,
});

// Navigation state
const isMobileMenuOpen = ref(false);
const activeNav = ref('home');

// Interactive Demo State
const activeDemoTab = ref<'customer' | 'kds' | 'qr' | 'analytics'>('customer');

// Customer Simulator State
const activeCategory = ref<'grills' | 'appetizers' | 'desserts' | 'drinks'>('grills');
interface MenuItemSim {
    id: number;
    name: string;
    category: 'grills' | 'appetizers' | 'desserts' | 'drinks';
    price: number;
    description: string;
    image: string;
    badge?: string;
    notes?: string;
}

const menuItems = ref<MenuItemSim[]>([
    {
        id: 1,
        name: 'كباب حلبي فاخر بالكرز',
        category: 'grills',
        price: 58,
        description: 'لحم بلدي مفروم مع صوص الكرز الطبيعي والمكسرات المحمصة',
        image: '/storage/menu/aleppo-grills.jpg',
        badge: 'الأكثر طلباً 🔥',
    },
    {
        id: 2,
        name: 'أوصال لحم مشوي عالفحم',
        category: 'grills',
        price: 64,
        description: 'قطع لحم غنم متبلة بخلطة تدلّل الخاصة مع خضار مشوية',
        image: '/storage/menu/grills.jpg',
    },
    {
        id: 3,
        name: 'كبة حلبية مشوية بالجوز',
        category: 'appetizers',
        price: 38,
        description: 'كبة مقرمشة محشوة باللحمة البلدية ودبس الرمان والجوز',
        image: '/storage/menu/aleppo-kibbeh.jpg',
        badge: 'تراثي مميز',
    },
    {
        id: 4,
        name: 'مقبلات مشكلة شهباء',
        category: 'appetizers',
        price: 32,
        description: 'متبل باذنجان، حمص بيروتي، يلنجي، ومحمرة بالجوز',
        image: '/storage/menu/aleppo-appetizers.jpg',
    },
    {
        id: 5,
        name: 'كنافة نابلسية بالجبن الفاخر',
        category: 'desserts',
        price: 28,
        description: 'كنافة ساخنة بجبنة عكاوية أصيلة وفستق حلبي مع القطر الخفيف',
        image: '/storage/menu/aleppo-desserts.jpg',
        badge: 'ساخن ولذيذ',
    },
    {
        id: 6,
        name: 'عصير رمان طبيعي مثلج',
        category: 'drinks',
        price: 18,
        description: 'عصير رمان طازج 100% مع حبات الرمان الطبيعية',
        image: '/storage/menu/drinks.jpg',
    },
]);

// Cart state in simulator
const cartQuantities = ref<Record<number, number>>({
    1: 2,
    3: 1,
});

const filteredMenuItems = computed(() => {
    return menuItems.value.filter(item => item.category === activeCategory.value);
});

const totalCartCount = computed(() => {
    return Object.values(cartQuantities.value).reduce((sum, q) => sum + q, 0);
});

const totalCartPrice = computed(() => {
    let total = 0;
    for (const [idStr, qty] of Object.entries(cartQuantities.value)) {
        const item = menuItems.value.find(i => i.id === Number(idStr));
        if (item) total += item.price * qty;
    }
    return total;
});

function addItem(id: number) {
    cartQuantities.value[id] = (cartQuantities.value[id] || 0) + 1;
}

function removeItem(id: number) {
    if (cartQuantities.value[id] && cartQuantities.value[id] > 0) {
        cartQuantities.value[id]--;
        if (cartQuantities.value[id] === 0) {
            delete cartQuantities.value[id];
        }
    }
}

// KDS Simulator State
interface KdsOrderSim {
    id: string;
    table: string;
    time: string;
    items: { name: string; qty: number; note?: string }[];
    status: 'pending' | 'cooking' | 'ready' | 'served';
    total: number;
    urgent?: boolean;
}

const kdsOrders = ref<KdsOrderSim[]>([
    {
        id: '#1042',
        table: 'طاولة 04',
        time: 'منذ 3 دقائق',
        items: [
            { name: 'كباب حلبي فاخر', qty: 2, note: 'بدون بصل - صوص إضافي' },
            { name: 'حمص بيروتي', qty: 1 },
        ],
        status: 'pending',
        total: 148,
        urgent: true,
    },
    {
        id: '#1041',
        table: 'طاولة 12',
        time: 'منذ 8 دقائق',
        items: [
            { name: 'أوصال لحم مشوي', qty: 1, note: 'مطهو جيداً (Well Done)' },
            { name: 'كبة مشوية بالجوز', qty: 2 },
            { name: 'عصير رمان', qty: 2 },
        ],
        status: 'cooking',
        total: 176,
    },
    {
        id: '#1039',
        table: 'طاولة 07',
        time: 'منذ 14 دقيقة',
        items: [
            { name: 'مقبلات مشكلة', qty: 1 },
            { name: 'كنافة نابلسية', qty: 2 },
        ],
        status: 'ready',
        total: 88,
    },
    {
        id: '#1038',
        table: 'طاولة 02',
        time: 'منذ 22 دقيقة',
        items: [
            { name: 'كباب حلبي بالكرز', qty: 1 },
            { name: 'عصير برتقال طازج', qty: 1 },
        ],
        status: 'served',
        total: 76,
    },
]);

function advanceKdsOrder(orderId: string) {
    const order = kdsOrders.value.find(o => o.id === orderId);
    if (!order) return;
    if (order.status === 'pending') order.status = 'cooking';
    else if (order.status === 'cooking') order.status = 'ready';
    else if (order.status === 'ready') order.status = 'served';
    else if (order.status === 'served') order.status = 'pending';
}

// Live Simulated Order Notifications (Social Proof ticker)
const liveToasts = [
    { table: 'طاولة 05', text: 'أكدت طلب (2x كباب حلبي، مقبلات مشكلة) بقيمة 148 ر.س', time: 'الآن' },
    { table: 'المطبخ والشيف', text: 'تم تجهيز طلب طاولة 12 في زمن قياسي: 7 دقائق فقط! ⚡', time: 'قبل دقيقة' },
    { table: 'طاولة 08', text: 'فعلت كود خصم TEDALLAL15 وحصلت على خصم فوري 15%', time: 'قبل دقيقتين' },
    { table: 'طاولة 03', text: 'أضافت 3 أصناف جديدة إلى سلة الطاولة بدون استدعاء النادل', time: 'قبل 4 دقائق' },
];
const currentToastIndex = ref(0);
const showToast = ref(true);
let toastInterval: ReturnType<typeof setInterval> | null = null;

// ROI Calculator State
const tableCount = ref(30);
const dailyOrdersPerTable = ref(6);

const monthlyOrders = computed(() => tableCount.value * dailyOrdersPerTable.value * 30);
const yearlyPaperSavings = computed(() => {
    // Average restaurant prints/replaces menus 3 times a year + QR flyers: approx 150 SAR per table/year
    return tableCount.value * 160;
});
const timeSavedHoursMonthly = computed(() => {
    // 10 minutes saved per table order in taking order & manual cashier entry
    return Math.round((monthlyOrders.value * 10) / 60);
});
const estimatedRevenueBoost = computed(() => {
    // Faster turnover increases capacity by approx 18-24%
    return Math.round(monthlyOrders.value * 0.22 * 75);
});

// Demo Modal State
const isDemoModalOpen = ref(false);
const demoForm = ref({
    restaurantName: '',
    contactPerson: '',
    phone: '',
    city: 'الرياض',
    tablesCount: '20-40',
});
const isSubmitted = ref(false);

function submitDemoRequest() {
    isSubmitted.value = true;
    setTimeout(() => {
        isDemoModalOpen.value = false;
        isSubmitted.value = false;
        demoForm.value = {
            restaurantName: '',
            contactPerson: '',
            phone: '',
            city: 'الرياض',
            tablesCount: '20-40',
        };
    }, 2500);
}

// FAQ Accordion State
const openFaqIndex = ref<number | null>(0);
function toggleFaq(index: number) {
    openFaqIndex.value = openFaqIndex.value === index ? null : index;
}

const faqs = [
    {
        q: 'هل يحتاج الزبون لتحميل أي تطبيق أو إنشاء حساب للطلب؟',
        a: 'أبداً! هذه إحدى أهم نقاط قوة منصة «تدلّل». يقوم الزبون فقط بفتح كاميرا هاتفه ومسح رمز الـ QR الملصق على الطاولة، لتفتح له القائمة التفاعلية مباشرة كصفحة ويب سريعة جداً مع حفظ سلة مشترياته وجلسته بأمان تام.',
    },
    {
        q: 'كيف تصل الطلبات للمطبخ وهل تتأخر؟',
        a: 'تعتمد المنصة خادم ويب سوكت محلي فائق السرعة عبر محرك Laravel Reverb. بمجرد ضغط الزبون على «تأكيد الطلب»، يصل الطلب لشاشة المطبخ (KDS) في أجزاء من الثانية مع نغمة تنبيهية صوتية وإشعار سطح المكتب دون الحاجة لإعادة تحميل الصفحة نهائياً.',
    },
    {
        q: 'هل تدعم المنصة طباعة الفواتير على الطابعات الحرارية بالمطعم؟',
        a: 'نعم، تدعم المنصة الطباعة المباشرة والمعيارية للفواتير وتذاكر المطبخ بمقاس 80 ملم (80mm Thermal Receipts) المعتمد عالمياً، متضمنة كافة تفاصيل الأصناف، وملاحظات الزبون الخاصة، وإجمالي الحساب، والباركود.',
    },
    {
        q: 'ماذا يحدث إذا قام المطعم بتعديل أسعار الأصناف أثناء وجود زبائن يطلبون؟',
        a: 'تحتوي المنصة على نظام حماية وضمان الأسعار اللحظي (Price Snapshot Guarantee)؛ حيث يتم تجميد أسماء وأسعار الأصناف في لحظة إرسال الزبون للطلب في قاعدة البيانات بشكل غير قابل للتعديل التراجعي، مما يحمي المطعم والزبون من أي تضارب مالي.',
    },
    {
        q: 'هل يمكنني تجديد رمز الـ QR للطاولة إذا تم تصويره خارج المطعم؟',
        a: 'بكل تأكيد، تتيح لوحة تحكم المطعم إعادة توليد رمز الـ QR والتوكن السري للطاولة بنقرة زر واحدة (Regenerate QR) بشكل فوري، مع إبقاء سجل الطلبات والمبيعات التاريخية سليماً دون أي تأثر.',
    },
    {
        q: 'هل يمكن تشغيل النظام على عدة فروع أو مطاعم؟',
        a: 'نعم، صُممت المنصة وفق معمارية تعدد المستأجرين (Multi-Tenant SaaS) مع لوحة تحكم مركزية للمنصة تتيح تفعيل وإدارة المطاعم، والتحكم بالباقات والميزات الإضافية (Modular Add-ons) بكل مرونة وسلاسة.',
    },
];

onMounted(() => {
    toastInterval = setInterval(() => {
        showToast.value = false;
        setTimeout(() => {
            currentToastIndex.value = (currentToastIndex.value + 1) % liveToasts.length;
            showToast.value = true;
        }, 400);
    }, 7000);
});

onUnmounted(() => {
    if (toastInterval) clearInterval(toastInterval);
});
</script>

<template>
    <Head>
        <title>منصة تدلّل | الحل السحابي الذكي لإدارة المطاعم والطلب عبر الـ QR</title>
        <meta
            name="description"
            content="منصة تدلّل (Tedallal) السحابية المتكاملة للمطاعم والكافيهات. نظام طلب ذكي عبر رموز الاستجابة السريعة QR بدون تطبيقات، شاشة مطبخ حية KDS عبر WebSockets، طباعة حرارية 80mm، وتحليلات أداء متقدمة."
        />
    </Head>

    <div dir="rtl" class="min-h-screen bg-[#070b14] text-slate-100 selection:bg-amber-500 selection:text-slate-950 font-sans antialiased overflow-x-hidden relative">

        <!-- Ambient Glow Effects & Mesh Gradients -->
        <div class="fixed inset-0 pointer-events-none z-0 overflow-hidden">
            <div class="absolute -top-40 right-1/4 w-[600px] h-[600px] bg-amber-500/10 rounded-full blur-[140px]"></div>
            <div class="absolute top-[35%] -left-32 w-[500px] h-[500px] bg-orange-600/10 rounded-full blur-[130px]"></div>
            <div class="absolute bottom-1/4 right-10 w-[550px] h-[550px] bg-emerald-600/10 rounded-full blur-[150px]"></div>
            <div class="absolute inset-0 bg-[radial-gradient(#1e293b_1px,transparent_1px)] [background-size:28px_28px] opacity-30"></div>
        </div>

        <!-- ========================================================================= -->
        <!-- TOP NAVBAR -->
        <!-- ========================================================================= -->
        <header class="sticky top-0 z-50 backdrop-blur-xl bg-[#070b14]/80 border-b border-white/5 transition-all">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-20">

                    <!-- Brand Logo -->
                    <div class="flex items-center gap-3">
                        <Link href="/" class="flex items-center gap-3 group">
                            <div class="relative w-11 h-11 rounded-2xl bg-gradient-to-tr from-amber-500 to-orange-500 p-[2px] shadow-lg shadow-amber-500/20 group-hover:shadow-amber-500/40 transition-all duration-300">
                                <div class="w-full h-full bg-[#0b101d] rounded-2xl flex items-center justify-center overflow-hidden">
                                    <img
                                        src="/images/tedallal-brand-logo.jpg"
                                        alt="شعار منصة تدلّل"
                                        class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300"
                                        onerror="this.src='/images/logo.jpg'"
                                    />
                                </div>
                            </div>
                            <div class="flex flex-col">
                                <div class="flex items-center gap-2">
                                    <span class="text-2xl font-bold tracking-tight bg-gradient-to-r from-amber-400 via-amber-200 to-amber-500 bg-clip-text text-transparent">
                                        تدلّل
                                    </span>
                                    <span class="text-[10px] font-semibold uppercase tracking-wider px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                        Tedallal
                                    </span>
                                </div>
                                <span class="text-[11px] text-slate-400 font-medium">المنظومة السحابية للضيافة الذكية</span>
                            </div>
                        </Link>
                    </div>

                    <!-- Desktop Nav Links -->
                    <nav class="hidden md:flex items-center gap-8 text-sm font-medium text-slate-300">
                        <a href="#how-it-works" class="hover:text-amber-400 transition-colors">كيف يعمل النظام؟</a>
                        <a href="#interactive-demo" class="hover:text-amber-400 transition-colors flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-amber-400 animate-ping"></span>
                            المحاكي التفاعلي
                        </a>
                        <a href="#features" class="hover:text-amber-400 transition-colors">مزايا المنصة</a>
                        <a href="#roi-calculator" class="hover:text-amber-400 transition-colors">حاسبة العائد والوفر</a>
                        <a href="#comparison" class="hover:text-amber-400 transition-colors">التقليدي vs تدلّل</a>
                        <a href="#faqs" class="hover:text-amber-400 transition-colors">الأسئلة الشائعة</a>
                    </nav>

                    <!-- Auth & CTA Actions -->
                    <div class="hidden sm:flex items-center gap-4">
                        <template v-if="$page.props.auth?.user">
                            <Link
                                :href="route('dashboard')"
                                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 text-slate-950 font-bold text-sm shadow-lg shadow-amber-500/25 hover:shadow-amber-500/40 hover:scale-[1.02] active:scale-[0.98] transition-all"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                                </svg>
                                <span>لوحة التحكم</span>
                            </Link>
                        </template>
                        <template v-else>
                            <Link
                                :href="route('login')"
                                class="text-sm font-semibold text-slate-300 hover:text-white px-4 py-2 rounded-xl hover:bg-white/5 transition-all"
                            >
                                تسجيل الدخول
                            </Link>
                            <button
                                @click="isDemoModalOpen = true"
                                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 text-slate-950 font-bold text-sm shadow-lg shadow-amber-500/20 hover:shadow-amber-500/35 hover:scale-[1.02] active:scale-[0.98] transition-all"
                            >
                                <span>طلب عرض تجريبي</span>
                                <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                                </svg>
                            </button>
                        </template>
                    </div>

                    <!-- Mobile Menu Hamburger -->
                    <div class="flex md:hidden items-center gap-3">
                        <button
                            @click="isDemoModalOpen = true"
                            class="px-3.5 py-1.5 rounded-lg bg-amber-500 text-slate-950 font-bold text-xs"
                        >
                            طلب عرض
                        </button>
                        <button
                            @click="isMobileMenuOpen = !isMobileMenuOpen"
                            class="p-2 rounded-xl text-slate-400 hover:text-white hover:bg-white/5 transition-all"
                            aria-label="القائمة"
                        >
                            <svg v-if="!isMobileMenuOpen" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"/>
                            </svg>
                            <svg v-else class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                </div>
            </div>

            <!-- Mobile Navigation Drawer -->
            <div v-if="isMobileMenuOpen" class="md:hidden border-t border-white/5 bg-[#090e1c]/95 backdrop-blur-2xl px-6 py-6 space-y-4">
                <a @click="isMobileMenuOpen = false" href="#how-it-works" class="block text-slate-200 font-medium py-2">كيف يعمل النظام؟</a>
                <a @click="isMobileMenuOpen = false" href="#interactive-demo" class="block text-amber-400 font-medium py-2">المحاكي التفاعلي</a>
                <a @click="isMobileMenuOpen = false" href="#features" class="block text-slate-200 font-medium py-2">مزايا المنصة</a>
                <a @click="isMobileMenuOpen = false" href="#roi-calculator" class="block text-slate-200 font-medium py-2">حاسبة العائد والوفر</a>
                <a @click="isMobileMenuOpen = false" href="#comparison" class="block text-slate-200 font-medium py-2">التقليدي vs تدلّل</a>
                <a @click="isMobileMenuOpen = false" href="#faqs" class="block text-slate-200 font-medium py-2">الأسئلة الشائعة</a>
                <div class="pt-4 border-t border-white/10 flex flex-col gap-3">
                    <template v-if="$page.props.auth?.user">
                        <Link :href="route('dashboard')" class="text-center w-full py-3 rounded-xl bg-amber-500 text-slate-950 font-bold text-sm">
                            دخول لوحة التحكم
                        </Link>
                    </template>
                    <template v-else>
                        <Link :href="route('login')" class="text-center w-full py-2.5 rounded-xl border border-white/10 text-white font-medium text-sm">
                            تسجيل الدخول
                        </Link>
                        <button @click="isDemoModalOpen = true; isMobileMenuOpen = false" class="w-full py-3 rounded-xl bg-amber-500 text-slate-950 font-bold text-sm">
                            طلب انضمام وعرض تجريبي
                        </button>
                    </template>
                </div>
            </div>
        </header>

        <!-- ========================================================================= -->
        <!-- HERO SECTION -->
        <!-- ========================================================================= -->
        <section class="relative pt-12 pb-24 lg:pt-20 lg:pb-32 overflow-hidden z-10">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center max-w-4xl mx-auto space-y-8">

                    <!-- Live Status Pill -->
                    <div class="inline-flex items-center gap-2.5 px-4 py-2 rounded-full bg-slate-900/80 border border-amber-500/30 text-amber-300 text-xs sm:text-sm font-semibold shadow-lg shadow-amber-500/5 animate-fade-in backdrop-blur-md">
                        <span class="relative flex h-2.5 w-2.5">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                        </span>
                        <span>الجيل الجديد من منصات طلبات المطاعم بنظام Multi-Tenant SaaS</span>
                        <span class="text-slate-500">|</span>
                        <span class="text-amber-400/90 font-mono">v2.4 Live Reverb</span>
                    </div>

                    <!-- Main Dynamic Headline -->
                    <h1 class="text-4xl sm:text-6xl lg:text-7xl font-extrabold tracking-tight leading-[1.18] sm:leading-[1.18]">
                        حوّل تجربة ضيافتك إلى
                        <span class="bg-gradient-to-r from-amber-400 via-orange-400 to-amber-300 bg-clip-text text-transparent block mt-2">
                            سرعة رقمية فائقة
                        </span>
                        من مسحة الـ QR حتى المطبخ والحراري!
                    </h1>

                    <!-- Compelling Sub-Headline -->
                    <p class="text-lg sm:text-xl text-slate-300 leading-relaxed max-w-3xl mx-auto font-normal">
                        منصة <strong class="text-white font-semibold">«تدلّل»</strong> تمكّن زبائنك من تصفح المنيو، تخصيص الوجبات، وتأكيد الطلب من طاولاتهم بلمسة واحدة
                        <span class="text-amber-400 font-medium">دون الحاجة لتحميل أي تطبيقات</span>.
                        تنتقل الطلبات لحظياً عبر الويب سوكت لشاشة المطبخ (KDS) مع تنبيهات صوتية وطباعة حرارية معيارية 80mm.
                    </p>

                    <!-- Hero Call-To-Actions -->
                    <div class="flex flex-col sm:flex-row items-center justify-center gap-4 pt-4">
                        <a
                            href="#interactive-demo"
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-3 px-8 py-4 rounded-2xl bg-gradient-to-r from-amber-500 via-amber-400 to-orange-500 text-slate-950 font-extrabold text-base shadow-xl shadow-amber-500/25 hover:shadow-amber-500/40 hover:scale-[1.02] active:scale-[0.98] transition-all group"
                        >
                            <span>جرّب المحاكي التفاعلي الحي</span>
                            <span class="p-1.5 rounded-xl bg-slate-950/15 group-hover:translate-x-[-4px] transition-transform">
                                <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                                </svg>
                            </span>
                        </a>

                        <button
                            @click="isDemoModalOpen = true"
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-8 py-4 rounded-2xl bg-slate-900/90 hover:bg-slate-800 text-white font-bold text-base border border-slate-700/80 hover:border-amber-500/40 backdrop-blur-xl shadow-lg transition-all"
                        >
                            <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <span>احجز جلسة استشارة وعرض مجاني</span>
                        </button>
                    </div>

                    <!-- Metrics Badges Bar -->
                    <div class="pt-10 grid grid-cols-2 md:grid-cols-4 gap-4 max-w-4xl mx-auto border-t border-white/10">
                        <div class="p-4 rounded-2xl bg-white/[0.02] border border-white/5 backdrop-blur-sm">
                            <div class="text-2xl sm:text-3xl font-extrabold text-amber-400">0 تطبيق</div>
                            <div class="text-xs text-slate-400 mt-1">يعمل فوراً عبر متصفح الجوال</div>
                        </div>
                        <div class="p-4 rounded-2xl bg-white/[0.02] border border-white/5 backdrop-blur-sm">
                            <div class="text-2xl sm:text-3xl font-extrabold text-emerald-400">زمن &lt; 0.2 ثانية</div>
                            <div class="text-xs text-slate-400 mt-1">بث الطلب الفوري عبر WebSockets</div>
                        </div>
                        <div class="p-4 rounded-2xl bg-white/[0.02] border border-white/5 backdrop-blur-sm">
                            <div class="text-2xl sm:text-3xl font-extrabold text-orange-400">+35% سرعة</div>
                            <div class="text-xs text-slate-400 mt-1">معدل تدوير الطاولات بالصالة</div>
                        </div>
                        <div class="p-4 rounded-2xl bg-white/[0.02] border border-white/5 backdrop-blur-sm">
                            <div class="text-2xl sm:text-3xl font-extrabold text-amber-300">100% مضمون</div>
                            <div class="text-xs text-slate-400 mt-1">تجميد الأسعار لحظة الشراء</div>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- TECHNICAL ARCHITECTURE & WORKFLOW FLOW -->
        <!-- ========================================================================= -->
        <section id="how-it-works" class="py-20 relative z-10 border-t border-white/5 bg-[#090d18]/60">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                <div class="text-center max-w-3xl mx-auto mb-16 space-y-4">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-500/10 border border-amber-500/20 text-amber-400 text-xs font-semibold">
                        <span>هندسة معمارية متطورة</span>
                    </div>
                    <h2 class="text-3xl sm:text-4xl font-extrabold text-white">
                        كيف تعمل دورة الطلب الذكية في «تدلّل»؟
                    </h2>
                    <p class="text-slate-400 text-base sm:text-lg">
                        منظومة مؤتمتة تلغي الأخطاء البشرية وتختصر مسافة التواصل بين زبون الطاولة وطاقم المطبخ إلى الصفر.
                    </p>
                </div>

                <!-- 4 Step Flow Visual Grid -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 relative">

                    <!-- Step 1 -->
                    <div class="relative p-6 rounded-3xl bg-slate-900/70 border border-white/10 hover:border-amber-500/40 transition-all duration-300 group">
                        <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-amber-500/20 to-orange-500/20 border border-amber-500/30 flex items-center justify-center text-amber-400 mb-6 group-hover:scale-110 transition-transform">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                            </svg>
                        </div>
                        <div class="text-amber-400 font-mono text-xs font-bold mb-1">الخطوة 01</div>
                        <h3 class="text-lg font-bold text-white mb-2">مسح الـ QR الآمن</h3>
                        <p class="text-sm text-slate-400 leading-relaxed">
                            يجلس الزبون على طاولته ويمسح الرمز، فيتم إنشاء جلسة طاولة مشفرة (Table Session) تربط الهاتف بالطاولة دون أي تسجيل حساب.
                        </p>
                    </div>

                    <!-- Step 2 -->
                    <div class="relative p-6 rounded-3xl bg-slate-900/70 border border-white/10 hover:border-amber-500/40 transition-all duration-300 group">
                        <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-amber-500/20 to-orange-500/20 border border-amber-500/30 flex items-center justify-center text-amber-400 mb-6 group-hover:scale-110 transition-transform">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                            </svg>
                        </div>
                        <div class="text-amber-400 font-mono text-xs font-bold mb-1">الخطوة 02</div>
                        <h3 class="text-lg font-bold text-white mb-2">السلة العائمة وتخصيص الوجبات</h3>
                        <p class="text-sm text-slate-400 leading-relaxed">
                            تصفح الأقسام، زيادة الكميات بعداد فوري بالبطاقة، كتابة ملاحظات دقيقة للشيف، وشريط سلة زجاجي عائم لا يحجب المنيو أبداً.
                        </p>
                    </div>

                    <!-- Step 3 -->
                    <div class="relative p-6 rounded-3xl bg-slate-900/70 border border-white/10 hover:border-amber-500/40 transition-all duration-300 group">
                        <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-emerald-500/20 to-teal-500/20 border border-emerald-500/30 flex items-center justify-center text-emerald-400 mb-6 group-hover:scale-110 transition-transform">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                        </div>
                        <div class="text-emerald-400 font-mono text-xs font-bold mb-1">الخطوة 03</div>
                        <h3 class="text-lg font-bold text-white mb-2">بث Reverb وتجميد الأسعار</h3>
                        <p class="text-sm text-slate-400 leading-relaxed">
                            حفظ فوري للقطة الأسعار وتأكيد الطلب، وبث الحدث لحظياً عبر خادم Laravel Reverb WebSockets إلى شاشات المطبخ وطاقم الخدمة.
                        </p>
                    </div>

                    <!-- Step 4 -->
                    <div class="relative p-6 rounded-3xl bg-slate-900/70 border border-white/10 hover:border-amber-500/40 transition-all duration-300 group">
                        <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-orange-500/20 to-amber-500/20 border border-orange-500/30 flex items-center justify-center text-orange-400 mb-6 group-hover:scale-110 transition-transform">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                            </svg>
                        </div>
                        <div class="text-orange-400 font-mono text-xs font-bold mb-1">الخطوة 04</div>
                        <h3 class="text-lg font-bold text-white mb-2">شاشة KDS والطباعة الحرارية</h3>
                        <p class="text-sm text-slate-400 leading-relaxed">
                            نغمة تنبيه صوتية بالمطبخ، بطاقة كانبان بمؤقت زمني ملون، وتتبع مباشر للزبون، مع طباعة تذكرة وإيصال 80 ملم بضغطة زر.
                        </p>
                    </div>

                </div>

                <!-- Technical Connection Simulation Bar -->
                <div class="mt-12 p-4 rounded-2xl bg-slate-950/80 border border-amber-500/20 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs">
                    <div class="flex items-center gap-3">
                        <span class="w-3 h-3 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span class="text-slate-300 font-medium">سيرفر WebSockets الحصري: <strong class="text-emerald-400">Laravel Reverb Engine Online</strong></span>
                    </div>
                    <div class="flex items-center gap-6 text-slate-400 font-mono">
                        <span>Latency: ~18ms</span>
                        <span>Zero Polling Overhead</span>
                        <span>Multi-Tenant DB Sharding</span>
                    </div>
                </div>

            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- INTERACTIVE LIVE DEMO PLAYGROUND (THE SHOWCASE) -->
        <!-- ========================================================================= -->
        <section id="interactive-demo" class="py-24 relative z-10">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                <div class="text-center max-w-3xl mx-auto mb-12 space-y-4">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-gradient-to-r from-amber-500/20 to-orange-500/20 border border-amber-500/30 text-amber-300 text-xs font-bold">
                        <span>⚡ محاكي الواجهات الحي والمباشر</span>
                    </div>
                    <h2 class="text-3xl sm:text-5xl font-extrabold text-white tracking-tight">
                        اختبر واجهات المشروع وكأنك داخل المطعم الآن!
                    </h2>
                    <p class="text-slate-300 text-base sm:text-lg">
                        انقر بين واجهات النظام الأربعة وجرّب التفاعل الحي بنفسك: اطلب من هاتف الزبون، تابع شاشة المطبخ KDS، واستعرض بطاقات الـ QR والتحليلات.
                    </p>
                </div>

                <!-- Tabs Switcher -->
                <div class="flex items-center justify-center mb-10">
                    <div class="inline-flex p-1.5 rounded-2xl bg-slate-900/90 border border-white/10 backdrop-blur-xl shadow-2xl overflow-x-auto max-w-full">
                        <button
                            @click="activeDemoTab = 'customer'"
                            :class="activeDemoTab === 'customer' ? 'bg-gradient-to-r from-amber-500 to-orange-500 text-slate-950 font-bold shadow-lg shadow-amber-500/20' : 'text-slate-300 hover:text-white'"
                            class="px-5 py-2.5 rounded-xl text-sm font-semibold transition-all flex items-center gap-2 whitespace-nowrap"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                            </svg>
                            <span>هاتف الزبون والمنيو</span>
                        </button>

                        <button
                            @click="activeDemoTab = 'kds'"
                            :class="activeDemoTab === 'kds' ? 'bg-gradient-to-r from-amber-500 to-orange-500 text-slate-950 font-bold shadow-lg shadow-amber-500/20' : 'text-slate-300 hover:text-white'"
                            class="px-5 py-2.5 rounded-xl text-sm font-semibold transition-all flex items-center gap-2 whitespace-nowrap"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/>
                            </svg>
                            <span>شاشة المطبخ الفورية (KDS)</span>
                        </button>

                        <button
                            @click="activeDemoTab = 'qr'"
                            :class="activeDemoTab === 'qr' ? 'bg-gradient-to-r from-amber-500 to-orange-500 text-slate-950 font-bold shadow-lg shadow-amber-500/20' : 'text-slate-300 hover:text-white'"
                            class="px-5 py-2.5 rounded-xl text-sm font-semibold transition-all flex items-center gap-2 whitespace-nowrap"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                            </svg>
                            <span>بطاقات الـ QR والطباعة 80mm</span>
                        </button>

                        <button
                            @click="activeDemoTab = 'analytics'"
                            :class="activeDemoTab === 'analytics' ? 'bg-gradient-to-r from-amber-500 to-orange-500 text-slate-950 font-bold shadow-lg shadow-amber-500/20' : 'text-slate-300 hover:text-white'"
                            class="px-5 py-2.5 rounded-xl text-sm font-semibold transition-all flex items-center gap-2 whitespace-nowrap"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                            <span>مركز التحليلات وذكاء الأعمال</span>
                        </button>
                    </div>
                </div>

                <!-- Showcase Canvas -->
                <div class="relative rounded-3xl bg-slate-950/90 border border-white/10 p-6 sm:p-10 shadow-2xl backdrop-blur-2xl">

                    <!-- ============================================== -->
                    <!-- TAB 1: CUSTOMER MOBILE MENU SIMULATOR -->
                    <!-- ============================================== -->
                    <div v-if="activeDemoTab === 'customer'" class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">

                        <!-- Explanatory Side Info -->
                        <div class="lg:col-span-5 space-y-6">
                            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-500/10 text-amber-400 text-xs font-bold border border-amber-500/20">
                                <span>تجربة زبون استثنائية (No App Needed)</span>
                            </div>
                            <h3 class="text-2xl sm:text-3xl font-extrabold text-white">
                                قائمة تفاعلية فورية وسلة عائمة زجاجية لا تحجب المنيو!
                            </h3>
                            <p class="text-slate-300 text-sm sm:text-base leading-relaxed">
                                جرّب الآن في الهاتف المقابل: انقر على التصنيفات، وأضف الوجبات بالسحب أو النقر على
                                <span class="text-amber-400 font-bold font-mono">[ + ]</span>
                                لتتحول البطاقة إلى عداد فوري، وشاهد شريط السلة العائم بالأسفل وهو يرتعش بنعومة مع احتساب المجموع لحظياً.
                            </p>
                            <div class="space-y-3 pt-2">
                                <div class="flex items-start gap-3">
                                    <div class="w-6 h-6 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center shrink-0 mt-0.5">✓</div>
                                    <span class="text-sm text-slate-300"><strong class="text-white">تخصيص الملاحظات:</strong> إمكانية كتابة رغبات الزبون لكل صنف مثل "بدون ملح" أو "زيادة صوص".</span>
                                </div>
                                <div class="flex items-start gap-3">
                                    <div class="w-6 h-6 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center shrink-0 mt-0.5">✓</div>
                                    <span class="text-sm text-slate-300"><strong class="text-white">سلة غير معطلة:</strong> شريط السلة العائم لا يغلق الشاشة، مما يسهل إضافة 5-10 أصناف متتالية بلمسات خاطفة.</span>
                                </div>
                                <div class="flex items-start gap-3">
                                    <div class="w-6 h-6 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center shrink-0 mt-0.5">✓</div>
                                    <span class="text-sm text-slate-300"><strong class="text-white">أكواد الخصم والعروض:</strong> فحص تلقائي لكود الخصم واحتساب القيمة المخفضة فوراً.</span>
                                </div>
                            </div>
                        </div>

                        <!-- Mobile Mockup Simulator -->
                        <div class="lg:col-span-7 flex justify-center">
                            <div class="w-full max-w-[370px] bg-slate-900 rounded-[42px] p-3.5 border-4 border-slate-700/80 shadow-[0_0_50px_rgba(245,158,11,0.15)] relative overflow-hidden">

                                <!-- Phone Notch & Speaker -->
                                <div class="absolute top-0 left-1/2 -translate-x-1/2 w-32 h-5 bg-slate-800 rounded-b-2xl z-30 flex items-center justify-center">
                                    <div class="w-10 h-1 bg-slate-600 rounded-full"></div>
                                </div>

                                <!-- Phone Screen Content -->
                                <div class="bg-[#0b101d] rounded-[32px] overflow-hidden flex flex-col h-[620px] relative text-slate-100 select-none">

                                    <!-- Restaurant Cover Header -->
                                    <div class="relative h-28 shrink-0">
                                        <img
                                            src="/storage/restaurants/aleppo-navbar.jpg"
                                            alt="غلاف المطعم"
                                            class="w-full h-full object-cover"
                                            onerror="this.src='/storage/restaurants/bait-alsham-navbar.jpg'"
                                        />
                                        <div class="absolute inset-0 bg-gradient-to-t from-[#0b101d] via-[#0b101d]/60 to-transparent"></div>
                                        <div class="absolute bottom-2.5 right-3 left-3 flex items-center justify-between">
                                            <div class="flex items-center gap-2">
                                                <img
                                                    src="/storage/restaurants/aleppo-logo.jpg"
                                                    alt="لوجو المطعم"
                                                    class="w-9 h-9 rounded-xl border border-amber-500/40 object-cover shadow"
                                                    onerror="this.src='/images/logo.jpg'"
                                                />
                                                <div>
                                                    <h4 class="text-xs font-bold text-white">مطعم حلب الشهباء</h4>
                                                    <div class="flex items-center gap-1.5 text-[10px] text-amber-400">
                                                        <span>★ 4.9 (520+ تقييم)</span>
                                                        <span class="text-slate-500">•</span>
                                                        <span class="text-emerald-400 font-semibold">مفتوح للطلبات</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <span class="text-[10px] px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/30 font-bold">
                                                طاولة 04
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Category Tabs Bar -->
                                    <div class="px-3 py-2 border-b border-white/5 flex gap-1.5 overflow-x-auto shrink-0 bg-[#090e1a]">
                                        <button
                                            @click="activeCategory = 'grills'"
                                            :class="activeCategory === 'grills' ? 'bg-amber-500 text-slate-950 font-bold' : 'bg-slate-800/80 text-slate-300'"
                                            class="px-3 py-1 rounded-full text-[11px] whitespace-nowrap transition-all"
                                        >
                                            مشاوي حلبية
                                        </button>
                                        <button
                                            @click="activeCategory = 'appetizers'"
                                            :class="activeCategory === 'appetizers' ? 'bg-amber-500 text-slate-950 font-bold' : 'bg-slate-800/80 text-slate-300'"
                                            class="px-3 py-1 rounded-full text-[11px] whitespace-nowrap transition-all"
                                        >
                                            مقبلات وكبب
                                        </button>
                                        <button
                                            @click="activeCategory = 'desserts'"
                                            :class="activeCategory === 'desserts' ? 'bg-amber-500 text-slate-950 font-bold' : 'bg-slate-800/80 text-slate-300'"
                                            class="px-3 py-1 rounded-full text-[11px] whitespace-nowrap transition-all"
                                        >
                                            حلويات
                                        </button>
                                        <button
                                            @click="activeCategory = 'drinks'"
                                            :class="activeCategory === 'drinks' ? 'bg-amber-500 text-slate-950 font-bold' : 'bg-slate-800/80 text-slate-300'"
                                            class="px-3 py-1 rounded-full text-[11px] whitespace-nowrap transition-all"
                                        >
                                            مشروبات
                                        </button>
                                    </div>

                                    <!-- Food Items Scroll Area -->
                                    <div class="flex-1 overflow-y-auto p-3 space-y-2.5 pb-20">
                                        <div
                                            v-for="item in filteredMenuItems"
                                            :key="item.id"
                                            class="p-2.5 rounded-2xl bg-slate-900/90 border border-white/5 hover:border-amber-500/30 transition-all flex gap-2.5 items-center"
                                        >
                                            <div class="relative w-16 h-16 rounded-xl overflow-hidden shrink-0 bg-slate-800">
                                                <img :src="item.image" :alt="item.name" class="w-full h-full object-cover" />
                                                <span v-if="cartQuantities[item.id]" class="absolute top-1 right-1 w-5 h-5 rounded-full bg-amber-500 text-slate-950 font-bold text-[10px] flex items-center justify-center">
                                                    {{ cartQuantities[item.id] }}
                                                </span>
                                            </div>

                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center gap-1.5">
                                                    <h5 class="text-xs font-bold text-white truncate">{{ item.name }}</h5>
                                                    <span v-if="item.badge" class="text-[9px] px-1.5 py-0.2 rounded bg-amber-500/20 text-amber-300 shrink-0">
                                                        {{ item.badge }}
                                                    </span>
                                                </div>
                                                <p class="text-[10px] text-slate-400 line-clamp-1 mt-0.5">{{ item.description }}</p>
                                                <div class="text-xs font-bold text-amber-400 mt-1 font-mono">
                                                    {{ item.price }} ر.س
                                                </div>
                                            </div>

                                            <!-- Stepper or Add button -->
                                            <div class="shrink-0">
                                                <div v-if="cartQuantities[item.id]" class="flex items-center gap-1.5 bg-slate-800 border border-amber-500/30 rounded-xl px-1.5 py-1">
                                                    <button @click="removeItem(item.id)" class="w-5 h-5 rounded-lg bg-slate-700 text-white text-xs flex items-center justify-center hover:bg-slate-600">-</button>
                                                    <span class="text-xs font-bold font-mono px-1">{{ cartQuantities[item.id] }}</span>
                                                    <button @click="addItem(item.id)" class="w-5 h-5 rounded-lg bg-amber-500 text-slate-950 text-xs font-bold flex items-center justify-center hover:bg-amber-400">+</button>
                                                </div>
                                                <button
                                                    v-else
                                                    @click="addItem(item.id)"
                                                    class="w-8 h-8 rounded-xl bg-amber-500/10 hover:bg-amber-500 text-amber-400 hover:text-slate-950 border border-amber-500/30 flex items-center justify-center font-bold text-sm transition-all"
                                                >
                                                    +
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Tedallal Signature: Floating Glassmorphism Cart Bar -->
                                    <div
                                        v-if="totalCartCount > 0"
                                        class="absolute bottom-2.5 right-2.5 left-2.5 p-3 rounded-2xl bg-[#090e1c]/95 border border-amber-500/40 shadow-2xl backdrop-blur-xl flex items-center justify-between z-20 animate-bounce-subtle"
                                    >
                                        <div class="flex items-center gap-2.5">
                                            <div class="relative w-8 h-8 rounded-xl bg-amber-500 text-slate-950 flex items-center justify-center font-bold shadow-md shadow-amber-500/30">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                                                </svg>
                                                <span class="absolute -top-1.5 -right-1.5 w-4 h-4 rounded-full bg-emerald-500 text-slate-950 text-[9px] font-extrabold flex items-center justify-center">
                                                    {{ totalCartCount }}
                                                </span>
                                            </div>
                                            <div>
                                                <div class="text-[10px] text-slate-400">سلة الطاولة الحالية</div>
                                                <div class="text-xs font-extrabold text-amber-400 font-mono">{{ totalCartPrice }} ر.س</div>
                                            </div>
                                        </div>

                                        <button
                                            @click="activeDemoTab = 'kds'"
                                            class="px-3.5 py-1.5 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 text-slate-950 text-xs font-bold hover:scale-105 active:scale-95 transition-all shadow"
                                        >
                                            إرسال للمطبخ ⚡
                                        </button>
                                    </div>

                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- ============================================== -->
                    <!-- TAB 2: STAFF KITCHEN DISPLAY (KDS) SIMULATOR -->
                    <!-- ============================================== -->
                    <div v-if="activeDemoTab === 'kds'" class="space-y-6">

                        <!-- KDS Header & Controls -->
                        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-white/10 pb-4">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="w-3 h-3 rounded-full bg-emerald-500 animate-ping"></span>
                                    <h3 class="text-xl sm:text-2xl font-bold text-white">شاشة المطبخ والخدمة الفورية (KDS Staff Station)</h3>
                                </div>
                                <p class="text-xs sm:text-sm text-slate-400 mt-1">
                                    تصل الطلبات من الهواتف فوراً بنظام كانبان تفاعلي مع نغمة صوتية وتتبع ذكي لزمن الانتظار. انقر على أي زر ترقية لنقل الطلب للمرحلة التالية!
                                </p>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="px-3 py-1 rounded-xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 text-xs font-bold">
                                    🔊 نغمة صوتية نشطة
                                </span>
                                <span class="px-3 py-1 rounded-xl bg-slate-800 text-slate-300 text-xs font-mono">
                                    4 طلبات نشطة الآن
                                </span>
                            </div>
                        </div>

                        <!-- KDS Kanban Columns -->
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">

                            <!-- Column: Pending -->
                            <div class="rounded-2xl bg-slate-900/70 border border-amber-500/20 p-3 space-y-3">
                                <div class="flex items-center justify-between border-b border-white/5 pb-2">
                                    <span class="text-xs font-bold text-amber-400 flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full bg-amber-400 animate-ping"></span>
                                        طلبات جديدة (Pending)
                                    </span>
                                    <span class="text-[10px] px-1.5 py-0.5 rounded bg-amber-500/20 text-amber-300 font-mono">1</span>
                                </div>

                                <div
                                    v-for="order in kdsOrders.filter(o => o.status === 'pending')"
                                    :key="order.id"
                                    class="p-3 rounded-xl bg-slate-950 border border-amber-500/40 shadow-lg space-y-2.5 relative"
                                >
                                    <div class="flex items-center justify-between text-xs font-bold">
                                        <span class="text-white">{{ order.table }}</span>
                                        <span class="text-[10px] text-amber-400 px-2 py-0.5 rounded-full bg-amber-500/10 font-mono">{{ order.time }}</span>
                                    </div>
                                    <div class="space-y-1.5 text-xs">
                                        <div v-for="(it, idx) in order.items" :key="idx" class="border-b border-white/5 pb-1">
                                            <div class="flex justify-between font-semibold text-slate-200">
                                                <span>{{ it.qty }}x {{ it.name }}</span>
                                            </div>
                                            <div v-if="it.note" class="text-[10px] text-amber-300 bg-amber-500/10 px-1.5 py-0.5 rounded mt-1">
                                                ملاحظة: {{ it.note }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="pt-2 flex items-center justify-between border-t border-white/5">
                                        <span class="text-xs font-mono font-bold text-slate-300">{{ order.total }} ر.س</span>
                                        <button
                                            @click="advanceKdsOrder(order.id)"
                                            class="px-3 py-1 rounded-lg bg-amber-500 text-slate-950 font-bold text-xs hover:bg-amber-400 transition-all"
                                        >
                                            بدء التحضير 👨‍🍳
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Column: Cooking -->
                            <div class="rounded-2xl bg-slate-900/70 border border-orange-500/20 p-3 space-y-3">
                                <div class="flex items-center justify-between border-b border-white/5 pb-2">
                                    <span class="text-xs font-bold text-orange-400 flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full bg-orange-400"></span>
                                        قيد التحضير (Cooking)
                                    </span>
                                    <span class="text-[10px] px-1.5 py-0.5 rounded bg-orange-500/20 text-orange-300 font-mono">1</span>
                                </div>

                                <div
                                    v-for="order in kdsOrders.filter(o => o.status === 'cooking')"
                                    :key="order.id"
                                    class="p-3 rounded-xl bg-slate-950 border border-orange-500/40 shadow-lg space-y-2.5"
                                >
                                    <div class="flex items-center justify-between text-xs font-bold">
                                        <span class="text-white">{{ order.table }}</span>
                                        <span class="text-[10px] text-orange-400 px-2 py-0.5 rounded-full bg-orange-500/10 font-mono">{{ order.time }}</span>
                                    </div>
                                    <div class="space-y-1.5 text-xs">
                                        <div v-for="(it, idx) in order.items" :key="idx" class="border-b border-white/5 pb-1">
                                            <div class="flex justify-between font-semibold text-slate-200">
                                                <span>{{ it.qty }}x {{ it.name }}</span>
                                            </div>
                                            <div v-if="it.note" class="text-[10px] text-amber-300 bg-amber-500/10 px-1.5 py-0.5 rounded mt-1">
                                                {{ it.note }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="pt-2 flex items-center justify-between border-t border-white/5">
                                        <span class="text-xs font-mono font-bold text-slate-300">{{ order.total }} ر.س</span>
                                        <button
                                            @click="advanceKdsOrder(order.id)"
                                            class="px-3 py-1 rounded-lg bg-orange-500 text-slate-950 font-bold text-xs hover:bg-orange-400 transition-all"
                                        >
                                            جاهز للتقديم 🍽️
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Column: Ready -->
                            <div class="rounded-2xl bg-slate-900/70 border border-emerald-500/20 p-3 space-y-3">
                                <div class="flex items-center justify-between border-b border-white/5 pb-2">
                                    <span class="text-xs font-bold text-emerald-400 flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                                        جاهز للتقديم (Ready)
                                    </span>
                                    <span class="text-[10px] px-1.5 py-0.5 rounded bg-emerald-500/20 text-emerald-300 font-mono">1</span>
                                </div>

                                <div
                                    v-for="order in kdsOrders.filter(o => o.status === 'ready')"
                                    :key="order.id"
                                    class="p-3 rounded-xl bg-slate-950 border border-emerald-500/40 shadow-lg space-y-2.5"
                                >
                                    <div class="flex items-center justify-between text-xs font-bold">
                                        <span class="text-white">{{ order.table }}</span>
                                        <span class="text-[10px] text-emerald-400 px-2 py-0.5 rounded-full bg-emerald-500/10 font-mono">{{ order.time }}</span>
                                    </div>
                                    <div class="space-y-1.5 text-xs">
                                        <div v-for="(it, idx) in order.items" :key="idx" class="border-b border-white/5 pb-1">
                                            <div class="flex justify-between font-semibold text-slate-200">
                                                <span>{{ it.qty }}x {{ it.name }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="pt-2 flex items-center justify-between border-t border-white/5">
                                        <span class="text-xs font-mono font-bold text-slate-300">{{ order.total }} ر.س</span>
                                        <button
                                            @click="advanceKdsOrder(order.id)"
                                            class="px-3 py-1 rounded-lg bg-emerald-500 text-slate-950 font-bold text-xs hover:bg-emerald-400 transition-all"
                                        >
                                            تم التقديم ✅
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Column: Served -->
                            <div class="rounded-2xl bg-slate-900/70 border border-slate-700/30 p-3 space-y-3 opacity-80">
                                <div class="flex items-center justify-between border-b border-white/5 pb-2">
                                    <span class="text-xs font-bold text-slate-400 flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full bg-slate-500"></span>
                                        تم التقديم (Served)
                                    </span>
                                    <span class="text-[10px] px-1.5 py-0.5 rounded bg-slate-800 text-slate-400 font-mono">1</span>
                                </div>

                                <div
                                    v-for="order in kdsOrders.filter(o => o.status === 'served')"
                                    :key="order.id"
                                    class="p-3 rounded-xl bg-slate-950 border border-slate-800 shadow space-y-2.5"
                                >
                                    <div class="flex items-center justify-between text-xs font-bold">
                                        <span class="text-slate-300">{{ order.table }}</span>
                                        <span class="text-[10px] text-slate-500 font-mono">{{ order.time }}</span>
                                    </div>
                                    <div class="space-y-1 text-xs text-slate-400">
                                        <div v-for="(it, idx) in order.items" :key="idx">
                                            <span>{{ it.qty }}x {{ it.name }}</span>
                                        </div>
                                    </div>
                                    <div class="pt-2 flex items-center justify-between border-t border-white/5 text-[11px] text-slate-500">
                                        <span>اكتمل بنجاح</span>
                                        <button
                                            @click="advanceKdsOrder(order.id)"
                                            class="text-amber-400 hover:underline"
                                        >
                                            إعادة تدوير
                                        </button>
                                    </div>
                                </div>
                            </div>

                        </div>

                    </div>

                    <!-- ============================================== -->
                    <!-- TAB 3: TABLE QR CARDS & 80MM THERMAL RECEIPT -->
                    <!-- ============================================== -->
                    <div v-if="activeDemoTab === 'qr'" class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-center">

                        <!-- QR Table Card Preview -->
                        <div class="p-6 rounded-3xl bg-slate-900/90 border border-amber-500/30 shadow-xl space-y-6 text-center">
                            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-500/10 text-amber-400 text-xs font-bold">
                                <span>بطاقة الطاولة الجاهزة للطباعة والقص</span>
                            </div>

                            <div class="max-w-[280px] mx-auto p-6 rounded-2xl bg-white text-slate-950 shadow-2xl border-4 border-amber-500 space-y-4">
                                <div class="flex items-center justify-center gap-2">
                                    <img src="/images/tedallal-brand-logo.jpg" alt="Logo" class="w-8 h-8 rounded-lg object-cover" onerror="this.src='/images/logo.jpg'" />
                                    <span class="font-bold text-base tracking-tight">مطعم حلب الشهباء</span>
                                </div>

                                <div class="p-2 rounded-xl bg-slate-50 border border-slate-200">
                                    <img
                                        src="/storage/qr/1/--eY9fjYlSGr57nA-bbecW8pHhr8f4JpT_nooTnncug.png"
                                        alt="رمز الـ QR للطاولة"
                                        class="w-44 h-44 mx-auto object-contain"
                                    />
                                </div>

                                <div class="space-y-1">
                                    <div class="text-xs font-bold text-slate-500">رقم الطاولة</div>
                                    <div class="text-2xl font-black text-amber-600 font-mono tracking-wider">TABLE #04</div>
                                </div>

                                <div class="text-[11px] font-semibold text-slate-600 border-t border-slate-200 pt-2">
                                    امسح بكاميرا الهاتف وتدلّل بطلبك فوراً 📱
                                </div>
                            </div>

                            <p class="text-xs text-slate-400 max-w-sm mx-auto">
                                إمكانية توليد وطباعة مجمعة لكافة طاولات المطعم بتنسيق شبكي جاهز للتركيب، مع إمكانية تجديد الرمز للأمان بنقرة واحدة (Regenerate QR).
                            </p>
                        </div>

                        <!-- 80mm Thermal Receipt Preview -->
                        <div class="p-6 rounded-3xl bg-slate-900/90 border border-white/10 shadow-xl space-y-6">
                            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/10 text-emerald-400 text-xs font-bold">
                                <span>معيار الفاتورة الحرارية 80mm Thermal Receipt</span>
                            </div>

                            <!-- Receipt Slip -->
                            <div class="max-w-[300px] mx-auto p-5 rounded-lg bg-[#faf8f5] text-slate-950 font-mono text-xs shadow-2xl border border-slate-300 space-y-3">
                                <div class="text-center space-y-1 border-b border-dashed border-slate-400 pb-3">
                                    <div class="font-extrabold text-sm">منصة تدلّل - مطعم حلب الشهباء</div>
                                    <div class="text-[10px] text-slate-600">طاولة رقم: 04 | طلب #1042</div>
                                    <div class="text-[10px] text-slate-500">التاريخ: 2026-10-25 17:30</div>
                                </div>

                                <div class="space-y-1.5 border-b border-dashed border-slate-400 pb-3">
                                    <div class="flex justify-between font-bold">
                                        <span>2x كباب حلبي بالكرز</span>
                                        <span>116.00</span>
                                    </div>
                                    <div class="text-[10px] text-slate-600 pr-2">- ملاحظة: بدون بصل مع صوص</div>

                                    <div class="flex justify-between font-bold">
                                        <span>1x مقبلات مشكلة</span>
                                        <span>32.00</span>
                                    </div>

                                    <div class="flex justify-between font-bold">
                                        <span>2x عصير رمان مثلج</span>
                                        <span>36.00</span>
                                    </div>
                                </div>

                                <div class="space-y-1 text-xs">
                                    <div class="flex justify-between">
                                        <span>المجموع الفرعي:</span>
                                        <span>184.00 ر.س</span>
                                    </div>
                                    <div class="flex justify-between text-emerald-700 font-bold">
                                        <span>كود خصم (TEDALLAL15):</span>
                                        <span>-27.60 ر.س</span>
                                    </div>
                                    <div class="flex justify-between font-black text-sm border-t border-slate-400 pt-1">
                                        <span>الإجمالي الصافي:</span>
                                        <span>156.40 ر.س</span>
                                    </div>
                                </div>

                                <div class="text-center text-[10px] text-slate-500 border-t border-dashed border-slate-400 pt-2">
                                    شكراً لزيارتكم • صحتين وهنا!
                                </div>
                            </div>

                            <p class="text-xs text-slate-400 text-center max-w-sm mx-auto">
                                طباعة متوافقة مع كافة طابعات الكاشير والمطبخ الحرارية القياسية عبر المتصفح مباشرة دون أي إضافات خارجية.
                            </p>
                        </div>

                    </div>

                    <!-- ============================================== -->
                    <!-- TAB 4: SMART ANALYTICS & BI -->
                    <!-- ============================================== -->
                    <div v-if="activeDemoTab === 'analytics'" class="space-y-6">

                        <div class="border-b border-white/10 pb-4">
                            <h3 class="text-2xl font-bold text-white">مركز التحليلات الذكية وذكاء الأعمال (BI Dashboard)</h3>
                            <p class="text-sm text-slate-400 mt-1">
                                بيانات حية تدعم قرارات المطعم: تحديد ساعات الذروة، تصنيف الأصناف الأكثر ربحية، واقتراحات خوارزمية ذكية لمضاعفة المبيعات.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

                            <!-- Peak Hours Simulated Chart -->
                            <div class="md:col-span-2 p-5 rounded-2xl bg-slate-900/80 border border-white/5 space-y-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-bold text-white">مخطط أوقات الذروة وتوزيع الطلبات (Peak Hours)</span>
                                    <span class="text-xs text-amber-400 font-mono">الذروة: 8:00 - 10:00 م</span>
                                </div>

                                <!-- Animated SVG Bar Chart Representation -->
                                <div class="h-44 flex items-end justify-between gap-2 pt-6 px-2">
                                    <div class="flex-1 flex flex-col items-center gap-2 group">
                                        <div class="w-full bg-slate-800 rounded-t-lg group-hover:bg-amber-500 transition-all" style="height: 25%;"></div>
                                        <span class="text-[10px] text-slate-500 font-mono">1 م</span>
                                    </div>
                                    <div class="flex-1 flex flex-col items-center gap-2 group">
                                        <div class="w-full bg-slate-800 rounded-t-lg group-hover:bg-amber-500 transition-all" style="height: 40%;"></div>
                                        <span class="text-[10px] text-slate-500 font-mono">3 م</span>
                                    </div>
                                    <div class="flex-1 flex flex-col items-center gap-2 group">
                                        <div class="w-full bg-slate-800 rounded-t-lg group-hover:bg-amber-500 transition-all" style="height: 30%;"></div>
                                        <span class="text-[10px] text-slate-500 font-mono">5 م</span>
                                    </div>
                                    <div class="flex-1 flex flex-col items-center gap-2 group">
                                        <div class="w-full bg-amber-500/50 rounded-t-lg group-hover:bg-amber-500 transition-all" style="height: 65%;"></div>
                                        <span class="text-[10px] text-slate-500 font-mono">7 م</span>
                                    </div>
                                    <div class="flex-1 flex flex-col items-center gap-2 group">
                                        <div class="w-full bg-gradient-to-t from-amber-500 to-orange-500 rounded-t-lg group-hover:brightness-125 transition-all shadow-lg shadow-amber-500/20" style="height: 98%;"></div>
                                        <span class="text-[10px] text-amber-400 font-bold font-mono">9 م</span>
                                    </div>
                                    <div class="flex-1 flex flex-col items-center gap-2 group">
                                        <div class="w-full bg-amber-500/70 rounded-t-lg group-hover:bg-amber-500 transition-all" style="height: 80%;"></div>
                                        <span class="text-[10px] text-slate-500 font-mono">11 م</span>
                                    </div>
                                    <div class="flex-1 flex flex-col items-center gap-2 group">
                                        <div class="w-full bg-slate-800 rounded-t-lg group-hover:bg-amber-500 transition-all" style="height: 35%;"></div>
                                        <span class="text-[10px] text-slate-500 font-mono">1 ص</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Top Dishes Breakdown -->
                            <div class="p-5 rounded-2xl bg-slate-900/80 border border-white/5 space-y-4">
                                <span class="text-sm font-bold text-white block">الأصناف الأكثر ربحية ومبيعاً</span>
                                <div class="space-y-3">
                                    <div>
                                        <div class="flex justify-between text-xs mb-1">
                                            <span class="text-slate-300">كباب حلبي بالكرز</span>
                                            <span class="text-amber-400 font-bold font-mono">42%</span>
                                        </div>
                                        <div class="w-full h-2 rounded-full bg-slate-800 overflow-hidden">
                                            <div class="h-full bg-amber-500 rounded-full" style="width: 42%;"></div>
                                        </div>
                                    </div>

                                    <div>
                                        <div class="flex justify-between text-xs mb-1">
                                            <span class="text-slate-300">أوصال لحم مشوي</span>
                                            <span class="text-amber-400 font-bold font-mono">28%</span>
                                        </div>
                                        <div class="w-full h-2 rounded-full bg-slate-800 overflow-hidden">
                                            <div class="h-full bg-orange-500 rounded-full" style="width: 28%;"></div>
                                        </div>
                                    </div>

                                    <div>
                                        <div class="flex justify-between text-xs mb-1">
                                            <span class="text-slate-300">كبة مشوية بالجوز</span>
                                            <span class="text-amber-400 font-bold font-mono">18%</span>
                                        </div>
                                        <div class="w-full h-2 rounded-full bg-slate-800 overflow-hidden">
                                            <div class="h-full bg-emerald-500 rounded-full" style="width: 18%;"></div>
                                        </div>
                                    </div>

                                    <div>
                                        <div class="flex justify-between text-xs mb-1">
                                            <span class="text-slate-300">كنافة نابلسية بالجبن</span>
                                            <span class="text-amber-400 font-bold font-mono">12%</span>
                                        </div>
                                        <div class="w-full h-2 rounded-full bg-slate-800 overflow-hidden">
                                            <div class="h-full bg-amber-300 rounded-full" style="width: 12%;"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <!-- Rule-Based Smart Advice Engine -->
                        <div class="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/30 flex items-start gap-3">
                            <span class="text-xl">💡</span>
                            <div>
                                <h5 class="text-sm font-bold text-amber-300">اقتراح ذكي من محرك Tedallal Insights:</h5>
                                <p class="text-xs text-slate-300 mt-1">
                                    تظهر البيانات ارتفاع طلبات "المقبلات المشكلة" بين الساعة 8 و 10 مساءً بنسبة 45%. نقترح إنشاء عرض كومبو يجمع المشاوي مع المقبلات لرفع متوسط قيمة الفاتورة بمقدار 22 ر.س.
                                </p>
                            </div>
                        </div>

                    </div>

                </div>

            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- CORE PLATFORM FEATURES GRID -->
        <!-- ========================================================================= -->
        <section id="features" class="py-20 border-t border-white/5 bg-[#080d19]/80 relative z-10">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                <div class="text-center max-w-3xl mx-auto mb-16 space-y-4">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-500/10 text-amber-400 text-xs font-bold border border-amber-500/20">
                        <span>قوة تقنية متكاملة</span>
                    </div>
                    <h2 class="text-3xl sm:text-4xl font-extrabold text-white">
                        كل ما يحتاجه مطعمك للنمو والأتمتة في مكان واحد
                    </h2>
                    <p class="text-slate-400 text-base sm:text-lg">
                        حلول هندسية صممت بعناية لتخدم الزبون، طاقم المطبخ، ومدير المطعم، والمالك الرئيسي.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">

                    <!-- Feature 1 -->
                    <div class="p-8 rounded-3xl bg-slate-900/60 border border-white/10 hover:border-amber-500/30 transition-all duration-300 group hover:-translate-y-1">
                        <div class="w-14 h-14 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-400 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-white mb-3">طلب فوري بدون تطبيقات</h3>
                        <p class="text-slate-400 text-sm leading-relaxed">
                            الزبون لا يضيع وقته في تحميل برامج أو ملء استمارات تسجيل. مسح الـ QR يفتح القائمة فوراً بجلسة مشفرة وآمنة.
                        </p>
                    </div>

                    <!-- Feature 2 -->
                    <div class="p-8 rounded-3xl bg-slate-900/60 border border-white/10 hover:border-amber-500/30 transition-all duration-300 group hover:-translate-y-1">
                        <div class="w-14 h-14 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-white mb-3">محرك Reverb اللحظي</h3>
                        <p class="text-slate-400 text-sm leading-relaxed">
                            خادم WebSockets فائق السرعة يدفع الطلبات وتحديثات الحالات لحظياً للشاشات دون عمل أي Refresh أو إجهاد للسيرفرات.
                        </p>
                    </div>

                    <!-- Feature 3 -->
                    <div class="p-8 rounded-3xl bg-slate-900/60 border border-white/10 hover:border-amber-500/30 transition-all duration-300 group hover:-translate-y-1">
                        <div class="w-14 h-14 rounded-2xl bg-orange-500/10 border border-orange-500/20 text-orange-400 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-white mb-3">تجميد الأسعار الفوري</h3>
                        <p class="text-slate-400 text-sm leading-relaxed">
                            حفظ لقطة غير قابلة للتعديل لأسماء وأسعار الأصناف في لحظة الشراء لحماية حقوق الزبون والمطعم ومنع أي تضارب محاسبي.
                        </p>
                    </div>

                    <!-- Feature 4 -->
                    <div class="p-8 rounded-3xl bg-slate-900/60 border border-white/10 hover:border-amber-500/30 transition-all duration-300 group hover:-translate-y-1">
                        <div class="w-14 h-14 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-400 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-white mb-3">طباعة حرارية 80mm وتوليد QR</h3>
                        <p class="text-slate-400 text-sm leading-relaxed">
                            طباعة مباشرة لتذاكر المطبخ وإيصالات الطاولات، مع إمكانية طباعة بطاقات الـ QR المجمعة وإعادة توليدها للأمان بنقرة زر.
                        </p>
                    </div>

                    <!-- Feature 5 -->
                    <div class="p-8 rounded-3xl bg-slate-900/60 border border-white/10 hover:border-amber-500/30 transition-all duration-300 group hover:-translate-y-1">
                        <div class="w-14 h-14 rounded-2xl bg-purple-500/10 border border-purple-500/20 text-purple-400 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-white mb-3">نظام الإضافات وباقات SaaS</h3>
                        <p class="text-slate-400 text-sm leading-relaxed">
                            تحكم مرن بالميزات والإضافات لكل مطعم بشكل مستقل، مع إدارة اشتراكات مركزية وحماية وضع الصيانة الحصري لمالك المنصة.
                        </p>
                    </div>

                    <!-- Feature 6 -->
                    <div class="p-8 rounded-3xl bg-slate-900/60 border border-white/10 hover:border-amber-500/30 transition-all duration-300 group hover:-translate-y-1">
                        <div class="w-14 h-14 rounded-2xl bg-cyan-500/10 border border-cyan-500/20 text-cyan-400 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-white mb-3">تحليلات ذكية ومقترحات آلية</h3>
                        <p class="text-slate-400 text-sm leading-relaxed">
                            رسوم بيانية تفاعلية تكشف ساعات الذروة، تصنيف الأصناف الراكدة والأعلى طلباً، مع خوارزميات ذكية تقترح عروضاً لزيادة الإيراد.
                        </p>
                    </div>

                </div>

            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- INTERACTIVE ROI & SAVINGS CALCULATOR -->
        <!-- ========================================================================= -->
        <section id="roi-calculator" class="py-24 relative z-10 border-t border-white/5 bg-[#070b14]">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                <div class="text-center max-w-3xl mx-auto mb-16 space-y-4">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/10 text-emerald-400 text-xs font-bold border border-emerald-500/20">
                        <span>💰 حاسبة العائد والوفر المالي</span>
                    </div>
                    <h2 class="text-3xl sm:text-4xl font-extrabold text-white">
                        كم ستوفر وتربح عند تشغيل «تدلّل» في مطعمك؟
                    </h2>
                    <p class="text-slate-400 text-base sm:text-lg">
                        حرّك المؤشرات أدناه وفق حجم مطعمك الحالي، واكتشف فوراً حجم الوفر المالي وزيادة الأرباح الناتجة عن تسريع الخدمة.
                    </p>
                </div>

                <div class="max-w-4xl mx-auto p-8 sm:p-12 rounded-3xl bg-slate-900/80 border border-white/10 shadow-2xl backdrop-blur-xl">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-10 items-center">

                        <!-- Sliders Controls -->
                        <div class="space-y-8">
                            <!-- Table Count Slider -->
                            <div class="space-y-3">
                                <div class="flex justify-between items-center text-sm">
                                    <span class="font-bold text-white">عدد الطاولات في المطعم:</span>
                                    <span class="text-amber-400 font-extrabold text-xl font-mono">{{ tableCount }} طاولة</span>
                                </div>
                                <input
                                    type="range"
                                    min="5"
                                    max="120"
                                    step="5"
                                    v-model.number="tableCount"
                                    class="w-full accent-amber-500 bg-slate-800 h-2.5 rounded-lg cursor-pointer"
                                />
                                <div class="flex justify-between text-[11px] text-slate-500 font-mono">
                                    <span>5 طاولات</span>
                                    <span>120 طاولة</span>
                                </div>
                            </div>

                            <!-- Daily Orders Slider -->
                            <div class="space-y-3">
                                <div class="flex justify-between items-center text-sm">
                                    <span class="font-bold text-white">متوسط الطلبات اليومية لكل طاولة:</span>
                                    <span class="text-amber-400 font-extrabold text-xl font-mono">{{ dailyOrdersPerTable }} طلبات</span>
                                </div>
                                <input
                                    type="range"
                                    min="2"
                                    max="15"
                                    step="1"
                                    v-model.number="dailyOrdersPerTable"
                                    class="w-full accent-amber-500 bg-slate-800 h-2.5 rounded-lg cursor-pointer"
                                />
                                <div class="flex justify-between text-[11px] text-slate-500 font-mono">
                                    <span>طلبين / يوم</span>
                                    <span>15 طلب / يوم</span>
                                </div>
                            </div>

                            <div class="p-4 rounded-2xl bg-white/[0.02] border border-white/5 text-xs text-slate-400 space-y-1">
                                <div class="flex justify-between">
                                    <span>إجمالي الطلبات الشهرية المتوقعة:</span>
                                    <strong class="text-white font-mono">{{ monthlyOrders.toLocaleString() }} طلب</strong>
                                </div>
                                <div class="flex justify-between">
                                    <span>متوسط الوقت الموفر لكل طاولة:</span>
                                    <strong class="text-emerald-400 font-mono">10 دقائق</strong>
                                </div>
                            </div>
                        </div>

                        <!-- Computed Impact Output -->
                        <div class="space-y-4 p-6 rounded-2xl bg-slate-950 border border-amber-500/20">

                            <div class="p-4 rounded-xl bg-amber-500/10 border border-amber-500/30">
                                <div class="text-xs text-amber-300 font-medium">الوفر السنوي في طباعة المنيو الورقي التالف:</div>
                                <div class="text-2xl sm:text-3xl font-black text-amber-400 font-mono mt-1">
                                    {{ yearlyPaperSavings.toLocaleString() }} ر.س
                                </div>
                                <div class="text-[11px] text-slate-400 mt-1">وفر مباشر بإلغاء طباعة القوائم وتعديل الأسعار ورقياً</div>
                            </div>

                            <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30">
                                <div class="text-xs text-emerald-300 font-medium">الوقت الإجمالي الموفر شهرياً لطاقم العمل:</div>
                                <div class="text-2xl sm:text-3xl font-black text-emerald-400 font-mono mt-1">
                                    {{ timeSavedHoursMonthly.toLocaleString() }} ساعة
                                </div>
                                <div class="text-[11px] text-slate-400 mt-1">يتفرغ الطاقم للترحيب والضيافة بدلاً من تسجيل الأوردرات</div>
                            </div>

                            <div class="p-4 rounded-xl bg-orange-500/10 border border-orange-500/30">
                                <div class="text-xs text-orange-300 font-medium">زيادة المبيعات التقديرية من سرعة تدوير الطاولات:</div>
                                <div class="text-2xl sm:text-3xl font-black text-orange-400 font-mono mt-1">
                                    +{{ estimatedRevenueBoost.toLocaleString() }} ر.س / شهرياً
                                </div>
                                <div class="text-[11px] text-slate-400 mt-1">بفضل استيعاب زبائن أكثر في أوقات الذروة</div>
                            </div>

                        </div>

                    </div>
                </div>

            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- COMPARISON: TRADITIONAL VS TEDALLAL -->
        <!-- ========================================================================= -->
        <section id="comparison" class="py-20 border-t border-white/5 bg-[#090e1c] relative z-10">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                <div class="text-center max-w-3xl mx-auto mb-16 space-y-4">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-500/10 text-amber-400 text-xs font-bold border border-amber-500/20">
                        <span>المقارنة الحاسمة</span>
                    </div>
                    <h2 class="text-3xl sm:text-4xl font-extrabold text-white">
                        لماذا المطاعم الرائدة تستبدل الأنظمة التقليدية بـ «تدلّل»؟
                    </h2>
                </div>

                <div class="max-w-4xl mx-auto rounded-3xl bg-slate-900/90 border border-white/10 shadow-2xl overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-right">
                            <thead>
                                <tr class="border-b border-white/10 bg-slate-950/80">
                                    <th class="py-5 px-6 font-bold text-slate-300">معيار المقارنة</th>
                                    <th class="py-5 px-6 font-bold text-red-400/90">المطاعم بالأسلوب التقليدي</th>
                                    <th class="py-5 px-6 font-extrabold text-amber-400 bg-amber-500/10">منصة تدلّل الذكية ⚡</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                <tr>
                                    <td class="py-4 px-6 font-semibold text-white">طريقة تصفح المنيو</td>
                                    <td class="py-4 px-6 text-slate-400">انتظار النادل لإحضار منيو ورقي متهالك</td>
                                    <td class="py-4 px-6 text-emerald-400 font-bold bg-amber-500/5">مسح فوري بالكاميرا بـ 2 ثانية فقط</td>
                                </tr>
                                <tr>
                                    <td class="py-4 px-6 font-semibold text-white">تحميل التطبيقات وتسجيل الحسابات</td>
                                    <td class="py-4 px-6 text-slate-400">يطلب من الزبون تثبيت تطبيق ورمز تحقق مزعج</td>
                                    <td class="py-4 px-6 text-emerald-400 font-bold bg-amber-500/5">صفر تطبيقات! يعمل فوراً عبر المتصفح</td>
                                </tr>
                                <tr>
                                    <td class="py-4 px-6 font-semibold text-white">تحديث الأسعار وتوفر الأصناف</td>
                                    <td class="py-4 px-6 text-slate-400">إعادة طباعة ورقية مكلفة أو شطب بالقلم</td>
                                    <td class="py-4 px-6 text-emerald-400 font-bold bg-amber-500/5">تعديل الأسعار وإخفاء النفاد بلمسة واحدة</td>
                                </tr>
                                <tr>
                                    <td class="py-4 px-6 font-semibold text-white">دقة طلبات وتخصيصات الزبون</td>
                                    <td class="py-4 px-6 text-slate-400">كثيرة الأخطاء الشفوية وسهو النادل</td>
                                    <td class="py-4 px-6 text-emerald-400 font-bold bg-amber-500/5">ملاحظات دقيقة ومبرزة لكل صنف على شاشة الشيف</td>
                                </tr>
                                <tr>
                                    <td class="py-4 px-6 font-semibold text-white">سرعة إرسال الطلب للمطبخ</td>
                                    <td class="py-4 px-6 text-slate-400">تأخير 5-15 دقيقة حتى يصل النادل للكاشير</td>
                                    <td class="py-4 px-6 text-emerald-400 font-bold bg-amber-500/5">بث لحظي في أجزاء من الثانية عبر WebSockets</td>
                                </tr>
                                <tr>
                                    <td class="py-4 px-6 font-semibold text-white">الطباعة الحرارية المعتمدة</td>
                                    <td class="py-4 px-6 text-slate-400">أجهزة POS مقيدة ببرامج وسيطة ومعقدة</td>
                                    <td class="py-4 px-6 text-emerald-400 font-bold bg-amber-500/5">طباعة حرارية معيارية 80mm مباشرة وبسيطة</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- TESTIMONIALS & RESTAURANTS SOCIAL PROOF -->
        <!-- ========================================================================= -->
        <section class="py-20 border-t border-white/5 bg-[#080c17] relative z-10">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                <div class="text-center max-w-3xl mx-auto mb-16 space-y-4">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-500/10 text-amber-400 text-xs font-bold border border-amber-500/20">
                        <span>شهادات حية</span>
                    </div>
                    <h2 class="text-3xl sm:text-4xl font-extrabold text-white">
                        ماذا يقول شركاؤنا من أصحاب المطاعم؟
                    </h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">

                    <!-- Testimonial 1 -->
                    <div class="p-8 rounded-3xl bg-slate-900/70 border border-white/10 hover:border-amber-500/30 transition-all space-y-4">
                        <div class="flex items-center gap-1 text-amber-400 text-sm">
                            ★★★★★
                        </div>
                        <p class="text-sm text-slate-300 leading-relaxed">
                            "«تدلّل» أحدثت نقلة جذرية في صالتنا. الزبائن منبهرون من سهولة تصفح المنيو بدون أي تطبيق، وشاشة المطبخ KDS مع التنبيه الصوتي منعت أي تأخير في تجهيز المشاوي بأوقات الذروة."
                        </p>
                        <div class="flex items-center gap-3 pt-4 border-t border-white/5">
                            <img src="/storage/restaurants/aleppo-logo.jpg" alt="مطعم حلب الشهباء" class="w-11 h-11 rounded-xl object-cover border border-amber-500/30" onerror="this.src='/images/logo.jpg'" />
                            <div>
                                <h4 class="text-sm font-bold text-white">أ. محمد الحلبي</h4>
                                <span class="text-xs text-slate-400">مدير عام - مطعم حلب الشهباء</span>
                            </div>
                        </div>
                    </div>

                    <!-- Testimonial 2 -->
                    <div class="p-8 rounded-3xl bg-slate-900/70 border border-white/10 hover:border-amber-500/30 transition-all space-y-4">
                        <div class="flex items-center gap-1 text-amber-400 text-sm">
                            ★★★★★
                        </div>
                        <p class="text-sm text-slate-300 leading-relaxed">
                            "أكثر ما أراحنا هو تجميد الأسعار الفوري، وعدم قدرة أي خطأ يدوي على تغيير حساب الزبون. وفرنا مبالغ ضخمة كنا ننفقها دورياً على تجديد المنيو المطبوع وتصميم البنرات."
                        </p>
                        <div class="flex items-center gap-3 pt-4 border-t border-white/5">
                            <img src="/storage/restaurants/bait-alsham-logo.jpg" alt="كافيه بيت الشام" class="w-11 h-11 rounded-xl object-cover border border-amber-500/30" onerror="this.src='/images/logo.jpg'" />
                            <div>
                                <h4 class="text-sm font-bold text-white">م. عمر الشامي</h4>
                                <span class="text-xs text-slate-400">الشريك المؤسس - كافيه وضيافة بيت الشام</span>
                            </div>
                        </div>
                    </div>

                    <!-- Testimonial 3 -->
                    <div class="p-8 rounded-3xl bg-slate-900/70 border border-white/10 hover:border-amber-500/30 transition-all space-y-4">
                        <div class="flex items-center gap-1 text-amber-400 text-sm">
                            ★★★★★
                        </div>
                        <p class="text-sm text-slate-300 leading-relaxed">
                            "مخطط أوقات الذروة والتحليلات الذكية ساعدتنا في ترتيب ورديات الطهاة بدقة. المنظومة مستقرة جداً وسريعة للغاية، وأنصح بها أي صاحب مطعم يسعى للارتقاء بخدمته."
                        </p>
                        <div class="flex items-center gap-3 pt-4 border-t border-white/5">
                            <img src="/storage/restaurants/al-diwan-logo.jpg" alt="قصر الديوان" class="w-11 h-11 rounded-xl object-cover border border-amber-500/30" onerror="this.src='/images/logo.jpg'" />
                            <div>
                                <h4 class="text-sm font-bold text-white">أ. فهد الدوسري</h4>
                                <span class="text-xs text-slate-400">إدارة التشغيل - قصر الديوان للضيافة</span>
                            </div>
                        </div>
                    </div>

                </div>

            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- INTERACTIVE FAQ ACCORDION -->
        <!-- ========================================================================= -->
        <section id="faqs" class="py-20 border-t border-white/5 bg-[#090d18] relative z-10">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

                <div class="text-center mb-16 space-y-4">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-500/10 text-amber-400 text-xs font-bold border border-amber-500/20">
                        <span>إجابات واضحة ومباشرة</span>
                    </div>
                    <h2 class="text-3xl sm:text-4xl font-extrabold text-white">
                        الأسئلة الشائعة حول منصة تدلّل
                    </h2>
                </div>

                <div class="space-y-4">
                    <div
                        v-for="(faq, index) in faqs"
                        :key="index"
                        class="rounded-2xl bg-slate-900/80 border border-white/10 overflow-hidden transition-all duration-200"
                    >
                        <button
                            @click="toggleFaq(index)"
                            class="w-full p-6 text-right flex items-center justify-between gap-4 font-bold text-white hover:text-amber-400 transition-colors"
                        >
                            <span class="text-base sm:text-lg">{{ faq.q }}</span>
                            <span class="p-1 rounded-lg bg-white/5 text-amber-400 shrink-0 transition-transform duration-200" :class="{ 'rotate-180': openFaqIndex === index }">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </span>
                        </button>
                        <div
                            v-if="openFaqIndex === index"
                            class="px-6 pb-6 text-slate-300 text-sm sm:text-base leading-relaxed border-t border-white/5 pt-4"
                        >
                            {{ faq.a }}
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- BOTTOM CALL-TO-ACTION (CTA) SECTION -->
        <!-- ========================================================================= -->
        <section class="py-24 relative z-10 border-t border-white/5 bg-gradient-to-b from-[#090d18] to-[#060911] overflow-hidden">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative">

                <!-- Glowing ambient halo -->
                <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[500px] h-[300px] bg-amber-500/15 rounded-full blur-[120px] pointer-events-none"></div>

                <div class="space-y-8 relative z-10">
                    <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-amber-500/10 text-amber-300 text-xs font-bold border border-amber-500/30">
                        <span>🚀 ابدأ خلال دقائق وبدون أي تعقيد</span>
                    </div>

                    <h2 class="text-4xl sm:text-5xl font-extrabold text-white tracking-tight leading-tight">
                        جاهز لنقل مطعمك إلى عصر
                        <span class="bg-gradient-to-r from-amber-400 via-orange-400 to-amber-300 bg-clip-text text-transparent block mt-2">
                            الضيافة الذكية والطلب الفوري؟
                        </span>
                    </h2>

                    <p class="text-slate-300 text-base sm:text-lg max-w-2xl mx-auto">
                        احصل على حساب تجريبي مجاني لمطعمك، وطباعة بطاقات الـ QR لطاولاتك، وشاهد فرق السرعة ورضا الزبائن بنفسك من اليوم الأول.
                    </p>

                    <div class="flex flex-col sm:flex-row items-center justify-center gap-4 pt-4">
                        <button
                            @click="isDemoModalOpen = true"
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-3 px-8 py-4 rounded-2xl bg-gradient-to-r from-amber-500 via-amber-400 to-orange-500 text-slate-950 font-extrabold text-base shadow-2xl shadow-amber-500/30 hover:shadow-amber-500/50 hover:scale-[1.02] active:scale-[0.98] transition-all"
                        >
                            <span>احجز عرضك الحي المجاني الآن</span>
                            <svg class="w-5 h-5 rtl:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                            </svg>
                        </button>

                        <Link
                            :href="route('login')"
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-8 py-4 rounded-2xl bg-slate-900 text-white font-bold text-base border border-slate-700 hover:border-amber-500/40 transition-all"
                        >
                            <span>دخول أصحاب المطاعم</span>
                        </Link>
                    </div>

                    <div class="flex items-center justify-center gap-6 text-xs text-slate-400 pt-4 font-medium">
                        <span class="flex items-center gap-1.5">✓ إعداد المطعم خلال 15 دقيقة</span>
                        <span class="flex items-center gap-1.5">✓ دعم فني لحظي</span>
                        <span class="flex items-center gap-1.5">✓ بدون عقود إلزامية</span>
                    </div>
                </div>

            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- LIVE ORDER NOTIFICATION TOAST SIMULATOR (BOTTOM-LEFT) -->
        <!-- ========================================================================= -->
        <div
            v-if="showToast"
            class="fixed bottom-6 left-6 z-40 max-w-sm p-4 rounded-2xl bg-slate-900/95 border border-amber-500/40 shadow-2xl backdrop-blur-xl transition-all duration-300 animate-slide-up flex items-start gap-3 select-none"
        >
            <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-amber-500 to-orange-500 flex items-center justify-center text-slate-950 font-bold shrink-0 shadow">
                ⚡
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between text-xs">
                    <span class="font-extrabold text-amber-400">{{ liveToasts[currentToastIndex].table }}</span>
                    <span class="text-[10px] text-slate-400 font-mono">{{ liveToasts[currentToastIndex].time }}</span>
                </div>
                <p class="text-xs text-slate-200 mt-1 leading-snug">
                    {{ liveToasts[currentToastIndex].text }}
                </p>
            </div>
            <button @click="showToast = false" class="text-slate-500 hover:text-white text-sm" aria-label="إغلاق">
                ✕
            </button>
        </div>

        <!-- ========================================================================= -->
        <!-- INTERACTIVE DEMO BOOKING MODAL -->
        <!-- ========================================================================= -->
        <div
            v-if="isDemoModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-md animate-fade-in"
        >
            <div class="relative w-full max-w-lg rounded-3xl bg-[#090e1c] border border-amber-500/30 p-6 sm:p-8 shadow-2xl">
                <!-- Close Button -->
                <button
                    @click="isDemoModalOpen = false"
                    class="absolute top-5 left-5 w-8 h-8 rounded-full bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center transition-all"
                >
                    ✕
                </button>

                <div v-if="!isSubmitted">
                    <div class="flex items-center gap-3 mb-5">
                        <div class="w-10 h-10 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center font-bold text-lg">
                            ✨
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-white">طلب عرض تجريبي مجاني</h3>
                            <p class="text-xs text-slate-400">انضم لأكثر من 50 مطعماً يعتمدون تدلّل</p>
                        </div>
                    </div>

                    <form @submit.prevent="submitDemoRequest" class="space-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">اسم المطعم / الكافيه</label>
                            <input
                                v-model="demoForm.restaurantName"
                                type="text"
                                required
                                placeholder="مثال: مطعم النرجس الدمشقي"
                                class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white text-sm focus:border-amber-500 focus:outline-none"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">اسم المسؤول</label>
                            <input
                                v-model="demoForm.contactPerson"
                                type="text"
                                required
                                placeholder="مثال: أ. أحمد العبدالله"
                                class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white text-sm focus:border-amber-500 focus:outline-none"
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-300 mb-1">رقم الجوال (واتساب)</label>
                                <input
                                    v-model="demoForm.phone"
                                    type="tel"
                                    required
                                    placeholder="05XXXXXXXX"
                                    class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white text-sm focus:border-amber-500 focus:outline-none font-mono"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-300 mb-1">المدينة</label>
                                <select
                                    v-model="demoForm.city"
                                    class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white text-sm focus:border-amber-500 focus:outline-none"
                                >
                                    <option value="الرياض">الرياض</option>
                                    <option value="جدة">جدة</option>
                                    <option value="الدمام">الدمام</option>
                                    <option value="مكة المكرمة">مكة المكرمة</option>
                                    <option value="المدينة المنورة">المدينة المنورة</option>
                                    <option value="دبي">دبي</option>
                                    <option value="أخرى">مدينة أخرى</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">عدد الطاولات التقديري</label>
                            <select
                                v-model="demoForm.tablesCount"
                                class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white text-sm focus:border-amber-500 focus:outline-none"
                            >
                                <option value="1-15">أقل من 15 طاولة</option>
                                <option value="15-35">15 - 35 طاولة</option>
                                <option value="35-70">35 - 70 طاولة</option>
                                <option value="70+">أكثر من 70 طاولة (فروع متعددة)</option>
                            </select>
                        </div>

                        <button
                            type="submit"
                            class="w-full py-3.5 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 text-slate-950 font-extrabold text-sm shadow-xl shadow-amber-500/25 hover:shadow-amber-500/40 hover:scale-[1.01] transition-all mt-2"
                        >
                            تأكيد إرسال الطلب والتواصل معي 🚀
                        </button>
                    </form>
                </div>

                <!-- Success Confirmation State -->
                <div v-else class="text-center py-8 space-y-4">
                    <div class="w-16 h-16 rounded-full bg-emerald-500/20 text-emerald-400 text-3xl flex items-center justify-center mx-auto animate-bounce">
                        ✓
                    </div>
                    <h3 class="text-2xl font-bold text-white">تم استلام طلبك بنجاح!</h3>
                    <p class="text-slate-300 text-sm max-w-sm mx-auto">
                        شكراً لك. سيتواصل معك مستشار حلول الضيافة لدى «تدلّل» خلال ساعات قليلة عبر الواتساب لتجهيز العرض الحي لحساب مطعمك.
                    </p>
                </div>

            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- FOOTER -->
        <!-- ========================================================================= -->
        <footer class="border-t border-white/5 bg-[#05080f] py-12 relative z-10 text-slate-400 text-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-8 mb-10">

                    <!-- Col 1: Brand -->
                    <div class="space-y-4 md:col-span-2">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-amber-500 to-orange-500 p-[1.5px]">
                                <div class="w-full h-full bg-[#0b101d] rounded-xl flex items-center justify-center overflow-hidden">
                                    <img src="/images/tedallal-brand-logo.jpg" alt="Logo" class="w-full h-full object-cover" onerror="this.src='/images/logo.jpg'" />
                                </div>
                            </div>
                            <span class="text-2xl font-extrabold text-white">تدلّل • Tedallal</span>
                        </div>
                        <p class="text-xs text-slate-400 leading-relaxed max-w-md">
                            منظومة سحابية سعودية متطورة مصممة للمطاعم والكافيهات بنظام SaaS متعدد المستأجرين. أتمتة كاملة لدورة الطلب من الطاولة حتى شاشات المطبخ والطباعة الحرارية.
                        </p>
                        <div class="flex items-center gap-2 text-xs text-emerald-400">
                            <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                            <span>كافة الخوادم السحابية ومحرك Reverb يعملان بكفاءة 99.98%</span>
                        </div>
                    </div>

                    <!-- Col 2: Quick Links -->
                    <div class="space-y-3">
                        <h5 class="text-xs font-bold text-white uppercase tracking-wider">روابط سريعة</h5>
                        <ul class="space-y-2 text-xs">
                            <li><a href="#how-it-works" class="hover:text-amber-400 transition-colors">دورة عمل النظام</a></li>
                            <li><a href="#interactive-demo" class="hover:text-amber-400 transition-colors">المحاكي التفاعلي الحي</a></li>
                            <li><a href="#features" class="hover:text-amber-400 transition-colors">المزايا التقنية</a></li>
                            <li><a href="#roi-calculator" class="hover:text-amber-400 transition-colors">حاسبة العائد والوفر</a></li>
                            <li><a href="#faqs" class="hover:text-amber-400 transition-colors">الأسئلة الشائعة</a></li>
                        </ul>
                    </div>

                    <!-- Col 3: Portal Access -->
                    <div class="space-y-3">
                        <h5 class="text-xs font-bold text-white uppercase tracking-wider">بوابات النظام</h5>
                        <ul class="space-y-2 text-xs">
                            <li><Link :href="route('login')" class="hover:text-amber-400 transition-colors">دخول إدارة المطعم (Admin)</Link></li>
                            <li><Link :href="route('login')" class="hover:text-amber-400 transition-colors">دخول طاقم المطبخ والخدمة (KDS Staff)</Link></li>
                            <li><Link :href="route('login')" class="hover:text-amber-400 transition-colors">بوابة مالك المنصة (Super Admin)</Link></li>
                            <li><button @click="isDemoModalOpen = true" class="text-amber-400 hover:underline">طلب فتح حساب مطعم جديد</button></li>
                        </ul>
                    </div>

                </div>

                <div class="pt-8 border-t border-white/5 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-500">
                    <div>
                        جميع الحقوق محفوظة لمنصة تدلّل © {{ new Date().getFullYear() }} • الحل الرقمي المتكامل للضيافة الذكية.
                    </div>
                    <div class="flex items-center gap-6">
                        <span class="hover:text-slate-400 transition-colors">Laravel 12 & Vue 3 Composition</span>
                        <span class="hover:text-slate-400 transition-colors">WebSockets Powered</span>
                    </div>
                </div>
            </div>
        </footer>

    </div>
</template>

<style scoped>
@keyframes subtleBounce {
    0%, 100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-4px);
    }
}

.animate-bounce-subtle {
    animation: subtleBounce 2.5s ease-in-out infinite;
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(16px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-slide-up {
    animation: slideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

.animate-fade-in {
    animation: fadeIn 0.3s ease-out forwards;
}
</style>
