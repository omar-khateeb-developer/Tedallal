<script setup lang="ts">
import AdminNav from '@/Components/Admin/AdminNav.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import axios from 'axios';

interface TableRow {
    id: number;
    name: string;
    public_token: string;
    qr_url: string;
    status: string;
}

const props = defineProps<{
    tables: TableRow[];
    restaurant: { name: string };
}>();

const tables = ref([...props.tables]);
const newName = ref('');
const editingId = ref<number | null>(null);
const editingName = ref('');
const loading = ref(false);
const message = ref('');

async function reload() {
    const { data } = await axios.get('/api/admin/tables');
    tables.value = data.data ?? [];
}

async function createTable() {
    if (!newName.value.trim()) return;
    loading.value = true;
    message.value = '';
    try {
        await axios.post('/api/admin/tables', { name: newName.value.trim() });
        newName.value = '';
        await reload();
        message.value = 'تم إنشاء الطاولة وتوليد رمز QR بنجاح';
    } catch (e: any) {
        message.value = e.response?.data?.message || 'تعذر إنشاء الطاولة';
    } finally {
        loading.value = false;
    }
}

function startEdit(table: TableRow) {
    editingId.value = table.id;
    editingName.value = table.name;
}

async function saveEdit(table: TableRow) {
    try {
        await axios.patch(`/api/admin/tables/${table.id}`, { name: editingName.value });
        editingId.value = null;
        await reload();
    } catch (e: any) {
        alert(e.response?.data?.message || 'تعذر الحفظ');
        console.error(e);
    }
}

async function toggleStatus(table: TableRow) {
    try {
        const status = table.status === 'active' ? 'inactive' : 'active';
        await axios.patch(`/api/admin/tables/${table.id}`, { status });
        await reload();
    } catch (e: any) {
        alert(e.response?.data?.message || 'تعذر تغيير الحالة');
        console.error(e);
    }
}

async function deleteTable(table: TableRow) {
    if (!confirm(`حذف ${table.name}؟`)) return;
    try {
        await axios.delete(`/api/admin/tables/${table.id}`);
        await reload();
        message.value = 'تم حذف الطاولة';
    } catch (e: any) {
        alert(e.response?.data?.message || 'لا يمكن حذف طاولة لها طلبات');
        console.error(e);
    }
}

async function regenerateToken(table: TableRow) {
    if (!confirm(`سيتم إبطال رمز QR الحالي للطاولة (${table.name}) وتوليد رمز جديد. متابعة؟`)) return;
    loading.value = true;
    message.value = '';
    try {
        await axios.post(`/api/admin/tables/${table.id}/regenerate-token`);
        await reload();
        message.value = `تم توليد رمز QR جديد للطاولة: ${table.name}`;
    } catch (e: any) {
        alert(e.response?.data?.message || 'تعذر توليد QR جديد');
        console.error(e);
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    tables.value = [...props.tables];
});
</script>

<template>
    <Head title="الطاولات" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800 dark:text-white">الطاولات ورموز QR</h2>
        </template>
        <div class="mx-auto max-w-5xl px-4 py-6" dir="rtl">
            <AdminNav />

            <p v-if="message" class="mb-4 text-sm font-medium text-green-600 dark:text-emerald-400">{{ message }}</p>

            <div class="mb-6 flex flex-wrap gap-3">
                <input
                    v-model="newName"
                    class="rounded-xl border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-stone-900 dark:text-white px-4 py-2 focus:border-amber-500 focus:ring-amber-500"
                    placeholder="اسم الطاولة الجديدة"
                />
                <button
                    class="rounded-xl bg-amber-600 dark:bg-amber-500 px-5 py-2 text-white dark:text-slate-950 font-bold disabled:opacity-50 hover:bg-amber-700 dark:hover:bg-amber-400 transition-colors"
                    :disabled="loading"
                    @click="createTable"
                >
                    إضافة طاولة
                </button>
                <a
                    :href="route('admin.tables.print')"
                    target="_blank"
                    class="rounded-xl bg-stone-800 dark:bg-slate-800 border border-stone-700 dark:border-slate-700 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700 dark:hover:bg-slate-700 transition-colors flex items-center gap-1.5"
                >
                    طباعة كل QR
                </a>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <article
                    v-for="table in tables"
                    :key="table.id"
                    class="rounded-2xl bg-white dark:bg-[#0a0f1d] p-5 text-center shadow-xs border border-stone-100 dark:border-slate-800 transition-all"
                    :class="table.status !== 'active' && 'opacity-60'"
                >
                    <div v-if="editingId === table.id" class="mb-3 flex gap-2">
                        <input v-model="editingName" class="flex-1 rounded-lg border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-stone-900 dark:text-white text-sm" />
                        <button class="rounded-lg bg-amber-600 px-3 text-white text-sm font-bold" @click="saveEdit(table)">حفظ</button>
                    </div>
                    <h3 v-else class="text-lg font-bold text-gray-900 dark:text-white">{{ table.name }}</h3>

                    <p class="mt-1 text-sm text-stone-500 dark:text-stone-400">{{ restaurant.name }}</p>
                    <div class="my-4 inline-block bg-white p-2 rounded-xl shadow-xs border border-stone-200 dark:border-slate-700">
                        <img
                            :src="table.qr_url"
                            :alt="`QR ${table.name}`"
                            class="h-32 w-32 object-contain"
                        />
                    </div>
                    <div class="flex flex-wrap justify-center gap-2 text-sm">
                        <button class="rounded-lg bg-stone-100 dark:bg-slate-800 text-stone-700 dark:text-stone-300 hover:bg-stone-200 dark:hover:bg-slate-700 px-3 py-1 transition-colors" @click="startEdit(table)">تعديل</button>
                        <button class="rounded-lg bg-stone-100 dark:bg-slate-800 text-stone-700 dark:text-stone-300 hover:bg-stone-200 dark:hover:bg-slate-700 px-3 py-1 transition-colors" @click="toggleStatus(table)">
                            {{ table.status === 'active' ? 'تعطيل' : 'تفعيل' }}
                        </button>
                        <button class="rounded-lg bg-stone-100 dark:bg-slate-800 text-stone-700 dark:text-stone-300 hover:bg-stone-200 dark:hover:bg-slate-700 px-3 py-1 transition-colors" @click="regenerateToken(table)">QR جديد</button>
                        <a :href="table.qr_url" download class="rounded-lg bg-amber-600 dark:bg-amber-500 px-3 py-1 text-white dark:text-slate-950 font-bold">تحميل</a>
                        <button class="rounded-lg bg-red-100 dark:bg-red-950/60 px-3 py-1 text-red-700 dark:text-red-300 hover:bg-red-200 dark:hover:bg-red-900/50 transition-colors" @click="deleteTable(table)">حذف</button>
                    </div>
                </article>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
