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
        message.value = 'تم إنشاء الطاولة';
    } catch {
        message.value = 'تعذر إنشاء الطاولة';
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
    } catch (e: any) {
        alert(e.response?.data?.message || 'لا يمكن حذف طاولة لها طلبات');
        console.error(e);
    }
}

async function regenerateToken(table: TableRow) {
    if (!confirm('سيتم إبطال رمز QR الحالي. متابعة؟')) return;
    try {
        await axios.post(`/api/admin/tables/${table.id}/regenerate-token`);
        await reload();
    } catch (e: any) {
        alert(e.response?.data?.message || 'تعذر توليد QR جديد');
        console.error(e);
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
            <h2 class="text-xl font-semibold text-gray-800">الطاولات ورموز QR</h2>
        </template>
        <div class="mx-auto max-w-5xl px-4 py-6" dir="rtl">
            <AdminNav />

            <p v-if="message" class="mb-4 text-sm text-green-600">{{ message }}</p>

            <div class="mb-6 flex flex-wrap gap-3">
                <input
                    v-model="newName"
                    class="rounded-lg border-stone-300"
                    placeholder="اسم الطاولة الجديدة"
                />
                <button
                    class="rounded-lg bg-amber-600 px-4 py-2 text-white disabled:opacity-50"
                    :disabled="loading"
                    @click="createTable"
                >
                    إضافة طاولة
                </button>
                <a
                    :href="route('admin.tables.print')"
                    target="_blank"
                    class="rounded-lg bg-stone-800 px-4 py-2 text-sm text-white"
                >
                    طباعة كل QR
                </a>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <article
                    v-for="table in tables"
                    :key="table.id"
                    class="rounded-2xl bg-white p-5 text-center shadow"
                    :class="table.status !== 'active' && 'opacity-60'"
                >
                    <div v-if="editingId === table.id" class="mb-3 flex gap-2">
                        <input v-model="editingName" class="flex-1 rounded-lg border-stone-300 text-sm" />
                        <button class="rounded-lg bg-amber-600 px-3 text-white text-sm" @click="saveEdit(table)">حفظ</button>
                    </div>
                    <h3 v-else class="text-lg font-bold">{{ table.name }}</h3>

                    <p class="mt-1 text-sm text-stone-500">{{ restaurant.name }}</p>
                    <img
                        :src="table.qr_url"
                        :alt="`QR ${table.name}`"
                        class="mx-auto my-4 h-36 w-36"
                    />
                    <div class="flex flex-wrap justify-center gap-2 text-sm">
                        <button class="rounded-lg bg-stone-100 px-3 py-1" @click="startEdit(table)">تعديل</button>
                        <button class="rounded-lg bg-stone-100 px-3 py-1" @click="toggleStatus(table)">
                            {{ table.status === 'active' ? 'تعطيل' : 'تفعيل' }}
                        </button>
                        <button class="rounded-lg bg-stone-100 px-3 py-1" @click="regenerateToken(table)">QR جديد</button>
                        <a :href="table.qr_url" download class="rounded-lg bg-amber-600 px-3 py-1 text-white">تحميل</a>
                        <button class="rounded-lg bg-red-100 px-3 py-1 text-red-700" @click="deleteTable(table)">حذف</button>
                    </div>
                </article>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
