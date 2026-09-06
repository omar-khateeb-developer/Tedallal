<script setup lang="ts">
import CustomerLayout from '@/Layouts/CustomerLayout.vue';
import { Link } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref, computed } from 'vue';
import axios from 'axios';

const props = defineProps<{
    restaurant: { currency: string; navbar_image?: string | null };
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
    try {
        const { data } = await axios.get(`/api/public/orders/${props.orderNumber}`);
        order.value = data.data;
    } catch (e) {
        console.error(e);
    }
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

const steps = [
    { key: 'pending', label: 'تم الاستلام' },
    { key: 'accepted', label: 'مقبول' },
    { key: 'preparing', label: 'جاري التحضير' },
    { key: 'ready', label: 'جاهز' },
    { key: 'served', label: 'تم التقديم' }
];

const currentStepIndex = computed(() => {
    if (!order.value) return 0;
    if (order.value.status === 'cancelled') return -1;
    const index = steps.findIndex(s => s.key === order.value!.status);
    return index >= 0 ? index : 0;
});
</script>

<template>
    <CustomerLayout title="حالة الطلب" :navbar-image="restaurant.navbar_image">
        <div v-if="!order" class="py-12 flex flex-col items-center justify-center text-stone-500">
            <svg class="h-8 w-8 animate-spin text-amber-600 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p>جاري التحميل...</p>
        </div>

        <div v-else class="space-y-6 pb-20">
            <!-- Order Header Card -->
            <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-stone-100 text-center relative overflow-hidden">
                <div class="absolute -right-4 -top-4 h-24 w-24 rounded-full bg-amber-50 opacity-50"></div>
                <div class="absolute -left-4 -bottom-4 h-16 w-16 rounded-full bg-stone-50 opacity-50"></div>
                
                <p class="text-sm font-medium text-stone-500 relative z-10">رقم الطلب</p>
                <p class="mt-1 text-3xl font-black tracking-wider text-stone-800 relative z-10">{{ orderNumber }}</p>
                
                <div v-if="order.status === 'cancelled'" class="mt-4 inline-flex items-center gap-2 rounded-full bg-red-100 px-4 py-2 text-sm font-bold text-red-700 relative z-10">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    تم إلغاء الطلب
                </div>
            </div>

            <!-- Tracker Stepper (Only show if not cancelled) -->
            <div v-if="order.status !== 'cancelled'" class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-stone-100">
                <h3 class="font-bold text-lg mb-6 text-stone-800">تتبع الطلب</h3>
                
                <div class="relative pl-2 pr-4 space-y-8">
                    <!-- Vertical Line -->
                    <div class="absolute right-[23px] top-4 bottom-4 w-[2px] bg-stone-100 rounded-full"></div>
                    <!-- Active Progress Line -->
                    <div class="absolute right-[23px] top-4 w-[2px] bg-amber-500 rounded-full transition-all duration-700 ease-in-out"
                         :style="{ height: currentStepIndex > 0 ? `calc(${currentStepIndex * (100 / (steps.length - 1))}% - 10px)` : '0%' }"></div>

                    <div v-for="(step, index) in steps" :key="index" class="relative z-10 flex items-center gap-4">
                        <!-- Step Indicator -->
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full transition-colors duration-500"
                             :class="[
                                 index < currentStepIndex ? 'bg-amber-500 text-white shadow-md' : '',
                                 index === currentStepIndex ? 'bg-amber-100 ring-4 ring-amber-50 text-amber-600 shadow-sm' : '',
                                 index > currentStepIndex ? 'bg-white ring-2 ring-stone-200 text-stone-300' : ''
                             ]">
                            
                            <!-- Check Icon for Completed -->
                            <svg v-if="index < currentStepIndex" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                            </svg>
                            <!-- Dot for Current/Future -->
                            <div v-else class="h-2.5 w-2.5 rounded-full" 
                                 :class="index === currentStepIndex ? 'bg-amber-500 animate-pulse' : 'bg-stone-300'">
                            </div>
                        </div>

                        <!-- Step Text -->
                        <div class="flex-1" :class="index <= currentStepIndex ? 'opacity-100' : 'opacity-40'">
                            <p class="font-bold transition-colors" 
                               :class="index === currentStepIndex ? 'text-amber-600 text-lg' : 'text-stone-800'">
                                {{ step.label }}
                            </p>
                            <p v-if="index === currentStepIndex" class="text-xs text-stone-500 mt-0.5">
                                جاري معالجة طلبك الآن...
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Details -->
            <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-stone-100">
                <h3 class="mb-4 font-bold text-lg text-stone-800">تفاصيل الفاتورة</h3>
                <ul class="space-y-3">
                    <li v-for="(item, i) in order.items" :key="i" class="flex justify-between items-center text-sm border-b border-stone-100 pb-3 last:border-0 last:pb-0">
                        <div class="flex items-center gap-3">
                            <span class="flex h-6 w-6 items-center justify-center rounded-md bg-stone-100 font-bold text-stone-600">{{ item.quantity }}</span>
                            <span class="font-medium text-stone-800">{{ item.item_name_snapshot }}</span>
                        </div>
                        <span class="font-semibold text-stone-600">{{ item.subtotal }} {{ restaurant.currency }}</span>
                    </li>
                </ul>
                <div class="mt-4 flex justify-between rounded-2xl bg-stone-50 p-4 font-bold">
                    <span class="text-stone-600">المبلغ الإجمالي</span>
                    <span class="text-amber-600 text-lg">{{ order.total }} {{ restaurant.currency }}</span>
                </div>
            </div>

            <!-- Sticky Back Button -->
            <div class="fixed inset-x-0 bottom-0 z-30 bg-gradient-to-t from-white via-white to-transparent pt-8 pb-4 px-4">
                <div class="mx-auto max-w-lg">
                    <Link
                        :href="`/t/${publicToken}`"
                        class="flex w-full items-center justify-center gap-2 rounded-2xl bg-stone-900 py-4 font-bold text-white shadow-lg transition active:scale-95"
                    >
                        <span>العودة لقائمة الطعام</span>
                        <svg class="h-5 w-5 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                    </Link>
                </div>
            </div>
        </div>
    </CustomerLayout>
</template>
