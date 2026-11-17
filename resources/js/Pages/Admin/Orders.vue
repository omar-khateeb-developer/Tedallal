<script setup lang="ts">
import AdminNav from '@/Components/Admin/AdminNav.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import NotificationToast from '@/Components/NotificationToast.vue';
import ConnectionBanner from '@/Components/ConnectionBanner.vue';
import { useRestaurantOrders } from '@/composables/useRealtimeOrders';
import { useToast } from '@/composables/useToast';
import { useWakeLock } from '@/composables/useWakeLock';
import { useLiveConnection } from '@/composables/useLiveConnection';
import { soundAlerts } from '@/utils/soundAlerts';
import { Head, usePage } from '@inertiajs/vue3';
import { computed, ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

interface OrderItem {
    id: number;
    item_name_snapshot: string;
    unit_price?: string;
    quantity: number;
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
    subtotal?: string;
    total: string;
    table?: { name: string };
    customer_note?: string | null;
    created_at?: string | null;
    items?: OrderItem[];
}

const orders = ref<Order[]>([]);
const searchQuery = ref('');
const page = usePage();
const restaurantId = computed(() => page.props.restaurantId as number | undefined);
const authRestaurant = computed(() => (page.props.auth as any)?.restaurant);
const authUser = computed(() => (page.props.auth as any)?.user);
const isPrepaid = computed(() => authRestaurant.value?.type === 'prepaid');
const isAdminUser = computed(() => {
    const role = authUser.value?.role;
    return role === 'admin' || role === 'super_admin' || authUser.value?.is_super_admin;
});
const isCashierUser = computed(() => {
    return authUser.value?.role === 'cashier';
});

const filteredOrders = computed(() => {
    if (!searchQuery.value.trim()) return orders.value;
    const q = searchQuery.value.trim().toLowerCase();
    return orders.value.filter(o => 
        o.order_number.toLowerCase().includes(q) ||
        (o.table?.name && o.table.name.toLowerCase().includes(q))
    );
});

const pendingCashierOrdersCount = computed(() => 
    orders.value.filter(o => o.status === 'pending' && !o.paid_at).length
);

const { showToast } = useToast();

function playSound() {
    soundAlerts.playCashierAlert();
}

async function loadOrders() {
    const { data } = await axios.get('/api/admin/orders');
    orders.value = data.data?.data ?? data.data ?? [];
}

const { isSupported: isWakeLockSupported, isActive: isWakeLockActive, toggleLock: toggleWakeLock } = useWakeLock();
const { isOnline, isSocketConnected, showReconnectedBanner, reconnectNow } = useLiveConnection(loadOrders);

function shareOnWhatsApp(order: Order) {
    const origin = window.location.origin;
    const printUrl = `${origin}/admin/orders/${order.id}/print`;
    const currency = (page.props.auth as any)?.restaurant?.currency ?? 'ر.س';
    const restName = (page.props.auth as any)?.restaurant?.name ?? 'المطعم';
    const tableInfo = order.table?.name ? ` (طاولة ${order.table.name})` : (order.service_type === 'takeaway' ? ' (سفري)' : '');
    const itemsText = order.items && order.items.length > 0
        ? '\n📋 تفاصيل الأصناف:\n' + order.items.map(i => `• ${i.quantity}x ${i.item_name_snapshot}`).join('\n')
        : '';
    const text = `فاتورة وتتبع طلب #${order.order_number}${tableInfo} من ${restName} 🍽️\n` +
        `• الحالة: ${order.status_label}\n` +
        `• الإجمالي: ${order.total} ${currency}${itemsText}\n\n` +
        `📄 رابط الفاتورة الإلكترونية:\n${printUrl}`;
    window.open(`https://wa.me/?text=${encodeURIComponent(text)}`, '_blank');
}

// حالة نافذة المعاينة التفاعلية للفاتورة الحرارية (80mm)
const receiptOrder = ref<Order | null>(null);
const showReceiptModal = ref(false);

function openReceiptModal(order: Order) {
    receiptOrder.value = order;
    showReceiptModal.value = true;
}

function closeReceiptModal() {
    showReceiptModal.value = false;
    receiptOrder.value = null;
}

function printReceipt(orderId: number) {
    window.open(`/admin/orders/${orderId}/print`, '_blank');
}

function formatReceiptTime(isoStr?: string | null): string {
    if (!isoStr) return new Date().toLocaleTimeString('ar-SY', { hour: '2-digit', minute: '2-digit' });
    try {
        const d = new Date(isoStr);
        return d.toLocaleDateString('ar-SY', { year: 'numeric', month: 'short', day: 'numeric' }) + ' ' +
               d.toLocaleTimeString('ar-SY', { hour: '2-digit', minute: '2-digit' });
    } catch {
        return String(isoStr);
    }
}

function onKeyDown(e: KeyboardEvent) {
    if (e.key === 'Escape' && showReceiptModal.value) {
        closeReceiptModal();
    }
}

onMounted(() => {
    if (typeof window !== 'undefined') {
        window.addEventListener('keydown', onKeyDown);
    }
});

onUnmounted(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('keydown', onKeyDown);
    }
});

// الطباعة التلقائية للفاتورة عند اكتمال التحضير (جاهز)
const isAutoPrintActive = ref<boolean>(
    !!((page.props.auth as any)?.restaurant?.effective_features?.auto_print_on_ready ?? (page.props.auth as any)?.restaurant?.features?.auto_print_on_ready)
);

function triggerAutoPrint(orderId: number) {
    let iframe = document.getElementById('auto-print-receipt-frame') as HTMLIFrameElement;
    if (!iframe) {
        iframe = document.createElement('iframe');
        iframe.id = 'auto-print-receipt-frame';
        iframe.style.position = 'fixed';
        iframe.style.right = '0';
        iframe.style.bottom = '0';
        iframe.style.width = '0';
        iframe.style.height = '0';
        iframe.style.border = '0';
        iframe.style.opacity = '0';
        document.body.appendChild(iframe);
    }
    iframe.src = `/admin/orders/${orderId}/print`;
}

const updatingIds = ref<Set<number>>(new Set());

async function updateStatus(order: Order, status: string, pickupWindow?: number | null) {
    if (updatingIds.value.has(order.id)) return;
    updatingIds.value.add(order.id);

    try {
        const payload: { status: string; pickup_window?: number | null } = { status };
        if (pickupWindow !== undefined && pickupWindow !== null) {
            payload.pickup_window = pickupWindow;
        }

        const { data } = await axios.patch(`/api/admin/orders/${order.id}/status`, payload);
        const index = orders.value.findIndex(o => o.id === order.id);
        if (index !== -1) {
            orders.value[index] = data.data ?? { ...order, status, pickup_window: payload.pickup_window };
        }

        // تشغيل الطباعة التلقائية فور تحويل الطلب إلى "جاهز / تم التحضير" لتوضع الفاتورة مع الوجبة
        if (status === 'ready' && isAutoPrintActive.value) {
            triggerAutoPrint(order.id);
        }
    } catch (error: any) {
        showToast('خطأ', error.response?.data?.message ?? 'تعذّر تحديث حالة الطلب', 'error');
        await loadOrders();
    } finally {
        updatingIds.value.delete(order.id);
    }
}


useRestaurantOrders(restaurantId.value, {
    onInitialLoad: loadOrders,
    onOrderCreated: (newOrder) => {
        // Prepend to array
        const exists = orders.value.find(o => o.id === newOrder.id);
        if (!exists) {
            orders.value.unshift(newOrder as Order);
        }
        playSound();
        showToast('طلب جديد', `تم استلام طلب جديد #${newOrder.order_number} ${newOrder.table ? 'من ' + newOrder.table.name : ''}`, 'success');
    },
    onOrderUpdated: (updatedOrder) => {
        const index = orders.value.findIndex(o => o.id === updatedOrder.id);
        const prevStatus = index !== -1 ? orders.value[index].status : null;
        if (index !== -1) {
            orders.value[index] = updatedOrder as Order;
        } else {
            orders.value.unshift(updatedOrder as Order);
        }

        // إذا تم تحويل الطلب إلى "جاهز" من شاشة المطبخ مثلاً وكان خيار الطباعة التلقائية مفعلاً
        if (updatedOrder.status === 'ready' && prevStatus !== 'ready' && isAutoPrintActive.value) {
            triggerAutoPrint(updatedOrder.id);
        }
    }
});
</script>

<template>
    <Head title="الطلبات" />

    <ConnectionBanner :is-online="isOnline" :is-socket-connected="isSocketConnected" :show-reconnected="showReconnectedBanner" @reconnect="reconnectNow" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-white">إدارة الطلبات</h2>
        </template>

        <div class="py-8" dir="rtl">
            <div class="mx-auto max-w-5xl px-4">
                <AdminNav />
            </div>
            <div class="mx-auto max-w-5xl space-y-4 px-4">
                <!-- شريط بحث الكاشير وتنبيه الطلبات بانتظار الدفع -->
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 bg-white dark:bg-[#0a0f1d] p-3.5 rounded-2xl border border-stone-200 dark:border-slate-800 shadow-xs">
                    <div class="relative flex-1">
                        <input
                            v-model="searchQuery"
                            type="text"
                            class="w-full rounded-xl border-stone-200 dark:border-slate-700 bg-stone-50 dark:bg-slate-900 pr-10 pl-8 py-2 text-sm text-stone-900 dark:text-white placeholder:text-stone-400 focus:border-amber-500 focus:ring-amber-500 transition-colors"
                            placeholder="🔎 ابحث برقم الطلب لمحاسبة الزبون فوراً (مثال: ORD-1042)..."
                        />
                        <button
                            v-if="searchQuery"
                            @click="searchQuery = ''"
                            class="absolute left-3 top-2.5 text-xs text-stone-400 hover:text-stone-600 dark:hover:text-stone-200"
                        >
                            ✕
                        </button>
                    </div>

                    <div v-if="isPrepaid" class="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 text-xs font-bold text-amber-800 dark:text-amber-300 shrink-0">
                        <span>💳 بانتظار المحاسبة:</span>
                        <span
                            class="rounded-full bg-amber-500 text-white px-2 py-0.5 text-xs font-black transition-all"
                            :class="{ 'animate-pulse shadow-md shadow-amber-500/40 ring-2 ring-amber-400': pendingCashierOrdersCount > 0 }"
                        >
                            {{ pendingCashierOrdersCount }}
                        </span>
                    </div>

                    <!-- تقرير إغلاق الوردية Z-Report -->
                    <a
                        href="/admin/orders/shift-report"
                        target="_blank"
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-purple-50 dark:bg-purple-950/40 border border-purple-200 dark:border-purple-800/60 text-xs font-bold text-purple-800 dark:text-purple-300 hover:bg-purple-100 dark:hover:bg-purple-900/60 transition-all select-none cursor-pointer shrink-0"
                        title="طباعة تقرير إغلاق الوردية اليومي (Z-Report) ومطابقة الصندوق"
                    >
                        <span>📊</span>
                        <span>تقرير الوردية (Z)</span>
                    </a>

                    <!-- إبقاء الشاشة مضاءة -->
                    <button
                        v-if="isWakeLockSupported"
                        @click="toggleWakeLock"
                        type="button"
                        :class="[
                            'flex items-center gap-1.5 px-3 py-1.5 rounded-xl border text-xs font-bold transition-all select-none cursor-pointer shrink-0',
                            isWakeLockActive
                                ? 'bg-amber-500/15 text-amber-600 dark:text-amber-400 border-amber-500/40'
                                : 'bg-stone-50 dark:bg-slate-900 text-stone-600 dark:text-slate-400 border-stone-200 dark:border-slate-800'
                        ]"
                        :title="isWakeLockActive ? 'الشاشة لن تنطفئ (مفعل)' : 'انقر لإبقاء الشاشة مضاءة دائماً'"
                    >
                        <span>{{ isWakeLockActive ? '💡 الشاشة نشطة' : '💤 شاشة عادية' }}</span>
                    </button>

                    <!-- زر تفعيل/إيقاف الطباعة التلقائية عند تم التحضير -->
                    <button
                        @click="isAutoPrintActive = !isAutoPrintActive"
                        type="button"
                        :class="[
                            'flex items-center gap-1.5 px-3 py-1.5 rounded-xl border text-xs font-bold transition-all select-none cursor-pointer shrink-0',
                            isAutoPrintActive
                                ? 'bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-500/40'
                                : 'bg-stone-50 dark:bg-slate-900 text-stone-500 dark:text-slate-400 border-stone-200 dark:border-slate-800'
                        ]"
                        :title="isAutoPrintActive ? 'الطباعة التلقائية عند تم التحضير مفعّلة' : 'انقر لتفعيل الطباعة التلقائية عند تم التحضير'"
                    >
                        <span>🖨️</span>
                        <span>{{ isAutoPrintActive ? 'طباعة تلقائية: مفعّلة' : 'طباعة تلقائية: متوقفة' }}</span>
                    </button>

                    <button
                        @click="soundAlerts.playCashierAlert()"
                        type="button"
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 text-xs font-bold text-emerald-800 dark:text-emerald-300 hover:bg-emerald-100 dark:hover:bg-emerald-900/60 transition-all select-none cursor-pointer shrink-0"
                        title="التنبيه الصوتي مفعّل، انقر لتجربة نغمة الكاشير الصاخبة"
                    >
                        <span>🔊</span>
                        <span>صوت الكاشير</span>
                    </button>
                </div>

                <div v-if="filteredOrders.length === 0" class="rounded-2xl bg-white dark:bg-[#0a0f1d] p-8 text-center text-gray-500 dark:text-gray-400 shadow-xs border border-dashed border-gray-300 dark:border-slate-800">
                    {{ searchQuery ? 'لم يتم العثور على أي طلب يطابق بحثك' : 'لا يوجد طلبات حالياً' }}
                </div>
                <article
                    v-for="order in filteredOrders"
                    :key="order.id"
                    class="rounded-2xl bg-white dark:bg-[#0a0f1d] p-4 shadow-xs border transition-all duration-300"
                    :class="[
                        order.status === 'pending' && !order.paid_at
                            ? 'border-amber-400 dark:border-amber-500/80 ring-2 ring-amber-400/40 dark:ring-amber-500/30 shadow-lg shadow-amber-500/10'
                            : 'border-slate-100 dark:border-slate-800'
                    ]"
                >
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2">
                                <span v-if="order.status === 'pending' && !order.paid_at" class="relative flex h-2.5 w-2.5" title="طلب بانتظار المحاسبة الفورية">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-amber-500"></span>
                                </span>
                                <p class="font-black text-gray-900 dark:text-white text-base">{{ order.order_number }}</p>
                                <span class="text-xs font-bold text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/50 px-2 py-0.5 rounded-full border border-amber-200/50 dark:border-amber-800/40">
                                    {{ (order.service_type ?? 'dine_in') === 'takeaway' ? '🥡 سفري' : (order.table?.name ? `🍽️ محلي (${order.table.name})` : '🍽️ محلي') }}
                                </span>
                                <span v-if="order.pickup_window" class="text-xs font-bold text-sky-700 dark:text-sky-300 bg-sky-50 dark:bg-sky-950/60 px-2 py-0.5 rounded-full border border-sky-200 dark:border-sky-800/40">
                                    🏢 نافذة {{ order.pickup_window }}
                                </span>
                                <span v-if="order.paid_at" class="text-xs font-bold text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/60 px-2 py-0.5 rounded-full border border-emerald-200 dark:border-emerald-800/40">
                                    ✓ مدفوع
                                </span>
                            </div>
                            <p class="text-sm font-semibold text-stone-600 dark:text-stone-300 mt-1">
                                القيمة: <span class="font-extrabold text-amber-600 dark:text-amber-400">{{ order.total }} {{ $page.props.auth.restaurant?.currency }}</span>
                            </p>
                        </div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="rounded-full bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 border border-amber-200/50 dark:border-amber-700/50 px-3 py-1 text-xs font-bold">
                                {{ order.status_label }}
                            </span>

                            <!-- دور المحاسب (الكاشير): تحديد أن الطلب تم دفعه ليظهر لنوافذ المطبخ -->
                            <!-- إذا لم يتم دفع الطلب بعد: يظهر زر الدفع فقط -->
                            <button
                                v-if="order.status === 'pending' && !order.paid_at"
                                type="button"
                                :disabled="updatingIds.has(order.id)"
                                class="rounded-xl bg-emerald-600 hover:bg-emerald-700 px-3 py-2 text-xs font-bold text-white shadow-xs disabled:opacity-50 transition-all flex items-center gap-1 cursor-pointer"
                                @click="updateStatus(order, 'accepted')"
                            >
                                💳 تحديد أن الطلب تم دفعه
                            </button>

                            <!-- الحالات التالية: المحاسب لا يملك أي أزرار لتغيير الحالة، تظهر مؤشرات توضيحية فقط -->
                            <span
                                v-else-if="order.status === 'accepted'"
                                class="rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800/60 px-3 py-1.5 text-xs font-bold flex items-center gap-1"
                            >
                                ⏳ مدفوع • بانتظار استلام المطبخ
                            </span>

                            <span
                                v-else-if="order.status === 'preparing'"
                                class="rounded-xl bg-purple-50 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800/60 px-3 py-1.5 text-xs font-bold flex items-center gap-1"
                            >
                                👨‍🍳 قيد التحضير {{ order.pickup_window ? `(نافذة ${order.pickup_window})` : '' }}
                            </span>

                            <span
                                v-else-if="order.status === 'ready'"
                                class="rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60 px-3 py-1.5 text-xs font-bold flex items-center gap-1"
                            >
                                🔔 جاهز للاستلام {{ order.pickup_window ? `(نافذة ${order.pickup_window})` : '' }}
                            </span>

                            <span
                                v-else-if="order.status === 'served'"
                                class="rounded-xl bg-stone-100 dark:bg-slate-800 text-stone-600 dark:text-stone-300 border border-stone-200 dark:border-slate-700 px-3 py-1.5 text-xs font-bold flex items-center gap-1"
                            >
                                ✓ تم تسليم الطلب
                            </span>

                            <span
                                v-else-if="order.status === 'cancelled'"
                                class="rounded-xl bg-red-50 dark:bg-red-950/60 text-red-700 dark:text-red-300 border border-red-200 dark:border-red-800/60 px-3 py-1.5 text-xs font-bold flex items-center gap-1"
                            >
                                ✕ تم إلغاء الطلب
                            </span>

                            <!-- معاينة الفاتورة الحرارية 80mm -->
                            <button
                                @click="openReceiptModal(order)"
                                type="button"
                                class="rounded-lg bg-stone-100 hover:bg-amber-500/10 hover:text-amber-600 hover:border-amber-300 dark:bg-slate-800 dark:hover:bg-amber-500/15 dark:hover:text-amber-300 dark:hover:border-amber-500/40 px-2.5 py-2 text-xs font-bold text-stone-700 dark:text-stone-200 border border-stone-200 dark:border-slate-700 transition-all flex items-center gap-1.5 cursor-pointer shadow-2xs select-none"
                                title="معاينة إيصال الفاتورة الحرارية (80mm)"
                            >
                                <span>🧾</span>
                                <span class="hidden sm:inline">معاينة الإيصال</span>
                            </button>

                            <!-- مشاركة الفاتورة عبر واتساب -->
                            <button
                                @click="shareOnWhatsApp(order)"
                                type="button"
                                class="rounded-lg bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-100 dark:hover:bg-emerald-900/60 border border-emerald-200 dark:border-emerald-800/50 px-2.5 py-2 text-xs font-bold transition-colors flex items-center gap-1 cursor-pointer"
                                title="إرسال الفاتورة وتتبع الطلب للزبون عبر واتساب"
                            >
                                <span>💬</span>
                                <span class="hidden sm:inline">واتساب</span>
                            </button>

                            <!-- طباعة الفاتورة (فقط عند مرحلة التسليم: جاهز أو مسلّم) -->
                            <a
                                v-if="['ready', 'served'].includes(order.status)"
                                :href="`/admin/orders/${order.id}/print`"
                                target="_blank"
                                class="rounded-lg bg-stone-100 dark:bg-slate-800 px-3 py-2 text-sm text-stone-700 dark:text-stone-200 hover:bg-stone-200 dark:hover:bg-slate-700 transition-colors flex items-center gap-1 border border-stone-200 dark:border-slate-700"
                                title="طباعة الفاتورة"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                                <span class="hidden sm:inline">طباعة</span>
                            </a>
                        </div>
                    </div>

                    <!-- ملاحظة الزبون على الطلب -->
                    <div v-if="order.customer_note" class="mt-3 rounded-xl bg-amber-50 dark:bg-amber-950/40 p-2.5 border border-amber-200 dark:border-amber-800/60 text-amber-900 dark:text-amber-200 text-xs shadow-xs">
                        <div class="flex items-center gap-1 font-bold mb-1 text-amber-800 dark:text-amber-300">
                            <span>📝 ملاحظة الزبون على الطلب:</span>
                        </div>
                        <p class="font-bold text-amber-950 dark:text-amber-100 pr-4 bg-amber-100/50 dark:bg-amber-900/40 p-1.5 rounded-lg border border-amber-200/50 dark:border-amber-800/40">{{ order.customer_note }}</p>
                    </div>

                    <!-- أصناف الطلب مع الملاحظات -->
                    <div v-if="order.items && order.items.length > 0" class="mt-3 pt-2.5 border-t border-stone-100 dark:border-slate-800 space-y-1.5">
                        <div v-for="item in order.items" :key="item.id" class="text-xs text-stone-700 dark:text-stone-300 py-0.5">
                            <div class="flex items-center gap-1.5">
                                <span class="font-bold text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/60 px-1.5 py-0.5 rounded border border-amber-200/40 dark:border-amber-800/40">{{ item.quantity }}x</span>
                                <span class="font-medium text-stone-800 dark:text-stone-200">{{ item.item_name_snapshot }}</span>
                            </div>
                            <div v-if="item.note" class="mt-0.5 flex items-start gap-1 mr-6 text-[11px] text-amber-800 dark:text-amber-300 bg-amber-50 dark:bg-amber-950/40 rounded px-2 py-0.5 border border-amber-200/60 dark:border-amber-800/50 inline-flex">
                                <span class="font-bold shrink-0">↳ ملاحظة:</span>
                                <span>{{ item.note }}</span>
                            </div>
                        </div>
                    </div>
                </article>
            </div>
        </div>

        <!-- نافذة معاينة الفاتورة الحرارية الواقعية (80mm Thermal Receipt Modal) -->
        <Teleport to="body">
            <div
                v-if="showReceiptModal && receiptOrder"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm transition-opacity duration-300 overflow-y-auto"
                dir="rtl"
                @click.self="closeReceiptModal"
            >
                <div class="relative w-full max-w-[390px] my-auto flex flex-col items-center animate-in fade-in zoom-in-95 duration-200">
                    <!-- أزرار الإجراءات السريعة العلوية -->
                    <div class="w-full flex items-center justify-between mb-2 px-1 text-white text-xs">
                        <span class="font-bold flex items-center gap-1.5 text-amber-300">
                            <span>🧾</span>
                            <span>معاينة إيصال طابعة 80mm</span>
                        </span>
                        <button
                            type="button"
                            @click="closeReceiptModal"
                            class="p-1.5 rounded-full bg-white/20 hover:bg-white/30 text-white transition cursor-pointer"
                            title="إغلاق النافذة (Esc)"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- ورقة الإيصال الحراري الواقعية -->
                    <div class="relative w-full bg-[#fbfbf8] text-stone-900 rounded-lg shadow-2xl p-5 border-t-8 border-dashed border-stone-300 select-text font-mono text-xs overflow-hidden">
                        <!-- ترويسة المطعم -->
                        <div class="text-center pb-3 border-b border-dashed border-stone-300">
                            <h3 class="text-lg font-black tracking-tight text-stone-950 font-sans">
                                {{ authRestaurant?.name || 'مطعم قلعة حلب' }}
                            </h3>
                            <p class="text-[11px] text-stone-600 font-sans mt-0.5">
                                منظومة ضيافة سحابية متطورة • تدلّل
                            </p>
                            <div class="mt-2.5 inline-block border-2 border-stone-900 rounded-md px-3 py-1 bg-stone-100">
                                <span class="text-[10px] block font-bold text-stone-600">رقم الطلب</span>
                                <span class="text-xl font-black tracking-wider text-stone-950">{{ receiptOrder.order_number }}</span>
                            </div>
                        </div>

                        <!-- تفاصيل الجلسة والخدمة -->
                        <div class="py-2.5 border-b border-dashed border-stone-300 space-y-1 text-[11px]">
                            <div class="flex justify-between">
                                <span class="text-stone-500">التاريخ والوقت:</span>
                                <span class="font-bold">{{ formatReceiptTime(receiptOrder.created_at) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-stone-500">نوع الطلب:</span>
                                <span class="font-bold">
                                    {{ receiptOrder.service_type === 'takeaway' ? '🥡 سفري' : (receiptOrder.table?.name ? `🍽️ محلي (${receiptOrder.table.name})` : '🍽️ محلي') }}
                                    <span v-if="receiptOrder.pickup_window" class="text-stone-700"> - نافذة {{ receiptOrder.pickup_window }}</span>
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-stone-500">حالة الدفع:</span>
                                <span class="font-bold" :class="receiptOrder.paid_at ? 'text-emerald-700' : 'text-amber-700'">
                                    {{ receiptOrder.paid_at ? '✓ تم الدفع' : '⏳ بانتظار المحاسبة' }}
                                </span>
                            </div>
                        </div>

                        <!-- جدول الأصناف -->
                        <div class="py-3 border-b border-dashed border-stone-300">
                            <div class="flex justify-between font-bold text-stone-700 pb-1.5 border-b border-stone-200 text-[11px]">
                                <span>الصنف / الكمية</span>
                                <span>الإجمالي</span>
                            </div>
                            <div class="divide-y divide-stone-100">
                                <div
                                    v-for="item in (receiptOrder.items ?? [])"
                                    :key="item.id"
                                    class="py-1.5"
                                >
                                    <div class="flex justify-between items-start gap-2">
                                        <div class="min-w-0">
                                            <span class="font-black text-stone-950 ml-1">{{ item.quantity }}x</span>
                                            <span class="font-semibold text-stone-800">{{ item.item_name_snapshot }}</span>
                                        </div>
                                        <span class="font-black text-stone-900 shrink-0">
                                            {{ item.subtotal }} {{ authRestaurant?.currency || 'ل.س' }}
                                        </span>
                                    </div>
                                    <div v-if="item.note" class="text-[10px] text-stone-500 mt-0.5 pr-4 italic">
                                        ↳ {{ item.note }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- الإجماليات المالية -->
                        <div class="py-3 border-b-2 border-dashed border-stone-400 space-y-1.5">
                            <div v-if="receiptOrder.subtotal" class="flex justify-between text-stone-600 text-[11px]">
                                <span>المجموع الفرعي:</span>
                                <span>{{ receiptOrder.subtotal }} {{ authRestaurant?.currency || 'ل.س' }}</span>
                            </div>
                            <div class="flex justify-between items-center text-sm font-black text-stone-950 pt-1">
                                <span class="text-base">المجموع النهائي:</span>
                                <span class="text-lg bg-stone-100 px-2 py-0.5 rounded border border-stone-300">
                                    {{ receiptOrder.total }} {{ authRestaurant?.currency || 'ل.س' }}
                                </span>
                            </div>
                        </div>

                        <!-- ملاحظة الزبون إن وجدت -->
                        <div v-if="receiptOrder.customer_note" class="py-2 border-b border-dashed border-stone-300 text-[10px] text-stone-600">
                            <span class="font-bold text-stone-800">ملاحظة الزبون:</span>
                            <p class="mt-0.5 italic bg-amber-50/60 p-1 rounded">{{ receiptOrder.customer_note }}</p>
                        </div>

                        <!-- كود باركود محاكى للطابعات الحرارية -->
                        <div class="py-3 text-center">
                            <div class="flex items-center justify-center gap-[2px] h-8 max-w-[220px] mx-auto opacity-80">
                                <div class="w-[2px] h-full bg-stone-900"></div>
                                <div class="w-[1px] h-full bg-stone-900"></div>
                                <div class="w-[3px] h-full bg-stone-900"></div>
                                <div class="w-[1px] h-full bg-stone-900"></div>
                                <div class="w-[2px] h-full bg-stone-900"></div>
                                <div class="w-[4px] h-full bg-stone-900"></div>
                                <div class="w-[1px] h-full bg-stone-900"></div>
                                <div class="w-[3px] h-full bg-stone-900"></div>
                                <div class="w-[2px] h-full bg-stone-900"></div>
                                <div class="w-[1px] h-full bg-stone-900"></div>
                                <div class="w-[3px] h-full bg-stone-900"></div>
                                <div class="w-[2px] h-full bg-stone-900"></div>
                                <div class="w-[4px] h-full bg-stone-900"></div>
                                <div class="w-[1px] h-full bg-stone-900"></div>
                                <div class="w-[2px] h-full bg-stone-900"></div>
                            </div>
                            <p class="text-[9px] tracking-widest text-stone-500 mt-1 font-mono">{{ receiptOrder.order_number }}</p>
                        </div>

                        <!-- خاتمة الإيصال -->
                        <div class="text-center pt-1 pb-1 text-[11px] font-sans text-stone-600">
                            <p class="font-bold">شكراً لزيارتكم الكريمة ✨</p>
                            <p class="text-[10px] text-stone-500">نسعد دائماً بخدمتكم وتلبية طلباتكم</p>
                        </div>

                        <!-- الحافة السفلية المشرشرة -->
                        <div class="absolute -bottom-1 left-0 right-0 h-2 bg-transparent border-b-8 border-dashed border-stone-300"></div>
                    </div>

                    <!-- أزرار التحكم والطباعة أسفل الإيصال -->
                    <div class="w-full mt-3 flex items-center gap-2">
                        <button
                            type="button"
                            @click="printReceipt(receiptOrder.id)"
                            class="flex-1 flex items-center justify-center gap-2 py-2.5 px-4 rounded-xl bg-amber-500 hover:bg-amber-600 text-stone-950 font-bold text-xs shadow-lg shadow-amber-500/25 transition active:scale-95 cursor-pointer"
                        >
                            <span>🖨️</span>
                            <span>طباعة الفاتورة الآن</span>
                        </button>
                        <button
                            type="button"
                            @click="shareOnWhatsApp(receiptOrder)"
                            class="flex items-center justify-center gap-1.5 py-2.5 px-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-md transition active:scale-95 cursor-pointer"
                            title="إرسال الفاتورة عبر واتساب"
                        >
                            <span>💬</span>
                            <span class="hidden sm:inline">واتساب</span>
                        </button>
                        <button
                            type="button"
                            @click="closeReceiptModal"
                            class="py-2.5 px-3 rounded-xl bg-stone-800 hover:bg-stone-700 text-stone-300 font-bold text-xs transition active:scale-95 cursor-pointer border border-stone-700"
                        >
                            إغلاق
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
        <NotificationToast />
    </AuthenticatedLayout>
</template>
