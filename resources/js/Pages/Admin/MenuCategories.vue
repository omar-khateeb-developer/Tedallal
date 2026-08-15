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
            <h2 class="text-xl font-semibold text-gray-800">تصنيفات القائمة</h2>
        </template>
        <div class="mx-auto max-w-3xl p-6" dir="rtl">
            <AdminNav />
            <div class="mb-4 flex gap-2">
                <input v-model="name" class="flex-1 rounded-lg border-stone-300" placeholder="اسم التصنيف" />
                <button class="rounded-lg bg-amber-600 px-4 py-2 text-white" :disabled="loading" @click="createCategory">
                    إضافة
                </button>
            </div>
            <ul class="space-y-2">
                <li
                    v-for="cat in categories"
                    :key="cat.id"
                    class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-white p-4 shadow"
                >
                    <input
                        :value="cat.name"
                        class="flex-1 rounded-lg border-stone-200 text-sm font-medium"
                        @change="updateName(cat, ($event.target as HTMLInputElement).value)"
                    />
                    <div class="flex gap-2">
                        <button
                            class="rounded-lg px-3 py-1 text-sm"
                            :class="cat.is_active ? 'bg-green-100 text-green-800' : 'bg-stone-100 text-stone-500'"
                            @click="toggleActive(cat)"
                        >
                            {{ cat.is_active ? 'نشط' : 'معطّل' }}
                        </button>
                        <button class="rounded-lg bg-red-100 px-3 py-1 text-sm text-red-700" @click="deleteCategory(cat)">
                            حذف
                        </button>
                    </div>
                </li>
            </ul>
        </div>
    </AuthenticatedLayout>
</template>
