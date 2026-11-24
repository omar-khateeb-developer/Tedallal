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
    table_type?: 'dine_in' | 'takeaway';
}

const props = defineProps<{
    tables: TableRow[];
    restaurant: { name: string };
}>();

const tables = ref([...props.tables]);
const newName = ref('');
const newTableType = ref<'dine_in' | 'takeaway'>('dine_in');
const editingId = ref<number | null>(null);
const editingName = ref('');
const editingType = ref<'dine_in' | 'takeaway'>('dine_in');
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
    const createdType = newTableType.value;
    try {
        await axios.post('/api/admin/tables', {
            name: newName.value.trim(),
            table_type: createdType,
        });
        newName.value = '';
        await reload();
        message.value = createdType === 'takeaway'
            ? 'تم إنشاء نقطة السفري / الاستلام وتوليد رمز QR بنجاح'
            : 'تم إنشاء الطاولة وتوليد رمز QR بنجاح';
    } catch (e: any) {
        message.value = e.response?.data?.message || 'تعذر إنشاء النقطة';
    } finally {
        loading.value = false;
    }
}

function startEdit(table: TableRow) {
    editingId.value = table.id;
    editingName.value = table.name;
    editingType.value = table.table_type === 'takeaway' ? 'takeaway' : 'dine_in';
}

async function saveEdit(table: TableRow) {
    try {
        await axios.patch(`/api/admin/tables/${table.id}`, {
            name: editingName.value,
            table_type: editingType.value,
        });
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

            <div class="mb-6 flex flex-wrap items-center gap-3">
                <div class="inline-flex rounded-xl bg-stone-100 dark:bg-slate-900 p-1 border border-stone-200 dark:border-slate-700">
                    <button
                        type="button"
                        @click="newTableType = 'dine_in'"
                        :class="[
                            'rounded-lg px-3 py-1.5 text-xs font-bold transition-all',
                            newTableType === 'dine_in'
                                ? 'bg-amber-600 dark:bg-amber-500 text-white dark:text-slate-950 shadow-xs'
                                : 'text-stone-600 dark:text-stone-400 hover:text-stone-900 dark:hover:text-white'
                        ]"
                    >
                        🍽️ طاولة صالة
                    </button>
                    <button
                        type="button"
                        @click="newTableType = 'takeaway'"
                        :class="[
                            'rounded-lg px-3 py-1.5 text-xs font-bold transition-all',
                            newTableType === 'takeaway'
                                ? 'bg-sky-600 text-white shadow-xs'
                                : 'text-stone-600 dark:text-stone-400 hover:text-stone-900 dark:hover:text-white'
                        ]"
                    >
                        🥡 نقطة سفري / استلام
                    </button>
                </div>

                <input
                    v-model="newName"
                    class="min-w-[210px] flex-1 sm:flex-none rounded-xl border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-stone-900 dark:text-white px-4 py-2 focus:border-amber-500 focus:ring-amber-500"
                    :placeholder="newTableType === 'takeaway' ? 'مثال: كاونتر السفري أو شباك الاستلام' : 'اسم الطاولة الجديدة'"
                />
                <button
                    class="rounded-xl px-5 py-2 text-white font-bold disabled:opacity-50 transition-colors"
                    :class="newTableType === 'takeaway'
                        ? 'bg-sky-600 hover:bg-sky-700'
                        : 'bg-amber-600 dark:bg-amber-500 dark:text-slate-950 hover:bg-amber-700 dark:hover:bg-amber-400'"
                    :disabled="loading"
                    @click="createTable"
                >
                    {{ newTableType === 'takeaway' ? 'إضافة نقطة سفري' : 'إضافة طاولة' }}
                </button>
                <a
                    :href="route('admin.tables.print')"
                    target="_blank"
                    class="rounded-xl bg-stone-800 dark:bg-slate-800 border border-stone-700 dark:border-slate-700 px-4 py-2 text-sm font-bold text-white hover:bg-stone-700 dark:hover:bg-slate-700 transition-colors flex items-center gap-1.5"
                >
                    🖨️ طباعة بطاقات الطاولات (QR Cards)
                </a>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <article
                    v-for="table in tables"
                    :key="table.id"
                    class="rounded-2xl bg-white dark:bg-[#0a0f1d] p-5 text-center shadow-xs border transition-all"
                    :class="[
                        table.table_type === 'takeaway'
                            ? 'border-sky-200 dark:border-sky-900/60'
                            : 'border-stone-100 dark:border-slate-800',
                        table.status !== 'active' && 'opacity-60'
                    ]"
                >
                    <div class="mb-2 flex justify-center">
                        <span
                            v-if="table.table_type === 'takeaway'"
                            class="inline-flex items-center gap-1 rounded-full bg-sky-50 dark:bg-sky-950/50 border border-sky-200 dark:border-sky-800 px-2.5 py-0.5 text-[11px] font-bold text-sky-700 dark:text-sky-300"
                        >
                            🥡 نقطة سفري / استلام
                        </span>
                        <span
                            v-else
                            class="inline-flex items-center gap-1 rounded-full bg-amber-50 dark:bg-amber-950/40 border border-amber-200/80 dark:border-amber-800/50 px-2.5 py-0.5 text-[11px] font-bold text-amber-700 dark:text-amber-300"
                        >
                            🍽️ طاولة صالة
                        </span>
                    </div>

                    <div v-if="editingId === table.id" class="mb-3 space-y-2">
                        <div class="flex gap-2">
                            <input v-model="editingName" class="flex-1 rounded-lg border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-stone-900 dark:text-white text-sm" />
                            <button class="rounded-lg bg-amber-600 px-3 text-white text-sm font-bold" @click="saveEdit(table)">حفظ</button>
                        </div>
                        <select
                            v-model="editingType"
                            class="w-full rounded-lg border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-stone-900 dark:text-white text-xs py-1.5"
                        >
                            <option value="dine_in">🍽️ طاولة صالة (داخل المطعم)</option>
                            <option value="takeaway">🥡 نقطة سفري / استلام مستقل</option>
                        </select>
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
                        <a :href="route('admin.tables.print', { table_id: table.id })" target="_blank" class="rounded-lg bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-300 hover:bg-amber-100 dark:hover:bg-amber-900/40 px-3 py-1 font-bold border border-amber-200 dark:border-amber-800 transition-colors" title="طباعة بطاقة هذه الطاولة">🖨️ طباعة</a>
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
