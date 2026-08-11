<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import axios from 'axios';

const categories = ref<Array<{ id: number; name: string; is_active: boolean }>>([]);
const name = ref('');
const loading = ref(false);

async function load() {
    const { data } = await axios.get('/api/admin/menu/categories');
    categories.value = data.data ?? [];
}

async function createCategory() {
    if (!name.value) return;
    loading.value = true;
    try {
        await axios.post('/api/admin/menu/categories', { name: name.value });
        name.value = '';
        await load();
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>

<template>
    <Head title="تصنيفات القائمة" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">تصنيفات القائمة</h2>
        </template>
        <div class="mx-auto max-w-3xl space-y-4 p-6" dir="rtl">
            <div class="flex gap-2">
                <input v-model="name" class="flex-1 rounded-lg border-stone-300" placeholder="اسم التصنيف" />
                <button class="rounded-lg bg-amber-600 px-4 py-2 text-white" :disabled="loading" @click="createCategory">
                    إضافة
                </button>
            </div>
            <ul class="space-y-2">
                <li v-for="cat in categories" :key="cat.id" class="rounded-xl bg-white p-4 shadow">
                    {{ cat.name }}
                </li>
            </ul>
        </div>
    </AuthenticatedLayout>
</template>
