<script setup lang="ts">
import CustomerLayout from '@/Layouts/CustomerLayout.vue';
import { Link } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';
import axios from 'axios';

const props = defineProps<{
    restaurant: { currency: string };
    table: { name: string };
    orderNumber: string;
    publicToken: string;
}>();

const order = ref<{
    status: string;
    status_label: string;
    total: string;
    items: Array<{ item_name_snapshot: string; quantity: number; subtotal: string }>;
} | null>(null);

let pollTimer: ReturnType<typeof setInterval> | null = null;

async function loadOrder() {
    const { data } = await axios.get(`/api/public/orders/${props.orderNumber}`);
    order.value = data.data;
}

function subscribeToRealtime() {
    if (!window.Echo) return;

    window.Echo.channel(`order.${props.orderNumber}`).listen(
        '.order.status_changed',
        (payload: { order: typeof order.value }) => {
            if (payload.order) {
                order.value = {
                    status: payload.order.status,
                    status_label: payload.order.status_label,
                    total: payload.order.total,
                    items: payload.order.items ?? order.value?.items ?? [],
                };
            }
        },
    );
}

onMounted(() => {
    loadOrder();
    subscribeToRealtime();
    pollTimer = setInterval(loadOrder, 10000);
});

onUnmounted(() => {
    if (pollTimer) clearInterval(pollTimer);
    if (window.Echo) {
        window.Echo.leave(`order.${props.orderNumber}`);
    }
});
</script>

<template>
    <CustomerLayout title="حالة الطلب">
        <div v-if="!order" class="py-12 text-center text-stone-500">جاري التحميل...</div>

        <div v-else class="space-y-4">
            <div class="rounded-2xl bg-white p-6 text-center shadow-sm">
                <p class="text-sm text-stone-500">رقم الطلب</p>
                <p class="mt-1 text-2xl font-bold tracking-wide">{{ orderNumber }}</p>
                <span
                    class="mt-4 inline-block rounded-full bg-amber-100 px-4 py-2 text-sm font-medium text-amber-800"
                >
                    {{ order.status_label }}
                </span>
            </div>

            <div class="rounded-2xl bg-white p-4 shadow-sm">
                <h2 class="mb-3 font-semibold">تفاصيل الطلب</h2>
                <ul class="space-y-2 text-sm">
                    <li
                        v-for="(item, i) in order.items"
                        :key="i"
                        class="flex justify-between"
                    >
                        <span>{{ item.item_name_snapshot }} × {{ item.quantity }}</span>
                        <span>{{ item.subtotal }} {{ restaurant.currency }}</span>
                    </li>
                </ul>
                <div class="mt-4 flex justify-between border-t pt-3 font-bold">
                    <span>الإجمالي</span>
                    <span>{{ order.total }} {{ restaurant.currency }}</span>
                </div>
            </div>

            <Link
                :href="`/t/${publicToken}`"
                class="block w-full rounded-2xl bg-stone-900 py-4 text-center font-medium text-white"
            >
                طلب المزيد
            </Link>
        </div>
    </CustomerLayout>
</template>
