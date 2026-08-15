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
            class="fixed inset-0 z-[60] flex items-end justify-center bg-black/50 sm:items-center"
            @click.self="emit('close')"
        >
            <div class="w-full max-w-lg rounded-t-3xl bg-white p-6 shadow-xl sm:rounded-3xl" dir="rtl">
                <h3 class="text-xl font-bold">تأكيد الطلب</h3>
                <p class="mt-2 text-stone-500">{{ itemCount }} صنف — {{ subtotal }} {{ currency }}</p>

                <div class="mt-5">
                    <label class="mb-1 block text-sm text-stone-600">ملاحظات عامة للطلب</label>
                    <textarea
                        v-model="customerNote"
                        rows="3"
                        class="w-full rounded-xl border-stone-200 text-sm"
                        placeholder="اختياري..."
                    />
                </div>

                <div class="mt-6 flex gap-3">
                    <button
                        type="button"
                        class="flex-1 rounded-xl bg-stone-100 py-3 font-medium"
                        @click="emit('close')"
                    >
                        رجوع
                    </button>
                    <button
                        type="button"
                        class="flex-1 rounded-xl bg-amber-600 py-3 font-bold text-white disabled:opacity-50"
                        :disabled="loading"
                        @click="emit('confirm')"
                    >
                        {{ loading ? 'جاري الإرسال...' : 'إرسال الطلب' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
