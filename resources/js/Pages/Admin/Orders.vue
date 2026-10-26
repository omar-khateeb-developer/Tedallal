<script setup lang="ts">
import AdminNav from '@/Components/Admin/AdminNav.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import NotificationToast from '@/Components/NotificationToast.vue';
import { useRestaurantOrders } from '@/composables/useRealtimeOrders';
import { useToast } from '@/composables/useToast';
import { Head, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import axios from 'axios';

interface OrderItem {
    id: number;
    item_name_snapshot: string;
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
    total: string;
    table?: { name: string };
    customer_note?: string | null;
    items?: OrderItem[];
}

const orders = ref<Order[]>([]);
const searchQuery = ref('');
const page = usePage();
const restaurantId = computed(() => page.props.restaurantId as number | undefined);
const authRestaurant = computed(() => (page.props.auth as any)?.restaurant);
const isPrepaid = computed(() => authRestaurant.value?.type === 'prepaid');

const filteredOrders = computed(() => {
    if (!searchQuery.value.trim()) return orders.value;
    const q = searchQuery.value.trim().toLowerCase();
    return orders.value.filter(o => 
        o.order_number.toLowerCase().includes(q) ||
        (o.table?.name && o.table.name.toLowerCase().includes(q))
    );
});

const pendingCashierOrdersCount = computed(() => 
    orders.value.filter(o => o.status === 'pending').length
);

const { showToast } = useToast();

function playSound() {
    try {
        const ctx = new (window.AudioContext || (window as any).webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.type = 'sine';
        osc.frequency.setValueAtTime(880, ctx.currentTime);
        gain.gain.setValueAtTime(0.4, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.5);
        osc.start(ctx.currentTime);
        osc.stop(ctx.currentTime + 0.5);
        setTimeout(() => ctx.close(), 800);
    } catch (e) {}
}

async function loadOrders() {
    const { data } = await axios.get('/api/admin/orders');
    orders.value = data.data?.data ?? data.data ?? [];
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
    } catch (error: any) {
        showToast('خطأ', error.response?.data?.message ?? 'تعذّر تحديث حالة الطلب', 'error');
        await loadOrders();
    } finally {
        updatingIds.value.delete(order.id);
    }
}

const nextStatus: Record<string, string> = {
    pending: 'accepted',
    accepted: 'preparing',
    preparing: 'ready',
    ready: 'served',
};

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
        if (index !== -1) {
            orders.value[index] = updatedOrder as Order;
        } else {
            orders.value.unshift(updatedOrder as Order);
        }
    }
});
</script>

<template>
    <Head title="الطلبات" />

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
                        <span class="rounded-full bg-amber-500 text-white px-2 py-0.5 text-xs font-black">{{ pendingCashierOrdersCount }}</span>
                    </div>
                </div>

                <div v-if="filteredOrders.length === 0" class="rounded-2xl bg-white dark:bg-[#0a0f1d] p-8 text-center text-gray-500 dark:text-gray-400 shadow-xs border border-dashed border-gray-300 dark:border-slate-800">
                    {{ searchQuery ? 'لم يتم العثور على أي طلب يطابق بحثك' : 'لا يوجد طلبات حالياً' }}
                </div>
                <article
                    v-for="order in filteredOrders"
                    :key="order.id"
                    class="rounded-2xl bg-white dark:bg-[#0a0f1d] p-4 shadow-xs border border-slate-100 dark:border-slate-800"
                >
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2">
                                <p class="font-black text-gray-900 dark:text-white text-base">{{ order.order_number }}</p>
                                <span class="text-xs font-bold text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/50 px-2 py-0.5 rounded-full border border-amber-200/50 dark:border-amber-800/40">
                                    {{ (order.service_type ?? 'dine_in') === 'takeaway' ? '🥡 سفري' : (order.table?.name ? `🍽️ محلي (${order.table.name})` : '🍽️ محلي') }}
                                </span>
                                <span v-if="order.pickup_window" class="text-xs font-bold text-sky-700 dark:text-sky-300 bg-sky-50 dark:bg-sky-950/60 px-2 py-0.5 rounded-full border border-sky-200 dark:border-sky-800/40">
                                    🏢 كوة {{ order.pickup_window }}
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

                            <!-- زر تأكيد الكاشير للدفع في نظام الدفع المسبق -->
                            <button
                                v-if="isPrepaid && order.status === 'pending'"
                                type="button"
                                :disabled="updatingIds.has(order.id)"
                                class="rounded-xl bg-emerald-600 hover:bg-emerald-700 px-3 py-2 text-xs font-bold text-white shadow-xs disabled:opacity-50 transition-all flex items-center gap-1 cursor-pointer"
                                @click="updateStatus(order, 'accepted')"
                            >
                                💳 تأكيد استلام المبلغ
                            </button>

                            <!-- زر الانتقال للحالة التالية -->
                            <button
                                v-else-if="nextStatus[order.status]"
                                type="button"
                                :disabled="updatingIds.has(order.id)"
                                class="rounded-lg bg-amber-600 px-3 py-2 text-xs font-bold text-white disabled:opacity-50 hover:bg-amber-700 transition-colors cursor-pointer"
                                @click="updateStatus(order, nextStatus[order.status])"
                            >
                                {{ 
                                    order.status === 'accepted' ? (isPrepaid ? 'بدء تحضير الكوة' : 'بدء التحضير') :
                                    order.status === 'preparing' ? 'جاهز للاستلام 🔔' :
                                    order.status === 'ready' ? 'تم التسليم ✓' : 'التالي'
                                }}
                            </button>
                            <a
                                :href="`/admin/orders/${order.id}/print`"
                                target="_blank"
                                class="rounded-lg bg-stone-100 dark:bg-slate-800 px-3 py-2 text-sm text-stone-700 dark:text-stone-200 hover:bg-stone-200 dark:hover:bg-slate-700 transition-colors flex items-center gap-1 border border-stone-200 dark:border-slate-700"
                                title="طباعة الفاتورة"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                                <span class="hidden sm:inline">طباعة</span>
                            </a>
                            <button
                                v-if="['pending', 'accepted'].includes(order.status)"
                                type="button"
                                :disabled="updatingIds.has(order.id)"
                                class="rounded-lg bg-red-100 dark:bg-red-950/60 px-3 py-2 text-sm text-red-700 dark:text-red-300 hover:bg-red-200 dark:hover:bg-red-900/50 disabled:opacity-50 transition-colors"
                                @click="updateStatus(order, 'cancelled')"
                            >
                                إلغاء
                            </button>
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
        <NotificationToast />
    </AuthenticatedLayout>
</template>
