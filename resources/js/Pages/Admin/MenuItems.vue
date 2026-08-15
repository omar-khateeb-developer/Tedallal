<script setup lang="ts">
import AdminNav from '@/Components/Admin/AdminNav.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import axios from 'axios';

interface MenuItemRow {
    id: number;
    name: string;
    price: string;
    category_id: number;
    is_available: boolean;
    is_active: boolean;
}

const items = ref<MenuItemRow[]>([]);
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
    if (!form.value.name || !form.value.price) return;
    await axios.post('/api/admin/menu/items', form.value);
    form.value.name = '';
    form.value.price = '';
    await load();
}

async function updateItem(item: MenuItemRow, field: string, value: unknown) {
    await axios.patch(`/api/admin/menu/items/${item.id}`, { [field]: value });
    await load();
}

async function deleteItem(item: MenuItemRow) {
    if (!confirm(`حذف "${item.name}"؟`)) return;
    await axios.delete(`/api/admin/menu/items/${item.id}`);
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
        <div class="mx-auto max-w-4xl p-6" dir="rtl">
            <AdminNav />
            <div class="mb-4 grid gap-2 sm:grid-cols-4">
                <select v-model="form.category_id" class="rounded-lg border-stone-300">
                    <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
                <input v-model="form.name" class="rounded-lg border-stone-300" placeholder="الاسم" />
                <input v-model="form.price" class="rounded-lg border-stone-300" placeholder="السعر" dir="ltr" />
                <button class="rounded-lg bg-amber-600 text-white" @click="createItem">إضافة</button>
            </div>
            <ul class="space-y-2">
                <li
                    v-for="item in items"
                    :key="item.id"
                    class="rounded-xl bg-white p-4 shadow"
                >
                    <div class="flex flex-wrap items-center gap-3">
                        <input
                            :value="item.name"
                            class="min-w-[120px] flex-1 rounded-lg border-stone-200 text-sm"
                            @change="updateItem(item, 'name', ($event.target as HTMLInputElement).value)"
                        />
                        <input
                            :value="item.price"
                            class="w-24 rounded-lg border-stone-200 text-sm"
                            dir="ltr"
                            @change="updateItem(item, 'price', ($event.target as HTMLInputElement).value)"
                        />
                        <button
                            class="rounded-lg px-2 py-1 text-xs"
                            :class="item.is_available ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-700'"
                            @click="updateItem(item, 'is_available', !item.is_available)"
                        >
                            {{ item.is_available ? 'متاح' : 'غير متاح' }}
                        </button>
                        <button
                            class="rounded-lg px-2 py-1 text-xs"
                            :class="item.is_active ? 'bg-blue-100 text-blue-800' : 'bg-stone-100'"
                            @click="updateItem(item, 'is_active', !item.is_active)"
                        >
                            {{ item.is_active ? 'ظاهر' : 'مخفي' }}
                        </button>
                        <button class="rounded-lg bg-red-100 px-2 py-1 text-xs text-red-700" @click="deleteItem(item)">
                            حذف
                        </button>
                    </div>
                </li>
            </ul>
        </div>
    </AuthenticatedLayout>
</template>
