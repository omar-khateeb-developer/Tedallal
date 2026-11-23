<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ref, onMounted, computed } from 'vue';

const props = defineProps<{
    message?: string;
}>();

const page = usePage();
const authUser = computed(() => (page.props.auth as any)?.user);

const currentLang = ref<'ar' | 'en'>('ar');

const i18n = {
    ar: {
        title: '403 · صالة خاصة ومحمية | تدلّل Tedallal',
        brand: 'تدلّل · Tedallal',
        badge: '✦ رمز الخطأ 403 · صالة خاصة ومحمية ✦',
        headline: 'عذراً، هذه الصالة مخصصة لأعضاء الإدارة أو رتب محددة',
        desc: 'أهلاً بك في تدلّل! يبدو أن الصفحة أو الإجراء الذي تحاول فتحه يتطلب صلاحيات مخصصة (مثل صلاحية مدير المطعم، شيف المطبخ، أو الكاشير). لا تقلق، يمكنك العودة لمساحتك المعتادة أو تسجيل الدخول بالحساب المصرح له.',
        defaultNotice: 'يتطلب هذا القسم صلاحيات وصول إدارية خاصة.',
        btnDashboard: 'الذهاب للوحة التحكم',
        btnHome: 'العودة للرئيسية',
        btnLogin: 'تبديل الحساب / تسجيل الدخول',
        btnBack: 'الرجوع خطوة للخلف',
        conciergeHead: 'كونسيرج تدلّل · طلب ترقية الصلاحية',
        conciergeSub: 'إذا كنت تعتقد أنك تستحق الوصول لهذه الصفحة، يمكنك مراسلة إدارة المنصة أو مسؤول المطعم فوراً.',
        whatsappBtn: 'محادثة الإدارة واتساب',
        footer: 'منظومة تدلّل السحابية © 2026 · الفخامة والأمان في كل تفصيل',
        langLabel: 'English',
    },
    en: {
        title: '403 · Exclusive Area | Tedallal',
        brand: 'Tedallal · تدلّل',
        badge: '✦ ERROR 403 · RESTRICTED ACCESS ✦',
        headline: 'Exclusive Lounge · Access Restricted to Designated Roles',
        desc: 'Welcome to Tedallal! The page or action you are attempting to open requires specific permissions (such as Restaurant Manager, Kitchen Chef, or Cashier). Rest assured, you can easily return to your authorized workspace or sign in with the proper account.',
        defaultNotice: 'This section requires specific administrative privileges.',
        btnDashboard: 'Go to Dashboard',
        btnHome: 'Return to Homepage',
        btnLogin: 'Switch Account / Log In',
        btnBack: 'Go Back One Step',
        conciergeHead: 'Tedallal Concierge · Request Access',
        conciergeSub: 'If you believe you should have access to this section, contact platform management or your restaurant administrator.',
        whatsappBtn: 'WhatsApp Support',
        footer: 'Tedallal Cloud Platform © 2026 · Luxury & Security in Every Detail',
        langLabel: 'العربية',
    },
};

function toggleLanguage() {
    currentLang.value = currentLang.value === 'ar' ? 'en' : 'ar';
}

function goBack() {
    if (typeof window !== 'undefined' && window.history && window.history.length > 1) {
        window.history.back();
    } else {
        window.location.href = authUser.value ? '/dashboard' : '/';
    }
}

const currentUrl = ref('');
onMounted(() => {
    if (typeof window !== 'undefined') {
        currentUrl.value = window.location.href;
    }
});
</script>

<template>
    <div
        :dir="currentLang === 'ar' ? 'rtl' : 'ltr'"
        class="min-h-screen bg-[#05040a] flex items-center justify-center p-4 sm:p-6 relative overflow-hidden font-['Tajawal','Inter',sans-serif] text-slate-100 select-none"
    >
        <Head :title="i18n[currentLang].title" />

        <!-- Ambient Atmospheric Orbs -->
        <div class="fixed inset-0 pointer-events-none z-0 overflow-hidden">
            <div
                class="absolute -top-32 -right-24 w-[520px] h-[520px] rounded-full blur-[110px] opacity-30 animate-pulse"
                style="background: radial-gradient(circle, #d97706 0%, transparent 70%); animation-duration: 9s;"
            ></div>
            <div
                class="absolute -bottom-36 -left-28 w-[480px] h-[480px] rounded-full blur-[100px] opacity-25 animate-pulse"
                style="background: radial-gradient(circle, #7c3aed 0%, transparent 70%); animation-duration: 11s;"
            ></div>
            <div
                class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[440px] h-[440px] rounded-full blur-[95px] opacity-15"
                style="background: radial-gradient(circle, #d4af37 0%, transparent 70%);"
            ></div>
            <div
                class="absolute inset-0 opacity-20"
                style="background-image: linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px); background-size: 44px 44px;"
            ></div>
        </div>

        <!-- Top Header Bar -->
        <header class="fixed top-5 left-6 right-6 z-50 flex items-center justify-between max-w-6xl mx-auto pointer-events-none">
            <Link
                href="/"
                class="pointer-events-auto inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white/[0.04] border border-amber-500/25 backdrop-blur-md text-amber-300 font-bold text-sm hover:bg-white/[0.08] hover:border-amber-500/50 transition-all shadow-lg shadow-black/40"
            >
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m2 4 3 12h14l3-12-6 7-4-7-4 7-6-7zm3 16h14"/>
                </svg>
                <span>{{ i18n[currentLang].brand }}</span>
            </Link>

            <button
                @click="toggleLanguage"
                class="pointer-events-auto inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full bg-white/[0.04] border border-white/15 backdrop-blur-md text-slate-200 text-xs font-semibold hover:bg-white/[0.1] hover:border-white/30 transition-all"
            >
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/>
                    <path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/>
                    <path d="M2 12h20"/>
                </svg>
                <span>{{ i18n[currentLang].langLabel }}</span>
            </button>
        </header>

        <!-- Main Card -->
        <main
            class="relative z-10 max-w-[650px] w-full mx-auto rounded-[32px] p-6 sm:p-10 text-center backdrop-blur-2xl bg-gradient-to-br from-white/[0.06] to-white/[0.015] border border-white/10 shadow-[0_30px_80px_-20px_rgba(0,0,0,0.8),0_0_50px_-10px_rgba(217,119,6,0.2)]"
        >
            <!-- Royal Shield & Key Emblem -->
            <div class="w-20 h-20 mx-auto mb-4 drop-shadow-[0_8px_20px_rgba(212,175,55,0.45)] transition-transform duration-500 hover:scale-105">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-full h-full">
                    <defs>
                        <linearGradient id="shield-gold-403" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#FFF8DC" />
                            <stop offset="30%" stop-color="#F5D77F" />
                            <stop offset="70%" stop-color="#D4AF37" />
                            <stop offset="100%" stop-color="#8F6920" />
                        </linearGradient>
                    </defs>
                    <!-- Shield outline -->
                    <path
                        d="M12 2L3 6V11.5C3 16.5 6.8 21.2 12 22.5C17.2 21.2 21 16.5 21 11.5V6L12 2Z"
                        stroke="url(#shield-gold-403)"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                    <!-- Inner Lock Body -->
                    <rect
                        x="8.5"
                        y="11.5"
                        width="7"
                        height="5.5"
                        rx="1.5"
                        fill="url(#shield-gold-403)"
                    />
                    <!-- Inner Lock Shackle -->
                    <path
                        d="M10 11.5V9.5C10 8.4 10.9 7.5 12 7.5C13.1 7.5 14 8.4 14 9.5V11.5"
                        stroke="url(#shield-gold-403)"
                        stroke-width="1.6"
                        stroke-linecap="round"
                    />
                    <!-- Keyhole -->
                    <circle cx="12" cy="13.8" r="0.8" fill="#1e1809" />
                    <path d="M12 14.5V15.7" stroke="#1e1809" stroke-width="0.8" stroke-linecap="round" />
                </svg>
            </div>

            <!-- 403 Large Display -->
            <div class="relative inline-flex items-center justify-center leading-none mb-2">
                <span
                    class="text-7xl sm:text-9xl font-black tracking-wider bg-gradient-to-br from-[#FFF8DC] via-[#E5C37A] to-[#8F6920] bg-clip-text text-transparent drop-shadow-[0_10px_30px_rgba(0,0,0,0.6)] font-serif"
                >
                    403
                </span>
            </div>

            <!-- Badge -->
            <div class="mb-4">
                <span
                    class="inline-flex items-center gap-2 px-4 py-1 rounded-full bg-amber-400/10 border border-amber-400/30 text-amber-300 text-xs sm:text-sm font-bold shadow-[0_0_20px_rgba(212,175,55,0.15)]"
                >
                    <span class="w-2 h-2 rounded-full bg-amber-400 animate-ping"></span>
                    {{ i18n[currentLang].badge }}
                </span>
            </div>

            <!-- Headline -->
            <h1 class="text-xl sm:text-3xl font-extrabold text-white mb-3">
                {{ i18n[currentLang].headline }}
            </h1>

            <!-- Description -->
            <p class="text-sm sm:text-base text-slate-400 max-w-lg mx-auto mb-5 leading-relaxed">
                {{ i18n[currentLang].desc }}
            </p>

            <!-- Specific Exception / Notice Callout if present -->
            <div
                v-if="props.message"
                class="mb-6 p-3 rounded-2xl bg-amber-500/[0.08] border border-amber-500/25 text-amber-200 text-xs sm:text-sm font-semibold flex items-center justify-center gap-2"
            >
                <span>🔒</span>
                <span>{{ props.message }}</span>
            </div>

            <!-- Actions Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 mb-6">
                <!-- Dashboard (if logged in) or Home -->
                <Link
                    :href="authUser ? route('dashboard') : '/'"
                    class="inline-flex items-center justify-center gap-2 px-5 py-3.5 rounded-2xl font-bold text-sm bg-gradient-to-r from-amber-400 to-amber-600 text-slate-950 shadow-[0_6px_20px_rgba(212,175,55,0.4)] hover:brightness-110 hover:-translate-y-0.5 transition-all cursor-pointer"
                >
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                        <polyline points="9 22 9 12 15 12 15 22"/>
                    </svg>
                    <span>{{ authUser ? i18n[currentLang].btnDashboard : i18n[currentLang].btnHome }}</span>
                </Link>

                <!-- Switch Account / Login -->
                <Link
                    :href="route('login')"
                    class="inline-flex items-center justify-center gap-2 px-5 py-3.5 rounded-2xl font-bold text-sm bg-indigo-500/15 border border-indigo-400/30 text-indigo-200 hover:bg-indigo-500/25 hover:border-indigo-400/60 hover:-translate-y-0.5 transition-all shadow-md shadow-indigo-900/20 cursor-pointer"
                >
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="8" cy="15" r="4"/>
                        <path d="m11 12 7-7 3 3-2 2-2-2-3 3"/>
                    </svg>
                    <span>{{ i18n[currentLang].btnLogin }}</span>
                </Link>

                <!-- Return Home (if logged in) -->
                <Link
                    v-if="authUser"
                    href="/"
                    class="inline-flex items-center justify-center gap-2 px-5 py-3.5 rounded-2xl font-bold text-sm bg-white/[0.04] border border-white/10 text-slate-300 hover:bg-white/[0.08] hover:border-white/20 hover:text-white hover:-translate-y-0.5 transition-all cursor-pointer"
                >
                    <svg class="w-4 h-4 text-amber-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="m10 15 5-3-5-3v6z"/>
                    </svg>
                    <span>{{ i18n[currentLang].btnHome }}</span>
                </Link>

                <!-- Back One Step -->
                <button
                    type="button"
                    @click="goBack"
                    :class="[
                        'inline-flex items-center justify-center gap-2 px-5 py-3.5 rounded-2xl font-bold text-sm bg-white/[0.04] border border-white/10 text-slate-300 hover:bg-white/[0.08] hover:border-white/20 hover:text-white hover:-translate-y-0.5 transition-all cursor-pointer',
                        !authUser ? 'sm:col-span-2' : ''
                    ]"
                >
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m15 18-6-6 6-6"/>
                    </svg>
                    <span>{{ i18n[currentLang].btnBack }}</span>
                </button>
            </div>

            <!-- WhatsApp Concierge Box -->
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 p-3.5 sm:p-4 rounded-2xl bg-emerald-500/[0.08] border border-emerald-500/25 text-center sm:text-start hover:bg-emerald-500/[0.12] transition-colors">
                <div>
                    <h4 class="text-sm font-bold text-emerald-400 flex items-center justify-center sm:justify-start gap-1.5 mb-0.5">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.816 9.816 0 0 0 12.04 2z"/>
                        </svg>
                        <span>{{ i18n[currentLang].conciergeHead }}</span>
                    </h4>
                    <p class="text-xs text-slate-400 leading-snug">
                        {{ i18n[currentLang].conciergeSub }}
                    </p>
                </div>
                <a
                    :href="`https://wa.me/963947064593?text=${encodeURIComponent('مرحباً إدارة تدلّل، أحاول الوصول للصفحة التالية وظهرت لي رسالة عدم وجود صلاحية (رمز 403):\n' + currentUrl)}`"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center justify-center gap-1.5 px-4 py-2.5 sm:py-2 rounded-xl bg-emerald-500 text-emerald-950 font-extrabold text-xs whitespace-nowrap hover:bg-emerald-400 transition-transform hover:scale-105 shadow-md shadow-emerald-500/30 cursor-pointer"
                >
                    <span>{{ i18n[currentLang].whatsappBtn }}</span>
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 12h14M12 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>

            <!-- Footer Note -->
            <p class="mt-6 text-xs text-slate-500">
                {{ i18n[currentLang].footer }}
            </p>
        </main>
    </div>
</template>
