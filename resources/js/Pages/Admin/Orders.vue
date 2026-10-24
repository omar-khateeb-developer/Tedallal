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
    total: string;
    table?: { name: string };
    customer_note?: string | null;
    items?: OrderItem[];
}

const orders = ref<Order[]>([]);
const page = usePage();
const restaurantId = computed(() => page.props.restaurantId as number | undefined);
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

async function updateStatus(order: Order, status: string) {
    if (updatingIds.value.has(order.id)) return;
    updatingIds.value.add(order.id);

    try {
        const { data } = await axios.patch(`/api/admin/orders/${order.id}/status`, { status });
        const index = orders.value.findIndex(o => o.id === order.id);
        if (index !== -1) {
            orders.value[index] = data.data ?? { ...order, status };
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
            <h2 class="text-xl font-semibold leading-tight text-gray-800">إدارة الطلبات</h2>
        </template>

        <div class="py-8" dir="rtl">
            <div class="mx-auto max-w-5xl px-4">
                <AdminNav />
            </div>
            <div class="mx-auto max-w-5xl space-y-3 px-4">
                <div v-if="orders.length === 0" class="rounded-2xl bg-white p-8 text-center text-gray-500 shadow border border-dashed border-gray-300">
                    لا يوجد طلبات حالياً
                </div>
                <article
                    v-for="order in orders"
                    :key="order.id"
                    class="rounded-2xl bg-white p-4 shadow"
                >
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="font-bold">{{ order.order_number }}</p>
                            <p class="text-sm text-stone-500">
                                {{ order.table?.name }} — {{ order.total }} {{ $page.props.auth.restaurant?.currency }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-amber-100 px-3 py-1 text-sm">
                                {{ order.status_label }}
                            </span>
                            <button
                                v-if="nextStatus[order.status]"
                                type="button"
                                :disabled="updatingIds.has(order.id)"
                                class="rounded-lg bg-amber-600 px-3 py-2 text-sm text-white disabled:opacity-50 hover:bg-amber-700 transition-colors"
                                @click="updateStatus(order, nextStatus[order.status])"
                            >
                                التالي
                            </button>
                            <a
                                :href="`/admin/orders/${order.id}/print`"
                                target="_blank"
                                class="rounded-lg bg-stone-100 px-3 py-2 text-sm text-stone-700 hover:bg-stone-200 transition-colors flex items-center gap-1 border border-stone-200"
                                title="طباعة الفاتورة"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                                <span class="hidden sm:inline">طباعة</span>
                            </a>
                            <button
                                v-if="['pending', 'accepted'].includes(order.status)"
                                type="button"
                                :disabled="updatingIds.has(order.id)"
                                class="rounded-lg bg-red-100 px-3 py-2 text-sm text-red-700 disabled:opacity-50"
                                @click="updateStatus(order, 'cancelled')"
                            >
                                إلغاء
                            </button>
                        </div>
                    </div>

                    <!-- ملاحظة الزبون على الطلب -->
                    <div v-if="order.customer_note" class="mt-3 rounded-xl bg-amber-50 p-2.5 border border-amber-200 text-amber-900 text-xs shadow-sm">
                        <div class="flex items-center gap-1 font-bold mb-1 text-amber-800">
                            <span>📝 ملاحظة الزبون على الطلب:</span>
                        </div>
                        <p class="font-bold text-amber-950 pr-4 bg-amber-100/50 p-1.5 rounded-lg border border-amber-200/50">{{ order.customer_note }}</p>
                    </div>

                    <!-- أصناف الطلب مع الملاحظات -->
                    <div v-if="order.items && order.items.length > 0" class="mt-3 pt-2.5 border-t border-stone-100 space-y-1.5">
                        <div v-for="item in order.items" :key="item.id" class="text-xs text-stone-700 py-0.5">
                            <div class="flex items-center gap-1.5">
                                <span class="font-bold text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded">{{ item.quantity }}x</span>
                                <span class="font-medium text-stone-800">{{ item.item_name_snapshot }}</span>
                            </div>
                            <div v-if="item.note" class="mt-0.5 flex items-start gap-1 mr-6 text-[11px] text-amber-800 bg-amber-50 rounded px-2 py-0.5 border border-amber-200/60 inline-flex">
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
