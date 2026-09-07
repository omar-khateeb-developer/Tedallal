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

// توليد صوت تنبيه مزدوج باستخدام Web Audio API (لا يحتاج ملف صوتي)
function playAlertSound() {
    try {
        const ctx = new (window.AudioContext || (window as any).webkitAudioContext)();

        const playBeep = (startTime: number, freq: number, duration: number) => {
            const oscillator = ctx.createOscillator();
            const gainNode = ctx.createGain();

            oscillator.connect(gainNode);
            gainNode.connect(ctx.destination);

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

        const now = ctx.currentTime;
        playBeep(now, 880, 0.18);        // نغمة أولى
        playBeep(now + 0.22, 1100, 0.18); // نغمة ثانية أعلى
        playBeep(now + 0.44, 880, 0.25);  // نغمة ختامية

        // إغلاق السياق بعد انتهاء الأصوات
        setTimeout(() => ctx.close(), 1200);
    } catch (e) {
        console.warn('Audio alert failed:', e);
    }
}

function showBrowserNotification(order: Order) {
    if (Notification.permission !== 'granted') return;

    const itemsSummary = order.items?.map(i => `${i.quantity}x ${i.item_name_snapshot}`).join('، ') ?? '';

    const notification = new Notification('🔔 طلب جديد وصل المطبخ!', {
        body: `${order.order_number} — ${order.table?.name ?? 'سفري'}\n${itemsSummary}`,
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
    total: string;
    table?: { name: string };
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
    if (['served', 'cancelled'].includes(order.status)) return 'border-stone-100';
    const m = getElapsedMinutes(order.created_at);
    if (m >= 20) return 'border-red-400 shadow-red-100';
    if (m >= 10) return 'border-yellow-400 shadow-yellow-100';
    return 'border-stone-100';
}

function elapsedBadgeClass(order: Order): string {
    if (['served', 'cancelled'].includes(order.status)) return 'bg-stone-100 text-stone-500';
    const m = getElapsedMinutes(order.created_at);
    if (m >= 20) return 'bg-red-100 text-red-700 animate-pulse';
    if (m >= 10) return 'bg-yellow-100 text-yellow-700';
    return 'bg-green-100 text-green-700';
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
    orders.value = data.data?.data ?? data.data ?? [];
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

    await updateStatus(order, newStatus);
};

async function updateStatus(order: Order, status: string) {
    if (processingOrderIds.value.includes(order.id)) return;
    
    processingOrderIds.value.push(order.id);
    try {
        await axios.patch(`/api/admin/orders/${order.id}/status`, { status });
        
        // Optimistic update
        const index = orders.value.findIndex(o => o.id === order.id);
        if (index !== -1) {
            orders.value[index].status = status;
            // Best effort status label update locally
            const statusLabels: Record<string, string> = {
                'accepted': 'مقبول',
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
const columns = [
    { id: 'pending', title: 'طلبات جديدة', bg: 'bg-orange-50/50', border: 'border-orange-200' },
    { id: 'accepted', title: 'بانتظار التحضير', bg: 'bg-blue-50/50', border: 'border-blue-200' },
    { id: 'preparing', title: 'قيد التحضير', bg: 'bg-purple-50/50', border: 'border-purple-200' },
    { id: 'ready', title: 'جاهز للتقديم', bg: 'bg-emerald-50/50', border: 'border-emerald-200' },
];

const getColumnOrders = (status: string) => {
    return orders.value.filter(o => o.status === status);
};

useRestaurantOrders(restaurantId.value, {
    onInitialLoad: loadOrders,
    onOrderCreated: (newOrder) => {
        const exists = orders.value.find(o => o.id === newOrder.id);
        if (!exists) {
            orders.value.unshift(newOrder as Order);
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
            <div class="flex items-center justify-between w-full">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">شاشة المطبخ (KDS)</h2>

                <!-- زر تفعيل إشعارات المتصفح -->
                <button
                    v-if="notificationPermission !== 'granted'"
                    @click="requestNotificationPermission"
                    class="flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-semibold transition-all"
                    :class="notificationPermission === 'denied'
                        ? 'bg-red-50 text-red-600 cursor-not-allowed'
                        : 'bg-amber-50 text-amber-700 hover:bg-amber-100 border border-amber-200'"
                    :disabled="notificationPermission === 'denied'"
                    :title="notificationPermission === 'denied' ? 'تم رفض الإشعارات. يرجى السماح بها من إعدادات المتصفح' : ''"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                    <span v-if="notificationPermission === 'denied'">الإشعارات محجوبة</span>
                    <span v-else>تفعيل الإشعارات</span>
                </button>
                <span v-else class="flex items-center gap-1.5 text-xs font-medium text-emerald-600 bg-emerald-50 px-3 py-1.5 rounded-lg border border-emerald-200">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    الإشعارات مفعّلة
                </span>
            </div>
        </template>

        <div class="py-6" dir="rtl">
            <div class="mx-auto max-w-[1400px] px-4 h-full flex flex-col">
                <AdminNav class="mb-4" />
                
                <div v-if="orders.length === 0" class="rounded-2xl bg-white p-12 text-center text-gray-500 shadow-sm border border-dashed border-gray-300 mt-4 flex flex-col items-center justify-center min-h-[300px]">
                    <svg class="w-16 h-16 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                    <p class="text-lg font-medium">لا يوجد طلبات نشطة حالياً</p>
                    <p class="text-sm opacity-70">عند استلام طلب جديد سيظهر هنا تلقائياً.</p>
                </div>

                <!-- Kanban Board Grid -->
                <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 items-start h-full">
                    
                    <div 
                        v-for="col in columns" 
                        :key="col.id"
                        class="flex flex-col h-full rounded-2xl border bg-white shadow-sm overflow-hidden"
                        :class="[col.border]"
                        @dragover.prevent
                        @drop.prevent="onDrop(col.id)"
                    >
                        <!-- Column Header -->
                        <div class="p-4 border-b border-stone-100 flex items-center justify-between" :class="col.bg">
                            <h3 class="font-bold text-gray-800">{{ col.title }}</h3>
                            <span class="bg-white rounded-full px-3 py-1 text-xs font-bold shadow-sm border border-stone-100">
                                {{ getColumnOrders(col.id).length }}
                            </span>
                        </div>

                        <!-- Column Body (Scrollable) -->
                        <div class="p-3 flex-1 overflow-y-auto bg-stone-50/30 min-h-[500px]">
                            
                            <div v-if="getColumnOrders(col.id).length === 0" class="h-full flex items-center justify-center text-stone-400 text-sm italic">
                                اسحب الطلب إلى هنا...
                            </div>
                            
                            <TransitionGroup name="list" tag="div" class="space-y-3 relative">
                                <article
                                    v-for="order in getColumnOrders(col.id)"
                                    :key="order.id"
                                    :draggable="!processingOrderIds.includes(order.id)"
                                    @dragstart="onDragStart($event, order.id)"
                                    @dragend="onDragEnd"
                                    class="rounded-xl bg-white p-3 shadow-sm border cursor-grab active:cursor-grabbing hover:shadow-md transition-all duration-200"
                                    :class="[elapsedCardClass(order), {'opacity-50 pointer-events-none': processingOrderIds.includes(order.id)}]"
                                >
                                    <div class="flex items-center justify-between mb-2">
                                        <div>
                                            <p class="font-black text-gray-900 text-lg">{{ order.order_number }}</p>
                                            <p class="text-xs font-semibold text-amber-600 bg-amber-50 px-2 py-0.5 rounded-full inline-block mt-1">
                                                {{ order.table?.name || 'سفري' }}
                                            </p>
                                        </div>
                                        <!-- شارة وقت الانتظار -->
                                        <span
                                            class="text-xs font-bold px-2 py-1 rounded-lg"
                                            :class="elapsedBadgeClass(order)"
                                        >
                                            {{ elapsedLabel(order.created_at) }}
                                        </span>
                                    </div>

                                    <!-- مخطط زمني للحالات -->
                                    <div class="space-y-1 mb-2">
                                        <div v-if="order.created_at" class="flex items-center gap-2 text-xs text-stone-500">
                                            <span class="w-2 h-2 rounded-full bg-orange-400 shrink-0"></span>
                                            <span class="font-medium text-stone-600">وصل:</span>
                                            <span dir="ltr">{{ formatTime(order.created_at) }}</span>
                                        </div>
                                        <div v-if="order.accepted_at" class="flex items-center gap-2 text-xs text-blue-600">
                                            <span class="w-2 h-2 rounded-full bg-blue-400 shrink-0"></span>
                                            <span class="font-medium">قُبل:</span>
                                            <span dir="ltr">{{ formatTime(order.accepted_at) }}</span>
                                        </div>
                                        <div v-if="order.preparing_at" class="flex items-center gap-2 text-xs text-purple-600">
                                            <span class="w-2 h-2 rounded-full bg-purple-400 shrink-0"></span>
                                            <span class="font-medium">بدأ التحضير:</span>
                                            <span dir="ltr">{{ formatTime(order.preparing_at) }}</span>
                                        </div>
                                        <div v-if="order.ready_at" class="flex items-center gap-2 text-xs text-emerald-600">
                                            <span class="w-2 h-2 rounded-full bg-emerald-400 shrink-0"></span>
                                            <span class="font-medium">جاهز:</span>
                                            <span dir="ltr">{{ formatTime(order.ready_at) }}</span>
                                        </div>
                                        <div v-if="order.cancelled_at" class="flex items-center gap-2 text-xs text-red-500">
                                            <span class="w-2 h-2 rounded-full bg-red-400 shrink-0"></span>
                                            <span class="font-medium">أُلغي:</span>
                                            <span dir="ltr">{{ formatTime(order.cancelled_at) }}</span>
                                        </div>
                                    </div>
                                    
                                    <!-- Items Accordion -->
                                    <div class="mt-3" v-if="order.items && order.items.length > 0">
                                        <button 
                                            @click="toggleItems(order.id)"
                                            class="flex w-full items-center justify-between bg-stone-50 hover:bg-stone-100 px-3 py-2 rounded-lg text-sm font-medium text-stone-700 transition-colors"
                                        >
                                            <span>تفاصيل الأصناف ({{ order.items.length }})</span>
                                            <svg class="w-4 h-4 transition-transform duration-200" :class="{'rotate-180': expandedOrderIds.has(order.id)}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                        </button>
                                        
                                        <div v-show="expandedOrderIds.has(order.id)" class="mt-2 space-y-1 px-1">
                                            <div v-for="item in order.items" :key="item.id" class="flex justify-between items-center text-sm py-1 border-b border-dashed border-stone-200 last:border-0">
                                                <div class="flex items-center gap-2">
                                                    <span class="font-black text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded">{{ item.quantity }}x</span>
                                                    <span class="font-medium text-stone-800">{{ item.item_name_snapshot }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Action Button (Fallback for touch screens) -->
                                    <div class="mt-4 pt-3 border-t border-stone-50 flex justify-end" v-if="nextStatus[order.status]">
                                        <button
                                            type="button"
                                            :disabled="processingOrderIds.includes(order.id)"
                                            class="w-full rounded-lg bg-stone-900 px-3 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-stone-800 disabled:opacity-50 transition-all flex items-center justify-center gap-2"
                                            @click="updateStatus(order, nextStatus[order.status])"
                                        >
                                            <span v-if="processingOrderIds.includes(order.id)">
                                                <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-dasharray="31.415, 31.415" opacity="0.3"></circle><path d="M12 2C6.477 2 2 6.477 2 12" stroke="currentColor" stroke-width="4" stroke-linecap="round"></path></svg>
                                            </span>
                                            <span v-else>
                                                {{ 
                                                    order.status === 'pending' ? 'البدء بالتجهيز' : 
                                                    order.status === 'accepted' ? 'بدء التحضير' : 
                                                    order.status === 'preparing' ? 'تم التجهيز' : 'التالي' 
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
