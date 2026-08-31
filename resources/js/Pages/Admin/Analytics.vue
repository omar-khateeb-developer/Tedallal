<script setup lang="ts">
import AdminNav from '@/Components/Admin/AdminNav.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, watch } from 'vue';
import axios from 'axios';

const stats = ref<any>(null);
const insights = ref<any[]>([]);
const isLocked = ref(false);
const loading = ref(true);
const period = ref('all'); // all, today, week, month

const periods = [
    { value: 'today', label: 'اليوم' },
    { value: 'week', label: 'هذا الأسبوع' },
    { value: 'month', label: 'هذا الشهر' },
    { value: 'all', label: 'كل الأوقات' }
];

const fetchAnalytics = async () => {
    loading.value = true;
    try {
        const response = await axios.get(`/api/admin/analytics?period=${period.value}`);
        stats.value = response.data.data;
    } catch (e: any) {
        if (e.response?.status === 403) {
            isLocked.value = true;
        }
    } finally {
        loading.value = false;
    }
};

const fetchInsights = async () => {
    try {
        const response = await axios.get('/api/admin/insights');
        insights.value = response.data.data;
    } catch (e) {
        console.error('Failed to load insights', e);
    }
};

onMounted(() => {
    fetchAnalytics();
    fetchInsights();
});

watch(period, () => {
    fetchAnalytics();
});

const getMaxOrders = (hourlyData: any[]) => {
    if (!hourlyData || hourlyData.length === 0) return 1;
    return Math.max(...hourlyData.map(d => d.total_orders));
};

const formatCurrency = (value: number) => {
    return Number(value).toFixed(2);
};
</script>

<template>
    <Head title="التحليلات الذكية" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">التحليلات الذكية</h2>
        </template>
        
        <div class="mx-auto max-w-7xl p-6" dir="rtl">
            <AdminNav />
            
            <!-- Locked State -->
            <div v-if="isLocked" class="bg-white rounded-2xl p-10 text-center shadow-sm border border-stone-100 max-w-2xl mx-auto mt-10">
                <div class="bg-amber-100 text-amber-600 w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-10"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                </div>
                <h3 class="text-2xl font-bold text-gray-800 mb-2">هذه الميزة غير مفعلة لمطعمك</h3>
                <p class="text-stone-500 mb-6 leading-relaxed">
                    ميزة التقارير والتحليلات الذكية تتيح لك مراقبة مبيعاتك، أرباحك، وأداء الطاولات مع توصيات تلقائية لتحسين مبيعاتك.
                </p>
                <button class="bg-amber-600 text-white px-8 py-3 rounded-xl font-semibold hover:bg-amber-700 transition shadow-lg shadow-amber-600/20">
                    تواصل مع الإدارة لتفعيل الميزة
                </button>
            </div>

            <!-- Dashboard Content -->
            <div v-else>
                <!-- Insights Section (Rule-based) -->
                <div v-if="insights.length > 0" class="mb-8 space-y-3">
                    <h3 class="text-lg font-bold text-gray-800 mb-2 flex items-center gap-2">
                        <span>💡</span> تحليلات ذكية ومقترحات
                    </h3>
                    <div v-for="(insight, idx) in insights" :key="idx" 
                         class="p-4 rounded-xl border flex items-start gap-4"
                         :class="{
                             'bg-amber-50 border-amber-200': insight.type === 'warning',
                             'bg-green-50 border-green-200': insight.type === 'success',
                             'bg-blue-50 border-blue-200': insight.type === 'info'
                         }">
                        <div class="mt-1">
                            <span v-if="insight.type === 'warning'" class="text-amber-500 text-xl">⚠️</span>
                            <span v-if="insight.type === 'success'" class="text-green-500 text-xl">📈</span>
                            <span v-if="insight.type === 'info'" class="text-blue-500 text-xl">ℹ️</span>
                        </div>
                        <div>
                            <h4 class="font-bold text-gray-800 mb-1">{{ insight.title }}</h4>
                            <p class="text-sm text-gray-600">{{ insight.description }}</p>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="flex items-center justify-between mb-6 bg-white p-2 rounded-xl shadow-sm border border-stone-100">
                    <div class="flex gap-1">
                        <button 
                            v-for="p in periods" 
                            :key="p.value"
                            @click="period = p.value"
                            class="px-4 py-2 rounded-lg text-sm font-medium transition"
                            :class="period === p.value ? 'bg-amber-100 text-amber-700' : 'text-stone-500 hover:bg-stone-50'"
                        >
                            {{ p.label }}
                        </button>
                    </div>
                </div>

                <div v-if="loading && !stats" class="text-center py-20">
                    <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-amber-600 mx-auto mb-4"></div>
                    <p class="text-stone-500">جاري تحليل البيانات...</p>
                </div>

                <div v-else-if="stats">
                    <!-- KPI Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                        <div class="bg-white p-5 rounded-xl shadow-sm border border-stone-100">
                            <p class="text-sm text-stone-500 mb-1">إجمالي المبيعات</p>
                            <p class="text-2xl font-bold text-green-600">{{ formatCurrency(stats.total_revenue) }} <span class="text-xs text-stone-400">SAR</span></p>
                        </div>
                        <div class="bg-white p-5 rounded-xl shadow-sm border border-stone-100">
                            <p class="text-sm text-stone-500 mb-1">الطلبات المكتملة</p>
                            <p class="text-2xl font-bold text-blue-600">{{ stats.total_orders }}</p>
                        </div>
                        <div class="bg-white p-5 rounded-xl shadow-sm border border-stone-100">
                            <p class="text-sm text-stone-500 mb-1">متوسط قيمة الطلب (AOV)</p>
                            <p class="text-2xl font-bold text-purple-600">{{ formatCurrency(stats.average_order_value) }} <span class="text-xs text-stone-400">SAR</span></p>
                        </div>
                        <div class="bg-white p-5 rounded-xl shadow-sm border border-stone-100">
                            <p class="text-sm text-stone-500 mb-1">معدل الإلغاء</p>
                            <p class="text-2xl font-bold" :class="stats.cancellation_rate > 10 ? 'text-red-600' : 'text-stone-800'">
                                {{ formatCurrency(stats.cancellation_rate) }}%
                            </p>
                        </div>
                    </div>

                    <!-- Hourly Heatmap -->
                    <div class="bg-white rounded-xl shadow-sm border border-stone-100 p-6 mb-6">
                        <h3 class="text-lg font-bold text-gray-800 mb-6">توزيع الطلبات حسب الساعة (أوقات الذروة)</h3>
                        <div class="flex items-end gap-1 h-32 w-full overflow-x-auto pb-2">
                            <div v-for="hour in stats.hourly_distribution" :key="hour.hour" class="flex-1 min-w-[20px] flex flex-col items-center justify-end group relative">
                                <div class="absolute -top-10 bg-gray-800 text-white text-xs py-1 px-2 rounded opacity-0 group-hover:opacity-100 transition whitespace-nowrap z-10 pointer-events-none">
                                    {{ hour.total_orders }} طلب
                                </div>
                                <div class="w-full bg-amber-200 rounded-t-sm transition-all duration-500 hover:bg-amber-400"
                                     :style="`height: ${Math.max((hour.total_orders / getMaxOrders(stats.hourly_distribution)) * 100, 2)}%`"></div>
                                <div class="text-[10px] text-stone-400 mt-2 rotate-45 origin-top-left w-full text-center">
                                    {{ hour.hour }}:00
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                        <!-- Top Items -->
                        <div class="bg-white rounded-xl shadow-sm border border-stone-100 p-6">
                            <h3 class="text-lg font-bold text-gray-800 mb-4 text-green-700 flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5"><path fill-rule="evenodd" d="M10 17a.75.75 0 0 1-.75-.75V5.612L5.29 9.77a.75.75 0 0 1-1.08-1.04l5.25-5.5a.75.75 0 0 1 1.08 0l5.25 5.5a.75.75 0 1 1-1.08 1.04l-3.96-4.158V16.25A.75.75 0 0 1 10 17Z" clip-rule="evenodd" /></svg>
                                أكثر الأصناف مبيعاً
                            </h3>
                            <div class="space-y-3">
                                <div v-if="!stats.top_items.length" class="text-stone-400 text-sm py-4">لا توجد مبيعات كافية.</div>
                                <div v-for="(item, idx) in stats.top_items" :key="idx" class="flex justify-between items-center bg-stone-50 p-3 rounded-lg">
                                    <div class="flex items-center gap-3">
                                        <div class="w-6 h-6 rounded-full bg-green-100 text-green-700 flex items-center justify-center text-xs font-bold">{{ idx + 1 }}</div>
                                        <span class="font-medium text-gray-800">{{ item.name }}</span>
                                    </div>
                                    <div class="text-left">
                                        <div class="text-sm font-bold text-gray-800">{{ item.total_quantity }} طلب</div>
                                        <div class="text-xs text-stone-500">{{ formatCurrency(item.total_revenue) }} SR</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Worst Items -->
                        <div class="bg-white rounded-xl shadow-sm border border-stone-100 p-6">
                            <h3 class="text-lg font-bold text-gray-800 mb-4 text-red-700 flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5"><path fill-rule="evenodd" d="M10 3a.75.75 0 0 1 .75.75v10.638l3.96-4.158a.75.75 0 1 1 1.08 1.04l-5.25 5.5a.75.75 0 0 1-1.08 0l-5.25-5.5a.75.75 0 1 1 1.08-1.04l3.96 4.158V3.75A.75.75 0 0 1 10 3Z" clip-rule="evenodd" /></svg>
                                الأصناف الأقل طلباً
                            </h3>
                            <div class="space-y-3">
                                <div v-if="!stats.worst_items.length" class="text-stone-400 text-sm py-4">لا توجد بيانات كافية.</div>
                                <div v-for="(item, idx) in stats.worst_items" :key="idx" class="flex justify-between items-center bg-stone-50 p-3 rounded-lg">
                                    <div class="flex items-center gap-3">
                                        <div class="w-6 h-6 rounded-full bg-red-100 text-red-700 flex items-center justify-center text-xs font-bold">{{ idx + 1 }}</div>
                                        <span class="font-medium text-gray-800">{{ item.name }}</span>
                                    </div>
                                    <div class="text-left">
                                        <div class="text-sm font-bold text-gray-800">{{ item.total_quantity }} طلب</div>
                                        <div class="text-xs text-stone-500">{{ formatCurrency(item.total_revenue) }} SR</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Categories -->
                        <div class="bg-white rounded-xl shadow-sm border border-stone-100 p-6">
                            <h3 class="text-lg font-bold text-gray-800 mb-4">المبيعات حسب التصنيف</h3>
                            <div class="space-y-4 mt-6">
                                <div v-if="!stats.category_breakdown.length" class="text-stone-400 text-sm py-4">لا توجد بيانات كافية.</div>
                                <div v-for="(cat, idx) in stats.category_breakdown" :key="idx" class="relative pt-1">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-sm font-medium text-gray-800">{{ cat.name }}</span>
                                        <span class="text-xs font-semibold text-stone-500">{{ formatCurrency(cat.total_revenue) }} SR</span>
                                    </div>
                                    <div class="overflow-hidden h-2 mb-4 text-xs flex rounded-full bg-stone-100">
                                        <div :style="`width: ${Math.min((cat.total_revenue / stats.total_revenue) * 100, 100)}%`" 
                                             class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-blue-500 rounded-full">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Top Tables -->
                    <div class="bg-white rounded-xl shadow-sm border border-stone-100 p-6">
                        <h3 class="text-lg font-bold text-gray-800 mb-4">أداء الطاولات (الأعلى مبيعاً)</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-right">
                                <thead class="text-xs text-stone-500 bg-stone-50 uppercase">
                                    <tr>
                                        <th class="px-4 py-3 rounded-r-lg">الترتيب</th>
                                        <th class="px-4 py-3">اسم الطاولة</th>
                                        <th class="px-4 py-3">عدد الطلبات</th>
                                        <th class="px-4 py-3 rounded-l-lg">إجمالي الإيرادات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="!stats.table_performance.length">
                                        <td colspan="4" class="px-4 py-8 text-center text-stone-400">لا توجد بيانات للطاولات في هذه الفترة.</td>
                                    </tr>
                                    <tr v-for="(table, idx) in stats.table_performance" :key="idx" class="border-b border-stone-50 last:border-0">
                                        <td class="px-4 py-3 font-bold text-stone-400">#{{ idx + 1 }}</td>
                                        <td class="px-4 py-3 font-medium text-gray-800">{{ table.name }}</td>
                                        <td class="px-4 py-3">{{ table.total_orders }}</td>
                                        <td class="px-4 py-3 font-semibold text-green-600">{{ formatCurrency(table.total_revenue) }} SR</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
