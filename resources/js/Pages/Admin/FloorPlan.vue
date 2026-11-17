<script setup lang="ts">
import AdminNav from '@/Components/Admin/AdminNav.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import axios from 'axios';

interface TableItem {
    id: number;
    name: string;
    public_token: string;
    qr_url: string;
    public_url?: string;
    status: 'active' | 'inactive';
    pos_x: number;
    pos_y: number;
    shape: 'circle' | 'rectangle' | 'booth' | 'vip';
    rotation: number;
    capacity: number;
    zone: string;
    width: number;
    height: number;
    iot_device_id?: string | null;
    active_session?: any;
}

interface ReservationItem {
    id: number;
    uuid: string;
    code: string;
    table_id: number;
    guest_name: string;
    guest_phone?: string;
    reservation_date: string;
    time_slot: string;
    guests_count: number;
    special_requests?: string;
    status: string;
    table_name?: string;
    table_zone?: string;
}

const props = defineProps<{
    tables: TableItem[];
    zones: string[];
    restaurant: { id: number; name: string; slug: string; currency?: string; features?: any };
    todayReservations?: ReservationItem[];
    isFeatureLocked: boolean;
}>();

// State
const tables = ref<TableItem[]>(props.tables.map(t => ({
    ...t,
    pos_x: Number(t.pos_x) || 80,
    pos_y: Number(t.pos_y) || 80,
    shape: t.shape || 'circle',
    rotation: Number(t.rotation) || 0,
    capacity: Number(t.capacity) || 4,
    zone: t.zone || 'main',
    width: Number(t.width) || (t.shape === 'vip' ? 140 : (t.shape === 'rectangle' ? 130 : 90)),
    height: Number(t.height) || (t.shape === 'rectangle' ? 80 : 90),
})));

const reservations = ref<ReservationItem[]>(props.todayReservations || []);
const activeZone = ref<string>('all');
const selectedTable = ref<TableItem | null>(null);
const editorMode = ref<'monitor' | 'designer'>('monitor'); // monitor mode prevents accidental drag
const zoomLevel = ref<number>(1);
const isSaving = ref(false);
const saveSuccess = ref(false);
const isAddModalOpen = ref(false);
const isReservationsDrawerOpen = ref(false);
const isCopiedLink = ref(false);
const showArchitecturalElements = ref(true);

const isDragging = ref(false);
const draggedTableId = ref<number | null>(null);
const dragOffset = ref({ x: 0, y: 0 });
const snapToGrid = ref(true);
const gridSize = 20;

// New table form
const newTableForm = ref({
    name: '',
    shape: 'circle' as 'circle' | 'rectangle' | 'booth' | 'vip',
    capacity: 4,
    zone: 'main',
    iot_device_id: '',
});

// Filtered tables by zone
const visibleTables = computed(() => {
    if (activeZone.value === 'all') return tables.value;
    return tables.value.filter(t => t.zone === activeZone.value);
});

// Zone labels dictionary
const zoneLabels: Record<string, string> = {
    all: 'كامل الصالة المعمارية',
    main: 'الصالة الرئيسية',
    vip: 'جناح النخبة (VIP Lounge)',
    terrace: 'التراس الخارجي',
    family: 'القسم العائلي',
};

function getZoneLabel(zoneKey: string) {
    return zoneLabels[zoneKey] || zoneKey;
}

// Reservation check helpers
function getTableReservation(tableId: number): ReservationItem | undefined {
    return reservations.value.find(r => r.table_id === tableId && (r.status === 'confirmed' || r.status === 'seated'));
}

// Dragging logic
function onMouseDown(e: MouseEvent, table: TableItem) {
    if (props.isFeatureLocked || editorMode.value !== 'designer') return;
    isDragging.value = true;
    draggedTableId.value = table.id;
    selectedTable.value = table;

    const canvas = document.getElementById('cad-canvas');
    if (!canvas) return;
    const rect = canvas.getBoundingClientRect();
    dragOffset.value = {
        x: (e.clientX - rect.left) / zoomLevel.value - table.pos_x,
        y: (e.clientY - rect.top) / zoomLevel.value - table.pos_y,
    };

    window.addEventListener('mousemove', onMouseMove);
    window.addEventListener('mouseup', onMouseUp);
}

function onMouseMove(e: MouseEvent) {
    if (!isDragging.value || draggedTableId.value === null) return;
    const canvas = document.getElementById('cad-canvas');
    if (!canvas) return;
    const rect = canvas.getBoundingClientRect();

    let newX = (e.clientX - rect.left) / zoomLevel.value - dragOffset.value.x;
    let newY = (e.clientY - rect.top) / zoomLevel.value - dragOffset.value.y;

    // Bounds checking inside architectural layout (1100x600 space)
    newX = Math.max(30, Math.min(newX, 1050));
    newY = Math.max(30, Math.min(newY, 520));

    // Snap to grid
    if (snapToGrid.value) {
        newX = Math.round(newX / gridSize) * gridSize;
        newY = Math.round(newY / gridSize) * gridSize;
    }

    const t = tables.value.find(item => item.id === draggedTableId.value);
    if (t) {
        t.pos_x = newX;
        t.pos_y = newY;
    }
}

function onMouseUp() {
    isDragging.value = false;
    draggedTableId.value = null;
    window.removeEventListener('mousemove', onMouseMove);
    window.removeEventListener('mouseup', onMouseUp);
}

// Table Transform Controls
function rotateSelectedTable() {
    if (!selectedTable.value) return;
    selectedTable.value.rotation = ((selectedTable.value.rotation || 0) + 90) % 360;
}

// Quick Duplicate
async function duplicateSelectedTable() {
    if (!selectedTable.value) return;
    const orig = selectedTable.value;
    const nextNum = tables.value.length + 1;
    try {
        const { data } = await axios.post('/api/admin/tables', {
            name: `طاولة ${nextNum}`,
            shape: orig.shape,
            capacity: orig.capacity,
            zone: orig.zone,
            rotation: orig.rotation,
            pos_x: Math.min(orig.pos_x + 30, 1020),
            pos_y: Math.min(orig.pos_y + 30, 500),
            status: 'active',
        });
        const created = data.data;
        tables.value.push({
            ...created,
            pos_x: created.pos_x,
            pos_y: created.pos_y,
            shape: created.shape || orig.shape,
            rotation: created.rotation || 0,
            capacity: created.capacity || orig.capacity,
            zone: created.zone || orig.zone,
            width: orig.width,
            height: orig.height,
        });
        selectedTable.value = tables.value[tables.value.length - 1];
        saveSuccess.value = true;
        setTimeout(() => { saveSuccess.value = false; }, 3000);
    } catch (e: any) {
        alert(e.response?.data?.message || 'تعذر تكرار الطاولة');
    }
}

// Zoom controls
function zoomIn() {
    if (zoomLevel.value < 1.4) zoomLevel.value = +(zoomLevel.value + 0.1).toFixed(1);
}
function zoomOut() {
    if (zoomLevel.value > 0.7) zoomLevel.value = +(zoomLevel.value - 0.1).toFixed(1);
}
function resetZoom() {
    zoomLevel.value = 1;
}

// Copy Public VIP Booking Link
function copyPublicVipBookingLink() {
    const url = `${window.location.origin}/r/${props.restaurant.slug}/vip-booking`;
    navigator.clipboard.writeText(url).then(() => {
        isCopiedLink.value = true;
        setTimeout(() => { isCopiedLink.value = false; }, 3000);
    });
}

// Save Layout
async function saveLayout() {
    isSaving.value = true;
    saveSuccess.value = false;
    try {
        await axios.post('/api/admin/tables/batch-layout', {
            tables: tables.value.map(t => ({
                id: t.id,
                pos_x: t.pos_x,
                pos_y: t.pos_y,
                shape: t.shape,
                rotation: t.rotation,
                capacity: t.capacity,
                zone: t.zone,
                width: t.width,
                height: t.height,
            }))
        });
        saveSuccess.value = true;
        setTimeout(() => { saveSuccess.value = false; }, 4000);
    } catch (e: any) {
        alert(e.response?.data?.message || 'حدث خطأ أثناء حفظ المخطط');
    } finally {
        isSaving.value = false;
    }
}

// Add Table Modal
async function createTableFromFloorPlan() {
    if (!newTableForm.value.name.trim()) return;
    try {
        const { data } = await axios.post('/api/admin/tables', {
            name: newTableForm.value.name.trim(),
            shape: newTableForm.value.shape,
            capacity: newTableForm.value.capacity,
            zone: newTableForm.value.zone,
            iot_device_id: newTableForm.value.iot_device_id || null,
            pos_x: 100,
            pos_y: 100,
            status: 'active',
        });
        const created = data.data;
        tables.value.push({
            ...created,
            pos_x: created.pos_x || 100,
            pos_y: created.pos_y || 100,
            shape: created.shape || newTableForm.value.shape,
            rotation: 0,
            capacity: created.capacity || newTableForm.value.capacity,
            zone: created.zone || newTableForm.value.zone,
            width: created.shape === 'vip' ? 140 : (created.shape === 'rectangle' ? 130 : 90),
            height: created.shape === 'rectangle' ? 80 : 90,
        });
        newTableForm.value.name = '';
        newTableForm.value.iot_device_id = '';
        isAddModalOpen.value = false;
    } catch (e: any) {
        alert(e.response?.data?.message || 'تعذر إضافة الطاولة');
    }
}

// Update single table in drawer
async function updateSelectedTable() {
    if (!selectedTable.value) return;
    try {
        await axios.patch(`/api/admin/tables/${selectedTable.value.id}`, {
            name: selectedTable.value.name,
            shape: selectedTable.value.shape,
            capacity: selectedTable.value.capacity,
            zone: selectedTable.value.zone,
            rotation: selectedTable.value.rotation,
            iot_device_id: selectedTable.value.iot_device_id,
        });
        saveSuccess.value = true;
        setTimeout(() => { saveSuccess.value = false; }, 3000);
    } catch (e: any) {
        alert(e.response?.data?.message || 'تعذر حفظ تعديل الطاولة');
    }
}

// Close active session & vacate table
async function closeSessionForTable() {
    if (!selectedTable.value) return;
    if (!confirm(`هل أنت متأكد من إنهاء جلسة ${selectedTable.value.name} وإخلائها للزبائن القادمين؟`)) return;
    try {
        await axios.post(`/api/admin/tables/${selectedTable.value.id}/close-session`);
        selectedTable.value.active_session = null;
        const t = tables.value.find(item => item.id === selectedTable.value?.id);
        if (t) t.active_session = null;
        saveSuccess.value = true;
        setTimeout(() => { saveSuccess.value = false; }, 3000);
    } catch (e: any) {
        alert(e.response?.data?.message || 'تعذر إنهاء الجلسة');
    }
}

// Seat arriving VIP Guest
async function markReservationSeated(reservation: ReservationItem) {
    try {
        await axios.patch(`/api/admin/reservations/${reservation.id}/status`, {
            status: 'seated',
        });
        reservation.status = 'seated';
        const t = tables.value.find(item => item.id === reservation.table_id);
        if (t && !t.active_session) {
            t.active_session = { id: reservation.code, guest_name: reservation.guest_name };
        }
        saveSuccess.value = true;
        setTimeout(() => { saveSuccess.value = false; }, 3000);
    } catch (e: any) {
        alert('تعذر تحديث حالة الحجز');
    }
}

// Delete table
async function deleteSelectedTable() {
    if (!selectedTable.value) return;
    if (!confirm(`هل أنت متأكد من حذف ${selectedTable.value.name} نهائياً؟`)) return;
    try {
        await axios.delete(`/api/admin/tables/${selectedTable.value.id}`);
        tables.value = tables.value.filter(t => t.id !== selectedTable.value?.id);
        selectedTable.value = null;
    } catch (e: any) {
        alert(e.response?.data?.message || 'لا يمكن حذف طاولة لها طلبات أو جلسات مسجلة');
    }
}
</script>

<template>
    <Head title="مخطط الصالة الذكي والتفاعلي - إدارة المطعم" />

    <AuthenticatedLayout>
        <div class="py-6 sm:py-8" dir="rtl">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Navigation -->
                <AdminNav />

                <!-- Locked Feature Screen -->
                <div v-if="isFeatureLocked" class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-950 via-indigo-950 to-slate-900 border border-amber-500/30 p-8 sm:p-12 text-white shadow-2xl text-center my-6">
                    <div class="absolute -top-24 -right-24 w-96 h-96 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>
                    <div class="absolute -bottom-24 -left-24 w-96 h-96 bg-purple-500/10 rounded-full blur-3xl pointer-events-none"></div>

                    <div class="relative z-10 max-w-2xl mx-auto">
                        <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-amber-500/20 border border-amber-500/40 text-amber-300 text-xs sm:text-sm font-black mb-6">
                            <span>👑</span>
                            <span>ميزة استثنائية فاخرة • باقة الشاملة (Max)</span>
                        </div>

                        <h2 class="text-2xl sm:text-4xl font-extrabold tracking-tight mb-4">
                            مخطط الصالة التفاعلي الحي وإضاءة الـ <span dir="ltr">IoT (ESP32)</span>
                        </h2>

                        <p class="text-sm sm:text-base text-slate-300 leading-relaxed mb-8">
                            حوّل صالة مطعمك إلى مسرح تقني مذهل! ارسم طاولاتك على مخطط معماري حي ثنائي الأبعاد، واسمح لزبائن النخبة بحجز طاولاتهم المفضلة بصرياً مسبقاً، وتناغم مع إضاءة محيطية ساحرة تتغير تلقائياً مع حالة الطاولة.
                        </p>

                        <!-- Highlights Grid -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-start mb-8">
                            <div class="bg-slate-900/60 border border-slate-700/60 rounded-2xl p-4 flex items-start gap-3">
                                <span class="text-2xl">🗺️</span>
                                <div>
                                    <h4 class="font-bold text-white text-sm">محرر صالة تفاعلي بالسحب والإفلات</h4>
                                    <p class="text-xs text-slate-400 mt-0.5">توزيع الطاولات بأشكالها الواقعية وربط الأبعاد بدقة هندسية.</p>
                                </div>
                            </div>
                            <div class="bg-slate-900/60 border border-slate-700/60 rounded-2xl p-4 flex items-start gap-3">
                                <span class="text-2xl">⚡</span>
                                <div>
                                    <h4 class="font-bold text-white text-sm">مزامنة لحظية عبر Reverb</h4>
                                    <p class="text-xs text-slate-400 mt-0.5">تحديث مباشر لألوان الطاولات فور إرسال الطلب أو استدعاء النادل.</p>
                                </div>
                            </div>
                            <div class="bg-slate-900/60 border border-slate-700/60 rounded-2xl p-4 flex items-start gap-3">
                                <span class="text-2xl">💎</span>
                                <div>
                                    <h4 class="font-bold text-white text-sm">حجز مرئي مسبق لزبائن VIP</h4>
                                    <p class="text-xs text-slate-400 mt-0.5">صفحة عامة فخمة تمكّن الضيف من رؤية صالتك واختيار طاولته المفضلة.</p>
                                </div>
                            </div>
                            <div class="bg-slate-900/60 border border-slate-700/60 rounded-2xl p-4 flex items-start gap-3">
                                <span class="text-2xl">💡</span>
                                <div>
                                    <h4 class="font-bold text-white text-sm">ربط عتاد الإضاءة الذكية (ESP32)</h4>
                                    <p class="text-xs text-slate-400 mt-0.5">إضاءة محيطية تتوهج بالألوان تلقائياً في صالة المطعم الحقيقية.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Upgrade Action -->
                        <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                            <a
                                href="https://wa.me/963987077445?text=أرغب%20في%20ترقية%20مطعمي%20لتفعيل%20ميزة%20مخطط%20الصالة%20التفاعلي%20وربط%20الـ%20IoT"
                                target="_blank"
                                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-8 py-3.5 rounded-2xl bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-400 hover:to-orange-400 text-stone-950 font-black text-sm shadow-xl shadow-amber-500/20 active:scale-95 transition-all duration-200"
                            >
                                <span>🚀</span>
                                <span>طلب ترقية الباقة وتفعيل الميزة فوراً</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Unlocked Active Floor Plan Canvas -->
                <div v-else class="space-y-4">
                    <!-- Control Toolbar -->
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 shadow-sm flex flex-wrap items-center justify-between gap-4">
                        <!-- Mode Switcher & Zone Tabs -->
                        <div class="flex flex-wrap items-center gap-3">
                            <!-- Mode Switcher (Monitor vs Designer) -->
                            <div class="flex items-center bg-slate-100 dark:bg-slate-800 p-1 rounded-2xl border border-slate-200 dark:border-slate-700">
                                <button
                                    type="button"
                                    @click="editorMode = 'monitor'"
                                    class="px-3.5 py-1.5 rounded-xl text-xs font-extrabold transition-all flex items-center gap-1.5"
                                    :class="editorMode === 'monitor'
                                        ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-sm'
                                        : 'text-slate-500 hover:text-slate-900 dark:hover:text-white'"
                                >
                                    <span>👁️</span>
                                    <span>المراقبة الحية والتشغيل</span>
                                </button>
                                <button
                                    type="button"
                                    @click="editorMode = 'designer'"
                                    class="px-3.5 py-1.5 rounded-xl text-xs font-extrabold transition-all flex items-center gap-1.5"
                                    :class="editorMode === 'designer'
                                        ? 'bg-amber-500 text-stone-950 shadow-sm'
                                        : 'text-slate-500 hover:text-slate-900 dark:hover:text-white'"
                                >
                                    <span>📐</span>
                                    <span>المحرر المعماري (CAD)</span>
                                </button>
                            </div>

                            <!-- Zone Filter Tabs -->
                            <div class="hidden sm:flex items-center gap-1.5 overflow-x-auto">
                                <button
                                    v-for="z in ['all', 'main', 'vip', 'terrace']"
                                    :key="z"
                                    type="button"
                                    @click="activeZone = z"
                                    class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all whitespace-nowrap"
                                    :class="activeZone === z
                                        ? 'bg-slate-900 text-white dark:bg-slate-700 dark:text-amber-300'
                                        : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800'"
                                >
                                    {{ getZoneLabel(z) }}
                                </button>
                            </div>
                        </div>

                        <!-- Status Indicators Legend matching reference image -->
                        <div class="hidden lg:flex items-center gap-4 text-xs font-semibold">
                            <span class="inline-flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400">
                                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 ring-4 ring-emerald-500/20 animate-pulse"></span>
                                <span>جلسة نشطة (Active)</span>
                            </span>
                            <span class="inline-flex items-center gap-1.5 text-amber-500">
                                <span class="w-2.5 h-2.5 rounded-full bg-amber-500 ring-4 ring-amber-500/20"></span>
                                <span>حجز VIP مؤكد (Reserved)</span>
                            </span>
                            <span class="inline-flex items-center gap-1.5 text-slate-400">
                                <span class="w-2.5 h-2.5 rounded-full bg-slate-500 ring-4 ring-slate-500/20"></span>
                                <span>طاولة شاغرة (Available)</span>
                            </span>
                        </div>

                        <!-- Right Actions -->
                        <div class="flex items-center gap-2">
                            <!-- Toggle Architecture Elements -->
                            <button
                                type="button"
                                @click="showArchitecturalElements = !showArchitecturalElements"
                                class="hidden md:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold border transition-all"
                                :class="showArchitecturalElements
                                    ? 'bg-slate-100 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200'
                                    : 'text-slate-400 border-transparent hover:bg-slate-100 dark:hover:bg-slate-800'"
                                title="إظهار / إخفاء الجدران والمعالم المعمارية"
                            >
                                <span>🏗️</span>
                                <span>المعالم</span>
                            </button>

                            <!-- VIP Reservations Drawer Button -->
                            <button
                                type="button"
                                @click="isReservationsDrawerOpen = true"
                                class="relative inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-gradient-to-r from-amber-500/15 to-orange-500/15 border border-amber-500/40 text-amber-700 dark:text-amber-300 text-xs sm:text-sm font-black transition-all hover:bg-amber-500/25"
                            >
                                <span>👑</span>
                                <span>حجوزات VIP</span>
                                <span v-if="reservations.length" class="w-5 h-5 rounded-full bg-amber-500 text-stone-950 text-[10px] flex items-center justify-center font-bold font-mono">
                                    {{ reservations.length }}
                                </span>
                            </button>

                            <!-- Copy Public VIP Link -->
                            <button
                                type="button"
                                @click="copyPublicVipBookingLink"
                                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold transition-all"
                                title="نسخ رابط صفحة الحجز المرئي للزبائن"
                            >
                                <span>🔗</span>
                                <span>{{ isCopiedLink ? 'تم النسخ!' : 'رابط الحجز' }}</span>
                            </button>

                            <!-- Zoom Controls -->
                            <div class="flex items-center bg-slate-100 dark:bg-slate-800 rounded-xl px-1 py-0.5 text-xs font-mono">
                                <button @click="zoomOut" class="px-2 py-1 hover:text-amber-500 font-bold" title="تصغير">-</button>
                                <span @click="resetZoom" class="px-1.5 cursor-pointer select-none font-bold text-slate-600 dark:text-slate-300">{{ Math.round(zoomLevel * 100) }}%</span>
                                <button @click="zoomIn" class="px-2 py-1 hover:text-amber-500 font-bold" title="تكبير">+</button>
                            </div>

                            <!-- Add Table Button (Designer Mode) -->
                            <button
                                v-if="editorMode === 'designer'"
                                type="button"
                                @click="isAddModalOpen = true"
                                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-100 text-xs sm:text-sm font-bold transition-all"
                            >
                                <span>➕</span>
                                <span>طاولة</span>
                            </button>

                            <!-- Save Button (Designer Mode) -->
                            <button
                                v-if="editorMode === 'designer'"
                                type="button"
                                @click="saveLayout"
                                :disabled="isSaving"
                                class="inline-flex items-center gap-2 px-5 py-2 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white text-xs sm:text-sm font-bold shadow-md shadow-emerald-600/20 disabled:opacity-50 transition-all"
                            >
                                <svg v-if="isSaving" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                <span v-else>💾</span>
                                <span>{{ isSaving ? 'جاري الحفظ...' : 'حفظ المخطط' }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Save Feedback Banner -->
                    <div v-if="saveSuccess" class="bg-emerald-500/15 border border-emerald-500/30 text-emerald-800 dark:text-emerald-300 px-4 py-2.5 rounded-2xl text-xs sm:text-sm font-bold flex items-center justify-between animate-fadeIn">
                        <span>✨ تم حفظ مواقع وإعدادات الطاولات بنجاح على المخطط العام.</span>
                        <button @click="saveSuccess = false" class="opacity-70 hover:opacity-100">✕</button>
                    </div>

                    <!-- Master Architectural CAD Blueprint Viewport -->
                    <div class="relative w-full rounded-3xl bg-[#070b14] border-2 border-slate-800/90 shadow-2xl overflow-hidden min-h-[660px] select-none">
                        <!-- CAD Grid Background -->
                        <div
                            class="absolute inset-0 pointer-events-none opacity-25"
                            style="background-size: 30px 30px; background-image: linear-gradient(to right, #1e293b 1px, transparent 1px), linear-gradient(to bottom, #1e293b 1px, transparent 1px);"
                        ></div>

                        <!-- Architectural Blueprint Frame Lines -->
                        <div class="absolute inset-3 border border-slate-800/60 pointer-events-none rounded-2xl"></div>

                        <!-- Dimension Markings (CAD Style) -->
                        <div class="absolute top-2 left-10 text-[9px] font-mono text-slate-700 pointer-events-none tracking-widest">
                            [ SCALE 1:50 • ARCHITECTURAL FLOOR BLUEPRINT • TEDALLAL ENGINE ]
                        </div>

                        <!-- Canvas Scalable Container -->
                        <div
                            id="cad-canvas"
                            class="relative w-[1140px] h-[640px] origin-top-right transition-transform duration-100 mx-auto"
                            :style="{ transform: `scale(${zoomLevel})` }"
                            @click="selectedTable = null"
                        >
                            <!-- ================= ARCHITECTURAL WALLS & STRUCTURE ================= -->
                            <template v-if="showArchitecturalElements">
                                <!-- Outer Perimeter Double Wall (CAD Architecture) -->
                                <div class="absolute top-6 left-6 right-6 bottom-6 border-2 border-slate-700/80 rounded-2xl pointer-events-none">
                                    <div class="absolute inset-1 border border-slate-800/60 rounded-xl"></div>
                                </div>

                                <!-- Left Main Entrance with Inward Swing Door Arc -->
                                <div class="absolute top-1/2 left-4 -translate-y-1/2 w-8 h-28 bg-[#070b14] border-y-2 border-slate-600 pointer-events-none flex flex-col items-center justify-center z-10">
                                    <div class="w-1.5 h-12 bg-emerald-500/80 rounded-full animate-pulse shadow-[0_0_10px_rgba(16,185,129,0.8)]"></div>
                                    <span class="text-[8px] font-mono text-emerald-400 rotate-90 whitespace-nowrap mt-2 tracking-wider">ENTRANCE</span>
                                </div>

                                <!-- Host / Reception Counter Desk -->
                                <div class="absolute top-[180px] left-[75px] w-24 h-12 bg-stone-900 border border-stone-700/80 rounded-lg pointer-events-none flex flex-col items-center justify-center shadow-lg">
                                    <span class="text-[9px] font-bold text-amber-400/90 font-mono">HOST DESK</span>
                                    <span class="text-[8px] text-stone-500 font-mono">Reception</span>
                                </div>

                                <!-- Bottom Patio / Terrace Glass Sliding Exit -->
                                <div class="absolute bottom-4 left-1/2 -translate-x-1/2 w-44 h-5 bg-[#070b14] border-x-2 border-cyan-500/40 pointer-events-none flex items-center justify-center z-10">
                                    <span class="text-[8px] font-mono text-cyan-400/80 tracking-widest uppercase">Patio Terrace Exit</span>
                                </div>

                                <!-- Central Corridor Guideline (Dashed Lines) -->
                                <div class="absolute top-1/2 left-28 right-[360px] -translate-y-1/2 h-[130px] border-y border-dashed border-slate-800/80 pointer-events-none flex items-center justify-center">
                                    <span class="text-[10px] uppercase font-mono tracking-widest text-slate-700/60">M a i n &nbsp; D i n i n g &nbsp; H a l l</span>
                                </div>

                                <!-- ================= VIP PRIVATE LOUNGE SALON (Right Wing) ================= -->
                                <!-- VIP Enclosing Wall with Golden Ambient Halo matching reference image -->
                                <div class="absolute top-8 right-8 bottom-8 w-[310px] border-2 border-amber-500/90 rounded-2xl bg-slate-950/70 shadow-[0_0_35px_rgba(245,158,11,0.22)] pointer-events-none p-4 flex flex-col justify-between">
                                    <!-- VIP Room Header Badge matching image -->
                                    <div class="bg-black/85 backdrop-blur-md border border-amber-500/40 px-3.5 py-2 rounded-xl self-start shadow-xl">
                                        <div class="flex items-center gap-1.5 text-xs font-black text-amber-300">
                                            <span>👑</span>
                                            <span>VIP Lounge</span>
                                        </div>
                                        <div class="text-[9px] text-slate-400 font-mono mt-0.5">Private dining salon</div>
                                    </div>

                                    <!-- Top VIP Luxury Sofa Suite with Coffee Table & Floor Lamp -->
                                    <div class="relative w-full h-16 bg-stone-900/90 border border-stone-700/80 rounded-xl flex items-center justify-between px-3 shadow-inner">
                                        <!-- Floor Lamp -->
                                        <div class="w-4 h-4 rounded-full bg-amber-400/80 shadow-[0_0_12px_rgba(245,158,11,0.9)] flex items-center justify-center text-[8px]">💡</div>
                                        <div class="text-[10px] font-bold text-stone-400 font-mono">🛋️ Luxury Sofa Suite</div>
                                        <div class="w-8 h-8 rounded-md bg-stone-950 border border-stone-800 flex items-center justify-center text-[8px] text-stone-500">Table</div>
                                    </div>

                                    <!-- Partition Divider between Salons -->
                                    <div class="w-full border-t border-dashed border-amber-500/30 my-1"></div>

                                    <!-- Bottom VIP Luxury Sofa Suite with Coffee Table & Floor Lamp -->
                                    <div class="relative w-full h-16 bg-stone-900/90 border border-stone-700/80 rounded-xl flex items-center justify-between px-3 shadow-inner">
                                        <!-- Floor Lamp -->
                                        <div class="w-4 h-4 rounded-full bg-amber-400/80 shadow-[0_0_12px_rgba(245,158,11,0.9)] flex items-center justify-center text-[8px]">💡</div>
                                        <div class="text-[10px] font-bold text-stone-400 font-mono">🛋️ Luxury Sofa Suite</div>
                                        <div class="w-8 h-8 rounded-md bg-stone-950 border border-stone-800 flex items-center justify-center text-[8px] text-stone-500">Table</div>
                                    </div>
                                </div>

                                <!-- VIP Door Openings (CAD Arch Swing Style) -->
                                <div class="absolute top-[260px] right-[316px] w-3 h-14 bg-[#070b14] border-y border-amber-500/60 pointer-events-none z-10 flex items-center justify-center">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-ping"></span>
                                </div>
                                <div class="absolute top-[370px] right-[316px] w-3 h-14 bg-[#070b14] border-y border-amber-500/60 pointer-events-none z-10 flex items-center justify-center">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-ping"></span>
                                </div>
                            </template>

                            <!-- ================= DYNAMIC TABLES ON CANVAS ================= -->
                            <div
                                v-for="table in visibleTables"
                                :key="table.id"
                                class="absolute transition-all duration-200 group z-20 select-none"
                                :class="[
                                    editorMode === 'designer' ? 'cursor-grab active:cursor-grabbing' : 'cursor-pointer hover:scale-105',
                                    selectedTable?.id === table.id ? 'z-30' : ''
                                ]"
                                :style="{
                                    left: table.pos_x + 'px',
                                    top: table.pos_y + 'px',
                                    width: (table.shape === 'vip' ? 140 : (table.shape === 'rectangle' ? 130 : 90)) + 'px',
                                    height: (table.shape === 'rectangle' ? 80 : 90) + 'px',
                                    transform: `rotate(${table.rotation || 0}deg)`,
                                }"
                                @mousedown.stop="onMouseDown($event, table)"
                                @click.stop="selectedTable = table"
                            >
                                <!-- ================= CHAIRS RENDERING AROUND TABLES ================= -->
                                <!-- 4 Chairs for Circular Table -->
                                <template v-if="table.shape === 'circle'">
                                    <div class="absolute -top-3.5 left-1/2 -translate-x-1/2 w-6 h-2.5 rounded-full bg-stone-800 border border-stone-600/80 pointer-events-none shadow-xs"></div>
                                    <div class="absolute -bottom-3.5 left-1/2 -translate-x-1/2 w-6 h-2.5 rounded-full bg-stone-800 border border-stone-600/80 pointer-events-none shadow-xs"></div>
                                    <div class="absolute top-1/2 -left-3.5 -translate-y-1/2 w-2.5 h-6 rounded-full bg-stone-800 border border-stone-600/80 pointer-events-none shadow-xs"></div>
                                    <div class="absolute top-1/2 -right-3.5 -translate-y-1/2 w-2.5 h-6 rounded-full bg-stone-800 border border-stone-600/80 pointer-events-none shadow-xs"></div>
                                </template>

                                <!-- 6/8 Chairs for Rectangular Banquet Table -->
                                <template v-else-if="table.shape === 'rectangle'">
                                    <!-- Top 3 Chairs -->
                                    <div class="absolute -top-3.5 left-3 w-6 h-2.5 rounded-full bg-stone-800 border border-stone-600/80 pointer-events-none shadow-xs"></div>
                                    <div class="absolute -top-3.5 left-1/2 -translate-x-1/2 w-6 h-2.5 rounded-full bg-stone-800 border border-stone-600/80 pointer-events-none shadow-xs"></div>
                                    <div class="absolute -top-3.5 right-3 w-6 h-2.5 rounded-full bg-stone-800 border border-stone-600/80 pointer-events-none shadow-xs"></div>
                                    <!-- Bottom 3 Chairs -->
                                    <div class="absolute -bottom-3.5 left-3 w-6 h-2.5 rounded-full bg-stone-800 border border-stone-600/80 pointer-events-none shadow-xs"></div>
                                    <div class="absolute -bottom-3.5 left-1/2 -translate-x-1/2 w-6 h-2.5 rounded-full bg-stone-800 border border-stone-600/80 pointer-events-none shadow-xs"></div>
                                    <div class="absolute -bottom-3.5 right-3 w-6 h-2.5 rounded-full bg-stone-800 border border-stone-600/80 pointer-events-none shadow-xs"></div>
                                </template>

                                <!-- ================= TABLE CORE & NEON HALO RING ================= -->
                                <div
                                    class="w-full h-full flex flex-col items-center justify-center text-center p-2 transition-all duration-300 relative shadow-2xl"
                                    :class="[
                                        table.shape === 'circle' ? 'rounded-full' : (table.shape === 'vip' ? 'rounded-3xl' : 'rounded-2xl'),
                                        table.active_session
                                            ? 'bg-slate-900/90 ring-4 ring-emerald-500 shadow-[0_0_35px_rgba(16,185,129,0.7)] border-2 border-emerald-400'
                                            : (getTableReservation(table.id)
                                                ? 'bg-slate-900/90 ring-4 ring-amber-500 shadow-[0_0_35px_rgba(245,158,11,0.65)] border-2 border-amber-400'
                                                : (table.zone === 'vip'
                                                    ? 'bg-slate-900/90 ring-2 ring-amber-500/60 shadow-[0_0_20px_rgba(245,158,11,0.3)] border border-amber-500/50'
                                                    : 'bg-[#0f172a] ring-2 ring-slate-700/80 hover:ring-slate-500 border border-slate-700/60'))
                                    ]"
                                >
                                    <!-- Neon Breathing Pulse for Active Session -->
                                    <div
                                        v-if="table.active_session"
                                        class="absolute inset-0 rounded-full animate-ping opacity-25 bg-emerald-400 pointer-events-none"
                                    ></div>

                                    <!-- Table Label Badge (Floating Tooltip matching reference image) -->
                                    <div
                                        class="absolute -top-7 whitespace-nowrap px-2.5 py-0.5 rounded-md text-[10px] font-black flex items-center gap-1 shadow-xl pointer-events-none transition-all z-30"
                                        :style="{ transform: `rotate(${-(table.rotation || 0)}deg)` }"
                                        :class="table.active_session
                                            ? 'bg-emerald-950/95 text-emerald-300 border border-emerald-500 shadow-emerald-900/40'
                                            : (getTableReservation(table.id)
                                                ? 'bg-amber-950/95 text-amber-300 border border-amber-500 shadow-amber-900/40'
                                                : 'bg-slate-900/90 text-slate-300 border border-slate-700')"
                                    >
                                        <span>{{ table.name }}</span>
                                        <span class="opacity-80">
                                            • {{ table.active_session ? 'Active' : (getTableReservation(table.id) ? 'Reserved' : 'Available') }}
                                        </span>
                                    </div>

                                    <!-- Table Number Inside -->
                                    <span class="font-black text-sm text-white drop-shadow-md tracking-tight">
                                        {{ table.name.replace(/[^0-9]/g, '') ? '#' + table.name.replace(/[^0-9]/g, '') : table.name }}
                                    </span>

                                    <!-- Seat Capacity -->
                                    <span class="text-[9px] text-slate-400 font-mono mt-0.5">
                                        {{ table.capacity }} Seats
                                    </span>

                                    <!-- IoT Connected ESP32 Cyan Dot -->
                                    <span
                                        v-if="table.iot_device_id"
                                        class="absolute -bottom-1 -right-1 w-3.5 h-3.5 rounded-full bg-cyan-400 ring-2 ring-slate-950 shadow-[0_0_8px_rgba(6,182,212,0.9)]"
                                        title="متصل بشريحة ESP32 الذكية"
                                    ></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Selected Table Detail Drawer -->
                <div
                    v-if="selectedTable"
                    class="fixed inset-y-0 left-0 z-50 w-full sm:w-96 bg-white dark:bg-slate-900 shadow-2xl border-r border-slate-200 dark:border-slate-800 p-6 flex flex-col justify-between overflow-y-auto animate-slideRight"
                    dir="rtl"
                >
                    <div class="space-y-6">
                        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-4">
                            <div>
                                <h3 class="text-lg font-extrabold text-slate-900 dark:text-white">إعدادات الطاولة</h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ selectedTable.name }}</p>
                            </div>
                            <button
                                @click="selectedTable = null"
                                class="p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                            >
                                ✕
                            </button>
                        </div>

                        <!-- Form Fields -->
                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">اسم أو رقم الطاولة</label>
                                <input
                                    v-model="selectedTable.name"
                                    type="text"
                                    class="w-full px-3.5 py-2.5 rounded-xl text-sm border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-amber-500"
                                />
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">شكل الطاولة الهندسي</label>
                                <select
                                    v-model="selectedTable.shape"
                                    class="w-full px-3.5 py-2.5 rounded-xl text-sm border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-amber-500"
                                >
                                    <option value="circle">دائرية (Standard Circle)</option>
                                    <option value="rectangle">مستطيلة (Family Rectangle)</option>
                                    <option value="booth">كابينة خاصة (Booth Seat)</option>
                                    <option value="vip">جناح فاخر (VIP Lounge)</option>
                                </select>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">سعة المقاعد</label>
                                    <input
                                        v-model.number="selectedTable.capacity"
                                        type="number"
                                        min="1"
                                        max="30"
                                        class="w-full px-3.5 py-2.5 rounded-xl text-sm border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-amber-500"
                                    />
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">المنطقة</label>
                                    <select
                                        v-model="selectedTable.zone"
                                        class="w-full px-3.5 py-2.5 rounded-xl text-sm border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-amber-500"
                                    >
                                        <option value="main">الصالة الرئيسية</option>
                                        <option value="vip">جناح النخبة (VIP Lounge)</option>
                                        <option value="terrace">التراس الخارجي</option>
                                        <option value="family">القسم العائلي</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Table Rotation & Quick Actions -->
                            <div class="grid grid-cols-2 gap-2 pt-1">
                                <button
                                    type="button"
                                    @click="rotateSelectedTable"
                                    class="py-2.5 px-3 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 text-xs font-bold flex items-center justify-center gap-1.5 transition"
                                >
                                    <span>🔄</span>
                                    <span>تدوير ({{ selectedTable.rotation || 0 }}°)</span>
                                </button>
                                <button
                                    type="button"
                                    @click="duplicateSelectedTable"
                                    class="py-2.5 px-3 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 text-xs font-bold flex items-center justify-center gap-1.5 transition"
                                >
                                    <span>📋</span>
                                    <span>تكرار الطاولة</span>
                                </button>
                            </div>

                            <!-- IoT Chipset Binding Section -->
                            <div class="bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/80 rounded-2xl p-4">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="text-base">💡</span>
                                    <label class="text-xs font-extrabold text-slate-800 dark:text-slate-200">
                                        معرّف شريحة الإضاءة الذكية (ESP32)
                                    </label>
                                </div>
                                <input
                                    v-model="selectedTable.iot_device_id"
                                    type="text"
                                    dir="ltr"
                                    placeholder="e.g. ESP32_TBL_08 أو MAC"
                                    class="w-full px-3 py-2 rounded-xl text-xs font-mono border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:ring-2 focus:ring-cyan-500"
                                />
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1.5">
                                    أدخل معرّف الجهاز لمزامنة إضاءة الليدات الفيزيائية مع حالة هذه الطاولة.
                                </p>
                            </div>

                            <!-- Active Session Management Card -->
                            <div v-if="selectedTable.active_session" class="p-3.5 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 space-y-2">
                                <div class="flex items-center justify-between text-xs font-bold text-emerald-800 dark:text-emerald-300">
                                    <span class="flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                        <span>جلسة نشطة حالياً</span>
                                    </span>
                                    <span class="text-[10px] font-mono opacity-80">معرّف: #{{ selectedTable.active_session.id }}</span>
                                </div>
                                <button
                                    type="button"
                                    @click="closeSessionForTable"
                                    class="w-full py-2 px-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold shadow-xs transition"
                                >
                                    🧹 إخلاء الطاولة وإنهاء الجلسة
                                </button>
                            </div>

                            <!-- VIP Booking on Table (if any) -->
                            <div v-if="getTableReservation(selectedTable.id)" class="p-3.5 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 space-y-2">
                                <div class="flex items-center justify-between text-xs font-bold text-amber-800 dark:text-amber-300">
                                    <span class="flex items-center gap-1.5">
                                        <span>👑</span>
                                        <span>حجز VIP مؤكد اليوم</span>
                                    </span>
                                    <span class="text-[10px] font-mono">{{ getTableReservation(selectedTable.id)?.time_slot }}</span>
                                </div>
                                <p class="text-xs text-amber-900 dark:text-amber-200 font-semibold">
                                    الضيف: {{ getTableReservation(selectedTable.id)?.guest_name }} ({{ getTableReservation(selectedTable.id)?.guests_count }} أشخاص)
                                </p>
                                <button
                                    v-if="getTableReservation(selectedTable.id)?.status !== 'seated'"
                                    type="button"
                                    @click="markReservationSeated(getTableReservation(selectedTable.id)!)"
                                    class="w-full py-2 px-3 rounded-xl bg-amber-500 hover:bg-amber-400 text-stone-950 text-xs font-bold shadow-xs transition"
                                >
                                    🚶‍♂️ تسجيل وصول الضيف والجلوس
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Bottom Drawer Buttons -->
                    <div class="pt-6 border-t border-slate-100 dark:border-slate-800 space-y-2">
                        <button
                            type="button"
                            @click="updateSelectedTable"
                            class="w-full py-3 rounded-xl bg-amber-500 hover:bg-amber-400 text-stone-950 font-black text-sm shadow-md transition"
                        >
                            حفظ تعديلات الطاولة
                        </button>
                        <button
                            type="button"
                            @click="deleteSelectedTable"
                            class="w-full py-2.5 rounded-xl bg-rose-50 hover:bg-rose-100 dark:bg-rose-950/40 dark:hover:bg-rose-950/60 text-rose-600 dark:text-rose-400 font-bold text-xs transition"
                        >
                            حذف الطاولة من المخطط
                        </button>
                    </div>
                </div>

                <!-- VIP Reservations Drawer -->
                <div
                    v-if="isReservationsDrawerOpen"
                    class="fixed inset-y-0 right-0 z-50 w-full sm:w-[440px] bg-white dark:bg-slate-900 shadow-2xl border-l border-slate-200 dark:border-slate-800 p-6 flex flex-col justify-between overflow-y-auto animate-slideLeft"
                    dir="rtl"
                >
                    <div class="space-y-6">
                        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-4">
                            <div class="flex items-center gap-2">
                                <span class="text-xl">👑</span>
                                <div>
                                    <h3 class="text-lg font-black text-slate-900 dark:text-white">حجوزات زبائن VIP</h3>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">قائمة الحجوزات المؤكدة اليوم</p>
                                </div>
                            </div>
                            <button
                                @click="isReservationsDrawerOpen = false"
                                class="p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                            >
                                ✕
                            </button>
                        </div>

                        <!-- Reservations List -->
                        <div v-if="reservations.length" class="space-y-3">
                            <div
                                v-for="res in reservations"
                                :key="res.id"
                                class="p-4 rounded-2xl border transition-all"
                                :class="res.status === 'seated'
                                    ? 'bg-emerald-50/60 dark:bg-emerald-950/20 border-emerald-300 dark:border-emerald-800'
                                    : 'bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-700'"
                            >
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-2">
                                        <span class="font-black text-sm text-slate-900 dark:text-white">{{ res.guest_name }}</span>
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-mono font-bold bg-amber-500/20 text-amber-600 dark:text-amber-400">
                                            {{ res.code }}
                                        </span>
                                    </div>
                                    <span class="text-xs font-mono font-bold text-slate-600 dark:text-slate-300">
                                        ⏰ {{ res.time_slot }}
                                    </span>
                                </div>

                                <div class="flex items-center gap-3 text-xs text-slate-500 dark:text-slate-400 mb-3">
                                    <span>🍽️ {{ res.table_name || ('طاولة #' + res.table_id) }}</span>
                                    <span>👥 {{ res.guests_count }} ضيوف</span>
                                    <span v-if="res.guest_phone" dir="ltr" class="font-mono">{{ res.guest_phone }}</span>
                                </div>

                                <div v-if="res.special_requests" class="text-xs text-slate-600 dark:text-slate-300 bg-white/70 dark:bg-slate-900/70 p-2.5 rounded-xl border border-slate-100 dark:border-slate-800 mb-3 italic">
                                    "{{ res.special_requests }}"
                                </div>

                                <!-- Action buttons -->
                                <div class="flex items-center gap-2">
                                    <button
                                        v-if="res.status !== 'seated'"
                                        type="button"
                                        @click="markReservationSeated(res)"
                                        class="flex-1 py-2 px-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition flex items-center justify-center gap-1"
                                    >
                                        <span>🚶‍♂️</span>
                                        <span>تسجيل وصول الضيف</span>
                                    </button>
                                    <span v-else class="text-xs font-bold text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                                        <span>✓</span>
                                        <span>تم جلوس الضيف في الصالة</span>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Empty State -->
                        <div v-else class="text-center py-12 px-4">
                            <span class="text-4xl">🎫</span>
                            <h4 class="font-bold text-slate-700 dark:text-slate-300 text-sm mt-3">لا توجد حجوزات VIP اليوم</h4>
                            <p class="text-xs text-slate-400 mt-1">شارك رابط الحجز المسبق لتمكين عملائك من اختيار طاولاتهم بصرياً.</p>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="pt-4 border-t border-slate-100 dark:border-slate-800">
                        <button
                            type="button"
                            @click="copyPublicVipBookingLink"
                            class="w-full py-3 rounded-xl bg-amber-500 hover:bg-amber-400 text-stone-950 font-black text-xs sm:text-sm shadow-md transition flex items-center justify-center gap-2"
                        >
                            <span>🔗</span>
                            <span>{{ isCopiedLink ? 'تم نسخ الرابط بنجاح!' : 'نسخ رابط حجز VIP للعملاء' }}</span>
                        </button>
                    </div>
                </div>

                <!-- Add Table Modal -->
                <div
                    v-if="isAddModalOpen"
                    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs animate-fadeIn"
                    dir="rtl"
                >
                    <div class="w-full max-w-md bg-white dark:bg-slate-900 rounded-3xl p-6 shadow-2xl border border-slate-200 dark:border-slate-800 space-y-5">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-extrabold text-slate-900 dark:text-white">إضافة طاولة جديدة إلى المخطط</h3>
                            <button @click="isAddModalOpen = false" class="text-slate-400 hover:text-slate-600">✕</button>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">اسم أو رقم الطاولة</label>
                                <input
                                    v-model="newTableForm.name"
                                    type="text"
                                    placeholder="مثال: طاولة 12 أو جناح A"
                                    class="w-full px-3.5 py-2.5 rounded-xl text-sm border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white"
                                />
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">الشكل</label>
                                    <select
                                        v-model="newTableForm.shape"
                                        class="w-full px-3 py-2.5 rounded-xl text-xs border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white"
                                    >
                                        <option value="circle">دائرية (Circle)</option>
                                        <option value="rectangle">مستطيلة (Banquet)</option>
                                        <option value="booth">كابينة (Booth)</option>
                                        <option value="vip">جناح VIP فاخر</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">السعة</label>
                                    <input
                                        v-model.number="newTableForm.capacity"
                                        type="number"
                                        min="1"
                                        max="30"
                                        class="w-full px-3 py-2.5 rounded-xl text-xs border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white"
                                    />
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">المنطقة</label>
                                <select
                                    v-model="newTableForm.zone"
                                    class="w-full px-3.5 py-2.5 rounded-xl text-sm border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white"
                                >
                                    <option value="main">الصالة الرئيسية</option>
                                    <option value="vip">جناح النخبة (VIP Lounge)</option>
                                    <option value="terrace">التراس الخارجي</option>
                                    <option value="family">القسم العائلي</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">معرّف جهاز الـ ESP32 (اختياري)</label>
                                <input
                                    v-model="newTableForm.iot_device_id"
                                    type="text"
                                    dir="ltr"
                                    placeholder="ESP32_DEV_ID"
                                    class="w-full px-3.5 py-2 rounded-xl text-xs font-mono border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white"
                                />
                            </div>
                        </div>

                        <div class="flex items-center gap-3 pt-4">
                            <button
                                type="button"
                                @click="createTableFromFloorPlan"
                                class="flex-1 py-3 rounded-xl bg-amber-500 hover:bg-amber-400 text-stone-950 font-black text-sm shadow-md transition"
                            >
                                إضافة وتوليد رمز QR
                            </button>
                            <button
                                type="button"
                                @click="isAddModalOpen = false"
                                class="px-5 py-3 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold text-sm"
                            >
                                إلغاء
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
@keyframes fadeIn {
    from { opacity: 0; transform: scale(0.98); }
    to { opacity: 1; transform: scale(1); }
}

@keyframes slideRight {
    from { transform: translateX(-100%); }
    to { transform: translateX(0); }
}

@keyframes slideLeft {
    from { transform: translateX(100%); }
    to { transform: translateX(0); }
}

.animate-fadeIn {
    animation: fadeIn 0.2s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.animate-slideRight {
    animation: slideRight 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.animate-slideLeft {
    animation: slideLeft 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
</style>
