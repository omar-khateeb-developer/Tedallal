<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import axios from 'axios';

const items = ref<Array<{ id: number; name: string; price: string }>>([]);
const categories = ref<Array<{ id: number; name: string }>>([]);
const form = ref({ category_id: 0, name: '', price: '' });

async function load() {
    const [itemsRes, catsRes] = await Promise.all([
        axios.get('/api/admin/menu/items'),
        axios.get('/api/admin/menu/categories'),
    ]);
    items.value = itemsRes.data.data ?? [];
    categories.value = catsRes.data.data ?? [];
    if (categories.value.length && !form.value.category_id) {
        form.value.category_id = categories.value[0].id;
    }
}

async function createItem() {
    await axios.post('/api/admin/menu/items', form.value);
    form.value.name = '';
    form.value.price = '';
    await load();
}

onMounted(load);
</script>

<template>
    <Head title="أصناف القائمة" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">أصناف القائمة</h2>
        </template>
        <div class="mx-auto max-w-3xl space-y-4 p-6" dir="rtl">
            <div class="grid gap-2 sm:grid-cols-4">
                <select v-model="form.category_id" class="rounded-lg border-stone-300">
                    <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
                <input v-model="form.name" class="rounded-lg border-stone-300" placeholder="الاسم" />
                <input v-model="form.price" class="rounded-lg border-stone-300" placeholder="السعر" />
                <button class="rounded-lg bg-amber-600 text-white" @click="createItem">إضافة</button>
            </div>
            <ul class="space-y-2">
                <li v-for="item in items" :key="item.id" class="flex justify-between rounded-xl bg-white p-4 shadow">
                    <span>{{ item.name }}</span>
                    <span>{{ item.price }} ر.س</span>
                </li>
            </ul>
        </div>
    </AuthenticatedLayout>
</template>
