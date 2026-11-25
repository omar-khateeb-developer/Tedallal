<script setup lang="ts">
import { ref } from 'vue';
import axios from 'axios';
import type { TableItem } from '@/types/floorPlan';

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'created', table: TableItem): void;
}>();

const form = ref({
    name: '',
    shape: 'circle' as 'circle' | 'rectangle' | 'booth' | 'vip',
    capacity: 4,
    zone: 'main',
    iot_device_id: '',
});

async function submit() {
    if (!form.value.name.trim()) return;
    try {
        const { data } = await axios.post('/api/admin/tables', {
            name: form.value.name.trim(),
            shape: form.value.shape,
            capacity: form.value.capacity,
            zone: form.value.zone,
            iot_device_id: form.value.iot_device_id || null,
            pos_x: 100,
            pos_y: 100,
            status: 'active',
        });
        const created = data.data;
        emit('created', {
            ...created,
            pos_x: created.pos_x || 100,
            pos_y: created.pos_y || 100,
            shape: created.shape || form.value.shape,
            rotation: 0,
            capacity: created.capacity || form.value.capacity,
            zone: created.zone || form.value.zone,
            width: created.shape === 'vip' ? 140 : (created.shape === 'rectangle' ? 130 : 90),
            height: created.shape === 'rectangle' ? 80 : 90,
        });
        form.value.name = '';
        form.value.iot_device_id = '';
        emit('close');
    } catch (e: any) {
        alert(e.response?.data?.message || 'تعذر إضافة الطاولة');
    }
}
</script>

<template>
    <div
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs animate-fadeIn"
        dir="rtl"
        @click.self="emit('close')"
    >
        <div
            class="w-full max-w-md bg-white dark:bg-slate-900 rounded-3xl p-6 shadow-2xl border border-slate-200 dark:border-slate-800 space-y-5"
            @click.stop
        >
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-extrabold text-slate-900 dark:text-white">إضافة طاولة جديدة إلى المخطط</h3>
                <button @click="emit('close')" type="button" class="text-slate-400 hover:text-slate-600 cursor-pointer">✕</button>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">اسم أو رقم الطاولة</label>
                    <input
                        v-model="form.name"
                        type="text"
                        placeholder="مثال: طاولة 12 أو جناح A"
                        class="w-full px-3.5 py-2.5 rounded-xl text-sm border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white"
                    />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">الشكل</label>
                        <select
                            v-model="form.shape"
                            class="w-full px-3 py-2.5 rounded-xl text-xs border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white"
                        >
                            <option value="circle">دائرية (Circle)</option>
                            <option value="rectangle">مستطيلة (Banquet)</option>
                            <option value="booth">كابينة (Booth)</option>
                            <option value="vip">جناح VIP فاخر</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">السعة</label>
                        <input
                            v-model.number="form.capacity"
                            type="number"
                            min="1"
                            max="30"
                            class="w-full px-3 py-2.5 rounded-xl text-xs border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white"
                        />
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">المنطقة</label>
                    <select
                        v-model="form.zone"
                        class="w-full px-3.5 py-2.5 rounded-xl text-sm border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white"
                    >
                        <option value="main">الصالة الرئيسية</option>
                        <option value="vip">جناح النخبة (VIP Lounge)</option>
                        <option value="terrace">الشرفة الخارجية</option>
                        <option value="family">القسم العائلي</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">معرّف جهاز الـ ESP32 (اختياري)</label>
                    <input
                        v-model="form.iot_device_id"
                        type="text"
                        dir="ltr"
                        placeholder="ESP32_DEV_ID"
                        class="w-full px-3.5 py-2 rounded-xl text-xs font-mono border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white"
                    />
                </div>
            </div>

            <div class="flex items-center gap-3 pt-4">
                <button
                    type="button"
                    @click="submit"
                    class="flex-1 py-3 rounded-xl bg-amber-500 hover:bg-amber-400 text-stone-950 font-black text-sm shadow-md transition cursor-pointer"
                >
                    إضافة وتوليد رمز QR
                </button>
                <button
                    type="button"
                    @click="emit('close')"
                    class="px-5 py-3 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold text-sm cursor-pointer"
                >
                    إلغاء
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes fadeIn {
    from { opacity: 0; transform: scale(0.98); }
    to { opacity: 1; transform: scale(1); }
}

.animate-fadeIn {
    animation: fadeIn 0.2s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
</style>
