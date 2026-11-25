<script setup lang="ts">
import { ref, watch } from 'vue';
import axios from 'axios';
import type { CustomerRow } from '@/types/customers';

const props = defineProps<{
    customer: CustomerRow;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'updated'): void;
}>();

const form = ref({
    name: props.customer.name,
    notes: props.customer.notes || '',
});
const saving = ref(false);

watch(
    () => props.customer,
    (c) => {
        form.value = { name: c.name, notes: c.notes || '' };
    },
    { immediate: true },
);

async function saveCustomerNotes() {
    saving.value = true;
    try {
        await axios.patch(`/api/admin/customers/${props.customer.id}`, form.value);
        emit('updated');
        emit('close');
    } catch (e: any) {
        alert(e.response?.data?.message || 'تعذر التحديث.');
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <div
        class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4"
        dir="rtl"
        @click.self="emit('close')"
    >
        <div
            class="bg-white dark:bg-[#0f172a] rounded-3xl max-w-md w-full p-6 border border-slate-200 dark:border-slate-700 shadow-2xl space-y-4"
            @click.stop
        >
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-black text-slate-900 dark:text-white">✏️ تحديث بيانات العميل</h3>
                <button
                    @click="emit('close')"
                    type="button"
                    class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white flex items-center justify-center font-bold text-sm cursor-pointer"
                    title="إغلاق (Esc)"
                >
                    ✕
                </button>
            </div>

            <div class="space-y-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">الاسم:</label>
                    <input
                        v-model="form.name"
                        type="text"
                        class="w-full rounded-xl text-sm border-slate-200 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                    />
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">ملاحظات:</label>
                    <textarea
                        v-model="form.notes"
                        rows="3"
                        class="w-full rounded-xl text-sm border-slate-200 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                    ></textarea>
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button
                    @click="emit('close')"
                    type="button"
                    class="px-4 py-2 rounded-xl text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 cursor-pointer"
                >
                    إلغاء
                </button>
                <button
                    @click="saveCustomerNotes"
                    :disabled="saving"
                    type="button"
                    class="px-5 py-2 rounded-xl text-xs font-black bg-amber-500 text-white hover:bg-amber-600 disabled:opacity-60 cursor-pointer"
                >
                    {{ saving ? 'جاري الحفظ...' : 'حفظ التعديلات' }}
                </button>
            </div>
        </div>
    </div>
</template>
