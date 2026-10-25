<script setup lang="ts">
import AdminNav from '@/Components/Admin/AdminNav.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, watch, computed } from 'vue';
import axios from 'axios';
import {
  Chart as ChartJS,
  Title,
  Tooltip,
  Legend,
  LineElement,
  PointElement,
  CategoryScale,
  LinearScale,
  ArcElement,
  Filler,
} from 'chart.js';
import { Line, Doughnut } from 'vue-chartjs';

ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  ArcElement,
  Filler
);

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

const hourlyChartData = computed(() => {
    if (!stats.value?.hourly_distribution) return { labels: [], datasets: [] };
    
    return {
        labels: stats.value.hourly_distribution.map((h: any) => `${h.hour}:00`),
        datasets: [
            {
                label: 'عدد الطلبات',
                data: stats.value.hourly_distribution.map((h: any) => h.total_orders),
                borderColor: '#d97706',
                backgroundColor: 'rgba(217, 119, 6, 0.2)',
                borderWidth: 3,
                pointBackgroundColor: '#fff',
                pointBorderColor: '#d97706',
                pointRadius: 4,
                fill: true,
                tension: 0.4
            }
        ]
    };
});

const hourlyChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            titleFont: { family: 'Cairo' },
            bodyFont: { family: 'Cairo' }
        }
    },
    scales: {
        y: {
            beginAtZero: true,
            ticks: { precision: 0, font: { family: 'Cairo' } },
            grid: { color: '#f5f5f4' }
        },
        x: {
            ticks: { font: { family: 'Cairo' } },
            grid: { display: false }
        }
    }
};

const categoryChartData = computed(() => {
    if (!stats.value?.category_breakdown) return { labels: [], datasets: [] };
    
    const colors = [
        '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899',
    ];

    return {
        labels: stats.value.category_breakdown.map((c: any) => c.name),
        datasets: [
            {
                data: stats.value.category_breakdown.map((c: any) => c.total_revenue),
                backgroundColor: colors,
                borderWidth: 0,
                hoverOffset: 4
            }
        ]
    };
});

const categoryChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { 
            position: 'left' as const,
            rtl: true,
            labels: { font: { family: 'Cairo', size: 12 }, usePointStyle: true, padding: 20 }
        },
        tooltip: {
            callbacks: {
                label: (context: any) => ` ${formatCurrency(context.raw)} SR`
            },
            titleFont: { family: 'Cairo' },
            bodyFont: { family: 'Cairo' }
        }
    }
};
</script>

<template>
    <Head title="التحليلات الذكية" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800 dark:text-white">التحليلات الذكية</h2>
        </template>
        
        <div class="mx-auto max-w-7xl p-6" dir="rtl">
            <AdminNav />
            
            <!-- Locked State -->
            <div v-if="isLocked" class="bg-white dark:bg-[#0a0f1d] rounded-2xl p-10 text-center shadow-xs border border-stone-100 dark:border-slate-800 max-w-2xl mx-auto mt-10">
                <div class="bg-amber-100 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-10"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                </div>
                <h3 class="text-2xl font-bold text-gray-800 dark:text-white mb-2">هذه الميزة غير مفعلة لمطعمك</h3>
                <p class="text-stone-500 dark:text-stone-400 mb-6 leading-relaxed">
                    ميزة التقارير والتحليلات الذكية تتيح لك مراقبة مبيعاتك، أرباحك، وأداء الطاولات مع توصيات تلقائية لتحسين مبيعاتك.
                </p>
                <button class="bg-amber-600 dark:bg-amber-500 text-white dark:text-slate-950 px-8 py-3 rounded-xl font-semibold hover:bg-amber-700 dark:hover:bg-amber-400 transition shadow-lg shadow-amber-600/20">
                    تواصل مع الإدارة لتفعيل الميزة
                </button>
            </div>

            <!-- Dashboard Content -->
            <div v-else>
                <!-- Insights Section (Rule-based) -->
                <div v-if="insights.length > 0" class="mb-8 space-y-3">
                    <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-2 flex items-center gap-2">
                        <span>💡</span> تحليلات ذكية ومقترحات
                    </h3>
                    <div v-for="(insight, idx) in insights" :key="idx" 
                         class="p-4 rounded-xl border flex items-start gap-4 transition-colors"
                         :class="{
                             'bg-amber-50 dark:bg-amber-950/40 border-amber-200 dark:border-amber-800/60': insight.type === 'warning',
                             'bg-green-50 dark:bg-emerald-950/40 border-green-200 dark:border-emerald-800/60': insight.type === 'success',
                             'bg-blue-50 dark:bg-blue-950/40 border-blue-200 dark:border-blue-800/60': insight.type === 'info'
                         }">
                        <div class="mt-1">
                            <span v-if="insight.type === 'warning'" class="text-amber-500 text-xl">⚠️</span>
                            <span v-if="insight.type === 'success'" class="text-green-500 text-xl">📈</span>
                            <span v-if="insight.type === 'info'" class="text-blue-500 text-xl">ℹ️</span>
                        </div>
                        <div>
                            <h4 class="font-bold text-gray-800 dark:text-white mb-1">{{ insight.title }}</h4>
                            <p class="text-sm text-gray-600 dark:text-slate-300">{{ insight.description }}</p>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="flex items-center justify-between mb-6 bg-white dark:bg-[#0a0f1d] p-2 rounded-xl shadow-xs border border-stone-100 dark:border-slate-800">
                    <div class="flex gap-1">
                        <button 
                            v-for="p in periods" 
                            :key="p.value"
                            @click="period = p.value"
                            class="px-4 py-2 rounded-lg text-sm font-medium transition"
                            :class="period === p.value ? 'bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 font-bold' : 'text-stone-500 dark:text-slate-400 hover:bg-stone-50 dark:hover:bg-slate-800'"
                        >
                            {{ p.label }}
                        </button>
                    </div>
                </div>

                <div v-if="loading && !stats" class="text-center py-20">
                    <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-amber-600 mx-auto mb-4"></div>
                    <p class="text-stone-500 dark:text-slate-400">جاري تحليل البيانات...</p>
                </div>

                <div v-else-if="!loading && !stats" class="text-center py-20">
                    <div class="bg-red-50 dark:bg-red-950/50 text-red-500 dark:text-red-400 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-2">عذراً، حدث خطأ أثناء جلب البيانات</h3>
                    <p class="text-stone-500 dark:text-stone-400 mb-4">قد تكون هناك مشكلة في الاتصال أو في الخادم. يرجى المحاولة مرة أخرى.</p>
                    <button @click="fetchAnalytics" class="bg-red-600 text-white px-6 py-2 rounded-lg font-semibold hover:bg-red-700 transition">إعادة المحاولة</button>
                </div>

                <div v-else-if="stats">
                    <!-- KPI Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                        <div class="bg-white dark:bg-[#0a0f1d] p-5 rounded-xl shadow-xs border border-stone-100 dark:border-slate-800">
                            <p class="text-sm text-stone-500 dark:text-stone-400 mb-1">إجمالي المبيعات</p>
                            <p class="text-2xl font-bold text-green-600 dark:text-emerald-400">{{ formatCurrency(stats.total_revenue) }} <span class="text-xs text-stone-400">{{ $page.props.auth.restaurant?.currency }}</span></p>
                        </div>
                        <div class="bg-white dark:bg-[#0a0f1d] p-5 rounded-xl shadow-xs border border-stone-100 dark:border-slate-800">
                            <p class="text-sm text-stone-500 dark:text-stone-400 mb-1">الطلبات المكتملة</p>
                            <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ stats.total_orders }}</p>
                        </div>
                        <div class="bg-white dark:bg-[#0a0f1d] p-5 rounded-xl shadow-xs border border-stone-100 dark:border-slate-800">
                            <p class="text-sm text-stone-500 dark:text-stone-400 mb-1">متوسط قيمة الطلب (AOV)</p>
                            <p class="text-2xl font-bold text-purple-600 dark:text-purple-400">{{ formatCurrency(stats.average_order_value) }} <span class="text-xs text-stone-400">{{ $page.props.auth.restaurant?.currency }}</span></p>
                        </div>
                        <div class="bg-white dark:bg-[#0a0f1d] p-5 rounded-xl shadow-xs border border-stone-100 dark:border-slate-800">
                            <p class="text-sm text-stone-500 dark:text-stone-400 mb-1">معدل الإلغاء</p>
                            <p class="text-2xl font-bold" :class="stats.cancellation_rate > 10 ? 'text-red-600 dark:text-red-400' : 'text-stone-800 dark:text-stone-100'">
                                {{ formatCurrency(stats.cancellation_rate) }}%
                            </p>
                        </div>
                    </div>

                    <!-- Hourly Heatmap -->
                    <div class="bg-white dark:bg-[#0a0f1d] rounded-xl shadow-xs border border-stone-100 dark:border-slate-800 p-6 mb-6">
                        <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-6">توزيع الطلبات حسب الساعة (أوقات الذروة)</h3>
                        <div class="h-64 w-full">
                            <template v-if="stats.hourly_distribution?.length">
                                <Line :data="hourlyChartData" :options="hourlyChartOptions" />
                            </template>
                            <template v-else>
                                <div class="flex items-end gap-1 h-full w-full overflow-x-auto pb-2">
                                    <div v-for="i in 12" :key="i" class="flex-1 min-w-[20px] flex flex-col items-center justify-end">
                                        <div class="w-full bg-stone-100 dark:bg-slate-800 rounded-t-sm animate-pulse" style="height: 5%"></div>
                                        <div class="text-[10px] text-stone-300 dark:text-slate-600 mt-2 rotate-45 origin-top-left w-full text-center">{{ i * 2 }}:00</div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                        <!-- Top Items -->
                        <div class="bg-white dark:bg-[#0a0f1d] rounded-xl shadow-xs border border-stone-100 dark:border-slate-800 p-6">
                            <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4 text-green-700 dark:text-emerald-400 flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5"><path fill-rule="evenodd" d="M10 17a.75.75 0 0 1-.75-.75V5.612L5.29 9.77a.75.75 0 0 1-1.08-1.04l5.25-5.5a.75.75 0 0 1 1.08 0l5.25 5.5a.75.75 0 1 1-1.08 1.04l-3.96-4.158V16.25A.75.75 0 0 1 10 17Z" clip-rule="evenodd" /></svg>
                                أكثر الأصناف مبيعاً
                            </h3>
                            <div class="space-y-3">
                                <template v-if="stats.top_items?.length">
                                    <div v-for="(item, idx) in stats.top_items" :key="idx" class="flex justify-between items-center bg-stone-50 dark:bg-slate-800/60 p-3 rounded-lg hover:bg-stone-100 dark:hover:bg-slate-800 transition">
                                        <div class="flex items-center gap-3">
                                            <div class="w-6 h-6 rounded-full bg-green-100 dark:bg-emerald-950/80 text-green-700 dark:text-emerald-300 flex items-center justify-center text-xs font-bold">{{ idx + 1 }}</div>
                                            <span class="font-medium text-gray-800 dark:text-white">{{ item.name }}</span>
                                        </div>
                                        <div class="text-left">
                                            <div class="text-sm font-bold text-gray-800 dark:text-white">{{ item.total_quantity }} طلب</div>
                                            <div class="text-xs text-stone-500 dark:text-slate-400">{{ formatCurrency(item.total_revenue) }} SR</div>
                                        </div>
                                    </div>
                                </template>
                                <template v-else>
                                    <div v-for="i in 3" :key="i" class="flex justify-between items-center bg-stone-50/50 dark:bg-slate-800/40 border border-stone-100 dark:border-slate-800 border-dashed p-3 rounded-lg opacity-60">
                                        <div class="flex items-center gap-3">
                                            <div class="w-6 h-6 rounded-full bg-stone-200 dark:bg-slate-700 text-stone-400 dark:text-slate-400 flex items-center justify-center text-xs font-bold">{{ i }}</div>
                                            <div class="h-4 w-24 bg-stone-200 dark:bg-slate-700 rounded animate-pulse"></div>
                                        </div>
                                        <div class="text-left space-y-2">
                                            <div class="h-3 w-12 bg-stone-200 dark:bg-slate-700 rounded animate-pulse ml-auto"></div>
                                            <div class="h-2 w-16 bg-stone-200 dark:bg-slate-700 rounded animate-pulse ml-auto"></div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Worst Items -->
                        <div class="bg-white dark:bg-[#0a0f1d] rounded-xl shadow-xs border border-stone-100 dark:border-slate-800 p-6">
                            <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4 text-red-700 dark:text-red-400 flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5"><path fill-rule="evenodd" d="M10 3a.75.75 0 0 1 .75.75v10.638l3.96-4.158a.75.75 0 1 1 1.08 1.04l-5.25 5.5a.75.75 0 0 1-1.08 0l-5.25-5.5a.75.75 0 1 1 1.08-1.04l3.96 4.158V3.75A.75.75 0 0 1 10 3Z" clip-rule="evenodd" /></svg>
                                الأصناف الأقل طلباً
                            </h3>
                            <div class="space-y-3">
                                <template v-if="stats.worst_items?.length">
                                    <div v-for="(item, idx) in stats.worst_items" :key="idx" class="flex justify-between items-center bg-stone-50 dark:bg-slate-800/60 p-3 rounded-lg hover:bg-stone-100 dark:hover:bg-slate-800 transition">
                                        <div class="flex items-center gap-3">
                                            <div class="w-6 h-6 rounded-full bg-red-100 dark:bg-red-950/80 text-red-700 dark:text-red-300 flex items-center justify-center text-xs font-bold">{{ idx + 1 }}</div>
                                            <span class="font-medium text-gray-800 dark:text-white">{{ item.name }}</span>
                                        </div>
                                        <div class="text-left">
                                            <div class="text-sm font-bold text-gray-800 dark:text-white">{{ item.total_quantity }} طلب</div>
                                            <div class="text-xs text-stone-500 dark:text-slate-400">{{ formatCurrency(item.total_revenue) }} SR</div>
                                        </div>
                                    </div>
                                </template>
                                <template v-else>
                                    <div v-for="i in 3" :key="i" class="flex justify-between items-center bg-stone-50/50 dark:bg-slate-800/40 border border-stone-100 dark:border-slate-800 border-dashed p-3 rounded-lg opacity-60">
                                        <div class="flex items-center gap-3">
                                            <div class="w-6 h-6 rounded-full bg-stone-200 dark:bg-slate-700 text-stone-400 dark:text-slate-400 flex items-center justify-center text-xs font-bold">{{ i }}</div>
                                            <div class="h-4 w-24 bg-stone-200 dark:bg-slate-700 rounded animate-pulse"></div>
                                        </div>
                                        <div class="text-left space-y-2">
                                            <div class="h-3 w-12 bg-stone-200 dark:bg-slate-700 rounded animate-pulse ml-auto"></div>
                                            <div class="h-2 w-16 bg-stone-200 dark:bg-slate-700 rounded animate-pulse ml-auto"></div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Categories -->
                        <div class="bg-white dark:bg-[#0a0f1d] rounded-xl shadow-xs border border-stone-100 dark:border-slate-800 p-6">
                            <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">المبيعات حسب التصنيف</h3>
                            <div class="h-64 w-full mt-6 flex items-center justify-center">
                                <template v-if="stats.category_breakdown?.length">
                                    <Doughnut :data="categoryChartData" :options="categoryChartOptions" />
                                </template>
                                <template v-else>
                                    <div class="w-48 h-48 rounded-full border-8 border-stone-100 dark:border-slate-800 animate-pulse"></div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <!-- Top Tables -->
                    <div class="bg-white dark:bg-[#0a0f1d] rounded-xl shadow-xs border border-stone-100 dark:border-slate-800 p-6">
                        <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">أداء الطاولات (الأعلى مبيعاً)</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-right">
                                <thead class="text-xs text-stone-500 dark:text-slate-400 bg-stone-50 dark:bg-slate-800/80 uppercase">
                                    <tr>
                                        <th class="px-4 py-3 rounded-r-lg">الترتيب</th>
                                        <th class="px-4 py-3">اسم الطاولة</th>
                                        <th class="px-4 py-3">عدد الطلبات</th>
                                        <th class="px-4 py-3 rounded-l-lg">إجمالي الإيرادات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template v-if="stats.table_performance?.length">
                                        <tr v-for="(table, idx) in stats.table_performance" :key="idx" class="border-b border-stone-50 dark:border-slate-800 last:border-0 hover:bg-stone-50 dark:hover:bg-slate-800/50 transition">
                                            <td class="px-4 py-3 font-bold text-stone-400 dark:text-slate-500">#{{ idx + 1 }}</td>
                                            <td class="px-4 py-3 font-medium text-gray-800 dark:text-white">{{ table.name }}</td>
                                            <td class="px-4 py-3 text-stone-700 dark:text-slate-300">{{ table.total_orders }}</td>
                                            <td class="px-4 py-3 font-semibold text-green-600 dark:text-emerald-400">{{ formatCurrency(table.total_revenue) }} SR</td>
                                        </tr>
                                    </template>
                                    <template v-else>
                                        <tr v-for="i in 3" :key="i" class="border-b border-stone-50 dark:border-slate-800 border-dashed last:border-0 opacity-60">
                                            <td class="px-4 py-4"><div class="h-4 w-6 bg-stone-200 dark:bg-slate-700 rounded animate-pulse ml-auto"></div></td>
                                            <td class="px-4 py-4"><div class="h-4 w-20 bg-stone-200 dark:bg-slate-700 rounded animate-pulse ml-auto"></div></td>
                                            <td class="px-4 py-4"><div class="h-4 w-10 bg-stone-200 dark:bg-slate-700 rounded animate-pulse ml-auto"></div></td>
                                            <td class="px-4 py-4"><div class="h-4 w-16 bg-stone-200 dark:bg-slate-700 rounded animate-pulse ml-auto"></div></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
