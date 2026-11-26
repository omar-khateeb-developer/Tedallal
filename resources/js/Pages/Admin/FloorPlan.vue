<script setup lang="ts">
import AdminNav from '@/Components/Admin/AdminNav.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted, defineAsyncComponent } from 'vue';
import axios from 'axios';
import { formatMoney } from '@/utils/money';
import { formatArabicCount, formatTableBadgeCode } from '@/utils/arabic';
import { soundAlerts } from '@/utils/soundAlerts';
import {
    getFloorZoneLabel,
    TABLE_STAGE_LABELS,
    type ReservationItem,
    type TableItem,
    type WaitlistItem,
} from '@/types/floorPlan';

const AddTableModal = defineAsyncComponent(() => import('@/Components/Admin/FloorPlan/AddTableModal.vue'));
const ReservationsDrawer = defineAsyncComponent(() => import('@/Components/Admin/FloorPlan/ReservationsDrawer.vue'));
const TableSettingsDrawer = defineAsyncComponent(() => import('@/Components/Admin/FloorPlan/TableSettingsDrawer.vue'));
const WaitlistDrawer = defineAsyncComponent(() => import('@/Components/Admin/FloorPlan/WaitlistDrawer.vue'));

const props = defineProps<{
    tables: TableItem[];
    zones: string[];
    restaurant: { id: number; name: string; slug: string; currency?: string; features?: any; whatsapp_status?: string };
    todayReservations?: ReservationItem[];
    todayWaitlist?: WaitlistItem[];
    isFeatureLocked: boolean;
}>();

// State — automatically stagger any unpositioned or physically overlapping tables (>=135x130 clear gap)
const placedCoords: Array<{ x: number; y: number }> = [];
const hasPhysicalOverlap = (x: number, y: number) =>
    placedCoords.some((c) => Math.abs(c.x - x) < 135 && Math.abs(c.y - y) < 130);

const tables = ref<TableItem[]>(props.tables.map((t, idx) => {
    let px = Number(t.pos_x) || 0;
    let py = Number(t.pos_y) || 0;
    const isUnpositioned = px <= 0 && py <= 0;
    if (isUnpositioned || hasPhysicalOverlap(px, py)) {
        let slot = idx;
        while (slot < 100) {
            const col = slot % 4;
            const row = Math.floor(slot / 4);
            const candidateX = 65 + col * 185;
            const candidateY = 82 + row * 168;
            if (!hasPhysicalOverlap(candidateX, candidateY)) {
                px = candidateX;
                py = candidateY;
                break;
            }
            slot++;
        }
    }
    placedCoords.push({ x: px, y: py });

    return {
        ...t,
        pos_x: px,
        pos_y: py,
        shape: t.shape || 'circle',
        rotation: Number(t.rotation) || 0,
        capacity: Number(t.capacity) || 4,
        zone: t.zone || 'main',
        width: Number(t.width) || (t.shape === 'vip' ? 140 : (t.shape === 'rectangle' ? 130 : 90)),
        height: Number(t.height) || (t.shape === 'rectangle' ? 80 : 90),
        operational_stage: t.operational_stage || (t.active_session ? 'seated' : 'available'),
        turn_minutes: Number(t.turn_minutes) || 0,
        stage_minutes: Number(t.stage_minutes) || 0,
        bill_wait_minutes: t.bill_wait_minutes ?? null,
    };
}));

const reservations = ref<ReservationItem[]>(props.todayReservations || []);
const waitlist = ref<WaitlistItem[]>(props.todayWaitlist || []);
const activeZone = ref<string>('all');
const selectedTable = ref<TableItem | null>(null);
const activeTableId = ref<number | null>(null);
const activeTable = computed(() => tables.value.find(t => t.id === activeTableId.value));
const editorMode = ref<'monitor' | 'designer'>('monitor'); // monitor mode prevents accidental drag
const displayMode = ref<'canvas' | 'grid'>('canvas');
const stageFilter = ref<'all' | 'available' | 'busy' | 'alerts'>('all');
const zoomLevel = ref<number>(1);
const isSaving = ref(false);
const saveSuccess = ref(false);
const isAddModalOpen = ref(false);
const isReservationsDrawerOpen = ref(false);
const isWaitlistDrawerOpen = ref(false);
const isCopiedLink = ref(false);
const showArchitecturalElements = ref(true);

const activeWaitingCount = computed(() => {
    return waitlist.value.filter(e => e.status === 'waiting' || e.status === 'notified').length;
});

const isDragging = ref(false);
const draggedTableId = ref<number | null>(null);
const dragOffset = ref({ x: 0, y: 0 });
const snapToGrid = ref(true);
const gridSize = 20;

// Filtered tables by zone + quick status pill
const visibleTables = computed(() => {
    return tables.value.filter(t => {
        if (activeZone.value !== 'all' && t.zone !== activeZone.value) return false;
        if (stageFilter.value === 'available') {
            return !t.active_session && (!t.operational_stage || t.operational_stage === 'available');
        }
        if (stageFilter.value === 'busy') {
            return Boolean(t.active_session) || (t.operational_stage && t.operational_stage !== 'available');
        }
        if (stageFilter.value === 'alerts') {
            return Boolean(t.has_waiter_call || t.operational_stage === 'bill_requested' || t.service_call);
        }
        return true;
    });
});

const floorStats = computed(() => {
    let available = 0;
    let busy = 0;
    let alerts = 0;
    for (const t of tables.value) {
        if (t.has_waiter_call || t.operational_stage === 'bill_requested' || t.service_call) alerts++;
        if (t.active_session || (t.operational_stage && t.operational_stage !== 'available')) {
            busy++;
        } else {
            available++;
        }
    }
    return { total: tables.value.length, available, busy, alerts };
});

function formatTurnDuration(minutes?: number): string {
    const m = Math.max(0, Math.round(Number(minutes) || 0));
    if (m <= 0) return '';
    if (m < 60) return `${m} د`;
    const hours = Math.floor(m / 60);
    if (hours < 24) return `${hours} س`;
    const days = Math.floor(hours / 24);
    const remHours = hours % 24;
    return remHours > 0 ? `${days}ي ${remHours}س` : `${days} ي`;
}

function autoArrangeTablesLayout() {
    const mainTables = tables.value.filter(t => t.zone !== 'vip');
    const vipTables = tables.value.filter(t => t.zone === 'vip');

    mainTables.forEach((t, i) => {
        const col = i % 4;
        const row = Math.floor(i / 4);
        t.pos_x = 65 + col * 195;
        t.pos_y = 82 + row * 170;
    });

    vipTables.forEach((t, i) => {
        const row = i % 3;
        const col = Math.floor(i / 3);
        t.pos_x = 890 + col * 130;
        t.pos_y = 95 + row * 170;
    });

    saveSuccess.value = true;
    setTimeout(() => { saveSuccess.value = false; }, 3500);
}

const getZoneLabel = getFloorZoneLabel;

// Reservation check helpers
function getTableReservation(tableId: number): ReservationItem | undefined {
    return reservations.value.find(r => r.table_id === tableId && (r.status === 'confirmed' || r.status === 'seated'));
}

// Table selection & Drawer actions
function selectTable(table: TableItem) {
    activeTableId.value = table.id;
}

function openTableDrawer(table: TableItem) {
    selectedTable.value = table;
    activeTableId.value = table.id;
}

// Dragging logic
function onMouseDown(e: MouseEvent, table: TableItem) {
    if (props.isFeatureLocked || editorMode.value !== 'designer') return;
    isDragging.value = true;
    draggedTableId.value = table.id;
    activeTableId.value = table.id; // Do not open drawer during dragging

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
const page = usePage();
const isRestaurantWhatsappConnected = computed(() => {
    const status = props.restaurant.whatsapp_status || (page.props.auth as any)?.restaurant?.whatsapp_status;
    return status === 'connected';
});
const vipWhatsappWarningToast = ref(false);

function copyPublicVipBookingLink() {
    const url = `${window.location.origin}/r/${props.restaurant.slug}/vip-booking`;
    navigator.clipboard.writeText(url).then(() => {
        isCopiedLink.value = true;
        if (!isRestaurantWhatsappConnected.value) {
            vipWhatsappWarningToast.value = true;
            setTimeout(() => { vipWhatsappWarningToast.value = false; }, 6500);
        }
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

function triggerSavedToast(durationMs = 3000) {
    saveSuccess.value = true;
    setTimeout(() => { saveSuccess.value = false; }, durationMs);
}

function handleTableCreated(created: TableItem) {
    tables.value.push(created);
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
async function closeSessionForTable(targetTable?: TableItem) {
    const table = targetTable || selectedTable.value;
    if (!table) return;
    if (!confirm(`هل أنت متأكد من إنهاء جلسة ${table.name} وإخلائها للزبائن القادمين؟`)) return;
    try {
        await axios.post(`/api/admin/tables/${table.id}/close-session`);
        table.active_session = null;
        table.operational_stage = 'available';
        table.turn_minutes = 0;
        table.has_waiter_call = false;
        if (selectedTable.value && selectedTable.value.id === table.id) {
            selectedTable.value.active_session = null;
            selectedTable.value.operational_stage = 'available';
            selectedTable.value.turn_minutes = 0;
            selectedTable.value.has_waiter_call = false;
        }
        const t = tables.value.find(item => item.id === table.id);
        if (t) {
            t.active_session = null;
            t.operational_stage = 'available';
            t.turn_minutes = 0;
            t.has_waiter_call = false;
        }
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

const stageLabels = TABLE_STAGE_LABELS;

function getTableStageLabel(table: TableItem): string {
    if (table.operational_stage && stageLabels[table.operational_stage]) {
        return stageLabels[table.operational_stage];
    }
    if (table.active_session) return 'جلسة نشطة';
    if (getTableReservation(table.id)) return 'محجوزة اليوم';
    return 'شاغرة';
}

function getTableRingClass(table: TableItem): string {
    if (table.has_waiter_call) {
        return 'bg-slate-900/95 ring-4 ring-amber-400 shadow-[0_0_35px_rgba(251,191,36,0.9)] border-2 border-amber-300 animate-pulse';
    }
    if (table.operational_stage === 'bill_requested') {
        return 'bg-slate-900/95 ring-4 ring-purple-500 shadow-[0_0_35px_rgba(168,85,247,0.85)] border-2 border-purple-400 animate-pulse';
    }
    if (table.operational_stage === 'preparing') {
        return 'bg-slate-900/95 ring-4 ring-amber-500 shadow-[0_0_35px_rgba(245,158,11,0.7)] border-2 border-amber-400';
    }
    if (table.operational_stage === 'served') {
        return 'bg-slate-900/95 ring-4 ring-sky-500 shadow-[0_0_35px_rgba(14,165,233,0.7)] border-2 border-sky-400';
    }
    if (table.operational_stage === 'seated') {
        return 'bg-slate-900/95 ring-4 ring-emerald-500 shadow-[0_0_35px_rgba(16,185,129,0.7)] border-2 border-emerald-400';
    }
    if (table.operational_stage === 'cleaning') {
        return 'bg-slate-900/90 ring-2 ring-stone-400 border-2 border-dashed border-stone-400 shadow-[0_0_20px_rgba(168,162,158,0.4)] text-stone-300';
    }
    if (getTableReservation(table.id)) {
        return 'bg-slate-900/90 ring-4 ring-amber-500 shadow-[0_0_35px_rgba(245,158,11,0.65)] border-2 border-amber-400';
    }
    if (table.zone === 'vip') {
        return 'bg-slate-900/90 ring-2 ring-amber-500/60 shadow-[0_0_20px_rgba(245,158,11,0.3)] border border-amber-500/50';
    }
    return 'bg-[#0f172a] ring-2 ring-slate-700/80 hover:ring-slate-500 border border-slate-700/60';
}

function getTableBadgeClass(table: TableItem): string {
    if (table.has_waiter_call) {
        return 'bg-amber-950/95 text-amber-300 border border-amber-400 shadow-amber-900/70 animate-pulse font-black';
    }
    if (table.operational_stage === 'bill_requested') {
        return 'bg-purple-950/95 text-purple-200 border border-purple-400 shadow-purple-900/60 animate-pulse';
    }
    if (table.operational_stage === 'preparing') {
        return 'bg-amber-950/95 text-amber-300 border border-amber-400 shadow-amber-900/50';
    }
    if (table.operational_stage === 'served') {
        return 'bg-sky-950/95 text-sky-300 border border-sky-400 shadow-sky-900/50';
    }
    if (table.operational_stage === 'seated') {
        return 'bg-emerald-950/95 text-emerald-300 border border-emerald-500 shadow-emerald-900/50';
    }
    if (table.operational_stage === 'cleaning') {
        return 'bg-stone-900/95 text-stone-300 border border-stone-500 shadow-stone-900/50';
    }
    if (getTableReservation(table.id)) {
        return 'bg-amber-950/95 text-amber-300 border border-amber-500 shadow-amber-900/40';
    }
    return 'bg-slate-900/90 text-slate-300 border border-slate-700';
}

async function setTableStage(table: TableItem, newStage: string) {
    try {
        const { data } = await axios.patch(`/api/admin/tables/${table.id}/stage`, { stage: newStage });
        const updated = data.data.table;
        const target = tables.value.find(t => t.id === table.id);
        if (target) {
            Object.assign(target, updated);
        }
        if (selectedTable.value && selectedTable.value.id === table.id) {
            Object.assign(selectedTable.value, updated);
        }
        saveSuccess.value = true;
        setTimeout(() => { saveSuccess.value = false; }, 3000);
    } catch (e: any) {
        alert(e.response?.data?.message || 'تعذر تحديث مرحلة الطاولة');
    }
}

function handleWaitlistTableSeated(updatedTable: TableItem) {
    const target = tables.value.find(t => t.id === updatedTable.id);
    if (target) {
        Object.assign(target, updatedTable);
    }
}

// Live Ticker for Table Turn Times
let turnTicker: any = null;

async function loadFloorServiceCalls() {
    try {
        const { data } = await axios.get('/api/admin/service-calls');
        const calls = data.data ?? [];
        calls.forEach((c: any) => {
            const tbl = tables.value.find(t => t.id === c.table_id);
            if (tbl) {
                if (c.type === 'waiter_call') {
                    tbl.has_waiter_call = true;
                    tbl.service_call = c;
                } else if (c.type === 'bill_request') {
                    tbl.operational_stage = 'bill_requested';
                    tbl.bill_requested_at = c.created_at;
                    tbl.service_call = c;
                }
            }
        });
    } catch (_) {}
}

async function attendTableServiceCall(tbl: TableItem) {
    if (!tbl.service_call?.id) {
        tbl.has_waiter_call = false;
        return;
    }
    try {
        await axios.post(`/api/admin/service-calls/${tbl.service_call.id}/attend`);
        tbl.has_waiter_call = false;
        tbl.service_call = null;
    } catch (e: any) {
        alert(e.response?.data?.message || 'تعذر تسجيل التلبية');
    }
}

onMounted(() => {
    if (window.innerWidth < 768) {
        displayMode.value = 'grid';
    }
    loadFloorServiceCalls();

    if (props.restaurant?.id && (window as any).Echo) {
        (window as any).Echo.private(`restaurant.${props.restaurant.id}.orders`)
            .listen('.table.service_called', (payload: any) => {
                const sc = payload.service_call;
                const tbl = tables.value.find(t => t.id === sc.table_id);
                if (tbl) {
                    if (sc.type === 'waiter_call') {
                        tbl.has_waiter_call = true;
                        tbl.service_call = sc;
                    } else if (sc.type === 'bill_request') {
                        tbl.operational_stage = 'bill_requested';
                        tbl.bill_requested_at = sc.created_at;
                        tbl.service_call = sc;
                    }
                }
                soundAlerts.playWaiterCallAlert(sc.type === 'bill_request' ? 'bill' : 'waiter');
            })
            .listen('.table.service_attended', (payload: any) => {
                const tbl = tables.value.find(t => t.id === payload.table_id);
                if (tbl) {
                    tbl.has_waiter_call = false;
                    tbl.service_call = null;
                }
            });
    }

    turnTicker = setInterval(() => {
        tables.value.forEach(t => {
            if (t.session_started_at) {
                const start = new Date(t.session_started_at).getTime();
                t.turn_minutes = Math.max(0, Math.floor((Date.now() - start) / 60000));
            }
            if (t.bill_requested_at) {
                const billStart = new Date(t.bill_requested_at).getTime();
                t.bill_wait_minutes = Math.max(0, Math.floor((Date.now() - billStart) / 60000));
            }
        });
    }, 30000);
});

onUnmounted(() => {
    if (turnTicker) clearInterval(turnTicker);

    if (props.restaurant?.id && (window as any).Echo) {
        (window as any).Echo.leave(`restaurant.${props.restaurant.id}.orders`);
    }
});
</script>

<template>
    <Head title="مخطط الصالة الذكي والتفاعلي - إدارة المطعم" />

    <AuthenticatedLayout>
        <div class="py-6 sm:py-8" dir="rtl">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Navigation -->
                <AdminNav />

                <!-- Manager Toast if VIP link copied before connecting restaurant WhatsApp -->
                <div
                    v-if="vipWhatsappWarningToast"
                    class="mt-4 rounded-2xl border border-amber-500/40 bg-amber-50 dark:bg-amber-950/60 px-4 py-3 text-amber-950 dark:text-amber-100 text-xs sm:text-sm font-bold flex flex-col sm:flex-row sm:items-center justify-between gap-2 shadow-sm"
                >
                    <span>⚠️ تم نسخ رابط حجز VIP، لكن مطعم «{{ restaurant.name }}» غير مربوط برقم واتساب! يجب ربط المطعم برقم واتساب أولاً قبل مشاركة الرابط حتى تصل رموز التحقق (OTP) للزبائن.</span>
                    <Link href="/admin/customers?connect_whatsapp=1" class="px-3 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-stone-950 text-xs font-black whitespace-nowrap shrink-0">
                        ربط الواتساب الآن ←
                    </Link>
                </div>

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
                                href="https://wa.me/963947064593?text=أرغب%20في%20ترقية%20مطعمي%20لتفعيل%20ميزة%20مخطط%20الصالة%20التفاعلي%20وربط%20الـ%20IoT"
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
                    <!-- Quick KPI Summary & View Switcher Strip -->
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-3 shadow-xs flex flex-wrap items-center justify-between gap-3">
                        <!-- Clickable Status Filter Counters -->
                        <div class="flex flex-wrap items-center gap-2">
                            <button
                                type="button"
                                @click="stageFilter = 'all'"
                                class="px-3 py-1.5 rounded-xl text-xs font-extrabold border transition flex items-center gap-1.5"
                                :class="stageFilter === 'all'
                                    ? 'bg-slate-900 text-white border-slate-900 dark:bg-amber-500 dark:text-stone-950 dark:border-amber-500 shadow-xs'
                                    : 'bg-slate-50 dark:bg-slate-800/70 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700 hover:bg-slate-100'"
                            >
                                <span>الكل</span>
                                <span class="font-mono opacity-80">({{ floorStats.total }})</span>
                            </button>
                            <button
                                type="button"
                                @click="stageFilter = 'available'"
                                class="px-3 py-1.5 rounded-xl text-xs font-extrabold border transition flex items-center gap-1.5"
                                :class="stageFilter === 'available'
                                    ? 'bg-emerald-600 text-white border-emerald-600 shadow-xs'
                                    : 'bg-emerald-50 dark:bg-emerald-950/30 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800/60 hover:bg-emerald-100'"
                            >
                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                <span>شاغرة ({{ floorStats.available }})</span>
                            </button>
                            <button
                                type="button"
                                @click="stageFilter = 'busy'"
                                class="px-3 py-1.5 rounded-xl text-xs font-extrabold border transition flex items-center gap-1.5"
                                :class="stageFilter === 'busy'
                                    ? 'bg-amber-600 text-white border-amber-600 shadow-xs'
                                    : 'bg-amber-50 dark:bg-amber-950/30 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800/60 hover:bg-amber-100'"
                            >
                                <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                                <span>مشغولة ({{ floorStats.busy }})</span>
                            </button>
                            <button
                                v-if="floorStats.alerts > 0 || stageFilter === 'alerts'"
                                type="button"
                                @click="stageFilter = 'alerts'"
                                class="px-3 py-1.5 rounded-xl text-xs font-black border transition flex items-center gap-1.5 animate-pulse"
                                :class="stageFilter === 'alerts'
                                    ? 'bg-rose-600 text-white border-rose-600 shadow-xs'
                                    : 'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border-rose-300 dark:border-rose-700'"
                            >
                                <span>🔔</span>
                                <span>نداءات وحساب ({{ floorStats.alerts }})</span>
                            </button>
                        </div>

                        <!-- Clear Display Switcher (Map vs Clean Grid Cards) -->
                        <div class="flex items-center gap-2 ms-auto">
                            <div class="flex items-center bg-slate-100 dark:bg-slate-800 p-1 rounded-xl border border-slate-200 dark:border-slate-700">
                                <button
                                    type="button"
                                    @click="displayMode = 'canvas'"
                                    class="px-3 py-1.5 rounded-lg text-xs font-extrabold transition flex items-center gap-1.5"
                                    :class="displayMode === 'canvas'
                                        ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-xs'
                                        : 'text-slate-500 hover:text-slate-900 dark:hover:text-white'"
                                >
                                    <span>🗺️</span>
                                    <span>المخطط المرئي</span>
                                </button>
                                <button
                                    type="button"
                                    @click="displayMode = 'grid'"
                                    class="px-3 py-1.5 rounded-lg text-xs font-extrabold transition flex items-center gap-1.5"
                                    :class="displayMode === 'grid'
                                        ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-xs'
                                        : 'text-slate-500 hover:text-slate-900 dark:hover:text-white'"
                                >
                                    <span>⊞</span>
                                    <span>بطاقات واضحة</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Control Toolbar -->
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-3.5 shadow-xs flex flex-wrap items-center justify-between gap-3">
                        <!-- Mode Switcher & Zone Tabs -->
                        <div class="flex flex-wrap items-center gap-2.5">
                            <!-- Mode Switcher (Monitor vs Designer) -->
                            <div v-if="displayMode === 'canvas'" class="flex items-center bg-slate-100 dark:bg-slate-800 p-1 rounded-xl border border-slate-200 dark:border-slate-700">
                                <button
                                    type="button"
                                    @click="editorMode = 'monitor'"
                                    class="px-3 py-1.5 rounded-lg text-xs font-extrabold transition-all flex items-center gap-1.5"
                                    :class="editorMode === 'monitor'
                                        ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-sm'
                                        : 'text-slate-500 hover:text-slate-900 dark:hover:text-white'"
                                >
                                    <span>👁️</span>
                                    <span>تشغيل حي</span>
                                </button>
                                <button
                                    type="button"
                                    @click="editorMode = 'designer'"
                                    class="px-3 py-1.5 rounded-lg text-xs font-extrabold transition-all flex items-center gap-1.5"
                                    :class="editorMode === 'designer'
                                        ? 'bg-amber-500 text-stone-950 shadow-sm'
                                        : 'text-slate-500 hover:text-slate-900 dark:hover:text-white'"
                                >
                                    <span>📐</span>
                                    <span>ترتيب وتعديل</span>
                                </button>
                            </div>

                            <!-- Zone Filter Tabs -->
                            <div class="flex items-center gap-1.5 overflow-x-auto">
                                <button
                                    v-for="z in ['all', ...zones.filter(item => item !== 'all')]"
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

                        <!-- Right Actions -->
                        <div class="flex flex-wrap items-center gap-2">
                            <!-- Digital Waitlist Button (WhatsApp Powered) -->
                            <button
                                type="button"
                                @click="isWaitlistDrawerOpen = true"
                                class="relative inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-gradient-to-r from-blue-600/15 to-indigo-600/15 border border-blue-500/40 text-blue-700 dark:text-blue-300 text-xs sm:text-sm font-black transition-all hover:bg-blue-500/25 cursor-pointer shadow-xs"
                            >
                                <span>📋</span>
                                <span>طابور الانتظار</span>
                                <span v-if="activeWaitingCount > 0" class="w-5 h-5 rounded-full bg-blue-500 text-white text-[10px] flex items-center justify-center font-bold font-mono animate-pulse">
                                    {{ activeWaitingCount }}
                                </span>
                            </button>

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

                            <!-- Zoom Controls (Canvas Only) -->
                            <div v-if="displayMode === 'canvas'" class="flex items-center bg-slate-100 dark:bg-slate-800 rounded-xl px-1 py-0.5 text-xs font-mono">
                                <button @click="zoomOut" class="px-2 py-1 hover:text-amber-500 font-bold" title="تصغير">-</button>
                                <span @click="resetZoom" class="px-1.5 cursor-pointer select-none font-bold text-slate-600 dark:text-slate-300">{{ Math.round(zoomLevel * 100) }}%</span>
                                <button @click="zoomIn" class="px-2 py-1 hover:text-amber-500 font-bold" title="تكبير">+</button>
                            </div>

                            <!-- Auto-Arrange Grid (Designer Mode) -->
                            <button
                                v-if="displayMode === 'canvas' && editorMode === 'designer'"
                                type="button"
                                @click="autoArrangeTablesLayout"
                                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-amber-500/15 hover:bg-amber-500/25 border border-amber-500/40 text-amber-700 dark:text-amber-300 text-xs font-bold transition-all"
                                title="توزيع الطاولات تلقائياً بمسافات متساوية دون أي تداخل"
                            >
                                <span>✨</span>
                                <span>ترتيب تلقائي</span>
                            </button>

                            <!-- Add Table Button (Designer Mode) -->
                            <button
                                v-if="displayMode === 'canvas' && editorMode === 'designer'"
                                type="button"
                                @click="isAddModalOpen = true"
                                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-100 text-xs sm:text-sm font-bold transition-all"
                            >
                                <span>➕</span>
                                <span>طاولة</span>
                            </button>

                            <!-- Save Button (Designer Mode) -->
                            <button
                                v-if="displayMode === 'canvas' && editorMode === 'designer'"
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

                    <!-- CLEAN & SCANNABLE GRID CARDS VIEW -->
                    <div v-if="displayMode === 'grid'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                        <div
                            v-for="table in visibleTables"
                            :key="table.id"
                            class="rounded-2xl bg-white dark:bg-slate-900 border p-4 shadow-xs transition-all hover:shadow-md flex flex-col justify-between gap-4"
                            :class="table.has_waiter_call || table.operational_stage === 'bill_requested'
                                ? 'border-amber-400 dark:border-amber-500/80 ring-2 ring-amber-400/30'
                                : (table.active_session
                                    ? 'border-emerald-300 dark:border-emerald-700/60'
                                    : 'border-slate-200 dark:border-slate-800')"
                        >
                            <!-- Card Top Header -->
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <h3 class="text-base font-black text-slate-900 dark:text-white leading-snug">{{ table.name }}</h3>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                        {{ getZoneLabel(table.zone) }} • {{ formatArabicCount(table.capacity, 'مقعد', 'مقعدان', 'مقاعد', 'مقعداً', 'مقعد واحد') }}
                                    </p>
                                </div>
                                <span
                                    class="px-2.5 py-1 rounded-full text-[11px] font-black shrink-0"
                                    :class="getTableBadgeClass(table)"
                                >
                                    {{ table.has_waiter_call ? '🛎️ نداء نادل' : getTableStageLabel(table) }}
                                </span>
                            </div>

                            <!-- Card Meta (Session Duration / Guest / Order) -->
                            <div class="space-y-1.5 text-xs">
                                <div v-if="table.turn_minutes && table.turn_minutes > 0" class="flex items-center justify-between px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-950/70 border border-slate-100 dark:border-slate-800">
                                    <span class="text-slate-500 dark:text-slate-400">مدة الجلسة:</span>
                                    <span class="font-bold font-mono" :class="table.turn_minutes > 240 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400'">
                                        ⏱️ {{ formatTurnDuration(table.turn_minutes) }}
                                    </span>
                                </div>
                                <div v-if="table.active_order" class="flex items-center justify-between px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-950/70 border border-slate-100 dark:border-slate-800">
                                    <span class="text-slate-500 dark:text-slate-400">طلب نشط #{{ table.active_order.order_number }}</span>
                                    <span class="font-black font-mono text-emerald-600 dark:text-emerald-400">{{ formatMoney(table.active_order.total, restaurant.currency) }}</span>
                                </div>
                                <div v-else-if="getTableReservation(table.id)" class="px-3 py-2 rounded-xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/50 text-amber-800 dark:text-amber-300 font-semibold">
                                    👑 محجوزة: {{ getTableReservation(table.id)?.guest_name }} ({{ getTableReservation(table.id)?.time_slot }})
                                </div>
                            </div>

                            <!-- Card One-Tap Actions -->
                            <div class="flex items-center gap-2 pt-1">
                                <button
                                    v-if="table.has_waiter_call || table.operational_stage === 'bill_requested' || table.service_call"
                                    type="button"
                                    @click="attendTableServiceCall(table)"
                                    class="flex-1 py-2 px-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-black transition cursor-pointer"
                                >
                                    ✓ تمت التلبية
                                </button>
                                <button
                                    v-if="table.active_session"
                                    type="button"
                                    @click="closeSessionForTable(table)"
                                    class="flex-1 py-2 px-3 rounded-xl bg-rose-50 hover:bg-rose-100 dark:bg-rose-950/50 dark:hover:bg-rose-900/60 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800 text-xs font-bold transition cursor-pointer"
                                >
                                    🧹 إخلاء
                                </button>
                                <button
                                    type="button"
                                    @click="openTableDrawer(table)"
                                    class="py-2 px-3 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold transition cursor-pointer"
                                    :class="!table.active_session && !table.has_waiter_call && table.operational_stage !== 'bill_requested' ? 'flex-1' : ''"
                                >
                                    ⚙️ إدارة
                                </button>
                            </div>
                        </div>

                        <div v-if="visibleTables.length === 0" class="col-span-full text-center py-12 rounded-2xl border border-dashed border-slate-300 dark:border-slate-700 text-slate-500 dark:text-slate-400 text-sm font-bold">
                            لا توجد طاولات مطابقة للتصفية الحالية.
                        </div>
                    </div>

                    <!-- Master Architectural CAD Blueprint Viewport -->
                    <div v-show="displayMode === 'canvas'" class="relative w-full rounded-3xl bg-[#070b14] border-2 border-slate-800/90 shadow-2xl overflow-auto min-h-[660px] select-none">
                        <!-- CAD Grid Background -->
                        <div
                            class="absolute inset-0 pointer-events-none opacity-20"
                            style="background-size: 30px 30px; background-image: linear-gradient(to right, #1e293b 1px, transparent 1px), linear-gradient(to bottom, #1e293b 1px, transparent 1px);"
                        ></div>

                        <!-- Architectural Blueprint Frame Lines -->
                        <div class="absolute inset-3 border border-slate-800/60 pointer-events-none rounded-2xl"></div>

                        <!-- Clean Arabic Blueprint Watermark -->
                        <div class="absolute top-2.5 left-8 text-[10px] font-bold text-slate-600 pointer-events-none">
                            مخطط الصالة التفاعلي • تدلّل
                        </div>

                        <!-- Canvas Scalable Container -->
                        <div
                            id="cad-canvas"
                            class="relative w-[1140px] h-[640px] origin-top-right transition-transform duration-100 mx-auto"
                            :style="{ transform: `scale(${zoomLevel})` }"
                            @click="selectedTable = null; activeTableId = null"
                        >
                            <!-- ================= ARCHITECTURAL WALLS & STRUCTURE (Localized Clean Arabic) ================= -->
                            <template v-if="showArchitecturalElements">
                                <!-- Outer Perimeter Double Wall -->
                                <div class="absolute top-6 left-6 right-6 bottom-6 border border-slate-800 rounded-2xl pointer-events-none"></div>

                                <!-- Left Main Entrance -->
                                <div class="absolute top-1/2 left-4 -translate-y-1/2 w-7 h-24 bg-[#070b14] border-y-2 border-slate-600 pointer-events-none flex flex-col items-center justify-center z-10">
                                    <div class="w-1.5 h-10 bg-emerald-500/80 rounded-full shadow-[0_0_10px_rgba(16,185,129,0.8)]"></div>
                                    <span class="text-[9px] font-bold text-emerald-400 rotate-90 whitespace-nowrap mt-2">المدخل</span>
                                </div>

                                <!-- Host / Reception Counter Desk -->
                                <div class="absolute bottom-7 left-10 px-3 py-1 bg-stone-900/90 border border-stone-700/80 rounded-lg pointer-events-none flex items-center gap-1.5 shadow-sm">
                                    <span class="text-[10px] font-bold text-amber-400/90">🛎️ الاستقبال</span>
                                </div>

                                <!-- Bottom Patio / Terrace Exit -->
                                <div class="absolute bottom-4 left-1/2 -translate-x-1/2 w-36 h-5 bg-[#070b14] border-x-2 border-cyan-500/40 pointer-events-none flex items-center justify-center z-10">
                                    <span class="text-[9px] font-bold text-cyan-400/80">مخرج التراس</span>
                                </div>

                                <!-- Subtle Main Hall Watermark -->
                                <div class="absolute top-1/2 left-28 right-[270px] -translate-y-1/2 flex items-center justify-center pointer-events-none">
                                    <span class="text-sm font-black tracking-wide text-slate-800/70">الصالة الرئيسية</span>
                                </div>

                                <!-- Compact VIP Wing Frame (No Cluttered Placeholder Sofas) -->
                                <div class="absolute top-8 right-8 bottom-8 w-[225px] border border-dashed border-amber-500/45 rounded-2xl bg-amber-950/[0.06] pointer-events-none p-3.5 flex flex-col justify-start">
                                    <div class="inline-flex items-center gap-1.5 bg-amber-950/70 border border-amber-500/35 px-3 py-1 rounded-lg self-start">
                                        <span class="text-xs">👑</span>
                                        <span class="text-[11px] font-black text-amber-300">جناح النخبة VIP</span>
                                    </div>
                                </div>
                            </template>

                            <!-- ================= DYNAMIC TABLES ON CANVAS ================= -->
                            <div
                                v-for="table in visibleTables"
                                :key="table.id"
                                class="absolute transition-all duration-200 group z-20 select-none"
                                :class="[
                                    editorMode === 'designer' ? 'cursor-grab active:cursor-grabbing' : 'cursor-pointer hover:scale-105',
                                    activeTableId === table.id ? 'z-30 ring-2 ring-amber-400 shadow-xl' : '',
                                    selectedTable?.id === table.id ? 'z-30 ring-2 ring-emerald-400 shadow-xl' : '',
                                    table.shape === 'circle' ? 'rounded-full' : (table.shape === 'vip' ? 'rounded-3xl' : 'rounded-2xl')
                                ]"
                                :style="{
                                    left: table.pos_x + 'px',
                                    top: Math.max(54, table.pos_y) + 'px',
                                    width: (table.shape === 'vip' ? 140 : (table.shape === 'rectangle' ? 130 : 90)) + 'px',
                                    height: (table.shape === 'rectangle' ? 80 : 90) + 'px',
                                    transform: `rotate(${table.rotation || 0}deg)`,
                                }"
                                @mousedown.stop="onMouseDown($event, table)"
                                @click.stop="selectTable(table)"
                            >
                                <!-- Settings Action Button (Hover / Selected only — prevents clutter) -->
                                <button
                                    type="button"
                                    @click.stop="openTableDrawer(table)"
                                    class="absolute -top-2 -left-2 z-40 w-6 h-6 rounded-full bg-slate-900/95 border border-slate-600 hover:border-amber-400 text-slate-300 hover:text-amber-300 flex items-center justify-center text-xs font-black shadow-md transition-opacity opacity-0 group-hover:opacity-100"
                                    :class="activeTableId === table.id || selectedTable?.id === table.id || editorMode === 'designer' ? '!opacity-100' : ''"
                                    :style="{ transform: `rotate(${-(table.rotation || 0)}deg)` }"
                                    title="خيارات وإعدادات الطاولة"
                                >
                                    ⋮
                                </button>

                                <!-- ================= CHAIRS RENDERING AROUND TABLES ================= -->
                                <!-- 4 Chairs for Circular Table -->
                                <template v-if="table.shape === 'circle'">
                                    <div class="absolute -top-3 left-1/2 -translate-x-1/2 w-5 h-2 rounded-full bg-stone-800 border border-stone-600/80 pointer-events-none"></div>
                                    <div class="absolute -bottom-3 left-1/2 -translate-x-1/2 w-5 h-2 rounded-full bg-stone-800 border border-stone-600/80 pointer-events-none"></div>
                                    <div class="absolute top-1/2 -left-3 -translate-y-1/2 w-2 h-5 rounded-full bg-stone-800 border border-stone-600/80 pointer-events-none"></div>
                                    <div class="absolute top-1/2 -right-3 -translate-y-1/2 w-2 h-5 rounded-full bg-stone-800 border border-stone-600/80 pointer-events-none"></div>
                                </template>

                                <!-- 6 Chairs for Rectangular Banquet Table -->
                                <template v-else-if="table.shape === 'rectangle'">
                                    <div class="absolute -top-3 left-3 w-5 h-2 rounded-full bg-stone-800 border border-stone-600/80 pointer-events-none"></div>
                                    <div class="absolute -top-3 left-1/2 -translate-x-1/2 w-5 h-2 rounded-full bg-stone-800 border border-stone-600/80 pointer-events-none"></div>
                                    <div class="absolute -top-3 right-3 w-5 h-2 rounded-full bg-stone-800 border border-stone-600/80 pointer-events-none"></div>
                                    <div class="absolute -bottom-3 left-3 w-5 h-2 rounded-full bg-stone-800 border border-stone-600/80 pointer-events-none"></div>
                                    <div class="absolute -bottom-3 left-1/2 -translate-x-1/2 w-5 h-2 rounded-full bg-stone-800 border border-stone-600/80 pointer-events-none"></div>
                                    <div class="absolute -bottom-3 right-3 w-5 h-2 rounded-full bg-stone-800 border border-stone-600/80 pointer-events-none"></div>
                                </template>

                                <!-- ================= TABLE CORE & NEON HALO RING ================= -->
                                <div
                                    class="w-full h-full flex flex-col items-center justify-center text-center p-2 transition-all duration-300 relative shadow-2xl"
                                    :class="[
                                        table.shape === 'circle' ? 'rounded-full' : (table.shape === 'vip' ? 'rounded-3xl' : 'rounded-2xl'),
                                        getTableRingClass(table)
                                    ]"
                                >
                                    <!-- Neon Breathing Pulse for Urgent Calls Only -->
                                    <div
                                        v-if="table.has_waiter_call"
                                        class="absolute inset-0 rounded-full animate-ping opacity-45 bg-amber-400 pointer-events-none"
                                    ></div>
                                    <div
                                        v-else-if="table.operational_stage === 'bill_requested'"
                                        class="absolute inset-0 rounded-full animate-ping opacity-30 bg-purple-400 pointer-events-none"
                                    ></div>

                                    <!-- Top Compact Name Pill (Truncated to prevent neighbor collisions) -->
                                    <div
                                        class="absolute -top-7 max-w-[126px] truncate px-2.5 py-0.5 rounded-full text-[10px] font-black shadow-md pointer-events-none z-30 bg-slate-900/95 text-slate-100 border border-slate-700/90"
                                        :style="{ transform: `rotate(${-(table.rotation || 0)}deg)` }"
                                        :title="table.name"
                                    >
                                        {{ table.name }}
                                    </div>

                                    <!-- Table Number Inside -->
                                    <span
                                        class="font-black text-white drop-shadow-md tracking-tight leading-none text-center px-1"
                                        :class="formatTableBadgeCode(table.name).length > 5 ? 'text-[11px]' : 'text-sm'"
                                    >
                                        {{ formatTableBadgeCode(table.name) }}
                                    </span>

                                    <!-- Seat Capacity (Arabic) & Humanized Duration -->
                                    <div class="flex items-center gap-1 mt-1">
                                        <span class="text-[9px] text-slate-300 font-bold">
                                            {{ formatArabicCount(table.capacity, 'مقعد', 'مقعدان', 'مقاعد', 'مقعداً', 'مقعد واحد') }}
                                        </span>
                                    </div>

                                    <span
                                        v-if="table.turn_minutes && table.turn_minutes > 0"
                                        class="text-[9px] font-mono px-1.5 py-0.2 rounded bg-black/55 mt-0.5"
                                        :class="table.turn_minutes > 240 ? 'text-amber-300 font-black' : 'text-emerald-300'"
                                    >
                                        ⏱️ {{ formatTurnDuration(table.turn_minutes) }}
                                    </span>

                                    <!-- Bottom Compact Stage Badge (No Horizontal Overflow) -->
                                    <div
                                        class="absolute -bottom-7 whitespace-nowrap px-2 py-0.5 rounded-full text-[9px] font-black shadow-md pointer-events-none z-30"
                                        :style="{ transform: `rotate(${-(table.rotation || 0)}deg)` }"
                                        :class="getTableBadgeClass(table)"
                                    >
                                        {{ table.has_waiter_call ? '🛎️ نداء نادل' : getTableStageLabel(table) }}
                                    </div>

                                    <!-- IoT Connected ESP32 Cyan Dot -->
                                    <span
                                        v-if="table.iot_device_id"
                                        class="absolute -bottom-1 -right-1 w-3.5 h-3.5 rounded-full bg-cyan-400 ring-2 ring-slate-950 shadow-[0_0_8px_rgba(6,182,212,0.9)]"
                                        title="متصل بشريحة ESP32 الذكية"
                                    ></span>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Info HUD Bar (When Table Selected in Live Monitor Mode) -->
                        <transition
                            enter-active-class="transition duration-200 ease-out"
                            enter-from-class="opacity-0 translate-y-4"
                            enter-to-class="opacity-100 translate-y-0"
                            leave-active-class="transition duration-150 ease-in"
                            leave-from-class="opacity-100 translate-y-0"
                            leave-to-class="opacity-0 translate-y-4"
                        >
                            <div
                                v-if="editorMode === 'monitor' && activeTable && !selectedTable"
                                class="absolute bottom-6 left-1/2 -translate-x-1/2 z-40 bg-slate-900/95 backdrop-blur-md border border-slate-700/80 shadow-2xl rounded-2xl p-4 flex flex-wrap items-center gap-4 text-white min-w-[340px] max-w-xl animate-fade-in"
                                dir="rtl"
                            >
                                <!-- Table Info & Badge -->
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-slate-800 border border-slate-700 flex items-center justify-center font-black text-amber-400 text-base shadow-inner">
                                        {{ activeTable.name.replace(/[^0-9]/g, '') ? '#' + activeTable.name.replace(/[^0-9]/g, '') : activeTable.name }}
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-sm text-white flex items-center gap-2">
                                            <span>{{ activeTable.name }}</span>
                                            <span class="text-[10px] px-2 py-0.5 rounded-full font-mono" :class="getTableBadgeClass(activeTable)">
                                                {{ getTableStageLabel(activeTable) }}
                                            </span>
                                            <span v-if="activeTable.turn_minutes && activeTable.turn_minutes > 0" class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-800" :class="activeTable.turn_minutes > 240 ? 'text-amber-300 font-bold' : 'text-emerald-400'">
                                                ⏱️ {{ formatTurnDuration(activeTable.turn_minutes) }}
                                            </span>
                                        </h4>
                                        <p class="text-[11px] text-slate-400 mt-0.5 flex items-center gap-2">
                                            <span>{{ getZoneLabel(activeTable.zone) }} • {{ formatArabicCount(activeTable.capacity, 'مقعد', 'مقعدان', 'مقاعد', 'مقعداً', 'مقعد واحد') }}</span>
                                            <span v-if="activeTable.current_guest_name" class="text-amber-300 font-bold">• الضيف: {{ activeTable.current_guest_name }}</span>
                                        </p>
                                    </div>
                                </div>

                                <!-- Session or Reservation or Order Summary -->
                                <div v-if="activeTable.active_order" class="text-xs bg-slate-950/80 border border-slate-800 px-3 py-1.5 rounded-xl text-slate-200 flex items-center gap-2">
                                    <span class="text-amber-400 font-bold font-mono">#{{ activeTable.active_order.order_number }}</span>
                                    <span>{{ formatArabicCount(activeTable.active_order.items_count, 'صنف', 'صنفان', 'أصناف', 'صنفاً', 'صنف واحد') }}</span>
                                    <span class="font-mono font-black text-emerald-400">{{ formatMoney(activeTable.active_order.total, restaurant.currency) }}</span>
                                </div>
                                <div v-else-if="activeTable.active_session" class="text-xs bg-emerald-950/60 border border-emerald-800/60 px-3 py-1.5 rounded-xl text-emerald-300">
                                    <span>جلسة نشطة</span>
                                </div>
                                <div v-else-if="getTableReservation(activeTable.id)" class="text-xs bg-amber-950/60 border border-amber-800/60 px-3 py-1.5 rounded-xl text-amber-300">
                                    <span>حجز: {{ getTableReservation(activeTable.id)?.guest_name }} ({{ getTableReservation(activeTable.id)?.time_slot }})</span>
                                </div>

                                <!-- Toast POS 1-Click Stage Switcher -->
                                <div class="flex items-center gap-1 bg-slate-950/90 p-1 rounded-xl border border-slate-800 text-[11px]">
                                    <button
                                        v-for="stg in [
                                            { key: 'available', label: 'شاغرة', icon: '🟢' },
                                            { key: 'seated', label: 'جلوس', icon: '🪑' },
                                            { key: 'preparing', label: 'تحضير', icon: '🍳' },
                                            { key: 'served', label: 'تقديم', icon: '🍽️' },
                                            { key: 'bill_requested', label: 'حساب', icon: '🧾' },
                                            { key: 'cleaning', label: 'تنظيف', icon: '🧹' },
                                        ]"
                                        :key="stg.key"
                                        type="button"
                                        @click="setTableStage(activeTable, stg.key)"
                                        class="px-2 py-1 rounded-lg font-bold transition flex items-center gap-1 cursor-pointer"
                                        :class="activeTable.operational_stage === stg.key ? 'bg-amber-500 text-slate-950 shadow-xs' : 'text-slate-400 hover:text-white hover:bg-slate-800'"
                                        :title="stg.label"
                                    >
                                        <span>{{ stg.icon }}</span>
                                        <span class="hidden md:inline">{{ stg.label }}</span>
                                    </button>
                                </div>

                                <!-- Actions -->
                                <div class="flex items-center gap-2 ms-auto">
                                    <button
                                        v-if="activeTable.has_waiter_call || activeTable.operational_stage === 'bill_requested' || activeTable.service_call"
                                        type="button"
                                        @click="attendTableServiceCall(activeTable)"
                                        class="px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-black transition shadow-xs flex items-center gap-1 cursor-pointer animate-pulse"
                                        title="تسجيل تلبية نداء الزبون"
                                    >
                                        <span>✓ تمت التلبية</span>
                                    </button>
                                    <button
                                        v-if="activeTable.active_session"
                                        type="button"
                                        @click="closeSessionForTable(activeTable)"
                                        class="px-3 py-1.5 rounded-xl bg-rose-600/90 hover:bg-rose-500 text-white text-xs font-bold transition shadow-xs flex items-center gap-1 cursor-pointer"
                                        title="إنهاء الجلسة وإخلاء الطاولة"
                                    >
                                        <span>🧹 إخلاء</span>
                                    </button>
                                    <button
                                        type="button"
                                        @click="openTableDrawer(activeTable)"
                                        class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold transition shadow-xs flex items-center gap-1 cursor-pointer"
                                    >
                                        <span>⚙️</span>
                                        <span>إعدادات</span>
                                    </button>
                                    <button
                                        type="button"
                                        @click="activeTableId = null"
                                        class="p-1.5 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 transition cursor-pointer"
                                        title="إغلاق"
                                    >
                                        ✕
                                    </button>
                                </div>
                            </div>
                        </transition>
                    </div>
                </div>

                <TableSettingsDrawer
                    v-if="selectedTable"
                    :table="selectedTable"
                    :reservation="getTableReservation(selectedTable.id)"
                    @close="selectedTable = null"
                    @set-stage="setTableStage"
                    @rotate="rotateSelectedTable"
                    @duplicate="duplicateSelectedTable"
                    @attend-call="attendTableServiceCall"
                    @close-session="closeSessionForTable"
                    @seat-reservation="markReservationSeated"
                    @save="updateSelectedTable"
                    @delete="deleteSelectedTable"
                />

                <ReservationsDrawer
                    v-if="isReservationsDrawerOpen"
                    :reservations="reservations"
                    :restaurant-name="restaurant.name"
                    :is-whatsapp-connected="isRestaurantWhatsappConnected"
                    :is-copied-link="isCopiedLink"
                    @close="isReservationsDrawerOpen = false"
                    @seat-reservation="markReservationSeated"
                    @copy-link="copyPublicVipBookingLink"
                />

                <WaitlistDrawer
                    v-if="isWaitlistDrawerOpen"
                    :waitlist="waitlist"
                    :tables="tables"
                    :restaurant-slug="restaurant.slug"
                    @close="isWaitlistDrawerOpen = false"
                    @update:waitlist="waitlist = $event"
                    @table-seated="handleWaitlistTableSeated"
                    @saved-toast="triggerSavedToast"
                />

                <AddTableModal
                    v-if="isAddModalOpen"
                    @close="isAddModalOpen = false"
                    @created="handleTableCreated"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
