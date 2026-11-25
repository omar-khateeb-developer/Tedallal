<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import axios from 'axios';
import { normalizeCurrency } from '@/utils/money';

interface RestaurantSummary {
    id: number;
    name: string;
    currency?: string | null;
}

const props = defineProps<{
    restaurants: RestaurantSummary[];
}>();

const promoTestingMode = ref(false);
const promoTestingLoading = ref(false);
const promoTestingLoaded = ref(false);
const promoTestingMessage = ref('');
const promoTestingSuccess = ref(false);

const diagCode = ref('');
const diagRestaurantId = ref<number | null>(null);
const diagCartTotal = ref<number>(100);
const diagLoading = ref(false);
const diagResult = ref<any>(null);
const diagActionLoading = ref(false);

watch(
    () => props.restaurants,
    (list) => {
        if (list.length > 0 && !diagRestaurantId.value) {
            diagRestaurantId.value = list[0].id;
        }
    },
    { immediate: true },
);

const fetchPromoTestingStatus = async () => {
    try {
        const response = await axios.get('/api/super-admin/promo-testing');
        promoTestingMode.value = response.data.data?.testing_mode ?? false;
        promoTestingLoaded.value = true;
    } catch (e) {
        console.error('Failed to fetch promo testing status', e);
    }
};

const togglePromoTesting = async () => {
    promoTestingLoading.value = true;
    promoTestingMessage.value = '';
    try {
        const response = await axios.post('/api/super-admin/promo-testing/toggle', {
            enabled: !promoTestingMode.value,
        });
        promoTestingMode.value = response.data.data?.testing_mode ?? false;
        promoTestingMessage.value = response.data.data?.message ?? '';
        promoTestingSuccess.value = true;
        setTimeout(() => {
            promoTestingMessage.value = '';
        }, 5000);
    } catch (e: any) {
        promoTestingMessage.value = e.response?.data?.message || 'حدث خطأ أثناء تغيير حالة التجريب';
        promoTestingSuccess.value = false;
    } finally {
        promoTestingLoading.value = false;
    }
};

const runDiagnosis = async () => {
    if (!diagCode.value.trim() || !diagRestaurantId.value) return;
    diagLoading.value = true;
    diagResult.value = null;
    try {
        const response = await axios.post('/api/super-admin/promo-testing/diagnose', {
            code: diagCode.value.trim(),
            restaurant_id: diagRestaurantId.value,
            cart_total: diagCartTotal.value,
        });
        diagResult.value = response.data.data;
    } catch (e: any) {
        alert(e.response?.data?.message || 'فشل تشخيص الكوبون');
    } finally {
        diagLoading.value = false;
    }
};

const forceActivateFromDashboard = async () => {
    if (!diagCode.value.trim() || !diagRestaurantId.value) return;
    diagActionLoading.value = true;
    try {
        const response = await axios.post('/api/super-admin/promo-testing/force-activate', {
            code: diagCode.value.trim(),
            restaurant_id: diagRestaurantId.value,
        });
        alert(response.data.data?.message || 'تم التفعيل الفوري');
        await runDiagnosis();
    } catch (e: any) {
        alert(e.response?.data?.message || 'فشل التفعيل الفوري');
    } finally {
        diagActionLoading.value = false;
    }
};

onMounted(() => {
    fetchPromoTestingStatus();
});
</script>

<template>
    <div class="bg-white dark:bg-[#0a0f1d] overflow-hidden shadow-sm sm:rounded-2xl border border-stone-200 dark:border-slate-800">
        <div class="p-6" dir="rtl">
            <div class="flex items-center justify-between flex-wrap gap-4 border-b border-stone-100 dark:border-slate-800 pb-5">
                <div class="flex items-center gap-4">
                    <div
                        class="p-3.5 rounded-2xl"
                        :class="promoTestingMode ? 'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300' : 'bg-stone-100 text-stone-600 dark:bg-slate-800 dark:text-slate-400'"
                    >
                        <span class="text-2xl">🧪</span>
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-900 dark:text-white text-base flex items-center gap-2">
                            <span>وضع التجريب وفحص الكوبونات</span>
                            <span class="text-xs font-normal text-stone-500">| Testing & Diagnostics Mode</span>
                        </h3>
                        <div class="flex items-center gap-2 mt-1">
                            <span
                                class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold"
                                :class="promoTestingMode
                                    ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300'
                                    : 'bg-stone-100 text-stone-600 dark:bg-slate-800 dark:text-slate-400'"
                            >
                                <span
                                    class="inline-block w-2 h-2 rounded-full"
                                    :class="promoTestingMode ? 'bg-amber-500 animate-pulse' : 'bg-stone-400'"
                                ></span>
                                <template v-if="promoTestingLoaded">
                                    {{ promoTestingMode ? 'وضع التجريب مفعل (يتم إظهار أسباب الرفض بالتفصيل للزبائن مع إمكانية التفعيل الفوري)' : 'وضع التجريب معطل (تعمل الرسائل بنظام الإنتاج العادي)' }}
                                </template>
                                <template v-else>جاري التحقق...</template>
                            </span>
                        </div>
                        <p
                            v-if="promoTestingMessage"
                            class="text-xs mt-1.5 font-bold"
                            :class="promoTestingSuccess ? 'text-emerald-600' : 'text-rose-600'"
                        >{{ promoTestingMessage }}</p>
                    </div>
                </div>
                <button
                    @click="togglePromoTesting"
                    :disabled="promoTestingLoading || !promoTestingLoaded"
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-bold text-sm transition-all duration-200 shadow-sm focus:ring-2 focus:ring-offset-2 disabled:opacity-50"
                    :class="promoTestingMode ? 'bg-rose-600 hover:bg-rose-700 text-white' : 'bg-amber-600 hover:bg-amber-700 text-white'"
                >
                    <span v-if="promoTestingLoading">جاري التحديث...</span>
                    <span v-else-if="promoTestingMode">إيقاف وضع التجريب ⏹️</span>
                    <span v-else>تشغيل وضع التجريب 🧪</span>
                </button>
            </div>

            <div class="mt-5 pt-2">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="text-sm font-bold text-gray-800 dark:text-slate-200 flex items-center gap-2">
                        <span>🔍 أداة فحص وتشخيص أي كوبون مباشر</span>
                        <span class="text-xs text-stone-500 font-normal">(أدخل أي كود وافحص حالته بالتفصيل)</span>
                    </h4>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
                    <div class="sm:col-span-4">
                        <label class="block text-xs font-semibold text-stone-600 dark:text-slate-400 mb-1">المطعم:</label>
                        <select
                            v-model="diagRestaurantId"
                            class="w-full text-xs font-bold rounded-xl border-stone-200 dark:border-slate-700 bg-stone-50 dark:bg-slate-900 text-stone-900 dark:text-white focus:border-amber-500 focus:ring-amber-500"
                        >
                            <option v-for="r in restaurants" :key="r.id" :value="r.id">
                                {{ r.name }} ({{ normalizeCurrency(r.currency) }})
                            </option>
                        </select>
                    </div>
                    <div class="sm:col-span-4">
                        <label class="block text-xs font-semibold text-stone-600 dark:text-slate-400 mb-1">كود الكوبون:</label>
                        <input
                            v-model="diagCode"
                            type="text"
                            placeholder="مثال: LUCK-9KR7ZU أو WELCOME20"
                            class="w-full text-xs font-bold uppercase rounded-xl border-stone-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-stone-900 dark:text-white placeholder:text-stone-400 focus:border-amber-500 focus:ring-amber-500"
                            dir="ltr"
                        />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold text-stone-600 dark:text-slate-400 mb-1">قيمة سلة افتراضية:</label>
                        <input
                            v-model.number="diagCartTotal"
                            type="number"
                            min="0"
                            class="w-full text-xs font-bold rounded-xl border-stone-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-stone-900 dark:text-white focus:border-amber-500 focus:ring-amber-500"
                        />
                    </div>
                    <div class="sm:col-span-2">
                        <button
                            @click="runDiagnosis"
                            :disabled="diagLoading || !diagCode.trim() || !diagRestaurantId"
                            class="w-full py-2.5 rounded-xl bg-stone-900 dark:bg-slate-800 text-white font-bold text-xs hover:bg-stone-800 dark:hover:bg-slate-700 transition disabled:opacity-50"
                        >
                            {{ diagLoading ? 'جاري الفحص...' : 'فحص الكوبون 🔍' }}
                        </button>
                    </div>
                </div>

                <div v-if="diagResult" class="mt-4 p-4 rounded-xl border text-xs" :class="diagResult.diagnostics.valid ? 'bg-emerald-50 dark:bg-emerald-950/30 border-emerald-200 dark:border-emerald-800' : 'bg-amber-50 dark:bg-amber-950/30 border-amber-200 dark:border-amber-800'">
                    <div class="flex items-start justify-between flex-wrap gap-2 mb-2">
                        <div class="flex items-center gap-2">
                            <span class="text-base">{{ diagResult.diagnostics.valid ? '✅' : '⚠️' }}</span>
                            <span class="font-bold text-sm" :class="diagResult.diagnostics.valid ? 'text-emerald-800 dark:text-emerald-300' : 'text-amber-800 dark:text-amber-300'">
                                {{ diagResult.diagnostics.valid ? 'الكوبون صالح ومقبول' : 'الكوبون مرفوض / غير قابل للتطبيق حالياً' }}
                            </span>
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-[11px] font-bold" :class="diagResult.diagnostics.valid ? 'bg-emerald-200 text-emerald-900' : 'bg-rose-200 text-rose-900'">
                            رمز الحالة: {{ diagResult.diagnostics.reason_code }}
                        </span>
                    </div>

                    <p class="text-stone-800 dark:text-slate-200 font-bold mb-3 leading-relaxed">
                        📢 السبب: {{ diagResult.diagnostics.reason_message }}
                    </p>

                    <div v-if="diagResult.promo" class="grid grid-cols-2 sm:grid-cols-4 gap-2 p-2.5 rounded-lg bg-white/70 dark:bg-slate-900/60 border border-stone-200/60 dark:border-slate-800/80 mb-3 text-[11px]">
                        <div><span class="text-stone-500">النوع والقيمة:</span> <strong class="text-stone-900 dark:text-white">{{ diagResult.promo.discount_type === 'percentage' ? '%' : normalizeCurrency(diagResult.restaurant.currency) }} {{ diagResult.promo.discount_value }}</strong></div>
                        <div><span class="text-stone-500">الحد الأدنى للطلب:</span> <strong class="text-stone-900 dark:text-white">{{ diagResult.promo.min_order_value }} {{ normalizeCurrency(diagResult.restaurant.currency) }}</strong></div>
                        <div><span class="text-stone-500">بداية الصلاحية:</span> <strong class="text-stone-900 dark:text-white">{{ diagResult.promo.starts_at || 'فوري' }}</strong></div>
                        <div><span class="text-stone-500">نهاية الصلاحية:</span> <strong class="text-stone-900 dark:text-white">{{ diagResult.promo.expires_at || 'غير محدد' }}</strong></div>
                    </div>

                    <div class="flex items-center justify-between flex-wrap gap-2 pt-2 border-t border-stone-200/60 dark:border-slate-800/80">
                        <div class="flex items-center gap-2">
                            <button
                                v-if="diagResult.diagnostics.can_force_activate || (diagResult.promo && !diagResult.promo.is_active)"
                                type="button"
                                @click="forceActivateFromDashboard"
                                :disabled="diagActionLoading"
                                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs transition shadow-xs disabled:opacity-50"
                            >
                                <span>⚡</span>
                                <span>{{ diagActionLoading ? 'جاري التفعيل...' : 'تفعيل الكوبون للبدء فوراً الآن' }}</span>
                            </button>
                        </div>
                        <a
                            v-if="diagResult.test_url"
                            :href="diagResult.test_url"
                            target="_blank"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-stone-800 hover:bg-stone-900 text-white font-bold text-xs transition"
                        >
                            <span>🛒 فتح شاشة طلب وسلة تجريبية في هذا المطعم</span>
                            <span>↗</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
