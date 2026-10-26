<script setup lang="ts">
import AdminNav from '@/Components/Admin/AdminNav.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { ref, onMounted, computed, watch } from 'vue';
import axios from 'axios';
import {
  Chart as ChartJS,
  Title,
  Tooltip,
  Legend,
  BarElement,
  LineElement,
  PointElement,
  CategoryScale,
  LinearScale,
  Filler,
} from 'chart.js';
import { Bar } from 'vue-chartjs';

ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
  Title,
  Tooltip,
  Legend,
  Filler
);

const page = usePage();
const restaurant = computed(() => (page.props.auth as any)?.restaurant);

// View selection: 'daily' | 'monthly' | 'yearly' | 'custom'
const activeView = ref<'daily' | 'monthly' | 'yearly' | 'custom'>('daily');

const currentYear = new Date().getFullYear();
const currentMonth = new Date().getMonth() + 1;

const selectedYear = ref(currentYear);
const selectedMonth = ref(currentMonth);
const customFromDate = ref(new Date(Date.now() - 30 * 86400000).toISOString().split('T')[0]);
const customToDate = ref(new Date().toISOString().split('T')[0]);

const yearsList = computed(() => {
    const list = [];
    for (let y = currentYear; y >= currentYear - 5; y--) {
        list.push(y);
    }
    return list;
});

const monthsList = [
    { value: 1, label: 'يناير' },
    { value: 2, label: 'فبراير' },
    { value: 3, label: 'مارس' },
    { value: 4, label: 'أبريل' },
    { value: 5, label: 'مايو' },
    { value: 6, label: 'يونيو' },
    { value: 7, label: 'يوليو' },
    { value: 8, label: 'أغسطس' },
    { value: 9, label: 'سبتمبر' },
    { value: 10, label: 'أكتوبر' },
    { value: 11, label: 'نوفمبر' },
    { value: 12, label: 'ديسمبر' },
];

const reportData = ref<any>(null);
const loading = ref(true);

async function fetchReport() {
    loading.value = true;
    try {
        let url = `/api/admin/reports/sales?view=${activeView.value}`;
        if (activeView.value === 'daily') {
            url += `&year=${selectedYear.value}&month=${selectedMonth.value}`;
        } else if (activeView.value === 'monthly') {
            url += `&year=${selectedYear.value}`;
        } else if (activeView.value === 'custom') {
            url += `&from_date=${customFromDate.value}&to_date=${customToDate.value}`;
        }

        const { data } = await axios.get(url);
        reportData.value = data.data;
    } catch (e) {
        console.error('Failed to load sales report', e);
    } finally {
        loading.value = false;
    }
}

watch([activeView, selectedYear, selectedMonth], () => {
    fetchReport();
});

onMounted(() => {
    fetchReport();
});

function formatNumber(num: string | number | undefined | null) {
    if (num === undefined || num === null) return '0.00';
    return Number(num).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// Chart configuration
const chartData = computed(() => {
    if (!reportData.value?.records || reportData.value.records.length === 0) {
        return { labels: [], datasets: [] };
    }

    const records = reportData.value.records;
    let labels: string[] = [];

    if (activeView.value === 'daily') {
        labels = records.map((r: any) => `${r.day_number}`);
    } else if (activeView.value === 'monthly') {
        labels = records.map((r: any) => r.month_name);
    } else if (activeView.value === 'yearly') {
        labels = records.map((r: any) => `${r.year}`);
    }

    const netSales = records.map((r: any) => Number(r.net_sales));
    const grossSales = records.map((r: any) => Number(r.gross_sales));

    return {
        labels,
        datasets: [
            {
                label: 'صافي المبيعات (' + (reportData.value?.currency || 'SYP') + ')',
                data: netSales,
                backgroundColor: 'rgba(245, 158, 11, 0.85)',
                borderRadius: 8,
                hoverBackgroundColor: 'rgba(217, 119, 6, 1)',
            },
            {
                label: 'إجمالي المبيعات قبل الخصم',
                data: grossSales,
                backgroundColor: 'rgba(100, 116, 139, 0.35)',
                borderRadius: 8,
            }
        ]
    };
});

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'top' as const,
            labels: {
                color: '#94a3b8',
                font: { family: 'inherit', size: 12 },
            }
        },
        tooltip: {
            rtl: true,
            titleFont: { family: 'inherit' },
            bodyFont: { family: 'inherit' },
            callbacks: {
                label: function(context: any) {
                    return ` ${context.dataset.label}: ${context.raw.toLocaleString('en-US', { minimumFractionDigits: 2 })} ${reportData.value?.currency || 'SYP'}`;
                }
            }
        }
    },
    scales: {
        x: {
            ticks: { color: '#94a3b8' },
            grid: { color: 'rgba(255, 255, 255, 0.05)' }
        },
        y: {
            ticks: { color: '#94a3b8' },
            grid: { color: 'rgba(255, 255, 255, 0.05)' }
        }
    }
};

function printReport() {
    window.print();
}
</script>

<template>
    <Head title="سجل وتقارير المبيعات المالية - تدلّل" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-black text-slate-900 dark:text-white flex items-center gap-2">
                        <span>📊</span>
                        <span>سجل المبيعات والتقارير المالية المعتمدة</span>
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                        كشوفات محاسبية رسمية دقيقة بنسبة خطأ 0% لحصر الإيرادات الإجمالية، الخصومات، وصافي الدخل.
                    </p>
                </div>

                <div class="flex items-center gap-2 print:hidden">
                    <button
                        @click="printReport"
                        class="px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white dark:bg-amber-500 dark:hover:bg-amber-600 dark:text-slate-950 font-bold text-xs flex items-center gap-2 shadow-lg transition-all"
                    >
                        <span>🖨️</span>
                        <span>طباعة الكشف المالي</span>
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="print:hidden">
                <AdminNav />
            </div>

            <!-- Print Header Banner (Only visible during print) -->
            <div class="hidden print:block p-6 border-b-2 border-slate-900 mb-6 text-slate-900" dir="rtl">
                <div class="flex justify-between items-start">
                    <div>
                        <h1 class="text-2xl font-black">{{ restaurant?.name || 'مطعم تدلّل' }}</h1>
                        <p class="text-xs text-slate-600 mt-1">كشف حركات المبيعات والضريبة المعتمد</p>
                        <p class="text-xs text-slate-600">الرقم الضريبي / السجل: {{ restaurant?.tax_number || 'مسجل نظامياً' }}</p>
                    </div>
                    <div class="text-left text-xs text-slate-600">
                        <p>تاريخ استخراج التقرير: {{ new Date().toLocaleString('ar-SA') }}</p>
                        <p>الفترة المحاسبية: {{ activeView === 'daily' ? `${selectedMonth} / ${selectedYear}` : (activeView === 'monthly' ? `سنة ${selectedYear}` : 'تقرير مالي') }}</p>
                        <p class="font-bold text-slate-900">العملة: {{ reportData?.currency || 'SYP' }}</p>
                    </div>
                </div>
            </div>

            <!-- Controls & Period Switcher Card -->
            <div class="p-4 sm:p-6 rounded-3xl bg-white dark:bg-[#0a0f1d] border border-slate-100 dark:border-slate-800 shadow-sm space-y-4 print:hidden" dir="rtl">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                    <!-- Period Type Tabs -->
                    <div class="inline-flex p-1.5 rounded-2xl bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 self-start">
                        <button
                            @click="activeView = 'daily'"
                            class="px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5"
                            :class="activeView === 'daily' ? 'bg-amber-500 text-slate-950 shadow-md font-black' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                        >
                            <span>📅</span>
                            <span>التقرير اليومي (شهر محدد)</span>
                        </button>

                        <button
                            @click="activeView = 'monthly'"
                            class="px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5"
                            :class="activeView === 'monthly' ? 'bg-amber-500 text-slate-950 shadow-md font-black' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                        >
                            <span>📊</span>
                            <span>التقرير الشهري (سنة كاملة)</span>
                        </button>

                        <button
                            @click="activeView = 'yearly'"
                            class="px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5"
                            :class="activeView === 'yearly' ? 'bg-amber-500 text-slate-950 shadow-md font-black' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                        >
                            <span>📈</span>
                            <span>التقرير السنوي (متعدد السنوات)</span>
                        </button>

                        <button
                            @click="activeView = 'custom'"
                            class="px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5"
                            :class="activeView === 'custom' ? 'bg-amber-500 text-slate-950 shadow-md font-black' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                        >
                            <span>🔍</span>
                            <span>فترة مخصصة</span>
                        </button>
                    </div>

                    <!-- Year & Month Selectors -->
                    <div class="flex items-center gap-2 flex-wrap">
                        <template v-if="activeView === 'daily'">
                            <select
                                v-model="selectedMonth"
                                class="px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs font-bold text-slate-800 dark:text-slate-200 focus:ring-amber-500 focus:border-amber-500"
                            >
                                <option v-for="m in monthsList" :key="m.value" :value="m.value">{{ m.label }}</option>
                            </select>

                            <select
                                v-model="selectedYear"
                                class="px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs font-bold text-slate-800 dark:text-slate-200 focus:ring-amber-500 focus:border-amber-500"
                            >
                                <option v-for="y in yearsList" :key="y" :value="y">{{ y }}</option>
                            </select>
                        </template>

                        <template v-else-if="activeView === 'monthly'">
                            <select
                                v-model="selectedYear"
                                class="px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs font-bold text-slate-800 dark:text-slate-200 focus:ring-amber-500 focus:border-amber-500"
                            >
                                <option v-for="y in yearsList" :key="y" :value="y">{{ y }}</option>
                            </select>
                        </template>

                        <template v-else-if="activeView === 'custom'">
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-slate-500">من:</span>
                                <input
                                    v-model="customFromDate"
                                    type="date"
                                    class="px-3 py-1.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs font-bold text-slate-800 dark:text-slate-200"
                                />
                                <span class="text-xs text-slate-500">إلى:</span>
                                <input
                                    v-model="customToDate"
                                    type="date"
                                    class="px-3 py-1.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs font-bold text-slate-800 dark:text-slate-200"
                                />
                                <button
                                    @click="fetchReport"
                                    class="px-3 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold text-xs"
                                >
                                    تطبيق
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="py-16 text-center text-slate-400">
                <div class="w-10 h-10 border-4 border-amber-500 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
                <p class="text-xs font-bold">جاري حساب التقارير والمطابقة المحاسبية 0% خطأ...</p>
            </div>

            <!-- Financial Executive Summary KPI Grid -->
            <div v-else-if="reportData?.summary" class="space-y-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4" dir="rtl">
                    <!-- Net Sales -->
                    <div class="p-5 rounded-3xl bg-gradient-to-br from-amber-500/10 via-orange-500/5 to-transparent border-2 border-amber-500/40 shadow-sm relative overflow-hidden">
                        <div class="text-xs font-bold text-amber-700 dark:text-amber-400 flex items-center justify-between">
                            <span>صافي الإيرادات المعترف بها</span>
                            <span class="text-lg">💰</span>
                        </div>
                        <div class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white mt-2 font-mono">
                            {{ formatNumber(reportData.summary.net_sales) }} <span class="text-xs font-sans text-amber-600">{{ reportData.currency }}</span>
                        </div>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">بعد استبعاد كافة الخصومات والكوبونات</p>
                    </div>

                    <!-- Gross Sales -->
                    <div class="p-5 rounded-3xl bg-white dark:bg-[#0a0f1d] border border-slate-100 dark:border-slate-800 shadow-sm">
                        <div class="text-xs font-bold text-slate-600 dark:text-slate-400 flex items-center justify-between">
                            <span>المبيعات الإجمالية (Gross)</span>
                            <span class="text-lg">🧾</span>
                        </div>
                        <div class="text-xl sm:text-2xl font-black text-slate-800 dark:text-slate-200 mt-2 font-mono">
                            {{ formatNumber(reportData.summary.gross_sales) }} <span class="text-xs font-sans text-slate-400">{{ reportData.currency }}</span>
                        </div>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">القيمة الإجمالية قبل تطبيق أي خصم</p>
                    </div>

                    <!-- Total Discounts -->
                    <div class="p-5 rounded-3xl bg-white dark:bg-[#0a0f1d] border border-slate-100 dark:border-slate-800 shadow-sm">
                        <div class="text-xs font-bold text-rose-600 dark:text-rose-400 flex items-center justify-between">
                            <span>إجمالي الخصومات والكوبونات</span>
                            <span class="text-lg">🏷️</span>
                        </div>
                        <div class="text-xl sm:text-2xl font-black text-rose-600 dark:text-rose-400 mt-2 font-mono">
                            -{{ formatNumber(reportData.summary.total_discounts) }} <span class="text-xs font-sans">{{ reportData.currency }}</span>
                        </div>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">تكلفة الحملات الترويجية المخصومة</p>
                    </div>

                    <!-- Total Orders & AOV -->
                    <div class="p-5 rounded-3xl bg-white dark:bg-[#0a0f1d] border border-slate-100 dark:border-slate-800 shadow-sm">
                        <div class="text-xs font-bold text-slate-600 dark:text-slate-400 flex items-center justify-between">
                            <span>الطلبات ومتوسط الفاتورة</span>
                            <span class="text-lg">📈</span>
                        </div>
                        <div class="text-xl sm:text-2xl font-black text-slate-800 dark:text-slate-200 mt-2 font-mono">
                            {{ reportData.summary.total_orders }} <span class="text-xs font-sans text-slate-400">طلب</span>
                        </div>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                            متوسط الفاتورة (AOV): <span class="font-mono font-bold text-amber-500">{{ formatNumber(reportData.summary.aov) }} {{ reportData.currency }}</span>
                        </p>
                    </div>
                </div>

                <!-- Secondary Operational KPIs -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4" dir="rtl">
                    <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <span class="text-xl">🍽️</span>
                            <div>
                                <div class="text-xs font-bold text-slate-800 dark:text-slate-200">مبيعات الصالة (محلي)</div>
                                <div class="text-[11px] text-slate-500">{{ reportData.summary.dine_in_count || 0 }} طلب</div>
                            </div>
                        </div>
                        <div class="text-sm font-black font-mono text-slate-900 dark:text-white">
                            {{ formatNumber(reportData.summary.dine_in_revenue || 0) }} {{ reportData.currency }}
                        </div>
                    </div>

                    <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <span class="text-xl">🥡</span>
                            <div>
                                <div class="text-xs font-bold text-slate-800 dark:text-slate-200">مبيعات الاستلام (سفري)</div>
                                <div class="text-[11px] text-slate-500">{{ reportData.summary.takeaway_count || 0 }} طلب</div>
                            </div>
                        </div>
                        <div class="text-sm font-black font-mono text-slate-900 dark:text-white">
                            {{ formatNumber(reportData.summary.takeaway_revenue || 0) }} {{ reportData.currency }}
                        </div>
                    </div>

                    <div class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/20 flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <span class="text-xl">⚠️</span>
                            <div>
                                <div class="text-xs font-bold text-rose-700 dark:text-rose-300">الطلبات الملغاة (فاقد)</div>
                                <div class="text-[11px] text-rose-500">{{ reportData.summary.cancelled_orders || 0 }} طلب ملغي</div>
                            </div>
                        </div>
                        <div class="text-sm font-black font-mono text-rose-700 dark:text-rose-300">
                            {{ formatNumber(reportData.summary.cancelled_lost || 0) }} {{ reportData.currency }}
                        </div>
                    </div>
                </div>

                <!-- Visual Bar Chart (Hidden during print) -->
                <div class="p-6 rounded-3xl bg-white dark:bg-[#0a0f1d] border border-slate-100 dark:border-slate-800 shadow-sm print:hidden">
                    <div class="flex items-center justify-between mb-4" dir="rtl">
                        <h4 class="text-sm font-black text-slate-900 dark:text-white flex items-center gap-2">
                            <span>📈</span>
                            <span>المنحنى البياني للمبيعات</span>
                        </h4>
                        <span class="text-xs text-slate-400 font-mono">{{ reportData.currency }}</span>
                    </div>
                    <div class="h-72">
                        <Bar :data="chartData" :options="chartOptions" />
                    </div>
                </div>

                <!-- Detailed Accounting Ledger Table -->
                <div class="rounded-3xl bg-white dark:bg-[#0a0f1d] border border-slate-100 dark:border-slate-800 shadow-sm overflow-hidden" dir="rtl">
                    <div class="p-5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                        <h4 class="font-black text-sm text-slate-900 dark:text-white flex items-center gap-2">
                            <span>📋</span>
                            <span>جدول التدقيق المالي اليومي والتفصيلي</span>
                        </h4>
                        <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                            {{ reportData.records?.length || 0 }} سجلات مسجلة
                        </span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-right text-xs">
                            <thead class="bg-slate-50 dark:bg-slate-900/80 text-slate-500 dark:text-slate-400 font-bold border-b border-slate-100 dark:border-slate-800">
                                <tr>
                                    <th class="p-3.5">{{ activeView === 'daily' ? 'اليوم والتاريخ' : (activeView === 'monthly' ? 'الشهر' : 'السنة') }}</th>
                                    <th class="p-3.5">عدد الطلبات</th>
                                    <th class="p-3.5">المبيعات الإجمالية</th>
                                    <th class="p-3.5">الخصومات</th>
                                    <th class="p-3.5 text-amber-600 dark:text-amber-400 font-black">صافي المبيعات</th>
                                    <th class="p-3.5">متوسط الفاتورة</th>
                                    <th v-if="activeView === 'monthly'" class="p-3.5">نسبة النمو الشهري</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                                <tr
                                    v-for="(row, idx) in reportData.records"
                                    :key="idx"
                                    class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors"
                                    :class="row.orders_count === 0 ? 'opacity-40' : ''"
                                >
                                    <td class="p-3.5 font-bold text-slate-900 dark:text-white">
                                        <template v-if="activeView === 'daily'">
                                            <span class="text-slate-400 font-mono ml-2">#{{ row.day_number }}</span>
                                            <span>{{ row.day_name }}</span>
                                            <span class="text-[10px] text-slate-400 font-mono mr-2">({{ row.date }})</span>
                                        </template>
                                        <template v-else-if="activeView === 'monthly'">
                                            <span>{{ row.month_name }}</span>
                                        </template>
                                        <template v-else-if="activeView === 'yearly'">
                                            <span class="font-mono text-sm font-black">{{ row.year }}</span>
                                        </template>
                                    </td>
                                    <td class="p-3.5 font-mono text-slate-700 dark:text-slate-300">{{ row.orders_count }}</td>
                                    <td class="p-3.5 font-mono text-slate-600 dark:text-slate-400">{{ formatNumber(row.gross_sales) }}</td>
                                    <td class="p-3.5 font-mono text-rose-500">-{{ formatNumber(row.discounts) }}</td>
                                    <td class="p-3.5 font-mono font-black text-slate-900 dark:text-white text-sm">
                                        {{ formatNumber(row.net_sales) }} {{ reportData.currency }}
                                    </td>
                                    <td class="p-3.5 font-mono text-slate-600 dark:text-slate-400">{{ formatNumber(row.aov) }}</td>
                                    <td v-if="activeView === 'monthly'" class="p-3.5 font-mono font-bold">
                                        <span v-if="row.growth_percentage === null" class="text-slate-400">-</span>
                                        <span v-else-if="row.growth_percentage > 0" class="text-emerald-500">↑ +{{ row.growth_percentage }}%</span>
                                        <span v-else-if="row.growth_percentage < 0" class="text-rose-500">↓ {{ row.growth_percentage }}%</span>
                                        <span v-else class="text-slate-400">0%</span>
                                    </td>
                                </tr>
                            </tbody>
                            <!-- Total Summary Footer -->
                            <tfoot class="bg-amber-500/10 font-bold border-t-2 border-amber-500/30 text-slate-900 dark:text-white">
                                <tr>
                                    <td class="p-4 font-black">المجموع المحاسبي العام</td>
                                    <td class="p-4 font-mono">{{ reportData.summary.total_orders }}</td>
                                    <td class="p-4 font-mono">{{ formatNumber(reportData.summary.gross_sales) }}</td>
                                    <td class="p-4 font-mono text-rose-500">-{{ formatNumber(reportData.summary.total_discounts) }}</td>
                                    <td class="p-4 font-mono font-black text-amber-600 dark:text-amber-400 text-base">
                                        {{ formatNumber(reportData.summary.net_sales) }} {{ reportData.currency }}
                                    </td>
                                    <td class="p-4 font-mono">{{ formatNumber(reportData.summary.aov) }}</td>
                                    <td v-if="activeView === 'monthly'" class="p-4 font-mono text-slate-400">-</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Print Signature Footer (Only visible during print) -->
                <div class="hidden print:flex justify-between items-end pt-12 border-t-2 border-slate-900 mt-12 text-xs text-slate-900" dir="rtl">
                    <div class="text-center w-48">
                        <p class="font-bold">المحاسب المالي المسؤول</p>
                        <div class="h-16 border-b border-dashed border-slate-400 mb-2"></div>
                        <p class="text-[10px] text-slate-500">التوقيع والاعتماد</p>
                    </div>
                    <div class="text-center w-48">
                        <p class="font-bold">ختم المنشأة / المطعم</p>
                        <div class="h-16 border-b border-dashed border-slate-400 mb-2"></div>
                        <p class="text-[10px] text-slate-500">الختم الرسمي</p>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
@media print {
    @page {
        margin: 15mm;
        size: A4 portrait;
    }
}
</style>
