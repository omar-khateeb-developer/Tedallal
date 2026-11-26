<script setup lang="ts">
import AdminNav from '@/Components/Admin/AdminNav.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import { getFloorZoneLabel, TABLE_STAGE_LABELS } from '@/types/floorPlan';

interface TableRow {
    id: number;
    name: string;
    public_token: string;
    qr_url: string;
    status: string;
    table_type?: 'dine_in' | 'takeaway';
    zone?: string;
    capacity?: number;
    operational_stage?: string;
    active_session?: { id: number } | null;
}

const props = defineProps<{
    tables: TableRow[];
    restaurant: { id?: number; name: string; features?: Record<string, any> };
}>();

const page = usePage();
const isFloorPlanEnabled = computed(() =>
    Boolean(props.restaurant?.features?.floor_plan ?? (page.props.auth as any)?.restaurant?.features?.floor_plan),
);

const tables = ref([...props.tables]);
const typeFilter = ref<'all' | 'dine_in' | 'takeaway'>('all');
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

const visibleTables = computed(() => {
    if (typeFilter.value === 'all') return tables.value;
    return tables.value.filter(t => (t.table_type ?? 'dine_in') === typeFilter.value);
});

const typeCounts = computed(() => {
    let dineIn = 0;
    let takeaway = 0;
    for (const t of tables.value) {
        if ((t.table_type ?? 'dine_in') === 'takeaway') takeaway++;
        else dineIn++;
    }
    return { all: tables.value.length, dine_in: dineIn, takeaway };
});

function stageBadgeText(table: TableRow): string {
    if (table.status !== 'active') return '🚫 معطّلة';
    if (table.operational_stage && TABLE_STAGE_LABELS[table.operational_stage]) {
        return TABLE_STAGE_LABELS[table.operational_stage];
    }
    return table.active_session ? 'جلسة نشطة' : 'شاغرة';
}

onMounted(() => {
    tables.value = [...props.tables];
    try {
        const tab = new URLSearchParams(window.location.search).get('tab');
        if (tab === 'takeaway' || tab === 'dine_in') {
            typeFilter.value = tab;
        }
    } catch (_) {}

    const restId = props.restaurant?.id || (page.props.auth as any)?.restaurant?.id;
    if (restId && (window as any).Echo) {
        (window as any).Echo.private(`restaurant.${restId}.orders`)
            .listen('.table.updated', (payload: { table?: TableRow; deleted?: boolean }) => {
                if (!payload.table) return;
                if (payload.deleted) {
                    tables.value = tables.value.filter(t => t.id !== payload.table?.id);
                    return;
                }
                const idx = tables.value.findIndex(t => t.id === payload.table?.id);
                if (idx !== -1) {
                    tables.value[idx] = { ...tables.value[idx], ...payload.table };
                } else {
                    tables.value.push(payload.table);
                }
            });
    }
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

            <!-- تبويبات الفصل بين طاولات الصالة ونقاط السفري -->
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                <div class="inline-flex flex-wrap gap-1.5 bg-white dark:bg-[#0a0f1d] p-1.5 rounded-2xl border border-stone-200 dark:border-slate-800 shadow-2xs">
                    <button
                        v-for="tab in [
                            { key: 'all', label: 'الكل' },
                            { key: 'dine_in', label: '🍽️ طاولات الصالة' },
                            { key: 'takeaway', label: '🥡 نقاط السفري' },
                        ] as const"
                        :key="tab.key"
                        type="button"
                        @click="typeFilter = tab.key"
                        :class="[
                            'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer',
                            typeFilter === tab.key
                                ? 'bg-amber-500 text-stone-950 font-black shadow-xs'
                                : 'text-stone-600 dark:text-slate-300 hover:bg-stone-100 dark:hover:bg-slate-800'
                        ]"
                    >
                        <span>{{ tab.label }}</span>
                        <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-black/10 dark:bg-white/10 font-mono">
                            {{ typeCounts[tab.key] }}
                        </span>
                    </button>
                </div>

                <Link
                    v-if="isFloorPlanEnabled"
                    :href="route('admin.floor_plan')"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-amber-500/15 hover:bg-amber-500/25 border border-amber-500/35 text-amber-800 dark:text-amber-300 text-xs font-black transition"
                >
                    <span>🗺️</span>
                    <span>فتح مخطط الصالة التفاعلي ←</span>
                </Link>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <article
                    v-for="table in visibleTables"
                    :key="table.id"
                    class="rounded-2xl bg-white dark:bg-[#0a0f1d] p-5 text-center shadow-xs border transition-all"
                    :class="[
                        table.table_type === 'takeaway'
                            ? 'border-sky-200 dark:border-sky-900/60'
                            : 'border-stone-100 dark:border-slate-800',
                        table.status !== 'active' && 'opacity-60'
                    ]"
                >
                    <div class="mb-2 flex flex-wrap items-center justify-center gap-1.5">
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
                            🍽️ {{ getFloorZoneLabel(table.zone || 'main') }}
                        </span>
                        <span
                            v-if="table.table_type !== 'takeaway'"
                            class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold border"
                            :class="table.status !== 'active'
                                ? 'bg-rose-50 dark:bg-rose-950/50 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800'
                                : (table.active_session || (table.operational_stage && table.operational_stage !== 'available')
                                    ? 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800'
                                    : 'bg-stone-100 dark:bg-slate-800 text-stone-600 dark:text-slate-300 border-stone-200 dark:border-slate-700')"
                        >
                            {{ stageBadgeText(table) }}
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
                        <Link
                            v-if="isFloorPlanEnabled && table.table_type !== 'takeaway'"
                            :href="route('admin.floor_plan', { table_id: table.id })"
                            class="rounded-lg bg-indigo-50 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-indigo-900/50 border border-indigo-200 dark:border-indigo-800 px-3 py-1 font-bold transition-colors"
                        >
                            🗺️ المخطط
                        </Link>
                        <button class="rounded-lg bg-red-100 dark:bg-red-950/60 px-3 py-1 text-red-700 dark:text-red-300 hover:bg-red-200 dark:hover:bg-red-900/50 transition-colors" @click="deleteTable(table)">حذف</button>
                    </div>
                </article>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
