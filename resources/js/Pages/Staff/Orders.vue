<script setup lang="ts">
import AdminNav from '@/Components/Admin/AdminNav.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useRestaurantOrders } from '@/composables/useRealtimeOrders';
import { Head, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import axios from 'axios';
import { useToast } from '@/composables/useToast';

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
}

const orders = ref<Order[]>([]);
const page = usePage();
const restaurantId = computed(() => page.props.restaurantId as number | undefined);

async function loadOrders() {
    const { data } = await axios.get('/api/admin/orders');
    orders.value = data.data?.data ?? data.data ?? [];
}

const processingOrderIds = ref<number[]>([]);
const { showToast } = useToast();

async function updateStatus(order: Order, status: string) {
    if (processingOrderIds.value.includes(order.id)) return;
    
    processingOrderIds.value.push(order.id);
    try {
        await axios.patch(`/api/admin/orders/${order.id}/status`, { status });
        showToast('نجاح', 'تم تحديث حالة الطلب بنجاح', 'success');
        await loadOrders();
    } catch (e: any) {
        showToast('خطأ', e.response?.data?.message || 'حدث خطأ أثناء تحديث الحالة', 'error');
    } finally {
        processingOrderIds.value = processingOrderIds.value.filter(id => id !== order.id);
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
        const exists = orders.value.find(o => o.id === newOrder.id);
        if (!exists) {
            orders.value.unshift(newOrder as Order);
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
    <Head title="الطلبات" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">لوحة الطلبات</h2>
        </template>

        <div class="py-8" dir="rtl">
            <div class="mx-auto max-w-5xl space-y-3 px-4">
                <AdminNav />
                <div v-if="orders.length === 0" class="rounded-2xl bg-white p-8 text-center text-gray-500 shadow border border-dashed border-gray-300 mt-4">
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
                                {{ order.table?.name || 'سفري' }} - الإجمالي: {{ order.total }} ل.س
                            </p>
                        </div>
                        
                        <!-- Order Items List -->
                        <div class="w-full mt-2 bg-stone-50 rounded-lg p-3 border border-stone-100" v-if="order.items && order.items.length > 0">
                            <h4 class="text-xs font-bold text-stone-500 mb-2 uppercase">محتويات الطلب:</h4>
                            <ul class="space-y-1">
                                <li v-for="item in order.items" :key="item.id" class="flex justify-between items-center text-sm border-b border-stone-200/50 pb-1 last:border-0 last:pb-0">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-amber-600">{{ item.quantity }}x</span>
                                        <span class="font-medium text-stone-800">{{ item.item_name_snapshot }}</span>
                                    </div>
                                    <span class="text-stone-500 text-xs">{{ item.subtotal }} ل.س</span>
                                </li>
                            </ul>
                        </div>

                        <div class="flex flex-wrap gap-2 pt-4 w-full justify-between items-center border-t border-stone-100 mt-2">
                            <span class="rounded-full bg-amber-100 px-3 py-1 text-sm">
                                {{ order.status_label }}
                            </span>
                            <button
                                v-if="nextStatus[order.status]"
                                type="button"
                                :disabled="processingOrderIds.includes(order.id)"
                                class="rounded-lg bg-amber-600 px-3 py-2 text-sm text-white disabled:opacity-50 transition-all active:scale-95"
                                @click="updateStatus(order, nextStatus[order.status])"
                            >
                                <span v-if="processingOrderIds.includes(order.id)" class="flex items-center gap-2">
                                    <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-dasharray="31.415, 31.415" opacity="0.3"></circle><path d="M12 2C6.477 2 2 6.477 2 12" stroke="currentColor" stroke-width="4" stroke-linecap="round"></path></svg>
                                    جاري التحديث...
                                </span>
                                <span v-else>التالي</span>
                            </button>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
