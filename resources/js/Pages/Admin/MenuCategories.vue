<script setup lang="ts">
import AdminNav from '@/Components/Admin/AdminNav.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import axios from 'axios';

interface Category {
    id: number;
    name: string;
    is_active: boolean;
    sort_order: number;
}

const categories = ref<Category[]>([]);
const name = ref('');
const loading = ref(false);

async function load() {
    const { data } = await axios.get('/api/admin/menu/categories');
    categories.value = data.data ?? [];
}

async function createCategory() {
    if (!name.value.trim()) return;
    loading.value = true;
    try {
        await axios.post('/api/admin/menu/categories', { name: name.value.trim() });
        name.value = '';
        await load();
    } finally {
        loading.value = false;
    }
}

async function toggleActive(cat: Category) {
    await axios.patch(`/api/admin/menu/categories/${cat.id}`, { is_active: !cat.is_active });
    await load();
}

async function deleteCategory(cat: Category) {
    if (!confirm(`حذف "${cat.name}"؟`)) return;
    try {
        await axios.delete(`/api/admin/menu/categories/${cat.id}`);
        await load();
    } catch {
        alert('لا يمكن حذف تصنيف يحتوي على أصناف');
    }
}

async function updateName(cat: Category, newName: string) {
    if (newName === cat.name) return;
    await axios.patch(`/api/admin/menu/categories/${cat.id}`, { name: newName });
    await load();
}

onMounted(load);
</script>

<template>
    <Head title="تصنيفات القائمة" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800 dark:text-white">تصنيفات القائمة</h2>
        </template>
        <div class="mx-auto max-w-3xl p-6" dir="rtl">
            <AdminNav />
            <div class="mb-4 flex gap-2">
                <input v-model="name" class="flex-1 rounded-xl border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-stone-900 dark:text-white px-4 py-2 focus:border-amber-500 focus:ring-amber-500" placeholder="اسم التصنيف" />
                <button class="rounded-xl bg-amber-600 dark:bg-amber-500 px-5 py-2 text-white dark:text-slate-950 font-bold hover:bg-amber-700 dark:hover:bg-amber-400 transition-colors" :disabled="loading" @click="createCategory">
                    إضافة
                </button>
            </div>
            <ul class="space-y-2">
                <li
                    v-for="cat in categories"
                    :key="cat.id"
                    class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-white dark:bg-[#0a0f1d] p-4 shadow-xs border border-stone-100 dark:border-slate-800 transition-colors"
                >
                    <input
                        :value="cat.name"
                        class="flex-1 rounded-lg border-stone-200 dark:border-slate-700 bg-stone-50 dark:bg-slate-900 text-stone-900 dark:text-white text-sm font-medium px-3 py-1.5 focus:border-amber-500 focus:ring-amber-500"
                        @change="updateName(cat, ($event.target as HTMLInputElement).value)"
                    />
                    <div class="flex gap-2">
                        <button
                            class="rounded-lg px-3 py-1 text-sm font-semibold transition-colors"
                            :class="cat.is_active ? 'bg-green-100 dark:bg-emerald-950/60 text-green-800 dark:text-emerald-300 border border-green-200/50 dark:border-emerald-800/40' : 'bg-stone-100 dark:bg-slate-800 text-stone-500 dark:text-stone-400'"
                            @click="toggleActive(cat)"
                        >
                            {{ cat.is_active ? 'نشط' : 'معطّل' }}
                        </button>
                        <button class="rounded-lg bg-red-100 dark:bg-red-950/60 px-3 py-1 text-sm text-red-700 dark:text-red-300 hover:bg-red-200 dark:hover:bg-red-900/50 transition-colors" @click="deleteCategory(cat)">
                            حذف
                        </button>
                    </div>
                </li>
            </ul>
        </div>
    </AuthenticatedLayout>
</template>
