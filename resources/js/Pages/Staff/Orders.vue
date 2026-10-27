<script setup lang="ts">
import AdminNav from '@/Components/Admin/AdminNav.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useRestaurantOrders } from '@/composables/useRealtimeOrders';
import { Head, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import axios from 'axios';
import { useToast } from '@/composables/useToast';

// ============================================================
// 🔔 نظام التنبيهات الصوتية وإشعارات المتصفح
// ============================================================

const notificationPermission = ref<NotificationPermission>('default');

// طلب صلاحية الإشعارات عند تحميل الصفحة
onMounted(async () => {
    if ('Notification' in window) {
        notificationPermission.value = Notification.permission;
    }
});

async function requestNotificationPermission() {
    if (!('Notification' in window)) return;
    const result = await Notification.requestPermission();
    notificationPermission.value = result;
}

const audioUnlocked = ref(false);
let globalAudioCtx: AudioContext | null = null;

function unlockAudio() {
    try {
        if (!globalAudioCtx) {
            globalAudioCtx = new (window.AudioContext || (window as any).webkitAudioContext)();
        }
        
        if (globalAudioCtx.state === 'suspended') {
            globalAudioCtx.resume();
        }
        
        // Play a silent short beep to fully unlock the context on iOS/Safari
        const oscillator = globalAudioCtx.createOscillator();
        const gainNode = globalAudioCtx.createGain();
        gainNode.gain.value = 0;
        oscillator.connect(gainNode);
        gainNode.connect(globalAudioCtx.destination);
        oscillator.start();
        oscillator.stop(globalAudioCtx.currentTime + 0.01);
        
        audioUnlocked.value = true;
    } catch (e) {
        console.error('Failed to unlock audio:', e);
    }
}

// توليد صوت تنبيه مزدوج باستخدام Web Audio API
function playAlertSound() {
    if (!audioUnlocked.value || !globalAudioCtx) return;

    try {
        if (globalAudioCtx.state === 'suspended') {
            globalAudioCtx.resume();
        }

        const playBeep = (startTime: number, freq: number, duration: number) => {
            if (!globalAudioCtx) return;
            const oscillator = globalAudioCtx.createOscillator();
            const gainNode = globalAudioCtx.createGain();

            oscillator.connect(gainNode);
            gainNode.connect(globalAudioCtx.destination);

            oscillator.type = 'sine';
            oscillator.frequency.setValueAtTime(freq, startTime);

            // fade in
            gainNode.gain.setValueAtTime(0, startTime);
            gainNode.gain.linearRampToValueAtTime(0.6, startTime + 0.02);
            // fade out
            gainNode.gain.linearRampToValueAtTime(0, startTime + duration - 0.02);

            oscillator.start(startTime);
            oscillator.stop(startTime + duration);
        };

        const now = globalAudioCtx.currentTime;
        playBeep(now, 880, 0.18);        // نغمة أولى
        playBeep(now + 0.22, 1100, 0.18); // نغمة ثانية أعلى
        playBeep(now + 0.44, 880, 0.25);  // نغمة ختامية

    } catch (e) {
        console.warn('Audio alert failed:', e);
    }
}

function showBrowserNotification(order: Order) {
    if (Notification.permission !== 'granted') return;

    const itemsSummary = order.items?.map(i => `${i.quantity}x ${i.item_name_snapshot}`).join('، ') ?? '';
    const noteText = order.customer_note ? `\n📝 ملاحظة الزبون: ${order.customer_note}` : '';

    const notification = new Notification('🔔 طلب جديد وصل المطبخ!', {
        body: `${order.order_number} — ${order.table?.name ?? 'سفري'}\n${itemsSummary}${noteText}`,
        icon: '/favicon.ico',
        badge: '/favicon.ico',
        tag: `order-${order.id}`,        // يمنع تكرار نفس الإشعار
        requireInteraction: false,       // يختفي تلقائياً
    });

    notification.onclick = () => {
        window.focus();
        notification.close();
    };

    // إغلاق الإشعار تلقائياً بعد 8 ثواني
    setTimeout(() => notification.close(), 8000);
}

function alertNewOrder(order: Order) {
    playAlertSound();
    showBrowserNotification(order);
}

interface OrderItem {
    id: number;
    item_name_snapshot: string;
    quantity: number;
    unit_price: string;
    subtotal: string;
    note?: string;
}

interface Order {
    id: number;
    order_number: string;
    status: string;
    status_label: string;
    service_type?: string;
    pickup_window?: number | null;
    paid_at?: string | null;
    restaurant_type?: string;
    windows_count?: number;
    total: string;
    table?: { name: string };
    customer_note?: string | null;
    items?: OrderItem[];
    created_at?: string;
    accepted_at?: string;
    preparing_at?: string;
    ready_at?: string;
    served_at?: string;
    cancelled_at?: string;
}

// ============================================================
// ⏱️ مؤقت الانتظار الحي - يتحدث كل 30 ثانية
// ============================================================

const nowTick = ref(Date.now());
let tickInterval: ReturnType<typeof setInterval>;

onMounted(() => {
    tickInterval = setInterval(() => { nowTick.value = Date.now(); }, 30_000);
});

onUnmounted(() => clearInterval(tickInterval));

function getElapsedMinutes(iso?: string): number {
    if (!iso) return 0;
    return Math.floor((nowTick.value - new Date(iso).getTime()) / 60_000);
}

function elapsedLabel(iso?: string): string {
    const m = getElapsedMinutes(iso);
    if (m < 1) return 'الآن';
    if (m < 60) return `منذ ${m} د`;
    const h = Math.floor(m / 60);
    const rem = m % 60;
    return rem > 0 ? `منذ ${h}س ${rem}د` : `منذ ${h} ساعة`;
}

// لون البطاقة حسب وقت الانتظار (فقط للطلبات غير المنتهية)
function elapsedCardClass(order: Order): string {
    if (['served', 'cancelled'].includes(order.status)) return 'border-stone-100 dark:border-slate-800';
    const m = getElapsedMinutes(order.created_at);
    if (m >= 20) return 'border-red-400 dark:border-red-500 shadow-red-100 dark:shadow-red-950/30';
    if (m >= 10) return 'border-yellow-400 dark:border-yellow-500 shadow-yellow-100 dark:shadow-yellow-950/30';
    return 'border-stone-100 dark:border-slate-800';
}

function elapsedBadgeClass(order: Order): string {
    if (['served', 'cancelled'].includes(order.status)) return 'bg-stone-100 dark:bg-slate-800 text-stone-500 dark:text-stone-400';
    const m = getElapsedMinutes(order.created_at);
    if (m >= 20) return 'bg-red-100 dark:bg-red-950/80 text-red-700 dark:text-red-300 animate-pulse border border-red-200 dark:border-red-800/50';
    if (m >= 10) return 'bg-yellow-100 dark:bg-yellow-950/80 text-yellow-700 dark:text-yellow-300 border border-yellow-200 dark:border-yellow-800/50';
    return 'bg-green-100 dark:bg-green-950/80 text-green-700 dark:text-green-300 border border-green-200 dark:border-green-800/50';
}

// تنسيق الوقت بشكل مختصر وجميل
function formatTime(iso?: string): string {
    if (!iso) return '';
    const d = new Date(iso);
    return d.toLocaleTimeString('ar-SA', { hour: '2-digit', minute: '2-digit', hour12: true });
}

const orders = ref<Order[]>([]);
const page = usePage();
const restaurantId = computed(() => page.props.restaurantId as number | undefined);

async function loadOrders() {
    const { data } = await axios.get('/api/admin/orders');
    const list = data.data?.data ?? data.data ?? [];
    orders.value = list;
    // Auto-expand orders so kitchen staff sees items and notes immediately
    list.forEach((o: Order) => expandedOrderIds.value.add(o.id));
}

const processingOrderIds = ref<number[]>([]);
const expandedOrderIds = ref<Set<number>>(new Set());

const { showToast } = useToast();

const toggleItems = (orderId: number) => {
    if (expandedOrderIds.value.has(orderId)) {
        expandedOrderIds.value.delete(orderId);
    } else {
        expandedOrderIds.value.add(orderId);
    }
};

const nextStatus: Record<string, string> = {
    pending: 'accepted',
    accepted: 'preparing',
    preparing: 'ready',
    ready: 'served',
};

// Drag and drop setup
const draggedOrderId = ref<number | null>(null);

const onDragStart = (event: DragEvent, orderId: number) => {
    draggedOrderId.value = orderId;
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        // For Firefox compatibility
        event.dataTransfer.setData('text/plain', orderId.toString());
    }
};

const onDragEnd = () => {
    draggedOrderId.value = null;
};

const onDrop = async (newStatus: string) => {
    if (!draggedOrderId.value) return;
    
    const orderId = draggedOrderId.value;
    draggedOrderId.value = null; // reset

    const order = orders.value.find(o => o.id === orderId);
    if (!order || order.status === newStatus) return;

    // Validate transition
    if (nextStatus[order.status] !== newStatus) {
        showToast('حركة غير صحيحة', 'لا يمكن تجاوز الترتيب المنطقي للطلبات', 'warning');
        return;
    }

    const win = (newStatus === 'preparing' && isPrepaid.value) ? (order.pickup_window || selectedWindow.value) : order.pickup_window;
    await updateStatus(order, newStatus, win);
};

const authUser = computed(() => (page.props.auth as any)?.user);
const authRestaurant = computed(() => (page.props.auth as any)?.restaurant);
const isPrepaid = computed(() => authRestaurant.value?.type === 'prepaid');
const totalWindows = computed(() => (authRestaurant.value?.windows_count as number) || 1);

// النافذة المحددة لهذا الجهاز / الموظف (إذا كان الشيف مرتبطاً بنافذة افتراضية تُستخدم تلقائياً)
const selectedWindow = ref<number>(
    authUser.value?.default_window || parseInt(localStorage.getItem('tedallal_kds_window') || '1', 10) || 1
);

function changeWindow(num: number) {
    selectedWindow.value = num;
    localStorage.setItem('tedallal_kds_window', num.toString());
}

// تصفية حسب نافذة التحضير (اختياري)
const filterMyWindowOnly = ref(false);

async function updateStatus(order: Order, status: string, pickupWindow?: number | null) {
    if (processingOrderIds.value.includes(order.id)) return;
    
    processingOrderIds.value.push(order.id);
    try {
        const payload: { status: string; pickup_window?: number | null } = { status };
        if (pickupWindow !== undefined && pickupWindow !== null) {
            payload.pickup_window = pickupWindow;
        } else if (status === 'preparing' && !order.pickup_window && isPrepaid.value) {
            payload.pickup_window = selectedWindow.value;
        }

        const { data } = await axios.patch(`/api/admin/orders/${order.id}/status`, payload);
        const updated = data.data;
        
        // Optimistic update
        const index = orders.value.findIndex(o => o.id === order.id);
        if (index !== -1) {
            orders.value[index].status = status;
            if (updated?.pickup_window) {
                orders.value[index].pickup_window = updated.pickup_window;
            } else if (payload.pickup_window) {
                orders.value[index].pickup_window = payload.pickup_window;
            }
            if (updated?.paid_at) {
                orders.value[index].paid_at = updated.paid_at;
            }
            // Best effort status label update locally
            const statusLabels: Record<string, string> = {
                'pending': isPrepaid.value ? 'بانتظار الدفع' : 'تم الاستلام',
                'accepted': isPrepaid.value ? 'مدفوع' : 'مقبول',
                'preparing': 'قيد التحضير',
                'ready': 'جاهز',
                'served': 'تم التقديم'
            };
            if (statusLabels[status]) {
                orders.value[index].status_label = statusLabels[status];
            }
        }
        
    } catch (e: any) {
        showToast('خطأ', e.response?.data?.message || 'حدث خطأ أثناء تحديث الحالة', 'error');
        await loadOrders(); // revert optimistic update
    } finally {
        processingOrderIds.value = processingOrderIds.value.filter(id => id !== order.id);
    }
}

// Columns definition
const columns = computed(() => {
    if (isPrepaid.value) {
        return [
            { 
                id: 'pending', 
                title: 'بانتظار الكاشير 💳', 
                subtitle: 'تأكيد الدفع',
                bg: 'bg-amber-50/60 dark:bg-amber-950/25', 
                border: 'border-amber-200 dark:border-amber-900/40' 
            },
            { 
                id: 'accepted', 
                title: 'طابور النوافذ ⏳', 
                subtitle: 'مدفوع - جاهز لاستلام النافذة',
                bg: 'bg-blue-50/60 dark:bg-blue-950/25', 
                border: 'border-blue-200 dark:border-blue-900/40' 
            },
            { 
                id: 'preparing', 
                title: 'قيد التحضير 👨‍🍳', 
                subtitle: 'موزع على النوافذ',
                bg: 'bg-purple-50/60 dark:bg-purple-950/25', 
                border: 'border-purple-200 dark:border-purple-900/40' 
            },
            { 
                id: 'ready', 
                title: 'جاهز للاستلام 🔔', 
                subtitle: 'عند نافذة التسليم',
                bg: 'bg-emerald-50/60 dark:bg-emerald-950/25', 
                border: 'border-emerald-200 dark:border-emerald-900/40' 
            },
        ];
    }

    return [
        { id: 'pending', title: 'طلبات جديدة', subtitle: '', bg: 'bg-orange-50/50 dark:bg-orange-950/25', border: 'border-orange-200 dark:border-orange-900/40' },
        { id: 'accepted', title: 'بانتظار التحضير', subtitle: '', bg: 'bg-blue-50/50 dark:bg-blue-950/25', border: 'border-blue-200 dark:border-blue-900/40' },
        { id: 'preparing', title: 'قيد التحضير', subtitle: '', bg: 'bg-purple-50/50 dark:bg-purple-950/25', border: 'border-purple-200 dark:border-purple-900/40' },
        { id: 'ready', title: 'جاهز للتقديم', subtitle: '', bg: 'bg-emerald-50/50 dark:bg-emerald-950/25', border: 'border-emerald-200 dark:border-emerald-900/40' },
    ];
});

const getColumnOrders = (status: string) => {
    let list = orders.value.filter(o => o.status === status);
    if (isPrepaid.value && filterMyWindowOnly.value) {
        if (['preparing', 'ready'].includes(status)) {
            list = list.filter(o => o.pickup_window === selectedWindow.value);
        }
    }
    return list;
};

useRestaurantOrders(restaurantId.value, {
    onInitialLoad: loadOrders,
    onOrderCreated: (newOrder) => {
        const exists = orders.value.find(o => o.id === newOrder.id);
        if (!exists) {
            orders.value.unshift(newOrder as Order);
            expandedOrderIds.value.add(newOrder.id);
            // 🔔 إطلاق التنبيه الصوتي والمرئي
            alertNewOrder(newOrder as Order);
        }
    },
    onOrderUpdated: (updatedOrder) => {
        const index = orders.value.findIndex(o => o.id === updatedOrder.id);
        if (index !== -1) {
            orders.value[index] = updatedOrder as Order;
        } else {
            orders.value.unshift(updatedOrder as Order);
        }
    }
});
</script>

<template>
    <Head title="المطبخ - لوحة الكانبان" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 w-full">
                <div class="flex items-center gap-3">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-white">شاشة المطبخ (KDS)</h2>
                    <span v-if="isPrepaid" class="text-xs font-bold px-2.5 py-1 rounded-full bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800/50">
                        ⚡ نظام دفع مسبق ({{ totalWindows }} نوافذ)
                    </span>
                    <span v-if="authUser?.default_window" class="text-xs font-bold px-2.5 py-1 rounded-full bg-blue-100 dark:bg-blue-950/70 text-blue-800 dark:text-blue-300 border border-blue-200 dark:border-blue-800/50">
                        👨‍🍳 شيف نافذة {{ authUser.default_window }}
                    </span>
                </div>

                <!-- مبدل النافذة والتصفية لمطاعم الدفع المسبق -->
                <div v-if="isPrepaid" class="flex items-center gap-2 bg-stone-100 dark:bg-slate-800/80 p-1.5 rounded-xl border border-stone-200 dark:border-slate-700">
                    <span class="text-xs font-bold text-stone-600 dark:text-slate-300 pr-2">🏢 نافذة العمل:</span>
                    <select
                        :value="selectedWindow"
                        @change="changeWindow(Number(($event.target as HTMLSelectElement).value))"
                        class="rounded-lg bg-white dark:bg-slate-900 text-xs font-extrabold text-stone-900 dark:text-white border-stone-300 dark:border-slate-600 py-1 pl-6 pr-2 focus:ring-amber-500 cursor-pointer"
                    >
                        <option v-for="w in totalWindows" :key="w" :value="w">
                            نافذة رقم {{ w }}
                        </option>
                    </select>

                    <button
                        type="button"
                        @click="filterMyWindowOnly = !filterMyWindowOnly"
                        :class="[
                            'text-xs font-bold px-2.5 py-1 rounded-lg transition-colors border',
                            filterMyWindowOnly
                                ? 'bg-amber-600 text-white border-amber-600'
                                : 'bg-white dark:bg-slate-900 text-stone-600 dark:text-slate-400 border-stone-200 dark:border-slate-700 hover:bg-stone-50'
                        ]"
                    >
                        {{ filterMyWindowOnly ? `نافذة ${selectedWindow} فقط` : 'عرض كل النوافذ' }}
                    </button>
                </div>

                <!-- أزرار الإشعارات والصوت -->
                <div class="flex items-center gap-3">
                    <!-- زر تفعيل الصوت -->
                    <button
                        v-if="!audioUnlocked"
                        @click="unlockAudio"
                        class="flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-semibold transition-all bg-red-50 dark:bg-red-950/50 text-red-600 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-900/50 border border-red-200 dark:border-red-800/60 animate-pulse"
                        title="المتصفح يمنع تشغيل الصوت التلقائي، انقر هنا للسماح بصوت التنبيهات"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2"></path></svg>
                        <span>تفعيل الصوت</span>
                    </button>
                    <span v-else class="flex items-center gap-1.5 text-xs font-medium text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/50 px-3 py-1.5 rounded-lg border border-emerald-200 dark:border-emerald-800/50">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"></path></svg>
                        الصوت مفعّل
                    </span>

                    <!-- زر تفعيل إشعارات المتصفح -->
                    <button
                        v-if="notificationPermission !== 'granted'"
                        @click="requestNotificationPermission"
                        class="flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-semibold transition-all"
                        :class="notificationPermission === 'denied'
                            ? 'bg-red-50 dark:bg-red-950/50 text-red-600 dark:text-red-400 cursor-not-allowed'
                            : 'bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-400 hover:bg-amber-100 dark:hover:bg-amber-900/50 border border-amber-200 dark:border-amber-800/50'"
                        :disabled="notificationPermission === 'denied'"
                        :title="notificationPermission === 'denied' ? 'تم رفض الإشعارات. يرجى السماح بها من إعدادات المتصفح' : ''"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                        <span v-if="notificationPermission === 'denied'">الإشعارات محجوبة</span>
                        <span v-else>تفعيل الإشعارات</span>
                    </button>
                    <span v-else class="flex items-center gap-1.5 text-xs font-medium text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/50 px-3 py-1.5 rounded-lg border border-emerald-200 dark:border-emerald-800/50">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        الإشعارات مفعّلة
                    </span>
                </div>
            </div>
        </template>

        <div class="py-6" dir="rtl">
            <div class="mx-auto max-w-[1400px] px-4 h-full flex flex-col">
                <AdminNav class="mb-4" />
                
                <div v-if="orders.length === 0" class="rounded-2xl bg-white dark:bg-[#0a0f1d] p-12 text-center text-gray-500 dark:text-gray-400 shadow-xs border border-dashed border-gray-300 dark:border-slate-800 mt-4 flex flex-col items-center justify-center min-h-[300px]">
                    <svg class="w-16 h-16 text-gray-300 dark:text-slate-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                    <p class="text-lg font-medium text-stone-700 dark:text-stone-300">لا يوجد طلبات نشطة حالياً</p>
                    <p class="text-sm opacity-70">عند استلام طلب جديد سيظهر هنا تلقائياً.</p>
                </div>

                <!-- Kanban Board Grid -->
                <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 items-start h-full">
                    
                    <div 
                        v-for="col in columns" 
                        :key="col.id"
                        class="flex flex-col h-full rounded-2xl border bg-white dark:bg-[#0a0f1d] shadow-xs overflow-hidden transition-colors"
                        :class="[col.border]"
                        @dragover.prevent
                        @drop.prevent="onDrop(col.id)"
                    >
                        <!-- Column Header -->
                        <div class="p-4 border-b border-stone-100 dark:border-slate-800 flex items-center justify-between" :class="col.bg">
                            <div>
                                <h3 class="font-bold text-gray-800 dark:text-white">{{ col.title }}</h3>
                                <p v-if="col.subtitle" class="text-[11px] text-stone-500 dark:text-slate-400 mt-0.5">{{ col.subtitle }}</p>
                            </div>
                            <span class="bg-white dark:bg-slate-800 text-stone-800 dark:text-stone-200 rounded-full px-3 py-1 text-xs font-bold shadow-xs border border-stone-100 dark:border-slate-700">
                                {{ getColumnOrders(col.id).length }}
                            </span>
                        </div>

                        <!-- Column Body (Scrollable) -->
                        <div class="p-3 flex-1 overflow-y-auto bg-stone-50/30 dark:bg-slate-950/30 min-h-[500px]">
                            
                            <div v-if="getColumnOrders(col.id).length === 0" class="h-full flex items-center justify-center text-stone-400 dark:text-slate-500 text-sm italic">
                                اسحب الطلب إلى هنا...
                            </div>
                            
                            <TransitionGroup name="list" tag="div" class="space-y-3 relative">
                                <article
                                    v-for="order in getColumnOrders(col.id)"
                                    :key="order.id"
                                    :draggable="!processingOrderIds.includes(order.id)"
                                    @dragstart="onDragStart($event, order.id)"
                                    @dragend="onDragEnd"
                                    class="rounded-xl bg-white dark:bg-slate-900 p-3 shadow-xs border cursor-grab active:cursor-grabbing hover:shadow-md transition-all duration-200"
                                    :class="[elapsedCardClass(order), {'opacity-50 pointer-events-none': processingOrderIds.includes(order.id)}]"
                                >
                                    <div class="flex items-center justify-between mb-2">
                                        <div>
                                            <p class="font-black text-gray-900 dark:text-white text-lg">{{ order.order_number }}</p>
                                            <div class="flex items-center gap-1.5 flex-wrap mt-1">
                                                <span class="text-xs font-semibold text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/50 px-2 py-0.5 rounded-full inline-block border border-amber-200/50 dark:border-amber-800/40">
                                                    {{ (order.service_type ?? 'dine_in') === 'takeaway' ? '🥡 سفري' : (order.table?.name || 'محلي') }}
                                                </span>
                                                <span v-if="order.pickup_window" class="text-xs font-bold text-sky-700 dark:text-sky-300 bg-sky-50 dark:bg-sky-950/60 px-2 py-0.5 rounded-full inline-block border border-sky-200 dark:border-sky-800/40">
                                                    🏢 نافذة {{ order.pickup_window }}
                                                </span>
                                                <span v-if="order.paid_at" class="text-xs font-bold text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/60 px-2 py-0.5 rounded-full inline-block border border-emerald-200 dark:border-emerald-800/40">
                                                    ✓ مدفوع
                                                </span>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <a
                                                :href="`/admin/orders/${order.id}/print`"
                                                target="_blank"
                                                class="p-1 text-stone-400 dark:text-slate-400 hover:text-stone-700 dark:hover:text-stone-200 hover:bg-stone-100 dark:hover:bg-slate-800 rounded-lg transition-colors"
                                                title="طباعة الفاتورة"
                                                @click.stop
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                                            </a>
                                            <!-- شارة وقت الانتظار -->
                                            <span
                                                class="text-xs font-bold px-2 py-1 rounded-lg"
                                                :class="elapsedBadgeClass(order)"
                                            >
                                                {{ elapsedLabel(order.created_at) }}
                                            </span>
                                        </div>
                                    </div>

                                    <!-- مخطط زمني للحالات -->
                                    <div class="space-y-1 mb-2">
                                        <div v-if="order.created_at" class="flex items-center gap-2 text-xs text-stone-500 dark:text-slate-400">
                                            <span class="w-2 h-2 rounded-full bg-orange-400 shrink-0"></span>
                                            <span class="font-medium text-stone-600 dark:text-slate-300">وصل:</span>
                                            <span dir="ltr">{{ formatTime(order.created_at) }}</span>
                                        </div>
                                        <div v-if="order.accepted_at" class="flex items-center gap-2 text-xs text-blue-600 dark:text-blue-400">
                                            <span class="w-2 h-2 rounded-full bg-blue-400 shrink-0"></span>
                                            <span class="font-medium">قُبل:</span>
                                            <span dir="ltr">{{ formatTime(order.accepted_at) }}</span>
                                        </div>
                                        <div v-if="order.preparing_at" class="flex items-center gap-2 text-xs text-purple-600 dark:text-purple-400">
                                            <span class="w-2 h-2 rounded-full bg-purple-400 shrink-0"></span>
                                            <span class="font-medium">بدأ التحضير:</span>
                                            <span dir="ltr">{{ formatTime(order.preparing_at) }}</span>
                                        </div>
                                        <div v-if="order.ready_at" class="flex items-center gap-2 text-xs text-emerald-600 dark:text-emerald-400">
                                            <span class="w-2 h-2 rounded-full bg-emerald-400 shrink-0"></span>
                                            <span class="font-medium">جاهز:</span>
                                            <span dir="ltr">{{ formatTime(order.ready_at) }}</span>
                                        </div>
                                        <div v-if="order.cancelled_at" class="flex items-center gap-2 text-xs text-red-500 dark:text-red-400">
                                            <span class="w-2 h-2 rounded-full bg-red-400 shrink-0"></span>
                                            <span class="font-medium">أُلغي:</span>
                                            <span dir="ltr">{{ formatTime(order.cancelled_at) }}</span>
                                        </div>
                                    </div>
                                    
                                    <!-- ملاحظة الزبون العامة للطلب -->
                                    <div v-if="order.customer_note" class="my-2.5 rounded-xl bg-amber-50 dark:bg-amber-950/40 p-3 border border-amber-200 dark:border-amber-800/60 text-amber-900 dark:text-amber-200 text-xs shadow-xs">
                                        <div class="flex items-center gap-1.5 font-bold mb-1 text-amber-800 dark:text-amber-300">
                                            <svg class="w-4 h-4 shrink-0 text-amber-600 dark:text-amber-400" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                            </svg>
                                            <span>ملاحظة الزبون على الطلب:</span>
                                        </div>
                                        <p class="font-bold whitespace-pre-wrap leading-relaxed text-amber-950 dark:text-amber-100 pr-5 bg-amber-100/60 dark:bg-amber-900/40 p-2 rounded-lg border border-amber-200/60 dark:border-amber-800/40">{{ order.customer_note }}</p>
                                    </div>
                                    
                                    <!-- Items Accordion -->
                                    <div class="mt-3" v-if="order.items && order.items.length > 0">
                                        <button 
                                            @click="toggleItems(order.id)"
                                            class="flex w-full items-center justify-between bg-stone-50 dark:bg-slate-800 hover:bg-stone-100 dark:hover:bg-slate-700/80 px-3 py-2 rounded-lg text-sm font-medium text-stone-700 dark:text-stone-200 transition-colors"
                                        >
                                            <div class="flex items-center gap-2">
                                                <span>تفاصيل الأصناف ({{ order.items.length }})</span>
                                                <span v-if="order.items.some(i => i.note)" class="text-[10px] bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 font-bold px-1.5 py-0.5 rounded-md border border-amber-200 dark:border-amber-800/50">
                                                    ملاحظات خاصة
                                                </span>
                                            </div>
                                            <svg class="w-4 h-4 transition-transform duration-200" :class="{'rotate-180': expandedOrderIds.has(order.id)}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                        </button>
                                        
                                        <div v-show="expandedOrderIds.has(order.id)" class="mt-2 space-y-1.5 px-1">
                                            <div v-for="item in order.items" :key="item.id" class="text-sm py-1.5 border-b border-dashed border-stone-200 dark:border-slate-800 last:border-0">
                                                <div class="flex justify-between items-center">
                                                    <div class="flex items-center gap-2">
                                                        <span class="font-black text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/60 px-1.5 py-0.5 rounded border border-emerald-200/40 dark:border-emerald-800/40">{{ item.quantity }}x</span>
                                                        <span class="font-medium text-stone-800 dark:text-stone-200">{{ item.item_name_snapshot }}</span>
                                                    </div>
                                                </div>
                                                <!-- ملاحظة خاصة بالصنف -->
                                                <div v-if="item.note" class="mt-1 flex items-start gap-1 mr-6 text-xs text-amber-800 dark:text-amber-300 bg-amber-50 dark:bg-amber-950/40 rounded-md px-2 py-1 border border-amber-200/80 dark:border-amber-800/60">
                                                    <span class="font-bold shrink-0">↳ ملاحظة:</span>
                                                    <span class="font-semibold">{{ item.note }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Action Button (Fallback for touch screens) -->
                                    <div class="mt-4 pt-3 border-t border-stone-100 dark:border-slate-800 flex justify-end" v-if="nextStatus[order.status]">
                                        <!-- زر تأكيد الكاشير للدفع في نظام الدفع المسبق -->
                                        <button
                                            v-if="isPrepaid && order.status === 'pending'"
                                            type="button"
                                            :disabled="processingOrderIds.includes(order.id)"
                                            class="w-full rounded-xl bg-emerald-600 hover:bg-emerald-700 dark:bg-emerald-600 dark:hover:bg-emerald-500 py-2.5 px-3 text-sm font-bold text-white shadow-md active:scale-95 transition-all flex items-center justify-center gap-2"
                                            @click="updateStatus(order, 'accepted')"
                                        >
                                            <span v-if="processingOrderIds.includes(order.id)">
                                                <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-dasharray="31.415, 31.415" opacity="0.3"></circle><path d="M12 2C6.477 2 2 6.477 2 12" stroke="currentColor" stroke-width="4" stroke-linecap="round"></path></svg>
                                            </span>
                                            <span v-else>💳 تأكيد استلام المبلغ وإرسال للمطبخ</span>
                                        </button>

                                        <!-- زر استلام النافذة للطلب في نظام الدفع المسبق -->
                                        <button
                                            v-else-if="isPrepaid && order.status === 'accepted'"
                                            type="button"
                                            :disabled="processingOrderIds.includes(order.id)"
                                            class="w-full rounded-xl bg-blue-600 hover:bg-blue-700 dark:bg-blue-600 dark:hover:bg-blue-500 py-2.5 px-3 text-sm font-bold text-white shadow-md active:scale-95 transition-all flex items-center justify-center gap-2"
                                            @click="updateStatus(order, 'preparing', selectedWindow)"
                                        >
                                            <span v-if="processingOrderIds.includes(order.id)">
                                                <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-dasharray="31.415, 31.415" opacity="0.3"></circle><path d="M12 2C6.477 2 2 6.477 2 12" stroke="currentColor" stroke-width="4" stroke-linecap="round"></path></svg>
                                            </span>
                                            <span v-else>👨‍🍳 استلام على نافذة رقم {{ selectedWindow }}</span>
                                        </button>

                                        <!-- باقي الحالات أو النظام الافتراضي (دفع لاحق) -->
                                        <button
                                            v-else
                                            type="button"
                                            :disabled="processingOrderIds.includes(order.id)"
                                            class="w-full rounded-lg bg-stone-900 dark:bg-gradient-to-r dark:from-amber-500 dark:to-orange-500 px-3 py-2.5 text-sm font-bold text-white dark:text-slate-950 shadow-xs hover:bg-stone-800 dark:hover:from-amber-600 dark:hover:to-orange-600 disabled:opacity-50 transition-all flex items-center justify-center gap-2"
                                            @click="updateStatus(order, nextStatus[order.status])"
                                        >
                                            <span v-if="processingOrderIds.includes(order.id)">
                                                <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-dasharray="31.415, 31.415" opacity="0.3"></circle><path d="M12 2C6.477 2 2 6.477 2 12" stroke="currentColor" stroke-width="4" stroke-linecap="round"></path></svg>
                                            </span>
                                            <span v-else>
                                                {{ 
                                                    order.status === 'pending' ? 'البدء بالتجهيز' : 
                                                    order.status === 'accepted' ? 'بدء التحضير' : 
                                                    order.status === 'preparing' ? (isPrepaid ? `جاهز للاستلام (نافذة ${order.pickup_window || selectedWindow}) 🔔` : 'تم التجهيز') : 
                                                    (isPrepaid ? 'تم تسليم الطلب للزبون ✓' : 'تم التقديم للزبون ✓')
                                                }}
                                            </span>
                                        </button>
                                    </div>
                                </article>
                            </TransitionGroup>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.list-enter-active,
.list-leave-active {
  transition: all 0.3s ease;
}
.list-enter-from,
.list-leave-to {
  opacity: 0;
  transform: translateY(15px);
}
.list-move {
  transition: transform 0.3s ease;
}
</style>
