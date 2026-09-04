<script setup lang="ts">
import AdminNav from '@/Components/Admin/AdminNav.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import NotificationToast from '@/Components/NotificationToast.vue';
import { useRestaurantOrders } from '@/composables/useRealtimeOrders';
import { useToast } from '@/composables/useToast';
import { Head, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import axios from 'axios';

interface Order {
    id: number;
    order_number: string;
    status: string;
    status_label: string;
    total: string;
    table?: { name: string };
}

const orders = ref<Order[]>([]);
const page = usePage();
const restaurantId = computed(() => page.props.restaurantId as number | undefined);
const { showToast } = useToast();

const notificationSound = new Audio('data:audio/wav;base64,UklGRl9vT19XQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YU'); // Very short placeholder, browsers might need a real valid Base64 wav. I will use a simple valid Base64 beep.
const beep = new Audio('data:audio/mp3;base64,//MkxAAAD/wwAAAAAAf88AAAAAAExBTUUzLjEwMKqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqq'); // Fallback placeholder.
function playSound() {
    try {
        // Attempt to play sound, might be blocked by browser auto-play policies until user interacts
        beep.play().catch(() => {});
    } catch(e) {}
}

async function loadOrders() {
    const { data } = await axios.get('/api/admin/orders');
    orders.value = data.data?.data ?? data.data ?? [];
}

async function updateStatus(order: Order, status: string) {
    await axios.patch(`/api/admin/orders/${order.id}/status`, { status });
    // Don't call loadOrders(), the WebSocket event will update it automatically for everyone!
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
                                class="rounded-lg bg-amber-600 px-3 py-2 text-sm text-white"
                                @click="updateStatus(order, nextStatus[order.status])"
                            >
                                التالي
                            </button>
                            <button
                                v-if="['pending', 'accepted'].includes(order.status)"
                                type="button"
                                class="rounded-lg bg-red-100 px-3 py-2 text-sm text-red-700"
                                @click="updateStatus(order, 'cancelled')"
                            >
                                إلغاء
                            </button>
                        </div>
                    </div>
                </article>
            </div>
        </div>
        <NotificationToast />
    </AuthenticatedLayout>
</template>
