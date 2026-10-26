<script setup lang="ts">
defineProps<{
    open: boolean;
    subtotal: string;
    currency: string;
    itemCount: number;
    loading: boolean;
    isPrepaid?: boolean;
}>();

const customerNote = defineModel<string>('customerNote', { default: '' });
const serviceType = defineModel<string>('serviceType', { default: 'dine_in' });

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
            <div class="w-full max-w-lg rounded-t-3xl bg-white dark:bg-[#0a0f1d] p-6 shadow-2xl sm:rounded-3xl border-t sm:border border-stone-200/80 dark:border-slate-800 transition-colors" dir="rtl">
                <h3 class="text-xl font-bold text-stone-900 dark:text-white">تأكيد وإرسال الطلب</h3>
                <p class="mt-1 text-sm font-medium text-stone-500 dark:text-slate-400">
                    {{ itemCount }} صنف — <span class="text-amber-600 dark:text-amber-400 font-bold">{{ subtotal }} {{ currency }}</span>
                </p>

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

                <div class="mt-4">
                    <label class="mb-1 block text-sm font-medium text-stone-600 dark:text-slate-300">ملاحظات عامة للطلب (اختياري)</label>
                    <textarea
                        v-model="customerNote"
                        rows="2"
                        class="w-full rounded-xl border-stone-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-stone-900 dark:text-white placeholder:text-stone-400 dark:placeholder:text-slate-500 text-sm focus:border-amber-500 focus:ring-amber-500 transition-colors"
                        placeholder="أي تفضيلات أو ملاحظات خاصة بالطاولة أو التقديم..."
                    />
                </div>

                <div class="mt-6 flex gap-3">
                    <button
                        type="button"
                        class="flex-1 rounded-xl bg-stone-100 dark:bg-slate-800 hover:bg-stone-200 dark:hover:bg-slate-700 py-3 font-semibold text-stone-700 dark:text-slate-300 active:scale-95 transition-all"
                        @click="emit('close')"
                    >
                        رجوع
                    </button>
                    <button
                        type="button"
                        class="flex-1 rounded-xl bg-amber-600 dark:bg-amber-500 hover:bg-amber-700 dark:hover:bg-amber-400 py-3 font-bold text-white shadow-lg shadow-amber-200 dark:shadow-amber-950/50 active:scale-95 transition-all disabled:opacity-50"
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
