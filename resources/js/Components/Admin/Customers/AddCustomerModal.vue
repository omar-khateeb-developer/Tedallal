<script setup lang="ts">
import { ref } from 'vue';
import axios from 'axios';

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'created'): void;
}>();

const form = ref({ name: '', phone: '', notes: '' });
const error = ref<string | null>(null);
const saving = ref(false);

async function submit() {
    error.value = null;
    saving.value = true;
    try {
        await axios.post('/api/admin/customers', form.value);
        form.value = { name: '', phone: '', notes: '' };
        emit('created');
        emit('close');
    } catch (e: any) {
        const errors = e.response?.data?.errors;
        if (errors) {
            error.value = Object.values(errors).flat()[0] as string;
        } else {
            error.value = e.response?.data?.message || 'تعذر إضافة العميل.';
        }
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
                <h3 class="text-lg font-black text-slate-900 dark:text-white">➕ إضافة عميل إلى سجل المطعم</h3>
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
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">الاسم الكامل:</label>
                    <input
                        v-model="form.name"
                        type="text"
                        placeholder="مثال: أحمد محمد"
                        class="w-full rounded-xl text-sm border-slate-200 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                    />
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">رقم الواتساب (يبدأ بـ 09):</label>
                    <input
                        v-model="form.phone"
                        type="tel"
                        dir="ltr"
                        maxlength="10"
                        placeholder="09XXXXXXXX"
                        class="w-full rounded-xl text-sm font-mono border-slate-200 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                    />
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">ملاحظات (اختياري):</label>
                    <textarea
                        v-model="form.notes"
                        rows="2"
                        placeholder="تفضيلات الزبون أو ملاحظات خاصة..."
                        class="w-full rounded-xl text-sm border-slate-200 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                    ></textarea>
                </div>
                <div v-if="error" class="p-2.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 text-xs font-bold">
                    ⚠️ {{ error }}
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
                    @click="submit"
                    :disabled="saving"
                    type="button"
                    class="px-5 py-2 rounded-xl text-xs font-black bg-emerald-600 text-white hover:bg-emerald-500 disabled:opacity-60 cursor-pointer"
                >
                    {{ saving ? 'جاري الحفظ...' : 'حفظ العميل' }}
                </button>
            </div>
        </div>
    </div>
</template>
