<script setup lang="ts">
import AdminNav from '@/Components/Admin/AdminNav.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import { soundAlerts } from '@/utils/soundAlerts';

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
    operational_stage?: 'available' | 'seated' | 'preparing' | 'served' | 'bill_requested' | 'cleaning';
    stage_updated_at?: string;
    session_started_at?: string;
    bill_requested_at?: string;
    current_guest_name?: string;
    turn_minutes?: number;
    stage_minutes?: number;
    bill_wait_minutes?: number;
    has_waiter_call?: boolean;
    service_call?: any;
    active_session?: any;
    active_order?: {
        id: number;
        order_number: string;
        status: string;
        total: number;
        items_count: number;
        created_at?: string;
    } | null;
}

interface WaitlistItem {
    id: number;
    uuid: string;
    guest_name: string;
    guest_phone: string;
    party_size: number;
    seating_preference: string;
    status: 'waiting' | 'notified' | 'seated' | 'cancelled' | 'no_show';
    queue_number: number;
    estimated_wait_minutes: number;
    waiting_minutes: number;
    notified_at?: string;
    assigned_table?: { id: number; name: string } | null;
    notes?: string;
    tracking_url?: string;
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
    restaurant: { id: number; name: string; slug: string; currency?: string; features?: any; whatsapp_status?: string };
    todayReservations?: ReservationItem[];
    todayWaitlist?: WaitlistItem[];
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
    operational_stage: t.operational_stage || (t.active_session ? 'seated' : 'available'),
    turn_minutes: Number(t.turn_minutes) || 0,
    stage_minutes: Number(t.stage_minutes) || 0,
    bill_wait_minutes: t.bill_wait_minutes ?? null,
})));

const reservations = ref<ReservationItem[]>(props.todayReservations || []);
const waitlist = ref<WaitlistItem[]>(props.todayWaitlist || []);
const activeZone = ref<string>('all');
const selectedTable = ref<TableItem | null>(null);
const activeTableId = ref<number | null>(null);
const activeTable = computed(() => tables.value.find(t => t.id === activeTableId.value));
const editorMode = ref<'monitor' | 'designer'>('monitor'); // monitor mode prevents accidental drag
const zoomLevel = ref<number>(1);
const isSaving = ref(false);
const saveSuccess = ref(false);
const isAddModalOpen = ref(false);
const isReservationsDrawerOpen = ref(false);
const isWaitlistDrawerOpen = ref(false);
const isAddWaitlistModalOpen = ref(false);
const isSeatModalOpen = ref(false);
const seatTargetEntry = ref<WaitlistItem | null>(null);
const selectedTableIdForSeating = ref<number | null>(null);
const isSubmittingWaitlist = ref(false);
const isCopiedLink = ref(false);
const isCopiedWaitlistLink = ref(false);
const showArchitecturalElements = ref(true);

const newWaitlistForm = ref({
    guest_name: '',
    guest_phone: '',
    party_size: 2,
    seating_preference: 'any',
    estimated_wait_minutes: 15,
    notes: '',
});

const activeWaitingCount = computed(() => {
    return waitlist.value.filter(e => e.status === 'waiting' || e.status === 'notified').length;
});

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
async function closeSessionForTable(targetTable?: TableItem) {
    const table = targetTable || selectedTable.value;
    if (!table) return;
    if (!confirm(`هل أنت متأكد من إنهاء جلسة ${table.name} وإخلائها للزبائن القادمين؟`)) return;
    try {
        await axios.post(`/api/admin/tables/${table.id}/close-session`);
        table.active_session = null;
        if (selectedTable.value && selectedTable.value.id === table.id) {
            selectedTable.value.active_session = null;
        }
        const t = tables.value.find(item => item.id === table.id);
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

// ================= TOAST POS TABLE STAGES & TURN TIME =================
const stageLabels: Record<string, string> = {
    available: 'شاغرة',
    seated: 'تم الجلوس',
    preparing: 'تحضير بالمطبخ',
    served: 'تم التقديم',
    bill_requested: 'طلب الحساب',
    cleaning: 'بانتظار التنظيف',
};

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

// ================= SMART DIGITAL WAITLIST ACTIONS =================
async function refreshWaitlist() {
    try {
        const { data } = await axios.get('/api/admin/waitlist');
        waitlist.value = data.data.entries;
    } catch (e) {
        console.error('Waitlist fetch error:', e);
    }
}

async function addWaitlistEntry() {
    if (!newWaitlistForm.value.guest_name.trim() || !newWaitlistForm.value.guest_phone.trim()) return;
    isSubmittingWaitlist.value = true;
    try {
        const { data } = await axios.post('/api/admin/waitlist', newWaitlistForm.value);
        waitlist.value.unshift(data.data.entry);
        await refreshWaitlist();
        isAddWaitlistModalOpen.value = false;
        newWaitlistForm.value = {
            guest_name: '',
            guest_phone: '',
            party_size: 2,
            seating_preference: 'any',
            estimated_wait_minutes: 15,
            notes: '',
        };
        saveSuccess.value = true;
        setTimeout(() => { saveSuccess.value = false; }, 3000);
    } catch (e: any) {
        alert(e.response?.data?.message || 'تعذر إضافة الزبون للانتظار');
    } finally {
        isSubmittingWaitlist.value = false;
    }
}

async function notifyWaitlistGuest(entry: WaitlistItem) {
    try {
        await axios.patch(`/api/admin/waitlist/${entry.id}/notify`);
        entry.status = 'notified';
        entry.notified_at = new Date().toISOString();
        saveSuccess.value = true;
        setTimeout(() => { saveSuccess.value = false; }, 3000);
    } catch (e: any) {
        alert(e.response?.data?.message || 'تعذر إرسال إشعار الواتساب للزبون');
    }
}

function openSeatModal(entry: WaitlistItem) {
    seatTargetEntry.value = entry;
    const available = tables.value.find(t => (t.operational_stage === 'available' || !t.active_session) && t.capacity >= entry.party_size);
    selectedTableIdForSeating.value = available ? available.id : (tables.value[0]?.id || null);
    isSeatModalOpen.value = true;
}

async function confirmSeating() {
    if (!seatTargetEntry.value || !selectedTableIdForSeating.value) return;
    try {
        const { data } = await axios.patch(`/api/admin/waitlist/${seatTargetEntry.value.id}/seat`, {
            table_id: selectedTableIdForSeating.value,
        });
        seatTargetEntry.value.status = 'seated';
        const updatedTable = data.data.table;
        const target = tables.value.find(t => t.id === selectedTableIdForSeating.value);
        if (target) {
            Object.assign(target, updatedTable);
        }
        isSeatModalOpen.value = false;
        seatTargetEntry.value = null;
        await refreshWaitlist();
        saveSuccess.value = true;
        setTimeout(() => { saveSuccess.value = false; }, 3000);
    } catch (e: any) {
        alert(e.response?.data?.message || 'تعذر إجلاس الزبون على الطاولة');
    }
}

async function cancelWaitlistEntry(entry: WaitlistItem) {
    if (!confirm(`هل أنت متأكد من إلغاء دور ${entry.guest_name}؟`)) return;
    try {
        await axios.patch(`/api/admin/waitlist/${entry.id}/status`, { status: 'cancelled' });
        entry.status = 'cancelled';
        await refreshWaitlist();
    } catch (e: any) {
        alert(e.response?.data?.message || 'تعذر إلغاء الدور');
    }
}

function copyWaitlistJoinLink() {
    const url = `${window.location.origin}/r/${props.restaurant.slug}/waitlist`;
    navigator.clipboard.writeText(url).then(() => {
        isCopiedWaitlistLink.value = true;
        setTimeout(() => { isCopiedWaitlistLink.value = false; }, 3000);
    });
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

                        <!-- Status Indicators Legend (Toast POS Operational Stages) -->
                        <div class="hidden xl:flex items-center gap-3 text-[11px] font-semibold">
                            <span class="inline-flex items-center gap-1 text-slate-400">
                                <span class="w-2 h-2 rounded-full bg-slate-500"></span>
                                <span>شاغرة</span>
                            </span>
                            <span class="inline-flex items-center gap-1 text-emerald-400">
                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                <span>جلوس</span>
                            </span>
                            <span class="inline-flex items-center gap-1 text-amber-400">
                                <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                                <span>تحضير بالمطبخ</span>
                            </span>
                            <span class="inline-flex items-center gap-1 text-sky-400">
                                <span class="w-2 h-2 rounded-full bg-sky-500"></span>
                                <span>تم التقديم</span>
                            </span>
                            <span class="inline-flex items-center gap-1 text-purple-400">
                                <span class="w-2 h-2 rounded-full bg-purple-500 animate-pulse"></span>
                                <span>طلب الحساب</span>
                            </span>
                            <span class="inline-flex items-center gap-1 text-stone-400">
                                <span class="w-2 h-2 rounded-full bg-stone-500"></span>
                                <span>تنظيف</span>
                            </span>
                        </div>

                        <!-- Right Actions -->
                        <div class="flex items-center gap-2">
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
                            @click="selectedTable = null; activeTableId = null"
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
                                    activeTableId === table.id ? 'z-30 ring-2 ring-amber-400 shadow-xl' : '',
                                    selectedTable?.id === table.id ? 'z-30 ring-2 ring-emerald-400 shadow-xl' : '',
                                    table.shape === 'circle' ? 'rounded-full' : (table.shape === 'vip' ? 'rounded-3xl' : 'rounded-2xl')
                                ]"
                                :style="{
                                    left: table.pos_x + 'px',
                                    top: table.pos_y + 'px',
                                    width: (table.shape === 'vip' ? 140 : (table.shape === 'rectangle' ? 130 : 90)) + 'px',
                                    height: (table.shape === 'rectangle' ? 80 : 90) + 'px',
                                    transform: `rotate(${table.rotation || 0}deg)`,
                                }"
                                @mousedown.stop="onMouseDown($event, table)"
                                @click.stop="selectTable(table)"
                            >
                                <!-- 3-Dots Action Button to Open Drawer / Settings -->
                                <button
                                    type="button"
                                    @click.stop="openTableDrawer(table)"
                                    class="absolute -top-2.5 -right-2.5 z-40 w-7 h-7 rounded-full bg-slate-900/95 border-2 border-slate-700 hover:border-amber-400 text-slate-300 hover:text-amber-300 flex items-center justify-center text-xs font-black shadow-xl hover:scale-115 active:scale-95 transition-all group-hover:border-slate-500"
                                    :style="{ transform: `rotate(${-(table.rotation || 0)}deg)` }"
                                    title="خيارات وإعدادات الطاولة"
                                >
                                    ⋮
                                </button>

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
                                        getTableRingClass(table)
                                    ]"
                                >
                                    <!-- Neon Breathing Pulse for Active Session or Urgent Stages -->
                                    <div
                                        v-if="table.has_waiter_call"
                                        class="absolute inset-0 rounded-full animate-ping opacity-45 bg-amber-400 pointer-events-none"
                                    ></div>
                                    <div
                                        v-else-if="table.operational_stage === 'bill_requested'"
                                        class="absolute inset-0 rounded-full animate-ping opacity-30 bg-purple-400 pointer-events-none"
                                    ></div>
                                    <div
                                        v-else-if="table.active_session || table.operational_stage === 'seated'"
                                        class="absolute inset-0 rounded-full animate-ping opacity-20 bg-emerald-400 pointer-events-none"
                                    ></div>

                                    <!-- Table Label Badge (Toast POS Stage & Timer Tooltip) -->
                                    <div
                                        class="absolute -top-7 whitespace-nowrap px-2.5 py-0.5 rounded-md text-[10px] font-black flex items-center gap-1.5 shadow-xl pointer-events-none transition-all z-30"
                                        :style="{ transform: `rotate(${-(table.rotation || 0)}deg)` }"
                                        :class="getTableBadgeClass(table)"
                                    >
                                        <span>{{ table.name }}</span>
                                        <span class="opacity-80">• {{ getTableStageLabel(table) }}</span>
                                        <span
                                            v-if="table.has_waiter_call"
                                            class="font-mono bg-amber-400 text-stone-950 px-1 py-0.2 rounded font-black animate-pulse"
                                        >
                                            🛎️ نداء نادل
                                        </span>
                                        <span
                                            v-if="table.turn_minutes && table.turn_minutes > 0"
                                            class="font-mono bg-black/40 px-1 py-0.2 rounded"
                                            :class="table.turn_minutes > 75 ? 'text-rose-300 font-bold animate-pulse' : (table.turn_minutes > 45 ? 'text-amber-300' : 'text-emerald-300')"
                                        >
                                            ⏱️ {{ table.turn_minutes }}m
                                        </span>
                                        <span
                                            v-if="table.operational_stage === 'bill_requested' && table.bill_wait_minutes !== null"
                                            class="font-mono bg-rose-950 text-rose-200 px-1 py-0.2 rounded font-bold animate-pulse"
                                        >
                                            🧾 {{ table.bill_wait_minutes }}د
                                        </span>
                                    </div>

                                    <!-- Table Number Inside -->
                                    <span class="font-black text-sm text-white drop-shadow-md tracking-tight">
                                        {{ table.name.replace(/[^0-9]/g, '') ? '#' + table.name.replace(/[^0-9]/g, '') : table.name }}
                                    </span>

                                    <!-- Seat Capacity & Turn Time Inner Indicator -->
                                    <div class="flex items-center gap-1 mt-0.5">
                                        <span class="text-[9px] text-slate-400 font-mono">
                                            {{ table.capacity }}S
                                        </span>
                                        <span
                                            v-if="table.turn_minutes && table.turn_minutes > 0"
                                            class="text-[9px] font-mono px-1 rounded bg-black/50"
                                            :class="table.turn_minutes > 75 ? 'text-rose-400 font-black' : (table.turn_minutes > 45 ? 'text-amber-400' : 'text-emerald-400')"
                                        >
                                            {{ table.turn_minutes }}m
                                        </span>
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
                                            <span v-if="activeTable.turn_minutes && activeTable.turn_minutes > 0" class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-800" :class="activeTable.turn_minutes > 75 ? 'text-rose-400 font-bold' : (activeTable.turn_minutes > 45 ? 'text-amber-400' : 'text-emerald-400')">
                                                ⏱️ {{ activeTable.turn_minutes }} دقيقة
                                            </span>
                                        </h4>
                                        <p class="text-[11px] text-slate-400 mt-0.5 flex items-center gap-2">
                                            <span>{{ getZoneLabel(activeTable.zone) }} • {{ activeTable.capacity }} مقاعد</span>
                                            <span v-if="activeTable.current_guest_name" class="text-amber-300 font-bold">• الضيف: {{ activeTable.current_guest_name }}</span>
                                        </p>
                                    </div>
                                </div>

                                <!-- Session or Reservation or Order Summary -->
                                <div v-if="activeTable.active_order" class="text-xs bg-slate-950/80 border border-slate-800 px-3 py-1.5 rounded-xl text-slate-200 flex items-center gap-2">
                                    <span class="text-amber-400 font-bold font-mono">#{{ activeTable.active_order.order_number }}</span>
                                    <span>{{ activeTable.active_order.items_count }} أصناف</span>
                                    <span class="font-mono font-black text-emerald-400">{{ activeTable.active_order.total }} {{ restaurant.currency || 'ل.س' }}</span>
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

                            <!-- Table Operational Stage (Toast POS Stages) -->
                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">المرحلة التشغيلية للطاولة</label>
                                <div class="grid grid-cols-3 gap-1.5">
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
                                        @click="setTableStage(selectedTable, stg.key)"
                                        class="py-2 px-1 rounded-xl text-xs font-bold border transition-all cursor-pointer flex items-center justify-center gap-1"
                                        :class="selectedTable.operational_stage === stg.key ? 'bg-amber-500 text-stone-950 border-amber-400 font-black shadow-xs' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700 hover:border-slate-400'"
                                    >
                                        <span>{{ stg.icon }}</span>
                                        <span>{{ stg.label }}</span>
                                    </button>
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

                            <!-- Active Service Call Alert Card (Waiter Call / Bill Request) -->
                            <div
                                v-if="selectedTable.has_waiter_call || selectedTable.operational_stage === 'bill_requested' || selectedTable.service_call"
                                class="p-3.5 rounded-2xl border space-y-2.5 shadow-md"
                                :class="selectedTable.operational_stage === 'bill_requested'
                                    ? 'bg-purple-950/40 border-purple-500/60 text-purple-200'
                                    : 'bg-amber-950/40 border-amber-500/60 text-amber-200'"
                            >
                                <div class="flex items-center justify-between text-xs font-black">
                                    <span class="flex items-center gap-1.5">
                                        <span class="w-2.5 h-2.5 rounded-full bg-current animate-ping"></span>
                                        <span>{{ selectedTable.operational_stage === 'bill_requested' ? '🧾 طلب حساب وفاتورة' : '🛎️ استدعاء نادل مباشر' }}</span>
                                    </span>
                                    <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-black/40">بانتظار التلبية</span>
                                </div>

                                <p v-if="selectedTable.service_call?.reason || selectedTable.service_call?.notes || selectedTable.service_call?.payment_method" class="text-xs text-stone-300">
                                    {{ selectedTable.service_call?.reason || (selectedTable.service_call?.payment_method === 'card' ? '💳 دفع بالبطاقة / مدى' : (selectedTable.service_call?.payment_method === 'cash' ? '💵 دفع نقداً' : '')) || selectedTable.service_call?.notes }}
                                </p>

                                <button
                                    type="button"
                                    @click="attendTableServiceCall(selectedTable)"
                                    class="w-full py-2 px-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-black shadow-md active:scale-95 transition flex items-center justify-center gap-1.5 cursor-pointer"
                                >
                                    <span>✓ تسجيل تمت التلبية</span>
                                </button>
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

                <!-- ================= SMART DIGITAL WAITLIST DRAWER ================= -->
                <div
                    v-if="isWaitlistDrawerOpen"
                    class="fixed inset-y-0 right-0 z-50 w-full sm:w-[480px] bg-white dark:bg-slate-900 shadow-2xl border-l border-slate-200 dark:border-slate-800 p-6 flex flex-col justify-between overflow-y-auto animate-slideLeft"
                    dir="rtl"
                >
                    <div class="space-y-5">
                        <!-- Drawer Header -->
                        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-4">
                            <div class="flex items-center gap-2.5">
                                <div class="w-10 h-10 rounded-2xl bg-blue-500/20 border border-blue-500/40 text-blue-400 flex items-center justify-center text-xl shadow-xs">
                                    📋
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h3 class="text-lg font-black text-slate-900 dark:text-white">طابور الانتظار الذكي</h3>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-500/20 text-blue-400 border border-blue-500/30">
                                            {{ activeWaitingCount }} بالانتظار
                                        </span>
                                    </div>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">إشعارات الواتساب الفورية ومتابعة الأدوار</p>
                                </div>
                            </div>
                            <button
                                @click="isWaitlistDrawerOpen = false"
                                class="p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer"
                            >
                                ✕
                            </button>
                        </div>

                        <!-- Top Action Controls -->
                        <div class="grid grid-cols-2 gap-2">
                            <button
                                type="button"
                                @click="isAddWaitlistModalOpen = true"
                                class="py-2.5 px-3 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-black text-xs shadow-md shadow-blue-600/20 flex items-center justify-center gap-1.5 transition cursor-pointer"
                            >
                                <span>➕</span>
                                <span>إضافة زبون (Walk-in)</span>
                            </button>
                            <button
                                type="button"
                                @click="copyWaitlistJoinLink"
                                class="py-2.5 px-3 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-xs border border-slate-200 dark:border-slate-700 flex items-center justify-center gap-1.5 transition cursor-pointer"
                            >
                                <span>🔗</span>
                                <span>{{ isCopiedWaitlistLink ? 'تم نسخ الرابط!' : 'رابط QR للمدخل' }}</span>
                            </button>
                        </div>

                        <!-- Queue List -->
                        <div v-if="waitlist.length" class="space-y-3">
                            <div
                                v-for="entry in waitlist"
                                :key="entry.id"
                                class="p-4 rounded-2xl border transition-all"
                                :class="[
                                    entry.status === 'notified'
                                        ? 'bg-emerald-50/70 dark:bg-emerald-950/30 border-emerald-300 dark:border-emerald-700 shadow-sm'
                                        : (entry.status === 'seated'
                                            ? 'bg-slate-50 dark:bg-slate-800/30 border-slate-200 dark:border-slate-800 opacity-60'
                                            : 'bg-white dark:bg-slate-800/60 border-slate-200 dark:border-slate-700/80 shadow-xs')
                                ]"
                            >
                                <div class="flex items-start justify-between gap-2 mb-2">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="font-black text-sm text-slate-900 dark:text-white">{{ entry.guest_name }}</span>
                                            <span class="px-2 py-0.5 rounded-md text-[10px] font-mono font-black bg-blue-500/20 text-blue-600 dark:text-blue-300">
                                                #{{ entry.queue_number }}
                                            </span>
                                        </div>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 flex items-center gap-2">
                                            <span>👥 {{ entry.party_size }} أفراد</span>
                                            <span>•</span>
                                            <span dir="ltr" class="font-mono">{{ entry.guest_phone }}</span>
                                        </p>
                                    </div>

                                    <!-- Status Pill -->
                                    <div class="text-end">
                                        <span
                                            class="inline-block px-2.5 py-1 rounded-full text-[10px] font-bold font-mono"
                                            :class="[
                                                entry.status === 'notified'
                                                    ? 'bg-emerald-500 text-slate-950 font-black animate-pulse'
                                                    : (entry.status === 'seated'
                                                        ? 'bg-slate-700 text-slate-300'
                                                        : 'bg-amber-500/20 text-amber-600 dark:text-amber-400 border border-amber-500/40')
                                            ]"
                                        >
                                            {{ entry.status === 'notified' ? '🔔 تم إشعار الجاهزية' : (entry.status === 'seated' ? '✓ تم الجلوس' : '⏱️ بانتظار دور') }}
                                        </span>
                                        <span class="block text-[10px] text-slate-400 font-mono mt-0.5">
                                            ينتظر منذ {{ entry.waiting_minutes }}د
                                        </span>
                                    </div>
                                </div>

                                <div v-if="entry.notes" class="text-xs text-slate-600 dark:text-slate-300 bg-slate-50 dark:bg-slate-900/60 p-2 rounded-xl mb-3 border border-slate-100 dark:border-slate-800 italic">
                                    "{{ entry.notes }}"
                                </div>

                                <!-- Action Buttons -->
                                <div v-if="entry.status === 'waiting' || entry.status === 'notified'" class="flex items-center gap-2 pt-1 border-t border-slate-100 dark:border-slate-800">
                                    <button
                                        type="button"
                                        @click="notifyWaitlistGuest(entry)"
                                        class="flex-1 py-2 px-2.5 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1 cursor-pointer"
                                        :class="entry.status === 'notified'
                                            ? 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200'
                                            : 'bg-emerald-600 hover:bg-emerald-500 text-white shadow-xs'"
                                    >
                                        <span>🔔</span>
                                        <span>{{ entry.status === 'notified' ? 'إعادة الإشعار' : 'إشعار بالجاهزية' }}</span>
                                    </button>
                                    <button
                                        type="button"
                                        @click="openSeatModal(entry)"
                                        class="flex-1 py-2 px-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-stone-950 text-xs font-black transition flex items-center justify-center gap-1 shadow-xs cursor-pointer"
                                    >
                                        <span>🪑</span>
                                        <span>تسكين على طاولة</span>
                                    </button>
                                    <button
                                        type="button"
                                        @click="cancelWaitlistEntry(entry)"
                                        class="p-2 rounded-xl text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition cursor-pointer"
                                        title="إلغاء الدور"
                                    >
                                        ✕
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Empty State -->
                        <div v-else class="text-center py-12 px-4 bg-slate-50 dark:bg-slate-800/30 rounded-3xl border border-dashed border-slate-200 dark:border-slate-800">
                            <span class="text-4xl">🌟</span>
                            <h4 class="font-bold text-slate-800 dark:text-slate-200 text-sm mt-3">لا يوجد زبائن في الانتظار حالياً</h4>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-xs mx-auto leading-relaxed">
                                عندما تمتلئ الصالة، يمكنك إضافة الزوار الوافدين بنقرة زر أو مشاركة رمز الـ QR مع الاستقبال.
                            </p>
                        </div>
                    </div>

                    <!-- Footer Link Copy -->
                    <div class="pt-4 border-t border-slate-100 dark:border-slate-800">
                        <button
                            type="button"
                            @click="copyWaitlistJoinLink"
                            class="w-full py-3 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 font-bold text-xs transition flex items-center justify-center gap-2 cursor-pointer"
                        >
                            <span>📲</span>
                            <span>{{ isCopiedWaitlistLink ? 'تم نسخ الرابط بنجاح!' : 'نسخ رابط تسجيل الانتظار الذاتي' }}</span>
                        </button>
                    </div>
                </div>

                <!-- ================= ADD WALK-IN GUEST MODAL ================= -->
                <div
                    v-if="isAddWaitlistModalOpen"
                    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs animate-fadeIn"
                    dir="rtl"
                >
                    <div class="w-full max-w-md bg-white dark:bg-slate-900 rounded-3xl p-6 shadow-2xl border border-slate-200 dark:border-slate-800 space-y-4">
                        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                            <h3 class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                                <span>➕</span>
                                <span>إضافة زبون إلى قائمة الانتظار</span>
                            </h3>
                            <button @click="isAddWaitlistModalOpen = false" class="text-slate-400 hover:text-slate-600 cursor-pointer">✕</button>
                        </div>

                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">اسم الزبون *</label>
                                <input
                                    v-model="newWaitlistForm.guest_name"
                                    type="text"
                                    placeholder="مثال: المهندس وسيم"
                                    class="w-full px-3.5 py-2.5 rounded-xl text-sm border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-hidden"
                                />
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">رقم الواتساب (لإرسال إشعار الطاولة) *</label>
                                <input
                                    v-model="newWaitlistForm.guest_phone"
                                    type="tel"
                                    placeholder="09xxxxxxxx"
                                    dir="ltr"
                                    class="w-full px-3.5 py-2.5 rounded-xl text-sm text-right border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-hidden"
                                />
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">عدد الأفراد</label>
                                    <input
                                        v-model.number="newWaitlistForm.party_size"
                                        type="number"
                                        min="1"
                                        max="30"
                                        class="w-full px-3.5 py-2.5 rounded-xl text-sm border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-hidden"
                                    />
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">الوقت التقديري (دقيقة)</label>
                                    <input
                                        v-model.number="newWaitlistForm.estimated_wait_minutes"
                                        type="number"
                                        min="1"
                                        max="120"
                                        class="w-full px-3.5 py-2.5 rounded-xl text-sm border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-hidden"
                                    />
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">تفضيل الجلسة</label>
                                <select
                                    v-model="newWaitlistForm.seating_preference"
                                    class="w-full px-3.5 py-2.5 rounded-xl text-sm border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-hidden"
                                >
                                    <option value="any">أي طاولة متاحة أولاً</option>
                                    <option value="indoor">صالة داخلية</option>
                                    <option value="terrace">تراس خارجي</option>
                                    <option value="family">قسم عائلي</option>
                                    <option value="vip">جناح VIP</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">ملاحظات إضافية</label>
                                <input
                                    v-model="newWaitlistForm.notes"
                                    type="text"
                                    placeholder="كرسي أطفال، طاولة هادئة..."
                                    class="w-full px-3.5 py-2 rounded-xl text-xs border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-hidden"
                                />
                            </div>
                        </div>

                        <div class="flex items-center gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                            <button
                                type="button"
                                :disabled="isSubmittingWaitlist"
                                @click="addWaitlistEntry"
                                class="flex-1 py-3 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-black text-sm shadow-md transition cursor-pointer disabled:opacity-50"
                            >
                                {{ isSubmittingWaitlist ? 'جاري التسجيل...' : 'تسجيل وإرسال إشعار الواتساب' }}
                            </button>
                            <button
                                type="button"
                                @click="isAddWaitlistModalOpen = false"
                                class="px-5 py-3 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold text-sm cursor-pointer"
                            >
                                إلغاء
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ================= SEAT GUEST MODAL ================= -->
                <div
                    v-if="isSeatModalOpen && seatTargetEntry"
                    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs animate-fadeIn"
                    dir="rtl"
                >
                    <div class="w-full max-w-sm bg-white dark:bg-slate-900 rounded-3xl p-6 shadow-2xl border border-slate-200 dark:border-slate-800 space-y-4">
                        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                            <h3 class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                                <span>🪑</span>
                                <span>تسكين الزبون على طاولة</span>
                            </h3>
                            <button @click="isSeatModalOpen = false" class="text-slate-400 hover:text-slate-600 cursor-pointer">✕</button>
                        </div>

                        <div class="p-3 bg-blue-50 dark:bg-blue-950/40 rounded-xl border border-blue-200 dark:border-blue-800/60 text-xs text-blue-900 dark:text-blue-200">
                            الزبون: <span class="font-black">{{ seatTargetEntry.guest_name }}</span> ({{ seatTargetEntry.party_size }} أفراد)
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-2">اختر الطاولة لتسكين الزبون عليها:</label>
                            <div class="max-h-56 overflow-y-auto space-y-1.5 pr-1">
                                <label
                                    v-for="tbl in tables"
                                    :key="tbl.id"
                                    class="flex items-center justify-between p-3 rounded-xl border cursor-pointer transition"
                                    :class="selectedTableIdForSeating === tbl.id
                                        ? 'bg-amber-50 dark:bg-amber-950/40 border-amber-500 ring-2 ring-amber-500/30'
                                        : 'bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-700 hover:border-slate-400'"
                                >
                                    <div class="flex items-center gap-2.5">
                                        <input
                                            type="radio"
                                            :value="tbl.id"
                                            v-model="selectedTableIdForSeating"
                                            class="text-amber-500 focus:ring-amber-500"
                                        />
                                        <span class="font-bold text-sm text-slate-900 dark:text-white">{{ tbl.name }}</span>
                                        <span class="text-xs text-slate-400">({{ tbl.capacity }} مقاعد - {{ getZoneLabel(tbl.zone) }})</span>
                                    </div>
                                    <span class="text-[10px] px-2 py-0.5 rounded-full font-mono" :class="tbl.operational_stage === 'available' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-slate-700 text-slate-300'">
                                        {{ getTableStageLabel(tbl) }}
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                            <button
                                type="button"
                                @click="confirmSeating"
                                class="flex-1 py-3 rounded-xl bg-amber-500 hover:bg-amber-400 text-stone-950 font-black text-sm shadow-md transition cursor-pointer"
                            >
                                تأكيد التسكين والجلوس
                            </button>
                            <button
                                type="button"
                                @click="isSeatModalOpen = false"
                                class="px-5 py-3 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold text-sm cursor-pointer"
                            >
                                إلغاء
                            </button>
                        </div>
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
