<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { translations, type Locale } from '@/locales/landing';

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
    const numLocale = currentLocale.value === 'ar' ? 'ar-SA' : 'en-US';
    if (dec === 0) {
        return Math.round(val).toLocaleString(numLocale);
    }
    return val.toLocaleString(numLocale, { minimumFractionDigits: dec, maximumFractionDigits: dec });
}

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
let toastInterval: ReturnType<typeof setInterval> | null = null;

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

    // Live tickers
    toastInterval = setInterval(() => {
        showToast.value = false;
        setTimeout(() => {
            currentToastIndex.value = (currentToastIndex.value + 1) % liveToasts.value.length;
            showToast.value = true;
        }, 400);
    }, 7000);

    trafficInterval = setInterval(() => {
        dynamicTrafficTick.value++;
    }, 2800);
});

onUnmounted(() => {
    window.removeEventListener('click', handleGlobalClick);
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
        :class="currentLocale === 'ar' ? 'font-sans' : 'font-sans font-english'"
        class="min-h-screen bg-[#070b14] text-slate-100 selection:bg-amber-500 selection:text-slate-950 antialiased overflow-x-hidden relative"
    >
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
                    <nav class="hidden lg:flex items-center gap-7 text-sm font-medium text-slate-300">
                        <a href="#how-it-works" class="hover:text-amber-400 transition-colors">{{ t.nav.howItWorks }}</a>
                        <a href="#interactive-demo" class="hover:text-amber-400 transition-colors flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-amber-400 animate-ping"></span>
                            {{ t.nav.interactiveDemo }}
                        </a>
                        <a href="#features" class="hover:text-amber-400 transition-colors">{{ t.nav.features }}</a>
                        <a href="#roi-calculator" class="hover:text-amber-400 transition-colors">{{ t.nav.roiCalculator }}</a>
                        <a href="#comparison" class="hover:text-amber-400 transition-colors">{{ t.nav.comparison }}</a>
                        <a href="#faqs" class="hover:text-amber-400 transition-colors">{{ t.nav.faqs }}</a>
                    </nav>

                    <!-- Language & Currency Controls + Auth CTAs -->
                    <div class="hidden sm:flex items-center gap-3">
                        <!-- Language Switcher Toggle -->
                        <button
                            @click="toggleLocale"
                            type="button"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-900/90 hover:bg-slate-800 border border-slate-700/80 hover:border-amber-500/50 text-slate-200 text-xs font-semibold transition-all shadow-sm"
                            :title="currentLocale === 'ar' ? 'Switch to English' : 'التحويل إلى العربية'"
                        >
                            <span class="text-sm">🌐</span>
                            <span class="font-bold text-amber-400">{{ currentLocale === 'ar' ? 'English' : 'العربية' }}</span>
                        </button>

                        <!-- Arab Currency Switcher Dropdown -->
                        <div class="relative" data-currency-dropdown>
                            <button
                                @click="isCurrencyMenuOpen = !isCurrencyMenuOpen"
                                type="button"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-900/90 hover:bg-slate-800 border border-slate-700/80 hover:border-amber-500/50 text-slate-200 text-xs font-semibold transition-all shadow-sm"
                                :title="t.nav.currencyTitle"
                            >
                                <span>{{ currentCurrency.flag }}</span>
                                <span class="font-bold text-amber-400 font-mono">{{ currentCurrencySymbol }}</span>
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
                                    class="absolute mt-2 w-56 rounded-2xl bg-slate-900/95 border border-slate-700 shadow-2xl backdrop-blur-xl p-2 z-50 space-y-1 text-xs"
                                    :class="currentLocale === 'ar' ? 'left-0' : 'right-0'"
                                >
                                    <div class="px-2.5 py-1 text-[10px] text-slate-400 font-bold border-b border-white/5 flex items-center justify-between">
                                        <span>{{ t.nav.currencyTitle }}</span>
                                        <span class="text-emerald-400 text-[9px]">{{ t.nav.autoDetect }}</span>
                                    </div>
                                    <div class="max-h-60 overflow-y-auto space-y-0.5 custom-scrollbar">
                                        <button
                                            v-for="c in Object.values(supportedArabCurrencies)"
                                            :key="c.code"
                                            @click="setCurrency(c.code)"
                                            type="button"
                                            class="w-full flex items-center justify-between px-2.5 py-2 rounded-xl transition-colors"
                                            :class="[
                                                currentLocale === 'ar' ? 'text-right' : 'text-left',
                                                currentCountryCode === c.code ? 'bg-amber-500/20 text-amber-300 font-bold border border-amber-500/30' : 'text-slate-300 hover:bg-white/5'
                                            ]"
                                        >
                                            <div class="flex items-center gap-2">
                                                <span class="text-base">{{ c.flag }}</span>
                                                <span class="truncate">{{ c.countryName[currentLocale] }}</span>
                                            </div>
                                            <span class="font-mono text-amber-400 text-xs shrink-0 font-bold">{{ c.symbol[currentLocale] }}</span>
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
                                class="text-xs font-semibold text-slate-300 hover:text-white px-3 py-2 rounded-xl hover:bg-white/5 transition-all"
                            >
                                {{ t.nav.signIn }}
                            </Link>
                            <button
                                @click="isDemoModalOpen = true"
                                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 text-slate-950 font-bold text-xs shadow-lg shadow-amber-500/20 hover:shadow-amber-500/35 hover:scale-[1.02] active:scale-[0.98] transition-all"
                            >
                                <span>{{ t.nav.bookDemo }}</span>
                                <svg class="w-3.5 h-3.5 rtl:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                                </svg>
                            </button>
                        </template>
                    </div>

                    <!-- Mobile Menu Hamburger & Quick Action -->
                    <div class="flex lg:hidden items-center gap-2">
                        <!-- Mobile Lang Switcher -->
                        <button
                            @click="toggleLocale"
                            type="button"
                            class="px-2.5 py-1.5 rounded-lg bg-slate-900 border border-slate-700 text-xs font-bold text-amber-400"
                        >
                            {{ currentLocale === 'ar' ? 'EN' : 'عربي' }}
                        </button>

                        <button
                            @click="isDemoModalOpen = true"
                            class="px-3 py-1.5 rounded-lg bg-amber-500 text-slate-950 font-bold text-xs"
                        >
                            {{ currentLocale === 'ar' ? 'عرض حي' : 'Demo' }}
                        </button>

                        <button
                            @click="isMobileMenuOpen = !isMobileMenuOpen"
                            class="p-2 rounded-xl text-slate-400 hover:text-white hover:bg-white/5 transition-all"
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
            <div v-if="isMobileMenuOpen" class="lg:hidden border-t border-white/5 bg-[#090e1c]/95 backdrop-blur-2xl px-6 py-6 space-y-4">
                <!-- Country & Currency selector row for mobile -->
                <div class="flex items-center justify-between pb-3 border-b border-white/10 text-xs">
                    <span class="text-slate-400">{{ t.nav.currencyTitle }}:</span>
                    <div class="flex items-center gap-2">
                        <select
                            v-model="currentCountryCode"
                            @change="setCurrency(currentCountryCode)"
                            class="bg-slate-800 border border-slate-700 text-white rounded-lg px-2.5 py-1 text-xs focus:outline-none focus:border-amber-500"
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

                <a @click="isMobileMenuOpen = false" href="#how-it-works" class="block text-slate-200 font-medium py-1.5">{{ t.nav.howItWorks }}</a>
                <a @click="isMobileMenuOpen = false" href="#interactive-demo" class="block text-amber-400 font-medium py-1.5">{{ t.nav.interactiveDemo }}</a>
                <a @click="isMobileMenuOpen = false" href="#features" class="block text-slate-200 font-medium py-1.5">{{ t.nav.features }}</a>
                <a @click="isMobileMenuOpen = false" href="#roi-calculator" class="block text-slate-200 font-medium py-1.5">{{ t.nav.roiCalculator }}</a>
                <a @click="isMobileMenuOpen = false" href="#comparison" class="block text-slate-200 font-medium py-1.5">{{ t.nav.comparison }}</a>
                <a @click="isMobileMenuOpen = false" href="#faqs" class="block text-slate-200 font-medium py-1.5">{{ t.nav.faqs }}</a>

                <div class="pt-4 border-t border-white/10 flex flex-col gap-3">
                    <template v-if="$page.props.auth?.user">
                        <Link :href="route('dashboard')" class="text-center w-full py-3 rounded-xl bg-amber-500 text-slate-950 font-bold text-sm">
                            {{ t.nav.dashboard }}
                        </Link>
                    </template>
                    <template v-else>
                        <Link :href="route('login')" class="text-center w-full py-2.5 rounded-xl border border-white/10 text-white font-medium text-sm">
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
        <!-- HERO SECTION -->
        <!-- ========================================================================= -->
        <section class="relative pt-12 pb-24 lg:pt-20 lg:pb-32 overflow-hidden z-10">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center max-w-4xl mx-auto space-y-8">

                    <!-- Live Status Pill -->
                    <div class="inline-flex items-center gap-2.5 px-4 py-2 rounded-full bg-slate-900/80 border border-amber-500/30 text-amber-300 text-xs sm:text-sm font-semibold shadow-lg shadow-amber-500/5 backdrop-blur-md">
                        <span class="relative flex h-2.5 w-2.5">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                        </span>
                        <span>{{ t.hero.statusBadge }}</span>
                        <span class="text-slate-500">|</span>
                        <span class="text-amber-400/90 font-mono">{{ t.hero.version }}</span>
                    </div>

                    <!-- Main Dynamic Headline -->
                    <h1 class="text-4xl sm:text-6xl lg:text-7xl font-extrabold tracking-tight leading-[1.18] sm:leading-[1.18]">
                        {{ t.hero.titleLine1 }}
                        <span class="bg-gradient-to-r from-amber-400 via-orange-400 to-amber-300 bg-clip-text text-transparent block mt-2">
                            {{ t.hero.titleLine2 }}
                        </span>
                    </h1>

                    <!-- Hero Subtitle -->
                    <p class="text-lg sm:text-xl text-slate-300 max-w-3xl mx-auto font-normal leading-relaxed">
                        {{ t.hero.subtitle }}
                    </p>

                    <!-- CTAs -->
                    <div class="flex flex-col sm:flex-row items-center justify-center gap-4 pt-4">
                        <a
                            href="#interactive-demo"
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-3 px-8 py-4 rounded-2xl bg-gradient-to-r from-amber-500 via-amber-400 to-orange-500 text-slate-950 font-extrabold text-base shadow-xl shadow-amber-500/25 hover:shadow-amber-500/40 hover:scale-[1.02] active:scale-[0.98] transition-all"
                        >
                            <span>{{ t.hero.tryDemoBtn }}</span>
                        </a>

                        <button
                            @click="isDemoModalOpen = true"
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-8 py-4 rounded-2xl bg-slate-900/90 hover:bg-slate-800/90 border border-slate-700 hover:border-amber-500/50 text-white font-bold text-base transition-all backdrop-blur-md shadow-lg"
                        >
                            <span>{{ t.hero.bookConsultationBtn }}</span>
                        </button>
                    </div>

                    <!-- Micro Trust Badges -->
                    <div class="pt-8 flex flex-wrap items-center justify-center gap-6 sm:gap-10 text-xs sm:text-sm text-slate-400">
                        <div class="flex items-center gap-2">
                            <span class="text-emerald-400">✓</span>
                            <span>{{ t.hero.badgeNoApp }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-emerald-400">✓</span>
                            <span>{{ t.hero.badgeSockets }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-emerald-400">✓</span>
                            <span>{{ t.hero.badgeMultiTenant }}</span>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- ARCHITECTURE WORKFLOW: HOW IT WORKS -->
        <!-- ========================================================================= -->
        <section id="how-it-works" class="py-20 relative z-10 border-t border-white/5 bg-[#080d19]/60">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                <div class="text-center max-w-3xl mx-auto mb-16 space-y-4">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-500/10 text-amber-400 text-xs font-bold border border-amber-500/20">
                        <span>{{ t.workflow.tag }}</span>
                    </div>
                    <h2 class="text-3xl sm:text-4xl font-extrabold text-white">
                        {{ t.workflow.title }}
                    </h2>
                    <p class="text-slate-400 text-base sm:text-lg">
                        {{ t.workflow.subtitle }}
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">

                    <!-- Step 1 -->
                    <div class="relative group p-6 rounded-3xl bg-slate-900/60 border border-white/5 hover:border-amber-500/40 transition-all duration-300 hover:-translate-y-1">
                        <div class="w-12 h-12 rounded-2xl bg-amber-500/10 text-amber-400 border border-amber-500/20 flex items-center justify-center text-xl font-black mb-5">
                            01
                        </div>
                        <h3 class="text-lg font-bold text-white mb-2">{{ t.workflow.step1Title }}</h3>
                        <p class="text-xs text-slate-400 leading-relaxed">{{ t.workflow.step1Desc }}</p>
                    </div>

                    <!-- Step 2 -->
                    <div class="relative group p-6 rounded-3xl bg-slate-900/60 border border-white/5 hover:border-amber-500/40 transition-all duration-300 hover:-translate-y-1">
                        <div class="w-12 h-12 rounded-2xl bg-orange-500/10 text-orange-400 border border-orange-500/20 flex items-center justify-center text-xl font-black mb-5">
                            02
                        </div>
                        <h3 class="text-lg font-bold text-white mb-2">{{ t.workflow.step2Title }}</h3>
                        <p class="text-xs text-slate-400 leading-relaxed">{{ t.workflow.step2Desc }}</p>
                    </div>

                    <!-- Step 3 -->
                    <div class="relative group p-6 rounded-3xl bg-slate-900/60 border border-white/5 hover:border-amber-500/40 transition-all duration-300 hover:-translate-y-1">
                        <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 flex items-center justify-center text-xl font-black mb-5">
                            03
                        </div>
                        <h3 class="text-lg font-bold text-white mb-2">{{ t.workflow.step3Title }}</h3>
                        <p class="text-xs text-slate-400 leading-relaxed">{{ t.workflow.step3Desc }}</p>
                    </div>

                    <!-- Step 4 -->
                    <div class="relative group p-6 rounded-3xl bg-slate-900/60 border border-white/5 hover:border-amber-500/40 transition-all duration-300 hover:-translate-y-1">
                        <div class="w-12 h-12 rounded-2xl bg-amber-400/10 text-amber-300 border border-amber-400/20 flex items-center justify-center text-xl font-black mb-5">
                            04
                        </div>
                        <h3 class="text-lg font-bold text-white mb-2">{{ t.workflow.step4Title }}</h3>
                        <p class="text-xs text-slate-400 leading-relaxed">{{ t.workflow.step4Desc }}</p>
                    </div>

                </div>

            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- INTERACTIVE LIVE SHOWCASE (4 MODULES) -->
        <!-- ========================================================================= -->
        <section id="interactive-demo" class="py-24 relative z-10">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                <div class="text-center max-w-3xl mx-auto mb-12 space-y-4">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-gradient-to-r from-amber-500/20 to-orange-500/20 text-amber-300 text-xs font-bold border border-amber-500/30">
                        <span>{{ t.demo.tag }}</span>
                    </div>
                    <h2 class="text-3xl sm:text-4xl font-extrabold text-white">
                        {{ t.demo.title }}
                    </h2>
                    <p class="text-slate-400 text-base sm:text-lg">
                        {{ t.demo.subtitle }}
                    </p>
                </div>

                <!-- Showcase Module Switcher Tabs -->
                <div class="flex flex-wrap items-center justify-center gap-2 sm:gap-3 mb-10">
                    <button
                        @click="activeDemoTab = 'customer'"
                        class="px-5 py-3 rounded-2xl font-bold text-xs sm:text-sm transition-all flex items-center gap-2"
                        :class="activeDemoTab === 'customer' ? 'bg-gradient-to-r from-amber-500 to-orange-500 text-slate-950 shadow-lg shadow-amber-500/25' : 'bg-slate-900/80 text-slate-300 hover:bg-slate-800 border border-white/5'"
                    >
                        <span>📱</span>
                        <span>{{ t.demo.tabCustomer }}</span>
                    </button>

                    <button
                        @click="activeDemoTab = 'kds'"
                        class="px-5 py-3 rounded-2xl font-bold text-xs sm:text-sm transition-all flex items-center gap-2"
                        :class="activeDemoTab === 'kds' ? 'bg-gradient-to-r from-amber-500 to-orange-500 text-slate-950 shadow-lg shadow-amber-500/25' : 'bg-slate-900/80 text-slate-300 hover:bg-slate-800 border border-white/5'"
                    >
                        <span>👨‍🍳</span>
                        <span>{{ t.demo.tabKds }}</span>
                    </button>

                    <button
                        @click="activeDemoTab = 'qr'"
                        class="px-5 py-3 rounded-2xl font-bold text-xs sm:text-sm transition-all flex items-center gap-2"
                        :class="activeDemoTab === 'qr' ? 'bg-gradient-to-r from-amber-500 to-orange-500 text-slate-950 shadow-lg shadow-amber-500/25' : 'bg-slate-900/80 text-slate-300 hover:bg-slate-800 border border-white/5'"
                    >
                        <span>🧾</span>
                        <span>{{ t.demo.tabQr }}</span>
                    </button>

                    <button
                        @click="activeDemoTab = 'analytics'"
                        class="px-5 py-3 rounded-2xl font-bold text-xs sm:text-sm transition-all flex items-center gap-2"
                        :class="activeDemoTab === 'analytics' ? 'bg-gradient-to-r from-amber-500 to-orange-500 text-slate-950 shadow-lg shadow-amber-500/25' : 'bg-slate-900/80 text-slate-300 hover:bg-slate-800 border border-white/5'"
                    >
                        <span>📊</span>
                        <span>{{ t.demo.tabAnalytics }}</span>
                    </button>
                </div>

                <!-- Showcase Stage -->
                <div class="relative rounded-3xl bg-slate-950/80 border border-white/10 shadow-2xl backdrop-blur-2xl p-4 sm:p-8 overflow-hidden min-h-[580px]">

                    <!-- TAB 1: CUSTOMER MOBILE MENU SIMULATOR -->
                    <div v-if="activeDemoTab === 'customer'" class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
                        <div class="lg:col-span-5 space-y-5">
                            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-500/10 text-amber-400 text-xs font-bold border border-amber-500/20">
                                <span>{{ t.demo.tabCustomer }}</span>
                            </div>
                            <h3 class="text-2xl sm:text-3xl font-extrabold text-white">
                                {{ currentLocale === 'ar' ? 'منيو رقمي فوري على الطاولة' : 'Instant Tabletop Digital Menu' }}
                            </h3>
                            <p class="text-slate-300 text-sm leading-relaxed">
                                {{ currentLocale === 'ar'
                                    ? 'بدون الحاجة لتحميل تطبيقات، يتصفح العميل قائمة الطعام بصور احترافية عالية الدقة، مع خيارات الإضافة، التخصيص، وسلة طاولة تفاعلية تتيح له إرسال الأوردر فوراً إلى المطبخ.'
                                    : 'With zero app friction, guests browse appetizing menu items with high-resolution photography, customizations, dietary tags, and a floating interactive cart that routes tickets straight to the kitchen.'
                                }}
                            </p>

                            <!-- Category Switcher in simulation -->
                            <div class="flex flex-wrap gap-2 pt-2">
                                <button
                                    @click="activeCategory = 'grills'"
                                    class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all"
                                    :class="activeCategory === 'grills' ? 'bg-amber-500 text-slate-950' : 'bg-slate-900 text-slate-300 hover:bg-slate-800'"
                                >
                                    {{ t.demo.catGrills }}
                                </button>
                                <button
                                    @click="activeCategory = 'appetizers'"
                                    class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all"
                                    :class="activeCategory === 'appetizers' ? 'bg-amber-500 text-slate-950' : 'bg-slate-900 text-slate-300 hover:bg-slate-800'"
                                >
                                    {{ t.demo.catAppetizers }}
                                </button>
                                <button
                                    @click="activeCategory = 'desserts'"
                                    class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all"
                                    :class="activeCategory === 'desserts' ? 'bg-amber-500 text-slate-950' : 'bg-slate-900 text-slate-300 hover:bg-slate-800'"
                                >
                                    {{ t.demo.catDesserts }}
                                </button>
                                <button
                                    @click="activeCategory = 'drinks'"
                                    class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all"
                                    :class="activeCategory === 'drinks' ? 'bg-amber-500 text-slate-950' : 'bg-slate-900 text-slate-300 hover:bg-slate-800'"
                                >
                                    {{ t.demo.catDrinks }}
                                </button>
                            </div>
                        </div>

                        <!-- Phone Mockup Container -->
                        <div class="lg:col-span-7 flex justify-center">
                            <div class="w-full max-w-[340px] sm:max-w-[360px] h-[550px] bg-slate-900 border-[6px] border-slate-800 rounded-[44px] shadow-2xl overflow-hidden relative flex flex-col">
                                <!-- Phone Notch -->
                                <div class="h-6 bg-slate-950 flex items-center justify-between px-6 text-[10px] text-slate-400 font-mono shrink-0">
                                    <span>9:41</span>
                                    <div class="w-16 h-3.5 bg-black rounded-full mx-auto"></div>
                                    <span>5G</span>
                                </div>

                                <!-- Mobile App Header -->
                                <div class="px-4 py-3 bg-slate-900/90 border-b border-white/5 flex items-center justify-between shrink-0">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-lg bg-amber-500/20 text-amber-400 flex items-center justify-center font-bold text-xs">
                                            04
                                        </div>
                                        <div>
                                            <div class="text-xs font-bold text-white">{{ currentLocale === 'ar' ? 'طاولة رقم 04' : 'Table 04' }}</div>
                                            <div class="text-[10px] text-emerald-400">{{ currentLocale === 'ar' ? 'متصل بالخدمة الحية ⚡' : 'Connected to Live Service ⚡' }}</div>
                                        </div>
                                    </div>
                                    <span class="text-xs font-bold text-amber-400">{{ currentCurrencySymbol }}</span>
                                </div>

                                <!-- Scrollable Menu Items -->
                                <div class="p-3 space-y-2.5 overflow-y-auto flex-1 custom-scrollbar pb-24">
                                    <div
                                        v-for="item in filteredMenuItems"
                                        :key="item.id"
                                        class="p-2.5 rounded-2xl bg-slate-950/90 border border-white/5 flex items-center gap-3 hover:border-amber-500/30 transition-all"
                                    >
                                        <img :src="item.image" :alt="item.name" class="w-14 h-14 rounded-xl object-cover shrink-0" onerror="this.src='/images/logo.jpg'" />
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-1.5">
                                                <h5 class="text-xs font-bold text-white truncate">{{ item.name }}</h5>
                                                <span v-if="item.badge" class="text-[9px] px-1.5 py-0.2 rounded bg-amber-500/20 text-amber-300 shrink-0">
                                                    {{ item.badge }}
                                                </span>
                                            </div>
                                            <p class="text-[10px] text-slate-400 line-clamp-1 mt-0.5">{{ item.description }}</p>
                                            <div class="text-xs font-bold text-amber-400 mt-1 font-mono">
                                                {{ formatPrice(item.price) }} {{ currentCurrencySymbol }}
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

                                <!-- Floating Glassmorphism Cart Bar -->
                                <div
                                    v-if="totalCartCount > 0"
                                    class="absolute bottom-2.5 right-2.5 left-2.5 p-3 rounded-2xl bg-[#090e1c]/95 border border-amber-500/40 shadow-2xl backdrop-blur-xl flex items-center justify-between z-20"
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
                                            <div class="text-[10px] text-slate-400">{{ t.demo.cartTitle }}</div>
                                            <div class="text-xs font-extrabold text-amber-400 font-mono">{{ formatPrice(totalCartPrice) }} {{ currentCurrencySymbol }}</div>
                                        </div>
                                    </div>

                                    <button
                                        @click="activeDemoTab = 'kds'"
                                        class="px-3.5 py-1.5 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 text-slate-950 text-xs font-bold hover:scale-105 active:scale-95 transition-all shadow"
                                    >
                                        {{ t.demo.sendToKitchenBtn }}
                                    </button>
                                </div>

                            </div>
                        </div>
                    </div>

                    <!-- TAB 2: KITCHEN DISPLAY SYSTEM (KDS) -->
                    <div v-else-if="activeDemoTab === 'kds'" class="space-y-6">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-white/5 pb-4">
                            <div>
                                <h4 class="text-lg font-bold text-white flex items-center gap-2">
                                    <span>👨‍🍳</span>
                                    <span>{{ t.demo.kdsHeaderTitle }}</span>
                                </h4>
                                <p class="text-xs text-slate-400 mt-1">{{ t.demo.kdsHeaderSubtitle }}</p>
                            </div>
                            <div class="flex items-center gap-2 text-xs">
                                <span class="px-3 py-1 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-bold flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                                    Reverb WebSockets Online
                                </span>
                            </div>
                        </div>

                        <!-- Kanban Columns -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <!-- New Orders -->
                            <div class="rounded-2xl bg-slate-900/70 border border-amber-500/20 p-3 space-y-3">
                                <div class="flex items-center justify-between border-b border-white/5 pb-2">
                                    <span class="text-xs font-bold text-amber-400 flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                                        {{ t.demo.colNew }}
                                    </span>
                                    <span class="text-[10px] px-1.5 py-0.5 rounded bg-amber-500/20 text-amber-300 font-mono">1</span>
                                </div>
                                <div
                                    v-for="order in kdsOrders.filter(o => o.status === 'new')"
                                    :key="order.id"
                                    class="p-3 rounded-xl bg-slate-950 border border-amber-500/40 shadow-lg space-y-2.5"
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
                                                {{ it.note }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="pt-2 flex items-center justify-between border-t border-white/5">
                                        <span class="text-xs font-mono font-bold text-slate-300">{{ formatPrice(order.total) }} {{ currentCurrencySymbol }}</span>
                                        <button
                                            @click="advanceKdsOrder(order.id)"
                                            class="px-3 py-1 rounded-lg bg-amber-500 text-slate-950 font-bold text-xs hover:bg-amber-400 transition-all"
                                        >
                                            {{ t.demo.btnStartCooking }}
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Cooking -->
                            <div class="rounded-2xl bg-slate-900/70 border border-orange-500/20 p-3 space-y-3">
                                <div class="flex items-center justify-between border-b border-white/5 pb-2">
                                    <span class="text-xs font-bold text-orange-400 flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full bg-orange-400"></span>
                                        {{ t.demo.colCooking }}
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
                                        <span class="text-xs font-mono font-bold text-slate-300">{{ formatPrice(order.total) }} {{ currentCurrencySymbol }}</span>
                                        <button
                                            @click="advanceKdsOrder(order.id)"
                                            class="px-3 py-1 rounded-lg bg-orange-500 text-slate-950 font-bold text-xs hover:bg-orange-400 transition-all"
                                        >
                                            {{ t.demo.btnMarkReady }}
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Ready -->
                            <div class="rounded-2xl bg-slate-900/70 border border-emerald-500/20 p-3 space-y-3">
                                <div class="flex items-center justify-between border-b border-white/5 pb-2">
                                    <span class="text-xs font-bold text-emerald-400 flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                                        {{ t.demo.colReady }}
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
                                        <span class="text-xs font-mono font-bold text-slate-300">{{ formatPrice(order.total) }} {{ currentCurrencySymbol }}</span>
                                        <button
                                            @click="advanceKdsOrder(order.id)"
                                            class="px-3 py-1 rounded-lg bg-emerald-500 text-slate-950 font-bold text-xs hover:bg-emerald-400 transition-all"
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
                            <div class="w-64 p-6 rounded-3xl bg-gradient-to-b from-slate-800 to-slate-900 border-2 border-amber-500/40 shadow-2xl relative">
                                <div class="text-center space-y-2">
                                    <span class="text-amber-400 font-bold text-sm tracking-widest block uppercase">TEDALLAL</span>
                                    <h5 class="text-base font-extrabold text-white">{{ t.demo.qrCardTitle }}</h5>
                                    <div class="w-40 h-40 bg-white rounded-2xl p-2 mx-auto shadow-inner flex items-center justify-center">
                                        <img src="/images/qr-sample.png" alt="QR" class="w-full h-full object-contain" />
                                    </div>
                                    <div class="text-xs text-amber-300 font-bold pt-1">{{ currentLocale === 'ar' ? 'طاولة رقم 04' : 'Table #04' }}</div>
                                    <p class="text-[10px] text-slate-400 leading-tight">{{ t.demo.qrCardHint }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Right: Thermal Receipt -->
                        <div class="flex flex-col items-center space-y-4">
                            <div class="w-72 bg-white text-slate-950 p-6 rounded-xl shadow-2xl font-mono text-xs space-y-3 border-t-8 border-amber-500">
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
                            <p class="text-xs text-slate-400 text-center max-w-sm mx-auto">
                                {{ t.demo.receiptHint }}
                            </p>
                        </div>
                    </div>

                    <!-- TAB 4: SMART ANALYTICS & BI -->
                    <div v-else-if="activeDemoTab === 'analytics'" class="space-y-6">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-white/5 pb-4">
                            <div>
                                <h4 class="text-lg font-bold text-white flex items-center gap-2">
                                    <span>📊</span>
                                    <span>{{ t.demo.analyticsTitle }}</span>
                                </h4>
                                <p class="text-xs text-slate-400 mt-1">{{ t.demo.analyticsSubtitle }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <button
                                    @click="selectedAnalyticsFilter = 'weekend'"
                                    class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all"
                                    :class="selectedAnalyticsFilter === 'weekend' ? 'bg-amber-500 text-slate-950' : 'bg-slate-900 text-slate-400 border border-white/5'"
                                >
                                    {{ t.demo.filterWeekend }}
                                </button>
                                <button
                                    @click="selectedAnalyticsFilter = 'weekday'"
                                    class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all"
                                    :class="selectedAnalyticsFilter === 'weekday' ? 'bg-amber-500 text-slate-950' : 'bg-slate-900 text-slate-400 border border-white/5'"
                                >
                                    {{ t.demo.filterWeekday }}
                                </button>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                            <!-- Peak Hours Chart -->
                            <div class="lg:col-span-7 p-5 rounded-2xl bg-slate-900/60 border border-white/5 space-y-4">
                                <h5 class="text-sm font-bold text-white flex items-center justify-between">
                                    <span>{{ t.demo.chartPeakTitle }}</span>
                                    <span class="text-xs text-amber-400 font-mono">{{ t.demo.ordersUnit }} / hr</span>
                                </h5>

                                <div class="h-44 pt-4 flex items-end justify-between gap-2 border-b border-white/10 pb-2">
                                    <div
                                        v-for="(bar, idx) in peakHoursData"
                                        :key="idx"
                                        class="flex-1 flex flex-col items-center gap-1 group relative cursor-pointer"
                                    >
                                        <div class="w-full h-36 flex items-end justify-center">
                                            <div
                                                class="w-full max-w-[28px] rounded-t-lg transition-all duration-500 group-hover:brightness-125"
                                                :class="bar.isPeak ? 'bg-gradient-to-t from-orange-500 to-amber-400 shadow-lg shadow-amber-500/30' : 'bg-slate-800 hover:bg-slate-700'"
                                                :style="{ height: `${bar.height}%` }"
                                            ></div>
                                        </div>
                                        <span class="text-[11px] font-mono" :class="bar.isPeak ? 'text-amber-400 font-extrabold' : 'text-slate-400'">{{ bar.time }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Top Dishes -->
                            <div class="lg:col-span-5 p-5 rounded-2xl bg-slate-900/60 border border-white/5 space-y-4">
                                <h5 class="text-sm font-bold text-white">{{ t.demo.chartTopDishesTitle }}</h5>
                                <div class="space-y-3 pt-2">
                                    <div v-for="(dish, idx) in topDishesData" :key="idx" class="space-y-1">
                                        <div class="flex justify-between text-xs font-semibold">
                                            <span class="text-slate-200">{{ dish.name }}</span>
                                            <span class="text-amber-400 font-mono">{{ dish.percent }}% ({{ dish.orders }} {{ t.demo.ordersUnit }})</span>
                                        </div>
                                        <div class="w-full h-2 rounded-full bg-slate-800 overflow-hidden">
                                            <div class="h-full rounded-full transition-all duration-700" :class="dish.color" :style="{ width: `${dish.percent}%` }"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Rule-Based Smart Advice Engine -->
                        <div class="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/30 flex items-start gap-3">
                            <span class="text-xl">💡</span>
                            <div>
                                <h5 class="text-sm font-bold text-amber-300">{{ t.demo.aiInsightTitle }}</h5>
                                <p class="text-xs text-slate-300 mt-1">
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
        <section id="features" class="py-20 relative z-10 border-t border-white/5 bg-[#080d19]/60">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                <div class="text-center max-w-3xl mx-auto mb-16 space-y-4">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-orange-500/10 text-orange-400 text-xs font-bold border border-orange-500/20">
                        <span>{{ t.features.tag }}</span>
                    </div>
                    <h2 class="text-3xl sm:text-4xl font-extrabold text-white">
                        {{ t.features.title }}
                    </h2>
                    <p class="text-slate-400 text-base sm:text-lg">
                        {{ t.features.subtitle }}
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <!-- Feature 1 -->
                    <div class="p-8 rounded-3xl bg-slate-900/60 border border-white/5 hover:border-amber-500/30 transition-all duration-300 hover:-translate-y-1 group">
                        <div class="w-14 h-14 rounded-2xl bg-amber-500/10 text-amber-400 border border-amber-500/20 flex items-center justify-center text-2xl mb-6 group-hover:scale-110 transition-transform">
                            ⚡
                        </div>
                        <h4 class="text-xl font-bold text-white mb-3">{{ t.features.f1Title }}</h4>
                        <p class="text-sm text-slate-400 leading-relaxed">{{ t.features.f1Desc }}</p>
                    </div>

                    <!-- Feature 2 -->
                    <div class="p-8 rounded-3xl bg-slate-900/60 border border-white/5 hover:border-amber-500/30 transition-all duration-300 hover:-translate-y-1 group">
                        <div class="w-14 h-14 rounded-2xl bg-orange-500/10 text-orange-400 border border-orange-500/20 flex items-center justify-center text-2xl mb-6 group-hover:scale-110 transition-transform">
                            📱
                        </div>
                        <h4 class="text-xl font-bold text-white mb-3">{{ t.features.f2Title }}</h4>
                        <p class="text-sm text-slate-400 leading-relaxed">{{ t.features.f2Desc }}</p>
                    </div>

                    <!-- Feature 3 -->
                    <div class="p-8 rounded-3xl bg-slate-900/60 border border-white/5 hover:border-amber-500/30 transition-all duration-300 hover:-translate-y-1 group">
                        <div class="w-14 h-14 rounded-2xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 flex items-center justify-center text-2xl mb-6 group-hover:scale-110 transition-transform">
                            👨‍🍳
                        </div>
                        <h4 class="text-xl font-bold text-white mb-3">{{ t.features.f3Title }}</h4>
                        <p class="text-sm text-slate-400 leading-relaxed">{{ t.features.f3Desc }}</p>
                    </div>

                    <!-- Feature 4 -->
                    <div class="p-8 rounded-3xl bg-slate-900/60 border border-white/5 hover:border-amber-500/30 transition-all duration-300 hover:-translate-y-1 group">
                        <div class="w-14 h-14 rounded-2xl bg-amber-400/10 text-amber-300 border border-amber-400/20 flex items-center justify-center text-2xl mb-6 group-hover:scale-110 transition-transform">
                            🖨️
                        </div>
                        <h4 class="text-xl font-bold text-white mb-3">{{ t.features.f4Title }}</h4>
                        <p class="text-sm text-slate-400 leading-relaxed">{{ t.features.f4Desc }}</p>
                    </div>

                    <!-- Feature 5 -->
                    <div class="p-8 rounded-3xl bg-slate-900/60 border border-white/5 hover:border-amber-500/30 transition-all duration-300 hover:-translate-y-1 group">
                        <div class="w-14 h-14 rounded-2xl bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 flex items-center justify-center text-2xl mb-6 group-hover:scale-110 transition-transform">
                            🏢
                        </div>
                        <h4 class="text-xl font-bold text-white mb-3">{{ t.features.f5Title }}</h4>
                        <p class="text-sm text-slate-400 leading-relaxed">{{ t.features.f5Desc }}</p>
                    </div>

                    <!-- Feature 6 -->
                    <div class="p-8 rounded-3xl bg-slate-900/60 border border-white/5 hover:border-amber-500/30 transition-all duration-300 hover:-translate-y-1 group">
                        <div class="w-14 h-14 rounded-2xl bg-rose-500/10 text-rose-400 border border-rose-500/20 flex items-center justify-center text-2xl mb-6 group-hover:scale-110 transition-transform">
                            📈
                        </div>
                        <h4 class="text-xl font-bold text-white mb-3">{{ t.features.f6Title }}</h4>
                        <p class="text-sm text-slate-400 leading-relaxed">{{ t.features.f6Desc }}</p>
                    </div>
                </div>

            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- ROI & SAVINGS CALCULATOR -->
        <!-- ========================================================================= -->
        <section id="roi-calculator" class="py-24 relative z-10 border-t border-white/5 bg-[#070b14]">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                <div class="text-center max-w-3xl mx-auto mb-16 space-y-4">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/10 text-emerald-400 text-xs font-bold border border-emerald-500/20">
                        <span>{{ t.roi.tag }}</span>
                    </div>
                    <h2 class="text-3xl sm:text-4xl font-extrabold text-white">
                        {{ t.roi.title }}
                    </h2>
                    <p class="text-slate-400 text-base sm:text-lg">
                        {{ t.roi.subtitle }}
                    </p>
                </div>

                <div class="max-w-4xl mx-auto p-8 sm:p-12 rounded-3xl bg-slate-900/80 border border-white/10 shadow-2xl backdrop-blur-xl">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-10 items-center">

                        <!-- Sliders Controls -->
                        <div class="space-y-8">
                            <!-- Table Count Slider -->
                            <div class="space-y-3">
                                <div class="flex justify-between items-center text-sm">
                                    <span class="font-bold text-white">{{ t.roi.tablesLabel }}</span>
                                    <span class="text-amber-400 font-extrabold text-xl font-mono">{{ tableCount }} {{ t.roi.tablesUnit }}</span>
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
                                    <span>5</span>
                                    <span>120</span>
                                </div>
                            </div>

                            <!-- Daily Orders Slider -->
                            <div class="space-y-3">
                                <div class="flex justify-between items-center text-sm">
                                    <span class="font-bold text-white">{{ t.roi.dailyOrdersLabel }}</span>
                                    <span class="text-amber-400 font-extrabold text-xl font-mono">{{ dailyOrdersPerTable }} {{ t.roi.dailyOrdersUnit }}</span>
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
                                    <span>2</span>
                                    <span>15</span>
                                </div>
                            </div>

                            <div class="p-4 rounded-2xl bg-white/[0.02] border border-white/5 text-xs text-slate-400 space-y-1">
                                <div class="flex justify-between">
                                    <span>{{ t.roi.monthlyOrdersEst }}</span>
                                    <strong class="text-white font-mono">{{ monthlyOrders.toLocaleString() }} {{ t.roi.ordersCountUnit }}</strong>
                                </div>
                                <div class="flex justify-between">
                                    <span>{{ t.roi.avgTimeSavedLabel }}</span>
                                    <strong class="text-emerald-400 font-mono">{{ t.roi.avgTimeSavedVal }}</strong>
                                </div>
                            </div>
                        </div>

                        <!-- Computed Impact Output -->
                        <div class="space-y-4 p-6 rounded-2xl bg-slate-950 border border-amber-500/20">

                            <div class="p-4 rounded-xl bg-amber-500/10 border border-amber-500/30">
                                <div class="text-xs text-amber-300 font-medium">{{ t.roi.paperSavingsTitle }}</div>
                                <div class="text-2xl sm:text-3xl font-black text-amber-400 font-mono mt-1">
                                    {{ formatPrice(yearlyPaperSavings) }} {{ currentCurrencySymbol }}
                                </div>
                                <div class="text-[11px] text-slate-400 mt-1">{{ t.roi.paperSavingsNote }}</div>
                            </div>

                            <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30">
                                <div class="text-xs text-emerald-300 font-medium">{{ t.roi.laborHoursTitle }}</div>
                                <div class="text-2xl sm:text-3xl font-black text-emerald-400 font-mono mt-1">
                                    {{ timeSavedHoursMonthly.toLocaleString() }} {{ t.roi.laborHoursUnit }}
                                </div>
                                <div class="text-[11px] text-slate-400 mt-1">{{ t.roi.laborHoursNote }}</div>
                            </div>

                            <div class="p-4 rounded-xl bg-orange-500/10 border border-orange-500/30">
                                <div class="text-xs text-orange-300 font-medium">{{ t.roi.revenueBoostTitle }}</div>
                                <div class="text-2xl sm:text-3xl font-black text-orange-400 font-mono mt-1">
                                    +{{ formatPrice(estimatedRevenueBoost) }} {{ currentCurrencySymbol }} {{ t.roi.revenueBoostPerMonth }}
                                </div>
                                <div class="text-[11px] text-slate-400 mt-1">{{ t.roi.revenueBoostNote }}</div>
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
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-rose-500/10 text-rose-400 text-xs font-bold border border-rose-500/20">
                        <span>{{ t.comparison.tag }}</span>
                    </div>
                    <h2 class="text-3xl sm:text-4xl font-extrabold text-white">
                        {{ t.comparison.title }}
                    </h2>
                    <p class="text-slate-400 text-base sm:text-lg">
                        {{ t.comparison.subtitle }}
                    </p>
                </div>

                <div class="max-w-4xl mx-auto overflow-hidden rounded-3xl border border-white/10 bg-slate-900/60 backdrop-blur-xl shadow-2xl">
                    <div class="grid grid-cols-12 bg-slate-950 border-b border-white/10 p-4 sm:p-6 text-xs sm:text-sm font-bold">
                        <div class="col-span-4 sm:col-span-4 text-slate-400">{{ currentLocale === 'ar' ? 'المعيار التشغيلي' : 'Operational Metric' }}</div>
                        <div class="col-span-4 sm:col-span-4 text-rose-400">{{ t.comparison.colTraditional }}</div>
                        <div class="col-span-4 sm:col-span-4 text-amber-400 font-black">{{ t.comparison.colTedallal }}</div>
                    </div>

                    <div class="divide-y divide-white/5 text-xs sm:text-sm">
                        <!-- Row 1 -->
                        <div class="grid grid-cols-12 p-4 sm:p-6 items-center hover:bg-white/[0.02] transition-colors">
                            <div class="col-span-4 font-bold text-white">{{ t.comparison.r1Label }}</div>
                            <div class="col-span-4 text-slate-400 leading-relaxed">{{ t.comparison.r1Trad }}</div>
                            <div class="col-span-4 text-amber-300 font-semibold leading-relaxed">{{ t.comparison.r1Ted }}</div>
                        </div>

                        <!-- Row 2 -->
                        <div class="grid grid-cols-12 p-4 sm:p-6 items-center hover:bg-white/[0.02] transition-colors">
                            <div class="col-span-4 font-bold text-white">{{ t.comparison.r2Label }}</div>
                            <div class="col-span-4 text-slate-400 leading-relaxed">{{ t.comparison.r2Trad }}</div>
                            <div class="col-span-4 text-amber-300 font-semibold leading-relaxed">{{ t.comparison.r2Ted }}</div>
                        </div>

                        <!-- Row 3 -->
                        <div class="grid grid-cols-12 p-4 sm:p-6 items-center hover:bg-white/[0.02] transition-colors">
                            <div class="col-span-4 font-bold text-white">{{ t.comparison.r3Label }}</div>
                            <div class="col-span-4 text-slate-400 leading-relaxed">{{ t.comparison.r3Trad }}</div>
                            <div class="col-span-4 text-amber-300 font-semibold leading-relaxed">{{ t.comparison.r3Ted }}</div>
                        </div>

                        <!-- Row 4 -->
                        <div class="grid grid-cols-12 p-4 sm:p-6 items-center hover:bg-white/[0.02] transition-colors">
                            <div class="col-span-4 font-bold text-white">{{ t.comparison.r4Label }}</div>
                            <div class="col-span-4 text-slate-400 leading-relaxed">{{ t.comparison.r4Trad }}</div>
                            <div class="col-span-4 text-amber-300 font-semibold leading-relaxed">{{ t.comparison.r4Ted }}</div>
                        </div>

                        <!-- Row 5 -->
                        <div class="grid grid-cols-12 p-4 sm:p-6 items-center hover:bg-white/[0.02] transition-colors">
                            <div class="col-span-4 font-bold text-white">{{ t.comparison.r5Label }}</div>
                            <div class="col-span-4 text-slate-400 leading-relaxed">{{ t.comparison.r5Trad }}</div>
                            <div class="col-span-4 text-amber-300 font-semibold leading-relaxed">{{ t.comparison.r5Ted }}</div>
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- TESTIMONIALS -->
        <!-- ========================================================================= -->
        <section class="py-20 relative z-10 border-t border-white/5 bg-[#070b14]">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                <div class="text-center max-w-3xl mx-auto mb-16 space-y-4">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-500/10 text-amber-400 text-xs font-bold border border-amber-500/20">
                        <span>{{ t.testimonials.tag }}</span>
                    </div>
                    <h2 class="text-3xl sm:text-4xl font-extrabold text-white">
                        {{ t.testimonials.title }}
                    </h2>
                    <p class="text-slate-400 text-base sm:text-lg">
                        {{ t.testimonials.subtitle }}
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <!-- Review 1 -->
                    <div class="p-8 rounded-3xl bg-slate-900/60 border border-white/5 space-y-6 flex flex-col justify-between">
                        <p class="text-slate-300 text-sm leading-relaxed italic">
                            {{ t.testimonials.t1Text }}
                        </p>
                        <div class="flex items-center gap-4 pt-4 border-t border-white/5">
                            <div class="w-11 h-11 rounded-full bg-gradient-to-tr from-amber-500 to-orange-500 text-slate-950 font-bold flex items-center justify-center text-sm">
                                TS
                            </div>
                            <div>
                                <h5 class="text-sm font-bold text-white">{{ t.testimonials.t1Author }}</h5>
                                <p class="text-xs text-amber-400/90">{{ t.testimonials.t1Role }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Review 2 -->
                    <div class="p-8 rounded-3xl bg-slate-900/60 border border-white/5 space-y-6 flex flex-col justify-between">
                        <p class="text-slate-300 text-sm leading-relaxed italic">
                            {{ t.testimonials.t2Text }}
                        </p>
                        <div class="flex items-center gap-4 pt-4 border-t border-white/5">
                            <div class="w-11 h-11 rounded-full bg-gradient-to-tr from-orange-500 to-rose-500 text-white font-bold flex items-center justify-center text-sm">
                                SA
                            </div>
                            <div>
                                <h5 class="text-sm font-bold text-white">{{ t.testimonials.t2Author }}</h5>
                                <p class="text-xs text-amber-400/90">{{ t.testimonials.t2Role }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Review 3 -->
                    <div class="p-8 rounded-3xl bg-slate-900/60 border border-white/5 space-y-6 flex flex-col justify-between">
                        <p class="text-slate-300 text-sm leading-relaxed italic">
                            {{ t.testimonials.t3Text }}
                        </p>
                        <div class="flex items-center gap-4 pt-4 border-t border-white/5">
                            <div class="w-11 h-11 rounded-full bg-gradient-to-tr from-emerald-500 to-amber-500 text-slate-950 font-bold flex items-center justify-center text-sm">
                                FT
                            </div>
                            <div>
                                <h5 class="text-sm font-bold text-white">{{ t.testimonials.t3Author }}</h5>
                                <p class="text-xs text-amber-400/90">{{ t.testimonials.t3Role }}</p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- FAQS ACCORDION -->
        <!-- ========================================================================= -->
        <section id="faqs" class="py-20 relative z-10 border-t border-white/5 bg-[#080d19]/80">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

                <div class="text-center mb-16 space-y-4">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-500/10 text-amber-400 text-xs font-bold border border-amber-500/20">
                        <span>{{ t.faqs.tag }}</span>
                    </div>
                    <h2 class="text-3xl sm:text-4xl font-extrabold text-white">
                        {{ t.faqs.title }}
                    </h2>
                    <p class="text-slate-400 text-base">
                        {{ t.faqs.subtitle }}
                    </p>
                </div>

                <div class="space-y-4">
                    <div
                        v-for="(faq, idx) in t.faqs.items"
                        :key="idx"
                        class="rounded-2xl border border-white/5 bg-slate-900/70 overflow-hidden transition-all"
                    >
                        <button
                            @click="toggleFaq(idx)"
                            class="w-full p-5 text-right flex items-center justify-between gap-4 font-bold text-sm sm:text-base text-white hover:text-amber-400 transition-colors"
                            :class="currentLocale === 'en' ? 'text-left' : 'text-right'"
                        >
                            <span>{{ faq.q }}</span>
                            <span class="text-amber-400 text-xl font-mono shrink-0">
                                {{ openFaqIndex === idx ? '−' : '+' }}
                            </span>
                        </button>
                        <div v-if="openFaqIndex === idx" class="px-5 pb-5 text-xs sm:text-sm text-slate-300 leading-relaxed border-t border-white/5 pt-4">
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
                v-if="showToast && liveToasts[currentToastIndex]"
                class="fixed bottom-6 z-40 max-w-sm w-[90%] sm:w-auto p-4 rounded-2xl bg-[#090e1c]/95 border border-amber-500/30 shadow-2xl backdrop-blur-2xl flex items-center gap-3.5"
                :class="currentLocale === 'ar' ? 'right-6' : 'left-6'"
            >
                <div class="w-10 h-10 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center shrink-0 text-lg font-bold">
                    🔔
                </div>
                <div class="flex-1 text-xs">
                    <div class="flex items-center justify-between gap-2 mb-0.5">
                        <span class="font-extrabold text-amber-400">{{ liveToasts[currentToastIndex].table }}</span>
                        <span class="text-[10px] text-slate-400 font-mono">{{ liveToasts[currentToastIndex].time }}</span>
                    </div>
                    <p class="text-slate-200 line-clamp-2">
                        {{ liveToasts[currentToastIndex].text }}
                    </p>
                </div>
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
            <div class="relative w-full max-w-lg rounded-3xl bg-slate-900 border border-white/10 p-6 sm:p-8 shadow-2xl space-y-6">

                <button
                    @click="isDemoModalOpen = false"
                    class="absolute top-5 text-slate-400 hover:text-white text-xl font-bold p-2"
                    :class="currentLocale === 'ar' ? 'left-5' : 'right-5'"
                >
                    ✕
                </button>

                <!-- Initial Form State -->
                <div v-if="!isSubmitted" class="space-y-4">
                    <div class="space-y-2">
                        <span class="text-xs font-bold text-amber-400">{{ t.modal.badge }}</span>
                        <h3 class="text-2xl font-black text-white">{{ t.modal.title }}</h3>
                        <p class="text-xs text-slate-400">
                            {{ t.modal.subtitle }}
                        </p>
                    </div>

                    <form @submit.prevent="submitDemoRequest" class="space-y-3.5 pt-2">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">{{ t.modal.restaurantName }}</label>
                            <input
                                v-model="demoForm.restaurantName"
                                required
                                type="text"
                                :placeholder="t.modal.restaurantNamePlaceholder"
                                class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white text-sm focus:border-amber-500 focus:outline-none"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">{{ t.modal.contactPerson }}</label>
                            <input
                                v-model="demoForm.contactPerson"
                                required
                                type="text"
                                :placeholder="t.modal.contactPersonPlaceholder"
                                class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white text-sm focus:border-amber-500 focus:outline-none"
                            />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-300 mb-1">{{ t.modal.phone }}</label>
                                <input
                                    v-model="demoForm.phone"
                                    required
                                    type="tel"
                                    :placeholder="t.modal.phonePlaceholder"
                                    class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white text-sm focus:border-amber-500 focus:outline-none"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-300 mb-1">{{ t.modal.country }}</label>
                                <select
                                    v-model="demoForm.country"
                                    class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white text-sm focus:border-amber-500 focus:outline-none"
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
                            <label class="block text-xs font-semibold text-slate-300 mb-1">{{ t.modal.tablesCount }}</label>
                            <select
                                v-model="demoForm.tablesCount"
                                class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white text-sm focus:border-amber-500 focus:outline-none"
                            >
                                <option value="1-15">{{ t.modal.tablesOption1 }}</option>
                                <option value="15-35">{{ t.modal.tablesOption2 }}</option>
                                <option value="35-70">{{ t.modal.tablesOption3 }}</option>
                                <option value="70+">{{ t.modal.tablesOption4 }}</option>
                            </select>
                        </div>

                        <button
                            type="submit"
                            class="w-full py-3.5 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 text-slate-950 font-extrabold text-sm shadow-xl shadow-amber-500/25 hover:shadow-amber-500/40 hover:scale-[1.01] transition-all mt-2"
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
                    <h3 class="text-2xl font-bold text-white">{{ t.modal.successTitle }}</h3>
                    <p class="text-slate-300 text-sm max-w-sm mx-auto">
                        {{ t.modal.successDesc }}
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
                            {{ t.footer.brandDesc }}
                        </p>
                        <div class="flex items-center gap-2 text-xs text-emerald-400">
                            <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                            <span>{{ t.footer.serverStatus }}</span>
                        </div>
                    </div>

                    <!-- Col 2: Quick Links -->
                    <div class="space-y-3">
                        <h5 class="text-xs font-bold text-white uppercase tracking-wider">{{ t.footer.quickLinksTitle }}</h5>
                        <ul class="space-y-2 text-xs">
                            <li><a href="#how-it-works" class="hover:text-amber-400 transition-colors">{{ t.nav.howItWorks }}</a></li>
                            <li><a href="#interactive-demo" class="hover:text-amber-400 transition-colors">{{ t.nav.interactiveDemo }}</a></li>
                            <li><a href="#features" class="hover:text-amber-400 transition-colors">{{ t.nav.features }}</a></li>
                            <li><a href="#roi-calculator" class="hover:text-amber-400 transition-colors">{{ t.nav.roiCalculator }}</a></li>
                            <li><a href="#faqs" class="hover:text-amber-400 transition-colors">{{ t.nav.faqs }}</a></li>
                        </ul>
                    </div>

                    <!-- Col 3: Portal Access -->
                    <div class="space-y-3">
                        <h5 class="text-xs font-bold text-white uppercase tracking-wider">{{ t.footer.portalAccessTitle }}</h5>
                        <ul class="space-y-2 text-xs">
                            <li><Link :href="route('login')" class="hover:text-amber-400 transition-colors">{{ t.footer.adminPortal }}</Link></li>
                            <li><Link :href="route('login')" class="hover:text-amber-400 transition-colors">{{ t.footer.kdsPortal }}</Link></li>
                            <li><Link :href="route('login')" class="hover:text-amber-400 transition-colors">{{ t.footer.superAdminPortal }}</Link></li>
                            <li><button @click="isDemoModalOpen = true" class="text-amber-400 hover:underline">{{ t.footer.requestAccount }}</button></li>
                        </ul>
                    </div>

                </div>

                <div class="pt-8 border-t border-white/5 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-500">
                    <div>
                        {{ t.footer.rights }} {{ new Date().getFullYear() }}
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
</style>
