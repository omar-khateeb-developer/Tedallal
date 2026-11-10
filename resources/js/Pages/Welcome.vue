<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { translations, type Locale } from '@/locales/landing';
import { useTheme } from '@/composables/useTheme';
import TedallalHeroLogo from '@/Components/TedallalHeroLogo.vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';

interface Props {
    canLogin?: boolean;
    canRegister?: boolean;
    detectedCountry?: string | null;
}

const props = withDefaults(defineProps<Props>(), {
    canLogin: true,
    canRegister: true,
    detectedCountry: null,
});

// =========================================================================
// 1. Theme System (Dark Obsidian & Royal Gold / Pristine Alabaster Light)
// =========================================================================
const { isDark, toggleTheme, setTheme, initTheme } = useTheme();

// =========================================================================
// 2. Bilingual System (Arabic Default & English)
// =========================================================================
const currentLocale = ref<Locale>('ar');
const t = computed(() => translations[currentLocale.value]);

function toggleLocale() {
    setLocale(currentLocale.value === 'ar' ? 'en' : 'ar');
}

function setLocale(loc: Locale) {
    currentLocale.value = loc;
    try {
        localStorage.setItem('tedallal_locale', loc);
    } catch {}
}

// =========================================================================
// 3. Arab Multi-Currency & Geolocation System
// =========================================================================
export interface CountryCurrencyConfig {
    code: string;
    countryName: { ar: string; en: string };
    currencyName: { ar: string; en: string };
    symbol: { ar: string; en: string };
    flag: string;
    rate: number;
    decimals: number;
}

const supportedArabCurrencies: Record<string, CountryCurrencyConfig> = {
    SY: {
        code: 'SY',
        countryName: { ar: 'الجمهورية العربية السورية', en: 'Syria' },
        currencyName: { ar: 'ليرة سورية', en: 'Syrian Pound' },
        symbol: { ar: 'ل.س', en: 'SYP' },
        flag: '🇸🇾',
        rate: 1.0,
        decimals: 0,
    },
    SA: {
        code: 'SA',
        countryName: { ar: 'المملكة العربية السعودية', en: 'Saudi Arabia' },
        currencyName: { ar: 'ريال سعودي', en: 'Saudi Riyal' },
        symbol: { ar: 'ر.س', en: 'SAR' },
        flag: '🇸🇦',
        rate: 1 / 3800,
        decimals: 0,
    },
    AE: {
        code: 'AE',
        countryName: { ar: 'الإمارات العربية المتحدة', en: 'United Arab Emirates' },
        currencyName: { ar: 'درهم إماراتي', en: 'UAE Dirham' },
        symbol: { ar: 'د.إ', en: 'AED' },
        flag: '🇦🇪',
        rate: 1 / 3800,
        decimals: 0,
    },
    KW: {
        code: 'KW',
        countryName: { ar: 'الكويت', en: 'Kuwait' },
        currencyName: { ar: 'دينار كويتي', en: 'Kuwaiti Dinar' },
        symbol: { ar: 'د.ك', en: 'KWD' },
        flag: '🇰🇼',
        rate: 1 / 46000,
        decimals: 2,
    },
    QA: {
        code: 'QA',
        countryName: { ar: 'قطر', en: 'Qatar' },
        currencyName: { ar: 'ريال قطري', en: 'Qatari Riyal' },
        symbol: { ar: 'ر.ق', en: 'QAR' },
        flag: '🇶🇦',
        rate: 1 / 3800,
        decimals: 0,
    },
    BH: {
        code: 'BH',
        countryName: { ar: 'البحرين', en: 'Bahrain' },
        currencyName: { ar: 'دينار بحريني', en: 'Bahraini Dinar' },
        symbol: { ar: 'د.ب', en: 'BHD' },
        flag: '🇧🇭',
        rate: 1 / 38000,
        decimals: 2,
    },
    OM: {
        code: 'OM',
        countryName: { ar: 'سلطنة عمان', en: 'Oman' },
        currencyName: { ar: 'ريال عماني', en: 'Omani Rial' },
        symbol: { ar: 'ر.ع', en: 'OMR' },
        flag: '🇴🇲',
        rate: 1 / 38000,
        decimals: 2,
    },
    JO: {
        code: 'JO',
        countryName: { ar: 'الأردن', en: 'Jordan' },
        currencyName: { ar: 'دينار أردني', en: 'Jordanian Dinar' },
        symbol: { ar: 'د.أ', en: 'JOD' },
        flag: '🇯🇴',
        rate: 1 / 20000,
        decimals: 1,
    },
    EG: {
        code: 'EG',
        countryName: { ar: 'مصر', en: 'Egypt' },
        currencyName: { ar: 'جنيه مصري', en: 'Egyptian Pound' },
        symbol: { ar: 'ج.م', en: 'EGP' },
        flag: '🇪🇬',
        rate: 1 / 300,
        decimals: 0,
    },
    IQ: {
        code: 'IQ',
        countryName: { ar: 'العراق', en: 'Iraq' },
        currencyName: { ar: 'دينار عراقي', en: 'Iraqi Dinar' },
        symbol: { ar: 'د.ع', en: 'IQD' },
        flag: '🇮🇶',
        rate: 1 / 11,
        decimals: 0,
    },
};

const currentCountryCode = ref('SY');
const isCurrencyMenuOpen = ref(false);

const currentCurrency = computed(() => {
    return supportedArabCurrencies[currentCountryCode.value] || supportedArabCurrencies.SY;
});

const currentCurrencySymbol = computed(() => {
    return currentCurrency.value.symbol[currentLocale.value];
});

function setCurrency(code: string) {
    if (supportedArabCurrencies[code]) {
        currentCountryCode.value = code;
        try {
            localStorage.setItem('tedallal_currency', code);
        } catch {}
        demoForm.value.country = supportedArabCurrencies[code].countryName[currentLocale.value];
        isCurrencyMenuOpen.value = false;
    }
}

function formatPrice(amountInSyp: number, forceDecimals?: number): string {
    const rate = currentCurrency.value.rate;
    const dec = forceDecimals !== undefined ? forceDecimals : currentCurrency.value.decimals;
    const val = amountInSyp * rate;
    if (dec === 0) {
        return Math.round(val).toLocaleString('en-US');
    }
    return val.toLocaleString('en-US', { minimumFractionDigits: dec, maximumFractionDigits: dec });
}

// =========================================================================
// 4. Restoria Grand Culinary Showcase (Interactive Haute Cuisine)
// =========================================================================
const activeCulinaryCategory = ref<'grills' | 'appetizers' | 'desserts' | 'drinks'>('grills');

interface RestoriaDish {
    id: number;
    name: string;
    category: 'grills' | 'appetizers' | 'desserts' | 'drinks';
    price: number;
    description: string;
    image: string;
    badge: { label: string; icon: string; style: string };
    prepTime: string;
}

const restoriaDishes = computed<RestoriaDish[]>(() => [
    {
        id: 1,
        name: currentLocale.value === 'ar' ? 'كباب حلبي فاخر بالكرز الطبيعي' : 'Aleppo Cherry Glazed Kebab',
        category: 'grills',
        price: 85000,
        description: currentLocale.value === 'ar'
            ? 'لحم ضأن بلدي مفروم على السيخ، مُتبل بالكرز الطبيعي وصنوبر محمص وخلطة البهارات الحلبية الأصيلة.'
            : 'Prime lamb skewers glazed with mountain cherries, roasted pine nuts & heritage spices.',
        image: '/storage/menu/grills.jpg',
        badge: { label: currentLocale.value === 'ar' ? 'توصية الشيف' : 'Chef Signature', icon: '⭐', style: 'bg-gradient-to-r from-amber-500 to-amber-600 text-stone-950 font-black' },
        prepTime: '14 دقيقة',
    },
    {
        id: 2,
        name: currentLocale.value === 'ar' ? 'أوصال لحم عجل بلدي مشوي' : 'Charcoal Grilled Veal Skewers',
        category: 'grills',
        price: 75000,
        description: currentLocale.value === 'ar'
            ? 'قطع لحم عجل طرية منقوعة بزيت الزيتون البكر وإكليل الجبل، مشوية على الفحم الطبيعي الهادئ.'
            : 'Tender veal cubes infused with virgin olive oil & fresh rosemary, charcoal grilled.',
        image: '/storage/menu/aleppo-grills.jpg',
        badge: { label: currentLocale.value === 'ar' ? 'الأكثر طلباً' : 'Top Seller', icon: '👑', style: 'bg-gradient-to-r from-amber-600 to-orange-500 text-white font-bold' },
        prepTime: '12 دقيقة',
    },
    {
        id: 3,
        name: currentLocale.value === 'ar' ? 'كبة مشوية حلبية بالجوز والرمان' : 'Aleppo Grilled Kibbeh with Walnuts',
        category: 'grills',
        price: 55000,
        description: currentLocale.value === 'ar'
            ? 'أقراص الكبة الحلبية المحشوة باللحم المفروم والدهن البلدي ودبس الرمان وحبات الجوز المحمص.'
            : 'Bulgur shell packed with minced lamb, pomegranate molasses & toasted walnuts.',
        image: '/storage/menu/aleppo-kibbeh.jpg',
        badge: { label: currentLocale.value === 'ar' ? 'تراثي فاخر' : 'Heritage Special', icon: '🏛️', style: 'bg-stone-800 text-amber-400 font-bold border border-amber-500/30' },
        prepTime: '15 دقيقة',
    },
    {
        id: 4,
        name: currentLocale.value === 'ar' ? 'مازة ومقبلات مشكلة شهباء' : 'Grand Aleppo Mezza Platter',
        category: 'appetizers',
        price: 42000,
        description: currentLocale.value === 'ar'
            ? 'تشكيلة راقية من متبل الباذنجان المدخن، حمص بيروتي، يلنجي بدبس الرمان، ومحمرة بالجوز.'
            : 'Smoked aubergine moutabal, Beirut hummus, stuffed vine leaves & spicy walnut dip.',
        image: '/storage/menu/aleppo-appetizers.jpg',
        badge: { label: currentLocale.value === 'ar' ? 'نباتي / صحي' : 'Vegan Choice', icon: '🥗', style: 'bg-emerald-600 text-white font-bold' },
        prepTime: '5 دقائق',
    },
    {
        id: 5,
        name: currentLocale.value === 'ar' ? 'كنافة نابلسية ذهبية بالجبن الفاخر' : 'Hot Golden Nabulsi Kunafa',
        category: 'desserts',
        price: 38000,
        description: currentLocale.value === 'ar'
            ? 'كنافة ذهبية مقرمشة محشوة بالجبن العكاوي الذائب، مُزينة بالفستق الحلبي والقطر الخفيف المعطر.'
            : 'Crisp golden pastry layered with melted Akkawi cheese, pistachios & fragrant syrup.',
        image: '/storage/menu/aleppo-desserts.jpg',
        badge: { label: currentLocale.value === 'ar' ? 'ساخن ومقرمش' : 'Served Warm', icon: '🔥', style: 'bg-amber-500 text-stone-950 font-black' },
        prepTime: '8 دقائق',
    },
    {
        id: 6,
        name: currentLocale.value === 'ar' ? 'عصير رمان طبيعي مثلج 100%' : '100% Fresh Ruby Pomegranate',
        category: 'drinks',
        price: 22000,
        description: currentLocale.value === 'ar'
            ? 'عصير رمان طازج معصور على البارد بدون سكر مضاف، يُقدم مع حبات الرمان الطبيعية والنعناع.'
            : 'Cold-pressed ruby pomegranates, pure & unsweetened, served with mint & seeds.',
        image: '/storage/menu/drinks.jpg',
        badge: { label: currentLocale.value === 'ar' ? 'طبيعي طازج' : '100% Pure', icon: '🍹', style: 'bg-rose-600 text-white font-bold' },
        prepTime: '3 دقائق',
    },
]);

const visibleDishes = computed(() => {
    return restoriaDishes.value.filter(d => d.category === activeCulinaryCategory.value);
});

// Interactive Showcase Cart Simulator
const simulatedCart = ref<Record<number, number>>({ 1: 1, 4: 1 });
const lastAddedName = ref<string | null>(null);

function addDishToSimulatedCart(dish: RestoriaDish) {
    simulatedCart.value[dish.id] = (simulatedCart.value[dish.id] || 0) + 1;
    lastAddedName.value = dish.name;
    setTimeout(() => {
        lastAddedName.value = null;
    }, 2500);
}

function removeDishFromSimulatedCart(dishId: number) {
    if (simulatedCart.value[dishId] && simulatedCart.value[dishId] > 0) {
        simulatedCart.value[dishId]--;
        if (simulatedCart.value[dishId] === 0) {
            delete simulatedCart.value[dishId];
        }
    }
}

const simulatedCartCount = computed(() => {
    return Object.values(simulatedCart.value).reduce((a, b) => a + b, 0);
});

const simulatedCartTotalSyp = computed(() => {
    let sum = 0;
    for (const [idStr, qty] of Object.entries(simulatedCart.value)) {
        const dish = restoriaDishes.value.find(d => d.id === Number(idStr));
        if (dish) sum += dish.price * qty;
    }
    return sum;
});

// =========================================================================
// 5. Floor-to-Kitchen Live Bridge State (Tabs: QR Scan vs Chef KDS)
// =========================================================================
const activeBridgeTab = ref<'dining' | 'kitchen'>('dining');

// =========================================================================
// 6. Interactive Bento & Operating Mode
// =========================================================================
const selectedOperatingMode = ref<'postpaid' | 'prepaid'>('postpaid');
const luckyWheelSpinning = ref(false);
const luckyWheelWonVoucher = ref<string | null>('VIP-WELCOME-15%');

function spinLuckyWheel() {
    if (luckyWheelSpinning.value) return;
    luckyWheelSpinning.value = true;
    luckyWheelWonVoucher.value = null;
    setTimeout(() => {
        luckyWheelSpinning.value = false;
        luckyWheelWonVoucher.value = 'ROYAL-DINE-20%';
    }, 1400);
}

// =========================================================================
// 7. Single-Slider Commission Savings Calculator (ROI)
// =========================================================================
const monthlySalesSyp = ref(60000000); // 60 Million SYP base

const deliveryCommissionLost = computed(() => {
    return monthlySalesSyp.value * 0.20;
});

const tedallalMonthlyFee = computed(() => {
    return 350000;
});

const netProfitsPreserved = computed(() => {
    return deliveryCommissionLost.value - tedallalMonthlyFee.value;
});

// =========================================================================
// 8. Progressive Disclosure Accordion (FAQs)
// =========================================================================
const openFaqIndex = ref<number | null>(0);
function toggleFaq(index: number) {
    openFaqIndex.value = openFaqIndex.value === index ? null : index;
}

const faqItems = computed(() => [
    {
        q: currentLocale.value === 'ar' ? 'هل يحتاج رواد المطعم أو طاقم المطبخ إلى تحميل أي تطبيق؟' : 'Do diners or kitchen staff need to install any apps?',
        a: currentLocale.value === 'ar'
            ? 'إطلاقاً! منصة تدلل تعمل بتقنية الويب السحابية الفائقة (PWA). يمسح الزبون رمز الـ QR لتفتح القائمة الملكية في المتصفح فوراً، ويفتح الشيف شاشة المطبخ KDS على أي تابلت أو شاشة بضغطة زر واحدة.'
            : 'Never! Tedallal is an ultra-fast Progressive Web App (PWA). Diners scan the QR to open the luxury menu in their mobile browser instantly, and chefs access the KDS queue from any tablet.',
    },
    {
        q: currentLocale.value === 'ar' ? 'هل تقتطع منصة تدلل أي نسبة أو عمولة من مبيعات المطعم؟' : 'Does Tedallal take any commission on restaurant sales?',
        a: currentLocale.value === 'ar'
            ? 'صفر عمولة على الإطلاق! أرباحك وعرق جبينك ملك لك وحدك 100%. اشتراك تدلل شهري أو سنوي ثابت بدون أي نسبة استقطاع من الفواتير مهما بلغت مبيعاتك.'
            : '0% commission! Your hard-earned revenue is 100% yours. Tedallal charges a predictable flat subscription with zero cut from your order receipts.',
    },
    {
        q: currentLocale.value === 'ar' ? 'ماذا يحدث إذا انقطع اتصال الإنترنت في الصالة فجأة؟' : 'What happens if the internet disconnects briefly?',
        a: currentLocale.value === 'ar'
            ? 'تتضمن المنصة خوارزمية ذكية لاحتواء انقطاع الشبكة (Offline Recovery)، حيث تحفظ حالة الجلسة وتستأنف الربط اللحظي مع الـ WebSockets فور استقرار الإنترنت دون فقدان أي طلب.'
            : 'The platform incorporates resilient offline recovery, preserving active sessions and seamlessly re-establishing WebSocket sync upon reconnection with zero order loss.',
    },
    {
        q: currentLocale.value === 'ar' ? 'هل تدعم المنصة طباعة الإيصالات الحرارية 80mm وتقارير الإغلاق Z؟' : 'Does it support 80mm thermal receipts and Z-Reports?',
        a: currentLocale.value === 'ar'
            ? 'نعم بكل تأكيد! تم تصميم قوالب حرارية مخصصة قياس 80mm لفواتير الزبائن وتقارير نهاية الوردية (Z-Report) مع تفصيل الإيرادات النقدية والشبكة، مع إمكانية مشاركة التقرير عبر واتساب بلمسة واحدة.'
            : 'Yes! Tedallal provides bespoke 80mm thermal templates for dining receipts and shift closing Z-Reports with cash/card breakdowns and 1-click WhatsApp dispatch.',
    },
    {
        q: currentLocale.value === 'ar' ? 'كم يستغرق إعداد وتفعيل المنظومة داخل المطعم؟' : 'How long does onboarding and deployment take?',
        a: currentLocale.value === 'ar'
            ? 'أقل من 30 دقيقة! واجهتنا مصممة ومختبرة لتكون بديهية للغاية، ولا تحتاج إلى أي تدريب تقني معقد للمضيفين أو طاقم المطبخ.'
            : 'Under 30 minutes! Our interface is crafted to be as intuitive as everyday messaging apps, requiring zero technical training for your service team.',
    },
]);

// =========================================================================
// 9. Consultation Demo Request Modal
// =========================================================================
const isDemoModalOpen = ref(false);
const demoForm = ref({
    restaurantName: '',
    contactPerson: '',
    phone: '',
    country: 'الجمهورية العربية السورية',
    tablesCount: '15-35',
});
const isSubmitted = ref(false);

// =========================================================================
// 8. INTERCONNECTED LUXURY SIMULATOR STATE
// =========================================================================
const interactiveActiveDish = ref<string | null>(null);
const interactiveRevenue = ref(12450);
const interactiveRevenueDiff = ref<number | null>(null);
const interactiveFlashActive = ref(false);
const interactiveTickets = ref([
    { id: 102, table: 'طاولة VIP #1', dish: 'سالمون مشوي مع زبدة الشبت', time: 'منذ 3 دقائق', status: 'prep', statusText: 'قيد التحضير ⏳' },
    { id: 101, table: 'طاولة #3', dish: 'سوفليه الشوكولاتة الساخنة', time: 'منذ 8 دقائق', status: 'ready', statusText: 'مكتمل وجاهز للتقديم ✓' }
]);
let ticketIdCounter = 103;

function triggerLiveSimulationOrder(dishName: string, price: number) {
    interactiveActiveDish.value = dishName;
    interactiveRevenue.value += price;
    interactiveRevenueDiff.value = price;
    interactiveFlashActive.value = true;

    // Play subtle haute cuisine chime
    try {
        const audioCtx = new (window.AudioContext || (window as any).webkitAudioContext)();
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(587.33, audioCtx.currentTime); // D5
        osc.frequency.exponentialRampToValueAtTime(880, audioCtx.currentTime + 0.15); // A5
        gain.gain.setValueAtTime(0.06, audioCtx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.35);
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.start();
        osc.stop(audioCtx.currentTime + 0.35);
    } catch (e) {}

    // Add new ticket to KDS
    interactiveTickets.value.unshift({
        id: ticketIdCounter++,
        table: 'طاولة VIP #4',
        dish: dishName,
        time: 'ورد للتو (مباشر)',
        status: 'new',
        statusText: 'طلب جديد 🔥'
    });

    if (interactiveTickets.value.length > 5) {
        interactiveTickets.value.pop();
    }

    setTimeout(() => {
        interactiveFlashActive.value = false;
    }, 900);

    setTimeout(() => {
        interactiveRevenueDiff.value = null;
    }, 2200);
}

function submitDemoRequest() {
    isSubmitted.value = true;
    setTimeout(() => {
        isDemoModalOpen.value = false;
        isSubmitted.value = false;
        demoForm.value = {
            restaurantName: '',
            contactPerson: '',
            phone: '',
            country: currentCurrency.value.countryName[currentLocale.value],
            tablesCount: '15-35',
        };
    }, 2400);
}

function handleGlobalClick(e: MouseEvent) {
    const target = e.target as HTMLElement;
    if (!target.closest('[data-currency-dropdown]')) {
        isCurrencyMenuOpen.value = false;
    }
}

onMounted(() => {
    initTheme('dark');
    try {
        const savedLocale = localStorage.getItem('tedallal_locale') as Locale | null;
        if (savedLocale === 'ar' || savedLocale === 'en') {
            currentLocale.value = savedLocale;
        } else {
            currentLocale.value = 'ar';
        }
        const savedCurr = localStorage.getItem('tedallal_currency');
        if (savedCurr && supportedArabCurrencies[savedCurr]) {
            currentCountryCode.value = savedCurr;
        } else if (props.detectedCountry && supportedArabCurrencies[props.detectedCountry.toUpperCase()]) {
            currentCountryCode.value = props.detectedCountry.toUpperCase();
        }
    } catch {}
    window.addEventListener('click', handleGlobalClick);
});

onUnmounted(() => {
    window.removeEventListener('click', handleGlobalClick);
});
</script>

<template>
    <Head>
        <title>{{ currentLocale === 'ar' ? 'تدلّـل | المنظومة الملكية الفاخرة لإدارة المطاعم وقوائم QR' : 'Tedallal | The Luxury Restaurant OS & Smart QR Suite' }}</title>
        <meta
            name="description"
            :content="currentLocale === 'ar'
                ? 'حوّل مطعمك إلى تجربة ملكية لا تُنسى: منيو QR رقمي تفاعلي، شاشات مطبخ KDS فورية، عجلة ولاء الزبائن، وتقارير وردية حرارية بدون عمولات مجحفة.'
                : 'Elevate your restaurant to a royal dining experience: interactive luxury QR menus, real-time KDS kitchen screens, and end-of-shift Z-reports with zero commission.'"
        />
    </Head>

    <div
        :dir="currentLocale === 'ar' ? 'rtl' : 'ltr'"
        :class="[
            'font-sans min-h-screen selection:bg-amber-400 selection:text-black antialiased overflow-x-hidden relative transition-colors duration-500',
            isDark ? 'dark-mode-theme bg-[#08080A] text-stone-100' : 'light-mode-theme bg-[#FAF8F5] text-stone-900'
        ]"
    >
        <!-- Background Wallpaper: Islamic Trefoil Arabesque Texture & Warm Ambient Spotlight -->
        <div class="fixed inset-0 pointer-events-none z-0">
            <div
                class="absolute inset-0 transition-opacity duration-700"
                :class="isDark ? 'opacity-[0.035] mix-blend-overlay filter grayscale' : 'opacity-[0.045] mix-blend-multiply filter contrast-125'"
                style="background-image: url('/images/luxury-restaurant-hero-bg.jpg'); background-size: cover; background-position: center;"
            ></div>
            <!-- Radial Warm Gold Glows (Calm, Subtle Luxury Ambiance) -->
            <div class="absolute -top-40 left-1/2 -translate-x-1/2 w-[1000px] h-[450px] rounded-full pointer-events-none transition-all duration-700"
                 :class="isDark ? 'bg-[radial-gradient(ellipse_at_top,rgba(212,175,55,0.06)_0%,transparent_70%)] blur-3xl' : 'bg-[radial-gradient(ellipse_at_top,rgba(180,140,60,0.03)_0%,transparent_70%)] blur-3xl'"
            ></div>
            <div class="absolute top-1/3 -right-60 w-[600px] h-[600px] rounded-full pointer-events-none"
                 :class="isDark ? 'bg-[radial-gradient(circle,rgba(212,175,55,0.03)_0%,transparent_65%)] blur-3xl' : 'bg-[radial-gradient(circle,rgba(197,160,89,0.02)_0%,transparent_65%)] blur-3xl'"
            ></div>
        </div>

        <!-- ========================================================================= -->
        <!-- 1. ROYAL GLASSMORPHISM NAVIGATION -->
        <!-- ========================================================================= -->
        <header
            class="sticky top-0 z-50 backdrop-blur-2xl border-b transition-all duration-300"
            :class="isDark ? 'bg-[#08080A]/90 border-[rgba(226,196,133,0.18)] shadow-[0_10px_35px_rgba(0,0,0,0.95)]' : 'bg-[#FAF8F5]/92 border-b border-[#C5A059]/30 shadow-[0_8px_30px_rgba(180,140,60,0.09)]'"
        >
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-20">
                    <!-- Brand Identity (Animated Royal Emblem + Tedallal Luxury Typography) -->
                    <Link href="/" class="flex items-center gap-3 group">
                        <div class="relative w-12 h-12 rounded-2xl bg-gradient-to-br from-[#BF953F] via-[#FCF6BA] to-[#B38728] p-0.5 shadow-sm group-hover:scale-105 transition-transform duration-300">
                            <div class="w-full h-full rounded-[14px] flex items-center justify-center p-1 overflow-hidden" :class="isDark ? 'bg-[#0C0C0E]' : 'bg-white'">
                                <ApplicationLogo variant="emblem" :animated="true" class="w-full h-full" />
                            </div>
                        </div>
                        <div class="flex flex-col">
                            <div class="flex items-center gap-1.5">
                                <span class="text-xl sm:text-2xl font-black tracking-tight" :class="isDark ? 'text-white' : 'text-stone-900'">
                                    {{ currentLocale === 'ar' ? 'تَدلَّـل' : 'Tedallal' }}
                                </span>
                                <span class="text-[9px] font-black uppercase tracking-wider px-2 py-0.5 rounded-md bg-amber-500/15 text-amber-400 border border-amber-500/30">
                                    ROYAL
                                </span>
                            </div>
                            <span class="text-[10px] font-bold text-amber-400/80 tracking-widest uppercase">
                                TEDALLAL
                            </span>
                        </div>
                    </Link>

                    <!-- Navigation Links -->
                    <nav class="hidden lg:flex items-center gap-7 text-xs xl:text-sm font-bold" :class="isDark ? 'text-stone-300' : 'text-stone-700'">
                        <a href="#platform-mockups" class="hover:text-amber-400 transition-colors">
                            {{ currentLocale === 'ar' ? 'للمطاعم' : 'For Restaurants' }}
                        </a>
                        <a href="#platform-mockups" class="hover:text-amber-400 transition-colors">
                            {{ currentLocale === 'ar' ? 'المزايا' : 'Features' }}
                        </a>
                        <a href="#showcase" class="hover:text-amber-400 transition-colors">
                            {{ currentLocale === 'ar' ? 'القوائم الذكية' : 'Digital Menus' }}
                        </a>
                        <a href="#floor-to-kitchen" class="hover:text-amber-400 transition-colors flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-amber-400 animate-ping"></span>
                            {{ currentLocale === 'ar' ? 'من الصالة للمطبخ' : 'Floor to Kitchen' }}
                        </a>
                        <a href="#bento" class="hover:text-amber-400 transition-colors">
                            {{ currentLocale === 'ar' ? 'رؤوس الخدمة' : 'Operations' }}
                        </a>
                        <a href="#roi" class="hover:text-amber-400 transition-colors">
                            {{ currentLocale === 'ar' ? 'الضرائب والعمولات' : 'ROI & Profit' }}
                        </a>
                        <a href="#faqs" class="hover:text-amber-400 transition-colors">
                            {{ currentLocale === 'ar' ? 'الأسئلة الشائعة' : 'FAQs' }}
                        </a>
                        <a
                            href="/presentation"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="hover:text-amber-400 transition-colors flex items-center gap-1.5 font-bold"
                            :class="isDark ? 'text-amber-300' : 'text-[#8C6010]'"
                            :title="currentLocale === 'ar' ? 'عرض الملف التعريفي الشامل لملاك المطاعم' : 'Executive Pitch Deck'"
                        >
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                            <span>{{ currentLocale === 'ar' ? 'الملف التعريفي' : 'Pitch Deck' }}</span>
                            <span class="text-[10px] opacity-70">↗</span>
                        </a>
                    </nav>

                    <!-- Header Utility Tools -->
                    <div class="flex items-center gap-2 sm:gap-3">
                        <!-- Arab Currency Selector -->
                        <div class="relative" data-currency-dropdown>
                            <button
                                type="button"
                                class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold border transition-colors shadow-2xs"
                                :class="isDark ? 'glass-ultra text-stone-200 hover:border-amber-500/50' : 'bg-white border-stone-200 text-stone-700 hover:border-amber-400'"
                                @click="isCurrencyMenuOpen = !isCurrencyMenuOpen"
                            >
                                <span>{{ currentCurrency.flag }}</span>
                                <span class="hidden sm:inline font-black">{{ currentCurrencySymbol }}</span>
                                <svg class="w-3.5 h-3.5 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>

                            <div
                                v-if="isCurrencyMenuOpen"
                                class="absolute end-0 mt-2 w-56 rounded-2xl border shadow-2xl py-2 z-50 backdrop-blur-2xl max-h-72 overflow-y-auto"
                                :class="isDark ? 'bg-[#0c1222] border-[rgba(226,196,133,0.18)] text-stone-200' : 'bg-white border-stone-200 text-stone-800'"
                            >
                                <div class="px-3 py-1.5 text-[11px] font-black uppercase tracking-wider text-amber-500 border-b border-stone-700/30">
                                    {{ currentLocale === 'ar' ? 'اختر عملة بلدك' : 'Select Currency' }}
                                </div>
                                <button
                                    v-for="curr in supportedArabCurrencies"
                                    :key="curr.code"
                                    type="button"
                                    class="w-full flex items-center justify-between px-3 py-2 text-xs hover:bg-amber-500/10 transition-colors text-right"
                                    :class="currentCountryCode === curr.code ? 'font-black text-amber-400' : ''"
                                    @click="setCurrency(curr.code)"
                                >
                                    <span class="flex items-center gap-2">
                                        <span>{{ curr.flag }}</span>
                                        <span>{{ curr.countryName[currentLocale] }}</span>
                                    </span>
                                    <span class="text-stone-400 font-bold">{{ curr.symbol[currentLocale] }}</span>
                                </button>
                            </div>
                        </div>

                        <!-- Language Switcher -->
                        <button
                            type="button"
                            class="px-2.5 py-1.5 rounded-xl text-xs font-bold border transition-colors shadow-2xs"
                            :class="isDark ? 'glass-ultra text-stone-200 hover:border-amber-500/50' : 'bg-white border-stone-200 text-stone-700 hover:border-amber-400'"
                            @click="toggleLocale"
                            :title="currentLocale === 'ar' ? 'Switch to English' : 'التحويل إلى العربية'"
                        >
                            {{ currentLocale === 'ar' ? 'EN' : 'عربي' }}
                        </button>

                        <!-- Theme Toggle -->
                        <button
                            type="button"
                            class="p-2 rounded-xl text-xs border transition-colors shadow-2xs"
                            :class="isDark ? 'glass-ultra text-amber-400 hover:border-amber-500/50' : 'bg-white border-stone-200 text-stone-700 hover:border-amber-400'"
                            @click="toggleTheme"
                            title="تبديل المظهر"
                        >
                            <svg v-if="isDark" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                            </svg>
                            <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                            </svg>
                        </button>

                        <!-- Primary VIP Booking CTA (Pill Button, Refined Luxury without Glare) -->
                        <button
                            type="button"
                            class="hidden sm:inline-flex items-center gap-2 px-6 py-2.5 rounded-full bg-gradient-to-r from-[#BF953F] via-[#E8C265] to-[#B38728] text-stone-950 font-black text-xs sm:text-sm shadow-sm hover:shadow-md hover:shadow-amber-500/15 hover:scale-105 active:scale-95 transition-all"
                            @click="isDemoModalOpen = true"
                        >
                            <span>{{ currentLocale === 'ar' ? 'جرّب الآن' : 'Get Started' }}</span>
                            <span class="text-stone-950">✦</span>
                        </button>

                        <!-- Login Link -->
                        <Link
                            v-if="canLogin"
                            href="/login"
                            class="px-3.5 py-1.5 rounded-xl font-bold text-xs sm:text-sm border transition-all"
                            :class="isDark ? 'border-amber-500/40 text-amber-400 hover:bg-amber-500/10' : 'border-stone-300 text-stone-800 hover:bg-stone-100'"
                        >
                            {{ currentLocale === 'ar' ? 'تسجيل الدخول' : 'Sign In' }}
                        </Link>
                    </div>
                </div>
            </div>
        </header>

        <!-- ========================================================================= -->
        <!-- 2. EXECUTIVE CHEF LUXURY HERO STAGE (Matching High-End Mockup) -->
        <!-- ========================================================================= -->
        <section class="relative pt-4 pb-10 md:pt-8 md:pb-16 overflow-hidden z-10">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Widescreen Framing Card with Executive Chef Background -->
                <div :class="isDark ? 'border-[rgba(226,196,133,0.18)] bg-[#0C0C0E] shadow-[0_20px_60px_rgba(0,0,0,0.85)]' : 'border-stone-200/90 bg-[#FBF9F5] shadow-[0_15px_45px_rgba(28,25,23,0.06)]'" class="relative rounded-[2.5rem] overflow-hidden border min-h-[500px] lg:min-h-[620px] flex flex-col lg:flex-row items-center transition-colors">
                    <!-- High-Resolution Executive Chef Plating Image (Desktop) -->
                    <img
                        src="/images/tedallal-chef-hero.jpg"
                        alt="Executive Chef Plating Exquisite Cuisine"
                        class="hidden lg:block absolute inset-0 w-full h-full object-cover object-[16%_center] transform scale-[1.03] transition-transform duration-1000"
                    />

                    <!-- Atmospheric Cinematic Gradients for Desktop (Gentle, Natural Contrast - No Overly Glowing Wash) -->
                    <div
                        class="hidden lg:block absolute inset-0 pointer-events-none"
                        :class="isDark
                            ? (currentLocale === 'ar' ? 'bg-gradient-to-l from-[#0C0C0E]/90 via-[#0C0C0E]/55 to-transparent' : 'bg-gradient-to-r from-transparent via-[#0C0C0E]/55 to-[#0C0C0E]/90')
                            : (currentLocale === 'ar' ? 'bg-gradient-to-l from-stone-900/60 via-stone-900/20 to-transparent' : 'bg-gradient-to-r from-transparent via-stone-900/20 to-stone-900/60')"
                    ></div>
                    <div class="hidden lg:block absolute inset-0 pointer-events-none bg-gradient-to-t from-stone-950/40 via-transparent to-transparent"></div>

                    <!-- Mobile Chef Upper Visual Banner (< lg) -->
                    <div class="block lg:hidden relative w-full h-64 sm:h-80 overflow-hidden">
                        <img
                            src="/images/tedallal-chef-hero.jpg"
                            alt="Executive Chef Plating Exquisite Cuisine"
                            class="w-full h-full object-cover object-[center_28%]"
                        />
                        <div class="absolute inset-0 bg-gradient-to-t from-[#0C0C0E] via-transparent to-black/30"></div>
                        <div class="absolute bottom-3 start-4 flex items-center gap-2 px-3 py-1 rounded-full bg-black/70 border border-amber-500/40 backdrop-blur-md">
                            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                            <span class="text-[11px] font-black text-amber-300">ميشلان ستار • أعلى معايير الضيافة</span>
                        </div>
                    </div>

                    <!-- Floating Glassmorphism Hero Box (Placed on the RIGHT side on Desktop) -->
                    <div
                        class="relative z-10 w-full lg:w-[48%] xl:w-[46%] p-4 sm:p-8 lg:p-12"
                        :class="currentLocale === 'ar' ? 'lg:me-auto lg:ms-6' : 'lg:ms-auto lg:me-6'"
                    >
                        <div :class="isDark ? 'glass-ultra text-white shadow-xl' : 'glass-ultra text-stone-900 shadow-[0_12px_30px_rgba(28,25,23,0.06)]'" class="rounded-3xl p-7 sm:p-10 relative overflow-hidden transition-all duration-300">
                            <!-- Origami Crown Badge & Sub-label -->
                            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full border mb-4" :class="isDark ? 'bg-amber-500/10 border-amber-500/30 text-amber-300' : 'bg-amber-500/10 border-[#C5A059]/30 text-[#7A5208] shadow-2xs'">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 18h18M4 18l2.5-11 4.5 6 3-8 3 8 4.5-6L20 18" />
                                    <circle cx="6.5" cy="7" r="0.75" fill="currentColor" />
                                    <circle cx="14" cy="5" r="0.75" fill="currentColor" />
                                    <circle cx="21.5" cy="7" r="0.75" fill="currentColor" />
                                </svg>
                                <span class="text-xs font-black uppercase tracking-widest">
                                    {{ currentLocale === 'ar' ? 'المنظومة السحابية الفاخرة للضيافة' : 'EXQUISITE HOSPITALITY OS' }}
                                </span>
                            </div>

                            <!-- Main Title (Matching the mockup exact wording) -->
                            <h1 class="text-2xl sm:text-4xl lg:text-5xl font-black leading-tight mb-2 tracking-tight transition-colors" :class="isDark ? 'text-white' : 'text-stone-900'">
                                {{ currentLocale === 'ar' ? 'تَدلَّل - لإدارةٍ راقيةٍ للمطاعم' : 'TEDALLAL - For Exquisite Restaurant Management' }}
                            </h1>

                            <!-- Subtitle / English line -->
                            <p class="text-xs sm:text-sm font-bold tracking-wide mb-3" :class="isDark ? 'text-amber-400' : 'text-[#8C6010]'">
                                TEDALLAL - For Exquisite Restaurant Management
                            </p>

                            <!-- Tagline -->
                            <p class="text-xs sm:text-sm leading-relaxed mb-8 font-medium transition-colors" :class="isDark ? 'text-stone-300' : 'text-stone-700'">
                                {{ currentLocale === 'ar'
                                    ? 'تدلّل • الإدارة الراقية للمطاعم. تجربة طعام ملكية لرواد مطعمك، مع شاشات مطبخ KDS فورية، تحليلات مالية شفافة، و0% عمولات مجحفة.'
                                    : 'Empowering fine-dining establishments with flawless digital QR menus, chef-centric kitchen automation, and full executive financial control.' }}
                            </p>

                            <!-- Golden Action Button (Pill matching mockup, Calm Luxury) -->
                            <div class="flex flex-wrap items-center gap-3">
                                <button
                                    type="button"
                                    class="px-8 py-3.5 rounded-full bg-gradient-to-r from-[#BF953F] via-[#E8C265] to-[#B38728] text-stone-950 font-black text-xs sm:text-sm shadow-sm hover:shadow-md hover:shadow-amber-500/15 hover:scale-105 active:scale-95 transition-all flex items-center gap-2"
                                    @click="isDemoModalOpen = true"
                                >
                                    <span>{{ currentLocale === 'ar' ? 'تدلّل - للمطاعم' : 'Tedallal - For Restaurants' }}</span>
                                    <span class="text-stone-950">✦</span>
                                </button>
                                <a
                                    href="/presentation"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="px-6 py-3.5 rounded-full border border-amber-500/50 hover:border-amber-400 bg-amber-500/10 hover:bg-amber-500/20 text-amber-300 hover:text-amber-200 text-xs sm:text-sm font-black flex items-center gap-2 transition-all shadow-sm hover:shadow-amber-500/15 hover:scale-105 active:scale-95"
                                    :class="!isDark && 'bg-amber-50/90 border-[#C5A059]/60 text-[#7A5208] hover:bg-amber-100'"
                                    :title="currentLocale === 'ar' ? 'عرض الملف التعريفي الشامل لملاك ومستثمري المطاعم' : 'Restaurant Owner Pitch Deck'"
                                >
                                    <svg class="w-4 h-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" />
                                    </svg>
                                    <span>{{ currentLocale === 'ar' ? 'الملف التعريفي لملاك المطاعم' : 'Pitch Deck' }}</span>
                                    <span class="text-xs">↗</span>
                                </a>
                                <a
                                    href="#platform-mockups"
                                    :class="isDark ? 'border-white/20 text-stone-200 hover:bg-white/10' : 'border-stone-300 text-stone-900 bg-white/90 hover:bg-stone-100 shadow-xs'" class="px-5 py-3 rounded-full border text-xs sm:text-sm font-bold transition-colors"
                                >
                                    {{ currentLocale === 'ar' ? 'استكشف الواجهات الحية' : 'Explore Platform' }} ↓
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- 3. TEDALLAL EXQUISITE PLATFORM MOCKUP SHOWCASE (BENTO SUITE) -->
        <!-- ========================================================================= -->
        <section id="platform-mockups" class="relative py-12 lg:py-20 z-10">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Section Header -->
                <div class="text-center max-w-3xl mx-auto mb-10">
                    <span class="inline-flex items-center gap-1.5 px-3.5 py-1 rounded-full text-[11px] font-black tracking-widest uppercase mb-3 border" :class="isDark ? 'bg-amber-500/10 text-amber-400 border-amber-500/30' : 'bg-amber-500/15 text-[#8C6010] border-[#C5A059]/40 shadow-xs'">
                        {{ currentLocale === 'ar' ? 'الواجهات والمكونات الحية' : 'EXECUTIVE HARDWARE & SOFTWARE ECOSYSTEM' }}
                    </span>
                    <h2 class="text-2xl sm:text-3xl lg:text-4xl font-black mb-3" :class="isDark ? 'text-white' : 'text-stone-900'">
                        {{ currentLocale === 'ar' ? 'منظومة تدلّل المتكاملة من الصالة إلى المطبخ والإدارة' : 'Seamless Integration from Dining Room to Kitchen' }}
                    </h2>
                    <div class="flex items-center justify-center gap-3 my-3">
                        <div class="h-px w-12 bg-amber-500/50"></div>
                        <span class="text-amber-400 text-sm">⚜️</span>
                        <div class="h-px w-12 bg-amber-500/50"></div>
                    </div>
                </div>

                <!-- TOP ROW: 3 Large Mockup Cards matching the user's design (LTR order: Phone -> KDS -> Graph) -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6" dir="ltr">

                    <!-- CARD 1: Luxury Digital QR Menu -->
                    <div dir="rtl" :class="isDark ? 'glass-ultra text-white' : 'glass-ultra text-stone-900'" class="rounded-3xl p-5 sm:p-6 flex flex-col justify-between overflow-hidden transition-all duration-500 hover:-translate-y-1.5">
                        <!-- Top Text -->
                        <div class="flex items-start justify-between mb-4">
                            <div>
                                <span class="text-[11px] font-black tracking-widest uppercase text-amber-400/90 block">
                                    Luxury Digital QR Menu
                                </span>
                                <h3 class="text-base sm:text-lg font-black mt-1" :class="isDark ? 'text-white' : 'text-stone-900'">
                                    {{ currentLocale === 'ar' ? 'قائمة طعام رقمية فاخرة (QR)' : 'Interactive Luxury QR Menu' }}
                                </h3>
                            </div>
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-500/15 text-amber-300 border border-amber-500/30">
                                PWA فوري
                            </span>
                        </div>

                        <!-- Image Mockup -->
                        <div class="relative w-full aspect-[4/3] rounded-2xl overflow-hidden border border-white/10 bg-black/60 shadow-inner group-hover:scale-[1.02] transition-transform duration-500">
                            <img
                                src="/images/tedallal-qr-phone-mockup.jpg"
                                alt="Luxury Digital QR Menu"
                                class="w-full h-full object-cover"
                            />
                            <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent pointer-events-none"></div>
                        </div>

                        <!-- Interactive Quick Tap Order Simulator -->
                        <div class="mt-3 p-2.5 rounded-2xl border backdrop-blur-md transition-colors" :class="isDark ? 'bg-black/40 border-white/10' : 'bg-amber-50/70 border-amber-900/15 shadow-2xs'">
                            <span class="text-[10px] font-bold block mb-1.5" :class="isDark ? 'text-amber-300' : 'text-amber-900'">👆 انقر لتجربة الطلب المباشر للمطبخ:</span>
                            <div class="grid grid-cols-2 gap-2">
                                <button
                                    type="button"
                                    @click="triggerLiveSimulationOrder('ستيك واغيو A5', 280)"
                                    :class="isDark ? 'border-amber-500/30 bg-white/5 hover:bg-amber-500/20' : 'border-amber-600/20 bg-stone-50 hover:bg-amber-500/10'" class="p-2 rounded-xl border text-right transition-all flex items-center justify-between group active:scale-95"
                                >
                                    <span class="text-xs font-bold group-hover:text-amber-500" :class="isDark ? 'text-white' : 'text-stone-900'">🥩 ستيك واغيو</span>
                                    <span class="text-[10px] font-mono text-amber-400 font-bold">+280</span>
                                </button>
                                <button
                                    type="button"
                                    @click="triggerLiveSimulationOrder('كافيار إمبريال', 420)"
                                    :class="isDark ? 'border-amber-500/30 bg-white/5 hover:bg-amber-500/20' : 'border-amber-600/20 bg-stone-50 hover:bg-amber-500/10'" class="p-2 rounded-xl border text-right transition-all flex items-center justify-between group active:scale-95"
                                >
                                    <span class="text-xs font-bold group-hover:text-amber-500" :class="isDark ? 'text-white' : 'text-stone-900'">✨ كافيار ملكي</span>
                                    <span class="text-[10px] font-mono text-amber-400 font-bold">+420</span>
                                </button>
                            </div>
                        </div>

                        <!-- Bottom Tagline -->
                        <p :class="isDark ? 'text-stone-400' : 'text-stone-600 font-medium'" class="text-xs mt-3 leading-relaxed transition-colors">
                            {{ currentLocale === 'ar'
                                ? 'رمز QR مذهب على طاولات الرخام يمنح ضيوفك تصفحاً ملكياً فائق السرعة وتحديد التفضيلات بدون تطبيقات.'
                                : 'Guests browse exquisitely formatted dishes, dietary badges, and submit orders directly without app downloads.' }}
                        </p>
                    </div>

                    <!-- CARD 2: Kitchen Display System (KDS) -->
                    <div dir="rtl" :class="isDark ? 'glass-ultra text-white' : 'glass-ultra text-stone-900'" class="rounded-3xl p-5 sm:p-6 flex flex-col justify-between overflow-hidden transition-all duration-500 hover:-translate-y-1.5">
                        <!-- Top Text -->
                        <div class="flex items-start justify-between mb-4">
                            <div>
                                <span class="text-[11px] font-black tracking-widest uppercase text-amber-400/90 block">
                                    Kitchen Display System (KDS)
                                </span>
                                <h3 class="text-base sm:text-lg font-black mt-1" :class="isDark ? 'text-white' : 'text-stone-900'">
                                    {{ currentLocale === 'ar' ? 'شاشات المطبخ الذكية (KDS)' : 'Executive Chef Display System' }}
                                </h3>
                            </div>
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-500/15 text-emerald-400 border border-emerald-500/30 flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                لحظي 0.2s
                            </span>
                        </div>

                        <!-- Image Mockup -->
                        <div class="relative w-full aspect-[4/3] rounded-2xl overflow-hidden border border-white/10 bg-black/60 shadow-inner group-hover:scale-[1.02] transition-transform duration-500">
                            <img
                                src="/images/tedallal-kds-tablet-mockup.jpg"
                                alt="Kitchen Display System KDS"
                                class="w-full h-full object-cover"
                            />
                            <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent pointer-events-none"></div>
                        </div>

                        <!-- Live Kitchen Ticket Stream -->
                        <div class="mt-3 space-y-1.5">
                            <div
                                v-for="ticket in interactiveTickets.slice(0, 2)"
                                :key="ticket.id"
                                class="p-2.5 rounded-xl border flex items-center justify-between transition-all duration-300"
                                :class="ticket.status === 'new' ? (isDark ? 'bg-amber-500/20 border-amber-400/50 flash-active-gold' : 'bg-amber-100 border-amber-400/60 flash-active-gold') : (isDark ? 'bg-black/40 border-white/10' : 'bg-white border-stone-200/90 shadow-2xs')"
                            >
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] font-bold font-mono" :class="isDark ? 'text-amber-300' : 'text-amber-800'">#{{ ticket.id }}</span>
                                    <span class="text-xs font-bold" :class="isDark ? 'text-white' : 'text-stone-900'">{{ ticket.dish }}</span>
                                </div>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded" :class="ticket.status === 'new' ? 'bg-amber-500 text-stone-950 font-black' : 'text-emerald-400 bg-emerald-500/10'">
                                    {{ ticket.statusText }}
                                </span>
                            </div>
                        </div>

                        <!-- Bottom Tagline -->
                        <p :class="isDark ? 'text-stone-400' : 'text-stone-600 font-medium'" class="text-xs mt-3 leading-relaxed transition-colors">
                            {{ currentLocale === 'ar'
                                ? 'شاشات لوحية في كاونتر الطهاة تنظم التذاكر، مؤقتات التحضير، وتنبيهات صوتية فاخرة لمنع تأخير أي طبق.'
                                : 'Kitchen tablets organizing cooking queues, prep timers, and audio cues to ensure Michelin-timed service.' }}
                        </p>
                    </div>

                    <!-- CARD 3: Executive Financial Analytics graph -->
                    <div dir="rtl" :class="isDark ? 'glass-ultra text-white' : 'glass-ultra text-stone-900'" class="rounded-3xl p-5 sm:p-6 flex flex-col justify-between overflow-hidden transition-all duration-500 hover:-translate-y-1.5">
                        <!-- Top Text -->
                        <div class="flex items-start justify-between mb-4">
                            <div>
                                <span class="text-[11px] font-black tracking-widest uppercase text-amber-400/90 block">
                                    Executive Financial Analytics graph
                                </span>
                                <h3 class="text-base sm:text-lg font-black mt-1" :class="isDark ? 'text-white' : 'text-stone-900'">
                                    {{ currentLocale === 'ar' ? 'التحليلات المالية والبيانية' : 'Executive Financial Analytics' }}
                                </h3>
                            </div>
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-500/15 text-amber-300 border border-amber-500/30">
                                0% عمولة
                            </span>
                        </div>

                        <!-- Image Mockup -->
                        <div class="relative w-full aspect-[4/3] rounded-2xl overflow-hidden border border-white/10 bg-black/60 shadow-inner group-hover:scale-[1.02] transition-transform duration-500">
                            <img
                                src="/images/tedallal-analytics-chart-mockup.jpg"
                                alt="Executive Financial Analytics graph"
                                class="w-full h-full object-cover"
                            />
                            <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent pointer-events-none"></div>
                        </div>

                        <!-- Bottom Tagline -->
                        <p :class="isDark ? 'text-stone-400' : 'text-stone-600 font-medium'" class="text-xs mt-4 leading-relaxed transition-colors">
                            {{ currentLocale === 'ar'
                                ? 'رؤية مالية واضحة بنسبة 100%: مراقبة نمو الإيرادات، مبيعات الأصناف الأكثر ربحية، وتوفير العمولات الشهرية.'
                                : 'Real-time revenue metrics, profit drivers, and cost transparency with zero cut taken by middleman apps.' }}
                        </p>
                    </div>

                </div>

                <!-- BOTTOM ROW: 2 Cards (Floor Plan + Operations & Cashier) -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6" dir="ltr">

                    <!-- CARD 4: Interactive Floor Plan & Tables -->
                    <div dir="rtl" :class="isDark ? 'glass-ultra text-white' : 'glass-ultra text-stone-900'" class="rounded-3xl p-5 sm:p-6 flex flex-col justify-between overflow-hidden transition-all duration-500 hover:-translate-y-1.5">
                        <div class="flex items-start justify-between mb-4">
                            <div>
                                <span class="text-[11px] font-black tracking-widest uppercase text-amber-400/90 block">
                                    Interactive Floor Plan & Tables
                                </span>
                                <h3 class="text-base sm:text-lg font-black mt-1" :class="isDark ? 'text-white' : 'text-stone-900'">
                                    {{ currentLocale === 'ar' ? 'خريطة الصالة وإدارة الطاولات وصالونات الـ VIP' : 'Interactive Table Floor Plan & VIP Lounges' }}
                                </h3>
                            </div>
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-500/15 text-amber-300 border border-amber-500/30">
                                تحكم حي
                            </span>
                        </div>

                        <div class="relative w-full aspect-[16/9] rounded-2xl overflow-hidden border border-white/10 bg-black/60 shadow-inner group-hover:scale-[1.01] transition-transform duration-500">
                            <img
                                src="/images/tedallal-floor-plan-mockup.jpg"
                                alt="Interactive Table Floor Plan"
                                class="w-full h-full object-cover"
                            />
                            <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent pointer-events-none"></div>
                        </div>

                        <p :class="isDark ? 'text-stone-400' : 'text-stone-600 font-medium'" class="text-xs mt-4 leading-relaxed transition-colors">
                            {{ currentLocale === 'ar'
                                ? 'رسم تخطيطي تفاعلي لصالة مطعمك: متابعة حالة الطاولات لحظياً (مشغولة، متاحة، محجوزة)، وجلسات الصالونات الخاصة.'
                                : 'Architectural visual overview of dining spaces, live occupancy rings, and personalized service for private rooms.' }}
                        </p>
                    </div>

                    <!-- CARD 5: Operations & Cashier Automation -->
                    <div dir="rtl" :class="isDark ? 'glass-ultra text-white' : 'glass-ultra text-stone-900'" class="rounded-3xl p-5 sm:p-6 flex flex-col justify-between overflow-hidden transition-all duration-500 hover:-translate-y-1.5">
                        <div class="flex items-start justify-between mb-4">
                            <div>
                                <span class="text-[11px] font-black tracking-widest uppercase text-amber-400/90 block">
                                    Point of Sale & Shift Z-Reports
                                </span>
                                <h3 class="text-base sm:text-lg font-black mt-1" :class="isDark ? 'text-white' : 'text-stone-900'">
                                    {{ currentLocale === 'ar' ? 'نظام نقاط البيع والكاشير وتقارير الوردية' : 'Point of Sale & Shift Z-Report Closures' }}
                                </h3>
                            </div>
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-500/15 text-amber-300 border border-amber-500/30">
                                80mm حراري
                            </span>
                        </div>

                        <!-- Interactive Mini POS & Z-Report Card -->
                        <div :class="isDark ? 'bg-black/70 border-white/10 text-white' : 'bg-amber-50/50 border-amber-900/15 text-stone-900 shadow-inner'" class="relative w-full aspect-[16/9] rounded-2xl overflow-hidden border p-5 flex flex-col justify-between">
                            <div class="flex items-center justify-between pb-3 border-b border-[rgba(226,196,133,0.18)]">
                                <div class="flex items-center gap-2">
                                    <span class="w-3 h-3 rounded-full bg-emerald-400 animate-pulse"></span>
                                    <span class="text-xs font-black" :class="isDark ? 'text-white' : 'text-stone-900'">صندوق الكاشير الرئيسي • وردية الصباح</span>
                                </div>
                                <span class="text-[11px] font-mono text-amber-400 font-bold">Z-REPORT #2481</span>
                            </div>

                            <div class="grid grid-cols-3 gap-3 text-center py-2">
                                <div class="p-2.5 rounded-xl border transition-colors" :class="isDark ? 'bg-stone-900/90 border-[rgba(226,196,133,0.18)]' : 'bg-white border-amber-900/15 shadow-2xs'">
                                    <span class="text-[10px] block mb-0.5" :class="isDark ? 'text-stone-400' : 'text-stone-600 font-bold'">إجمالي المبيعات</span>
                                    <span class="text-xs sm:text-sm font-black" :class="isDark ? 'text-emerald-400' : 'text-emerald-700'">SAR 12,450</span>
                                </div>
                                <div class="p-2.5 rounded-xl border transition-colors" :class="isDark ? 'bg-stone-900/90 border-[rgba(226,196,133,0.18)]' : 'bg-white border-amber-900/15 shadow-2xs'">
                                    <span class="text-[10px] block mb-0.5" :class="isDark ? 'text-stone-400' : 'text-stone-600 font-bold'">عدد الطلبات</span>
                                    <span class="text-xs sm:text-sm font-black" :class="isDark ? 'text-white' : 'text-stone-900'">84 طلب</span>
                                </div>
                                <div class="p-2.5 rounded-xl border transition-colors" :class="isDark ? 'bg-stone-900/90 border-[rgba(226,196,133,0.18)]' : 'bg-white border-amber-900/15 shadow-2xs'">
                                    <span class="text-[10px] block mb-0.5" :class="isDark ? 'text-stone-400' : 'text-stone-600 font-bold'">وفورات العمولة</span>
                                    <span class="text-xs sm:text-sm font-black" :class="isDark ? 'text-amber-400' : 'text-amber-700'">+SAR 2,490</span>
                                </div>
                            </div>

                            <div class="flex items-center justify-between pt-2 border-t border-[rgba(226,196,133,0.18)]">
                                <span class="text-[11px]" :class="isDark ? 'text-stone-400' : 'text-stone-600 font-medium'">طباعة فورية بنقرة واحدة لطابعات Epson و Star</span>
                                <button type="button" class="px-3 py-1 rounded-lg bg-amber-500/20 text-amber-300 border border-amber-500/40 text-xs font-bold" @click="isDemoModalOpen = true">
                                    معاينة الفاتورة 🧾
                                </button>
                            </div>
                        </div>

                        <p :class="isDark ? 'text-stone-400' : 'text-stone-600 font-medium'" class="text-xs mt-4 leading-relaxed transition-colors">
                            {{ currentLocale === 'ar'
                                ? 'إغلاق وردية دقيق بدون فروقات نقدية: تفصيل الدفع (نقدي، مدى، فيزا)، مع طباعة حرارية سريعة وإرسال الفواتير عبر الواتساب.'
                                : 'Flawless cash drawer closures, multi-tender payment breakdown, and instant thermal receipt printing.' }}
                        </p>
                    </div>

                </div>
            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- 3. RESTORIA GRAND CULINARY SHOWCASE (معرض الأطباق الفاخر) -->
        <!-- ========================================================================= -->
        <section id="showcase" class="py-16 md:py-28 border-y relative z-10 transition-colors"
            :class="isDark ? 'bg-[#08080A] border-amber-500/15' : 'bg-[#F5F0E6]/75 border-[#C5A059]/25'"
        >
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Section Header with Restoria Royal Ornaments -->
                <div class="text-center max-w-3xl mx-auto mb-12">
                    <span class="inline-flex items-center gap-1.5 px-3.5 py-1 rounded-full text-[11px] font-black tracking-widest uppercase mb-3 border" :class="isDark ? 'bg-amber-500/10 text-amber-400 border-amber-500/30' : 'bg-amber-500/15 text-[#8C6010] border-[#C5A059]/40 shadow-xs'">
                        {{ currentLocale === 'ar' ? 'معرض التذوق والطهي الملكي' : 'HAUTE CUISINE EXHIBITION' }}
                    </span>
                    <h2 class="text-3xl sm:text-4xl lg:text-5xl font-black mb-3" :class="isDark ? 'text-white' : 'text-stone-900'">
                        {{ currentLocale === 'ar' ? 'قوائم أطباق فاخرة تُبهر الحواس وتزيد المبيعات' : 'Luxury Digital Menus That Captivate Diners' }}
                    </h2>
                    <div class="flex items-center justify-center gap-3 my-4">
                        <div class="h-px w-12 bg-amber-500/50"></div>
                        <span class="text-amber-400 text-sm">⚜️</span>
                        <div class="h-px w-12 bg-amber-500/50"></div>
                    </div>
                    <p :class="isDark ? 'text-stone-400' : 'text-stone-600 font-medium'" class="text-sm sm:text-base max-w-xl mx-auto transition-colors">
                        {{ currentLocale === 'ar'
                            ? 'أضف لمطعمك تجربة بصرية شهية: صور أطباق عالية الوضوح، شارات توصية الشيف، واحتساب فوري بالعملة المحلية.'
                            : 'Mouth-watering photography, chef signature badges, and live currency calculations in an ultra-luxurious grid.' }}
                    </p>
                </div>

                <!-- Culinary Category Switcher Tabs -->
                <div class="flex items-center justify-center mb-10 overflow-x-auto scrollbar-hide px-2">
                    <div class="inline-flex p-1.5 rounded-2xl border backdrop-blur-md gap-1.5"
                        :class="isDark ? 'glass-ultra' : 'glass-ultra shadow-sm'"
                    >
                        <button
                            type="button"
                            class="flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs sm:text-sm font-black transition-all shrink-0"
                            :class="activeCulinaryCategory === 'grills'
                                ? 'bg-gradient-to-r from-[#BF953F] to-[#B38728] text-stone-950 shadow-md shadow-amber-500/20'
                                : (isDark ? 'text-stone-400 hover:text-white' : 'text-stone-600 hover:text-stone-900 hover:bg-stone-100')"
                            @click="activeCulinaryCategory = 'grills'"
                        >
                            <span>🍢</span>
                            <span>{{ currentLocale === 'ar' ? 'المشويات الحلبية الفاخرة' : 'Signature Grills' }}</span>
                        </button>

                        <button
                            type="button"
                            class="flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs sm:text-sm font-black transition-all shrink-0"
                            :class="activeCulinaryCategory === 'appetizers'
                                ? 'bg-gradient-to-r from-[#BF953F] to-[#B38728] text-stone-950 shadow-md shadow-amber-500/20'
                                : (isDark ? 'text-stone-400 hover:text-white' : 'text-stone-600 hover:text-stone-900 hover:bg-stone-100')"
                            @click="activeCulinaryCategory = 'appetizers'"
                        >
                            <span>🥗</span>
                            <span>{{ currentLocale === 'ar' ? 'المازة والمقبلات الشهباء' : 'Grand Mezza' }}</span>
                        </button>

                        <button
                            type="button"
                            class="flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs sm:text-sm font-black transition-all shrink-0"
                            :class="activeCulinaryCategory === 'desserts'
                                ? 'bg-gradient-to-r from-[#BF953F] to-[#B38728] text-stone-950 shadow-md shadow-amber-500/20'
                                : (isDark ? 'text-stone-400 hover:text-white' : 'text-stone-600 hover:text-stone-900 hover:bg-stone-100')"
                            @click="activeCulinaryCategory = 'desserts'"
                        >
                            <span>🍯</span>
                            <span>{{ currentLocale === 'ar' ? 'الحلويات التراثية الساخنة' : 'Warm Desserts' }}</span>
                        </button>

                        <button
                            type="button"
                            class="flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs sm:text-sm font-black transition-all shrink-0"
                            :class="activeCulinaryCategory === 'drinks'
                                ? 'bg-gradient-to-r from-[#BF953F] to-[#B38728] text-stone-950 shadow-md shadow-amber-500/20'
                                : (isDark ? 'text-stone-400 hover:text-white' : 'text-stone-600 hover:text-stone-900 hover:bg-stone-100')"
                            @click="activeCulinaryCategory = 'drinks'"
                        >
                            <span>🍹</span>
                            <span>{{ currentLocale === 'ar' ? 'العصائر الطبيعية المنعشة' : 'Fresh Juices' }}</span>
                        </button>
                    </div>
                </div>

                <!-- 3-Column Restoria Food Card Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <article
                        v-for="dish in visibleDishes"
                        :key="dish.id"
                        class="group rounded-3xl border overflow-hidden flex flex-col justify-between transition-all duration-300 hover:shadow-2xl hover:-translate-y-1 glass-ultra"
                    >
                        <!-- Gourmet Dish Photography -->
                        <div class="relative w-full aspect-[16/10] overflow-hidden bg-stone-900">
                            <img
                                :src="dish.image"
                                :alt="dish.name"
                                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                            />
                            <div class="absolute inset-0 bg-gradient-to-t from-[#0a0f1d] via-transparent to-transparent opacity-80"></div>

                            <!-- Chef Culinary Tag Badge -->
                            <span class="absolute top-3 start-3 inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] shadow-lg backdrop-blur-md"
                                :class="dish.badge.style"
                            >
                                <span>{{ dish.badge.icon }}</span>
                                <span>{{ dish.badge.label }}</span>
                            </span>

                            <!-- Preparation Time Badge -->
                            <span class="absolute bottom-3 end-3 px-2.5 py-1 rounded-lg text-[10px] font-bold bg-stone-950/80 text-stone-300 border border-stone-700/50 backdrop-blur-md">
                                ⏱️ {{ dish.prepTime }}
                            </span>
                        </div>

                        <!-- Dish Body -->
                        <div class="p-5 flex-1 flex flex-col justify-between">
                            <div>
                                <h3 class="font-black text-lg mb-1 group-hover:text-amber-400 transition-colors" :class="isDark ? 'text-white' : 'text-stone-900'">
                                    {{ dish.name }}
                                </h3>
                                <p :class="isDark ? 'text-stone-400' : 'text-stone-600'" class="text-xs line-clamp-2 leading-relaxed mb-4 transition-colors">
                                    {{ dish.description }}
                                </p>
                            </div>

                            <!-- Pricing & Interactive Quick Add Row -->
                            <div class="pt-3 border-t border-[rgba(226,196,133,0.18)]/80 flex items-center justify-between">
                                <div class="flex flex-col">
                                    <span class="text-[10px] text-stone-500 uppercase font-bold">السعر</span>
                                    <span class="text-base font-black text-amber-400">
                                        {{ formatPrice(dish.price) }} {{ currentCurrencySymbol }}
                                    </span>
                                </div>

                                <!-- Add / Counter Buttons -->
                                <div class="flex items-center gap-2">
                                    <template v-if="simulatedCart[dish.id]">
                                        <button
                                            type="button"
                                            class="w-8 h-8 rounded-full bg-stone-800 text-stone-200 font-black flex items-center justify-center hover:bg-stone-700 active:scale-90 transition-all text-sm"
                                            @click="removeDishFromSimulatedCart(dish.id)"
                                        >
                                            −
                                        </button>
                                        <span class="text-xs font-black min-w-[16px] text-center text-amber-300">
                                            {{ simulatedCart[dish.id] }}
                                        </span>
                                    </template>
                                    <button
                                        type="button"
                                        class="h-9 px-3.5 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 text-stone-950 font-black text-xs shadow-md shadow-amber-500/20 hover:from-amber-400 hover:to-amber-500 active:scale-95 transition-all flex items-center gap-1.5"
                                        @click="addDishToSimulatedCart(dish)"
                                    >
                                        <span>+</span>
                                        <span>{{ currentLocale === 'ar' ? 'إضافة للسلة' : 'Add' }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>

                <!-- Floating Order Bar for Simulator -->
                <div v-if="simulatedCartCount > 0" class="mt-8 max-w-xl mx-auto p-4 rounded-2xl border flex items-center justify-between shadow-2xl backdrop-blur-xl animate-bounce-short"
                    :class="isDark ? 'bg-[#0c1222]/95 border-amber-500/40 text-white' : 'bg-white border-amber-400 shadow-xl'"
                >
                    <div class="flex items-center gap-3">
                        <span class="w-10 h-10 rounded-full bg-amber-500 text-stone-950 font-black flex items-center justify-center text-sm">
                            {{ simulatedCartCount }}
                        </span>
                        <div>
                            <p class="text-xs font-black">
                                {{ currentLocale === 'ar' ? 'طلب الطاولة قيد المعاينة الحية' : 'Live Table Order Preview' }}
                            </p>
                            <p class="text-[11px] text-stone-400">
                                {{ formatPrice(simulatedCartTotalSyp) }} {{ currentCurrencySymbol }} • يصل فوراً للمطبخ
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-stone-950 font-black text-xs transition-all active:scale-95"
                        @click="isDemoModalOpen = true"
                    >
                        {{ currentLocale === 'ar' ? 'فعّل المنظومة لمطعمك ➔' : 'Activate for Your Restaurant ➔' }}
                    </button>
                </div>
            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- 4. THE FLOOR-TO-KITCHEN LIVE BRIDGE (من الصالة للمطبخ) -->
        <!-- ========================================================================= -->
        <section id="floor-to-kitchen" class="py-16 md:py-28 relative z-10">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Section Header -->
                <div class="text-center max-w-3xl mx-auto mb-14">
                    <span class="inline-flex items-center gap-1.5 px-3.5 py-1 rounded-full text-[11px] font-black tracking-widest uppercase mb-3 border" :class="isDark ? 'bg-amber-500/10 text-amber-400 border-amber-500/30' : 'bg-amber-500/15 text-[#8C6010] border-[#C5A059]/40 shadow-xs'">
                        {{ currentLocale === 'ar' ? 'دورة الطلب الذكية' : 'THE SEAMLESS WORKFLOW' }}
                    </span>
                    <h2 class="text-3xl sm:text-4xl lg:text-5xl font-black mb-3" :class="isDark ? 'text-white' : 'text-stone-900'">
                        {{ currentLocale === 'ar' ? 'انسجام تام بين رواد الصالة وشيف المطبخ' : 'Floor to Kitchen in 0.2 Seconds' }}
                    </h2>
                    <p :class="isDark ? 'text-stone-400' : 'text-stone-600 font-medium'" class="text-sm max-w-xl mx-auto transition-colors">
                        {{ currentLocale === 'ar'
                            ? 'وداعاً للفواتير الورقية الضائعة والنداءات المتكررة. الطلب ينطلق من يد الزبون ليستقر أمام عيني الشيف فوراً.'
                            : 'Eliminate missed tickets and chaotic communication with a direct digital link from seat to chef.' }}
                    </p>
                </div>

                <!-- 2 Grand Visual Cards: Dining QR vs Chef KDS -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

                    <!-- CARD 1: Dining QR Luxury Experience -->
                    <div class="rounded-3xl border overflow-hidden flex flex-col justify-between transition-all glass-ultra"
                    >
                        <div class="relative w-full aspect-[16/10] overflow-hidden">
                            <img
                                src="/images/storyboard/scene_3_tedallal_qr_solution_1793695540137.jpg"
                                alt="مسح QR على طاولة رخامية"
                                class="w-full h-full object-cover transform hover:scale-105 transition-transform duration-700"
                            />
                            <div class="absolute inset-0 bg-gradient-to-t from-[#0a0f1d] via-transparent to-transparent"></div>
                            <span class="absolute top-4 start-4 px-3 py-1 rounded-full bg-stone-950/80 text-amber-300 text-xs font-black border border-amber-500/40 backdrop-blur-md">
                                📱 تجربة رواد الصالة
                            </span>
                        </div>
                        <div class="p-6">
                            <h3 class="text-xl font-black mb-2" :class="isDark ? 'text-white' : 'text-stone-900'">
                                {{ currentLocale === 'ar' ? 'طلب فوري ومخصص بدون انتظار' : 'Instant In-Seat Dining' }}
                            </h3>
                            <p :class="isDark ? 'text-stone-400' : 'text-stone-600'" class="text-xs sm:text-sm leading-relaxed mb-4 transition-colors">
                                {{ currentLocale === 'ar'
                                    ? 'يتصفح الزبائن القائمة بأريحية، يحددون درجات الطهي والإضافات والملاحظات الخاصة، ويؤكدون الطلب بلمسة واحدة دون التلويح للنادل.'
                                    : 'Guests browse dish photography, set custom notes (e.g., extra sauce), and place orders without waiting for waitstaff.' }}
                            </p>
                            <div class="flex items-center gap-2 text-xs font-bold text-amber-400">
                                <span>✓ دعم أجهزة iOS وأندرويد</span>
                                <span class="text-stone-600">•</span>
                                <span>✓ بدون تحميل أي تطبيق</span>
                            </div>
                        </div>
                    </div>

                    <!-- CARD 2: Executive Chef KDS Display -->
                    <div class="rounded-3xl border overflow-hidden flex flex-col justify-between transition-all glass-ultra"
                    >
                        <div class="relative w-full aspect-[16/10] overflow-hidden">
                            <img
                                src="/images/storyboard/scene_4_kitchen_kds_automation_1793695563497.jpg"
                                alt="شاشة الشيف KDS في المطبخ"
                                class="w-full h-full object-cover transform hover:scale-105 transition-transform duration-700"
                            />
                            <div class="absolute inset-0 bg-gradient-to-t from-[#0a0f1d] via-transparent to-transparent"></div>
                            <span class="absolute top-4 start-4 px-3 py-1 rounded-full bg-emerald-950/80 text-emerald-300 text-xs font-black border border-emerald-500/40 backdrop-blur-md">
                                👨‍🍳 شاشة المطبخ والشيف KDS
                            </span>
                        </div>
                        <div class="p-6">
                            <h3 class="text-xl font-black mb-2" :class="isDark ? 'text-white' : 'text-stone-900'">
                                {{ currentLocale === 'ar' ? 'تحضير دقيق وتوقيت قياسي للطلبات' : 'Clockwork Kitchen Precision' }}
                            </h3>
                            <p class="text-xs sm:text-sm text-stone-400 leading-relaxed mb-4">
                                {{ currentLocale === 'ar'
                                    ? 'تظهر الطلبات بألوان متدرجة فور وصولها مع عداد زمني للتحضير، تنبيهات صوتية جرسية، وشاشة لا تنطفئ طوال الوردية.'
                                    : 'Tickets arrive categorized by color with elapsed timers, sound alerts, and continuous Screen WakeLock throughout shifts.' }}
                            </p>
                            <div class="flex items-center gap-2 text-xs font-bold text-emerald-400">
                                <span>✓ رنين تنبيهي للطلبات الجديدة</span>
                                <span class="text-stone-600">•</span>
                                <span>✓ شاشة لا تنطفئ (Wake Lock)</span>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- 5. ASYMMETRIC BENTO GRID (4 Power Modules) -->
        <!-- ========================================================================= -->
        <section id="bento" class="py-16 md:py-28 border-t relative z-10 transition-colors"
            :class="isDark ? 'bg-[#08080A] border-[rgba(226,196,133,0.18)]' : 'bg-[#F5F0E6]/75 border-[#C5A059]/25'"
        >
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Section Header -->
                <div class="text-center max-w-3xl mx-auto mb-14">
                    <span class="inline-flex items-center gap-1.5 px-3.5 py-1 rounded-full text-[11px] font-black tracking-widest uppercase mb-3 border" :class="isDark ? 'bg-amber-500/10 text-amber-400 border-amber-500/30' : 'bg-amber-500/15 text-[#8C6010] border-[#C5A059]/40 shadow-xs'">
                        {{ currentLocale === 'ar' ? 'هندسة المنظومة المتكاملة' : 'CORE CAPABILITIES' }}
                    </span>
                    <h2 class="text-3xl sm:text-4xl lg:text-5xl font-black mb-3" :class="isDark ? 'text-white' : 'text-stone-900'">
                        {{ currentLocale === 'ar' ? 'إدارة ذكية ومحاسبة حرارية بلا أخطاء' : 'Engineered for Flawless Operations' }}
                    </h2>
                </div>

                <!-- 4 Bento Modules -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

                    <!-- MODULE 1 (Col Span 2): Full Service vs Fast Casual -->
                    <div class="md:col-span-2 rounded-3xl border p-6 sm:p-8 flex flex-col justify-between transition-all"
                        :class="isDark ? 'glass-ultra' : 'glass-ultra shadow-sm'"
                    >
                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <span class="px-3 py-1 rounded-full text-xs font-black bg-amber-500/10 text-amber-400 border border-amber-500/30">
                                    {{ currentLocale === 'ar' ? 'مرونة تشغيلية تناسب نمط مطعمك' : 'Dual Operating Modes' }}
                                </span>
                                <!-- Mode Switcher Pill -->
                                <div class="inline-flex p-1 rounded-xl border text-xs font-bold gap-1"
                                    :class="isDark ? 'bg-stone-900 border-[rgba(226,196,133,0.18)]' : 'bg-stone-100 border-stone-200'"
                                >
                                    <button
                                        type="button"
                                        class="px-3 py-1 rounded-lg transition-all"
                                        :class="selectedOperatingMode === 'postpaid' ? 'bg-amber-500 text-stone-950 font-black' : 'text-stone-400'"
                                        @click="selectedOperatingMode = 'postpaid'"
                                    >
                                        {{ currentLocale === 'ar' ? 'خدمة كاملة (دفع لاحق)' : 'Dine-In Tab' }}
                                    </button>
                                    <button
                                        type="button"
                                        class="px-3 py-1 rounded-lg transition-all"
                                        :class="selectedOperatingMode === 'prepaid' ? 'bg-amber-500 text-stone-950 font-black' : 'text-stone-400'"
                                        @click="selectedOperatingMode = 'prepaid'"
                                    >
                                        {{ currentLocale === 'ar' ? 'وجبات سريعة (دفع مسبق)' : 'Fast-Casual' }}
                                    </button>
                                </div>
                            </div>

                            <h3 class="text-xl sm:text-2xl font-black mb-2" :class="isDark ? 'text-white' : 'text-stone-900'">
                                {{ selectedOperatingMode === 'postpaid'
                                    ? (currentLocale === 'ar' ? 'نظام الخدمة الكاملة: فتح حساب طاولة وطلب متكرر' : 'Full-Service Dine-In: Open Tabs & Rounds')
                                    : (currentLocale === 'ar' ? 'نظام الدفع السريع: تحصيل فوري عند الكاونتر قبل التجهيز' : 'Fast-Casual: Instant Payment Before Cooking') }}
                            </h3>
                            <p class="text-xs sm:text-sm text-stone-400 mb-6 leading-relaxed">
                                {{ selectedOperatingMode === 'postpaid'
                                    ? (currentLocale === 'ar' ? 'يجلس الزبائن، يطلبون مقبلات ثم وجبات رئيسية ثم حلويات، وتُجمع الفاتورة تلقائياً حتى المغادرة.' : 'Guests order multiple rounds over their visit, and the tab accumulates seamlessly until final checkout.')
                                    : (currentLocale === 'ar' ? 'مثالي للكافيهات ومطاعم الوجبات السريعة: يدفع الزبون أولاً، وينطلق الطلب فوراً إلى شاشات المطبخ.' : 'Ideal for cafes and burger spots: pay first, then orders flow straight to kitchen preparation screens.') }}
                            </p>
                        </div>

                        <!-- 3-Step Micro Workflow -->
                        <div class="grid grid-cols-3 gap-3 pt-4 border-t border-[rgba(226,196,133,0.18)] text-center">
                            <div class="p-3 rounded-2xl" :class="isDark ? 'bg-stone-900/60' : 'bg-stone-50'">
                                <span class="text-base font-black block text-amber-400">1</span>
                                <span class="text-xs font-bold" :class="isDark ? 'text-stone-200' : 'text-stone-800'">
                                    {{ currentLocale === 'ar' ? 'مسح QR' : 'Scan QR' }}
                                </span>
                            </div>
                            <div class="p-3 rounded-2xl" :class="isDark ? 'bg-stone-900/60' : 'bg-stone-50'">
                                <span class="text-base font-black block text-amber-400">2</span>
                                <span class="text-xs font-bold" :class="isDark ? 'text-stone-200' : 'text-stone-800'">
                                    {{ selectedOperatingMode === 'postpaid' ? (currentLocale === 'ar' ? 'تجهيز مباشر' : 'Direct Prep') : (currentLocale === 'ar' ? 'دفع فوري' : 'Quick Pay') }}
                                </span>
                            </div>
                            <div class="p-3 rounded-2xl" :class="isDark ? 'bg-stone-900/60' : 'bg-stone-50'">
                                <span class="text-base font-black block text-amber-400">3</span>
                                <span class="text-xs font-bold" :class="isDark ? 'text-stone-200' : 'text-stone-800'">
                                    {{ selectedOperatingMode === 'postpaid' ? (currentLocale === 'ar' ? 'سداد عند الخروج' : 'Pay at End') : (currentLocale === 'ar' ? 'استلام فوري' : 'Pick Up') }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- MODULE 2 (Col Span 1): Lucky Wheel Loyalty -->
                    <div class="rounded-3xl border p-6 flex flex-col justify-between transition-all"
                        :class="isDark ? 'glass-ultra' : 'glass-ultra shadow-sm'"
                    >
                        <div>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-rose-500/10 text-rose-400 border border-rose-500/30 mb-3 inline-block">
                                {{ currentLocale === 'ar' ? 'برنامج ولاء تفاعلي' : 'Gamified Loyalty' }}
                            </span>
                            <h3 class="text-lg font-black mb-1" :class="isDark ? 'text-white' : 'text-stone-900'">
                                {{ currentLocale === 'ar' ? 'عجلة الحظ ومضاعفة الزيارات' : 'Lucky Wheel Rewards' }}
                            </h3>
                            <p class="text-xs text-stone-400 mb-4 leading-relaxed">
                                {{ currentLocale === 'ar' ? 'كافئ زبائنك بعد إنهاء الطلب بفرصة تدوير العجلة لربح كوبون يضمن زيارتهم القادمة.' : 'Delight diners after checkout with a fun spin that delivers vouchers for their next visit.' }}
                            </p>
                        </div>

                        <!-- Mini Interactive Wheel Graphic -->
                        <div class="p-4 rounded-2xl border text-center flex flex-col items-center justify-center my-2"
                            :class="isDark ? 'bg-stone-900/50 border-[rgba(226,196,133,0.18)]' : 'bg-stone-50 border-stone-200'"
                        >
                            <div class="w-20 h-20 rounded-full border-4 border-amber-400 flex items-center justify-center mb-3 shadow-lg"
                                :class="luckyWheelSpinning ? 'animate-spin' : ''"
                            >
                                <span class="text-2xl">🎡</span>
                            </div>
                            <button
                                type="button"
                                class="px-4 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-stone-950 font-black text-xs active:scale-95 transition-all shadow-xs"
                                :disabled="luckyWheelSpinning"
                                @click="spinLuckyWheel"
                            >
                                {{ luckyWheelSpinning ? 'جارٍ التدوير...' : (currentLocale === 'ar' ? 'جرّب التدوير الآن' : 'Spin Wheel') }}
                            </button>
                            <span v-if="luckyWheelWonVoucher" class="mt-2 text-[11px] font-black text-emerald-400">
                                🎁 كود الهدية: {{ luckyWheelWonVoucher }}
                            </span>
                        </div>
                    </div>

                    <!-- MODULE 3 (Col Span 1): Thermal Z-Report Printing -->
                    <div class="rounded-3xl border p-6 flex flex-col justify-between transition-all"
                        :class="isDark ? 'glass-ultra' : 'glass-ultra shadow-sm'"
                    >
                        <div>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-blue-500/10 text-blue-400 border border-blue-500/30 mb-3 inline-block">
                                {{ currentLocale === 'ar' ? 'محاسبة حرارية 80mm' : 'CPA 80mm Z-Report' }}
                            </span>
                            <h3 class="text-lg font-black mb-1" :class="isDark ? 'text-white' : 'text-stone-900'">
                                {{ currentLocale === 'ar' ? 'إغلاق الوردية (تقرير Z)' : 'End-of-Shift Z-Reports' }}
                            </h3>
                            <p class="text-xs text-stone-400 mb-4 leading-relaxed">
                                {{ currentLocale === 'ar' ? 'طباعة تقرير الإغلاق الحراري 80mm بلمسة زر ومشاركته عبر واتساب مع مالك المطعم مباشرة.' : 'Instant 80mm thermal shift closing receipts with cash/card breakdown and 1-click WhatsApp export.' }}
                            </p>
                        </div>

                        <!-- Thermal Receipt Preview -->
                        <div class="p-3 rounded-2xl border font-mono text-[11px] space-y-1"
                            :class="isDark ? 'bg-stone-900 border-[rgba(226,196,133,0.18)] text-stone-300' : 'bg-amber-50/50 border-amber-200 text-stone-800'"
                        >
                            <div class="text-center pb-1 border-b border-stone-700/30 font-bold">
                                --- تقرير إغلاق الوردية (Z) ---
                            </div>
                            <div class="flex justify-between">
                                <span>إجمالي المبيعات:</span>
                                <span class="font-bold text-amber-400">{{ formatPrice(2450000) }} {{ currentCurrencySymbol }}</span>
                            </div>
                            <div class="flex justify-between text-[10px] text-stone-400">
                                <span>نقدي: 70% | شبكة: 30%</span>
                                <span>عدد الفواتير: 48</span>
                            </div>
                            <div class="pt-1 text-center text-[10px] text-emerald-400 font-sans font-bold">
                                📲 متوافق مع طابعات 80mm وإرسال الواتساب
                            </div>
                        </div>
                    </div>

                    <!-- MODULE 4 (Col Span 2): Kitchen Screen WakeLock -->
                    <div class="md:col-span-2 rounded-3xl border p-6 sm:p-8 flex flex-col justify-between transition-all"
                        :class="isDark ? 'glass-ultra' : 'glass-ultra shadow-sm'"
                    >
                        <div>
                            <span class="px-3 py-1 rounded-full text-xs font-black bg-emerald-500/10 text-emerald-400 border border-emerald-500/30 mb-3 inline-block">
                                {{ currentLocale === 'ar' ? 'تشغيل مستمر بدون انطفاء' : 'Screen Wake Lock API' }}
                            </span>
                            <h3 class="text-xl sm:text-2xl font-black mb-2" :class="isDark ? 'text-white' : 'text-stone-900'">
                                {{ currentLocale === 'ar' ? 'شاشات المطبخ لا تنطفئ أبداً طوال الوردية' : 'Kitchen Displays Never Sleep' }}
                            </h3>
                            <p class="text-xs sm:text-sm text-stone-400 mb-4 leading-relaxed">
                                {{ currentLocale === 'ar'
                                    ? 'يبقى تابلت الشيف والمطبخ مضاءً طوال الوردية بدون لمس الشاشة، مع اتصال دائم بالـ WebSockets وتنبيه صوتي بالرنين عند وصول كل طلب جديد.'
                                    : 'Your kitchen tablets remain active continuously throughout shifts without sleep, with instant ring chimes on incoming orders.' }}
                            </p>
                        </div>

                        <!-- Live Status Indicators -->
                        <div class="flex items-center gap-3 pt-4 border-t border-[rgba(226,196,133,0.18)]">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-emerald-500/10 text-emerald-400 text-xs font-bold border border-emerald-500/30">
                                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                                {{ currentLocale === 'ar' ? 'مانع انطفاء الشاشة نشط ⚡' : 'Screen WakeLock Active' }}
                            </span>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-amber-500/10 text-amber-400 text-xs font-bold border border-amber-500/30">
                                🔔 {{ currentLocale === 'ar' ? 'تنبيه جرس صوتي للطلبات' : 'Sound Alerts Enabled' }}
                            </span>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- 6. SINGLE-SLIDER COMMISSION SAVINGS CALCULATOR (ROI) -->
        <!-- ========================================================================= -->
        <section id="roi" class="py-16 md:py-28 relative z-10">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                <span class="inline-flex items-center gap-1.5 px-3.5 py-1 rounded-full text-[11px] font-black tracking-widest uppercase mb-3 border" :class="isDark ? 'bg-amber-500/10 text-amber-400 border-amber-500/30' : 'bg-amber-500/15 text-[#8C6010] border-[#C5A059]/40 shadow-xs'">
                    {{ currentLocale === 'ar' ? 'حاسبة استرداد الأرباح' : 'COMMISSION ROI CALCULATOR' }}
                </span>
                <h2 class="text-3xl sm:text-4xl lg:text-5xl font-black mb-3" :class="isDark ? 'text-white' : 'text-stone-900'">
                    {{ currentLocale === 'ar' ? 'كم توفر شهرياً بالتخلص من عمولات التطبيقات؟' : 'Calculate Your Monthly Net Savings' }}
                </h2>
                <p :class="isDark ? 'text-stone-400' : 'text-stone-600 font-medium'" class="text-sm sm:text-base mb-10 max-w-xl mx-auto transition-colors">
                    {{ currentLocale === 'ar'
                        ? 'تقتطع منصات وتطبيقات الطلب وسيطاً ما بين 15% إلى 25% من إيراداتك. مع تدلل، تدفع اشتراكاً ثابتاً وتسترد أرباحك كاملة 100%.'
                        : 'Third-party delivery apps slash 15%-25% off every order. With Tedallal, you pay a flat fee and keep all profits.' }}
                </p>

                <!-- Interactive Single-Slider Card -->
                <div class="rounded-3xl border p-6 sm:p-10 shadow-2xl text-right max-w-2xl mx-auto"
                    :class="isDark ? 'glass-ultra' : 'glass-ultra shadow-xl'"
                >
                    <!-- Slider Value -->
                    <div class="flex items-center justify-between mb-4">
                        <label class="text-xs sm:text-sm font-bold" :class="isDark ? 'text-stone-300' : 'text-stone-700'">
                            {{ currentLocale === 'ar' ? 'مبيعات مطعمك الشهرية التقديرية:' : 'Approximate Monthly Sales:' }}
                        </label>
                        <span class="text-base sm:text-2xl font-black text-amber-400">
                            {{ formatPrice(monthlySalesSyp) }} {{ currentCurrencySymbol }}
                        </span>
                    </div>

                    <!-- Range Input -->
                    <input
                        v-model.number="monthlySalesSyp"
                        type="range"
                        min="10000000"
                        max="250000000"
                        step="5000000"
                        :class="isDark ? 'bg-stone-700 accent-amber-500' : 'bg-stone-200 accent-amber-600'" class="w-full h-3 rounded-lg appearance-none cursor-pointer mb-8"
                    />

                    <!-- Comparative Calculation Breakdown -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-6 border-t border-[rgba(226,196,133,0.18)]">
                        <div class="p-4 rounded-2xl border text-center"
                            :class="isDark ? 'bg-red-500/10 border-red-500/30' : 'bg-red-50 border-red-200'"
                        >
                            <span class="text-xs text-red-400 font-bold block mb-1">
                                {{ currentLocale === 'ar' ? 'عمولة التطبيقات المفقودة (20%)' : 'Commission Slashed (20%)' }}
                            </span>
                            <span class="text-lg sm:text-xl font-black text-red-500">
                                -{{ formatPrice(deliveryCommissionLost) }} {{ currentCurrencySymbol }}
                            </span>
                        </div>

                        <div class="p-4 rounded-2xl border text-center"
                            :class="isDark ? 'bg-emerald-500/10 border-emerald-500/30' : 'bg-emerald-50 border-emerald-200'"
                        >
                            <span class="text-xs text-emerald-400 font-bold block mb-1">
                                {{ currentLocale === 'ar' ? 'أرباح إضافية مستردة مع تدلل 💰' : 'Net Profits Preserved' }}
                            </span>
                            <span class="text-lg sm:text-xl font-black text-emerald-400">
                                +{{ formatPrice(netProfitsPreserved) }} {{ currentCurrencySymbol }}
                            </span>
                        </div>
                    </div>

                    <p class="text-center text-[11px] text-stone-500 mt-6">
                        {{ currentLocale === 'ar' ? '* الحساب تقريبي مبني على متوسط عمولة 20% لتطبيقات الطلب مقارنة باشتراك تدلل الرمزي.' : '* Calculation based on standard 20% delivery app fees vs. Tedallal flat subscription.' }}
                    </p>

                    <!-- Executive Pitch Deck Banner for Restaurant Decision Makers -->
                    <div class="mt-8 p-5 sm:p-6 rounded-2xl border flex flex-col sm:flex-row items-center justify-between gap-4 transition-all"
                        :class="isDark ? 'bg-gradient-to-r from-amber-500/10 via-amber-500/5 to-transparent border-amber-500/30' : 'bg-gradient-to-r from-amber-50 via-amber-100/50 to-white border-[#C5A059]/40'"
                    >
                        <div class="flex items-center gap-3 text-center sm:text-right">
                            <span class="text-2xl">📊</span>
                            <div>
                                <p class="text-sm font-black" :class="isDark ? 'text-amber-300' : 'text-[#7A5208]'">
                                    {{ currentLocale === 'ar' ? 'هل أنت صاحب مطعم أو مدير تشغيل وترغب بدراسة الجدوى؟' : 'Are you a Restaurant Owner or GM?' }}
                                </p>
                                <p class="text-xs" :class="isDark ? 'text-stone-300' : 'text-stone-600'">
                                    {{ currentLocale === 'ar' ? 'استعرض العرض التقديمي التنفيذي الشامل (12 شريحة تفاعلية مع حاسبة العوائد ومحاكاة الصالة).' : 'Explore the 12-slide interactive pitch deck with floor simulators & ROI analysis.' }}
                                </p>
                            </div>
                        </div>
                        <a
                            href="/presentation"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="px-5 py-2.5 rounded-full bg-gradient-to-r from-[#BF953F] via-[#E8C265] to-[#B38728] text-stone-950 font-black text-xs shadow-md hover:scale-105 active:scale-95 transition-all whitespace-nowrap flex items-center gap-1.5"
                        >
                            <span>{{ currentLocale === 'ar' ? 'فتح الملف التعريفي التنفيذي' : 'Open Pitch Deck' }}</span>
                            <span>↗</span>
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- 7. PROGRESSIVE DISCLOSURE ACCORDION (FAQs) -->
        <!-- ========================================================================= -->
        <section id="faqs" class="py-16 md:py-24 border-t relative z-10 transition-colors"
            :class="isDark ? 'bg-[#08080A] border-[rgba(226,196,133,0.18)]' : 'bg-[#FAF8F5] border-[#C5A059]/25'"
        >
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center max-w-xl mx-auto mb-12">
                    <span class="inline-flex items-center gap-1.5 px-3.5 py-1 rounded-full text-[11px] font-black tracking-widest uppercase mb-3 border" :class="isDark ? 'bg-amber-500/10 text-amber-400 border-amber-500/30' : 'bg-amber-500/15 text-[#8C6010] border-[#C5A059]/40 shadow-xs'">
                        {{ currentLocale === 'ar' ? 'إجابات فورية' : 'FAQS' }}
                    </span>
                    <h2 class="text-3xl sm:text-4xl font-black mb-3" :class="isDark ? 'text-white' : 'text-stone-900'">
                        {{ currentLocale === 'ar' ? 'الأسئلة الأكثر شيوعاً' : 'Frequently Asked Questions' }}
                    </h2>
                </div>

                <div class="space-y-3">
                    <div
                        v-for="(faq, idx) in faqItems"
                        :key="idx"
                        class="rounded-2xl border overflow-hidden transition-all"
                        :class="isDark ? 'glass-ultra text-white' : 'glass-ultra text-stone-900 shadow-sm'"
                    >
                        <button
                            type="button"
                            class="w-full flex items-center justify-between p-4 sm:p-5 text-right font-black text-sm sm:text-base transition-colors"
                            :class="isDark ? 'text-stone-100 hover:text-amber-400' : 'text-stone-900 hover:text-amber-600'"
                            @click="toggleFaq(idx)"
                        >
                            <span>{{ faq.q }}</span>
                            <span class="text-amber-400 text-lg transition-transform duration-200" :class="openFaqIndex === idx ? 'rotate-180' : ''">
                                ▾
                            </span>
                        </button>
                        <div
                            v-if="openFaqIndex === idx"
                            :class="isDark ? 'text-stone-300 border-stone-800' : 'text-stone-700 border-stone-200 font-medium'" class="px-4 pb-5 sm:px-5 sm:pb-6 text-xs sm:text-sm leading-relaxed border-t pt-3 transition-colors"
                        >
                            {{ faq.a }}
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- 8. VIP CONSULTATION REQUEST MODAL -->
        <!-- ========================================================================= -->
        <div
            v-if="isDemoModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-md"
            @click.self="isDemoModalOpen = false"
        >
            <div class="w-full max-w-lg rounded-3xl border p-6 sm:p-8 shadow-2xl relative transition-all"
                :class="isDark ? 'bg-[#0c1222] border-amber-500/40 text-white' : 'glass-ultra text-stone-900'"
            >
                <button
                    type="button"
                    class="absolute top-5 end-5 w-8 h-8 rounded-full border flex items-center justify-center text-stone-400 hover:text-white"
                    :class="isDark ? 'border-[rgba(226,196,133,0.18)] hover:bg-stone-800' : 'border-stone-200 hover:bg-stone-100'"
                    @click="isDemoModalOpen = false"
                >
                    ✕
                </button>

                <div v-if="!isSubmitted">
                    <span class="text-xs font-black uppercase tracking-wider text-amber-400 block mb-1">
                        {{ currentLocale === 'ar' ? 'جلسة تجريبية خاصة' : 'VIP Consultation' }}
                    </span>
                    <h3 class="text-2xl font-black mb-2">
                        {{ currentLocale === 'ar' ? 'احجز استشارتك المجانية لمطعمك' : 'Book Your Private Restaurant Demo' }}
                    </h3>
                    <p class="text-xs text-stone-400 mb-6">
                        {{ currentLocale === 'ar' ? 'سيقوم مستشارنا بالتواصل معك عبر واتساب لتجهيز القائمة وتفعيل الطاولات فوراً.' : 'Our hospitality specialist will connect via WhatsApp to configure your menu & tables.' }}
                    </p>

                    <form class="space-y-4" @submit.prevent="submitDemoRequest">
                        <div>
                            <label class="block text-xs font-bold mb-1.5">
                                {{ currentLocale === 'ar' ? 'اسم المطعم أو الكافيه' : 'Restaurant Name' }}
                            </label>
                            <input
                                v-model="demoForm.restaurantName"
                                type="text"
                                required
                                class="w-full px-4 py-2.5 rounded-xl border text-sm focus:outline-hidden focus:border-amber-500"
                                :class="isDark ? 'bg-stone-900 border-[rgba(226,196,133,0.18)] text-white' : 'bg-stone-50 border-stone-200'"
                                :placeholder="currentLocale === 'ar' ? 'مثال: قصر الشهباء الفاخر' : 'e.g. Al-Shahba Palace'"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-bold mb-1.5">
                                {{ currentLocale === 'ar' ? 'اسم المسؤول أو المدير' : 'Contact Person' }}
                            </label>
                            <input
                                v-model="demoForm.contactPerson"
                                type="text"
                                required
                                class="w-full px-4 py-2.5 rounded-xl border text-sm focus:outline-hidden focus:border-amber-500"
                                :class="isDark ? 'bg-stone-900 border-[rgba(226,196,133,0.18)] text-white' : 'bg-stone-50 border-stone-200'"
                                :placeholder="currentLocale === 'ar' ? 'الاسم الكريم' : 'Full Name'"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-bold mb-1.5">
                                {{ currentLocale === 'ar' ? 'رقم الهاتف أو الواتساب' : 'Phone / WhatsApp' }}
                            </label>
                            <input
                                v-model="demoForm.phone"
                                type="tel"
                                required
                                class="w-full px-4 py-2.5 rounded-xl border text-sm focus:outline-hidden focus:border-amber-500 text-left"
                                dir="ltr"
                                :class="isDark ? 'bg-stone-900 border-[rgba(226,196,133,0.18)] text-white' : 'bg-stone-50 border-stone-200'"
                                placeholder="+963 9xx xxx xxx"
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold mb-1.5">
                                    {{ currentLocale === 'ar' ? 'الدولة' : 'Country' }}
                                </label>
                                <input
                                    v-model="demoForm.country"
                                    type="text"
                                    readonly
                                    class="w-full px-3 py-2.5 rounded-xl border text-xs font-bold"
                                    :class="isDark ? 'bg-stone-900/50 border-[rgba(226,196,133,0.18)] text-stone-300' : 'bg-stone-100 border-stone-200 text-stone-700'"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-bold mb-1.5">
                                    {{ currentLocale === 'ar' ? 'عدد الطاولات' : 'Tables Count' }}
                                </label>
                                <select
                                    v-model="demoForm.tablesCount"
                                    class="w-full px-3 py-2.5 rounded-xl border text-xs font-bold"
                                    :class="isDark ? 'bg-stone-900 border-[rgba(226,196,133,0.18)] text-white' : 'bg-stone-50 border-stone-200'"
                                >
                                    <option value="1-15">1 - 15 {{ currentLocale === 'ar' ? 'طاولة' : 'tables' }}</option>
                                    <option value="15-35">15 - 35 {{ currentLocale === 'ar' ? 'طاولة' : 'tables' }}</option>
                                    <option value="35-70">35 - 70 {{ currentLocale === 'ar' ? 'طاولة' : 'tables' }}</option>
                                    <option value="70+">70+ {{ currentLocale === 'ar' ? 'طاولة' : 'tables' }}</option>
                                </select>
                            </div>
                        </div>

                        <button
                            type="submit"
                            class="w-full py-3.5 rounded-xl bg-gradient-to-r from-[#BF953F] via-[#FCF6BA] to-[#B38728] text-stone-950 font-black text-sm shadow-lg shadow-amber-500/25 hover:from-amber-400 hover:to-amber-500 active:scale-95 transition-all mt-4"
                        >
                            {{ currentLocale === 'ar' ? 'إرسال الطلب والتواصل الفوري ✨' : 'Submit Consultation Request ✨' }}
                        </button>

                        <div class="relative flex py-2 items-center">
                            <div class="grow border-t border-stone-700/40"></div>
                            <span class="shrink mx-3 text-[11px] text-stone-400 font-bold">{{ currentLocale === 'ar' ? 'أو تواصل فوراً' : 'Or connect instantly' }}</span>
                            <div class="grow border-t border-stone-700/40"></div>
                        </div>

                        <a
                            href="https://wa.me/963934578117?text=%D9%85%D8%B1%D8%AD%D8%A8%D8%A7%D9%8B%D8%8C%20%D8%A3%D8%B1%D8%BA%D8%A8%20%D9%81%D9%8A%20%D8%AD%D8%AC%D8%B2%20%D8%AC%D9%84%D8%B3%D8%A9%20%D8%AA%D8%AC%D8%B1%D9%8A%D8%A8%D9%8A%D8%A9%20%D9%81%D9%88%D8%B1%D9%8A%D8%A9%20%D9%84%D9%85%D8%B7%D8%B9%D9%85%D9%8A%20%D8%B9%D8%A8%D8%B1%20%D9%85%D9%86%D8%B8%D9%88%D9%85%D8%A9%20%D8%AA%D8%AF%D9%84%D9%91%D9%84"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="w-full py-3 rounded-xl bg-[#25D366] hover:bg-[#20ba59] text-white font-bold text-xs sm:text-sm shadow-md shadow-emerald-500/20 active:scale-95 transition-all flex items-center justify-center gap-2"
                        >
                            <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24">
                                <path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.711 2.598 2.664-.698c.969.587 1.961.921 3.009.922 3.176 0 5.766-2.587 5.767-5.767.001-3.187-2.575-5.77-6.082-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.006c.106.005.249-.04.39.298.144.347.491 1.2.534 1.287.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.354.101.174.449.741.964 1.201.662.591 1.221.774 1.394.86s.275.072.376-.043c.101-.116.433-.506.549-.68.116-.173.231-.145.39-.087s1.011.477 1.184.564.289.13.332.202c.045.072.045.419-.099.824z"/>
                            </svg>
                            <span>{{ currentLocale === 'ar' ? 'محادثة مباشرة عبر واتساب' : 'Direct WhatsApp Chat' }} (<span dir="ltr">+963 934 578 117</span>)</span>
                        </a>
                    </form>
                </div>

                <div v-else class="text-center py-8">
                    <div class="w-16 h-16 rounded-full bg-emerald-500/20 text-emerald-400 text-3xl flex items-center justify-center mx-auto mb-4">
                        ✓
                    </div>
                    <h3 class="text-xl font-black mb-2">
                        {{ currentLocale === 'ar' ? 'تم استلام طلبك بنجاح!' : 'Request Received Successfully!' }}
                    </h3>
                    <p class="text-xs text-stone-400">
                        {{ currentLocale === 'ar' ? 'سيتواصل معك خبير تدلل عبر واتساب خلال دقائق لتفعيل المنظومة.' : 'A specialist will contact you via WhatsApp shortly to set up your restaurant.' }}
                    </p>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- 9. MINIMALIST LUXURY FOOTER -->
        <!-- ========================================================================= -->
        <footer class="border-t py-12 relative z-10 text-xs transition-colors"
            :class="isDark ? 'bg-[#03050a] border-[rgba(226,196,133,0.18)] text-stone-500' : 'bg-[#0D0D11] border-t border-[#C5A059]/35 text-stone-300'"
        >
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col md:flex-row items-center justify-between gap-6 text-center md:text-right">
                    <div class="flex items-center gap-3">
                        <img src="/images/tedallal-luxury-transparent.png" alt="تدلل" class="w-9 h-9 object-contain" />
                        <div>
                            <p class="font-black text-sm text-white">
                                {{ currentLocale === 'ar' ? 'منظومة تدلّـل الملكية للضيافة الفاخرة' : 'Tedallal Royal Hospitality Suite' }}
                            </p>
                            <p class="text-[11px] text-stone-400">
                                {{ currentLocale === 'ar' ? 'جميع الحقوق محفوظة © 2026' : 'All rights reserved © 2026' }}
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-center gap-4 sm:gap-6 font-bold text-xs">
                        <a
                            href="https://wa.me/963934578117?text=%D9%85%D8%B1%D8%AD%D8%A8%D8%A7%D9%8B%D8%8C%20%D8%A3%D8%B1%D8%BA%D8%A8%20%D9%81%D9%8A%20%D9%85%D8%B9%D8%B1%D9%81%D8%A9%20%D8%AA%D9%81%D8%A7%D8%B5%D9%8A%D9%84%20%D9%85%D9%86%D8%B8%D9%88%D9%85%D8%A9%20%D8%AA%D8%AF%D9%84%D9%91%D9%84%20%D9%84%D9%84%D9%85%D8%B7%D8%A7%D8%B9%D9%85"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-1.5 text-[#25D366] hover:text-emerald-300 transition-colors"
                        >
                            <span>💬</span>
                            <span>{{ currentLocale === 'ar' ? 'واتساب:' : 'WhatsApp:' }} <span dir="ltr">+963 934 578 117</span></span>
                        </a>
                        <a
                            href="tel:+963934578117"
                            class="inline-flex items-center gap-1.5 text-stone-300 hover:text-amber-400 transition-colors"
                        >
                            <span>📞</span>
                            <span dir="ltr">+963 934 578 117</span>
                        </a>
                        <Link href="/login" class="hover:text-amber-400 transition-colors">
                            {{ currentLocale === 'ar' ? 'بوابة الإدارة' : 'Admin Portal' }}
                        </Link>
                        <a href="#floor-to-kitchen" class="hover:text-amber-400 transition-colors">
                            {{ currentLocale === 'ar' ? 'شاشة المطبخ KDS' : 'KDS Portal' }}
                        </a>
                        <button type="button" class="hover:text-amber-400 transition-colors" @click="isDemoModalOpen = true">
                            {{ currentLocale === 'ar' ? 'طلب حساب جديد' : 'New Account' }}
                        </button>
                        <a
                            href="/presentation"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="hover:text-amber-400 transition-colors flex items-center gap-1 font-bold text-amber-400"
                        >
                            <span>📊</span>
                            <span>{{ currentLocale === 'ar' ? 'الملف التعريفي' : 'Pitch Deck' }}</span>
                            <span class="text-[10px]">↗</span>
                        </a>
                    </div>

                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full border border-stone-800 bg-stone-900 text-[11px] font-bold text-emerald-400">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span>{{ currentLocale === 'ar' ? 'كافة الأنظمة السحابية تعمل بكفاءة 99.9%' : 'All Systems Operational' }}</span>
                    </div>
                </div>
            </div>
        </footer>

        <!-- Floating Direct WhatsApp Button (Official Emerald Green & Royal Pulse) -->
        <a
            href="https://wa.me/963934578117?text=%D9%85%D8%B1%D8%AD%D8%A8%D8%A7%D9%8B%D8%8C%20%D8%A3%D8%B1%D8%BA%D8%A8%20%D9%81%D9%8A%20%D9%85%D8%B9%D8%B1%D9%81%D8%A9%20%D8%AA%D9%81%D8%A7%D8%B5%D9%8A%D9%84%20%D9%88%D8%B9%D8%B1%D8%B6%20%D8%AA%D8%AC%D8%B1%D9%8A%D8%A8%D9%8A%20%D9%84%D9%85%D9%86%D8%B8%D9%88%D9%85%D8%A9%20%D8%AA%D8%AF%D9%84%D9%91%D9%84"
            target="_blank"
            rel="noopener noreferrer"
            class="fixed bottom-6 z-50 inline-flex items-center gap-2.5 px-4 py-3 rounded-full bg-[#25D366] hover:bg-[#20ba59] text-white font-bold text-xs sm:text-sm shadow-xl shadow-emerald-600/35 hover:scale-105 active:scale-95 transition-all duration-300 select-none cursor-pointer group"
            :class="currentLocale === 'ar' ? 'left-6' : 'right-6'"
            title="تواصل مباشر عبر واتساب (+963 934 578 117)"
        >
            <span class="relative flex h-3 w-3">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-white opacity-75"></span>
                <span class="relative inline-flex rounded-full h-3 w-3 bg-white"></span>
            </span>
            <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24">
                <path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.711 2.598 2.664-.698c.969.587 1.961.921 3.009.922 3.176 0 5.766-2.587 5.767-5.767.001-3.187-2.575-5.77-6.082-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.006c.106.005.249-.04.39.298.144.347.491 1.2.534 1.287.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.354.101.174.449.741.964 1.201.662.591 1.221.774 1.394.86s.275.072.376-.043c.101-.116.433-.506.549-.68.116-.173.231-.145.39-.087s1.011.477 1.184.564.289.13.332.202c.045.072.045.419-.099.824z"/>
            </svg>
            <span class="font-extrabold tracking-wide">{{ currentLocale === 'ar' ? 'محادثة واتساب مباشرة' : 'WhatsApp Direct' }}</span>
        </a>
    </div>
</template>

<style scoped>
.scrollbar-hide::-webkit-scrollbar {
    display: none;
}
.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
@keyframes bounce-short {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-4px); }
}
.animate-bounce-short {
    animation: bounce-short 2s ease-in-out infinite;
}

/* ========================================================================= */
/* TEDALLAL ULTRA-LUXURY GLASSMORPHISM SYSTEM (Haute Crystal & Gold)         */
/* ========================================================================= */
.glass-ultra {
    background: radial-gradient(120% 120% at 50% 0%, rgba(32, 28, 22, 0.72) 0%, rgba(12, 12, 15, 0.92) 100%);
    backdrop-filter: blur(28px) saturate(190%) contrast(105%);
    -webkit-backdrop-filter: blur(28px) saturate(190%) contrast(105%);
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.9), inset 0 1px 1px 0 rgba(255, 255, 255, 0.15);
    position: relative;
    border: 1px solid rgba(226, 196, 133, 0.15);
    transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
}
.glass-ultra::before {
    content: '';
    position: absolute;
    inset: -1px;
    border-radius: inherit;
    padding: 1px;
    background: linear-gradient(
        135deg,
        rgba(255, 255, 255, 0.25) 0%,
        rgba(226, 196, 133, 0.40) 25%,
        rgba(255, 255, 255, 0.03) 60%,
        rgba(226, 196, 133, 0.15) 100%
    );
    -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
    mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
    -webkit-mask-composite: xor;
    mask-composite: exclude;
    pointer-events: none;
}
.glass-ultra:hover {
    border-color: rgba(226, 196, 133, 0.35);
    box-shadow: 0 30px 60px -10px rgba(0, 0, 0, 0.95), 0 0 15px 1px rgba(226, 196, 133, 0.08), inset 0 1px 1px 0 rgba(255, 255, 255, 0.25);
}

.gold-glow-node {
    background: radial-gradient(circle, rgba(226, 196, 133, 0.06) 0%, transparent 70%);
    filter: blur(45px);
    pointer-events: none;
}

.flash-active-gold {
    animation: flashGold 0.85s ease-out;
}
@keyframes flashGold {
    0% { box-shadow: 0 0 0 0 rgba(226, 196, 133, 0.6); }
    70% { box-shadow: 0 0 20px 5px rgba(226, 196, 133, 0.2); }
    100% { box-shadow: 0 0 0 0 rgba(226, 196, 133, 0); }
}

/* ========================================================================= */
/* TEDALLAL ULTRA-LUXURY LIGHT MODE DESIGN SYSTEM                            */
/* (Warm Alabaster Porcelain, Imperial Antique Gold, Obsidian Contrast)     */
/* ========================================================================= */

.light-mode-theme {
    color: #12110F;
}

/* 1. Haute Imperial Glassmorphism in Light Mode (Crisp Porcelain, Calm & Elegant) */
.light-mode-theme .glass-ultra {
    background: rgba(255, 255, 255, 0.94) !important;
    backdrop-filter: blur(20px) saturate(150%) !important;
    -webkit-backdrop-filter: blur(20px) saturate(150%) !important;
    border: 1px solid rgba(197, 160, 89, 0.22) !important;
    box-shadow: 
        0 10px 30px -5px rgba(28, 25, 23, 0.06),
        0 2px 8px -2px rgba(0, 0, 0, 0.03),
        inset 0 1px 0 rgba(255, 255, 255, 0.9) !important;
}

.light-mode-theme .glass-ultra::before {
    display: none !important;
}

.light-mode-theme .glass-ultra:hover {
    border-color: rgba(180, 140, 60, 0.45) !important;
    box-shadow: 
        0 16px 40px -8px rgba(28, 25, 23, 0.09),
        0 4px 12px -2px rgba(0, 0, 0, 0.03),
        inset 0 1px 1px 0 rgba(255, 255, 255, 1) !important;
}

/* 2. Text High Contrast & Readability */
.light-mode-theme h1,
.light-mode-theme h2,
.light-mode-theme h3,
.light-mode-theme h4,
.light-mode-theme .text-stone-900,
.light-mode-theme .text-white {
    color: #12110F !important;
}

.light-mode-theme .text-stone-300,
.light-mode-theme .text-slate-300,
.light-mode-theme .text-stone-400,
.light-mode-theme .text-slate-400 {
    color: #38322B !important;
}

.light-mode-theme .text-stone-500,
.light-mode-theme .text-stone-600 {
    color: #524B40 !important;
}

/* Burnished Antique Gold Typography (WCAG Contrast 5:1+) */
.light-mode-theme .text-amber-400,
.light-mode-theme .text-amber-300,
.light-mode-theme .text-amber-500,
.light-mode-theme .text-yellow-400,
.light-mode-theme .text-amber-400\/90,
.light-mode-theme .text-amber-400\/80 {
    color: #8C6010 !important;
}

/* 3. Capsule Badges in Light Mode */
.light-mode-theme span[class*="bg-amber-500/10"],
.light-mode-theme span[class*="bg-amber-500/15"],
.light-mode-theme span[class*="bg-amber-500/20"] {
    background-color: rgba(212, 175, 55, 0.14) !important;
    border-color: rgba(180, 140, 60, 0.45) !important;
    color: #8C6010 !important;
    font-weight: 800 !important;
}

/* 4. Sub-cards and inner containers */
.light-mode-theme .bg-black\/40,
.light-mode-theme .bg-black\/60,
.light-mode-theme .bg-black\/70,
.light-mode-theme .bg-stone-900,
.light-mode-theme .bg-stone-900\/50,
.light-mode-theme .bg-stone-900\/60,
.light-mode-theme .bg-stone-900\/90 {
    background-color: rgba(255, 255, 255, 0.92) !important;
    border-color: rgba(197, 160, 89, 0.30) !important;
    color: #12110F !important;
}

.light-mode-theme .border-white\/10,
.light-mode-theme .border-white\/20 {
    border-color: rgba(197, 160, 89, 0.28) !important;
}

/* 5. Inputs & Dropdowns */
.light-mode-theme input,
.light-mode-theme select {
    background-color: #FFFFFF !important;
    border-color: rgba(197, 160, 89, 0.40) !important;
    color: #12110F !important;
    box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.05);
}
.light-mode-theme input:focus,
.light-mode-theme select:focus {
    border-color: #B48C3C !important;
    box-shadow: 0 0 0 3px rgba(180, 140, 60, 0.20) !important;
}

/* 6. Footer Luxury Anchor */
.light-mode-theme footer {
    background-color: #0E0E12 !important;
    border-top-color: rgba(197, 160, 89, 0.35) !important;
    color: #A3A19C !important;
}
.light-mode-theme footer .text-white,
.light-mode-theme footer .text-stone-900,
.light-mode-theme footer p.font-black {
    color: #FBF8F5 !important;
}
.light-mode-theme footer a {
    color: #C5A059 !important;
}
.light-mode-theme footer a:hover {
    color: #E6C875 !important;
}

/* 7. Action Secondary Buttons */
.light-mode-theme button[class*="border-white\/20"],
.light-mode-theme a[class*="border-white\/20"] {
    background-color: #FFFFFF !important;
    border-color: rgba(197, 160, 89, 0.45) !important;
    color: #12110F !important;
    box-shadow: 0 2px 8px rgba(180, 140, 60, 0.10) !important;
}
.light-mode-theme button[class*="border-white\/20"]:hover,
.light-mode-theme a[class*="border-white\/20"]:hover {
    background-color: #FAF5EB !important;
    border-color: #B48C3C !important;
}
</style>
