<script setup lang="ts">
import { ref, computed } from 'vue';
import axios from 'axios';

const props = defineProps<{
    open: boolean;
    subtotal: string;
    currency: string;
    itemCount: number;
    loading: boolean;
    isPrepaid?: boolean;
}>();

const customerNote = defineModel<string>('customerNote', { default: '' });
const serviceType = defineModel<string>('serviceType', { default: 'dine_in' });
const promoCode = defineModel<string>('promoCode', { default: '' });

const promoInput = ref('');
const appliedCode = ref('');
const discountAmount = ref('0.00');
const validatingPromo = ref(false);
const promoError = ref('');

const finalTotal = computed(() => {
    const sub = parseFloat(props.subtotal) || 0;
    const disc = parseFloat(discountAmount.value) || 0;
    return Math.max(0, sub - disc).toFixed(2);
});

async function applyPromo() {
    if (!promoInput.value.trim()) return;
    validatingPromo.value = true;
    promoError.value = '';
    try {
        const { data } = await axios.post('/api/public/promo-codes/validate', {
            code: promoInput.value.trim(),
        });
        appliedCode.value = data.data.code;
        discountAmount.value = data.data.discount_amount.toString();
        promoCode.value = data.data.code;
    } catch (e: any) {
        promoError.value = e.response?.data?.message || 'الكوبون غير صالح أو لا يغطي الحد الأدنى للطلب';
        appliedCode.value = '';
        discountAmount.value = '0.00';
        promoCode.value = '';
    } finally {
        validatingPromo.value = false;
    }
}

function removePromo() {
    appliedCode.value = '';
    discountAmount.value = '0.00';
    promoInput.value = '';
    promoCode.value = '';
    promoError.value = '';
}

const emit = defineEmits<{
    close: [];
    confirm: [];
}>();
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-[60] flex items-end justify-center bg-black/60 backdrop-blur-xs sm:items-center"
            @click.self="emit('close')"
        >
            <div class="w-full max-w-lg rounded-t-3xl bg-white dark:bg-[#0a0f1d] p-6 shadow-2xl sm:rounded-3xl border-t sm:border border-stone-200/80 dark:border-slate-800 transition-colors max-h-[90vh] overflow-y-auto" dir="rtl">
                <div class="flex items-center justify-between pb-3 border-b border-stone-100 dark:border-slate-800">
                    <div>
                        <h3 class="text-xl font-bold text-stone-900 dark:text-white">تأكيد وإرسال الطلب</h3>
                        <p class="text-xs text-stone-500 dark:text-slate-400 mt-0.5">
                            {{ itemCount }} صنف في السلة
                        </p>
                    </div>
                    <button type="button" @click="emit('close')" class="text-stone-400 hover:text-stone-600 dark:hover:text-stone-200 text-xl font-bold">
                        ✕
                    </button>
                </div>

                <!-- خيار نوع الطلب (محلي / سفري) -->
                <div class="mt-4">
                    <label class="mb-2 block text-xs font-bold text-stone-700 dark:text-slate-300">نوع الطلب</label>
                    <div class="grid grid-cols-2 gap-2.5">
                        <button
                            type="button"
                            @click="serviceType = 'dine_in'"
                            :class="[
                                'flex items-center justify-center gap-2 py-2.5 px-3 rounded-xl border-2 font-bold text-xs transition-all',
                                serviceType === 'dine_in'
                                    ? 'border-amber-500 bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:border-amber-500 dark:text-amber-300 shadow-xs'
                                    : 'border-stone-200 dark:border-slate-800 text-stone-600 dark:text-slate-400 hover:border-stone-300 dark:hover:border-slate-700'
                            ]"
                        >
                            <span class="text-base">🍽️</span>
                            <span>محلي / في الصالة</span>
                        </button>

                        <button
                            type="button"
                            @click="serviceType = 'takeaway'"
                            :class="[
                                'flex items-center justify-center gap-2 py-2.5 px-3 rounded-xl border-2 font-bold text-xs transition-all',
                                serviceType === 'takeaway'
                                    ? 'border-amber-500 bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:border-amber-500 dark:text-amber-300 shadow-xs'
                                    : 'border-stone-200 dark:border-slate-800 text-stone-600 dark:text-slate-400 hover:border-stone-300 dark:hover:border-slate-700'
                            ]"
                        >
                            <span class="text-base">🥡</span>
                            <span>سفري / استلام</span>
                        </button>
                    </div>
                </div>

                <!-- إشعار للمطاعم ذات الدفع المسبق -->
                <div v-if="isPrepaid" class="mt-3 p-3 rounded-xl bg-amber-50/80 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-900/50 flex items-start gap-2.5">
                    <span class="text-lg leading-none">💳</span>
                    <p class="text-xs text-amber-800 dark:text-amber-300 font-medium leading-relaxed">
                        نظام دفع مسبق: يرجى التوجه للكاشير لتأكيد الدفع بعد إرسال الطلب، وسيظهر لك رقم الشباك المخصص لاستلام وجبتك.
                    </p>
                </div>

                <!-- إدخال كود الخصم (الكوبون) -->
                <div class="mt-4 rounded-xl bg-stone-50 dark:bg-slate-900/70 p-3.5 border border-stone-200/80 dark:border-slate-800">
                    <label class="block text-xs font-bold text-stone-700 dark:text-slate-300 mb-1.5">
                        كود الخصم (كوبون) 🏷️
                    </label>
                    <div class="flex gap-2">
                        <input
                            v-model="promoInput"
                            type="text"
                            placeholder="أدخل كود الكوبون..."
                            :disabled="!!appliedCode"
                            class="flex-1 rounded-xl border-stone-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-stone-900 dark:text-white placeholder:text-stone-400 text-xs uppercase font-bold focus:border-amber-500 focus:ring-amber-500"
                            dir="ltr"
                        />
                        <button
                            v-if="!appliedCode"
                            type="button"
                            @click="applyPromo"
                            :disabled="!promoInput.trim() || validatingPromo"
                            class="px-4 py-2 rounded-xl bg-stone-800 dark:bg-slate-800 text-white font-bold text-xs hover:bg-stone-700 dark:hover:bg-slate-700 disabled:opacity-50 transition-colors"
                        >
                            {{ validatingPromo ? '...' : 'تطبيق' }}
                        </button>
                        <button
                            v-else
                            type="button"
                            @click="removePromo"
                            class="px-3.5 py-2 rounded-xl bg-rose-100 dark:bg-rose-950/60 text-rose-700 dark:text-rose-400 font-bold text-xs hover:bg-rose-200 transition-colors"
                        >
                            إلغاء
                        </button>
                    </div>
                    <p v-if="promoError" class="mt-1.5 text-xs text-rose-600 dark:text-rose-400 font-semibold">{{ promoError }}</p>
                    <p v-if="appliedCode" class="mt-1.5 text-xs text-emerald-600 dark:text-emerald-400 font-bold flex items-center gap-1">
                        <span>✓</span> تم تطبيق كود الخصم ({{ appliedCode }}) بنجاح!
                    </p>
                </div>

                <!-- ملاحظات عامة للطلب -->
                <div class="mt-4">
                    <label class="mb-1 block text-xs font-bold text-stone-700 dark:text-slate-300">ملاحظات عامة للطلب (اختياري)</label>
                    <textarea
                        v-model="customerNote"
                        rows="2"
                        class="w-full rounded-xl border-stone-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-stone-900 dark:text-white placeholder:text-stone-400 dark:placeholder:text-slate-500 text-xs focus:border-amber-500 focus:ring-amber-500 transition-colors"
                        placeholder="أي تفضيلات أو ملاحظات خاصة بالطاولة أو التقديم..."
                    />
                </div>

                <!-- ملخص الحساب -->
                <div class="mt-4 rounded-xl bg-amber-50/80 dark:bg-amber-950/30 border border-amber-200/80 dark:border-amber-800/60 p-3.5 space-y-1.5">
                    <div class="flex justify-between text-xs text-stone-600 dark:text-slate-300 font-medium">
                        <span>المجموع الفرعي:</span>
                        <span>{{ subtotal }} {{ currency }}</span>
                    </div>
                    <div v-if="appliedCode" class="flex justify-between text-xs text-emerald-600 dark:text-emerald-400 font-bold">
                        <span>قيمة الخصم ({{ appliedCode }}):</span>
                        <span>- {{ discountAmount }} {{ currency }}</span>
                    </div>
                    <div class="flex justify-between text-base font-black text-stone-900 dark:text-white pt-1.5 border-t border-amber-200/60 dark:border-amber-800/60">
                        <span>الإجمالي المطلوب:</span>
                        <span class="text-amber-600 dark:text-amber-400">{{ finalTotal }} {{ currency }}</span>
                    </div>
                </div>

                <div class="mt-5 flex gap-3">
                    <button
                        type="button"
                        class="flex-1 rounded-xl bg-stone-100 dark:bg-slate-800 hover:bg-stone-200 dark:hover:bg-slate-700 py-3 font-semibold text-stone-700 dark:text-slate-300 active:scale-95 transition-all text-sm"
                        @click="emit('close')"
                    >
                        رجوع
                    </button>
                    <button
                        type="button"
                        class="flex-1 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 py-3 font-bold text-white shadow-lg shadow-amber-200 dark:shadow-amber-950/50 active:scale-95 transition-all disabled:opacity-50 text-sm"
                        :disabled="loading"
                        @click="emit('confirm')"
                    >
                        {{ loading ? 'جاري الإرسال...' : 'إرسال الطلب ⚡' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
