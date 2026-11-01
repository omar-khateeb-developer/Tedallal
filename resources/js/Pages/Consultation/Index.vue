<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { useTheme } from '@/composables/useTheme';

interface Props {
    detectedCountry?: string | null;
}

const props = defineProps<Props>();

// Theme System
const { isDark, toggleTheme, initTheme } = useTheme();

onMounted(() => {
    initTheme('dark');
});

// Step Management (1, 2, 3 -> 4 for Instant Success Blueprint)
const currentStep = ref(1);
const totalSteps = 3;
const isSubmitting = ref(false);
const errorMessage = ref('');
const resultBlueprint = ref<any>(null);

// Form State (Concentrated on what Tedallal actually delivers)
const form = ref({
    // Step 1: Service & Cashier Model
    service_type: '',
    payment_model: '',

    // Step 2: Kitchen & Smart Features
    kitchen_setup: '',
    desired_features: [] as string[],

    // Step 3: Contact Info
    name: '',
    restaurant_name: '',
    phone: '',
    city: '',
    notes: '',
});

// 1. Service Types in Tedallal
const serviceTypeOptions = [
    {
        id: 'صالة طعام وطاولات جلوس (Dine-in)',
        title: 'صالة طعام وطاولات جلوس',
        desc: 'رمز QR ذكي على كل طاولة يطلبه الزبون مباشرة من مكانه دون انتظار الويتر',
        icon: '🪑',
        badge: 'باركود لكل طاولة',
    },
    {
        id: 'طلبات سفري واستلام من الكاونتر (Takeaway)',
        title: 'طلبات سفري واستلام من الكاونتر',
        desc: 'طلب سريع مع إشعار الزبون برقم طلبه للاستلام من نافذة التسليم فور جهوزيته',
        icon: '🛍️',
        badge: 'نداء رقم الطلب',
    },
    {
        id: 'كلاهما معاً (خدمة صالة + طلبات سفري)',
        title: 'كلاهما معاً (خدمة صالة + استلام سفري)',
        desc: 'نظام هجين متكامل يدير طاولات الصالة وطلبات الكاونتر في واجهة واحدة',
        icon: '🔄',
        badge: 'الأكثر طلباً',
    },
];

// 2. Payment Models in Tedallal
const paymentModelOptions = [
    {
        id: 'دفع مسبق عند الكاشير قبل التحضير (Prepaid)',
        title: 'دفع مسبق عند الكاشير (Prepaid)',
        desc: 'يسدد الزبون الفاتورة أولاً، ثم يُرسل الطلب للمطبخ لبدء التحضير فور استلام المبلغ',
        icon: '💳',
        tag: 'مناسب للوجبات السريعة والسفري',
    },
    {
        id: 'دفع لاحق بعد تناول الوجبة (Postpaid)',
        title: 'دفع لاحق بعد تناول الوجبة (Postpaid)',
        desc: 'يطلب الزبون براحته ويضيف أصنافاً جديدة، ثم يُغلق حسابه عند الكاشير عند المغادرة',
        icon: '🍽️',
        tag: 'مثالي للمطاعم والكافيهات',
    },
];

// 3. Kitchen Display Setup (KDS)
const kitchenSetupOptions = [
    {
        id: 'شاشة مطبخ مركزية واحدة',
        title: 'شاشة مطبخ مركزية واحدة',
        desc: 'تظهر كافة الطلبات على شاشة رئيسية واحدة لطاقم التحضير',
        icon: '🍳',
    },
    {
        id: 'عدة شاشات تحضير مخصصة (نوافذ متعددة KDS)',
        title: 'عدة شاشات تحضير مخصصة (نوافذ وأقسام متعددة)',
        desc: 'توجيه كل صنف لقسمه تلقائياً (مشاوي، بارد وسلطات، بار مشروبات، فرن...)',
        icon: '🥩🍕',
    },
];

// 4. Smart Modules in Tedallal
const desiredFeatureOptions = [
    {
        id: 'الويتر الذكي (AI Waiter)',
        title: 'الويتر الذكي بالذكاء الاصطناعي (AI Waiter)',
        desc: 'يجيب الزبائن فورياً، يقترح أطباقاً متوافقة لزيادة متوسط الفاتورة (Upselling)',
        icon: '🤖',
        highlight: 'حصرية تدلّل',
    },
    {
        id: 'منيو الباركود التفاعلي (Smart QR Menu)',
        title: 'منيو الباركود التفاعلي فائق السرعة',
        desc: 'تصفح أطباقك بصور عالية الجودة وملاحظات خاصة دون تحميل أي تطبيق',
        icon: '📱',
        highlight: 'أساسي',
    },
    {
        id: 'شاشات المطبخ الرقمية (KDS Kitchen Screens)',
        title: 'شاشات المطبخ الذكية وتتبع وقت الإعداد',
        desc: 'استبدال الفواتير الورقية بشاشات تفاعلية للشيف وتنبيهات جاهزية الوجبات',
        icon: '🖥️',
        highlight: 'كفاءة وسرعة',
    },
    {
        id: 'نظام الكوبونات والعروض الترويجية الذكية',
        title: 'نظام الكوبونات والعروض الترويجية الذكية',
        desc: 'حملات خصم ومكافآت حصرية تحفز الزبائن على تكرار الزيارة وترفع المبيعات',
        icon: '🎟️',
        highlight: 'تنشيط المبيعات',
    },
    {
        id: 'لوحة التقارير والتحليلات اللحظية (Live Reports)',
        title: 'تقارير المبيعات ومؤشرات الأداء اللحظية',
        desc: 'متابعة أداء الطاولات، الأصناف الأكثر طلباً، ومداخيل الكاشير مباشرة من هاتفك',
        icon: '📊',
        highlight: 'إدارة ذكية',
    },
];

// Multi-select toggle
function toggleFeature(featureId: string) {
    const idx = form.value.desired_features.indexOf(featureId);
    if (idx > -1) {
        form.value.desired_features.splice(idx, 1);
    } else {
        form.value.desired_features.push(featureId);
    }
}

// Validations
const isStep1Valid = computed(() => {
    return !!form.value.service_type && !!form.value.payment_model;
});

const isStep2Valid = computed(() => {
    return !!form.value.kitchen_setup && form.value.desired_features.length > 0;
});

const isStep3Valid = computed(() => {
    return (
        form.value.name.trim().length >= 2 &&
        form.value.restaurant_name.trim().length >= 2 &&
        form.value.phone.trim().length >= 7
    );
});

function nextStep() {
    errorMessage.value = '';
    if (currentStep.value === 1 && !isStep1Valid.value) {
        errorMessage.value = 'يرجى اختيار أسلوب استقبال الزبائن ونموذج المحاسبة للمتابعة.';
        return;
    }
    if (currentStep.value === 2 && !isStep2Valid.value) {
        errorMessage.value = 'يرجى اختيار نظام شاشات المطبخ وتحديد ميزة واحدة على الأقل.';
        return;
    }

    if (currentStep.value < totalSteps) {
        currentStep.value++;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

function prevStep() {
    errorMessage.value = '';
    if (currentStep.value > 1) {
        currentStep.value--;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

// Submission
async function submitConsultation() {
    if (!isStep3Valid.value) {
        errorMessage.value = 'يرجى كتابة اسمك الكريم، اسم المطعم، ورقم الهاتف بدقة للمتابعة.';
        return;
    }

    isSubmitting.value = true;
    errorMessage.value = '';

    try {
        const response = await axios.post('/consultation', form.value);
        resultBlueprint.value = response.data;
        currentStep.value = 4; // Success / Blueprint Screen
        window.scrollTo({ top: 0, behavior: 'smooth' });
    } catch (err: any) {
        if (err.response?.data?.message) {
            errorMessage.value = err.response.data.message;
        } else {
            errorMessage.value = 'حدث خطأ أثناء إرسال البيانات، يرجى المحاولة ثانية أو التواصل مباشرة عبر الواتساب.';
        }
    } finally {
        isSubmitting.value = false;
    }
}

const progressPercentage = computed(() => {
    if (currentStep.value > totalSteps) return 100;
    return Math.round(((currentStep.value - 1) / totalSteps) * 100);
});

const directWhatsappUrl = computed(() => {
    const text = encodeURIComponent(
        `مرحباً أستاذ عمر، قمت بتعبئة استمارة متطلبات مطعم (${form.value.restaurant_name}) عبر موقع تدلّل، وأود مناقشة الخطة التشغيلية وتفعيل النسخة التجريبية.`
    );
    return `https://wa.me/963934578117?text=${text}`;
});
</script>

<template>
    <Head>
        <title>صمّم بنية نظام مطعمك الذكي | تدلّل</title>
        <meta
            name="description"
            content="استمارة سريعة في أقل من دقيقة لتحديد متطلبات مطعمك ونموذج الخدمة والشاشات الذكية، واستلام دراسة تشغيلية وعرض فوري متكامل مع نظام تدلّل."
        />
    </Head>

    <div
        class="min-h-screen bg-stone-50 text-stone-900 transition-colors duration-200 selection:bg-amber-500 selection:text-white dark:bg-[#070b14] dark:text-stone-100"
        dir="rtl"
    >
        <!-- Floating Ambient Lighting -->
        <div class="pointer-events-none fixed inset-0 overflow-hidden">
            <div
                class="absolute -top-40 right-1/4 h-[500px] w-[500px] rounded-full bg-amber-500/10 blur-[130px] dark:bg-amber-500/15"
            ></div>
            <div
                class="absolute bottom-10 left-10 h-[450px] w-[450px] rounded-full bg-emerald-500/5 blur-[140px] dark:bg-emerald-500/10"
            ></div>
        </div>

        <!-- Sticky Navigation Header -->
        <header
            class="sticky top-0 z-40 border-b border-stone-200/80 bg-white/95 backdrop-blur-md dark:border-slate-800/80 dark:bg-[#0a0f1d]/95"
        >
            <div class="mx-auto flex max-w-3xl items-center justify-between px-4 py-3 sm:px-6">
                <Link :href="route('welcome')" class="flex items-center gap-2.5 group">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-500 text-slate-950 font-black shadow-md shadow-amber-500/25 text-lg transition-transform group-hover:scale-105"
                    >
                        ت
                    </div>
                    <div>
                        <span class="block text-base sm:text-lg font-black tracking-tight text-stone-900 dark:text-white">
                            تدلّـل <span class="text-xs text-amber-600 dark:text-amber-400 font-bold mr-1">Pro</span>
                        </span>
                        <span class="block text-[11px] font-medium text-stone-500 dark:text-slate-400">
                            استمارة تخصيص نظام المطاعم
                        </span>
                    </div>
                </Link>

                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        @click="toggleTheme"
                        class="flex h-9 w-9 items-center justify-center rounded-lg border border-stone-300 bg-stone-100 text-stone-700 transition hover:bg-stone-200 dark:border-slate-700 dark:bg-slate-800 dark:text-amber-400 dark:hover:bg-slate-700"
                        title="تبديل المظهر"
                    >
                        <span v-if="isDark">☀️</span>
                        <span v-else>🌙</span>
                    </button>
                    <Link
                        :href="route('welcome')"
                        class="hidden sm:inline-flex items-center gap-1.5 text-xs font-bold text-stone-600 hover:text-amber-600 dark:text-slate-300 dark:hover:text-amber-400 transition"
                    >
                        <span>الرئيسية</span>
                        <span class="text-sm">←</span>
                    </Link>
                </div>
            </div>

            <!-- Progress Bar -->
            <div v-if="currentStep <= totalSteps" class="w-full bg-stone-200 dark:bg-slate-800 h-1.5">
                <div
                    class="h-1.5 bg-amber-500 transition-all duration-300"
                    :style="{ width: `${progressPercentage}%` }"
                ></div>
            </div>
        </header>

        <!-- Main Form Container -->
        <main class="relative z-10 mx-auto max-w-2xl px-4 py-6 sm:px-6 sm:py-10">
            <!-- Step Indicators -->
            <div v-if="currentStep <= totalSteps" class="mb-6">
                <div class="flex items-center justify-between text-xs font-extrabold text-stone-600 dark:text-slate-400 mb-2">
                    <span class="inline-flex items-center gap-1.5 text-amber-700 dark:text-amber-400">
                        <span>المرحلة {{ currentStep }} من {{ totalSteps }}</span>
                        <span class="text-stone-300 dark:text-slate-700">·</span>
                        <span v-if="currentStep === 1">آلية الخدمة والدفع</span>
                        <span v-else-if="currentStep === 2">المطبخ والميزات</span>
                        <span v-else>بيانات التواصل</span>
                    </span>
                    <span class="text-stone-500 dark:text-slate-400 font-mono">{{ progressPercentage }}%</span>
                </div>

                <div class="grid grid-cols-3 gap-2">
                    <div
                        v-for="s in totalSteps"
                        :key="s"
                        class="h-1.5 rounded-full transition-all"
                        :class="[
                            s < currentStep
                                ? 'bg-amber-500'
                                : s === currentStep
                                ? 'bg-amber-500 animate-pulse'
                                : 'bg-stone-200 dark:bg-slate-800',
                        ]"
                    ></div>
                </div>
            </div>

            <!-- Error Banner -->
            <div
                v-if="errorMessage"
                class="mb-6 flex items-center gap-2.5 rounded-2xl border border-red-300 bg-red-100/90 p-4 text-sm font-bold text-red-900 dark:border-red-800 dark:bg-red-950/60 dark:text-red-200"
            >
                <span class="text-lg">⚠️</span>
                <span>{{ errorMessage }}</span>
            </div>

            <!-- ============================================================== -->
            <!-- STEP 1: SERVICE & CASHIER MODEL -->
            <!-- ============================================================== -->
            <div v-if="currentStep === 1" class="space-y-7">
                <div class="text-right">
                    <div
                        class="inline-flex items-center gap-1.5 rounded-full border border-amber-500/30 bg-amber-500/10 px-3 py-1 text-xs font-black text-amber-700 dark:text-amber-400 mb-2"
                    >
                        <span>⏱️ دقيقة واحدة فقط لتخصيص مطعمك</span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-black text-stone-900 dark:text-white tracking-tight">
                        كيف يستقبل مطعمك الزبائن والمحاسبة؟
                    </h1>
                    <p class="mt-1.5 text-xs sm:text-sm text-stone-600 dark:text-slate-400">
                        حدد طبيعة الخدمة ونمط الدفع لضبط تسلسل تحضير الطلبات ونوافذ الكاشير في نظامك.
                    </p>
                </div>

                <!-- 1. Service Type -->
                <div class="rounded-3xl border border-stone-200 bg-white p-5 sm:p-6 shadow-xs dark:border-slate-800 dark:bg-[#0a0f1d] space-y-4">
                    <label class="block text-sm font-bold text-stone-900 dark:text-white">
                        1. أسلوب تقديم الخدمة في مطعمك:
                    </label>
                    <div class="space-y-3">
                        <button
                            v-for="st in serviceTypeOptions"
                            :key="st.id"
                            type="button"
                            @click="form.service_type = st.id"
                            class="w-full flex items-start gap-3.5 rounded-2xl p-4 text-right border transition-all text-stone-900 dark:text-white"
                            :class="[
                                form.service_type === st.id
                                    ? 'border-amber-500 bg-amber-50/80 ring-2 ring-amber-500/30 dark:bg-amber-950/30 dark:border-amber-500'
                                    : 'border-stone-200 bg-stone-50/50 hover:border-amber-300 dark:border-slate-800 dark:bg-slate-900/60 dark:hover:border-slate-700',
                            ]"
                        >
                            <span class="text-2xl shrink-0 mt-0.5">{{ st.icon }}</span>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="font-extrabold text-sm text-stone-900 dark:text-white">{{ st.title }}</span>
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-md bg-stone-200/70 text-stone-700 dark:bg-slate-800 dark:text-slate-300">
                                        {{ st.badge }}
                                    </span>
                                </div>
                                <p class="text-xs text-stone-600 dark:text-slate-400 mt-1 leading-relaxed">
                                    {{ st.desc }}
                                </p>
                            </div>
                            <div
                                class="h-5 w-5 rounded-full border-2 flex items-center justify-center shrink-0 mt-1 transition-colors"
                                :class="[
                                    form.service_type === st.id
                                        ? 'border-amber-500 bg-amber-500 text-slate-950 font-black text-xs'
                                        : 'border-stone-400 dark:border-slate-600',
                                ]"
                            >
                                <span v-if="form.service_type === st.id">✓</span>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- 2. Payment Model -->
                <div class="rounded-3xl border border-stone-200 bg-white p-5 sm:p-6 shadow-xs dark:border-slate-800 dark:bg-[#0a0f1d] space-y-4">
                    <label class="block text-sm font-bold text-stone-900 dark:text-white">
                        2. آلية المحاسبة المعتمدة عند الكاشير:
                    </label>
                    <div class="space-y-3">
                        <button
                            v-for="pm in paymentModelOptions"
                            :key="pm.id"
                            type="button"
                            @click="form.payment_model = pm.id"
                            class="w-full flex items-start gap-3.5 rounded-2xl p-4 text-right border transition-all text-stone-900 dark:text-white"
                            :class="[
                                form.payment_model === pm.id
                                    ? 'border-amber-500 bg-amber-50/80 ring-2 ring-amber-500/30 dark:bg-amber-950/30 dark:border-amber-500'
                                    : 'border-stone-200 bg-stone-50/50 hover:border-amber-300 dark:border-slate-800 dark:bg-slate-900/60 dark:hover:border-slate-700',
                            ]"
                        >
                            <span class="text-2xl shrink-0 mt-0.5">{{ pm.icon }}</span>
                            <div class="flex-1 min-w-0">
                                <span class="font-extrabold text-sm text-stone-900 dark:text-white">{{ pm.title }}</span>
                                <p class="text-xs text-stone-600 dark:text-slate-400 mt-1 leading-relaxed">
                                    {{ pm.desc }}
                                </p>
                            </div>
                            <div
                                class="h-5 w-5 rounded-full border-2 flex items-center justify-center shrink-0 mt-1 transition-colors"
                                :class="[
                                    form.payment_model === pm.id
                                        ? 'border-amber-500 bg-amber-500 text-slate-950 font-black text-xs'
                                        : 'border-stone-400 dark:border-slate-600',
                                ]"
                            >
                                <span v-if="form.payment_model === pm.id">✓</span>
                            </div>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ============================================================== -->
            <!-- STEP 2: KITCHEN SETUP & SMART MODULES -->
            <!-- ============================================================== -->
            <div v-if="currentStep === 2" class="space-y-7">
                <div class="text-right">
                    <div
                        class="inline-flex items-center gap-1.5 rounded-full border border-amber-500/30 bg-amber-500/10 px-3 py-1 text-xs font-black text-amber-700 dark:text-amber-400 mb-2"
                    >
                        <span>🍳 تنظيم المطبخ والميزات</span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-black text-stone-900 dark:text-white tracking-tight">
                        توزيع شاشات المطبخ والميزات المطلوبة
                    </h1>
                    <p class="mt-1.5 text-xs sm:text-sm text-stone-600 dark:text-slate-400">
                        اختر كيف تفضل توجيه طلبات الطهاة، وحدد الميزات الذكية التي تود تفعيلها في حسابك.
                    </p>
                </div>

                <!-- 3. Kitchen Display Setup -->
                <div class="rounded-3xl border border-stone-200 bg-white p-5 sm:p-6 shadow-xs dark:border-slate-800 dark:bg-[#0a0f1d] space-y-4">
                    <label class="block text-sm font-bold text-stone-900 dark:text-white">
                        3. كيف تفضل توزيع الطلبات داخل المطبخ؟ (شاشات KDS):
                    </label>
                    <div class="space-y-3">
                        <button
                            v-for="ks in kitchenSetupOptions"
                            :key="ks.id"
                            type="button"
                            @click="form.kitchen_setup = ks.id"
                            class="w-full flex items-start gap-3.5 rounded-2xl p-4 text-right border transition-all text-stone-900 dark:text-white"
                            :class="[
                                form.kitchen_setup === ks.id
                                    ? 'border-amber-500 bg-amber-50/80 ring-2 ring-amber-500/30 dark:bg-amber-950/30 dark:border-amber-500'
                                    : 'border-stone-200 bg-stone-50/50 hover:border-amber-300 dark:border-slate-800 dark:bg-slate-900/60 dark:hover:border-slate-700',
                            ]"
                        >
                            <span class="text-2xl shrink-0 mt-0.5">{{ ks.icon }}</span>
                            <div class="flex-1 min-w-0">
                                <span class="font-extrabold text-sm text-stone-900 dark:text-white">{{ ks.title }}</span>
                                <p class="text-xs text-stone-600 dark:text-slate-400 mt-1 leading-relaxed">
                                    {{ ks.desc }}
                                </p>
                            </div>
                            <div
                                class="h-5 w-5 rounded-full border-2 flex items-center justify-center shrink-0 mt-1 transition-colors"
                                :class="[
                                    form.kitchen_setup === ks.id
                                        ? 'border-amber-500 bg-amber-500 text-slate-950 font-black text-xs'
                                        : 'border-stone-400 dark:border-slate-600',
                                ]"
                            >
                                <span v-if="form.kitchen_setup === ks.id">✓</span>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- 4. Desired Features (Multi-select) -->
                <div class="rounded-3xl border border-stone-200 bg-white p-5 sm:p-6 shadow-xs dark:border-slate-800 dark:bg-[#0a0f1d] space-y-4">
                    <div class="flex items-center justify-between">
                        <label class="block text-sm font-bold text-stone-900 dark:text-white">
                            4. ميزات "تدلّل" الذكية التي تود تفعيلها:
                        </label>
                        <span class="text-xs text-amber-700 dark:text-amber-400 font-bold">تحديد متعدد</span>
                    </div>
                    <div class="space-y-3">
                        <button
                            v-for="feat in desiredFeatureOptions"
                            :key="feat.id"
                            type="button"
                            @click="toggleFeature(feat.id)"
                            class="w-full flex items-start gap-3.5 rounded-2xl p-4 text-right border transition-all text-stone-900 dark:text-white"
                            :class="[
                                form.desired_features.includes(feat.id)
                                    ? 'border-amber-500 bg-amber-50/80 ring-2 ring-amber-500/30 dark:bg-amber-950/30 dark:border-amber-500'
                                    : 'border-stone-200 bg-stone-50/50 hover:border-amber-300 dark:border-slate-800 dark:bg-slate-900/60 dark:hover:border-slate-700',
                            ]"
                        >
                            <span class="text-2xl shrink-0 mt-0.5">{{ feat.icon }}</span>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="font-extrabold text-sm text-stone-900 dark:text-white">{{ feat.title }}</span>
                                    <span class="text-[10px] font-black px-2 py-0.5 rounded-md bg-amber-500/20 text-amber-800 dark:text-amber-300">
                                        {{ feat.highlight }}
                                    </span>
                                </div>
                                <p class="text-xs text-stone-600 dark:text-slate-400 mt-1 leading-relaxed">
                                    {{ feat.desc }}
                                </p>
                            </div>
                            <div
                                class="h-5 w-5 rounded-md border-2 flex items-center justify-center shrink-0 mt-1 transition-colors"
                                :class="[
                                    form.desired_features.includes(feat.id)
                                        ? 'border-amber-500 bg-amber-500 text-slate-950 font-black text-xs'
                                        : 'border-stone-400 dark:border-slate-600',
                                ]"
                            >
                                <span v-if="form.desired_features.includes(feat.id)">✓</span>
                            </div>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ============================================================== -->
            <!-- STEP 3: CONTACT INFO & SUBMISSION -->
            <!-- ============================================================== -->
            <div v-if="currentStep === 3" class="space-y-7">
                <div class="text-right">
                    <div
                        class="inline-flex items-center gap-1.5 rounded-full border border-amber-500/30 bg-amber-500/10 px-3 py-1 text-xs font-black text-amber-700 dark:text-amber-400 mb-2"
                    >
                        <span>📝 الخطوة الأخيرة • بيانات التواصل</span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-black text-stone-900 dark:text-white tracking-tight">
                        أين نرسل لك دراسة النظام والعرض التوضيحي؟
                    </h1>
                    <p class="mt-1.5 text-xs sm:text-sm text-stone-600 dark:text-slate-400">
                        سيتواصل معك المستشار التقني بالباقة التوصية لمطعمك وتجهيز النسخة التجريبية فوراً.
                    </p>
                </div>

                <div class="rounded-3xl border border-stone-200 bg-white p-5 sm:p-7 shadow-xs dark:border-slate-800 dark:bg-[#0a0f1d] space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-stone-800 dark:text-slate-200 mb-1.5">
                                اسمك الكريم / المسؤول: <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="form.name"
                                type="text"
                                placeholder="مثال: عمر خطيب"
                                class="w-full rounded-xl border border-stone-300 bg-stone-50 px-3.5 py-3 text-sm font-bold text-stone-900 placeholder:text-stone-400 focus:border-amber-500 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-amber-500/20 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-500"
                                required
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-stone-800 dark:text-slate-200 mb-1.5">
                                اسم المطعم / العلامة التجارية: <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="form.restaurant_name"
                                type="text"
                                placeholder="مثال: مطعم بيت حلب"
                                class="w-full rounded-xl border border-stone-300 bg-stone-50 px-3.5 py-3 text-sm font-bold text-stone-900 placeholder:text-stone-400 focus:border-amber-500 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-amber-500/20 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-500"
                                required
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-stone-800 dark:text-slate-200 mb-1.5">
                                رقم الواتساب / الهاتف: <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="form.phone"
                                type="tel"
                                placeholder="مثال: +963 991 234 567"
                                dir="ltr"
                                class="w-full rounded-xl border border-stone-300 bg-stone-50 px-3.5 py-3 text-sm font-bold text-stone-900 placeholder:text-stone-400 focus:border-amber-500 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-amber-500/20 dark:border-slate-700 dark:bg-slate-900 dark:text-white text-right dark:placeholder:text-slate-500"
                                required
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-stone-800 dark:text-slate-200 mb-1.5">
                                المدينة / الدولة (اختياري):
                            </label>
                            <input
                                v-model="form.city"
                                type="text"
                                placeholder="مثال: حلب، سوريا / الرياض، السعودية"
                                class="w-full rounded-xl border border-stone-300 bg-stone-50 px-3.5 py-3 text-sm font-bold text-stone-900 placeholder:text-stone-400 focus:border-amber-500 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-amber-500/20 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-500"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-stone-800 dark:text-slate-200 mb-1.5">
                            ملاحظة أو استفسار خاص تود إضافته (اختياري):
                        </label>
                        <textarea
                            v-model="form.notes"
                            rows="2"
                            placeholder="أي تفاصيل ترغب بإخبارنا بها..."
                            class="w-full rounded-xl border border-stone-300 bg-stone-50 p-3 text-sm font-medium text-stone-900 placeholder:text-stone-400 focus:border-amber-500 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-amber-500/20 dark:border-slate-700 dark:bg-slate-900 dark:text-white resize-none dark:placeholder:text-slate-500"
                        ></textarea>
                    </div>
                </div>
            </div>

            <!-- ============================================================== -->
            <!-- ACTION BUTTONS: CLEAR, HIGH CONTRAST IN LIGHT & DARK MODE -->
            <!-- ============================================================== -->
            <div v-if="currentStep <= totalSteps" class="mt-8 flex items-center justify-between gap-4">
                <!-- Previous Button -->
                <button
                    v-if="currentStep > 1"
                    type="button"
                    @click="prevStep"
                    class="inline-flex items-center gap-2 rounded-2xl border-2 border-stone-300 bg-white px-5 py-3.5 text-sm font-extrabold text-stone-800 shadow-sm transition hover:bg-stone-100 hover:border-stone-400 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 cursor-pointer"
                >
                    <span>السابق</span>
                    <span class="text-base">→</span>
                </button>
                <div v-else></div>

                <!-- Next Button (Step 1 & 2) -->
                <button
                    v-if="currentStep < totalSteps"
                    type="button"
                    @click="nextStep"
                    class="inline-flex items-center gap-2.5 rounded-2xl bg-amber-500 hover:bg-amber-600 active:scale-95 px-8 py-3.5 text-sm font-black text-slate-950 shadow-lg shadow-amber-500/30 transition-all cursor-pointer border border-amber-400"
                >
                    <span>متابعة الخطوة التالية</span>
                    <span class="text-base">←</span>
                </button>

                <!-- Final Submit Button (Step 3) -->
                <button
                    v-else
                    type="button"
                    @click="submitConsultation"
                    :disabled="isSubmitting"
                    class="inline-flex items-center gap-2.5 rounded-2xl bg-emerald-600 hover:bg-emerald-700 active:scale-95 px-8 py-3.5 text-sm font-black text-white shadow-lg shadow-emerald-600/35 transition-all cursor-pointer border border-emerald-500 disabled:opacity-50"
                >
                    <span v-if="isSubmitting" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"></span>
                    <span class="text-base">🚀</span>
                    <span>{{ isSubmitting ? 'جاري تحليل المتطلبات وإرسال التقرير...' : 'إرسال وتحليل احتياجات مطعمي' }}</span>
                </button>
            </div>

            <!-- ============================================================== -->
            <!-- STEP 4: SUCCESS & TAILORED BLUEPRINT SCREEN -->
            <!-- ============================================================== -->
            <div v-if="currentStep === 4 && resultBlueprint" class="space-y-6">
                <!-- Success Header Card -->
                <div class="rounded-3xl border border-emerald-500/40 bg-emerald-500/10 p-6 sm:p-8 text-center relative overflow-hidden dark:bg-emerald-950/20">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-500 text-white text-2xl shadow-lg shadow-emerald-500/30 mb-3">
                        ✓
                    </div>
                    <span class="inline-block rounded-full bg-emerald-500/20 px-3.5 py-1 text-xs font-black text-emerald-800 dark:text-emerald-300 mb-2">
                        تم استلام متطلبات مطعمك بنجاح
                    </span>
                    <h2 class="text-2xl sm:text-3xl font-black text-stone-900 dark:text-white">
                        أهلاً بك أستاذ {{ resultBlueprint.client_name }} في تدلّل!
                    </h2>
                    <p class="mt-2 text-xs sm:text-sm text-stone-700 dark:text-slate-300 max-w-lg mx-auto leading-relaxed">
                        تم تحليل متطلبات مطعم <strong class="text-emerald-700 dark:text-emerald-400 font-black">({{ resultBlueprint.restaurant_name }})</strong>، وإليك ملخص الخطة التشغيلية الموصى بها.
                    </p>
                </div>

                <!-- ROI Projection Badges -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                    <div class="rounded-2xl border border-stone-200 bg-white p-4 text-center dark:border-slate-800 dark:bg-[#0a0f1d] shadow-xs">
                        <div class="text-2xl mb-1">⚡</div>
                        <div class="text-lg font-black text-amber-600 dark:text-amber-400">
                            {{ resultBlueprint.blueprint?.estimated_turnover_boost }}
                        </div>
                        <div class="text-[11px] font-semibold text-stone-500 dark:text-slate-400 mt-0.5">
                            سرعة تجهيز ودوران طاولات
                        </div>
                    </div>

                    <div class="rounded-2xl border border-stone-200 bg-white p-4 text-center dark:border-slate-800 dark:bg-[#0a0f1d] shadow-xs">
                        <div class="text-2xl mb-1">🎯</div>
                        <div class="text-lg font-black text-emerald-600 dark:text-emerald-400">
                            {{ resultBlueprint.blueprint?.estimated_error_reduction }}
                        </div>
                        <div class="text-[11px] font-semibold text-stone-500 dark:text-slate-400 mt-0.5">
                            صفر أخطاء وفواتير ضائعة
                        </div>
                    </div>

                    <div class="rounded-2xl border border-stone-200 bg-white p-4 text-center dark:border-slate-800 dark:bg-[#0a0f1d] shadow-xs">
                        <div class="text-2xl mb-1">🤖</div>
                        <div class="text-lg font-black text-blue-600 dark:text-blue-400">
                            +18% إلى +25%
                        </div>
                        <div class="text-[11px] font-semibold text-stone-500 dark:text-slate-400 mt-0.5">
                            مبيعات إضافية باقتراحات الويتر الذكي
                        </div>
                    </div>
                </div>

                <!-- Tailored Blueprint Details -->
                <div class="rounded-3xl border border-stone-200 bg-white p-5 sm:p-7 shadow-xs dark:border-slate-800 dark:bg-[#0a0f1d]">
                    <div class="flex items-center gap-2 mb-4 border-b border-stone-100 pb-3 dark:border-slate-800">
                        <span class="text-xl">📋</span>
                        <h3 class="text-sm sm:text-base font-black text-stone-900 dark:text-white">
                            البنية التقنية الموصى بها لمطعمك:
                        </h3>
                    </div>

                    <div class="space-y-2.5">
                        <div
                            v-for="(hl, idx) in resultBlueprint.blueprint?.highlights"
                            :key="idx"
                            class="flex items-start gap-3 rounded-xl bg-stone-50 p-3 text-xs sm:text-sm font-bold text-stone-800 dark:bg-slate-900/60 dark:text-slate-200"
                        >
                            <span class="text-emerald-600 dark:text-emerald-400 font-black shrink-0 mt-0.5">✔</span>
                            <span>{{ hl }}</span>
                        </div>
                    </div>

                    <!-- Next Step Info -->
                    <div class="mt-5 rounded-2xl bg-amber-500/10 border border-amber-500/25 p-4 text-xs sm:text-sm text-stone-800 dark:text-slate-200 leading-relaxed font-medium">
                        ℹ️ <strong>الخطوة التالية:</strong> تم إشعار المستشار التقني المهندس <strong>عمر خطيب</strong> بالمتطلبات كاملة وسيقوم بالتواصل معك عبر الواتساب على رقمك <strong>({{ form.phone }})</strong> خلال وقت قصير لترتيب العرض التوضيحي الحي وتجهيز القائمة التجريبية الخاصة بك.
                    </div>

                    <!-- Action Buttons -->
                    <div class="mt-6 flex flex-col sm:flex-row items-center gap-3">
                        <a
                            :href="directWhatsappUrl"
                            target="_blank"
                            class="w-full sm:w-auto flex-1 inline-flex items-center justify-center gap-2 rounded-2xl bg-[#25d366] hover:bg-[#20ba59] active:scale-95 px-6 py-3.5 text-sm font-black text-white shadow-lg shadow-[#25d366]/25 transition-all"
                        >
                            <span>💬 التواصل الفوري عبر واتساب للبدء</span>
                        </a>
                        <Link
                            :href="route('welcome')"
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-1.5 rounded-2xl border-2 border-stone-300 bg-white px-6 py-3.5 text-sm font-bold text-stone-800 hover:bg-stone-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                        >
                            <span>الرئيسية</span>
                        </Link>
                    </div>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="mt-12 border-t border-stone-200/80 py-6 text-center text-xs text-stone-500 dark:border-slate-800 dark:text-slate-400">
            <p>جميع الحقوق محفوظة © {{ new Date().getFullYear() }} تدلّل (Tedallal). منصة إدارة المطاعم الذكية.</p>
        </footer>
    </div>
</template>
