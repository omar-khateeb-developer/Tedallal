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

function exportReport() {
    let url = `/api/admin/reports/sales/export?view=${activeView.value}`;
    if (activeView.value === 'daily') {
        url += `&year=${selectedYear.value}&month=${selectedMonth.value}`;
    } else if (activeView.value === 'monthly') {
        url += `&year=${selectedYear.value}`;
    } else if (activeView.value === 'custom') {
        url += `&from_date=${customFromDate.value}&to_date=${customToDate.value}`;
    }
    window.open(url, '_blank');
}

const showZReportModal = ref(false);
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

                <div class="flex items-center gap-2 print:hidden flex-wrap">
                    <button
                        @click="showZReportModal = true"
                        class="px-4 py-2 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs flex items-center gap-1.5 shadow-md transition-all active:scale-95"
                    >
                        <span>⚡</span>
                        <span>كشف الوردية اللحظي (Z-Report)</span>
                    </button>

                    <button
                        @click="exportReport"
                        class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs flex items-center gap-1.5 shadow-md transition-all active:scale-95"
                    >
                        <span>📥</span>
                        <span>تصدير Excel / CSV</span>
                    </button>

                    <button
                        @click="printReport"
                        class="px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white dark:bg-amber-500 dark:hover:bg-amber-600 dark:text-slate-950 font-bold text-xs flex items-center gap-1.5 shadow-md transition-all active:scale-95"
                    >
                        <span>🖨️</span>
                        <span>طباعة الكشف</span>
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
                        <div v-if="(parseFloat(reportData.summary.loyalty_discounts || 0) > 0) || (parseFloat(reportData.summary.wheel_discounts || 0) > 0)" class="mt-2 pt-2 border-t border-slate-100 dark:border-slate-800/80 space-y-0.5 text-[10px]">
                            <div class="flex justify-between text-purple-600 dark:text-purple-400 font-bold">
                                <span>🏆 جوائز الولاء ({{ reportData.summary.loyalty_orders_count || 0 }}):</span>
                                <span class="font-mono">-{{ formatNumber(reportData.summary.loyalty_discounts || 0) }} {{ reportData.currency }}</span>
                            </div>
                            <div class="flex justify-between text-amber-600 dark:text-amber-400 font-bold">
                                <span>🎡 عجلة الحظ ({{ reportData.summary.wheel_orders_count || 0 }}):</span>
                                <span class="font-mono">-{{ formatNumber(reportData.summary.wheel_discounts || 0) }} {{ reportData.currency }}</span>
                            </div>
                        </div>
                        <p v-else class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">تكلفة الحملات الترويجية المخصومة</p>
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

                <!-- Smart Analytics: Peak Hours & Top Dishes (Hidden during print) -->
                <div v-if="reportData?.analytics" class="grid grid-cols-1 lg:grid-cols-2 gap-6 print:hidden" dir="rtl">
                    <!-- Peak Hours Card -->
                    <div class="p-6 rounded-3xl bg-white dark:bg-[#0a0f1d] border border-slate-100 dark:border-slate-800 shadow-sm space-y-4">
                        <div class="flex items-center justify-between">
                            <h4 class="font-black text-sm text-slate-900 dark:text-white flex items-center gap-2">
                                <span>⏰</span>
                                <span>ساعات الذروة الذهبية (Peak Hours)</span>
                            </h4>
                            <span v-if="reportData.analytics.peak_hour" class="px-2.5 py-1 rounded-full text-[11px] font-black bg-amber-500/15 text-amber-500 border border-amber-500/30">
                                الذروة: {{ reportData.analytics.peak_hour.label }}
                            </span>
                        </div>

                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            توزيع الحركات البيانية لمساعدة الإدارة في تنظيم طاقم الخدمة وجاهزية المطبخ.
                        </p>

                        <!-- Top 4 Busy Hours Grid -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 pt-2">
                            <div
                                v-for="h in (reportData.analytics.hourly || []).filter(item => item.orders_count > 0).sort((a,b) => parseFloat(b.net_sales) - parseFloat(a.net_sales)).slice(0, 4)"
                                :key="h.hour"
                                class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200/80 dark:border-slate-800 text-center"
                            >
                                <span class="text-xs font-black font-mono text-amber-600 dark:text-amber-400 block">{{ h.label }}</span>
                                <span class="text-xs font-bold text-slate-800 dark:text-slate-200 mt-1 block">{{ formatNumber(h.net_sales) }} {{ reportData.currency }}</span>
                                <span class="text-[10px] text-slate-400 block">{{ h.orders_count }} طلب</span>
                            </div>
                        </div>
                    </div>

                    <!-- Top 5 Revenue Dishes -->
                    <div class="p-6 rounded-3xl bg-white dark:bg-[#0a0f1d] border border-slate-100 dark:border-slate-800 shadow-sm space-y-4">
                        <div class="flex items-center justify-between">
                            <h4 class="font-black text-sm text-slate-900 dark:text-white flex items-center gap-2">
                                <span>🍽️</span>
                                <span>الأطباق الأكثر مساهمة في الدخل</span>
                            </h4>
                            <span class="text-xs text-slate-400">أفضل 5 أطباق</span>
                        </div>

                        <div v-if="reportData.analytics.top_dishes?.length === 0" class="py-8 text-center text-xs text-slate-400 font-medium">
                            لا توجد بيانات أصناف كافية للفترة المحددة.
                        </div>

                        <div v-else class="space-y-3 pt-1">
                            <div
                                v-for="(dish, dIdx) in reportData.analytics.top_dishes"
                                :key="dIdx"
                                class="space-y-1"
                            >
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                        <span class="w-5 h-5 rounded-full bg-amber-500/15 text-amber-500 text-[10px] font-black flex items-center justify-center font-mono">
                                            #{{ dIdx + 1 }}
                                        </span>
                                        <span>{{ dish.name }}</span>
                                    </span>
                                    <span class="font-mono font-bold text-slate-800 dark:text-slate-200">
                                        {{ formatNumber(dish.revenue) }} {{ reportData.currency }}
                                        <span class="text-[10px] text-slate-400 font-sans">({{ dish.quantity }} قطعة)</span>
                                    </span>
                                </div>
                                <div class="w-full h-1.5 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                                    <div
                                        class="h-full bg-gradient-to-l from-amber-500 to-amber-600 rounded-full transition-all duration-500"
                                        :style="{ width: `${Math.min(dish.share_percentage || 5, 100)}%` }"
                                    ></div>
                                </div>
                            </div>
                        </div>
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

        <!-- Instant Z-Report Modal -->
        <div
            v-if="showZReportModal && reportData?.summary"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm print:hidden"
            dir="rtl"
        >
            <div class="relative w-full max-w-md bg-white dark:bg-[#0c1222] rounded-3xl border border-slate-200 dark:border-slate-800 shadow-2xl p-6 overflow-hidden space-y-5 animate-in fade-in zoom-in-95 duration-200">
                <!-- Modal Header -->
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-4">
                    <div class="flex items-center gap-2.5">
                        <span class="w-9 h-9 rounded-2xl bg-purple-500/15 text-purple-600 dark:text-purple-400 flex items-center justify-center text-lg">⚡</span>
                        <div>
                            <h3 class="font-black text-slate-900 dark:text-white text-base">كشف الوردية اللحظي (Z-Report)</h3>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">مطابقة رصيد الصندوق وإغلاق الوردية المحاسبي</p>
                        </div>
                    </div>
                    <button
                        @click="showZReportModal = false"
                        class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 hover:text-slate-900 dark:hover:text-white flex items-center justify-center text-sm font-bold transition-colors"
                    >
                        ✕
                    </button>
                </div>

                <!-- Receipt Styled Paper (Thermal Style) -->
                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/90 border border-dashed border-slate-300 dark:border-slate-700 font-mono text-xs space-y-3">
                    <div class="text-center border-b border-dashed border-slate-300 dark:border-slate-700 pb-2">
                        <p class="font-black font-sans text-sm text-slate-900 dark:text-white">{{ restaurant?.name || 'منظومة تدلّل' }}</p>
                        <p class="text-[10px] text-slate-500 font-sans">كشف إغلاق الصندوق المالي المعتمد</p>
                        <p class="text-[10px] text-slate-400 mt-1">{{ new Date().toLocaleString('ar-SA') }}</p>
                    </div>

                    <div class="space-y-1.5 text-slate-700 dark:text-slate-300">
                        <div class="flex justify-between">
                            <span class="font-sans">إجمالي المبيعات (Gross):</span>
                            <span class="font-bold">{{ formatNumber(reportData.summary.gross_sales) }} {{ reportData.currency }}</span>
                        </div>
                        <div class="flex justify-between text-rose-500">
                            <span class="font-sans">إجمالي الخصومات:</span>
                            <span class="font-bold">-{{ formatNumber(reportData.summary.total_discounts) }} {{ reportData.currency }}</span>
                        </div>
                        <div v-if="parseFloat(reportData.summary.wheel_discounts || 0) > 0" class="flex justify-between text-[11px] text-amber-500 pr-3">
                            <span class="font-sans">منها خصومات العجلة:</span>
                            <span>-{{ formatNumber(reportData.summary.wheel_discounts) }} {{ reportData.currency }}</span>
                        </div>
                        <div v-if="parseFloat(reportData.summary.loyalty_discounts || 0) > 0" class="flex justify-between text-[11px] text-purple-400 pr-3">
                            <span class="font-sans">منها جوائز الولاء:</span>
                            <span>-{{ formatNumber(reportData.summary.loyalty_discounts) }} {{ reportData.currency }}</span>
                        </div>
                    </div>

                    <div class="pt-2 border-t-2 border-dashed border-slate-300 dark:border-slate-700 flex justify-between items-center text-sm font-black text-amber-600 dark:text-amber-400">
                        <span class="font-sans">صافي الصندوق النهائي:</span>
                        <span>{{ formatNumber(reportData.summary.net_sales) }} {{ reportData.currency }}</span>
                    </div>

                    <div class="pt-2 border-t border-dashed border-slate-300 dark:border-slate-700 space-y-1 text-[11px] text-slate-500 dark:text-slate-400">
                        <div class="flex justify-between">
                            <span class="font-sans">الفواتير المكتملة:</span>
                            <span class="font-bold font-mono text-slate-800 dark:text-slate-200">{{ reportData.summary.total_orders }} طلب</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="font-sans">متوسط الفاتورة (AOV):</span>
                            <span class="font-bold font-mono">{{ formatNumber(reportData.summary.aov) }} {{ reportData.currency }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="font-sans">طلبات الصالة / سفري:</span>
                            <span class="font-mono">{{ reportData.summary.dine_in_count || 0 }} صالة • {{ reportData.summary.takeaway_count || 0 }} سفري</span>
                        </div>
                        <div class="flex justify-between text-rose-500">
                            <span class="font-sans">الفاقد من الإلغاءات:</span>
                            <span class="font-mono">{{ reportData.summary.cancelled_orders || 0 }} طلب ({{ formatNumber(reportData.summary.cancelled_lost) }} {{ reportData.currency }})</span>
                        </div>
                    </div>
                </div>

                <!-- Modal Actions -->
                <div class="flex items-center gap-3">
                    <button
                        @click="printReport"
                        class="flex-1 py-2.5 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-black text-xs flex items-center justify-center gap-2 shadow-lg transition-transform active:scale-95"
                    >
                        <span>🖨️</span>
                        <span>طباعة إيصال Z-Report حرارياً</span>
                    </button>
                    <button
                        @click="showZReportModal = false"
                        class="px-5 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-bold text-xs transition-colors"
                    >
                        إغلاق
                    </button>
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
