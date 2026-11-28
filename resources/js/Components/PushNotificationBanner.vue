<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { usePwa } from '@/composables/usePwa';

const page = usePage<any>();
const {
    isIOS,
    isStandalone,
    isPushSupported,
    pushPermission,
    isPushSubscribed,
    isPushLoading,
    pushError,
    refreshPushStatus,
    subscribeToPush,
    unsubscribeFromPush,
} = usePwa();

function readPushBannerCollapsed(): boolean {
    try {
        return typeof window !== 'undefined' && localStorage.getItem('tedallal_dismiss_push_banner') === 'true';
    } catch {
        return false;
    }
}

const collapsed = ref(readPushBannerCollapsed());
const testFeedback = ref<{ type: 'success' | 'error'; text: string } | null>(null);
const isTesting = ref(false);

const hasVapidKey = computed(() => Boolean(page.props.vapidPublicKey));
const isPushActive = computed(() => isPushSubscribed.value && pushPermission.value === 'granted');

onMounted(() => {
    collapsed.value = readPushBannerCollapsed();
});

function collapseBanner() {
    collapsed.value = true;
    try {
        localStorage.setItem('tedallal_dismiss_push_banner', 'true');
    } catch {}
}

async function handleEnablePush() {
    testFeedback.value = null;
    const vapidKey = page.props.vapidPublicKey || '';
    const success = await subscribeToPush(vapidKey);
    if (success) {
        collapsed.value = false;
        try {
            localStorage.removeItem('tedallal_dismiss_push_banner');
        } catch {}
    } else {
        collapsed.value = false;
    }
}

async function handleDisablePush() {
    testFeedback.value = null;
    await unsubscribeFromPush();
}

async function sendTestNotification() {
    isTesting.value = true;
    testFeedback.value = null;
    try {
        const res = await fetch('/api/push/test', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
            },
        });
        if (res.ok) {
            testFeedback.value = {
                type: 'success',
                text: 'تم إرسال إشعار تجريبي! راقب إشعارات جهازك الآن.',
            };
        } else {
            testFeedback.value = {
                type: 'error',
                text: 'تعذر إرسال الإشعار التجريبي، أعد المحاولة.',
            };
        }
    } catch {
        testFeedback.value = {
            type: 'error',
            text: 'حدث خطأ في الاتصال أثناء تجربة الإشعار.',
        };
    } finally {
        isTesting.value = false;
        setTimeout(() => {
            testFeedback.value = null;
        }, 5000);
    }
}
</script>

<template>
    <div dir="rtl" class="font-cairo space-y-2">
        <!-- حالة 1: مفعّلة بوضوح (شريط حالة مضغوط ودائم مع أزرار تجربة وتعطيل) -->
        <div
            v-if="isPushActive"
            class="flex flex-wrap items-center justify-between gap-2.5 rounded-2xl border border-emerald-200 dark:border-emerald-700/50 bg-emerald-50/90 dark:bg-emerald-950/30 px-3.5 py-2 text-xs shadow-2xs"
        >
            <div class="flex items-center gap-2.5 min-w-0">
                <span class="relative flex h-2.5 w-2.5 shrink-0">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                </span>
                <span class="font-extrabold text-emerald-900 dark:text-emerald-200">
                    التنبيهات الفورية: مفعّلة على هذا الجهاز
                </span>
                <span class="hidden sm:inline text-emerald-700/80 dark:text-emerald-300/75">
                    — ستصلك إشعارات الطلبات والحجوزات حتى عند إغلاق الشاشة
                </span>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <button
                    type="button"
                    @click="sendTestNotification"
                    :disabled="isTesting"
                    class="inline-flex items-center gap-1 rounded-xl border border-emerald-300 dark:border-emerald-600/60 bg-white dark:bg-emerald-900/40 px-2.5 py-1 text-[11px] font-bold text-emerald-800 dark:text-emerald-200 hover:bg-emerald-100 dark:hover:bg-emerald-900/70 transition disabled:opacity-50"
                >
                    <span>{{ isTesting ? '⏳ جارِ الإرسال...' : '🔔 تجربة إشعار' }}</span>
                </button>
                <button
                    type="button"
                    @click="handleDisablePush"
                    :disabled="isPushLoading"
                    class="inline-flex items-center gap-1 rounded-xl border border-slate-200 dark:border-slate-700 bg-white/80 dark:bg-slate-900/70 px-2.5 py-1 text-[11px] font-bold text-slate-600 dark:text-slate-300 hover:text-rose-600 dark:hover:text-rose-400 hover:border-rose-300 transition disabled:opacity-50"
                >
                    <span>{{ isPushLoading ? '...' : 'تعطيل' }}</span>
                </button>
            </div>
        </div>

        <!-- حالة 2: محجوبة من المتصفح -->
        <div
            v-else-if="pushPermission === 'denied'"
            class="flex flex-wrap items-center justify-between gap-2.5 rounded-2xl border border-rose-200 dark:border-rose-800/60 bg-rose-50/90 dark:bg-rose-950/35 px-3.5 py-2.5 text-xs shadow-2xs"
        >
            <div class="flex items-center gap-2.5">
                <span class="h-2.5 w-2.5 rounded-full bg-rose-500 shrink-0"></span>
                <div>
                    <span class="font-extrabold text-rose-900 dark:text-rose-200">
                        التنبيهات الفورية: محجوبة من المتصفح
                    </span>
                    <span class="block sm:inline sm:ms-1.5 text-rose-700/90 dark:text-rose-300/80">
                        — اسمح بالإشعارات من أيقونة القفل بجانب رابط الموقع ثم اضغط «تحديث الحالة».
                    </span>
                </div>
            </div>
            <button
                type="button"
                @click="refreshPushStatus"
                class="rounded-xl border border-rose-300 dark:border-rose-700 bg-white dark:bg-rose-900/40 px-3 py-1 text-[11px] font-bold text-rose-800 dark:text-rose-200 hover:bg-rose-100 transition shrink-0"
            >
                تحديث الحالة
            </button>
        </div>

        <!-- حالة 3: غير مدعومة على هذا الجهاز/المتصفح -->
        <div
            v-else-if="!isPushSupported || !hasVapidKey"
            class="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50/90 dark:bg-slate-900/60 px-3.5 py-2 text-xs text-slate-600 dark:text-slate-300"
        >
            <div class="flex items-center gap-2">
                <span class="h-2.5 w-2.5 rounded-full bg-slate-400 shrink-0"></span>
                <span class="font-bold">التنبيهات الفورية: غير متاحة حالياً</span>
                <span v-if="isIOS && !isStandalone" class="text-slate-500 dark:text-slate-400">
                    — على آيفون/آيباد أضف التطبيق للشاشة الرئيسية أولاً لتعمل الإشعارات.
                </span>
                <span v-else-if="!isPushSupported" class="text-slate-500 dark:text-slate-400">
                    — المتصفح الحالي لا يدعم إشعارات الخلفية.
                </span>
                <span v-else class="text-slate-500 dark:text-slate-400">
                    — بانتظار إعداد مفتاح الإشعارات على الخادم.
                </span>
            </div>
        </div>

        <!-- حالة 4أ: غير مفعّلة (مصغّرة بعد النقر على تصغير — تظل واضحة الحالة مع زر تفعيل مباشر) -->
        <div
            v-else-if="collapsed"
            class="flex flex-wrap items-center justify-between gap-2.5 rounded-2xl border border-amber-200/80 dark:border-amber-700/40 bg-amber-50/75 dark:bg-amber-950/20 px-3.5 py-2 text-xs shadow-2xs"
        >
            <div class="flex items-center gap-2.5">
                <span class="h-2.5 w-2.5 rounded-full bg-slate-400 dark:bg-slate-500 shrink-0"></span>
                <span class="font-extrabold text-stone-800 dark:text-amber-200">
                    التنبيهات الفورية: غير مفعّلة
                </span>
                <span class="hidden sm:inline text-stone-500 dark:text-slate-400">
                    — قد تفوتك إشعارات الطلبات إذا كان الجهاز مقفلاً
                </span>
            </div>
            <button
                type="button"
                @click="handleEnablePush"
                :disabled="isPushLoading"
                class="inline-flex items-center gap-1.5 rounded-xl bg-amber-500 hover:bg-amber-400 px-3 py-1 text-[11px] font-black text-stone-950 transition shadow-2xs disabled:opacity-50"
            >
                <span v-if="isPushLoading" class="animate-spin">⏳</span>
                <span>{{ isPushLoading ? 'جارِ التفعيل...' : '🔔 تفعيل التنبيهات الآن' }}</span>
            </button>
        </div>

        <!-- حالة 4ب: غير مفعّلة (البطاقة الكاملة الواضحة متوافقة مع الوضع الفاتح والداكن) -->
        <div
            v-else
            class="rounded-2xl border border-amber-300/80 dark:border-amber-500/35 bg-gradient-to-l from-amber-50 via-white to-amber-50/60 dark:from-amber-950/40 dark:via-[#0e1726] dark:to-[#0c1222] p-4 shadow-sm"
        >
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                <div class="flex items-start sm:items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-500/15 dark:bg-amber-500/20 text-amber-600 dark:text-amber-400 border border-amber-400/40 dark:border-amber-500/30">
                        <span class="text-lg animate-pulse">🔔</span>
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-sm font-black text-stone-900 dark:text-amber-300">
                                تنبيهات الطلبات الفورية
                            </p>
                            <span class="inline-flex items-center gap-1 rounded-full bg-slate-200/80 dark:bg-slate-800 px-2 py-0.5 text-[11px] font-bold text-slate-700 dark:text-slate-300">
                                <span class="h-1.5 w-1.5 rounded-full bg-slate-500"></span>
                                <span>غير مفعّلة</span>
                            </span>
                        </div>
                        <p class="text-xs text-stone-600 dark:text-zinc-400 mt-1">
                            ليصلك رنين جرس واهتزاز فور ورود أي طلب جديد أو حجز طاولة حتى عند قفل الهاتف.
                        </p>
                        <p v-if="pushError" class="mt-1.5 text-xs font-bold text-rose-600 dark:text-rose-400">
                            ⚠️ {{ pushError }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2 self-end sm:self-center w-full sm:w-auto shrink-0">
                    <button
                        type="button"
                        @click="handleEnablePush"
                        :disabled="isPushLoading"
                        class="flex-1 sm:flex-none flex items-center justify-center gap-1.5 px-4 py-2 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-zinc-950 text-xs font-black transition shadow-sm disabled:opacity-50"
                    >
                        <span v-if="isPushLoading" class="animate-spin text-xs">⏳</span>
                        <span>{{ isPushLoading ? 'جارِ التفعيل...' : '🔔 تفعيل التنبيهات الآن' }}</span>
                    </button>
                    <button
                        type="button"
                        @click="collapseBanner"
                        class="px-3 py-2 rounded-xl text-xs font-bold text-stone-600 dark:text-zinc-400 hover:text-stone-900 dark:hover:text-white hover:bg-stone-200/60 dark:hover:bg-white/5 transition"
                    >
                        تصغير
                    </button>
                </div>
            </div>
        </div>

        <!-- تغذية راجعة لنتيجة الإشعار التجريبي -->
        <div
            v-if="testFeedback"
            :class="[
                'rounded-xl border px-3.5 py-2 text-xs font-bold flex items-center justify-between',
                testFeedback.type === 'success'
                    ? 'border-emerald-300 dark:border-emerald-500/30 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-300'
                    : 'border-rose-300 dark:border-rose-500/30 bg-rose-50 dark:bg-rose-950/40 text-rose-800 dark:text-rose-300',
            ]"
        >
            <span>{{ testFeedback.type === 'success' ? '✅' : '⚠️' }} {{ testFeedback.text }}</span>
        </div>
    </div>
</template>
