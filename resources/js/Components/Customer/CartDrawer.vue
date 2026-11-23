<script setup lang="ts">
import { ref, toRef, computed, watch, onUnmounted } from 'vue';
import axios from 'axios';
import { useVisualViewportModal } from '@/composables/useVisualViewport';

export interface CartLine {
    id: number;
    quantity: number;
    note: string | null;
    menu_item: { name: string; price: string };
}

const props = withDefaults(defineProps<{
    open: boolean;
    currency: string;
    isPrepaid?: boolean;
    submitting?: boolean;
}>(), {
    isPrepaid: false,
    submitting: false,
});

const { modalStyle } = useVisualViewportModal(toRef(props, 'open'));

const emit = defineEmits<{
    'update:open': [value: boolean];
    'submit-order': [payload: { serviceType: string; customerNote: string; promoCode: string }];
    checkout: [];
    updated: [];
}>();

const items = ref<CartLine[]>([]);
const subtotal = ref('0.00');
const count = ref(0);
const loading = ref(false);

// 1-Step Unified Checkout State
const serviceType = ref<'dine_in' | 'takeaway'>('dine_in');
const generalNote = ref('');
const promoInput = ref('');
const appliedCode = ref('');
const discountAmount = ref('0.00');
const validatingPromo = ref(false);
const promoError = ref('');
const showPromoInput = ref(false);

const finalTotal = computed(() => {
    const sub = parseFloat(subtotal.value) || 0;
    const disc = parseFloat(discountAmount.value) || 0;
    return Math.max(0, sub - disc).toFixed(2);
});

const finalTotalFormatted = computed(() => {
    const num = parseFloat(finalTotal.value);
    const intVal = isNaN(num) ? 0 : Math.round(num);
    return intVal.toLocaleString();
});

const subtotalFormatted = computed(() => {
    const num = parseFloat(subtotal.value);
    const intVal = isNaN(num) ? 0 : Math.round(num);
    return intVal.toLocaleString();
});

const discountFormatted = computed(() => {
    const num = parseFloat(discountAmount.value);
    const intVal = isNaN(num) ? 0 : Math.round(num);
    return intVal.toLocaleString();
});

async function load() {
    loading.value = true;
    try {
        const { data } = await axios.get('/api/public/cart');
        items.value = data.data?.items ?? [];
        subtotal.value = data.data?.subtotal ?? '0.00';
        count.value = data.data?.count ?? 0;
    } finally {
        loading.value = false;
    }
}

async function updateQty(item: CartLine, quantity: number) {
    await axios.patch(`/api/public/cart/items/${item.id}`, {
        quantity,
        note: item.note,
    });
    await load();
    emit('updated');
}

async function updateNote(item: CartLine, note: string) {
    await axios.patch(`/api/public/cart/items/${item.id}`, {
        quantity: item.quantity,
        note: note || null,
    });
    item.note = note || null;
    emit('updated');
}

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
    } catch (e: any) {
        promoError.value = e.response?.data?.message || 'الكوبون غير صالح أو لا يغطي الحد الأدنى للطلب';
        appliedCode.value = '';
        discountAmount.value = '0.00';
    } finally {
        validatingPromo.value = false;
    }
}

function removePromo() {
    appliedCode.value = '';
    discountAmount.value = '0.00';
    promoInput.value = '';
    promoError.value = '';
    showPromoInput.value = false;
}

function handleOrderSubmit() {
    emit('submit-order', {
        serviceType: serviceType.value,
        customerNote: generalNote.value.trim(),
        promoCode: appliedCode.value,
    });
}

function close() {
    emit('update:open', false);
    emit('updated');
}

watch(() => props.open, (isOpen) => {
    if (isOpen) {
        document.body.style.overflow = 'hidden';
        load();
    } else {
        document.body.style.overflow = '';
    }
});

onUnmounted(() => {
    document.body.style.overflow = '';
});

defineExpose({ load, count, subtotal, finalTotal, serviceType, generalNote, appliedCode });
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-40 bg-black/60 backdrop-blur-xs transition-opacity"
            @click="close"
        />
        <div
            v-if="open"
            class="fixed inset-x-0 bottom-0 z-50 flex flex-col max-h-[92dvh] rounded-t-3xl bg-white dark:bg-[#0a0f1d] text-stone-900 dark:text-white shadow-2xl border-t border-stone-200/80 dark:border-slate-800 transition-colors"
            :style="modalStyle"
            dir="rtl"
        >
            <!-- مقبض السحب المرئي لشاشات الموبايل -->
            <div class="w-12 h-1.5 rounded-full bg-stone-300 dark:bg-stone-700 mx-auto my-2 shrink-0"></div>

            <!-- هيدر السلة الفاخر -->
            <div class="shrink-0 flex items-center justify-between border-b border-stone-100 dark:border-slate-800 bg-white/95 dark:bg-[#0a0f1d]/95 backdrop-blur-md px-4 py-3">
                <div class="flex items-center gap-2">
                    <h2 class="text-lg font-bold text-stone-900 dark:text-white">سلة الطلب</h2>
                    <span v-if="count > 0" class="text-xs px-2.5 py-0.5 rounded-full bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 font-black">
                        {{ count }} {{ count === 1 ? 'صنف' : count === 2 ? 'صنفان' : 'أصناف' }}
                    </span>
                </div>
                <button
                    type="button"
                    class="h-8 w-8 rounded-full flex items-center justify-center text-stone-500 hover:text-stone-800 dark:text-slate-400 dark:hover:text-white hover:bg-stone-100 dark:hover:bg-slate-800 transition-colors cursor-pointer"
                    @click="close"
                    title="إغلاق السلة"
                >
                    ✕
                </button>
            </div>

            <!-- مؤشر التحميل -->
            <div v-if="loading && items.length === 0" class="py-16 text-center text-stone-500 dark:text-slate-400">
                <div class="inline-block w-8 h-8 border-3 border-amber-500/30 border-t-amber-500 rounded-full animate-spin mb-3"></div>
                <p class="text-sm font-semibold">جاري تحضير السلة...</p>
            </div>

            <!-- السلة فارغة -->
            <div v-else-if="items.length === 0" class="py-16 text-center text-stone-500 dark:text-slate-400 px-6">
                <div class="text-5xl mb-3">🛒</div>
                <h3 class="text-base font-bold text-stone-700 dark:text-slate-300">سلتك فارغة حالياً</h3>
                <p class="text-xs text-stone-400 dark:text-slate-500 mt-1">اختر أطباقك المفضلة من المنيو لتجربة طعام مميزة</p>
                <button
                    type="button"
                    class="mt-4 px-5 py-2.5 rounded-xl bg-amber-500 text-white font-bold text-xs shadow-md shadow-amber-500/20 active:scale-95 transition-all"
                    @click="close"
                >
                    تصفح المنيو الآن
                </button>
            </div>

            <!-- قائمة الأصناف وخيارات الطلب السريعة (خطوة واحدة) -->
            <div v-else class="flex-1 overflow-y-auto space-y-4 p-4 scrollbar-hide">
                <!-- 1. قائمة أصناف السلة -->
                <div class="space-y-2.5">
                    <article
                        v-for="item in items"
                        :key="item.id"
                        class="rounded-2xl bg-stone-50 dark:bg-slate-900/80 p-3.5 border border-stone-100 dark:border-slate-800 transition-colors shadow-2xs"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex-1 min-w-0">
                                <h3 class="font-bold text-stone-900 dark:text-white text-sm truncate">{{ item.menu_item.name }}</h3>
                                <p class="text-xs font-black text-amber-700 dark:text-amber-400 mt-0.5">
                                    {{ item.menu_item.price }} {{ currency }}
                                </p>
                            </div>
                            <div class="flex items-center gap-1.5 bg-white dark:bg-slate-800/80 border border-stone-200 dark:border-slate-700 rounded-full p-0.5 shadow-2xs shrink-0">
                                <button
                                    type="button"
                                    class="h-7 w-7 rounded-full bg-stone-50 dark:bg-slate-700 text-stone-800 dark:text-slate-100 font-bold flex items-center justify-center hover:bg-stone-200 dark:hover:bg-slate-600 active:scale-90 transition-all text-xs select-none cursor-pointer"
                                    @click="updateQty(item, item.quantity - 1)"
                                    title="إنقاص"
                                >
                                    −
                                </button>
                                <span class="w-5 text-center font-black text-xs text-stone-900 dark:text-white">{{ item.quantity }}</span>
                                <button
                                    type="button"
                                    class="h-7 w-7 rounded-full bg-amber-600 dark:bg-amber-500 text-white font-bold flex items-center justify-center hover:bg-amber-700 dark:hover:bg-amber-400 active:scale-90 transition-all text-xs select-none cursor-pointer"
                                    @click="updateQty(item, item.quantity + 1)"
                                    title="زيادة"
                                >
                                    +
                                </button>
                            </div>
                        </div>
                        <input
                            :value="item.note ?? ''"
                            type="text"
                            class="mt-2.5 w-full rounded-xl border-stone-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-stone-900 dark:text-white placeholder:text-stone-400 dark:placeholder:text-slate-500 text-[13px] px-3 py-1.5 focus:border-amber-500 focus:ring-amber-500"
                            placeholder="ملاحظة خاصة على هذا الصنف (اختياري)..."
                            @change="updateNote(item, ($event.target as HTMLInputElement).value)"
                        />
                    </article>
                </div>

                <!-- 2. خيار نوع الخدمة المباشر (محلي أو سفري) بلمسة واحدة -->
                <div class="pt-2 border-t border-stone-100 dark:border-slate-800/80">
                    <label class="mb-1.5 block text-xs font-bold text-stone-700 dark:text-slate-300">طريقة الاستلام</label>
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            @click="serviceType = 'dine_in'"
                            :class="[
                                'flex items-center justify-center gap-2 py-2.5 px-3 rounded-xl border-2 font-bold text-xs transition-all cursor-pointer active:scale-95',
                                serviceType === 'dine_in'
                                    ? 'border-amber-500 bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:border-amber-500 dark:text-amber-300 shadow-2xs'
                                    : 'border-stone-200 dark:border-slate-800 text-stone-600 dark:text-slate-400 hover:border-stone-300 dark:hover:border-slate-700'
                            ]"
                        >
                            <span class="text-base">🍽️</span>
                            <span>محلي (على الطاولة)</span>
                        </button>

                        <button
                            type="button"
                            @click="serviceType = 'takeaway'"
                            :class="[
                                'flex items-center justify-center gap-2 py-2.5 px-3 rounded-xl border-2 font-bold text-xs transition-all cursor-pointer active:scale-95',
                                serviceType === 'takeaway'
                                    ? 'border-amber-500 bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:border-amber-500 dark:text-amber-300 shadow-2xs'
                                    : 'border-stone-200 dark:border-slate-800 text-stone-600 dark:text-slate-400 hover:border-stone-300 dark:hover:border-slate-700'
                            ]"
                        >
                            <span class="text-base">🥡</span>
                            <span>سفري (تغليف للاستلام)</span>
                        </button>
                    </div>
                </div>

                <!-- 3. إشعار الدفع المسبق (إن وجد) -->
                <div v-if="isPrepaid" class="p-3 rounded-xl bg-amber-50/80 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-900/50 flex items-start gap-2.5 text-xs text-amber-900 dark:text-amber-200">
                    <span class="text-base shrink-0">💳</span>
                    <p class="leading-relaxed">
                        <strong>نظام الدفع المسبق:</strong> بعد تأكيد الطلب، يرجى التوجه للكاشير للمحاسبة برقم طلبك ليبدأ تحضيره فوراً.
                    </p>
                </div>

                <!-- 4. كوبون الخصم المباشر (أنيق ومدمج) -->
                <div class="pt-1">
                    <div v-if="appliedCode" class="flex items-center justify-between p-2.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 text-xs">
                        <div class="flex items-center gap-2 text-emerald-800 dark:text-emerald-300 font-bold">
                            <span>🎉</span>
                            <span>تم تطبيق الكوبون ({{ appliedCode }}) - خصم {{ discountFormatted }} {{ currency }}</span>
                        </div>
                        <button type="button" @click="removePromo" class="text-stone-400 hover:text-rose-500 font-black px-1.5 py-0.5 rounded-md hover:bg-rose-50 dark:hover:bg-rose-950/50 transition-colors">✕</button>
                    </div>

                    <div v-else>
                        <button
                            v-if="!showPromoInput"
                            type="button"
                            @click="showPromoInput = true"
                            class="inline-flex items-center gap-1.5 text-xs font-bold text-amber-600 dark:text-amber-400 hover:underline cursor-pointer py-1"
                        >
                            <span>🏷️</span>
                            <span>هل لديك كوبون خصم؟</span>
                        </button>
                        <div v-else class="flex gap-2">
                            <input
                                v-model="promoInput"
                                type="text"
                                placeholder="أدخل رمز الكوبون..."
                                class="flex-1 uppercase rounded-xl border-stone-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-stone-900 dark:text-white placeholder:text-stone-400 text-xs px-3 py-2 focus:border-amber-500 focus:ring-amber-500"
                                @keyup.enter="applyPromo"
                            />
                            <button
                                type="button"
                                :disabled="validatingPromo || !promoInput.trim()"
                                @click="applyPromo"
                                class="px-3.5 py-2 rounded-xl bg-amber-600 text-white font-bold text-xs hover:bg-amber-700 disabled:opacity-50 cursor-pointer transition-all"
                            >
                                {{ validatingPromo ? '...' : 'تطبيق' }}
                            </button>
                            <button
                                type="button"
                                @click="showPromoInput = false; promoError = ''"
                                class="px-2 text-stone-400 hover:text-stone-600 text-xs"
                            >
                                إلغاء
                            </button>
                        </div>
                        <p v-if="promoError" class="mt-1 text-[11px] font-semibold text-rose-600 dark:text-rose-400">
                            {{ promoError }}
                        </p>
                    </div>
                </div>

                <!-- 5. ملاحظة عامة على الطلب -->
                <div>
                    <label class="mb-1 block text-xs font-bold text-stone-700 dark:text-slate-300">ملاحظة عامة للمطبخ (اختياري)</label>
                    <textarea
                        v-model="generalNote"
                        rows="2"
                        class="w-full rounded-xl border-stone-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-stone-900 dark:text-white placeholder:text-stone-400 text-xs px-3 py-2 focus:border-amber-500 focus:ring-amber-500"
                        placeholder="أي تفاصيل ترغب بإخبار المطعم بها (مثال: تقديم المشروبات مع الوجبة)..."
                    />
                </div>
            </div>

            <!-- الفوتر الثابت: تفاصيل السعر وزر الإرسال الفوري (1-Step Submit) -->
            <div
                v-if="items.length > 0 && !loading"
                class="shrink-0 border-t border-stone-200 dark:border-slate-800 bg-white/95 dark:bg-[#0a0f1d]/95 backdrop-blur-md p-4 pb-[max(1rem,env(safe-area-inset-bottom))] transition-colors space-y-3"
            >
                <!-- ملخص الحساب -->
                <div class="space-y-1 text-xs">
                    <div v-if="parseFloat(discountAmount) > 0" class="flex justify-between text-stone-500 dark:text-slate-400">
                        <span>المجموع قبل الخصم:</span>
                        <span>{{ subtotalFormatted }} {{ currency }}</span>
                    </div>
                    <div v-if="parseFloat(discountAmount) > 0" class="flex justify-between text-emerald-600 dark:text-emerald-400 font-bold">
                        <span>خصم الكوبون:</span>
                        <span>-{{ discountFormatted }} {{ currency }}</span>
                    </div>
                    <div class="flex justify-between text-base font-black text-stone-900 dark:text-white pt-0.5">
                        <span>المبلغ الإجمالي:</span>
                        <span class="text-amber-600 dark:text-amber-400 text-lg">{{ finalTotalFormatted }} {{ currency }}</span>
                    </div>
                </div>

                <!-- زر الإرسال المباشر للطلب (خطوة واحدة فقط بدون أي نوافذ إضافية) -->
                <button
                    type="button"
                    :disabled="submitting || items.length === 0"
                    class="w-full rounded-2xl bg-gradient-to-r from-amber-600 via-amber-500 to-amber-600 hover:from-amber-700 hover:to-amber-500 py-3.5 text-base font-black text-white shadow-lg shadow-amber-500/25 active:scale-[0.98] transition-all disabled:opacity-50 cursor-pointer flex items-center justify-center gap-2"
                    @click="handleOrderSubmit"
                >
                    <span v-if="submitting" class="inline-block w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                    <span>{{ submitting ? 'جاري إرسال الطلب للمطبخ...' : 'إرسال الطلب الآن ⚡' }}</span>
                    <span v-if="!submitting" class="text-xs bg-black/20 px-2.5 py-0.5 rounded-full font-black">
                        {{ finalTotalFormatted }} {{ currency }}
                    </span>
                </button>
            </div>
        </div>
    </Teleport>
</template>
