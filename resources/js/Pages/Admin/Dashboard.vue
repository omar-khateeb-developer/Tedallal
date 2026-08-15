<script setup lang="ts">
import AdminNav from '@/Components/Admin/AdminNav.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import axios from 'axios';

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
            <h2 class="text-xl font-semibold leading-tight text-gray-800">تدلّل — لوحة التحكم</h2>
        </template>

        <div class="py-8" dir="rtl">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <AdminNav />

                <div v-if="stats" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div v-for="(label, key) in {
                        total_orders: 'إجمالي الطلبات',
                        pending_orders: 'قيد الانتظار',
                        preparing_orders: 'قيد التحضير',
                        ready_orders: 'جاهزة',
                        served_orders: 'تم التقديم',
                        today_revenue: 'إيراد اليوم',
                        active_tables: 'طاولات نشطة',
                        unavailable_menu_items: 'أصناف غير متاحة',
                    }" :key="key" class="rounded-2xl bg-white p-5 shadow">
                        <p class="text-sm text-stone-500">{{ label }}</p>
                        <p class="mt-2 text-2xl font-bold">{{ stats[key] }}</p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
