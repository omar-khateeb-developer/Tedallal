<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { translations, type Locale } from '@/locales/landing';
import { useTheme } from '@/composables/useTheme';

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
// Theme System (Light / Dark Mode)
// =========================================================================
const { isDark, toggleTheme, setTheme, initTheme } = useTheme();

// =========================================================================
// Bilingual System (Arabic / English)
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
    } catch {
        // ignore
    }
}

// =========================================================================
// Arab Multi-Currency & Geolocation Detection System
// =========================================================================
export interface CountryCurrencyConfig {
    code: string;
    countryName: { ar: string; en: string };
    currencyName: { ar: string; en: string };
    symbol: { ar: string; en: string };
    flag: string;
    rate: number; // 1 SAR = X
    decimals: number;
}

const supportedArabCurrencies: Record<string, CountryCurrencyConfig> = {
    SY: {
        code: 'SY',
        countryName: { ar: 'الجمهورية العربية السورية', en: 'Syria' },
        currencyName: { ar: 'ليرة سورية', en: 'Syrian Pound' },
        symbol: { ar: 'ل.س', en: 'SYP' },
        flag: '🇸🇾',
        rate: 1.0, // Primary Base Currency!
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

function resolveArabCountryCode(code: string | null | undefined): string {
    if (!code) return 'SY';
    const c = code.toUpperCase();
    if (supportedArabCurrencies[c]) return c;
    return 'SY';
}

function detectTimezoneCountry(): string | null {
    try {
        const tz = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
        if (tz.includes('Damascus')) return 'SY';
        if (tz.includes('Riyadh')) return 'SA';
        if (tz.includes('Dubai')) return 'AE';
        if (tz.includes('Kuwait')) return 'KW';
        if (tz.includes('Qatar')) return 'QA';
        if (tz.includes('Bahrain')) return 'BH';
        if (tz.includes('Muscat')) return 'OM';
        if (tz.includes('Amman')) return 'JO';
        if (tz.includes('Cairo')) return 'EG';
        if (tz.includes('Baghdad')) return 'IQ';
    } catch {
        return null;
    }
    return null;
}

// Demo Modal State (declared early for setter sync)
const isDemoModalOpen = ref(false);
const demoForm = ref({
    restaurantName: '',
    contactPerson: '',
    phone: '',
    country: 'الجمهورية العربية السورية',
    tablesCount: '15-35',
});
const isSubmitted = ref(false);

function setCurrency(code: string) {
    if (supportedArabCurrencies[code]) {
        currentCountryCode.value = code;
        try {
            localStorage.setItem('tedallal_currency', code);
        } catch {
            // ignore
        }
        demoForm.value.country = supportedArabCurrencies[code].countryName[currentLocale.value];
        isCurrencyMenuOpen.value = false;
    }
}

function formatPrice(amountInSar: number, forceDecimals?: number): string {
    const rate = currentCurrency.value.rate;
    const dec = forceDecimals !== undefined ? forceDecimals : currentCurrency.value.decimals;
    const val = amountInSar * rate;
    if (dec === 0) {
        return Math.round(val).toLocaleString('en-US');
    }
    return val.toLocaleString('en-US', { minimumFractionDigits: dec, maximumFractionDigits: dec });
}

// Navigation state
const isMobileMenuOpen = ref(false);
const activeNav = ref('home');

// Workflow Operating Mode Switcher (Postpaid vs Prepaid)
const selectedOperatingMode = ref<'postpaid' | 'prepaid'>('postpaid');
const activeWorkflow = computed(() => {
    return selectedOperatingMode.value === 'prepaid'
        ? t.value.workflow.prepaid
        : t.value.workflow.postpaid;
});

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
}

const menuItems = computed<MenuItemSim[]>(() => [
    {
        id: 1,
        name: currentLocale.value === 'ar' ? 'كباب حلبي فاخر بالكرز' : 'Aleppo Cherry Kebab',
        category: 'grills',
        price: 85000,
        description: currentLocale.value === 'ar'
            ? 'لحم بلدي طازج مفروم مع حبّات الكرز والبهارات الحلبية التراثية وصنوبر محمص'
            : 'Fresh minced lamb glazed with tart cherries, roasted pine nuts, and heritage Aleppo spices',
        image: '/storage/menu/aleppo-kebab.jpg',
        badge: currentLocale.value === 'ar' ? 'الأكثر طلباً 🔥' : 'Top Seller 🔥',
    },
    {
        id: 2,
        name: currentLocale.value === 'ar' ? 'أوصال لحم عجل مشوي' : 'Charcoal Grilled Veal Skewers',
        category: 'grills',
        price: 75000,
        description: currentLocale.value === 'ar'
            ? 'قطع لحم طرية متبلة بزيت الزيتون وإكليل الجبل ومشوية على الفحم الطبيعي'
            : 'Tender veal cubes marinated in virgin olive oil & rosemary, grilled over natural charcoal',
        image: '/storage/menu/aleppo-grills.jpg',
    },
    {
        id: 3,
        name: currentLocale.value === 'ar' ? 'كبة مشوية حلبية بالجوز' : 'Grilled Aleppo Kibbeh with Walnuts',
        category: 'grills',
        price: 55000,
        description: currentLocale.value === 'ar'
            ? 'أقراص الكبة المحشوة باللحم المفروم والدهن والرمان والجوز البلدي'
            : 'Bulgur shell stuffed with spiced minced meat, pomegranate molasses & toasted walnuts',
        image: '/storage/menu/aleppo-kibbeh.jpg',
        badge: currentLocale.value === 'ar' ? 'تراثي مميز' : 'Chef Special',
    },
    {
        id: 4,
        name: currentLocale.value === 'ar' ? 'مقبلات مشكلة شهباء' : 'Assorted Grand Mezza Platter',
        category: 'appetizers',
        price: 42000,
        description: currentLocale.value === 'ar'
            ? 'متبل باذنجان، حمص بيروتي، يلنجي، ومحمرة بالجوز'
            : 'Smoked moutabal, Beirut hummus, stuffed vine leaves, and spicy walnut muhammara',
        image: '/storage/menu/aleppo-appetizers.jpg',
    },
    {
        id: 5,
        name: currentLocale.value === 'ar' ? 'كنافة نابلسية بالجبن الفاخر' : 'Hot Nabulsi Cheese Kunafa',
        category: 'desserts',
        price: 38000,
        description: currentLocale.value === 'ar'
            ? 'كنافة ساخنة بجبنة عكاوية أصيلة وفستق حلبي مع القطر الخفيف'
            : 'Crisp golden spun pastry layered with Akkawi melted cheese, pistachio & fragrant syrup',
        image: '/storage/menu/aleppo-desserts.jpg',
        badge: currentLocale.value === 'ar' ? 'ساخن ولذيذ' : 'Served Warm',
    },
    {
        id: 6,
        name: currentLocale.value === 'ar' ? 'عصير رمان طبيعي مثلج' : 'Fresh Iced Pomegranate Juice',
        category: 'drinks',
        price: 22000,
        description: currentLocale.value === 'ar'
            ? 'عصير رمان طازج 100% مع حبات الرمان الطبيعية'
            : '100% freshly pressed ruby pomegranate with hand-picked seeds',
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
    id: number;
    table: string;
    time: string;
    items: Array<{ name: string; qty: number; note?: string }>;
    status: 'new' | 'cooking' | 'ready' | 'served';
    total: number;
}

const kdsOrders = ref<KdsOrderSim[]>([
    {
        id: 101,
        table: 'طاولة 04',
        time: 'منذ 3 دقائق',
        items: [
            { name: 'كباب حلبي بالكرز', qty: 2, note: 'بدون بصل مع صوص إضافي' },
            { name: 'مقبلات مشكلة', qty: 1 },
            { name: 'عصير رمان مثلج', qty: 2 },
        ],
        status: 'new',
        total: 256000,
    },
    {
        id: 102,
        table: 'طاولة 12',
        time: 'منذ 8 دقائق',
        items: [
            { name: 'أوصال لحم عجل مشوي', qty: 1 },
            { name: 'كنافة نابلسية بالجبن', qty: 1, note: 'قطر خفيف جداً' },
        ],
        status: 'cooking',
        total: 113000,
    },
    {
        id: 103,
        table: 'طاولة 08',
        time: 'منذ 14 دقيقة',
        items: [
            { name: 'كبة مشوية بالجوز', qty: 2 },
            { name: 'عصير رمان طبيعي', qty: 2 },
        ],
        status: 'ready',
        total: 154000,
    },
]);

function advanceKdsOrder(id: number) {
    const order = kdsOrders.value.find(o => o.id === id);
    if (!order) return;
    if (order.status === 'new') order.status = 'cooking';
    else if (order.status === 'cooking') order.status = 'ready';
    else if (order.status === 'ready') order.status = 'served';
    else if (order.status === 'served') order.status = 'new';
}

// Live Simulated Order Notifications
const liveToasts = computed(() => {
    if (currentLocale.value === 'en') {
        return [
            { table: 'Table 05', text: `Confirmed order (2x Aleppo Kebab, Mezza) for ${formatPrice(212000)} ${currentCurrencySymbol.value}`, time: 'Just now' },
            { table: 'Kitchen & Chef', text: 'Table 12 order prepared in record time: 7 minutes! ⚡', time: '1 min ago' },
            { table: 'Table 08', text: 'Applied promo TEDALLAL15 for instant 15% discount', time: '2 mins ago' },
            { table: 'Table 03', text: 'Added 3 items to cart without waiting for a server', time: '4 mins ago' },
        ];
    }
    return [
        { table: 'طاولة 05', text: `أكدت طلب (2x كباب حلبي، مقبلات مشكلة) بقيمة ${formatPrice(212000)} ${currentCurrencySymbol.value}`, time: 'الآن' },
        { table: 'المطبخ والشيف', text: 'تم تجهيز طلب طاولة 12 في زمن قياسي: 7 دقائق فقط! ⚡', time: 'قبل دقيقة' },
        { table: 'طاولة 08', text: 'فعلت كود خصم TEDALLAL15 وحصلت على خصم فوري 15%', time: 'قبل دقيقتين' },
        { table: 'طاولة 03', text: 'أضافت 3 أصناف جديدة إلى سلة الطاولة بدون استدعاء النادل', time: 'قبل 4 دقائق' },
    ];
});

const currentToastIndex = ref(0);
const showToast = ref(true);
const isToastPermanentlyDismissed = ref(false);
let toastInterval: ReturnType<typeof setInterval> | null = null;

function dismissToast() {
    isToastPermanentlyDismissed.value = true;
    showToast.value = false;
    if (toastInterval) {
        clearInterval(toastInterval);
        toastInterval = null;
    }
}

// =========================================================================
// INTERACTIVE HERO TOGGLE EXPERIENCE STATE
// =========================================================================
const heroMode = ref<'traditional' | 'smart'>('traditional');
const isEmeraldFlashing = ref(false);
let heroAutoSwitchTimer: ReturnType<typeof setTimeout> | null = null;
let heroFlashTimeout: ReturnType<typeof setTimeout> | null = null;

function setHeroMode(mode: 'traditional' | 'smart', isManual = true) {
    if (isManual && heroAutoSwitchTimer) {
        clearTimeout(heroAutoSwitchTimer);
        heroAutoSwitchTimer = null;
    }
    if (heroMode.value === mode) return;

    heroMode.value = mode;

    if (mode === 'smart') {
        triggerEmeraldFlash();
    }
}

function triggerEmeraldFlash() {
    isEmeraldFlashing.value = true;
    if (heroFlashTimeout) clearTimeout(heroFlashTimeout);
    heroFlashTimeout = setTimeout(() => {
        isEmeraldFlashing.value = false;
    }, 900);
}

// Interactive Analytics State & Live Traffic Simulation
const selectedAnalyticsFilter = ref<'weekend' | 'weekday'>('weekend');
const dynamicTrafficTick = ref(0);
let trafficInterval: ReturnType<typeof setInterval> | null = null;

const peakHoursData = computed(() => {
    const tick = dynamicTrafficTick.value;
    if (selectedAnalyticsFilter.value === 'weekend') {
        return [
            { time: currentLocale.value === 'ar' ? '1 م' : '1 PM', height: 35 + ((tick % 2 === 0) ? 3 : -1), orders: 24, volume: currentLocale.value === 'ar' ? 'خفيف' : 'Light' },
            { time: currentLocale.value === 'ar' ? '3 م' : '3 PM', height: 55 + ((tick % 3 === 0) ? 4 : -2), orders: 48, volume: currentLocale.value === 'ar' ? 'متوسط' : 'Moderate' },
            { time: currentLocale.value === 'ar' ? '5 م' : '5 PM', height: 45 + ((tick % 2 === 1) ? 3 : 0), orders: 36, volume: currentLocale.value === 'ar' ? 'متوسط' : 'Moderate' },
            { time: currentLocale.value === 'ar' ? '7 م' : '7 PM', height: 78 + ((tick % 3 === 1) ? 4 : -1), orders: 74, volume: currentLocale.value === 'ar' ? 'مرتفع 🔥' : 'High 🔥' },
            { time: currentLocale.value === 'ar' ? '9 م' : '9 PM', height: 96 + ((tick % 2 === 0) ? 2 : -2), orders: 98, isPeak: true, volume: currentLocale.value === 'ar' ? 'قمة الذروة 🔥🔥' : 'Peak Rush 🔥🔥' },
            { time: currentLocale.value === 'ar' ? '11 م' : '11 PM', height: 82 + ((tick % 2 === 1) ? 3 : -1), orders: 79, volume: currentLocale.value === 'ar' ? 'مرتفع' : 'High' },
            { time: currentLocale.value === 'ar' ? '1 ص' : '1 AM', height: 40 + ((tick % 3 === 2) ? 3 : 0), orders: 32, volume: currentLocale.value === 'ar' ? 'هادئ' : 'Quiet' },
        ];
    }
    return [
        { time: currentLocale.value === 'ar' ? '1 م' : '1 PM', height: 25 + ((tick % 2 === 0) ? 2 : 0), orders: 18, volume: currentLocale.value === 'ar' ? 'هادئ' : 'Quiet' },
        { time: currentLocale.value === 'ar' ? '3 م' : '3 PM', height: 38 + ((tick % 3 === 0) ? 3 : -1), orders: 28, volume: currentLocale.value === 'ar' ? 'خفيف' : 'Light' },
        { time: currentLocale.value === 'ar' ? '5 م' : '5 PM', height: 32 + ((tick % 2 === 1) ? 2 : 0), orders: 22, volume: currentLocale.value === 'ar' ? 'خفيف' : 'Light' },
        { time: currentLocale.value === 'ar' ? '7 م' : '7 PM', height: 62 + ((tick % 3 === 1) ? 3 : 0), orders: 46, volume: currentLocale.value === 'ar' ? 'متوسط' : 'Moderate' },
        { time: currentLocale.value === 'ar' ? '9 م' : '9 PM', height: 84 + ((tick % 2 === 0) ? 2 : -2), orders: 68, isPeak: true, volume: currentLocale.value === 'ar' ? 'ذروة منتصف الأسبوع 🔥' : 'Weekday Peak 🔥' },
        { time: currentLocale.value === 'ar' ? '11 م' : '11 PM', height: 58 + ((tick % 2 === 1) ? 3 : 0), orders: 42, volume: currentLocale.value === 'ar' ? 'متوسط' : 'Moderate' },
        { time: currentLocale.value === 'ar' ? '1 ص' : '1 AM', height: 22 + ((tick % 3 === 2) ? 2 : 0), orders: 14, volume: currentLocale.value === 'ar' ? 'هادئ' : 'Quiet' },
    ];
});

const topDishesData = computed(() => {
    if (selectedAnalyticsFilter.value === 'weekend') {
        return [
            { name: currentLocale.value === 'ar' ? 'كباب حلبي بالكرز' : 'Aleppo Cherry Kebab', percent: 42, color: 'bg-amber-500', orders: 142 },
            { name: currentLocale.value === 'ar' ? 'أوصال لحم مشوي' : 'Grilled Veal Skewers', percent: 28, color: 'bg-orange-500', orders: 98 },
            { name: currentLocale.value === 'ar' ? 'كبة مشوية بالجوز' : 'Grilled Walnut Kibbeh', percent: 18, color: 'bg-emerald-500', orders: 65 },
            { name: currentLocale.value === 'ar' ? 'كنافة نابلسية بالجبن' : 'Nabulsi Cheese Kunafa', percent: 12, color: 'bg-amber-300', orders: 45 },
        ];
    }
    return [
        { name: currentLocale.value === 'ar' ? 'كباب حلبي بالكرز' : 'Aleppo Cherry Kebab', percent: 36, color: 'bg-amber-500', orders: 95 },
        { name: currentLocale.value === 'ar' ? 'أوصال لحم مشوي' : 'Grilled Veal Skewers', percent: 32, color: 'bg-orange-500', orders: 84 },
        { name: currentLocale.value === 'ar' ? 'كبة مشوية بالجوز' : 'Grilled Walnut Kibbeh', percent: 20, color: 'bg-emerald-500', orders: 52 },
        { name: currentLocale.value === 'ar' ? 'كنافة نابلسية بالجبن' : 'Nabulsi Cheese Kunafa', percent: 12, color: 'bg-amber-300', orders: 31 },
    ];
});

// CPA-Grade Financial Ledger Simulation for Tab 4
const financialKpis = computed(() => {
    const isWeekend = selectedAnalyticsFilter.value === 'weekend';
    if (isWeekend) {
        return {
            netSales: 3450000,
            grossSales: 3620000,
            discounts: 170000,
            ordersCount: 420,
            aov: 8214,
            dineInNet: 2450000,
            takeawayNet: 1000000,
            cancelledOrders: 0,
            accuracyBadge: currentLocale.value === 'ar' ? 'دقة محاسبية 0% خطأ' : '0% Float Drift (CPA-Grade)',
        };
    }
    return {
        netSales: 1980000,
        grossSales: 2060000,
        discounts: 80000,
        ordersCount: 260,
        aov: 7615,
        dineInNet: 1380000,
        takeawayNet: 600000,
        cancelledOrders: 0,
        accuracyBadge: currentLocale.value === 'ar' ? 'دقة محاسبية 0% خطأ' : '0% Float Drift (CPA-Grade)',
    };
});

// ROI Calculator State
const tableCount = ref(30);
const dailyOrdersPerTable = ref(6);

const monthlyOrders = computed(() => tableCount.value * dailyOrdersPerTable.value * 30);
const yearlyPaperSavings = computed(() => tableCount.value * 450000);
const timeSavedHoursMonthly = computed(() => Math.round((monthlyOrders.value * 10) / 60));
const estimatedRevenueBoost = computed(() => Math.round(monthlyOrders.value * 0.22 * 95000));

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
    }, 2500);
}

// FAQ Accordion State
const openFaqIndex = ref<number | null>(0);
function toggleFaq(index: number) {
    openFaqIndex.value = openFaqIndex.value === index ? null : index;
}

// Global click-outside listener
function handleGlobalClick(e: MouseEvent) {
    const target = e.target as HTMLElement;
    if (!target.closest('[data-currency-dropdown]')) {
        isCurrencyMenuOpen.value = false;
    }
}

onMounted(async () => {
    // 0. Initialize theme preference (supports ?theme=light/dark or defaults to dark)
    try {
        const urlParams = new URLSearchParams(window.location.search);
        const themeParam = urlParams.get('theme');
        if (themeParam === 'light' || themeParam === 'dark') {
            setTheme(themeParam);
        } else {
            initTheme('dark');
        }
    } catch {
        initTheme('dark');
    }

    // 1. Language preference check
    try {
        const savedLocale = localStorage.getItem('tedallal_locale') as Locale | null;
        if (savedLocale && (savedLocale === 'ar' || savedLocale === 'en')) {
            currentLocale.value = savedLocale;
        } else {
            const navLang = navigator.language.toLowerCase();
            if (navLang.startsWith('en')) {
                currentLocale.value = 'en';
            }
        }
    } catch {
        // default ar
    }

    // 2. Currency preference check
    try {
        const savedCurr = localStorage.getItem('tedallal_currency');
        if (savedCurr && supportedArabCurrencies[savedCurr]) {
            currentCountryCode.value = savedCurr;
            demoForm.value.country = supportedArabCurrencies[savedCurr].countryName[currentLocale.value];
        } else if (props.detectedCountry) {
            const resolved = resolveArabCountryCode(props.detectedCountry);
            currentCountryCode.value = resolved;
            demoForm.value.country = supportedArabCurrencies[resolved].countryName[currentLocale.value];
        } else {
            const tz = detectTimezoneCountry();
            if (tz) {
                currentCountryCode.value = tz;
                demoForm.value.country = supportedArabCurrencies[tz].countryName[currentLocale.value];
            } else {
                // Non-blocking geo API
                fetch('https://api.country.is/', { signal: AbortSignal.timeout(2000) })
                    .then(r => r.json())
                    .then(data => {
                        if (data?.country) {
                            const res = resolveArabCountryCode(data.country);
                            currentCountryCode.value = res;
                            demoForm.value.country = supportedArabCurrencies[res].countryName[currentLocale.value];
                        }
                    })
                    .catch(() => {});
            }
        }
    } catch {
        // keep SA
    }

    // Attach click listener
    window.addEventListener('click', handleGlobalClick);

    // Auto-Demo on load: show traditional mode for 1.8s, then smoothly auto-switch to smart mode
    heroAutoSwitchTimer = setTimeout(() => {
        setHeroMode('smart', false);
        heroAutoSwitchTimer = null;
    }, 1800);

    // Live tickers
    toastInterval = setInterval(() => {
        if (isToastPermanentlyDismissed.value) return;
        showToast.value = false;
        setTimeout(() => {
            if (!isToastPermanentlyDismissed.value) {
                currentToastIndex.value = (currentToastIndex.value + 1) % liveToasts.value.length;
                showToast.value = true;
            }
        }, 400);
    }, 7000);

    trafficInterval = setInterval(() => {
        dynamicTrafficTick.value++;
    }, 2800);
});

onUnmounted(() => {
    window.removeEventListener('click', handleGlobalClick);
    if (heroAutoSwitchTimer) clearTimeout(heroAutoSwitchTimer);
    if (heroFlashTimeout) clearTimeout(heroFlashTimeout);
    if (toastInterval) clearInterval(toastInterval);
    if (trafficInterval) clearInterval(trafficInterval);
});
</script>

<template>
    <Head>
        <title>{{ t.meta.title }}</title>
        <meta name="description" :content="t.meta.description" />
    </Head>

    <div
        :dir="currentLocale === 'ar' ? 'rtl' : 'ltr'"
        :class="[
            currentLocale === 'ar' ? 'font-sans' : 'font-sans font-english',
            isDark ? 'dark-mode-theme' : 'light-mode-theme'
        ]"
        class="min-h-screen bg-slate-50 dark:bg-[#070b14] text-slate-900 dark:text-slate-100 selection:bg-amber-500 selection:text-slate-950 antialiased overflow-x-hidden relative transition-colors duration-300"
    >
        <!-- Ambient Glow Effects & Mesh Gradients -->
        <div class="fixed inset-0 pointer-events-none z-0 overflow-hidden ambient-glow-layer">
            <div class="absolute -top-40 right-1/4 w-[600px] h-[600px] rounded-full blur-[140px] transition-colors duration-700" :class="isDark ? 'bg-amber-500/10' : 'bg-amber-500/15'"></div>
            <div class="absolute top-[35%] -left-32 w-[500px] h-[500px] rounded-full blur-[130px] transition-colors duration-700" :class="isDark ? 'bg-orange-600/10' : 'bg-orange-500/10'"></div>
            <div class="absolute bottom-1/4 right-10 w-[550px] h-[550px] rounded-full blur-[150px] transition-colors duration-700" :class="isDark ? 'bg-emerald-600/10' : 'bg-emerald-500/10'"></div>
            <div class="absolute inset-0 bg-radial-mesh [background-size:28px_28px] transition-opacity duration-500" :class="isDark ? 'bg-[radial-gradient(#1e293b_1px,transparent_1px)] opacity-30' : 'bg-[radial-gradient(#94a3b8_1.2px,transparent_1.2px)] opacity-25'"></div>
        </div>

        <!-- ========================================================================= -->
        <!-- TOP NAVBAR -->
        <!-- ========================================================================= -->
        <header
            class="sticky top-0 z-50 backdrop-blur-xl border-b transition-all duration-300"
            :class="isDark ? 'bg-[#070b14]/80 border-white/5 shadow-none' : 'bg-white/90 border-slate-200/90 shadow-xs'"
        >
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-20">

                    <!-- Brand Logo -->
                    <div class="flex items-center gap-3">
                        <Link href="/" class="flex items-center gap-3 group">
                            <div class="relative w-11 h-11 rounded-2xl bg-gradient-to-tr from-amber-500 to-orange-500 p-[2px] shadow-lg shadow-amber-500/20 group-hover:shadow-amber-500/40 transition-all duration-300">
                                <div class="w-full h-full bg-[#0b101d] rounded-2xl flex items-center justify-center overflow-hidden">
                                    <img
                                        src="/images/tedallal-brand-logo.jpg"
                                        alt="Tedallal"
                                        class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300"
                                        onerror="this.src='/images/logo.jpg'"
                                    />
                                </div>
                            </div>
                            <div class="flex flex-col">
                                <div class="flex items-center gap-2">
                                    <span class="text-2xl font-bold tracking-tight bg-gradient-to-r from-amber-400 via-amber-200 to-amber-500 bg-clip-text text-transparent">
                                        {{ currentLocale === 'ar' ? 'تدلّل' : 'Tedallal' }}
                                    </span>
                                    <span class="text-[10px] font-semibold uppercase tracking-wider px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                        {{ currentLocale === 'ar' ? 'Tedallal' : 'Platform' }}
                                    </span>
                                </div>
                                <span class="text-[11px] text-slate-400 font-medium">{{ t.nav.brandSubtitle }}</span>
                            </div>
                        </Link>
                    </div>

                    <!-- Desktop Nav Links -->
                    <nav class="hidden lg:flex items-center gap-5 xl:gap-6 text-sm font-medium text-slate-300">
                        <a href="#how-it-works" class="hover:text-amber-400 transition-colors">{{ t.nav.howItWorks }}</a>
                        <a href="#solutions" class="hover:text-amber-400 transition-colors">{{ t.nav.solutions }}</a>
                        <a href="#interactive-demo" class="hover:text-amber-400 transition-colors flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-amber-400 animate-ping"></span>
                            {{ t.nav.interactiveDemo }}
                        </a>
                        <a href="#features" class="hover:text-amber-400 transition-colors">{{ t.nav.features }}</a>
                        <a href="#directory" class="hover:text-amber-400 transition-colors">{{ t.nav.directory }}</a>
                        <a href="#roi-calculator" class="hover:text-amber-400 transition-colors">{{ t.nav.roiCalculator }}</a>
                        <a href="#comparison" class="hover:text-amber-400 transition-colors">{{ t.nav.comparison }}</a>
                        <a href="#faqs" class="hover:text-amber-400 transition-colors">{{ t.nav.faqs }}</a>
                    </nav>

                    <!-- Language, Currency & Theme Controls + Auth CTAs -->
                    <div class="hidden sm:flex items-center gap-3">
                        <!-- Theme Toggle Button (Light / Dark) -->
                        <button
                            id="theme-toggle-btn"
                            @click="toggleTheme"
                            type="button"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl transition-all shadow-sm border cursor-pointer select-none"
                            :class="isDark
                                ? 'bg-slate-900/90 hover:bg-slate-800 border-slate-700/80 hover:border-amber-500/50 text-slate-200'
                                : 'bg-white hover:bg-slate-100 border-slate-200 hover:border-amber-500/50 text-slate-700 shadow-xs'"
                            :title="isDark ? (currentLocale === 'ar' ? 'التبديل إلى الوضع الفاتح' : 'Switch to Light Mode') : (currentLocale === 'ar' ? 'التبديل إلى الوضع الداكن' : 'Switch to Dark Mode')"
                        >
                            <span v-if="isDark" class="flex items-center gap-1.5">
                                <svg class="h-4 w-4 text-amber-400 transition-transform duration-300 hover:rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                                <span class="text-xs font-bold text-amber-300">{{ currentLocale === 'ar' ? 'فاتح' : 'Light' }}</span>
                            </span>
                            <span v-else class="flex items-center gap-1.5">
                                <svg class="h-4 w-4 text-slate-700 transition-transform duration-300 hover:-rotate-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                                </svg>
                                <span class="text-xs font-bold text-slate-700">{{ currentLocale === 'ar' ? 'داكن' : 'Dark' }}</span>
                            </span>
                        </button>

                        <!-- Language Switcher Toggle -->
                        <button
                            @click="toggleLocale"
                            type="button"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border text-xs font-semibold transition-all shadow-sm cursor-pointer"
                            :class="isDark
                                ? 'bg-slate-900/90 hover:bg-slate-800 border-slate-700/80 hover:border-amber-500/50 text-slate-200'
                                : 'bg-white hover:bg-slate-100 border-slate-200 hover:border-amber-500/50 text-slate-700 shadow-xs'"
                            :title="currentLocale === 'ar' ? 'Switch to English' : 'التحويل إلى العربية'"
                        >
                            <span class="text-sm">🌐</span>
                            <span class="font-bold text-amber-500">{{ currentLocale === 'ar' ? 'English' : 'العربية' }}</span>
                        </button>

                        <!-- Arab Currency Switcher Dropdown -->
                        <div class="relative" data-currency-dropdown>
                            <button
                                @click="isCurrencyMenuOpen = !isCurrencyMenuOpen"
                                type="button"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border text-xs font-semibold transition-all shadow-sm cursor-pointer"
                                :class="isDark
                                    ? 'bg-slate-900/90 hover:bg-slate-800 border-slate-700/80 hover:border-amber-500/50 text-slate-200'
                                    : 'bg-white hover:bg-slate-100 border-slate-200 hover:border-amber-500/50 text-slate-700 shadow-xs'"
                                :title="t.nav.currencyTitle"
                            >
                                <span>{{ currentCurrency.flag }}</span>
                                <span class="font-bold text-amber-500 font-mono">{{ currentCurrencySymbol }}</span>
                                <svg class="w-3 h-3 text-slate-400 transition-transform duration-200" :class="{ 'rotate-180': isCurrencyMenuOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>

                            <!-- Dropdown Menu -->
                            <transition
                                enter-active-class="transition duration-150 ease-out"
                                enter-from-class="transform scale-95 opacity-0"
                                enter-to-class="transform scale-100 opacity-100"
                                leave-active-class="transition duration-100 ease-in"
                                leave-from-class="transform scale-100 opacity-100"
                                leave-to-class="transform scale-95 opacity-0"
                            >
                                <div
                                    v-if="isCurrencyMenuOpen"
                                    class="absolute mt-2 w-56 rounded-2xl shadow-2xl backdrop-blur-xl p-2 z-50 space-y-1 text-xs border"
                                    :class="[
                                        currentLocale === 'ar' ? 'left-0' : 'right-0',
                                        isDark ? 'bg-slate-900/95 border-slate-700 text-slate-200' : 'bg-white border-slate-200 text-slate-800 shadow-xl'
                                    ]"
                                >
                                    <div class="px-2.5 py-1 text-[10px] font-bold border-b flex items-center justify-between" :class="isDark ? 'text-slate-400 border-white/5' : 'text-slate-500 border-slate-100'">
                                        <span>{{ t.nav.currencyTitle }}</span>
                                        <span class="text-emerald-500 text-[9px]">{{ t.nav.autoDetect }}</span>
                                    </div>
                                    <div class="max-h-60 overflow-y-auto space-y-0.5 custom-scrollbar">
                                        <button
                                            v-for="c in Object.values(supportedArabCurrencies)"
                                            :key="c.code"
                                            @click="setCurrency(c.code)"
                                            type="button"
                                            class="w-full flex items-center justify-between px-2.5 py-2 rounded-xl transition-colors cursor-pointer"
                                            :class="[
                                                currentLocale === 'ar' ? 'text-right' : 'text-left',
                                                currentCountryCode === c.code
                                                    ? (isDark ? 'bg-amber-500/20 text-amber-300 font-bold border border-amber-500/30' : 'bg-amber-50 text-amber-800 font-bold border border-amber-200')
                                                    : (isDark ? 'text-slate-300 hover:bg-white/5' : 'text-slate-700 hover:bg-slate-100')
                                            ]"
                                        >
                                            <div class="flex items-center gap-2">
                                                <span class="text-base">{{ c.flag }}</span>
                                                <span class="truncate">{{ c.countryName[currentLocale] }}</span>
                                            </div>
                                            <span class="font-mono text-amber-500 text-xs shrink-0 font-bold">{{ c.symbol[currentLocale] }}</span>
                                        </button>
                                    </div>
                                </div>
                            </transition>
                        </div>

                        <!-- Auth Actions -->
                        <template v-if="$page.props.auth?.user">
                            <Link
                                :href="route('dashboard')"
                                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 text-slate-950 font-bold text-xs shadow-lg shadow-amber-500/25 hover:shadow-amber-500/40 hover:scale-[1.02] active:scale-[0.98] transition-all"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                                </svg>
                                <span>{{ t.nav.dashboard }}</span>
                            </Link>
                        </template>
                        <template v-else>
                            <Link
                                :href="route('login')"
                                class="text-xs font-semibold px-3 py-2 rounded-xl transition-all"
                                :class="isDark ? 'text-slate-300 hover:text-white hover:bg-white/5' : 'text-slate-700 hover:text-slate-950 hover:bg-slate-100'"
                            >
                                {{ t.nav.signIn }}
                            </Link>
                            <button
                                @click="isDemoModalOpen = true"
                                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 text-slate-950 font-bold text-xs shadow-lg shadow-amber-500/20 hover:shadow-amber-500/35 hover:scale-[1.02] active:scale-[0.98] transition-all cursor-pointer"
                            >
                                <span>{{ t.nav.bookDemo }}</span>
                                <svg class="w-3.5 h-3.5 rtl:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                                </svg>
                            </button>
                        </template>
                    </div>

                    <!-- Mobile Menu Hamburger & Quick Actions -->
                    <div class="flex lg:hidden items-center gap-2">
                        <!-- Mobile Theme Toggle -->
                        <button
                            @click="toggleTheme"
                            type="button"
                            class="p-2 rounded-lg border text-xs font-bold transition-all cursor-pointer"
                            :class="isDark ? 'bg-slate-900 border-slate-700 text-amber-400' : 'bg-white border-slate-200 text-slate-700 shadow-xs'"
                            :title="isDark ? (currentLocale === 'ar' ? 'التبديل إلى الوضع الفاتح' : 'Switch to Light Mode') : (currentLocale === 'ar' ? 'التبديل إلى الوضع الداكن' : 'Switch to Dark Mode')"
                        >
                            <svg v-if="isDark" class="h-4 w-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                            <svg v-else class="h-4 w-4 text-slate-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                            </svg>
                        </button>

                        <!-- Mobile Lang Switcher -->
                        <button
                            @click="toggleLocale"
                            type="button"
                            class="px-2.5 py-1.5 rounded-lg border text-xs font-bold transition-all cursor-pointer"
                            :class="isDark ? 'bg-slate-900 border-slate-700 text-amber-400' : 'bg-white border-slate-200 text-amber-600 shadow-xs'"
                        >
                            {{ currentLocale === 'ar' ? 'EN' : 'عربي' }}
                        </button>

                        <button
                            @click="isDemoModalOpen = true"
                            class="px-3 py-1.5 rounded-lg bg-amber-500 text-slate-950 font-bold text-xs shadow-sm cursor-pointer"
                        >
                            {{ currentLocale === 'ar' ? 'عرض حي' : 'Demo' }}
                        </button>

                        <button
                            @click="isMobileMenuOpen = !isMobileMenuOpen"
                            class="p-2 rounded-xl transition-all cursor-pointer"
                            :class="isDark ? 'text-slate-400 hover:text-white hover:bg-white/5' : 'text-slate-700 hover:text-slate-950 hover:bg-slate-100'"
                            aria-label="Menu"
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
            <div
                v-if="isMobileMenuOpen"
                class="lg:hidden border-t backdrop-blur-2xl px-6 py-6 space-y-4"
                :class="isDark ? 'bg-[#090e1c]/95 border-white/5' : 'bg-white/95 border-slate-200 shadow-xl'"
            >
                <!-- Theme Mode row for mobile -->
                <div class="flex items-center justify-between pb-3 border-b text-xs" :class="isDark ? 'border-white/10' : 'border-slate-100'">
                    <span :class="isDark ? 'text-slate-400' : 'text-slate-500'">{{ currentLocale === 'ar' ? 'مظهر الصفحة' : 'Theme Mode' }}:</span>
                    <button
                        @click="toggleTheme"
                        type="button"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-xs font-bold cursor-pointer"
                        :class="isDark ? 'bg-slate-800 border-slate-700 text-amber-300' : 'bg-slate-100 border-slate-200 text-slate-800'"
                    >
                        <span>{{ isDark ? '🌙 ' + (currentLocale === 'ar' ? 'الوضع الداكن' : 'Dark Mode') : '☀️ ' + (currentLocale === 'ar' ? 'الوضع الفاتح' : 'Light Mode') }}</span>
                    </button>
                </div>

                <!-- Country & Currency selector row for mobile -->
                <div class="flex items-center justify-between pb-3 border-b text-xs" :class="isDark ? 'border-white/10' : 'border-slate-100'">
                    <span :class="isDark ? 'text-slate-400' : 'text-slate-500'">{{ t.nav.currencyTitle }}:</span>
                    <div class="flex items-center gap-2">
                        <select
                            v-model="currentCountryCode"
                            @change="setCurrency(currentCountryCode)"
                            class="rounded-lg px-2.5 py-1 text-xs focus:outline-none focus:border-amber-500 border"
                            :class="isDark ? 'bg-slate-800 border-slate-700 text-white' : 'bg-slate-50 border-slate-200 text-slate-900'"
                        >
                            <option
                                v-for="c in Object.values(supportedArabCurrencies)"
                                :key="c.code"
                                :value="c.code"
                            >
                                {{ c.flag }} {{ c.countryName[currentLocale] }} ({{ c.symbol[currentLocale] }})
                            </option>
                        </select>
                    </div>
                </div>

                <a @click="isMobileMenuOpen = false" href="#how-it-works" class="block font-medium py-1.5" :class="isDark ? 'text-slate-200' : 'text-slate-700'">{{ t.nav.howItWorks }}</a>
                <a @click="isMobileMenuOpen = false" href="#solutions" class="block font-medium py-1.5" :class="isDark ? 'text-slate-200' : 'text-slate-700'">{{ t.nav.solutions }}</a>
                <a @click="isMobileMenuOpen = false" href="#interactive-demo" class="block text-amber-500 font-medium py-1.5">{{ t.nav.interactiveDemo }}</a>
                <a @click="isMobileMenuOpen = false" href="#features" class="block font-medium py-1.5" :class="isDark ? 'text-slate-200' : 'text-slate-700'">{{ t.nav.features }}</a>
                <a @click="isMobileMenuOpen = false" href="#directory" class="block font-medium py-1.5" :class="isDark ? 'text-slate-200' : 'text-slate-700'">{{ t.nav.directory }}</a>
                <a @click="isMobileMenuOpen = false" href="#roi-calculator" class="block font-medium py-1.5" :class="isDark ? 'text-slate-200' : 'text-slate-700'">{{ t.nav.roiCalculator }}</a>
                <a @click="isMobileMenuOpen = false" href="#comparison" class="block font-medium py-1.5" :class="isDark ? 'text-slate-200' : 'text-slate-700'">{{ t.nav.comparison }}</a>
                <a @click="isMobileMenuOpen = false" href="#faqs" class="block font-medium py-1.5" :class="isDark ? 'text-slate-200' : 'text-slate-700'">{{ t.nav.faqs }}</a>

                <div class="pt-4 border-t flex flex-col gap-3" :class="isDark ? 'border-white/10' : 'border-slate-100'">
                    <template v-if="$page.props.auth?.user">
                        <Link :href="route('dashboard')" class="text-center w-full py-3 rounded-xl bg-amber-500 text-slate-950 font-bold text-sm">
                            {{ t.nav.dashboard }}
                        </Link>
                    </template>
                    <template v-else>
                        <Link
                            :href="route('login')"
                            class="text-center w-full py-2.5 rounded-xl font-medium text-sm border"
                            :class="isDark ? 'border-white/10 text-white' : 'border-slate-200 text-slate-800 bg-slate-50'"
                        >
                            {{ t.nav.signIn }}
                        </Link>
                        <button @click="isDemoModalOpen = true; isMobileMenuOpen = false" class="w-full py-3 rounded-xl bg-amber-500 text-slate-950 font-bold text-sm">
                            {{ t.nav.bookDemo }}
                        </button>
                    </template>
                </div>
            </div>
        </header>

        <!-- ========================================================================= -->
        <!-- HERO SECTION: INTERACTIVE BEFORE/AFTER TOGGLE EXPERIENCE -->
        <!-- ========================================================================= -->
        <section class="relative pt-8 pb-20 lg:pt-16 lg:pb-28 overflow-hidden z-10 transition-colors duration-700">
            <!-- Dynamic Ambient Atmosphere Glow based on Mode -->
            <div
                class="pointer-events-none absolute top-1/4 left-1/2 -translate-x-1/2 w-full max-w-5xl h-[450px] rounded-full blur-[140px] transition-all duration-1000 -z-10"
                :class="heroMode === 'traditional'
                    ? (isDark ? 'bg-rose-950/30' : 'bg-rose-200/40')
                    : (isDark ? 'bg-emerald-950/25' : 'bg-emerald-200/40')"
            ></div>

            <!-- Soft Emerald Glow Flash Overlay (Triggers on Auto-Switch & Manual Switch to Smart) -->
            <div
                class="pointer-events-none absolute inset-0 z-0 transition-opacity duration-700 ease-out"
                :class="isEmeraldFlashing ? 'opacity-100' : 'opacity-0'"
            >
                <div class="absolute inset-0 bg-radial-gradient from-emerald-500/20 via-teal-500/10 to-transparent blur-3xl"></div>
                <div class="absolute top-1/3 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[750px] h-[400px] bg-emerald-400/15 rounded-full blur-[120px] animate-pulse"></div>
            </div>

            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
                <div class="text-center max-w-4xl mx-auto space-y-7">

                    <!-- Top Centered Interactive Mode Switcher (Pill Switcher) -->
                    <div class="flex flex-col items-center justify-center gap-2">
                        <div
                            class="inline-flex items-center p-1.5 rounded-full border shadow-2xl backdrop-blur-xl relative z-20 transition-colors"
                            :class="isDark ? 'bg-slate-900/90 border-white/10' : 'bg-white/95 border-slate-200/90 shadow-md'"
                        >
                            <!-- Traditional Pill Option -->
                            <button
                                @click="setHeroMode('traditional', true)"
                                type="button"
                                :class="heroMode === 'traditional'
                                    ? (isDark ? 'bg-rose-500/20 text-rose-200 border-rose-500/50 shadow-lg shadow-rose-950/60 font-black' : 'bg-rose-50 text-rose-700 border-rose-300 shadow-sm font-black')
                                    : (isDark ? 'text-slate-400 hover:text-rose-300 hover:bg-white/5 border-transparent font-medium' : 'text-slate-500 hover:text-rose-600 hover:bg-slate-50 border-transparent font-medium')"
                                class="relative flex items-center gap-2 px-3.5 sm:px-5 py-2 sm:py-2.5 rounded-full text-xs sm:text-sm border transition-all duration-300 cursor-pointer"
                            >
                                <span class="text-sm sm:text-base">⚠️</span>
                                <span>{{ currentLocale === 'ar' ? 'الوضع التقليدي (نزيف الأرباح)' : 'Traditional Legacy (Profit Bleed)' }}</span>
                                <span v-if="heroMode === 'traditional'" class="w-2 h-2 rounded-full" :class="isDark ? 'bg-rose-500 animate-pulse' : 'bg-rose-600 animate-pulse'"></span>
                            </button>

                            <!-- Smart Platform Pill Option -->
                            <button
                                @click="setHeroMode('smart', true)"
                                type="button"
                                :class="heroMode === 'smart'
                                    ? 'bg-gradient-to-r from-emerald-500 via-teal-500 to-emerald-400 text-slate-950 font-black shadow-lg shadow-emerald-500/30 border-emerald-300/40'
                                    : (isDark ? 'text-slate-400 hover:text-emerald-300 hover:bg-white/5 border-transparent font-medium' : 'text-slate-500 hover:text-emerald-700 hover:bg-slate-50 border-transparent font-medium')"
                                class="relative flex items-center gap-2 px-3.5 sm:px-5 py-2 sm:py-2.5 rounded-full text-xs sm:text-sm border transition-all duration-300 cursor-pointer"
                            >
                                <span class="text-sm sm:text-base">✨</span>
                                <span>{{ currentLocale === 'ar' ? 'مع المنظومة الذكية (التحكم الكامل)' : 'With Smart Platform (Total Control)' }}</span>
                                <span v-if="heroMode === 'smart'" class="w-2 h-2 rounded-full bg-slate-950 animate-ping"></span>
                            </button>
                        </div>
                    </div>

                    <!-- Dynamic State Container with Zero Layout Shift -->
                    <div class="min-h-[520px] sm:min-h-[440px] lg:min-h-[400px] flex flex-col justify-center">
                        <Transition name="hero-mode" mode="out-in">
                            <div :key="heroMode" class="space-y-6">

                                <!-- State Dynamic Badge -->
                                <div class="flex justify-center">
                                    <div
                                        class="inline-flex items-center gap-2.5 px-4 py-2 rounded-full text-xs sm:text-sm font-bold shadow-lg backdrop-blur-md transition-all border"
                                        :class="heroMode === 'traditional'
                                            ? (isDark ? 'bg-rose-500/10 border-rose-500/30 text-rose-300 shadow-rose-950/40' : 'bg-rose-50 border-rose-200 text-rose-700 shadow-rose-100')
                                            : (isDark ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-300 shadow-emerald-950/40' : 'bg-emerald-50 border-emerald-200 text-emerald-800 shadow-emerald-100')"
                                    >
                                        <span class="relative flex h-2.5 w-2.5">
                                            <span
                                                class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75"
                                                :class="heroMode === 'traditional' ? (isDark ? 'bg-rose-400' : 'bg-rose-500') : (isDark ? 'bg-emerald-400' : 'bg-emerald-500')"
                                            ></span>
                                            <span
                                                class="relative inline-flex rounded-full h-2.5 w-2.5"
                                                :class="heroMode === 'traditional' ? (isDark ? 'bg-rose-500' : 'bg-rose-600') : (isDark ? 'bg-emerald-500' : 'bg-emerald-600')"
                                            ></span>
                                        </span>
                                        <span class="text-sm">{{ heroMode === 'traditional' ? '⚠️' : '✨' }}</span>
                                        <span>
                                            {{ heroMode === 'traditional'
                                                ? (currentLocale === 'ar' ? 'فوضى تشغيلية وهدر مستمر' : 'Operational Chaos & Profit Leaks')
                                                : (currentLocale === 'ar' ? 'السيطرة المالية والتشغيلية الكاملة' : 'Total Financial & Operational Command')
                                            }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Dynamic H1 Headline -->
                                <h1 class="text-3xl sm:text-5xl lg:text-6xl font-black tracking-tight leading-[1.2] sm:leading-[1.2]">
                                    <template v-if="heroMode === 'traditional'">
                                        <span :class="isDark ? 'text-white' : 'text-slate-900'">{{ currentLocale === 'ar' ? 'مطبخ مزدحم، مبيعات تتصاعد..' : 'Crowded kitchen, surging sales..' }}</span>
                                        <span class="bg-gradient-to-r from-rose-500 via-red-600 to-rose-500 dark:from-rose-400 dark:via-red-500 dark:to-rose-300 bg-clip-text text-transparent block mt-2">
                                            {{ currentLocale === 'ar' ? 'وأرباحك الحقيقية تتبخر بين التطبيقات والهدر!' : 'yet your true profits vanish between delivery apps and waste!' }}
                                        </span>
                                    </template>
                                    <template v-else>
                                        <span :class="isDark ? 'text-white' : 'text-slate-900'">{{ currentLocale === 'ar' ? 'مطبخ منظم كالساعة، طلبات مباشرة..' : 'Clockwork kitchen, direct orders..' }}</span>
                                        <span class="bg-gradient-to-r from-emerald-600 via-teal-600 to-amber-600 dark:from-emerald-300 dark:via-teal-300 dark:to-amber-300 bg-clip-text text-transparent block mt-2">
                                            {{ currentLocale === 'ar' ? 'وكل قرش من أرباحك يدخل جيبك بالكامل!' : 'and every single cent of profit goes directly into your pocket!' }}
                                        </span>
                                    </template>
                                </h1>

                                <!-- Dynamic Sub-headline -->
                                <p
                                    class="text-base sm:text-xl max-w-3xl mx-auto font-normal leading-relaxed"
                                    :class="isDark ? 'text-slate-300' : 'text-slate-600'"
                                >
                                    <template v-if="heroMode === 'traditional'">
                                        {{ currentLocale === 'ar'
                                            ? 'عمولات تلتهم 25% من إيراداتك، أخطاء طلبات يومية، ومخزون يتسرب دون حسيب أو رقيب.'
                                            : 'Third-party delivery apps eating 25% of your revenue, daily kitchen ticket errors, and inventory leaking unchecked.'
                                        }}
                                    </template>
                                    <template v-else>
                                        {{ currentLocale === 'ar'
                                            ? 'نظام سحابي ذكي يوقف نزيف العمولات، يربط الصالة بالمطبخ لحظياً، ويحميك من أي تسريب مالي بدقة 100%.'
                                            : 'Intelligent cloud ecosystem that eliminates app commissions, links floor to kitchen instantly, and prevents leakages with 100% precision.'
                                        }}
                                    </template>
                                </p>

                                <!-- Micro-Metrics Cards (3 Cards) -->
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5 sm:gap-4 max-w-3xl mx-auto pt-2">
                                    <template v-if="heroMode === 'traditional'">
                                        <!-- Card 1 -->
                                        <div
                                            class="rounded-2xl p-4 sm:p-5 backdrop-blur-md flex flex-col items-center justify-center text-center transition-all border shadow-lg"
                                            :class="isDark ? 'bg-slate-900/80 border-rose-500/25 shadow-rose-950/30' : 'bg-white border-rose-200 shadow-sm'"
                                        >
                                            <div class="text-xs sm:text-sm font-semibold mb-1" :class="isDark ? 'text-slate-400' : 'text-slate-500'">
                                                {{ currentLocale === 'ar' ? 'عمولات تطبيقات' : 'App Commissions' }}
                                            </div>
                                            <div class="text-3xl sm:text-4xl font-black font-mono tracking-tight" :class="isDark ? 'text-rose-400' : 'text-rose-600'" dir="ltr">
                                                -25%
                                            </div>
                                            <div
                                                class="mt-2 text-[11px] font-semibold px-2.5 py-0.5 rounded-full border"
                                                :class="isDark ? 'text-rose-300/90 bg-rose-500/10 border-rose-500/20' : 'text-rose-700 bg-rose-50 border-rose-200'"
                                            >
                                                {{ currentLocale === 'ar' ? 'اقتطاع إجباري من الإيراد' : 'Mandatory revenue cut' }}
                                            </div>
                                        </div>

                                        <!-- Card 2 -->
                                        <div
                                            class="rounded-2xl p-4 sm:p-5 backdrop-blur-md flex flex-col items-center justify-center text-center transition-all border shadow-lg"
                                            :class="isDark ? 'bg-slate-900/80 border-rose-500/25 shadow-rose-950/30' : 'bg-white border-rose-200 shadow-sm'"
                                        >
                                            <div class="text-xs sm:text-sm font-semibold mb-1" :class="isDark ? 'text-slate-400' : 'text-slate-500'">
                                                {{ currentLocale === 'ar' ? 'فاقد وهدر مواد' : 'Kitchen Food Waste' }}
                                            </div>
                                            <div class="text-3xl sm:text-4xl font-black font-mono tracking-tight" :class="isDark ? 'text-rose-400' : 'text-rose-600'" dir="ltr">
                                                -15%
                                            </div>
                                            <div
                                                class="mt-2 text-[11px] font-semibold px-2.5 py-0.5 rounded-full border"
                                                :class="isDark ? 'text-rose-300/90 bg-rose-500/10 border-rose-500/20' : 'text-rose-700 bg-rose-50 border-rose-200'"
                                            >
                                                {{ currentLocale === 'ar' ? 'أخطاء وسوء تقدير تكلفة' : 'Ticket errors & food loss' }}
                                            </div>
                                        </div>

                                        <!-- Card 3 -->
                                        <div
                                            class="rounded-2xl p-4 sm:p-5 backdrop-blur-md flex flex-col items-center justify-center text-center transition-all border shadow-lg"
                                            :class="isDark ? 'bg-slate-900/80 border-rose-500/25 shadow-rose-950/30' : 'bg-white border-rose-200 shadow-sm'"
                                        >
                                            <div class="text-xs sm:text-sm font-semibold mb-1" :class="isDark ? 'text-slate-400' : 'text-slate-500'">
                                                {{ currentLocale === 'ar' ? 'شعور صاحب المطعم' : 'Owner Sentiment' }}
                                            </div>
                                            <div class="text-xl sm:text-2xl font-black mt-1" :class="isDark ? 'text-rose-300' : 'text-rose-700'">
                                                {{ currentLocale === 'ar' ? 'مجهود بلا عائد' : 'Effort Zero Return' }}
                                            </div>
                                            <div
                                                class="mt-2 text-[11px] font-semibold px-2.5 py-0.5 rounded-full border"
                                                :class="isDark ? 'text-rose-300/90 bg-rose-500/10 border-rose-500/20' : 'text-rose-700 bg-rose-50 border-rose-200'"
                                            >
                                                {{ currentLocale === 'ar' ? 'إرهاق وضياع للهوامش' : 'Exhaustion & lost margins' }}
                                            </div>
                                        </div>
                                    </template>

                                    <template v-else>
                                        <!-- Card 1 -->
                                        <div
                                            class="rounded-2xl p-4 sm:p-5 backdrop-blur-md flex flex-col items-center justify-center text-center transition-all border shadow-lg"
                                            :class="isDark ? 'bg-slate-900/80 border-emerald-500/30 shadow-emerald-950/30' : 'bg-white border-emerald-200 shadow-sm'"
                                        >
                                            <div class="text-xs sm:text-sm font-semibold mb-1" :class="isDark ? 'text-slate-400' : 'text-slate-500'">
                                                {{ currentLocale === 'ar' ? 'عمولة وسيط' : 'Intermediary Cut' }}
                                            </div>
                                            <div class="text-3xl sm:text-4xl font-black font-mono tracking-tight" :class="isDark ? 'text-emerald-400' : 'text-emerald-600'" dir="ltr">
                                                0%
                                            </div>
                                            <div
                                                class="mt-2 text-[11px] font-semibold px-2.5 py-0.5 rounded-full border"
                                                :class="isDark ? 'text-emerald-300/90 bg-emerald-500/10 border-emerald-500/20' : 'text-emerald-800 bg-emerald-50 border-emerald-200'"
                                            >
                                                {{ currentLocale === 'ar' ? 'أرباحك كاملة 100% لك' : '100% direct revenue retained' }}
                                            </div>
                                        </div>

                                        <!-- Card 2 -->
                                        <div
                                            class="rounded-2xl p-4 sm:p-5 backdrop-blur-md flex flex-col items-center justify-center text-center transition-all border shadow-lg"
                                            :class="isDark ? 'bg-slate-900/80 border-teal-500/30 shadow-teal-950/30' : 'bg-white border-teal-200 shadow-sm'"
                                        >
                                            <div class="text-xs sm:text-sm font-semibold mb-1" :class="isDark ? 'text-slate-400' : 'text-slate-500'">
                                                {{ currentLocale === 'ar' ? 'ضبط التكاليف' : 'Cost & Stock Control' }}
                                            </div>
                                            <div class="text-xl sm:text-2xl font-black mt-1" :class="isDark ? 'text-teal-300' : 'text-teal-700'">
                                                {{ currentLocale === 'ar' ? 'فوري ولحظي' : 'Real-Time Live' }}
                                            </div>
                                            <div
                                                class="mt-2 text-[11px] font-semibold px-2.5 py-0.5 rounded-full border"
                                                :class="isDark ? 'text-teal-300/90 bg-teal-500/10 border-teal-500/20' : 'text-teal-800 bg-teal-50 border-teal-200'"
                                            >
                                                {{ currentLocale === 'ar' ? 'ربط المطبخ بالوصفات آلياً' : 'Automated kitchen & recipe sync' }}
                                            </div>
                                        </div>

                                        <!-- Card 3 -->
                                        <div
                                            class="rounded-2xl p-4 sm:p-5 backdrop-blur-md flex flex-col items-center justify-center text-center transition-all border shadow-lg"
                                            :class="isDark ? 'bg-slate-900/80 border-amber-500/30 shadow-amber-950/30' : 'bg-white border-amber-200 shadow-sm'"
                                        >
                                            <div class="text-xs sm:text-sm font-semibold mb-1" :class="isDark ? 'text-slate-400' : 'text-slate-500'">
                                                {{ currentLocale === 'ar' ? 'النتيجة' : 'Bottom-Line Result' }}
                                            </div>
                                            <div class="text-xl sm:text-2xl font-black mt-1" :class="isDark ? 'text-amber-300' : 'text-amber-700'">
                                                {{ currentLocale === 'ar' ? 'مضاعفة صافي الأرباح' : '+100% Profit Boost' }}
                                            </div>
                                            <div
                                                class="mt-2 text-[11px] font-semibold px-2.5 py-0.5 rounded-full border"
                                                :class="isDark ? 'text-amber-300/90 bg-amber-500/10 border-amber-500/20' : 'text-amber-800 bg-amber-50 border-amber-200'"
                                            >
                                                {{ currentLocale === 'ar' ? 'نمو وسيطرة مالية 100%' : 'High margin & complete control' }}
                                            </div>
                                        </div>
                                    </template>
                                </div>

                            </div>
                        </Transition>
                    </div>

                    <!-- CTAs -->
                    <div class="flex flex-col sm:flex-row items-center justify-center gap-4 pt-2">
                        <!-- Primary CTA -->
                        <button
                            @click="isDemoModalOpen = true"
                            type="button"
                            :class="heroMode === 'traditional'
                                ? 'bg-gradient-to-r from-rose-500 via-red-500 to-rose-600 text-white shadow-xl shadow-rose-500/25 hover:shadow-rose-500/40'
                                : 'bg-gradient-to-r from-emerald-500 via-teal-400 to-emerald-400 text-slate-950 shadow-xl shadow-emerald-500/30 hover:shadow-emerald-500/50'"
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-3 px-8 py-4 rounded-2xl font-extrabold text-base hover:scale-[1.02] active:scale-[0.98] transition-all cursor-pointer group"
                        >
                            <span class="group-hover:scale-110 transition-transform">🛡️</span>
                            <span>{{ currentLocale === 'ar' ? 'ابدأ بتأمين أرباحك مجاناً' : 'Start Securing Your Profits Free' }}</span>
                            <span
                                class="text-xs px-2.5 py-0.5 rounded-full"
                                :class="heroMode === 'traditional' ? 'bg-black/25 text-white' : 'bg-black/10 text-slate-950 font-black'"
                            >
                                14 {{ currentLocale === 'ar' ? 'يوماً تجربة' : 'Days Free' }}
                            </span>
                        </button>

                        <!-- Secondary CTA -->
                        <a
                            href="#how-it-works"
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-3 px-8 py-4 rounded-2xl border font-bold text-base transition-all backdrop-blur-md shadow-lg group cursor-pointer"
                            :class="isDark
                                ? 'bg-slate-900/90 hover:bg-slate-800/90 border-slate-700 hover:border-emerald-500/50 text-white'
                                : 'bg-white hover:bg-slate-50 border-slate-300 hover:border-emerald-500/60 text-slate-800 shadow-xs'"
                        >
                            <span class="w-7 h-7 rounded-full bg-emerald-500/20 text-emerald-500 dark:text-emerald-400 flex items-center justify-center text-xs group-hover:scale-110 transition-transform font-bold">
                                ▶
                            </span>
                            <span>{{ currentLocale === 'ar' ? 'شاهد كيف توفر في 90 ثانية' : 'See How You Save in 90 Seconds' }}</span>
                        </a>
                    </div>

                    <!-- Micro Trust Badges -->
                    <div
                        class="pt-4 flex flex-wrap items-center justify-center gap-4 sm:gap-8 text-xs sm:text-sm"
                        :class="isDark ? 'text-slate-400' : 'text-slate-600'"
                    >
                        <div class="flex items-center gap-2">
                            <span class="text-emerald-500 font-bold">✓</span>
                            <span>{{ currentLocale === 'ar' ? 'بدون تحميل أي تطبيق للزبائن' : 'Zero App Downloads for Customers' }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-emerald-500 font-bold">✓</span>
                            <span>{{ currentLocale === 'ar' ? 'شاشات مطبخ KDS وربط مباشر' : 'Real-Time Cloud KDS Kitchen Screens' }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-emerald-500 font-bold">✓</span>
                            <span>{{ currentLocale === 'ar' ? 'صفر عمولة 0% (أرباحك لك بالكامل)' : '0% Sales Commission (Keep 100%)' }}</span>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- ARCHITECTURE WORKFLOW: HOW IT WORKS (POSTPAID & PREPAID) -->
        <!-- ========================================================================= -->
        <section
            id="how-it-works"
            class="py-24 relative z-10 border-t backdrop-blur-xl transition-colors duration-500"
            :class="isDark ? 'border-white/5 bg-[#080d19]/80' : 'border-slate-200/90 bg-white'"
        >
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                <!-- Header & Tag -->
                <div class="text-center max-w-3xl mx-auto mb-10 space-y-4">
                    <div
                        class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-bold border shadow-xs"
                        :class="isDark ? 'bg-amber-500/10 text-amber-400 border-amber-500/20' : 'bg-amber-50 text-amber-800 border-amber-200/80'"
                    >
                        <span>{{ t.workflow.tag }}</span>
                    </div>
                    <h2
                        class="text-3xl sm:text-5xl font-black tracking-tight"
                        :class="isDark ? 'text-white' : 'text-slate-900'"
                    >
                        {{ t.workflow.title }}
                    </h2>
                    <p
                        class="text-sm sm:text-base leading-relaxed"
                        :class="isDark ? 'text-slate-400' : 'text-slate-600'"
                    >
                        {{ t.workflow.subtitle }}
                    </p>

                    <!-- Interactive Restaurant Operating Model Switcher -->
                    <div class="pt-4 flex justify-center">
                        <div
                            class="inline-flex p-1.5 rounded-2xl border shadow-xl backdrop-blur-2xl max-w-full overflow-x-auto transition-colors"
                            :class="isDark ? 'bg-slate-950/90 border-white/10' : 'bg-slate-100 border-slate-200/90 shadow-inner'"
                        >
                            <button
                                @click="selectedOperatingMode = 'postpaid'"
                                type="button"
                                class="px-5 py-3 rounded-xl font-extrabold text-xs sm:text-sm transition-all duration-300 flex items-center gap-2.5 whitespace-nowrap cursor-pointer"
                                :class="selectedOperatingMode === 'postpaid'
                                    ? 'bg-gradient-to-r from-amber-500 to-amber-600 text-slate-950 shadow-lg shadow-amber-500/25 scale-[1.02]'
                                    : (isDark ? 'text-slate-400 hover:text-slate-200 hover:bg-white/5' : 'text-slate-600 hover:text-slate-900 hover:bg-white/80 font-bold')"
                            >
                                <span class="text-base sm:text-lg">🍽️</span>
                                <span>{{ t.workflow.togglePostpaid }}</span>
                            </button>

                            <button
                                @click="selectedOperatingMode = 'prepaid'"
                                type="button"
                                class="px-5 py-3 rounded-xl font-extrabold text-xs sm:text-sm transition-all duration-300 flex items-center gap-2.5 whitespace-nowrap cursor-pointer"
                                :class="selectedOperatingMode === 'prepaid'
                                    ? 'bg-gradient-to-r from-orange-500 to-amber-500 text-slate-950 shadow-lg shadow-orange-500/25 scale-[1.02]'
                                    : (isDark ? 'text-slate-400 hover:text-slate-200 hover:bg-white/5' : 'text-slate-600 hover:text-slate-900 hover:bg-white/80 font-bold')"
                            >
                                <span class="text-base sm:text-lg">⚡</span>
                                <span>{{ t.workflow.togglePrepaid }}</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Active Mode Overview Banner -->
                <transition mode="out-in" enter-active-class="transition duration-300 ease-out" enter-from-class="opacity-0 -translate-y-2" enter-to-class="opacity-100 translate-y-0" leave-active-class="transition duration-200 ease-in" leave-from-class="opacity-100" leave-to-class="opacity-0">
                    <div
                        :key="selectedOperatingMode"
                        class="max-w-3xl mx-auto mb-14 p-5 rounded-3xl border shadow-xl flex flex-col sm:flex-row items-center sm:items-start gap-4 text-center sm:text-start transition-all"
                        :class="selectedOperatingMode === 'prepaid'
                            ? (isDark ? 'bg-gradient-to-r from-orange-500/10 via-amber-500/10 to-orange-500/10 border-orange-500/30' : 'bg-gradient-to-r from-orange-50/90 via-amber-50/70 to-orange-50/90 border-orange-200/90 shadow-sm')
                            : (isDark ? 'bg-gradient-to-r from-amber-500/10 via-yellow-500/10 to-amber-500/10 border-amber-500/30' : 'bg-gradient-to-r from-amber-50/90 via-yellow-50/70 to-amber-50/90 border-amber-200/90 shadow-sm')"
                    >
                        <div
                            class="w-12 h-12 rounded-2xl flex items-center justify-center text-2xl shrink-0 shadow-lg"
                            :class="selectedOperatingMode === 'prepaid'
                                ? (isDark ? 'bg-orange-500/20 text-orange-400 border border-orange-500/30' : 'bg-orange-100 text-orange-700 border border-orange-200')
                                : (isDark ? 'bg-amber-500/20 text-amber-400 border border-amber-500/30' : 'bg-amber-100 text-amber-800 border border-amber-200')"
                        >
                            {{ selectedOperatingMode === 'prepaid' ? '⚡' : '🍽️' }}
                        </div>
                        <div class="space-y-1">
                            <h3
                                class="text-base font-extrabold"
                                :class="isDark ? 'text-white' : 'text-slate-900'"
                            >
                                {{ activeWorkflow.title }}
                            </h3>
                            <p
                                class="text-xs sm:text-sm leading-relaxed"
                                :class="isDark ? 'text-slate-300' : 'text-slate-700'"
                            >
                                {{ activeWorkflow.subtitle }}
                            </p>
                        </div>
                    </div>
                </transition>

                <!-- 4 Steps Interactive Cards Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">

                    <!-- Step 1 -->
                    <div
                        class="relative group p-6 rounded-3xl border transition-all duration-300 hover:-translate-y-1 flex flex-col justify-between shadow-xl backdrop-blur-xl"
                        :class="isDark
                            ? 'bg-slate-900/70 border-white/5 hover:border-amber-500/40'
                            : 'bg-white border-slate-200/90 hover:border-amber-400 hover:shadow-2xl shadow-sm'"
                    >
                        <div>
                            <div class="flex items-center justify-between mb-5">
                                <div
                                    class="w-12 h-12 rounded-2xl flex items-center justify-center text-xl font-black border"
                                    :class="isDark ? 'bg-amber-500/10 text-amber-400 border-amber-500/20' : 'bg-amber-50 text-amber-700 border-amber-200'"
                                >
                                    01
                                </div>
                                <span
                                    class="text-[10px] font-bold px-2.5 py-1 rounded-full border"
                                    :class="isDark ? 'bg-slate-800 text-slate-300 border-white/5' : 'bg-slate-100 text-slate-700 border-slate-200'"
                                >
                                    {{ selectedOperatingMode === 'prepaid' ? (currentLocale === 'ar' ? 'طلب فوري + كاشير' : 'Order & Cashier') : (currentLocale === 'ar' ? 'مسح رمز الطاولة' : 'Table QR Scan') }}
                                </span>
                            </div>
                            <h3
                                class="text-base sm:text-lg font-bold mb-2"
                                :class="isDark ? 'text-white' : 'text-slate-900'"
                            >
                                {{ activeWorkflow.step1Title }}
                            </h3>
                            <p
                                class="text-xs leading-relaxed"
                                :class="isDark ? 'text-slate-400' : 'text-slate-600'"
                            >
                                {{ activeWorkflow.step1Desc }}
                            </p>
                        </div>

                        <!-- Simulation Callout -->
                        <div
                            class="mt-5 pt-4 border-t"
                            :class="isDark ? 'border-white/5' : 'border-slate-100'"
                        >
                            <div
                                v-if="selectedOperatingMode === 'prepaid'"
                                class="p-3.5 rounded-2xl border text-xs leading-relaxed space-y-1.5"
                                :class="isDark ? 'bg-amber-500/10 border-amber-500/30 text-amber-200' : 'bg-amber-50/90 border-amber-200 text-amber-900'"
                            >
                                <div
                                    class="font-extrabold text-[11px] flex items-center gap-1.5"
                                    :class="isDark ? 'text-amber-300' : 'text-amber-800'"
                                >
                                    <span>💬</span>
                                    <span>{{ currentLocale === 'ar' ? 'رسالة تظهر للزبون بعد إرسال الطلب:' : 'Prompt shown to guest upon sending:' }}</span>
                                </div>
                                <div
                                    class="font-bold p-2.5 rounded-xl border text-[11px] leading-snug"
                                    :class="isDark ? 'text-white bg-slate-950/60 border-amber-500/20' : 'text-slate-900 bg-white border-amber-200 shadow-xs'"
                                >
                                    {{ currentLocale === 'ar'
                                        ? '«يرجى التوجه للكاشير و اخباره برقم طلبك لتدفع الفاتورة ليتم تحضير طلبك»'
                                        : '"Please head to the cashier and share your order number to settle your bill so prep can begin."'
                                    }}
                                </div>
                            </div>
                            <div
                                v-else
                                class="p-3 rounded-2xl border text-xs flex items-center gap-2"
                                :class="isDark ? 'bg-slate-950/50 border-white/5 text-slate-300' : 'bg-slate-50 border-slate-200 text-slate-700'"
                            >
                                <span class="text-base" :class="isDark ? 'text-emerald-400' : 'text-emerald-600'">📲</span>
                                <span class="text-[11px]">
                                    {{ currentLocale === 'ar' ? 'فتح المنيو فوراً بدون أي تطبيق وإرسال الأصناف للمطبخ بنقرة' : 'Instant digital menu launch with zero app barrier; send items in a tap' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2 -->
                    <div
                        class="relative group p-6 rounded-3xl border transition-all duration-300 hover:-translate-y-1 flex flex-col justify-between shadow-xl backdrop-blur-xl"
                        :class="isDark
                            ? 'bg-slate-900/70 border-white/5 hover:border-orange-500/40'
                            : 'bg-white border-slate-200/90 hover:border-orange-400 hover:shadow-2xl shadow-sm'"
                    >
                        <div>
                            <div class="flex items-center justify-between mb-5">
                                <div
                                    class="w-12 h-12 rounded-2xl flex items-center justify-center text-xl font-black border"
                                    :class="isDark ? 'bg-orange-500/10 text-orange-400 border-orange-500/20' : 'bg-orange-50 text-orange-700 border-orange-200'"
                                >
                                    02
                                </div>
                                <span
                                    class="text-[10px] font-bold px-2.5 py-1 rounded-full border"
                                    :class="isDark ? 'bg-slate-800 text-slate-300 border-white/5' : 'bg-slate-100 text-slate-700 border-slate-200'"
                                >
                                    {{ selectedOperatingMode === 'prepaid' ? (currentLocale === 'ar' ? 'محطة الكاشير' : 'Cashier Station') : (currentLocale === 'ar' ? 'شاشة وبونات المطبخ' : 'Kitchen KDS & Prints') }}
                                </span>
                            </div>
                            <h3
                                class="text-base sm:text-lg font-bold mb-2"
                                :class="isDark ? 'text-white' : 'text-slate-900'"
                            >
                                {{ activeWorkflow.step2Title }}
                            </h3>
                            <p
                                class="text-xs leading-relaxed"
                                :class="isDark ? 'text-slate-400' : 'text-slate-600'"
                            >
                                {{ activeWorkflow.step2Desc }}
                            </p>
                        </div>

                        <!-- Simulation Callout -->
                        <div
                            class="mt-5 pt-4 border-t"
                            :class="isDark ? 'border-white/5' : 'border-slate-100'"
                        >
                            <div
                                v-if="selectedOperatingMode === 'prepaid'"
                                class="p-3.5 rounded-2xl border text-xs leading-relaxed space-y-1.5"
                                :class="isDark ? 'bg-orange-500/10 border-orange-500/30 text-orange-200' : 'bg-orange-50/90 border-orange-200 text-orange-900'"
                            >
                                <div
                                    class="font-extrabold text-[11px] flex items-center gap-1.5"
                                    :class="isDark ? 'text-orange-300' : 'text-orange-800'"
                                >
                                    <span>💰</span>
                                    <span>{{ currentLocale === 'ar' ? 'تأكيد الكاشير اللحظي:' : 'Instant Cashier Confirmation:' }}</span>
                                </div>
                                <div
                                    class="text-[11px] p-2 rounded-xl border leading-relaxed"
                                    :class="isDark ? 'text-slate-200 bg-slate-950/60 border-orange-500/20' : 'text-slate-800 bg-white border-orange-200 shadow-xs'"
                                >
                                    {{ currentLocale === 'ar'
                                        ? 'يؤكد الكاشير استلام المبلغ ➔ ينبثق الطلب فوراً على شاشات نوافذ المطبخ'
                                        : 'Cashier confirms payment ➔ order immediately pops up on kitchen window screens'
                                    }}
                                </div>
                            </div>
                            <div
                                v-else
                                class="p-3 rounded-2xl border text-xs flex items-center gap-2"
                                :class="isDark ? 'bg-slate-950/50 border-white/5 text-slate-300' : 'bg-slate-50 border-slate-200 text-slate-700'"
                            >
                                <span class="text-base" :class="isDark ? 'text-orange-400' : 'text-orange-600'">⚡</span>
                                <span class="text-[11px]">
                                    {{ currentLocale === 'ar' ? 'طباعة البونات وتقييد الحساب تلقائياً على رقم الطاولة' : 'Auto kitchen ticket printing and automatic bill binding to the table' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3 -->
                    <div
                        class="relative group p-6 rounded-3xl border transition-all duration-300 hover:-translate-y-1 flex flex-col justify-between shadow-xl backdrop-blur-xl"
                        :class="isDark
                            ? 'bg-slate-900/70 border-white/5 hover:border-emerald-500/40'
                            : 'bg-white border-slate-200/90 hover:border-emerald-400 hover:shadow-2xl shadow-sm'"
                    >
                        <div>
                            <div class="flex items-center justify-between mb-5">
                                <div
                                    class="w-12 h-12 rounded-2xl flex items-center justify-center text-xl font-black border"
                                    :class="isDark ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' : 'bg-emerald-50 text-emerald-700 border-emerald-200'"
                                >
                                    03
                                </div>
                                <span
                                    class="text-[10px] font-bold px-2.5 py-1 rounded-full border"
                                    :class="isDark ? 'bg-slate-800 text-slate-300 border-white/5' : 'bg-slate-100 text-slate-700 border-slate-200'"
                                >
                                    {{ selectedOperatingMode === 'prepaid' ? (currentLocale === 'ar' ? 'استلام النافذة الذكي' : 'Window Claim') : (currentLocale === 'ar' ? 'تنظيم محطات الطهي' : 'Cook Station Sync') }}
                                </span>
                            </div>
                            <h3
                                class="text-base sm:text-lg font-bold mb-2"
                                :class="isDark ? 'text-white' : 'text-slate-900'"
                            >
                                {{ activeWorkflow.step3Title }}
                            </h3>
                            <p
                                class="text-xs leading-relaxed"
                                :class="isDark ? 'text-slate-400' : 'text-slate-600'"
                            >
                                {{ activeWorkflow.step3Desc }}
                            </p>
                        </div>

                        <!-- Simulation Callout -->
                        <div
                            class="mt-5 pt-4 border-t"
                            :class="isDark ? 'border-white/5' : 'border-slate-100'"
                        >
                            <div
                                v-if="selectedOperatingMode === 'prepaid'"
                                class="p-3.5 rounded-2xl border text-xs leading-relaxed space-y-1.5"
                                :class="isDark ? 'bg-sky-500/10 border-sky-500/30 text-sky-200' : 'bg-sky-50/90 border-sky-200 text-sky-900'"
                            >
                                <div
                                    class="font-extrabold text-[11px] flex items-center gap-1.5"
                                    :class="isDark ? 'text-sky-300' : 'text-sky-800'"
                                >
                                    <span>👨‍🍳</span>
                                    <span>{{ currentLocale === 'ar' ? 'تنسيق النوافذ ومنع التضارب:' : 'Window Sync & Conflict Prevention:' }}</span>
                                </div>
                                <div
                                    class="text-[11px] p-2 rounded-xl border leading-relaxed"
                                    :class="isDark ? 'text-slate-200 bg-slate-950/60 border-sky-500/20' : 'text-slate-800 bg-white border-sky-200 shadow-xs'"
                                >
                                    {{ currentLocale === 'ar'
                                        ? 'شيف النافذة يستلم الأوردر ➔ يظهر لباقي النوافذ فوراً أنه مستلم ومحجوز'
                                        : 'Window chef claims ticket ➔ immediately shows as claimed to all other windows'
                                    }}
                                </div>
                            </div>
                            <div
                                v-else
                                class="p-3 rounded-2xl border text-xs flex items-center gap-2"
                                :class="isDark ? 'bg-slate-950/50 border-white/5 text-slate-300' : 'bg-slate-50 border-slate-200 text-slate-700'"
                            >
                                <span class="text-base" :class="isDark ? 'text-emerald-400' : 'text-emerald-600'">⏱️</span>
                                <span class="text-[11px]">
                                    {{ currentLocale === 'ar' ? 'مؤقتات دقيقة ومراحل طهي واضحة تضمن تقديم الطعام ساخناً' : 'Precise prep timers and clear stages ensuring piping hot food delivery' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Step 4 -->
                    <div
                        class="relative group p-6 rounded-3xl border transition-all duration-300 hover:-translate-y-1 flex flex-col justify-between shadow-xl backdrop-blur-xl"
                        :class="isDark
                            ? 'bg-slate-900/70 border-white/5 hover:border-amber-400/40'
                            : 'bg-white border-slate-200/90 hover:border-amber-400 hover:shadow-2xl shadow-sm'"
                    >
                        <div>
                            <div class="flex items-center justify-between mb-5">
                                <div
                                    class="w-12 h-12 rounded-2xl flex items-center justify-center text-xl font-black border"
                                    :class="isDark ? 'bg-amber-400/10 text-amber-300 border-amber-400/20' : 'bg-amber-50 text-amber-700 border-amber-200'"
                                >
                                    04
                                </div>
                                <span
                                    class="text-[10px] font-bold px-2.5 py-1 rounded-full border"
                                    :class="isDark ? 'bg-slate-800 text-slate-300 border-white/5' : 'bg-slate-100 text-slate-700 border-slate-200'"
                                >
                                    {{ selectedOperatingMode === 'prepaid' ? (currentLocale === 'ar' ? 'جوال الزبون والاستلام' : 'Guest Phone & Pickup') : (currentLocale === 'ar' ? 'دفع الحساب والمغادرة' : 'Bill Pay & Departure') }}
                                </span>
                            </div>
                            <h3
                                class="text-base sm:text-lg font-bold mb-2"
                                :class="isDark ? 'text-white' : 'text-slate-900'"
                            >
                                {{ activeWorkflow.step4Title }}
                            </h3>
                            <p
                                class="text-xs leading-relaxed"
                                :class="isDark ? 'text-slate-400' : 'text-slate-600'"
                            >
                                {{ activeWorkflow.step4Desc }}
                            </p>
                        </div>

                        <!-- Simulation Callout -->
                        <div
                            class="mt-5 pt-4 border-t"
                            :class="isDark ? 'border-white/5' : 'border-slate-100'"
                        >
                            <div
                                v-if="selectedOperatingMode === 'prepaid'"
                                class="p-3.5 rounded-2xl border text-xs leading-relaxed space-y-1.5"
                                :class="isDark ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-200' : 'bg-emerald-50/90 border-emerald-200 text-emerald-900'"
                            >
                                <div
                                    class="font-extrabold text-[11px] flex items-center gap-1.5"
                                    :class="isDark ? 'text-emerald-300' : 'text-emerald-800'"
                                >
                                    <span>📱</span>
                                    <span>{{ currentLocale === 'ar' ? 'شاشة جوال الزبون المباشرة:' : 'Live Guest Mobile Tracker:' }}</span>
                                </div>
                                <div
                                    class="font-bold p-2.5 rounded-xl border text-[11px] leading-snug"
                                    :class="isDark ? 'text-white bg-slate-950/60 border-emerald-500/20' : 'text-slate-900 bg-white border-emerald-200 shadow-xs'"
                                >
                                    {{ currentLocale === 'ar'
                                        ? '«تم استلام طلبك من قِبَل النافذة رقم 2» ثم يظهر تتبع حالة طلب الزبون'
                                        : '"Your order was received by Window #2" followed by live customer order tracking'
                                    }}
                                </div>
                            </div>
                            <div
                                v-else
                                class="p-3 rounded-2xl border text-xs flex items-center gap-2"
                                :class="isDark ? 'bg-slate-950/50 border-white/5 text-slate-300' : 'bg-slate-50 border-slate-200 text-slate-700'"
                            >
                                <span class="text-base" :class="isDark ? 'text-amber-400' : 'text-amber-600'">💳</span>
                                <span class="text-[11px]">
                                    {{ currentLocale === 'ar' ? 'طلب الفاتورة بلمسة واحدة وسداد المبلغ كاش أو شبكة وإغلاق الطاولة' : 'One-tap bill request, seamless cash or card payment, and auto table closing' }}
                                </span>
                            </div>
                        </div>
                    </div>

                </div>

            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- TAILORED RESTAURANT SOLUTIONS: POSTPAID & PREPAID -->
        <!-- ========================================================================= -->
        <section
            id="solutions"
            class="py-24 relative z-10 border-t transition-colors duration-500"
            :class="isDark ? 'border-white/5 bg-[#060a13]' : 'border-slate-200/90 bg-slate-50/70'"
        >
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                <div class="text-center max-w-3xl mx-auto mb-16 space-y-4">
                    <div
                        class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold border shadow-xs"
                        :class="isDark ? 'bg-amber-500/10 text-amber-400 border-amber-500/20' : 'bg-amber-50 text-amber-800 border-amber-200/80'"
                    >
                        <span>{{ t.solutions.tag }}</span>
                    </div>
                    <h2
                        class="text-3xl sm:text-5xl font-extrabold leading-tight tracking-tight"
                        :class="isDark ? 'text-white' : 'text-slate-900'"
                    >
                        {{ t.solutions.title }}
                    </h2>
                    <p
                        class="text-base sm:text-lg"
                        :class="isDark ? 'text-slate-400' : 'text-slate-600'"
                    >
                        {{ t.solutions.subtitle }}
                    </p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-stretch">

                    <!-- Solution 1: Postpaid (Dine-in / Hall) -->
                    <div
                        class="relative group p-8 sm:p-10 rounded-3xl border transition-all duration-300 flex flex-col justify-between shadow-2xl backdrop-blur-xl"
                        :class="isDark
                            ? 'bg-slate-900/70 border-amber-500/20 hover:border-amber-500/40'
                            : 'bg-white border-slate-200/90 hover:border-amber-400 hover:shadow-2xl shadow-md'"
                    >
                        <div class="space-y-6">
                            <div class="flex items-center justify-between flex-wrap gap-3">
                                <div
                                    class="w-14 h-14 rounded-2xl flex items-center justify-center text-3xl shadow-lg border"
                                    :class="isDark
                                        ? 'bg-amber-500/15 text-amber-400 border-amber-500/30 shadow-amber-500/10'
                                        : 'bg-amber-50 text-amber-700 border-amber-200 shadow-amber-500/5'"
                                >
                                    🍽️
                                </div>
                                <span
                                    class="px-3 py-1 rounded-full text-xs font-bold border"
                                    :class="isDark
                                        ? 'bg-amber-500/10 text-amber-300 border-amber-500/30'
                                        : 'bg-amber-50 text-amber-800 border-amber-200'"
                                >
                                    {{ t.solutions.postpaidBadge }}
                                </span>
                            </div>

                            <div>
                                <h3
                                    class="text-2xl font-black mb-2"
                                    :class="isDark ? 'text-white' : 'text-slate-900'"
                                >
                                    {{ t.solutions.postpaidTitle }}
                                </h3>
                                <p
                                    class="text-sm leading-relaxed"
                                    :class="isDark ? 'text-slate-300' : 'text-slate-600'"
                                >
                                    {{ t.solutions.postpaidDesc }}
                                </p>
                            </div>

                            <ul class="space-y-3.5 pt-2">
                                <li
                                    v-for="(point, idx) in t.solutions.postpaidPoints"
                                    :key="idx"
                                    class="flex items-start gap-3 text-xs sm:text-sm leading-relaxed"
                                    :class="isDark ? 'text-slate-300' : 'text-slate-700'"
                                >
                                    <span
                                        class="w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold shrink-0 mt-0.5"
                                        :class="isDark ? 'bg-emerald-500/20 text-emerald-400' : 'bg-emerald-100 text-emerald-800 font-black'"
                                    >
                                        ✓
                                    </span>
                                    <span>{{ point }}</span>
                                </li>
                            </ul>
                        </div>

                        <div
                            class="pt-8 mt-8 border-t flex items-center justify-between flex-wrap gap-3"
                            :class="isDark ? 'border-white/5' : 'border-slate-100'"
                        >
                            <div
                                class="text-xs font-semibold flex items-center gap-2"
                                :class="isDark ? 'text-amber-400' : 'text-amber-800'"
                            >
                                <span>⭐</span>
                                <span>{{ currentLocale === 'ar' ? 'مثالي للمطاعم العائلية والمقاهي الراقية' : 'Ideal for Fine Dining & Family Lounges' }}</span>
                            </div>
                            <button
                                @click="isDemoModalOpen = true"
                                class="px-4 py-2 rounded-xl text-xs font-bold transition-all border cursor-pointer"
                                :class="isDark
                                    ? 'bg-amber-500/10 hover:bg-amber-500/20 text-amber-300 border-amber-500/30'
                                    : 'bg-amber-50 hover:bg-amber-100 text-amber-800 border-amber-300 shadow-xs'"
                            >
                                {{ currentLocale === 'ar' ? 'طلب عرض مخصص' : 'Request Demo' }}
                            </button>
                        </div>
                    </div>

                    <!-- Solution 2: Prepaid (Fast Food & Counters) -->
                    <div
                        class="relative group p-8 sm:p-10 rounded-3xl border transition-all duration-300 flex flex-col justify-between shadow-2xl backdrop-blur-xl"
                        :class="isDark
                            ? 'bg-slate-900/70 border-orange-500/20 hover:border-orange-500/40'
                            : 'bg-white border-slate-200/90 hover:border-orange-400 hover:shadow-2xl shadow-md'"
                    >
                        <div class="space-y-6">
                            <div class="flex items-center justify-between flex-wrap gap-3">
                                <div
                                    class="w-14 h-14 rounded-2xl flex items-center justify-center text-3xl shadow-lg border"
                                    :class="isDark
                                        ? 'bg-orange-500/15 text-orange-400 border-orange-500/30 shadow-orange-500/10'
                                        : 'bg-orange-50 text-orange-700 border-orange-200 shadow-orange-500/5'"
                                >
                                    ⚡
                                </div>
                                <span
                                    class="px-3 py-1 rounded-full text-xs font-bold border"
                                    :class="isDark
                                        ? 'bg-orange-500/10 text-orange-300 border-orange-500/30'
                                        : 'bg-orange-50 text-orange-800 border-orange-200'"
                                >
                                    {{ t.solutions.prepaidBadge }}
                                </span>
                            </div>

                            <div>
                                <h3
                                    class="text-2xl font-black mb-2"
                                    :class="isDark ? 'text-white' : 'text-slate-900'"
                                >
                                    {{ t.solutions.prepaidTitle }}
                                </h3>
                                <p
                                    class="text-sm leading-relaxed"
                                    :class="isDark ? 'text-slate-300' : 'text-slate-600'"
                                >
                                    {{ t.solutions.prepaidDesc }}
                                </p>
                            </div>

                            <ul class="space-y-3.5 pt-2">
                                <li
                                    v-for="(point, idx) in t.solutions.prepaidPoints"
                                    :key="idx"
                                    class="flex items-start gap-3 text-xs sm:text-sm leading-relaxed"
                                    :class="isDark ? 'text-slate-300' : 'text-slate-700'"
                                >
                                    <span
                                        class="w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold shrink-0 mt-0.5"
                                        :class="isDark ? 'bg-orange-500/20 text-orange-400' : 'bg-orange-100 text-orange-800 font-black'"
                                    >
                                        ✓
                                    </span>
                                    <span>{{ point }}</span>
                                </li>
                            </ul>
                        </div>

                        <div
                            class="pt-8 mt-8 border-t flex items-center justify-between flex-wrap gap-3"
                            :class="isDark ? 'border-white/5' : 'border-slate-100'"
                        >
                            <div
                                class="text-xs font-semibold flex items-center gap-2"
                                :class="isDark ? 'text-orange-400' : 'text-orange-800'"
                            >
                                <span>🚀</span>
                                <span>{{ currentLocale === 'ar' ? 'مثالي للبرجر، الشاورما، والوجبات السريعة' : 'Ideal for Burgers, Shawarma & Quick Cafés' }}</span>
                            </div>
                            <button
                                @click="isDemoModalOpen = true"
                                class="px-4 py-2 rounded-xl text-xs font-bold transition-all border cursor-pointer"
                                :class="isDark
                                    ? 'bg-orange-500/10 hover:bg-orange-500/20 text-orange-300 border-orange-500/30'
                                    : 'bg-orange-50 hover:bg-orange-100 text-orange-800 border-orange-300 shadow-xs'"
                            >
                                {{ currentLocale === 'ar' ? 'طلب عرض مخصص' : 'Request Demo' }}
                            </button>
                        </div>
                    </div>

                </div>

            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- INTERACTIVE LIVE SHOWCASE (4 MODULES) -->
        <!-- ========================================================================= -->
        <section
            id="interactive-demo"
            class="py-24 relative z-10 border-t transition-colors duration-500"
            :class="isDark ? 'border-white/5 bg-transparent' : 'border-slate-200/90 bg-white'"
        >
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                <div class="text-center max-w-3xl mx-auto mb-12 space-y-4">
                    <div
                        class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold border shadow-xs"
                        :class="isDark ? 'bg-gradient-to-r from-amber-500/20 to-orange-500/20 text-amber-300 border-amber-500/30' : 'bg-amber-50 text-amber-800 border-amber-200/90'"
                    >
                        <span>{{ t.demo.tag }}</span>
                    </div>
                    <h2
                        class="text-3xl sm:text-4xl font-extrabold tracking-tight"
                        :class="isDark ? 'text-white' : 'text-slate-900'"
                    >
                        {{ t.demo.title }}
                    </h2>
                    <p
                        class="text-base sm:text-lg"
                        :class="isDark ? 'text-slate-400' : 'text-slate-600'"
                    >
                        {{ t.demo.subtitle }}
                    </p>
                </div>

                <!-- Showcase Module Switcher Tabs -->
                <div class="flex flex-wrap items-center justify-center gap-2 sm:gap-3 mb-10">
                    <button
                        @click="activeDemoTab = 'customer'"
                        class="px-5 py-3 rounded-2xl font-bold text-xs sm:text-sm transition-all flex items-center gap-2 cursor-pointer"
                        :class="activeDemoTab === 'customer'
                            ? 'bg-gradient-to-r from-amber-500 to-orange-500 text-slate-950 shadow-lg shadow-amber-500/25 font-black scale-[1.02]'
                            : (isDark ? 'bg-slate-900/80 text-slate-300 hover:bg-slate-800 border border-white/5' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 border border-slate-200/90 shadow-xs font-bold')"
                    >
                        <span>📱</span>
                        <span>{{ t.demo.tabCustomer }}</span>
                    </button>

                    <button
                        @click="activeDemoTab = 'kds'"
                        class="px-5 py-3 rounded-2xl font-bold text-xs sm:text-sm transition-all flex items-center gap-2 cursor-pointer"
                        :class="activeDemoTab === 'kds'
                            ? 'bg-gradient-to-r from-amber-500 to-orange-500 text-slate-950 shadow-lg shadow-amber-500/25 font-black scale-[1.02]'
                            : (isDark ? 'bg-slate-900/80 text-slate-300 hover:bg-slate-800 border border-white/5' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 border border-slate-200/90 shadow-xs font-bold')"
                    >
                        <span>👨‍🍳</span>
                        <span>{{ t.demo.tabKds }}</span>
                    </button>

                    <button
                        @click="activeDemoTab = 'qr'"
                        class="px-5 py-3 rounded-2xl font-bold text-xs sm:text-sm transition-all flex items-center gap-2 cursor-pointer"
                        :class="activeDemoTab === 'qr'
                            ? 'bg-gradient-to-r from-amber-500 to-orange-500 text-slate-950 shadow-lg shadow-amber-500/25 font-black scale-[1.02]'
                            : (isDark ? 'bg-slate-900/80 text-slate-300 hover:bg-slate-800 border border-white/5' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 border border-slate-200/90 shadow-xs font-bold')"
                    >
                        <span>🧾</span>
                        <span>{{ t.demo.tabQr }}</span>
                    </button>

                    <button
                        @click="activeDemoTab = 'analytics'"
                        class="px-5 py-3 rounded-2xl font-bold text-xs sm:text-sm transition-all flex items-center gap-2 cursor-pointer"
                        :class="activeDemoTab === 'analytics'
                            ? 'bg-gradient-to-r from-amber-500 to-orange-500 text-slate-950 shadow-lg shadow-amber-500/25 font-black scale-[1.02]'
                            : (isDark ? 'bg-slate-900/80 text-slate-300 hover:bg-slate-800 border border-white/5' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 border border-slate-200/90 shadow-xs font-bold')"
                    >
                        <span>📊</span>
                        <span>{{ t.demo.tabAnalytics }}</span>
                    </button>
                </div>

                <!-- Showcase Stage -->
                <div
                    class="relative rounded-3xl border shadow-2xl backdrop-blur-2xl p-4 sm:p-8 overflow-hidden min-h-[580px] transition-colors"
                    :class="isDark ? 'bg-slate-950/80 border-white/10' : 'bg-slate-50/70 border-slate-200/90 shadow-xl'"
                >

                    <!-- TAB 1: CUSTOMER MOBILE MENU SIMULATOR -->
                    <div v-if="activeDemoTab === 'customer'" class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
                        <div class="lg:col-span-5 space-y-5">
                            <div
                                class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold border shadow-xs"
                                :class="isDark ? 'bg-amber-500/10 text-amber-400 border-amber-500/20' : 'bg-amber-100/80 text-amber-900 border-amber-200'"
                            >
                                <span>{{ t.demo.tabCustomer }}</span>
                            </div>
                            <h3
                                class="text-2xl sm:text-3xl font-extrabold"
                                :class="isDark ? 'text-white' : 'text-slate-900'"
                            >
                                {{ currentLocale === 'ar' ? 'منيو رقمي فوري على الطاولة' : 'Instant Tabletop Digital Menu' }}
                            </h3>
                            <p
                                class="text-sm leading-relaxed"
                                :class="isDark ? 'text-slate-300' : 'text-slate-600'"
                            >
                                {{ currentLocale === 'ar'
                                    ? 'بدون الحاجة لتحميل تطبيقات، يتصفح العميل قائمة الطعام بصور احترافية عالية الدقة، مع خيارات الإضافة، التخصيص، وسلة طاولة تفاعلية تتيح له إرسال الأوردر فوراً إلى المطبخ.'
                                    : 'With zero app friction, guests browse appetizing menu items with high-resolution photography, customizations, dietary tags, and a floating interactive cart that routes tickets straight to the kitchen.'
                                }}
                            </p>

                            <!-- Category Switcher in simulation -->
                            <div class="flex flex-wrap gap-2 pt-2">
                                <button
                                    @click="activeCategory = 'grills'"
                                    class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer"
                                    :class="activeCategory === 'grills'
                                        ? 'bg-amber-500 text-slate-950 font-bold'
                                        : (isDark ? 'bg-slate-900 text-slate-300 hover:bg-slate-800' : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-200 shadow-xs font-semibold')"
                                >
                                    {{ t.demo.catGrills }}
                                </button>
                                <button
                                    @click="activeCategory = 'appetizers'"
                                    class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer"
                                    :class="activeCategory === 'appetizers'
                                        ? 'bg-amber-500 text-slate-950 font-bold'
                                        : (isDark ? 'bg-slate-900 text-slate-300 hover:bg-slate-800' : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-200 shadow-xs font-semibold')"
                                >
                                    {{ t.demo.catAppetizers }}
                                </button>
                                <button
                                    @click="activeCategory = 'desserts'"
                                    class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer"
                                    :class="activeCategory === 'desserts'
                                        ? 'bg-amber-500 text-slate-950 font-bold'
                                        : (isDark ? 'bg-slate-900 text-slate-300 hover:bg-slate-800' : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-200 shadow-xs font-semibold')"
                                >
                                    {{ t.demo.catDesserts }}
                                </button>
                                <button
                                    @click="activeCategory = 'drinks'"
                                    class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer"
                                    :class="activeCategory === 'drinks'
                                        ? 'bg-amber-500 text-slate-950 font-bold'
                                        : (isDark ? 'bg-slate-900 text-slate-300 hover:bg-slate-800' : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-200 shadow-xs font-semibold')"
                                >
                                    {{ t.demo.catDrinks }}
                                </button>
                            </div>
                        </div>

                        <!-- Phone Mockup Container -->
                        <div class="lg:col-span-7 flex justify-center">
                            <div
                                class="w-full max-w-[340px] sm:max-w-[360px] h-[550px] rounded-[44px] shadow-2xl overflow-hidden relative flex flex-col transition-all"
                                :class="isDark ? 'bg-slate-900 border-[6px] border-slate-800' : 'bg-white border-[6px] border-slate-300 shadow-2xl'"
                            >
                                <!-- Phone Notch -->
                                <div
                                    class="h-6 flex items-center justify-between px-6 text-[10px] font-mono shrink-0"
                                    :class="isDark ? 'bg-slate-950 text-slate-400' : 'bg-slate-100 text-slate-600 border-b border-slate-200/60'"
                                >
                                    <span>9:41</span>
                                    <div class="w-16 h-3.5 bg-black rounded-full mx-auto"></div>
                                    <span>5G</span>
                                </div>

                                <!-- Mobile App Header -->
                                <div
                                    class="px-4 py-3 border-b flex items-center justify-between shrink-0"
                                    :class="isDark ? 'bg-slate-900/90 border-white/5' : 'bg-slate-50 border-slate-200'"
                                >
                                    <div class="flex items-center gap-2">
                                        <div
                                            class="w-7 h-7 rounded-lg flex items-center justify-center font-bold text-xs"
                                            :class="isDark ? 'bg-amber-500/20 text-amber-400' : 'bg-amber-100 text-amber-800'"
                                        >
                                            04
                                        </div>
                                        <div>
                                            <div
                                                class="text-xs font-bold"
                                                :class="isDark ? 'text-white' : 'text-slate-900'"
                                            >
                                                {{ currentLocale === 'ar' ? 'طاولة رقم 04' : 'Table 04' }}
                                            </div>
                                            <div
                                                class="text-[10px]"
                                                :class="isDark ? 'text-emerald-400' : 'text-emerald-700 font-bold'"
                                            >
                                                {{ currentLocale === 'ar' ? 'متصل بالخدمة الحية ⚡' : 'Connected to Live Service ⚡' }}
                                            </div>
                                        </div>
                                    </div>
                                    <span
                                        class="text-xs font-bold"
                                        :class="isDark ? 'text-amber-400' : 'text-amber-700'"
                                    >
                                        {{ currentCurrencySymbol }}
                                    </span>
                                </div>

                                <!-- Scrollable Menu Items -->
                                <div class="p-3 space-y-2.5 overflow-y-auto flex-1 custom-scrollbar pb-24">
                                    <div
                                        v-for="item in filteredMenuItems"
                                        :key="item.id"
                                        class="p-2.5 rounded-2xl border flex items-center gap-3 transition-all"
                                        :class="isDark
                                            ? 'bg-slate-950/90 border-white/5 hover:border-amber-500/30'
                                            : 'bg-white border-slate-200 hover:border-amber-300 shadow-xs'"
                                    >
                                        <img :src="item.image" :alt="item.name" class="w-14 h-14 rounded-xl object-cover shrink-0" onerror="this.src='/images/logo.jpg'" />
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-1.5">
                                                <h5
                                                    class="text-xs font-bold truncate"
                                                    :class="isDark ? 'text-white' : 'text-slate-900'"
                                                >
                                                    {{ item.name }}
                                                </h5>
                                                <span
                                                    v-if="item.badge"
                                                    class="text-[9px] px-1.5 py-0.2 rounded shrink-0 font-bold"
                                                    :class="isDark ? 'bg-amber-500/20 text-amber-300' : 'bg-amber-50 text-amber-800 border border-amber-200'"
                                                >
                                                    {{ item.badge }}
                                                </span>
                                            </div>
                                            <p
                                                class="text-[10px] line-clamp-1 mt-0.5"
                                                :class="isDark ? 'text-slate-400' : 'text-slate-500'"
                                            >
                                                {{ item.description }}
                                            </p>
                                            <div
                                                class="text-xs mt-1 font-mono"
                                                :class="isDark ? 'text-amber-400 font-bold' : 'text-amber-700 font-extrabold'"
                                            >
                                                {{ formatPrice(item.price) }} {{ currentCurrencySymbol }}
                                            </div>
                                        </div>

                                        <!-- Stepper or Add button -->
                                        <div class="shrink-0">
                                            <div
                                                v-if="cartQuantities[item.id]"
                                                class="flex items-center gap-1.5 border rounded-xl px-1.5 py-1"
                                                :class="isDark ? 'bg-slate-800 border-amber-500/30' : 'bg-slate-50 border-amber-300'"
                                            >
                                                <button
                                                    @click="removeItem(item.id)"
                                                    class="w-5 h-5 rounded-lg text-xs flex items-center justify-center cursor-pointer transition-colors"
                                                    :class="isDark ? 'bg-slate-700 text-white hover:bg-slate-600' : 'bg-white text-slate-800 border border-slate-200 shadow-xs hover:bg-slate-100'"
                                                >
                                                    -
                                                </button>
                                                <span
                                                    class="text-xs font-bold font-mono px-1"
                                                    :class="isDark ? 'text-white' : 'text-slate-900'"
                                                >
                                                    {{ cartQuantities[item.id] }}
                                                </span>
                                                <button
                                                    @click="addItem(item.id)"
                                                    class="w-5 h-5 rounded-lg bg-amber-500 text-slate-950 text-xs font-bold flex items-center justify-center hover:bg-amber-400 cursor-pointer"
                                                >
                                                    +
                                                </button>
                                            </div>
                                            <button
                                                v-else
                                                @click="addItem(item.id)"
                                                class="w-8 h-8 rounded-xl flex items-center justify-center font-bold text-sm transition-all cursor-pointer"
                                                :class="isDark
                                                    ? 'bg-amber-500/10 hover:bg-amber-500 text-amber-400 hover:text-slate-950 border border-amber-500/30'
                                                    : 'bg-amber-50 hover:bg-amber-500 text-amber-700 hover:text-slate-950 border border-amber-200'"
                                            >
                                                +
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Floating Glassmorphism Cart Bar -->
                                <div
                                    v-if="totalCartCount > 0"
                                    class="absolute bottom-2.5 right-2.5 left-2.5 p-3 rounded-2xl border shadow-2xl backdrop-blur-xl flex items-center justify-between z-20 transition-all"
                                    :class="isDark
                                        ? 'bg-[#090e1c]/95 border-amber-500/40 text-white'
                                        : 'bg-white/95 border-amber-400 text-slate-900 shadow-2xl'"
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
                                            <div
                                                class="text-[10px]"
                                                :class="isDark ? 'text-slate-400' : 'text-slate-500 font-semibold'"
                                            >
                                                {{ t.demo.cartTitle }}
                                            </div>
                                            <div
                                                class="text-xs font-mono font-black"
                                                :class="isDark ? 'text-amber-400' : 'text-amber-800'"
                                            >
                                                {{ formatPrice(totalCartPrice) }} {{ currentCurrencySymbol }}
                                            </div>
                                        </div>
                                    </div>

                                    <button
                                        @click="activeDemoTab = 'kds'"
                                        class="px-3.5 py-1.5 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 text-slate-950 text-xs font-bold hover:scale-105 active:scale-95 transition-all shadow cursor-pointer"
                                    >
                                        {{ t.demo.sendToKitchenBtn }}
                                    </button>
                                </div>

                            </div>
                        </div>
                    </div>

                    <!-- TAB 2: KITCHEN DISPLAY SYSTEM (KDS) -->
                    <div v-else-if="activeDemoTab === 'kds'" class="space-y-6">
                        <div
                            class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b pb-4"
                            :class="isDark ? 'border-white/5' : 'border-slate-200'"
                        >
                            <div>
                                <h4
                                    class="text-lg font-bold flex items-center gap-2"
                                    :class="isDark ? 'text-white' : 'text-slate-900'"
                                >
                                    <span>👨‍🍳</span>
                                    <span>{{ t.demo.kdsHeaderTitle }}</span>
                                </h4>
                                <p
                                    class="text-xs mt-1"
                                    :class="isDark ? 'text-slate-400' : 'text-slate-600'"
                                >
                                    {{ t.demo.kdsHeaderSubtitle }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2 text-xs">
                                <span
                                    class="px-3 py-1 rounded-full border font-bold flex items-center gap-1.5"
                                    :class="isDark
                                        ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20'
                                        : 'bg-emerald-50 text-emerald-800 border-emerald-200'"
                                >
                                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                                    Reverb WebSockets Online
                                </span>
                            </div>
                        </div>

                        <!-- Kanban Columns -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <!-- New Orders -->
                            <div
                                class="rounded-2xl border p-3 space-y-3"
                                :class="isDark ? 'bg-slate-900/70 border-amber-500/20' : 'bg-amber-50/70 border-amber-200 shadow-xs'"
                            >
                                <div
                                    class="flex items-center justify-between border-b pb-2"
                                    :class="isDark ? 'border-white/5' : 'border-amber-200/80'"
                                >
                                    <span
                                        class="text-xs font-bold flex items-center gap-1.5"
                                        :class="isDark ? 'text-amber-400' : 'text-amber-900 font-extrabold'"
                                    >
                                        <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                                        {{ t.demo.colNew }}
                                    </span>
                                    <span
                                        class="text-[10px] px-1.5 py-0.5 rounded font-mono font-bold"
                                        :class="isDark ? 'bg-amber-500/20 text-amber-300' : 'bg-amber-100 text-amber-900'"
                                    >
                                        1
                                    </span>
                                </div>
                                <div
                                    v-for="order in kdsOrders.filter(o => o.status === 'new')"
                                    :key="order.id"
                                    class="p-3 rounded-xl border shadow-lg space-y-2.5"
                                    :class="isDark ? 'bg-slate-950 border-amber-500/40' : 'bg-white border-amber-300 shadow-sm'"
                                >
                                    <div class="flex items-center justify-between text-xs font-bold">
                                        <span :class="isDark ? 'text-white' : 'text-slate-900 font-extrabold'">{{ order.table }}</span>
                                        <span
                                            class="text-[10px] px-2 py-0.5 rounded-full font-mono font-bold"
                                            :class="isDark ? 'text-amber-400 bg-amber-500/10' : 'text-amber-800 bg-amber-50 border border-amber-200'"
                                        >
                                            {{ order.time }}
                                        </span>
                                    </div>
                                    <div class="space-y-1.5 text-xs">
                                        <div
                                            v-for="(it, idx) in order.items"
                                            :key="idx"
                                            class="border-b pb-1"
                                            :class="isDark ? 'border-white/5' : 'border-slate-100'"
                                        >
                                            <div
                                                class="flex justify-between font-semibold"
                                                :class="isDark ? 'text-slate-200' : 'text-slate-800 font-bold'"
                                            >
                                                <span>{{ it.qty }}x {{ it.name }}</span>
                                            </div>
                                            <div
                                                v-if="it.note"
                                                class="text-[10px] px-1.5 py-0.5 rounded mt-1 font-semibold"
                                                :class="isDark ? 'text-amber-300 bg-amber-500/10' : 'text-amber-900 bg-amber-100/90'"
                                            >
                                                {{ it.note }}
                                            </div>
                                        </div>
                                    </div>
                                    <div
                                        class="pt-2 flex items-center justify-between border-t"
                                        :class="isDark ? 'border-white/5' : 'border-slate-100'"
                                    >
                                        <span
                                            class="text-xs font-mono font-bold"
                                            :class="isDark ? 'text-slate-300' : 'text-slate-900'"
                                        >
                                            {{ formatPrice(order.total) }} {{ currentCurrencySymbol }}
                                        </span>
                                        <button
                                            @click="advanceKdsOrder(order.id)"
                                            class="px-3 py-1 rounded-lg bg-amber-500 text-slate-950 font-bold text-xs hover:bg-amber-400 transition-all cursor-pointer"
                                        >
                                            {{ t.demo.btnStartCooking }}
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Cooking -->
                            <div
                                class="rounded-2xl border p-3 space-y-3"
                                :class="isDark ? 'bg-slate-900/70 border-orange-500/20' : 'bg-orange-50/70 border-orange-200 shadow-xs'"
                            >
                                <div
                                    class="flex items-center justify-between border-b pb-2"
                                    :class="isDark ? 'border-white/5' : 'border-orange-200/80'"
                                >
                                    <span
                                        class="text-xs font-bold flex items-center gap-1.5"
                                        :class="isDark ? 'text-orange-400' : 'text-orange-900 font-extrabold'"
                                    >
                                        <span class="w-2 h-2 rounded-full bg-orange-500"></span>
                                        {{ t.demo.colCooking }}
                                    </span>
                                    <span
                                        class="text-[10px] px-1.5 py-0.5 rounded font-mono font-bold"
                                        :class="isDark ? 'bg-orange-500/20 text-orange-300' : 'bg-orange-100 text-orange-900'"
                                    >
                                        1
                                    </span>
                                </div>
                                <div
                                    v-for="order in kdsOrders.filter(o => o.status === 'cooking')"
                                    :key="order.id"
                                    class="p-3 rounded-xl border shadow-lg space-y-2.5"
                                    :class="isDark ? 'bg-slate-950 border-orange-500/40' : 'bg-white border-orange-300 shadow-sm'"
                                >
                                    <div class="flex items-center justify-between text-xs font-bold">
                                        <span :class="isDark ? 'text-white' : 'text-slate-900 font-extrabold'">{{ order.table }}</span>
                                        <span
                                            class="text-[10px] px-2 py-0.5 rounded-full font-mono font-bold"
                                            :class="isDark ? 'text-orange-400 bg-orange-500/10' : 'text-orange-800 bg-orange-50 border border-orange-200'"
                                        >
                                            {{ order.time }}
                                        </span>
                                    </div>
                                    <div class="space-y-1.5 text-xs">
                                        <div
                                            v-for="(it, idx) in order.items"
                                            :key="idx"
                                            class="border-b pb-1"
                                            :class="isDark ? 'border-white/5' : 'border-slate-100'"
                                        >
                                            <div
                                                class="flex justify-between font-semibold"
                                                :class="isDark ? 'text-slate-200' : 'text-slate-800 font-bold'"
                                            >
                                                <span>{{ it.qty }}x {{ it.name }}</span>
                                            </div>
                                            <div
                                                v-if="it.note"
                                                class="text-[10px] px-1.5 py-0.5 rounded mt-1 font-semibold"
                                                :class="isDark ? 'text-amber-300 bg-amber-500/10' : 'text-amber-900 bg-amber-100/90'"
                                            >
                                                {{ it.note }}
                                            </div>
                                        </div>
                                    </div>
                                    <div
                                        class="pt-2 flex items-center justify-between border-t"
                                        :class="isDark ? 'border-white/5' : 'border-slate-100'"
                                    >
                                        <span
                                            class="text-xs font-mono font-bold"
                                            :class="isDark ? 'text-slate-300' : 'text-slate-900'"
                                        >
                                            {{ formatPrice(order.total) }} {{ currentCurrencySymbol }}
                                        </span>
                                        <button
                                            @click="advanceKdsOrder(order.id)"
                                            class="px-3 py-1 rounded-lg bg-orange-500 text-slate-950 font-bold text-xs hover:bg-orange-400 transition-all cursor-pointer"
                                        >
                                            {{ t.demo.btnMarkReady }}
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Ready -->
                            <div
                                class="rounded-2xl border p-3 space-y-3"
                                :class="isDark ? 'bg-slate-900/70 border-emerald-500/20' : 'bg-emerald-50/70 border-emerald-200 shadow-xs'"
                            >
                                <div
                                    class="flex items-center justify-between border-b pb-2"
                                    :class="isDark ? 'border-white/5' : 'border-emerald-200/80'"
                                >
                                    <span
                                        class="text-xs font-bold flex items-center gap-1.5"
                                        :class="isDark ? 'text-emerald-400' : 'text-emerald-900 font-extrabold'"
                                    >
                                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                        {{ t.demo.colReady }}
                                    </span>
                                    <span
                                        class="text-[10px] px-1.5 py-0.5 rounded font-mono font-bold"
                                        :class="isDark ? 'bg-emerald-500/20 text-emerald-300' : 'bg-emerald-100 text-emerald-900'"
                                    >
                                        1
                                    </span>
                                </div>
                                <div
                                    v-for="order in kdsOrders.filter(o => o.status === 'ready')"
                                    :key="order.id"
                                    class="p-3 rounded-xl border shadow-lg space-y-2.5"
                                    :class="isDark ? 'bg-slate-950 border-emerald-500/40' : 'bg-white border-emerald-300 shadow-sm'"
                                >
                                    <div class="flex items-center justify-between text-xs font-bold">
                                        <span :class="isDark ? 'text-white' : 'text-slate-900 font-extrabold'">{{ order.table }}</span>
                                        <span
                                            class="text-[10px] px-2 py-0.5 rounded-full font-mono font-bold"
                                            :class="isDark ? 'text-emerald-400 bg-emerald-500/10' : 'text-emerald-800 bg-emerald-50 border border-emerald-200'"
                                        >
                                            {{ order.time }}
                                        </span>
                                    </div>
                                    <div class="space-y-1.5 text-xs">
                                        <div
                                            v-for="(it, idx) in order.items"
                                            :key="idx"
                                            class="border-b pb-1"
                                            :class="isDark ? 'border-white/5' : 'border-slate-100'"
                                        >
                                            <div
                                                class="flex justify-between font-semibold"
                                                :class="isDark ? 'text-slate-200' : 'text-slate-800 font-bold'"
                                            >
                                                <span>{{ it.qty }}x {{ it.name }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div
                                        class="pt-2 flex items-center justify-between border-t"
                                        :class="isDark ? 'border-white/5' : 'border-slate-100'"
                                    >
                                        <span
                                            class="text-xs font-mono font-bold"
                                            :class="isDark ? 'text-slate-300' : 'text-slate-900'"
                                        >
                                            {{ formatPrice(order.total) }} {{ currentCurrencySymbol }}
                                        </span>
                                        <button
                                            @click="advanceKdsOrder(order.id)"
                                            class="px-3 py-1 rounded-lg bg-emerald-500 text-slate-950 font-bold text-xs hover:bg-emerald-400 transition-all cursor-pointer"
                                        >
                                            {{ t.demo.btnMarkServed }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 3: TABLE QR & THERMAL PRINTING -->
                    <div v-else-if="activeDemoTab === 'qr'" class="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
                        <!-- Left: Acrylic Stand -->
                        <div class="flex flex-col items-center text-center space-y-4">
                            <div
                                class="w-64 p-6 rounded-3xl border-2 shadow-2xl relative transition-all"
                                :class="isDark ? 'bg-gradient-to-b from-slate-800 to-slate-900 border-amber-500/40' : 'bg-white border-amber-300 shadow-xl'"
                            >
                                <div class="text-center space-y-2">
                                    <span
                                        class="font-bold text-sm tracking-widest block uppercase"
                                        :class="isDark ? 'text-amber-400' : 'text-amber-700'"
                                    >
                                        TEDALLAL
                                    </span>
                                    <h5
                                        class="text-base font-extrabold"
                                        :class="isDark ? 'text-white' : 'text-slate-900'"
                                    >
                                        {{ t.demo.qrCardTitle }}
                                    </h5>
                                    <div class="w-40 h-40 bg-white rounded-2xl p-2 mx-auto shadow-inner flex items-center justify-center border border-slate-200">
                                        <img src="/images/qr-sample.png" alt="QR" class="w-full h-full object-contain" />
                                    </div>
                                    <div
                                        class="text-xs font-bold pt-1"
                                        :class="isDark ? 'text-amber-300' : 'text-amber-800 font-extrabold'"
                                    >
                                        {{ currentLocale === 'ar' ? 'طاولة رقم 04' : 'Table #04' }}
                                    </div>
                                    <p
                                        class="text-[10px] leading-tight"
                                        :class="isDark ? 'text-slate-400' : 'text-slate-500'"
                                    >
                                        {{ t.demo.qrCardHint }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Right: Thermal Receipt -->
                        <div class="flex flex-col items-center space-y-4">
                            <div class="w-72 bg-white text-slate-950 p-6 rounded-xl shadow-2xl font-mono text-xs space-y-3 border-t-8 border-amber-500 border border-slate-200">
                                <div class="text-center border-b border-dashed border-slate-400 pb-3">
                                    <div class="font-extrabold text-sm tracking-wider">مطعم حلب الشهباء الفاخر</div>
                                    <div class="text-[10px] text-slate-600">{{ t.demo.receiptHeader }}</div>
                                    <div class="text-[9px] text-slate-500">{{ t.demo.receiptTaxNo }}</div>
                                </div>

                                <div class="flex justify-between text-[11px] border-b border-slate-300 pb-1">
                                    <span>{{ currentLocale === 'ar' ? 'طاولة: 04' : 'Table: 04' }}</span>
                                    <span>#INV-9402</span>
                                </div>

                                <div class="space-y-1.5 border-b border-dashed border-slate-400 pb-3 text-xs">
                                    <div class="flex justify-between font-bold">
                                        <span>2x كباب حلبي بالكرز</span>
                                        <span>{{ formatPrice(170000) }}</span>
                                    </div>
                                    <div class="flex justify-between font-bold">
                                        <span>1x مقبلات مشكلة</span>
                                        <span>{{ formatPrice(42000) }}</span>
                                    </div>
                                    <div class="flex justify-between font-bold">
                                        <span>2x عصير رمان مثلج</span>
                                        <span>{{ formatPrice(44000) }}</span>
                                    </div>
                                </div>

                                <div class="space-y-1 text-xs">
                                    <div class="flex justify-between">
                                        <span>{{ t.demo.receiptSubtotal }}</span>
                                        <span>{{ formatPrice(256000) }} {{ currentCurrencySymbol }}</span>
                                    </div>
                                    <div class="flex justify-between text-emerald-700 font-bold">
                                        <span>{{ t.demo.receiptDiscount }}</span>
                                        <span>-{{ formatPrice(38400) }} {{ currentCurrencySymbol }}</span>
                                    </div>
                                    <div class="flex justify-between font-black text-sm border-t border-slate-400 pt-1">
                                        <span>{{ t.demo.receiptTotal }}</span>
                                        <span>{{ formatPrice(217600) }} {{ currentCurrencySymbol }}</span>
                                    </div>
                                </div>

                                <div class="text-center text-[10px] text-slate-500 border-t border-dashed border-slate-400 pt-2">
                                    {{ t.demo.receiptFooter }}
                                </div>
                            </div>
                            <p
                                class="text-xs text-center max-w-sm mx-auto"
                                :class="isDark ? 'text-slate-400' : 'text-slate-500'"
                            >
                                {{ t.demo.receiptHint }}
                            </p>
                        </div>
                    </div>

                    <!-- TAB 4: SMART ANALYTICS & BI -->
                    <div v-else-if="activeDemoTab === 'analytics'" class="space-y-6">
                        <div
                            class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b pb-4"
                            :class="isDark ? 'border-white/5' : 'border-slate-200'"
                        >
                            <div>
                                <h4
                                    class="text-lg font-bold flex items-center gap-2"
                                    :class="isDark ? 'text-white' : 'text-slate-900'"
                                >
                                    <span>📊</span>
                                    <span>{{ t.demo.analyticsTitle }}</span>
                                </h4>
                                <p
                                    class="text-xs mt-1"
                                    :class="isDark ? 'text-slate-400' : 'text-slate-600'"
                                >
                                    {{ t.demo.analyticsSubtitle }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <button
                                    @click="selectedAnalyticsFilter = 'weekend'"
                                    class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer"
                                    :class="selectedAnalyticsFilter === 'weekend'
                                        ? 'bg-amber-500 text-slate-950 font-bold shadow-xs'
                                        : (isDark ? 'bg-slate-900 text-slate-400 border border-white/5' : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-200 shadow-xs')"
                                >
                                    {{ t.demo.filterWeekend }}
                                </button>
                                <button
                                    @click="selectedAnalyticsFilter = 'weekday'"
                                    class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer"
                                    :class="selectedAnalyticsFilter === 'weekday'
                                        ? 'bg-amber-500 text-slate-950 font-bold shadow-xs'
                                        : (isDark ? 'bg-slate-900 text-slate-400 border border-white/5' : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-200 shadow-xs')"
                                >
                                    {{ t.demo.filterWeekday }}
                                </button>
                            </div>
                        </div>

                        <!-- CPA Financial Sales Summary Cards -->
                        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                            <!-- Net Realized Sales -->
                            <div
                                class="p-4 rounded-2xl border shadow-lg relative overflow-hidden"
                                :class="isDark
                                    ? 'bg-gradient-to-br from-emerald-500/15 via-slate-900 to-slate-950 border-emerald-500/40'
                                    : 'bg-gradient-to-br from-emerald-50 via-white to-teal-50 border-emerald-200 shadow-sm'"
                            >
                                <div
                                    class="flex items-center justify-between text-[11px] font-bold mb-1"
                                    :class="isDark ? 'text-emerald-400' : 'text-emerald-800 font-extrabold'"
                                >
                                    <span>{{ currentLocale === 'ar' ? 'صافي المبيعات المحقق' : 'Net Realized Sales' }}</span>
                                    <span
                                        class="px-1.5 py-0.5 rounded text-[9px] font-bold"
                                        :class="isDark ? 'bg-emerald-500/20 text-emerald-300' : 'bg-emerald-100 text-emerald-900'"
                                    >
                                        0% خطأ
                                    </span>
                                </div>
                                <div
                                    class="text-xl sm:text-2xl font-black font-mono"
                                    :class="isDark ? 'text-white' : 'text-slate-950'"
                                >
                                    {{ formatPrice(financialKpis.netSales) }}
                                    <span
                                        class="text-xs font-normal"
                                        :class="isDark ? 'text-amber-400' : 'text-amber-700 font-bold'"
                                    >
                                        {{ currentCurrencySymbol }}
                                    </span>
                                </div>
                                <div
                                    class="text-[10px] mt-1 flex items-center gap-1"
                                    :class="isDark ? 'text-slate-400' : 'text-slate-600 font-medium'"
                                >
                                    <span>{{ currentLocale === 'ar' ? 'إجمالي الطلبات:' : 'Total Orders:' }}</span>
                                    <span
                                        class="font-mono font-bold"
                                        :class="isDark ? 'text-white' : 'text-slate-900'"
                                    >
                                        {{ financialKpis.ordersCount }}
                                    </span>
                                </div>
                            </div>

                            <!-- Gross Sales & Discounts -->
                            <div
                                class="p-4 rounded-2xl border shadow-lg"
                                :class="isDark ? 'bg-slate-900/80 border-white/10' : 'bg-white border-slate-200 shadow-sm'"
                            >
                                <div
                                    class="text-[11px] font-semibold mb-1"
                                    :class="isDark ? 'text-slate-400' : 'text-slate-600'"
                                >
                                    {{ currentLocale === 'ar' ? 'إجمالي المبيعات والخصومات' : 'Gross Sales & Discounts' }}
                                </div>
                                <div
                                    class="text-lg sm:text-xl font-bold font-mono"
                                    :class="isDark ? 'text-slate-200' : 'text-slate-900 font-extrabold'"
                                >
                                    {{ formatPrice(financialKpis.grossSales) }}
                                    <span class="text-xs font-normal" :class="isDark ? 'text-slate-400' : 'text-slate-500'">{{ currentCurrencySymbol }}</span>
                                </div>
                                <div class="text-[10px] text-rose-600 dark:text-rose-400 mt-1 flex items-center justify-between font-bold">
                                    <span>{{ currentLocale === 'ar' ? 'الخصومات:' : 'Discounts:' }}</span>
                                    <span class="font-mono">-{{ formatPrice(financialKpis.discounts) }} {{ currentCurrencySymbol }}</span>
                                </div>
                            </div>

                            <!-- Average Order Value (AOV) -->
                            <div
                                class="p-4 rounded-2xl border shadow-lg"
                                :class="isDark ? 'bg-slate-900/80 border-white/10' : 'bg-white border-slate-200 shadow-sm'"
                            >
                                <div
                                    class="text-[11px] font-semibold mb-1"
                                    :class="isDark ? 'text-slate-400' : 'text-slate-600'"
                                >
                                    {{ currentLocale === 'ar' ? 'متوسط الفاتورة (AOV)' : 'Avg Order Value (AOV)' }}
                                </div>
                                <div
                                    class="text-lg sm:text-xl font-bold font-mono"
                                    :class="isDark ? 'text-amber-400' : 'text-amber-800 font-extrabold'"
                                >
                                    {{ formatPrice(financialKpis.aov) }}
                                    <span class="text-xs font-normal" :class="isDark ? 'text-amber-300' : 'text-amber-700'">{{ currentCurrencySymbol }}</span>
                                </div>
                                <div
                                    class="text-[10px] mt-1 flex items-center gap-1 font-bold"
                                    :class="isDark ? 'text-emerald-400' : 'text-emerald-700'"
                                >
                                    <span>▲ +28%</span>
                                    <span :class="isDark ? 'text-slate-400' : 'text-slate-600 font-normal'">{{ currentLocale === 'ar' ? 'نمو سلة الشراء' : 'Upsell Growth' }}</span>
                                </div>
                            </div>

                            <!-- Dine-in vs Takeaway Breakdown -->
                            <div
                                class="p-4 rounded-2xl border shadow-lg"
                                :class="isDark ? 'bg-slate-900/80 border-white/10' : 'bg-white border-slate-200 shadow-sm'"
                            >
                                <div
                                    class="text-[11px] font-semibold mb-1"
                                    :class="isDark ? 'text-slate-400' : 'text-slate-600'"
                                >
                                    {{ currentLocale === 'ar' ? 'الصالة مقابل السفري' : 'Dine-In vs Takeaway' }}
                                </div>
                                <div class="text-xs space-y-1 font-mono pt-0.5">
                                    <div
                                        class="flex justify-between"
                                        :class="isDark ? 'text-slate-300' : 'text-slate-800 font-semibold'"
                                    >
                                        <span class="text-[10px]" :class="isDark ? 'text-slate-400' : 'text-slate-500'">{{ currentLocale === 'ar' ? '🍽️ صالة:' : '🍽️ Dine-in:' }}</span>
                                        <span class="font-bold">{{ formatPrice(financialKpis.dineInNet) }} {{ currentCurrencySymbol }}</span>
                                    </div>
                                    <div
                                        class="flex justify-between"
                                        :class="isDark ? 'text-slate-300' : 'text-slate-800 font-semibold'"
                                    >
                                        <span class="text-[10px]" :class="isDark ? 'text-slate-400' : 'text-slate-500'">{{ currentLocale === 'ar' ? '⚡ سفري:' : '⚡ Takeaway:' }}</span>
                                        <span class="font-bold">{{ formatPrice(financialKpis.takeawayNet) }} {{ currentCurrencySymbol }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                            <!-- Peak Hours Chart -->
                            <div
                                class="lg:col-span-7 p-5 rounded-2xl border space-y-4"
                                :class="isDark ? 'bg-slate-900/60 border-white/5' : 'bg-white border-slate-200 shadow-sm'"
                            >
                                <h5
                                    class="text-sm font-bold flex items-center justify-between"
                                    :class="isDark ? 'text-white' : 'text-slate-900'"
                                >
                                    <span>{{ t.demo.chartPeakTitle }}</span>
                                    <span
                                        class="text-xs font-mono font-bold"
                                        :class="isDark ? 'text-amber-400' : 'text-amber-700'"
                                    >
                                        {{ t.demo.ordersUnit }} / hr
                                    </span>
                                </h5>

                                <div
                                    class="h-44 pt-4 flex items-end justify-between gap-2 border-b pb-2"
                                    :class="isDark ? 'border-white/10' : 'border-slate-200'"
                                >
                                    <div
                                        v-for="(bar, idx) in peakHoursData"
                                        :key="idx"
                                        class="flex-1 flex flex-col items-center gap-1 group relative cursor-pointer"
                                    >
                                        <div class="w-full h-36 flex items-end justify-center">
                                            <div
                                                class="w-full max-w-[28px] rounded-t-lg transition-all duration-500 group-hover:brightness-125"
                                                :class="bar.isPeak
                                                    ? 'bg-gradient-to-t from-orange-500 to-amber-400 shadow-lg shadow-amber-500/30'
                                                    : (isDark ? 'bg-slate-800 hover:bg-slate-700' : 'bg-slate-200 hover:bg-slate-300')"
                                                :style="{ height: `${bar.height}%` }"
                                            ></div>
                                        </div>
                                        <span
                                            class="text-[11px] font-mono"
                                            :class="bar.isPeak
                                                ? (isDark ? 'text-amber-400 font-extrabold' : 'text-amber-800 font-black')
                                                : (isDark ? 'text-slate-400' : 'text-slate-600')"
                                        >
                                            {{ bar.time }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Top Dishes -->
                            <div
                                class="lg:col-span-5 p-5 rounded-2xl border space-y-4"
                                :class="isDark ? 'bg-slate-900/60 border-white/5' : 'bg-white border-slate-200 shadow-sm'"
                            >
                                <h5
                                    class="text-sm font-bold"
                                    :class="isDark ? 'text-white' : 'text-slate-900'"
                                >
                                    {{ t.demo.chartTopDishesTitle }}
                                </h5>
                                <div class="space-y-3 pt-2">
                                    <div v-for="(dish, idx) in topDishesData" :key="idx" class="space-y-1">
                                        <div class="flex justify-between text-xs font-semibold">
                                            <span :class="isDark ? 'text-slate-200' : 'text-slate-800 font-bold'">{{ dish.name }}</span>
                                            <span
                                                class="font-mono font-bold"
                                                :class="isDark ? 'text-amber-400' : 'text-amber-700'"
                                            >
                                                {{ dish.percent }}% ({{ dish.orders }} {{ t.demo.ordersUnit }})
                                            </span>
                                        </div>
                                        <div
                                            class="w-full h-2 rounded-full overflow-hidden"
                                            :class="isDark ? 'bg-slate-800' : 'bg-slate-200'"
                                        >
                                            <div class="h-full rounded-full transition-all duration-700" :class="dish.color" :style="{ width: `${dish.percent}%` }"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Rule-Based Smart Advice Engine -->
                        <div
                            class="p-4 rounded-2xl border flex items-start gap-3 shadow-xs"
                            :class="isDark ? 'bg-amber-500/10 border-amber-500/30' : 'bg-amber-50/90 border-amber-200 text-amber-950'"
                        >
                            <span class="text-xl">💡</span>
                            <div>
                                <h5
                                    class="text-sm font-bold"
                                    :class="isDark ? 'text-amber-300' : 'text-amber-900 font-extrabold'"
                                >
                                    {{ t.demo.aiInsightTitle }}
                                </h5>
                                <p
                                    class="text-xs mt-1 leading-relaxed"
                                    :class="isDark ? 'text-slate-300' : 'text-slate-700'"
                                >
                                    {{ t.demo.aiInsightText }} {{ formatPrice(35000) }} {{ currentCurrencySymbol }}.
                                </p>
                            </div>
                        </div>
                    </div>

                </div>

            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- CORE SAAS TECHNICAL FEATURES -->
        <!-- ========================================================================= -->
        <section
            id="features"
            class="py-20 relative z-10 border-t transition-colors duration-500"
            :class="isDark ? 'border-white/5 bg-[#080d19]/60' : 'border-slate-200/90 bg-white'"
        >
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                <div class="text-center max-w-3xl mx-auto mb-16 space-y-4">
                    <div
                        class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold border shadow-xs"
                        :class="isDark ? 'bg-orange-500/10 text-orange-400 border-orange-500/20' : 'bg-orange-50 text-orange-800 border-orange-200/90'"
                    >
                        <span>{{ t.features.tag }}</span>
                    </div>
                    <h2
                        class="text-3xl sm:text-4xl font-extrabold tracking-tight"
                        :class="isDark ? 'text-white' : 'text-slate-900'"
                    >
                        {{ t.features.title }}
                    </h2>
                    <p
                        class="text-base sm:text-lg"
                        :class="isDark ? 'text-slate-400' : 'text-slate-600'"
                    >
                        {{ t.features.subtitle }}
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <!-- Feature 1 -->
                    <div
                        class="p-8 rounded-3xl border transition-all duration-300 hover:-translate-y-1 group"
                        :class="isDark ? 'bg-slate-900/60 border-white/5 hover:border-amber-500/30' : 'bg-white border-slate-200/90 hover:border-amber-400 hover:shadow-xl shadow-xs'"
                    >
                        <div
                            class="w-14 h-14 rounded-2xl border flex items-center justify-center text-2xl mb-6 group-hover:scale-110 transition-transform shadow-xs"
                            :class="isDark ? 'bg-amber-500/10 text-amber-400 border-amber-500/20' : 'bg-amber-50 text-amber-700 border-amber-200/90'"
                        >
                            ⚡
                        </div>
                        <h4
                            class="text-xl font-bold mb-3"
                            :class="isDark ? 'text-white' : 'text-slate-900 font-extrabold'"
                        >{{ t.features.f1Title }}</h4>
                        <p
                            class="text-sm leading-relaxed"
                            :class="isDark ? 'text-slate-400' : 'text-slate-600'"
                        >{{ t.features.f1Desc }}</p>
                    </div>

                    <!-- Feature 2 -->
                    <div
                        class="p-8 rounded-3xl border transition-all duration-300 hover:-translate-y-1 group"
                        :class="isDark ? 'bg-slate-900/60 border-white/5 hover:border-amber-500/30' : 'bg-white border-slate-200/90 hover:border-amber-400 hover:shadow-xl shadow-xs'"
                    >
                        <div
                            class="w-14 h-14 rounded-2xl border flex items-center justify-center text-2xl mb-6 group-hover:scale-110 transition-transform shadow-xs"
                            :class="isDark ? 'bg-orange-500/10 text-orange-400 border-orange-500/20' : 'bg-orange-50 text-orange-700 border-orange-200/90'"
                        >
                            📱
                        </div>
                        <h4
                            class="text-xl font-bold mb-3"
                            :class="isDark ? 'text-white' : 'text-slate-900 font-extrabold'"
                        >{{ t.features.f2Title }}</h4>
                        <p
                            class="text-sm leading-relaxed"
                            :class="isDark ? 'text-slate-400' : 'text-slate-600'"
                        >{{ t.features.f2Desc }}</p>
                    </div>

                    <!-- Feature 3 -->
                    <div
                        class="p-8 rounded-3xl border transition-all duration-300 hover:-translate-y-1 group"
                        :class="isDark ? 'bg-slate-900/60 border-white/5 hover:border-amber-500/30' : 'bg-white border-slate-200/90 hover:border-amber-400 hover:shadow-xl shadow-xs'"
                    >
                        <div
                            class="w-14 h-14 rounded-2xl border flex items-center justify-center text-2xl mb-6 group-hover:scale-110 transition-transform shadow-xs"
                            :class="isDark ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' : 'bg-emerald-50 text-emerald-700 border-emerald-200/90'"
                        >
                            👨‍🍳
                        </div>
                        <h4
                            class="text-xl font-bold mb-3"
                            :class="isDark ? 'text-white' : 'text-slate-900 font-extrabold'"
                        >{{ t.features.f3Title }}</h4>
                        <p
                            class="text-sm leading-relaxed"
                            :class="isDark ? 'text-slate-400' : 'text-slate-600'"
                        >{{ t.features.f3Desc }}</p>
                    </div>

                    <!-- Feature 4 -->
                    <div
                        class="p-8 rounded-3xl border transition-all duration-300 hover:-translate-y-1 group"
                        :class="isDark ? 'bg-slate-900/60 border-white/5 hover:border-amber-500/30' : 'bg-white border-slate-200/90 hover:border-amber-400 hover:shadow-xl shadow-xs'"
                    >
                        <div
                            class="w-14 h-14 rounded-2xl border flex items-center justify-center text-2xl mb-6 group-hover:scale-110 transition-transform shadow-xs"
                            :class="isDark ? 'bg-amber-400/10 text-amber-300 border-amber-400/20' : 'bg-amber-50 text-amber-700 border-amber-200/90'"
                        >
                            🖨️
                        </div>
                        <h4
                            class="text-xl font-bold mb-3"
                            :class="isDark ? 'text-white' : 'text-slate-900 font-extrabold'"
                        >{{ t.features.f4Title }}</h4>
                        <p
                            class="text-sm leading-relaxed"
                            :class="isDark ? 'text-slate-400' : 'text-slate-600'"
                        >{{ t.features.f4Desc }}</p>
                    </div>

                    <!-- Feature 5 -->
                    <div
                        class="p-8 rounded-3xl border transition-all duration-300 hover:-translate-y-1 group"
                        :class="isDark ? 'bg-slate-900/60 border-white/5 hover:border-amber-500/30' : 'bg-white border-slate-200/90 hover:border-amber-400 hover:shadow-xl shadow-xs'"
                    >
                        <div
                            class="w-14 h-14 rounded-2xl border flex items-center justify-center text-2xl mb-6 group-hover:scale-110 transition-transform shadow-xs"
                            :class="isDark ? 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20' : 'bg-indigo-50 text-indigo-700 border-indigo-200/90'"
                        >
                            🏢
                        </div>
                        <h4
                            class="text-xl font-bold mb-3"
                            :class="isDark ? 'text-white' : 'text-slate-900 font-extrabold'"
                        >{{ t.features.f5Title }}</h4>
                        <p
                            class="text-sm leading-relaxed"
                            :class="isDark ? 'text-slate-400' : 'text-slate-600'"
                        >{{ t.features.f5Desc }}</p>
                    </div>

                    <!-- Feature 6: CPA Reports -->
                    <div
                        class="p-8 rounded-3xl border transition-all duration-300 hover:-translate-y-1 group relative overflow-hidden"
                        :class="isDark ? 'bg-slate-900/60 border-white/5 hover:border-emerald-500/40' : 'bg-white border-slate-200/90 hover:border-emerald-500 hover:shadow-xl shadow-xs'"
                    >
                        <div
                            class="w-14 h-14 rounded-2xl border flex items-center justify-center text-2xl mb-6 group-hover:scale-110 transition-transform shadow-xs"
                            :class="isDark ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' : 'bg-emerald-50 text-emerald-700 border-emerald-200/90'"
                        >
                            📊
                        </div>
                        <h4
                            class="text-xl font-bold mb-3 flex items-center gap-2"
                            :class="isDark ? 'text-white' : 'text-slate-900 font-extrabold'"
                        >
                            <span>{{ t.features.f6Title }}</span>
                        </h4>
                        <p
                            class="text-sm leading-relaxed"
                            :class="isDark ? 'text-slate-400' : 'text-slate-600'"
                        >{{ t.features.f6Desc }}</p>
                    </div>
                </div>

            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- RESTAURANT DIRECTORY & FREE PROMOTION -->
        <!-- ========================================================================= -->
        <!-- ========================================================================= -->
        <!-- RESTAURANT DIRECTORY & FREE PROMOTION -->
        <!-- ========================================================================= -->
        <section
            id="directory"
            class="py-24 relative z-10 border-t transition-colors duration-500"
            :class="isDark ? 'border-white/5 bg-[#070b14]' : 'border-slate-200/90 bg-slate-50/60'"
        >
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                <div
                    class="relative overflow-hidden rounded-3xl border p-8 sm:p-14 backdrop-blur-2xl transition-all"
                    :class="isDark
                        ? 'bg-gradient-to-br from-amber-500/10 via-slate-900/90 to-orange-500/10 border-amber-500/30 shadow-2xl'
                        : 'bg-gradient-to-br from-amber-50/80 via-white to-orange-50/80 border-amber-300/80 shadow-2xl'"
                >
                    <div
                        class="absolute -top-24 -right-24 w-80 h-80 rounded-full blur-3xl pointer-events-none"
                        :class="isDark ? 'bg-amber-500/15' : 'bg-amber-300/30'"
                    ></div>
                    <div
                        class="absolute -bottom-24 -left-24 w-80 h-80 rounded-full blur-3xl pointer-events-none"
                        :class="isDark ? 'bg-orange-500/15' : 'bg-orange-300/30'"
                    ></div>

                    <div class="relative z-10 max-w-3xl mx-auto text-center space-y-5 mb-12">
                        <div
                            class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold border shadow-xs"
                            :class="isDark ? 'bg-amber-500/15 text-amber-300 border-amber-500/30' : 'bg-amber-100/90 text-amber-900 border-amber-300 font-bold'"
                        >
                            <span>{{ t.directoryPromo.tag }}</span>
                        </div>
                        <h2
                            class="text-3xl sm:text-5xl font-black tracking-tight"
                            :class="isDark ? 'text-white' : 'text-slate-950 font-black'"
                        >
                            {{ t.directoryPromo.title }}
                        </h2>
                        <p
                            class="text-sm sm:text-base leading-relaxed"
                            :class="isDark ? 'text-slate-300' : 'text-slate-700'"
                        >
                            {{ t.directoryPromo.subtitle }}
                        </p>
                        <div
                            class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full text-xs sm:text-sm font-bold border shadow-xs"
                            :class="isDark ? 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30' : 'bg-emerald-100/90 text-emerald-900 border-emerald-300'"
                        >
                            <span>✨</span>
                            <span>{{ t.directoryPromo.badge }}</span>
                        </div>
                    </div>

                    <!-- 3 Benefit Pillars -->
                    <div class="relative z-10 grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div
                            class="p-6 rounded-2xl border transition-all"
                            :class="isDark ? 'bg-slate-950/70 border-white/5 hover:border-amber-500/30' : 'bg-white border-slate-200/90 hover:border-amber-400 hover:shadow-xl shadow-xs'"
                        >
                            <div
                                class="w-12 h-12 rounded-xl flex items-center justify-center text-2xl mb-4 shadow-xs"
                                :class="isDark ? 'bg-amber-500/15 text-amber-400' : 'bg-amber-100/90 text-amber-800'"
                            >
                                📍
                            </div>
                            <h4
                                class="text-base font-bold mb-2"
                                :class="isDark ? 'text-white' : 'text-slate-900 font-extrabold'"
                            >{{ t.directoryPromo.benefit1Title }}</h4>
                            <p
                                class="text-xs leading-relaxed"
                                :class="isDark ? 'text-slate-400' : 'text-slate-600'"
                            >{{ t.directoryPromo.benefit1Desc }}</p>
                        </div>

                        <div
                            class="p-6 rounded-2xl border transition-all"
                            :class="isDark ? 'bg-slate-950/70 border-white/5 hover:border-amber-500/30' : 'bg-white border-slate-200/90 hover:border-amber-400 hover:shadow-xl shadow-xs'"
                        >
                            <div
                                class="w-12 h-12 rounded-xl flex items-center justify-center text-2xl mb-4 shadow-xs"
                                :class="isDark ? 'bg-orange-500/15 text-orange-400' : 'bg-orange-100/90 text-orange-800'"
                            >
                                🎟️
                            </div>
                            <h4
                                class="text-base font-bold mb-2"
                                :class="isDark ? 'text-white' : 'text-slate-900 font-extrabold'"
                            >{{ t.directoryPromo.benefit2Title }}</h4>
                            <p
                                class="text-xs leading-relaxed"
                                :class="isDark ? 'text-slate-400' : 'text-slate-600'"
                            >{{ t.directoryPromo.benefit2Desc }}</p>
                        </div>

                        <div
                            class="p-6 rounded-2xl border transition-all"
                            :class="isDark ? 'bg-slate-950/70 border-white/5 hover:border-amber-500/30' : 'bg-white border-slate-200/90 hover:border-amber-400 hover:shadow-xl shadow-xs'"
                        >
                            <div
                                class="w-12 h-12 rounded-xl flex items-center justify-center text-2xl mb-4 shadow-xs"
                                :class="isDark ? 'bg-emerald-500/15 text-emerald-400' : 'bg-emerald-100/90 text-emerald-800'"
                            >
                                💰
                            </div>
                            <h4
                                class="text-base font-bold mb-2"
                                :class="isDark ? 'text-white' : 'text-slate-900 font-extrabold'"
                            >{{ t.directoryPromo.benefit3Title }}</h4>
                            <p
                                class="text-xs leading-relaxed"
                                :class="isDark ? 'text-slate-400' : 'text-slate-600'"
                            >{{ t.directoryPromo.benefit3Desc }}</p>
                        </div>
                    </div>

                    <!-- Join CTA -->
                    <div class="relative z-10 pt-10 text-center">
                        <button
                            @click="isDemoModalOpen = true"
                            class="inline-flex items-center gap-3 px-8 py-4 rounded-2xl bg-gradient-to-r from-amber-500 via-amber-400 to-orange-500 text-slate-950 font-black text-sm sm:text-base shadow-xl shadow-amber-500/25 hover:shadow-amber-500/40 hover:scale-[1.02] active:scale-[0.98] transition-all cursor-pointer"
                        >
                            <span>{{ currentLocale === 'ar' ? 'سجّل مطعمك في دليل تدلّل الآن 🚀' : 'Feature Your Restaurant on Tedallal Now 🚀' }}</span>
                        </button>
                    </div>

                </div>

            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- ROI & SAVINGS CALCULATOR -->
        <!-- ========================================================================= -->
        <!-- ========================================================================= -->
        <!-- ROI & SAVINGS CALCULATOR -->
        <!-- ========================================================================= -->
        <section
            id="roi-calculator"
            class="py-24 relative z-10 border-t transition-colors duration-500"
            :class="isDark ? 'border-white/5 bg-[#070b14]' : 'border-slate-200/90 bg-white'"
        >
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                <div class="text-center max-w-3xl mx-auto mb-16 space-y-4">
                    <div
                        class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold border shadow-xs"
                        :class="isDark ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' : 'bg-emerald-50 text-emerald-800 border-emerald-200/90 font-bold'"
                    >
                        <span>{{ t.roi.tag }}</span>
                    </div>
                    <h2
                        class="text-3xl sm:text-4xl font-extrabold tracking-tight"
                        :class="isDark ? 'text-white' : 'text-slate-900'"
                    >
                        {{ t.roi.title }}
                    </h2>
                    <p
                        class="text-base sm:text-lg"
                        :class="isDark ? 'text-slate-400' : 'text-slate-600'"
                    >
                        {{ t.roi.subtitle }}
                    </p>
                </div>

                <div
                    class="max-w-4xl mx-auto p-8 sm:p-12 rounded-3xl border shadow-2xl backdrop-blur-xl transition-all"
                    :class="isDark ? 'bg-slate-900/80 border-white/10' : 'bg-slate-50/70 border-slate-200/90 shadow-2xl'"
                >
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-10 items-center">

                        <!-- Sliders Controls -->
                        <div class="space-y-8">
                            <!-- Table Count Slider -->
                            <div class="space-y-3">
                                <div class="flex justify-between items-center text-sm">
                                    <span
                                        class="font-bold"
                                        :class="isDark ? 'text-white' : 'text-slate-900 font-extrabold'"
                                    >{{ t.roi.tablesLabel }}</span>
                                    <span
                                        class="font-extrabold text-xl font-mono"
                                        :class="isDark ? 'text-amber-400' : 'text-amber-800 font-black'"
                                    >{{ tableCount }} {{ t.roi.tablesUnit }}</span>
                                </div>
                                <input
                                    type="range"
                                    min="5"
                                    max="120"
                                    step="5"
                                    v-model.number="tableCount"
                                    class="w-full accent-amber-500 h-2.5 rounded-lg cursor-pointer"
                                    :class="isDark ? 'bg-slate-800' : 'bg-slate-200'"
                                />
                                <div
                                    class="flex justify-between text-[11px] font-mono"
                                    :class="isDark ? 'text-slate-500' : 'text-slate-400 font-semibold'"
                                >
                                    <span>5</span>
                                    <span>120</span>
                                </div>
                            </div>

                            <!-- Daily Orders Slider -->
                            <div class="space-y-3">
                                <div class="flex justify-between items-center text-sm">
                                    <span
                                        class="font-bold"
                                        :class="isDark ? 'text-white' : 'text-slate-900 font-extrabold'"
                                    >{{ t.roi.dailyOrdersLabel }}</span>
                                    <span
                                        class="font-extrabold text-xl font-mono"
                                        :class="isDark ? 'text-amber-400' : 'text-amber-800 font-black'"
                                    >{{ dailyOrdersPerTable }} {{ t.roi.dailyOrdersUnit }}</span>
                                </div>
                                <input
                                    type="range"
                                    min="2"
                                    max="15"
                                    step="1"
                                    v-model.number="dailyOrdersPerTable"
                                    class="w-full accent-amber-500 h-2.5 rounded-lg cursor-pointer"
                                    :class="isDark ? 'bg-slate-800' : 'bg-slate-200'"
                                />
                                <div
                                    class="flex justify-between text-[11px] font-mono"
                                    :class="isDark ? 'text-slate-500' : 'text-slate-400 font-semibold'"
                                >
                                    <span>2</span>
                                    <span>15</span>
                                </div>
                            </div>

                            <div
                                class="p-4 rounded-2xl border text-xs space-y-1"
                                :class="isDark ? 'bg-white/[0.02] border-white/5 text-slate-400' : 'bg-white border-slate-200/90 text-slate-600 shadow-xs'"
                            >
                                <div class="flex justify-between">
                                    <span>{{ t.roi.monthlyOrdersEst }}</span>
                                    <strong
                                        class="font-mono font-bold"
                                        :class="isDark ? 'text-white' : 'text-slate-900 font-extrabold'"
                                    >{{ monthlyOrders.toLocaleString('en-US') }} {{ t.roi.ordersCountUnit }}</strong>
                                </div>
                                <div class="flex justify-between">
                                    <span>{{ t.roi.avgTimeSavedLabel }}</span>
                                    <strong
                                        class="font-mono font-bold"
                                        :class="isDark ? 'text-emerald-400' : 'text-emerald-700'"
                                    >{{ t.roi.avgTimeSavedVal }}</strong>
                                </div>
                            </div>
                        </div>

                        <!-- Computed Impact Output -->
                        <div
                            class="space-y-4 p-6 rounded-2xl border"
                            :class="isDark ? 'bg-slate-950 border-amber-500/20' : 'bg-white border-slate-200/90 shadow-md'"
                        >

                            <div
                                class="p-4 rounded-xl border"
                                :class="isDark ? 'bg-amber-500/10 border-amber-500/30' : 'bg-amber-50/90 border-amber-200 shadow-xs'"
                            >
                                <div
                                    class="text-xs font-semibold"
                                    :class="isDark ? 'text-amber-300' : 'text-amber-900 font-bold'"
                                >{{ t.roi.paperSavingsTitle }}</div>
                                <div
                                    class="text-2xl sm:text-3xl font-black font-mono mt-1"
                                    :class="isDark ? 'text-amber-400' : 'text-amber-800 font-black'"
                                >
                                    {{ formatPrice(yearlyPaperSavings) }} {{ currentCurrencySymbol }}
                                </div>
                                <div
                                    class="text-[11px] mt-1"
                                    :class="isDark ? 'text-slate-400' : 'text-slate-600 font-medium'"
                                >{{ t.roi.paperSavingsNote }}</div>
                            </div>

                            <div
                                class="p-4 rounded-xl border"
                                :class="isDark ? 'bg-emerald-500/10 border-emerald-500/30' : 'bg-emerald-50/90 border-emerald-200 shadow-xs'"
                            >
                                <div
                                    class="text-xs font-semibold"
                                    :class="isDark ? 'text-emerald-300' : 'text-emerald-900 font-bold'"
                                >{{ t.roi.laborHoursTitle }}</div>
                                <div
                                    class="text-2xl sm:text-3xl font-black font-mono mt-1"
                                    :class="isDark ? 'text-emerald-400' : 'text-emerald-800 font-black'"
                                >
                                    {{ timeSavedHoursMonthly.toLocaleString('en-US') }} {{ t.roi.laborHoursUnit }}
                                </div>
                                <div
                                    class="text-[11px] mt-1"
                                    :class="isDark ? 'text-slate-400' : 'text-slate-600 font-medium'"
                                >{{ t.roi.laborHoursNote }}</div>
                            </div>

                            <div
                                class="p-4 rounded-xl border"
                                :class="isDark ? 'bg-orange-500/10 border-orange-500/30' : 'bg-orange-50/90 border-orange-200 shadow-xs'"
                            >
                                <div
                                    class="text-xs font-semibold"
                                    :class="isDark ? 'text-orange-300' : 'text-orange-900 font-bold'"
                                >{{ t.roi.revenueBoostTitle }}</div>
                                <div
                                    class="text-2xl sm:text-3xl font-black font-mono mt-1"
                                    :class="isDark ? 'text-orange-400' : 'text-orange-800 font-black'"
                                >
                                    +{{ formatPrice(estimatedRevenueBoost) }} {{ currentCurrencySymbol }} {{ t.roi.revenueBoostPerMonth }}
                                </div>
                                <div
                                    class="text-[11px] mt-1"
                                    :class="isDark ? 'text-slate-400' : 'text-slate-600 font-medium'"
                                >{{ t.roi.revenueBoostNote }}</div>
                            </div>

                        </div>

                    </div>
                </div>

            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- COMPARISON: TRADITIONAL VS TEDALLAL -->
        <!-- ========================================================================= -->
        <section
            id="comparison"
            class="py-20 border-t transition-colors duration-500 relative z-10"
            :class="isDark ? 'border-white/5 bg-[#090e1c]' : 'border-slate-200/90 bg-slate-50/60'"
        >
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                <div class="text-center max-w-3xl mx-auto mb-16 space-y-4">
                    <div
                        class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold border shadow-xs"
                        :class="isDark ? 'bg-rose-500/10 text-rose-400 border-rose-500/20' : 'bg-rose-50 text-rose-800 border-rose-200/90 font-bold'"
                    >
                        <span>{{ t.comparison.tag }}</span>
                    </div>
                    <h2
                        class="text-3xl sm:text-4xl font-extrabold tracking-tight"
                        :class="isDark ? 'text-white' : 'text-slate-900'"
                    >
                        {{ t.comparison.title }}
                    </h2>
                    <p
                        class="text-base sm:text-lg"
                        :class="isDark ? 'text-slate-400' : 'text-slate-600'"
                    >
                        {{ t.comparison.subtitle }}
                    </p>
                </div>

                <div
                    class="max-w-4xl mx-auto overflow-hidden rounded-3xl border shadow-2xl transition-all"
                    :class="isDark ? 'border-white/10 bg-slate-900/60 backdrop-blur-xl' : 'border-slate-200/90 bg-white shadow-xl'"
                >
                    <div
                        class="grid grid-cols-12 border-b p-4 sm:p-6 text-xs sm:text-sm font-bold"
                        :class="isDark ? 'bg-slate-950 border-white/10' : 'bg-slate-100/90 border-slate-200/90'"
                    >
                        <div
                            class="col-span-4 sm:col-span-4"
                            :class="isDark ? 'text-slate-400' : 'text-slate-700 font-bold'"
                        >{{ currentLocale === 'ar' ? 'المعيار التشغيلي' : 'Operational Metric' }}</div>
                        <div
                            class="col-span-4 sm:col-span-4"
                            :class="isDark ? 'text-rose-400' : 'text-rose-700 font-bold'"
                        >{{ t.comparison.colTraditional }}</div>
                        <div
                            class="col-span-4 sm:col-span-4 font-black"
                            :class="isDark ? 'text-amber-400' : 'text-amber-800 font-black'"
                        >{{ t.comparison.colTedallal }}</div>
                    </div>

                    <div
                        class="divide-y text-xs sm:text-sm"
                        :class="isDark ? 'divide-white/5' : 'divide-slate-200/80'"
                    >
                        <!-- Row 1 -->
                        <div
                            class="grid grid-cols-12 p-4 sm:p-6 items-center transition-colors"
                            :class="isDark ? 'hover:bg-white/[0.02]' : 'hover:bg-amber-50/40'"
                        >
                            <div
                                class="col-span-4 font-bold"
                                :class="isDark ? 'text-white' : 'text-slate-900 font-bold'"
                            >{{ t.comparison.r1Label }}</div>
                            <div
                                class="col-span-4 leading-relaxed"
                                :class="isDark ? 'text-slate-400' : 'text-slate-600 font-medium'"
                            >{{ t.comparison.r1Trad }}</div>
                            <div
                                class="col-span-4 font-bold leading-relaxed"
                                :class="isDark ? 'text-amber-300' : 'text-amber-800 font-extrabold'"
                            >{{ t.comparison.r1Ted }}</div>
                        </div>

                        <!-- Row 2 -->
                        <div
                            class="grid grid-cols-12 p-4 sm:p-6 items-center transition-colors"
                            :class="isDark ? 'hover:bg-white/[0.02]' : 'hover:bg-amber-50/40'"
                        >
                            <div
                                class="col-span-4 font-bold"
                                :class="isDark ? 'text-white' : 'text-slate-900 font-bold'"
                            >{{ t.comparison.r2Label }}</div>
                            <div
                                class="col-span-4 leading-relaxed"
                                :class="isDark ? 'text-slate-400' : 'text-slate-600 font-medium'"
                            >{{ t.comparison.r2Trad }}</div>
                            <div
                                class="col-span-4 font-bold leading-relaxed"
                                :class="isDark ? 'text-amber-300' : 'text-amber-800 font-extrabold'"
                            >{{ t.comparison.r2Ted }}</div>
                        </div>

                        <!-- Row 3 -->
                        <div
                            class="grid grid-cols-12 p-4 sm:p-6 items-center transition-colors"
                            :class="isDark ? 'hover:bg-white/[0.02]' : 'hover:bg-amber-50/40'"
                        >
                            <div
                                class="col-span-4 font-bold"
                                :class="isDark ? 'text-white' : 'text-slate-900 font-bold'"
                            >{{ t.comparison.r3Label }}</div>
                            <div
                                class="col-span-4 leading-relaxed"
                                :class="isDark ? 'text-slate-400' : 'text-slate-600 font-medium'"
                            >{{ t.comparison.r3Trad }}</div>
                            <div
                                class="col-span-4 font-bold leading-relaxed"
                                :class="isDark ? 'text-amber-300' : 'text-amber-800 font-extrabold'"
                            >{{ t.comparison.r3Ted }}</div>
                        </div>

                        <!-- Row 4 -->
                        <div
                            class="grid grid-cols-12 p-4 sm:p-6 items-center transition-colors"
                            :class="isDark ? 'hover:bg-white/[0.02]' : 'hover:bg-amber-50/40'"
                        >
                            <div
                                class="col-span-4 font-bold"
                                :class="isDark ? 'text-white' : 'text-slate-900 font-bold'"
                            >{{ t.comparison.r4Label }}</div>
                            <div
                                class="col-span-4 leading-relaxed"
                                :class="isDark ? 'text-slate-400' : 'text-slate-600 font-medium'"
                            >{{ t.comparison.r4Trad }}</div>
                            <div
                                class="col-span-4 font-bold leading-relaxed"
                                :class="isDark ? 'text-amber-300' : 'text-amber-800 font-extrabold'"
                            >{{ t.comparison.r4Ted }}</div>
                        </div>

                        <!-- Row 5 -->
                        <div
                            class="grid grid-cols-12 p-4 sm:p-6 items-center transition-colors"
                            :class="isDark ? 'hover:bg-white/[0.02]' : 'hover:bg-amber-50/40'"
                        >
                            <div
                                class="col-span-4 font-bold"
                                :class="isDark ? 'text-white' : 'text-slate-900 font-bold'"
                            >{{ t.comparison.r5Label }}</div>
                            <div
                                class="col-span-4 leading-relaxed"
                                :class="isDark ? 'text-slate-400' : 'text-slate-600 font-medium'"
                            >{{ t.comparison.r5Trad }}</div>
                            <div
                                class="col-span-4 font-bold leading-relaxed"
                                :class="isDark ? 'text-amber-300' : 'text-amber-800 font-extrabold'"
                            >{{ t.comparison.r5Ted }}</div>
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- TESTIMONIALS -->
        <!-- ========================================================================= -->
        <section
            class="py-20 relative z-10 border-t transition-colors duration-500"
            :class="isDark ? 'border-white/5 bg-[#070b14]' : 'border-slate-200/90 bg-white'"
        >
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                <div class="text-center max-w-3xl mx-auto mb-16 space-y-4">
                    <div
                        class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold border shadow-xs"
                        :class="isDark ? 'bg-amber-500/10 text-amber-400 border-amber-500/20' : 'bg-amber-50 text-amber-800 border-amber-200/90 font-bold'"
                    >
                        <span>{{ t.testimonials.tag }}</span>
                    </div>
                    <h2
                        class="text-3xl sm:text-4xl font-extrabold tracking-tight"
                        :class="isDark ? 'text-white' : 'text-slate-900'"
                    >
                        {{ t.testimonials.title }}
                    </h2>
                    <p
                        class="text-base sm:text-lg"
                        :class="isDark ? 'text-slate-400' : 'text-slate-600'"
                    >
                        {{ t.testimonials.subtitle }}
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <!-- Review 1 -->
                    <div
                        class="p-8 rounded-3xl border space-y-6 flex flex-col justify-between transition-all"
                        :class="isDark ? 'bg-slate-900/60 border-white/5' : 'bg-white border-slate-200/90 shadow-sm hover:shadow-md'"
                    >
                        <p
                            class="text-sm leading-relaxed italic"
                            :class="isDark ? 'text-slate-300' : 'text-slate-700 font-medium'"
                        >
                            {{ t.testimonials.t1Text }}
                        </p>
                        <div
                            class="flex items-center gap-4 pt-4 border-t"
                            :class="isDark ? 'border-white/5' : 'border-slate-100'"
                        >
                            <div class="w-11 h-11 rounded-full bg-gradient-to-tr from-amber-500 to-orange-500 text-slate-950 font-bold flex items-center justify-center text-sm shadow-xs">
                                TS
                            </div>
                            <div>
                                <h5
                                    class="text-sm font-bold"
                                    :class="isDark ? 'text-white' : 'text-slate-900 font-bold'"
                                >{{ t.testimonials.t1Author }}</h5>
                                <p
                                    class="text-xs"
                                    :class="isDark ? 'text-amber-400/90' : 'text-amber-800 font-semibold'"
                                >{{ t.testimonials.t1Role }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Review 2 -->
                    <div
                        class="p-8 rounded-3xl border space-y-6 flex flex-col justify-between transition-all"
                        :class="isDark ? 'bg-slate-900/60 border-white/5' : 'bg-white border-slate-200/90 shadow-sm hover:shadow-md'"
                    >
                        <p
                            class="text-sm leading-relaxed italic"
                            :class="isDark ? 'text-slate-300' : 'text-slate-700 font-medium'"
                        >
                            {{ t.testimonials.t2Text }}
                        </p>
                        <div
                            class="flex items-center gap-4 pt-4 border-t"
                            :class="isDark ? 'border-white/5' : 'border-slate-100'"
                        >
                            <div class="w-11 h-11 rounded-full bg-gradient-to-tr from-orange-500 to-rose-500 text-white font-bold flex items-center justify-center text-sm shadow-xs">
                                SA
                            </div>
                            <div>
                                <h5
                                    class="text-sm font-bold"
                                    :class="isDark ? 'text-white' : 'text-slate-900 font-bold'"
                                >{{ t.testimonials.t2Author }}</h5>
                                <p
                                    class="text-xs"
                                    :class="isDark ? 'text-amber-400/90' : 'text-amber-800 font-semibold'"
                                >{{ t.testimonials.t2Role }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Review 3 -->
                    <div
                        class="p-8 rounded-3xl border space-y-6 flex flex-col justify-between transition-all"
                        :class="isDark ? 'bg-slate-900/60 border-white/5' : 'bg-white border-slate-200/90 shadow-sm hover:shadow-md'"
                    >
                        <p
                            class="text-sm leading-relaxed italic"
                            :class="isDark ? 'text-slate-300' : 'text-slate-700 font-medium'"
                        >
                            {{ t.testimonials.t3Text }}
                        </p>
                        <div
                            class="flex items-center gap-4 pt-4 border-t"
                            :class="isDark ? 'border-white/5' : 'border-slate-100'"
                        >
                            <div class="w-11 h-11 rounded-full bg-gradient-to-tr from-emerald-500 to-amber-500 text-slate-950 font-bold flex items-center justify-center text-sm shadow-xs">
                                FT
                            </div>
                            <div>
                                <h5
                                    class="text-sm font-bold"
                                    :class="isDark ? 'text-white' : 'text-slate-900 font-bold'"
                                >{{ t.testimonials.t3Author }}</h5>
                                <p
                                    class="text-xs"
                                    :class="isDark ? 'text-amber-400/90' : 'text-amber-800 font-semibold'"
                                >{{ t.testimonials.t3Role }}</p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- FAQS ACCORDION -->
        <!-- ========================================================================= -->
        <section
            id="faqs"
            class="py-20 relative z-10 border-t transition-colors duration-500"
            :class="isDark ? 'border-white/5 bg-[#080d19]/80' : 'border-slate-200/90 bg-slate-50/60'"
        >
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

                <div class="text-center mb-16 space-y-4">
                    <div
                        class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold border shadow-xs"
                        :class="isDark ? 'bg-amber-500/10 text-amber-400 border-amber-500/20' : 'bg-amber-50 text-amber-800 border-amber-200/90 font-bold'"
                    >
                        <span>{{ t.faqs.tag }}</span>
                    </div>
                    <h2
                        class="text-3xl sm:text-4xl font-extrabold tracking-tight"
                        :class="isDark ? 'text-white' : 'text-slate-900'"
                    >
                        {{ t.faqs.title }}
                    </h2>
                    <p
                        class="text-base"
                        :class="isDark ? 'text-slate-400' : 'text-slate-600'"
                    >
                        {{ t.faqs.subtitle }}
                    </p>
                </div>

                <div class="space-y-4">
                    <div
                        v-for="(faq, idx) in t.faqs.items"
                        :key="idx"
                        class="rounded-2xl border overflow-hidden transition-all"
                        :class="isDark ? 'border-white/5 bg-slate-900/70' : 'border-slate-200/90 bg-white shadow-xs'"
                    >
                        <button
                            @click="toggleFaq(idx)"
                            class="w-full p-5 text-right flex items-center justify-between gap-4 font-bold text-sm sm:text-base transition-colors cursor-pointer"
                            :class="[
                                currentLocale === 'en' ? 'text-left' : 'text-right',
                                isDark ? 'text-white hover:text-amber-400' : 'text-slate-900 hover:text-amber-700'
                            ]"
                        >
                            <span>{{ faq.q }}</span>
                            <span
                                class="text-xl font-mono shrink-0 font-bold"
                                :class="isDark ? 'text-amber-400' : 'text-amber-700'"
                            >
                                {{ openFaqIndex === idx ? '−' : '+' }}
                            </span>
                        </button>
                        <div
                            v-if="openFaqIndex === idx"
                            class="px-5 pb-5 text-xs sm:text-sm leading-relaxed border-t pt-4"
                            :class="isDark ? 'border-white/5 text-slate-300' : 'border-slate-100 text-slate-600 bg-slate-50/50'"
                        >
                            {{ faq.a }}
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- SOCIAL PROOF LIVE ORDER TOAST TICKER -->
        <!-- ========================================================================= -->
        <transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="transform translate-y-4 opacity-0"
            enter-to-class="transform translate-y-0 opacity-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="transform translate-y-0 opacity-100"
            leave-to-class="transform translate-y-4 opacity-0"
        >
            <div
                v-if="!isToastPermanentlyDismissed && showToast && liveToasts[currentToastIndex]"
                class="fixed bottom-6 z-40 max-w-sm w-[90%] sm:w-auto p-4 rounded-2xl border shadow-2xl backdrop-blur-2xl flex items-center gap-3.5 transition-all"
                :class="[
                    currentLocale === 'ar' ? 'right-6' : 'left-6',
                    isDark ? 'bg-[#090e1c]/95 border-amber-500/30 text-white' : 'bg-white/95 border-amber-300 text-slate-900 shadow-xl'
                ]"
            >
                <div
                    class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 text-lg font-bold shadow-xs"
                    :class="isDark ? 'bg-amber-500/20 text-amber-400' : 'bg-amber-100 text-amber-800'"
                >
                    🔔
                </div>
                <div class="flex-1 text-xs">
                    <div class="flex items-center justify-between gap-2 mb-0.5">
                        <span
                            class="font-extrabold"
                            :class="isDark ? 'text-amber-400' : 'text-amber-800'"
                        >{{ liveToasts[currentToastIndex].table }}</span>
                        <span
                            class="text-[10px] font-mono"
                            :class="isDark ? 'text-slate-400' : 'text-slate-500'"
                        >{{ liveToasts[currentToastIndex].time }}</span>
                    </div>
                    <p :class="isDark ? 'text-slate-200' : 'text-slate-700 font-medium'" class="line-clamp-2">
                        {{ liveToasts[currentToastIndex].text }}
                    </p>
                </div>
                <button
                    @click="dismissToast"
                    type="button"
                    class="p-1 rounded-lg transition-colors shrink-0 -mt-4 text-xs font-bold cursor-pointer"
                    :class="isDark ? 'text-slate-400 hover:text-white hover:bg-white/10' : 'text-slate-400 hover:text-slate-900 hover:bg-slate-100'"
                    :title="currentLocale === 'ar' ? 'إيقاف ظهور الإشعارات' : 'Dismiss notifications'"
                    aria-label="Dismiss notifications"
                >
                    ✕
                </button>
            </div>
        </transition>

        <!-- ========================================================================= -->
        <!-- DEMO REQUEST MODAL -->
        <!-- ========================================================================= -->
        <div
            v-if="isDemoModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-md"
            @click.self="isDemoModalOpen = false"
        >
            <div
                class="relative w-full max-w-lg rounded-3xl border p-6 sm:p-8 shadow-2xl space-y-6 transition-all"
                :class="isDark ? 'bg-slate-900 border-white/10' : 'bg-white border-slate-200/90 shadow-2xl'"
            >

                <button
                    @click="isDemoModalOpen = false"
                    class="absolute top-5 text-xl font-bold p-2 transition-colors cursor-pointer"
                    :class="[
                        currentLocale === 'ar' ? 'left-5' : 'right-5',
                        isDark ? 'text-slate-400 hover:text-white' : 'text-slate-400 hover:text-slate-900'
                    ]"
                >
                    ✕
                </button>

                <!-- Initial Form State -->
                <div v-if="!isSubmitted" class="space-y-4">
                    <div class="space-y-2">
                        <span
                            class="text-xs font-bold"
                            :class="isDark ? 'text-amber-400' : 'text-amber-700'"
                        >{{ t.modal.badge }}</span>
                        <h3
                            class="text-2xl font-black"
                            :class="isDark ? 'text-white' : 'text-slate-900'"
                        >{{ t.modal.title }}</h3>
                        <p
                            class="text-xs"
                            :class="isDark ? 'text-slate-400' : 'text-slate-600'"
                        >
                            {{ t.modal.subtitle }}
                        </p>
                    </div>

                    <form @submit.prevent="submitDemoRequest" class="space-y-3.5 pt-2">
                        <div>
                            <label
                                class="block text-xs font-semibold mb-1"
                                :class="isDark ? 'text-slate-300' : 'text-slate-700 font-bold'"
                            >{{ t.modal.restaurantName }}</label>
                            <input
                                v-model="demoForm.restaurantName"
                                required
                                type="text"
                                :placeholder="t.modal.restaurantNamePlaceholder"
                                class="w-full px-4 py-2.5 rounded-xl border text-sm focus:outline-none transition-colors"
                                :class="isDark ? 'bg-slate-900 border-slate-700 text-white focus:border-amber-500' : 'bg-slate-50 border-slate-300 text-slate-900 focus:bg-white focus:border-amber-500 shadow-xs'"
                            />
                        </div>

                        <div>
                            <label
                                class="block text-xs font-semibold mb-1"
                                :class="isDark ? 'text-slate-300' : 'text-slate-700 font-bold'"
                            >{{ t.modal.contactPerson }}</label>
                            <input
                                v-model="demoForm.contactPerson"
                                required
                                type="text"
                                :placeholder="t.modal.contactPersonPlaceholder"
                                class="w-full px-4 py-2.5 rounded-xl border text-sm focus:outline-none transition-colors"
                                :class="isDark ? 'bg-slate-900 border-slate-700 text-white focus:border-amber-500' : 'bg-slate-50 border-slate-300 text-slate-900 focus:bg-white focus:border-amber-500 shadow-xs'"
                            />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label
                                    class="block text-xs font-semibold mb-1"
                                    :class="isDark ? 'text-slate-300' : 'text-slate-700 font-bold'"
                                >{{ t.modal.phone }}</label>
                                <input
                                    v-model="demoForm.phone"
                                    required
                                    type="tel"
                                    :placeholder="t.modal.phonePlaceholder"
                                    class="w-full px-4 py-2.5 rounded-xl border text-sm focus:outline-none transition-colors"
                                    :class="isDark ? 'bg-slate-900 border-slate-700 text-white focus:border-amber-500' : 'bg-slate-50 border-slate-300 text-slate-900 focus:bg-white focus:border-amber-500 shadow-xs'"
                                />
                            </div>
                            <div>
                                <label
                                    class="block text-xs font-semibold mb-1"
                                    :class="isDark ? 'text-slate-300' : 'text-slate-700 font-bold'"
                                >{{ t.modal.country }}</label>
                                <select
                                    v-model="demoForm.country"
                                    class="w-full px-4 py-2.5 rounded-xl border text-sm focus:outline-none transition-colors"
                                    :class="isDark ? 'bg-slate-900 border-slate-700 text-white focus:border-amber-500' : 'bg-slate-50 border-slate-300 text-slate-900 focus:bg-white focus:border-amber-500 shadow-xs'"
                                >
                                    <option
                                        v-for="c in Object.values(supportedArabCurrencies)"
                                        :key="c.code"
                                        :value="c.countryName[currentLocale]"
                                    >
                                        {{ c.countryName[currentLocale] }} {{ c.flag }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label
                                class="block text-xs font-semibold mb-1"
                                :class="isDark ? 'text-slate-300' : 'text-slate-700 font-bold'"
                            >{{ t.modal.tablesCount }}</label>
                            <select
                                v-model="demoForm.tablesCount"
                                class="w-full px-4 py-2.5 rounded-xl border text-sm focus:outline-none transition-colors"
                                :class="isDark ? 'bg-slate-900 border-slate-700 text-white focus:border-amber-500' : 'bg-slate-50 border-slate-300 text-slate-900 focus:bg-white focus:border-amber-500 shadow-xs'"
                            >
                                <option value="1-15">{{ t.modal.tablesOption1 }}</option>
                                <option value="15-35">{{ t.modal.tablesOption2 }}</option>
                                <option value="35-70">{{ t.modal.tablesOption3 }}</option>
                                <option value="70+">{{ t.modal.tablesOption4 }}</option>
                            </select>
                        </div>

                        <button
                            type="submit"
                            class="w-full py-3.5 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 text-slate-950 font-extrabold text-sm shadow-xl shadow-amber-500/25 hover:shadow-amber-500/40 hover:scale-[1.01] transition-all mt-2 cursor-pointer"
                        >
                            {{ t.modal.submitBtn }}
                        </button>
                    </form>
                </div>

                <!-- Success Confirmation State -->
                <div v-else class="text-center py-8 space-y-4">
                    <div class="w-16 h-16 rounded-full bg-emerald-500/20 text-emerald-400 text-3xl flex items-center justify-center mx-auto animate-bounce">
                        ✓
                    </div>
                    <h3
                        class="text-2xl font-bold"
                        :class="isDark ? 'text-white' : 'text-slate-900'"
                    >{{ t.modal.successTitle }}</h3>
                    <p
                        class="text-sm max-w-sm mx-auto"
                        :class="isDark ? 'text-slate-300' : 'text-slate-600'"
                    >
                        {{ t.modal.successDesc }}
                    </p>
                </div>

            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- FOOTER -->
        <!-- ========================================================================= -->
        <footer
            class="border-t py-12 relative z-10 text-sm transition-colors duration-500"
            :class="isDark ? 'border-white/5 bg-[#05080f] text-slate-400' : 'border-slate-200/90 bg-slate-100 text-slate-600'"
        >
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-8 mb-10">

                    <!-- Col 1: Brand -->
                    <div class="space-y-4 md:col-span-2">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-amber-500 to-orange-500 p-[1.5px]">
                                <div
                                    class="w-full h-full rounded-xl flex items-center justify-center overflow-hidden"
                                    :class="isDark ? 'bg-[#0b101d]' : 'bg-white'"
                                >
                                    <img src="/images/tedallal-brand-logo.jpg" alt="Logo" class="w-full h-full object-cover" onerror="this.src='/images/logo.jpg'" />
                                </div>
                            </div>
                            <span
                                class="text-2xl font-extrabold"
                                :class="isDark ? 'text-white' : 'text-slate-900'"
                            >تدلّل • Tedallal</span>
                        </div>
                        <p
                            class="text-xs leading-relaxed max-w-md"
                            :class="isDark ? 'text-slate-400' : 'text-slate-600'"
                        >
                            {{ t.footer.brandDesc }}
                        </p>
                        <div
                            class="flex items-center gap-2 text-xs font-semibold"
                            :class="isDark ? 'text-emerald-400' : 'text-emerald-700'"
                        >
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            <span>{{ t.footer.serverStatus }}</span>
                        </div>
                    </div>

                    <!-- Col 2: Quick Links -->
                    <div class="space-y-3">
                        <h5
                            class="text-xs font-bold uppercase tracking-wider"
                            :class="isDark ? 'text-white' : 'text-slate-900 font-extrabold'"
                        >{{ t.footer.quickLinksTitle }}</h5>
                        <ul class="space-y-2 text-xs">
                            <li><a href="#how-it-works" class="hover:text-amber-500 transition-colors">{{ t.nav.howItWorks }}</a></li>
                            <li><a href="#solutions" class="hover:text-amber-500 transition-colors">{{ t.nav.solutions }}</a></li>
                            <li><a href="#interactive-demo" class="hover:text-amber-500 transition-colors">{{ t.nav.interactiveDemo }}</a></li>
                            <li><a href="#features" class="hover:text-amber-500 transition-colors">{{ t.nav.features }}</a></li>
                            <li><a href="#directory" class="hover:text-amber-500 transition-colors">{{ t.nav.directory }}</a></li>
                            <li><a href="#roi-calculator" class="hover:text-amber-500 transition-colors">{{ t.nav.roiCalculator }}</a></li>
                            <li><a href="#faqs" class="hover:text-amber-500 transition-colors">{{ t.nav.faqs }}</a></li>
                        </ul>
                    </div>

                    <!-- Col 3: Portal Access -->
                    <div class="space-y-3">
                        <h5
                            class="text-xs font-bold uppercase tracking-wider"
                            :class="isDark ? 'text-white' : 'text-slate-900 font-extrabold'"
                        >{{ t.footer.portalAccessTitle }}</h5>
                        <ul class="space-y-2 text-xs">
                            <li><Link :href="route('login')" class="hover:text-amber-500 transition-colors">{{ t.footer.adminPortal }}</Link></li>
                            <li><Link :href="route('login')" class="hover:text-amber-500 transition-colors">{{ t.footer.kdsPortal }}</Link></li>
                            <li><Link :href="route('login')" class="hover:text-amber-500 transition-colors">{{ t.footer.superAdminPortal }}</Link></li>
                            <li><button @click="isDemoModalOpen = true" class="text-amber-600 dark:text-amber-400 font-bold hover:underline cursor-pointer">{{ t.footer.requestAccount }}</button></li>
                        </ul>
                    </div>

                </div>

                <div
                    class="pt-8 border-t flex flex-col sm:flex-row items-center justify-between gap-4 text-xs"
                    :class="isDark ? 'border-white/5 text-slate-500' : 'border-slate-200 text-slate-500'"
                >
                    <div>
                        {{ t.footer.rights }} {{ new Date().getFullYear() }}
                    </div>
                    <div class="flex items-center gap-6">
                        <span class="hover:text-slate-700 dark:hover:text-slate-400 transition-colors">{{ currentLocale === 'ar' ? 'أعلى معايير حماية وأمان البيانات' : 'Enterprise Cloud Security' }}</span>
                        <span class="hover:text-slate-700 dark:hover:text-slate-400 transition-colors">{{ currentLocale === 'ar' ? 'دعم فني واستجابة فورية على مدار الساعة' : '24/7 Dedicated Support' }}</span>
                    </div>
                </div>

            </div>
        </footer>

    </div>
</template>

<style scoped>
.font-english {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(245, 158, 11, 0.2);
    border-radius: 4px;
}

/* Hero Mode Switch Transition (Zero Layout Shift Blur Fade) */
.hero-mode-enter-active,
.hero-mode-leave-active {
    transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
}
.hero-mode-enter-from {
    opacity: 0;
    transform: translateY(10px) scale(0.99);
    filter: blur(4px);
}
.hero-mode-leave-to {
    opacity: 0;
    transform: translateY(-10px) scale(0.99);
    filter: blur(4px);
}

/* =========================================================================
   LIGHT MODE DESIGN SYSTEM FOR TEDALLAL LANDING PAGE
   ========================================================================= */
.light-mode-theme {
    color-scheme: light;
}

.light-mode-theme,
.dark-mode-theme {
    transition: background-color 0.35s ease, color 0.35s ease, border-color 0.35s ease;
}

/* Light mode ambient background mesh & glowing warmth */
.light-mode-theme .ambient-glow-layer .bg-radial-mesh {
    background-image: radial-gradient(#cbd5e1 1.2px, transparent 1.2px) !important;
    opacity: 0.35 !important;
}

/* Header & Navigation in Light Mode */
.light-mode-theme header {
    background-color: rgba(255, 255, 255, 0.94) !important;
    border-bottom-color: rgba(226, 232, 240, 0.9) !important;
    box-shadow: 0 1px 3px 0 rgba(15, 23, 42, 0.04), 0 1px 2px -1px rgba(15, 23, 42, 0.03);
}

.light-mode-theme header nav a {
    color: #475569 !important;
}
.light-mode-theme header nav a:hover {
    color: #b45309 !important;
}

/* Main section alternating backgrounds in Light Mode */
.light-mode-theme section {
    border-top-color: #e2e8f0 !important;
}

.light-mode-theme section#how-it-works,
.light-mode-theme section#features,
.light-mode-theme section#roi-calculator {
    background-color: #ffffff !important;
}

.light-mode-theme section#solutions,
.light-mode-theme section#directory,
.light-mode-theme section#comparison,
.light-mode-theme section#faqs {
    background-color: #f8fafc !important;
}

/* Light mode headings & titles (High contrast #0f172a) */
.light-mode-theme h1:not([class*="text-transparent"]):not([class*="bg-clip-text"]),
.light-mode-theme h2:not([class*="text-transparent"]):not([class*="bg-clip-text"]),
.light-mode-theme h3:not([class*="text-transparent"]):not([class*="bg-clip-text"]),
.light-mode-theme h4:not([class*="text-transparent"]):not([class*="bg-clip-text"]),
.light-mode-theme h5:not([class*="text-transparent"]):not([class*="bg-clip-text"]) {
    color: #0f172a !important;
}

.light-mode-theme .text-slate-300 {
    color: #334155 !important;
}

.light-mode-theme .text-slate-400 {
    color: #64748b !important;
}

/* Light mode high-contrast color tokens for WCAG AA+ accessibility */
.light-mode-theme .text-amber-400 {
    color: #b45309 !important;
}
.light-mode-theme .text-amber-300 {
    color: #92400e !important;
}
.light-mode-theme .text-emerald-400 {
    color: #047857 !important;
}
.light-mode-theme .text-emerald-300 {
    color: #065f46 !important;
}
.light-mode-theme .text-orange-400 {
    color: #c2410c !important;
}
.light-mode-theme .text-orange-300 {
    color: #9a3412 !important;
}
.light-mode-theme .text-rose-400 {
    color: #be123c !important;
}
.light-mode-theme .text-rose-300 {
    color: #9f1239 !important;
}

/* Catch-all for dark backgrounds in light mode */
.light-mode-theme .bg-slate-900,
.light-mode-theme .bg-slate-900\/60,
.light-mode-theme .bg-slate-900\/70,
.light-mode-theme .bg-slate-900\/80,
.light-mode-theme .bg-slate-900\/90,
.light-mode-theme .bg-slate-900\/95,
.light-mode-theme .bg-slate-950,
.light-mode-theme .bg-slate-950\/70,
.light-mode-theme .bg-slate-950\/80,
.light-mode-theme .bg-slate-950\/90,
.light-mode-theme .bg-\[\#090e1c\],
.light-mode-theme .bg-\[\#090e1c\]\/95,
.light-mode-theme .bg-\[\#0b101d\],
.light-mode-theme .bg-\[\#080d19\]\/80,
.light-mode-theme .bg-\[\#080d19\]\/60,
.light-mode-theme .bg-\[\#070b14\],
.light-mode-theme .bg-\[\#060a13\] {
    background-color: #ffffff !important;
    border-color: #e2e8f0 !important;
    box-shadow: 0 1px 3px 0 rgba(15, 23, 42, 0.05), 0 1px 2px -1px rgba(15, 23, 42, 0.04);
}

/* Text white inside light cards */
.light-mode-theme .bg-slate-900 span.text-white,
.light-mode-theme .bg-slate-900 p.text-white,
.light-mode-theme .bg-slate-900 h3.text-white,
.light-mode-theme .bg-slate-900 h4.text-white,
.light-mode-theme .bg-slate-950 span.text-white,
.light-mode-theme .bg-slate-950 p.text-white,
.light-mode-theme .bg-slate-950 h3.text-white,
.light-mode-theme .bg-slate-950 h4.text-white,
.light-mode-theme #comparison .text-white,
.light-mode-theme #faqs .text-white {
    color: #0f172a !important;
}

/* Comparison table alternate rows in light mode */
.light-mode-theme #comparison tr:nth-child(even) {
    background-color: #f8fafc !important;
}
.light-mode-theme #comparison tr:nth-child(odd) {
    background-color: #ffffff !important;
}
.light-mode-theme #comparison td,
.light-mode-theme #comparison th {
    border-color: #e2e8f0 !important;
}

/* Light mode FAQ items */
.light-mode-theme #faqs .rounded-2xl {
    background-color: #ffffff !important;
    border-color: #e2e8f0 !important;
    box-shadow: 0 1px 2px 0 rgba(15, 23, 42, 0.04);
}
.light-mode-theme #faqs button span:first-child {
    color: #0f172a !important;
}

/* Light mode form inputs & selects */
.light-mode-theme input,
.light-mode-theme select {
    background-color: #f8fafc !important;
    border-color: #cbd5e1 !important;
    color: #0f172a !important;
}
.light-mode-theme input:focus,
.light-mode-theme select:focus {
    border-color: #f59e0b !important;
    background-color: #ffffff !important;
    box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.15) !important;
}

/* Light mode mobile drawer */
.light-mode-theme .lg\:hidden.bg-\[\#090e1c\]\/95 {
    background-color: rgba(255, 255, 255, 0.98) !important;
    border-top-color: #e2e8f0 !important;
}
.light-mode-theme .lg\:hidden.bg-\[\#090e1c\]\/95 a.text-slate-200 {
    color: #1e293b !important;
}

/* Light mode floating toast */
.light-mode-theme .fixed.bottom-6.bg-\[\#090e1c\]\/95 {
    background-color: rgba(255, 255, 255, 0.98) !important;
    border-color: rgba(245, 158, 11, 0.4) !important;
    box-shadow: 0 20px 25px -5px rgba(15, 23, 42, 0.1), 0 8px 10px -6px rgba(15, 23, 42, 0.08);
}
.light-mode-theme .fixed.bottom-6 p.text-slate-200 {
    color: #334155 !important;
}

/* Light mode modal card */
.light-mode-theme .bg-slate-900.border-white\/10 {
    background-color: #ffffff !important;
    border-color: #e2e8f0 !important;
    box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.25);
}

/* Light mode footer */
.light-mode-theme footer {
    background-color: #f1f5f9 !important;
    border-top-color: #e2e8f0 !important;
    color: #64748b !important;
}
.light-mode-theme footer h5 {
    color: #0f172a !important;
}
.light-mode-theme footer a {
    color: #64748b !important;
}
.light-mode-theme footer a:hover {
    color: #b45309 !important;
}
.light-mode-theme footer span.text-white {
    color: #0f172a !important;
}
</style>
