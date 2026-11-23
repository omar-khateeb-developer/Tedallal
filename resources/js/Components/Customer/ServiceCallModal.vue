<script setup lang="ts">
import { ref, computed } from 'vue';
import axios from 'axios';
import { useVisualViewportModal } from '@/Composables/useVisualViewport';

const props = defineProps<{
    show: boolean;
    table: { name: string; public_token: string };
    currency?: string;
    activeOrdersTotal?: string;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'callSent', type: 'waiter' | 'bill', data: any): void;
}>();

const { modalContainerStyle } = useVisualViewportModal();

const activeTab = ref<'waiter' | 'bill'>('waiter');
const selectedReason = ref<string>('general');
const customNote = ref<string>('');
const paymentMethod = ref<'cash' | 'card'>('cash');
const isSubmitting = ref(false);
const errorMessage = ref<string | null>(null);

const presetReasons = [
    { key: 'general', label: 'مساعدة واستفسار', icon: '🛎️' },
    { key: 'water', label: 'طلب ماء أو ثلج', icon: '💧' },
    { key: 'cutlery', label: 'أدوات طعام / شوك', icon: '🍴' },
    { key: 'cleaning', label: 'تنظيف الطاولة', icon: '🧹' },
    { key: 'order_inquiry', label: 'سؤال عن صنف', icon: '❓' },
];

function selectReason(key: string) {
    selectedReason.value = key;
    triggerHaptic('light');
}

function selectPayment(method: 'cash' | 'card') {
    paymentMethod.value = method;
    triggerHaptic('light');
}

function triggerHaptic(type: 'light' | 'success' | 'warning' = 'light') {
    if (typeof window !== 'undefined' && 'vibrate' in navigator) {
        try {
            if (type === 'light') navigator.vibrate(12);
            else if (type === 'success') navigator.vibrate([15, 30, 20]);
            else if (type === 'warning') navigator.vibrate([30, 50, 30]);
        } catch {}
    }
}

async function submitWaiterCall() {
    if (isSubmitting.value) return;
    isSubmitting.value = true;
    errorMessage.value = null;

    try {
        const { data } = await axios.post(`/api/public/tables/${props.table.public_token}/call-waiter`, {
            reason: selectedReason.value,
            notes: customNote.value.trim() || null,
        });

        triggerHaptic('success');
        emit('callSent', 'waiter', data.data);
        emit('close');
        customNote.value = '';
    } catch (e: any) {
        errorMessage.value = e.response?.data?.message || 'تعذر إرسال النداء، يرجى المحاولة ثانية.';
        triggerHaptic('warning');
    } finally {
        isSubmitting.value = false;
    }
}

async function submitBillRequest() {
    if (isSubmitting.value) return;
    isSubmitting.value = true;
    errorMessage.value = null;

    try {
        const { data } = await axios.post(`/api/public/tables/${props.table.public_token}/request-bill`, {
            payment_method: paymentMethod.value,
            notes: customNote.value.trim() || null,
        });

        triggerHaptic('success');
        emit('callSent', 'bill', data.data);
        emit('close');
        customNote.value = '';
    } catch (e: any) {
        errorMessage.value = e.response?.data?.message || 'تعذر طلب الفاتورة، يرجى المحاولة ثانية.';
        triggerHaptic('warning');
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-end justify-center bg-black/75 backdrop-blur-sm transition-opacity duration-300"
        @click.self="emit('close')"
    >
        <div
            :style="modalContainerStyle"
            class="w-full max-w-lg bg-stone-900 border-t border-amber-500/40 rounded-t-3xl shadow-2xl p-6 text-white pb-8 overflow-y-auto max-h-[85vh] animate-in slide-in-from-bottom-6 duration-200"
        >
            <!-- Drag Indicator -->
            <div class="flex justify-center -mt-2 mb-4">
                <div class="w-12 h-1.5 rounded-full bg-stone-700"></div>
            </div>

            <!-- Header -->
            <div class="flex items-center justify-between border-b border-stone-800 pb-4 mb-5">
                <div>
                    <h3 class="text-lg font-bold text-amber-400 flex items-center gap-2">
                        <span>🛎️ خدمة الطاولة الهادئة</span>
                    </h3>
                    <p class="text-xs text-stone-400 mt-0.5">
                        {{ table.name }} • نلبي رغباتكم بكل رقي ودون الحاجة لمناداة
                    </p>
                </div>
                <button
                    type="button"
                    @click="emit('close')"
                    class="w-8 h-8 rounded-full bg-stone-800 text-stone-400 hover:text-white flex items-center justify-center text-sm font-bold active:scale-95 transition-all"
                >
                    ✕
                </button>
            </div>

            <!-- Tab Switcher -->
            <div class="grid grid-cols-2 gap-2 p-1.5 bg-stone-950/80 rounded-2xl border border-stone-800 mb-6">
                <button
                    type="button"
                    @click="activeTab = 'waiter'; triggerHaptic('light')"
                    class="py-2.5 px-3 rounded-xl font-bold text-xs flex items-center justify-center gap-2 transition-all"
                    :class="activeTab === 'waiter' ? 'bg-amber-500 text-stone-950 shadow-lg' : 'text-stone-400 hover:text-stone-200'"
                >
                    <span>🛎️ استدعاء النادل</span>
                </button>
                <button
                    type="button"
                    @click="activeTab = 'bill'; triggerHaptic('light')"
                    class="py-2.5 px-3 rounded-xl font-bold text-xs flex items-center justify-center gap-2 transition-all"
                    :class="activeTab === 'bill' ? 'bg-amber-500 text-stone-950 shadow-lg' : 'text-stone-400 hover:text-stone-200'"
                >
                    <span>🧾 طلب الفاتورة</span>
                </button>
            </div>

            <!-- Error Banner -->
            <div v-if="errorMessage" class="mb-4 p-3 rounded-xl bg-rose-950/80 border border-rose-600/50 text-rose-200 text-xs">
                ⚠️ {{ errorMessage }}
            </div>

            <!-- TAB 1: CALL WAITER -->
            <div v-if="activeTab === 'waiter'" class="space-y-5">
                <div>
                    <label class="block text-xs font-semibold text-stone-300 mb-2.5">
                        ما الذي يمكننا مساعدتكم به؟
                    </label>
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            v-for="r in presetReasons"
                            :key="r.key"
                            type="button"
                            @click="selectReason(r.key)"
                            class="p-3 rounded-xl border text-right transition-all flex items-center gap-2.5"
                            :class="selectedReason === r.key
                                ? 'bg-amber-500/15 border-amber-500 text-amber-300 font-bold shadow-md'
                                : 'bg-stone-800/60 border-stone-700/80 text-stone-300 hover:border-stone-600'"
                        >
                            <span class="text-base">{{ r.icon }}</span>
                            <span class="text-xs">{{ r.label }}</span>
                        </button>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-stone-300 mb-1.5">
                        ملاحظة إضافية للنادل (اختياري):
                    </label>
                    <input
                        v-model="customNote"
                        type="text"
                        placeholder="مثال: يرجى إحضار كرسي للأطفال أو مناديل إضافية..."
                        class="w-full bg-stone-950 border border-stone-700 rounded-xl px-3.5 py-3 text-xs text-white placeholder-stone-500 focus:outline-none focus:border-amber-400"
                        style="font-size: 16px;"
                    />
                </div>

                <button
                    type="button"
                    :disabled="isSubmitting"
                    @click="submitWaiterCall"
                    class="w-full py-3.5 px-4 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-stone-950 font-black text-sm shadow-xl active:scale-98 transition-all disabled:opacity-50 flex items-center justify-center gap-2"
                >
                    <span v-if="!isSubmitting">🛎️ إرسال النداء إلى النادل</span>
                    <span v-else class="animate-pulse">⏳ جاري إشعار طاقم الخدمة...</span>
                </button>
            </div>

            <!-- TAB 2: REQUEST BILL -->
            <div v-else class="space-y-5">
                <!-- If active orders exist with total -->
                <div v-if="activeOrdersTotal" class="p-3.5 rounded-2xl bg-amber-500/10 border border-amber-500/30 flex items-center justify-between">
                    <span class="text-xs text-stone-300 font-medium">إجمالي حساب الطاولة الحالي:</span>
                    <span class="text-base font-black text-amber-400 font-mono">{{ activeOrdersTotal }} {{ currency || 'ر.س' }}</span>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-stone-300 mb-2.5">
                        طريقة الدفع المفضلة:
                    </label>
                    <div class="grid grid-cols-2 gap-3">
                        <button
                            type="button"
                            @click="selectPayment('cash')"
                            class="p-4 rounded-2xl border text-center transition-all flex flex-col items-center justify-center gap-2"
                            :class="paymentMethod === 'cash'
                                ? 'bg-amber-500/15 border-amber-500 text-amber-300 font-bold shadow-lg ring-2 ring-amber-500/40'
                                : 'bg-stone-800/60 border-stone-700/80 text-stone-300 hover:border-stone-600'"
                        >
                            <span class="text-2xl">💵</span>
                            <span class="text-xs font-bold">نقداً (كاش)</span>
                            <span class="text-[10px] text-stone-400">سيتوجه الكاشير لاستلام النقد</span>
                        </button>

                        <button
                            type="button"
                            @click="selectPayment('card')"
                            class="p-4 rounded-2xl border text-center transition-all flex flex-col items-center justify-center gap-2"
                            :class="paymentMethod === 'card'
                                ? 'bg-amber-500/15 border-amber-500 text-amber-300 font-bold shadow-lg ring-2 ring-amber-500/40'
                                : 'bg-stone-800/60 border-stone-700/80 text-stone-300 hover:border-stone-600'"
                        >
                            <span class="text-2xl">💳</span>
                            <span class="text-xs font-bold">بطاقة / مدى</span>
                            <span class="text-[10px] text-stone-400">سيحضر النادل جهاز الشبكة للطاولة</span>
                        </button>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-stone-300 mb-1.5">
                        ملاحظة للكاشير (اختياري):
                    </label>
                    <input
                        v-model="customNote"
                        type="text"
                        placeholder="مثال: فاتورة ضريبية، أو تقسيم الحساب..."
                        class="w-full bg-stone-950 border border-stone-700 rounded-xl px-3.5 py-3 text-xs text-white placeholder-stone-500 focus:outline-none focus:border-amber-400"
                        style="font-size: 16px;"
                    />
                </div>

                <button
                    type="button"
                    :disabled="isSubmitting"
                    @click="submitBillRequest"
                    class="w-full py-3.5 px-4 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-stone-950 font-black text-sm shadow-xl active:scale-98 transition-all disabled:opacity-50 flex items-center justify-center gap-2"
                >
                    <span v-if="!isSubmitting">🧾 إرسال طلب الفاتورة إلى الكاشير</span>
                    <span v-else class="animate-pulse">⏳ جاري إشعار الكاشير...</span>
                </button>
            </div>
        </div>
    </div>
</template>
