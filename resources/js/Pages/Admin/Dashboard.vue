<script setup lang="ts">
import AdminNav from '@/Components/Admin/AdminNav.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import axios from 'axios';
import CountUp from '@/Components/CountUp.vue';

const stats = ref<Record<string, number | string> | null>(null);

onMounted(async () => {
    const { data } = await axios.get('/api/admin/dashboard/stats');
    stats.value = data.data;
});
</script>

<template>
    <Head title="لوحة التحكم" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center">
                <div class="h-8 w-2 bg-gradient-to-b from-amber-400 to-orange-500 rounded-full ml-3"></div>
                <h2 class="text-2xl font-bold leading-tight text-slate-800 dark:text-white tracking-tight">
                    لوحة التحكم
                </h2>
            </div>
        </template>

        <div class="py-8" dir="rtl">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <AdminNav />

                <!-- Loading Skeleton -->
                <div v-if="!stats" class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <div v-for="i in 8" :key="i" class="relative overflow-hidden rounded-2xl bg-white dark:bg-[#0a0f1d] p-6 shadow-xs border border-slate-100 dark:border-slate-800 animate-pulse">
                        <div class="h-4 w-24 bg-slate-200 dark:bg-slate-800 rounded mb-4"></div>
                        <div class="h-8 w-16 bg-slate-200 dark:bg-slate-800 rounded"></div>
                    </div>
                </div>

                <!-- Stats Grid -->
                <div v-else class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <div v-for="(label, key) in {
                        total_orders: 'إجمالي الطلبات',
                        pending_orders: 'قيد الانتظار',
                        preparing_orders: 'قيد التحضير',
                        ready_orders: 'جاهزة',
                        served_orders: 'تم التقديم',
                        today_revenue: 'إيراد اليوم',
                        active_tables: 'طاولات نشطة',
                        unavailable_menu_items: 'أصناف غير متاحة',
                    }" :key="key" class="relative overflow-hidden rounded-2xl bg-white dark:bg-[#0a0f1d] p-6 shadow-xs border border-slate-100 dark:border-slate-800 transition-all duration-300 hover:-translate-y-1 hover:shadow-xl hover:border-amber-500/40 group cursor-pointer">
                        
                        <div class="relative z-10">
                            <p class="text-sm font-medium text-slate-500 dark:text-slate-400 mb-1 group-hover:text-amber-500 transition-colors">{{ label }}</p>
                            <p class="text-3xl font-bold text-slate-800 dark:text-white tracking-tight">
                                <CountUp :to="stats[key]" />
                            </p>
                        </div>
                        
                        <!-- Decorative blob -->
                        <div class="absolute -right-4 -top-4 h-20 w-20 rounded-full bg-gradient-to-br from-amber-100/60 to-orange-50/60 dark:from-amber-500/10 dark:to-orange-500/5 group-hover:scale-[2] transition-transform duration-700 ease-out"></div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
