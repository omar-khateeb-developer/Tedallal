<script setup lang="ts">
import AdminNav from '@/Components/Admin/AdminNav.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import axios from 'axios';

const stats = ref<any>(null);
const isLocked = ref(false);
const loading = ref(true);

onMounted(async () => {
    try {
        const response = await axios.get('/api/admin/analytics');
        stats.value = response.data.data;
    } catch (e: any) {
        if (e.response?.status === 403) {
            isLocked.value = true;
        }
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <Head title="التحليلات" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">التقارير والتحليلات المتقدمة</h2>
        </template>
        <div class="mx-auto max-w-5xl p-6" dir="rtl">
            <AdminNav />
            
            <div v-if="loading" class="text-center py-10">
                <p class="text-stone-500">جاري تحميل البيانات...</p>
            </div>

            <!-- Locked State -->
            <div v-else-if="isLocked" class="bg-white rounded-2xl p-10 text-center shadow-sm border border-stone-100 max-w-2xl mx-auto mt-10">
                <div class="bg-amber-100 text-amber-600 w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-10"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                </div>
                <h3 class="text-2xl font-bold text-gray-800 mb-2">هذه الميزة غير مفعلة لمطعمك</h3>
                <p class="text-stone-500 mb-6 leading-relaxed">
                    ميزة التقارير والتحليلات المتقدمة تتيح لك مراقبة مبيعاتك، أرباحك، وأكثر الوجبات مبيعاً بشكل لحظي مما يساعدك على اتخاذ قرارات أفضل لمطعمك.
                </p>
                <button class="bg-amber-600 text-white px-8 py-3 rounded-xl font-semibold hover:bg-amber-700 transition shadow-lg shadow-amber-600/20">
                    تواصل مع الإدارة لتفعيل الميزة
                </button>
            </div>

            <!-- Analytics State -->
            <div v-else>
                <!-- Overview Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-stone-100 flex flex-col justify-center">
                        <p class="text-sm text-stone-500 mb-1">إجمالي المبيعات</p>
                        <p class="text-3xl font-bold text-green-600">{{ stats.total_revenue }} <span class="text-sm font-normal text-stone-400">SAR</span></p>
                    </div>
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-stone-100 flex flex-col justify-center">
                        <p class="text-sm text-stone-500 mb-1">إجمالي الطلبات المكتملة</p>
                        <p class="text-3xl font-bold text-blue-600">{{ stats.total_orders }}</p>
                    </div>
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-stone-100 flex flex-col justify-center">
                        <p class="text-sm text-stone-500 mb-1">متوسط قيمة الطلب (AOV)</p>
                        <p class="text-3xl font-bold text-purple-600">{{ Math.round(stats.average_order_value) }} <span class="text-sm font-normal text-stone-400">SAR</span></p>
                    </div>
                </div>

                <!-- Top Items -->
                <div class="bg-white rounded-2xl shadow-sm border border-stone-100 p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">أكثر 5 أصناف مبيعاً</h3>
                    <div class="space-y-4">
                        <div v-if="stats.top_items.length === 0" class="text-stone-500 text-sm py-4">لا توجد مبيعات كافية حتى الآن.</div>
                        <div v-for="(item, index) in stats.top_items" :key="index" class="flex items-center justify-between border-b border-stone-50 pb-3 last:border-0 last:pb-0">
                            <div class="flex items-center gap-3">
                                <div class="bg-amber-100 text-amber-700 w-8 h-8 rounded flex items-center justify-center font-bold text-sm">{{ index + 1 }}</div>
                                <p class="font-medium text-gray-800">{{ item.name }}</p>
                            </div>
                            <div class="text-left">
                                <p class="text-sm font-semibold text-gray-800">{{ item.total_quantity }} طلب</p>
                                <p class="text-xs text-stone-400">{{ item.total_revenue }} SAR</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
