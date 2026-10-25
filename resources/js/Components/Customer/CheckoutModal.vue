<script setup lang="ts">
defineProps<{
    open: boolean;
    subtotal: string;
    currency: string;
    itemCount: number;
    loading: boolean;
}>();

const customerNote = defineModel<string>('customerNote', { default: '' });

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

                <div class="mt-5">
                    <label class="mb-1 block text-sm font-medium text-stone-600 dark:text-slate-300">ملاحظات عامة للطلب (اختياري)</label>
                    <textarea
                        v-model="customerNote"
                        rows="3"
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
