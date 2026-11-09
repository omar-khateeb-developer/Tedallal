<script setup lang="ts">
import CustomerLayout from '@/Layouts/CustomerLayout.vue';
import { Link } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref, computed } from 'vue';
import axios from 'axios';
import { soundAlerts } from '@/utils/soundAlerts';

const props = defineProps<{
    restaurant: {
        name: string;
        currency: string;
        navbar_image?: string | null;
        logo?: string | null;
    };
    table: { name: string };
    orderNumber: string;
    publicToken: string;
    wheelToken?: string | null;
    isWheelClaimed?: boolean;
    initialOrder?: any;
    sessionToken?: string | null;
}>();

const order = ref<{
    status: string;
    status_label: string;
    total: string;
    customer_note?: string;
    service_type?: string;
    pickup_window?: number | null;
    paid_at?: string | null;
    restaurant_type?: string;
    windows_count?: number;
    items: Array<{ item_name_snapshot: string; quantity: number; subtotal: string; note?: string }>;
} | null>(props.initialOrder || null);

let pollTimer: ReturnType<typeof setInterval> | null = null;

const shortOrderNumber = computed(() => {
    if (!props.orderNumber) return '';
    const parts = props.orderNumber.split('-');
    const last = parts[parts.length - 1];
    const num = parseInt(last, 10);
    return isNaN(num) ? props.orderNumber : String(num);
});

function formatPrice(price: string | number) {
    if (price === null || price === undefined) return '0 ل.س';
    const cleanStr = String(price).replace(/,/g, '');
    const num = parseFloat(cleanStr);
    const intVal = isNaN(num) ? 0 : Math.round(num);
    const curr = (!props.restaurant.currency || props.restaurant.currency.toUpperCase() === 'SYP') ? 'ل.س' : props.restaurant.currency;
    return `${intVal} ${curr}`;
}

function notifyStatusChange(oldStatus?: string, newStatus?: string) {
    if (!oldStatus || !newStatus || oldStatus === newStatus) return;

    if (newStatus === 'ready') {
        soundAlerts.playCustomerAlert('ready');
    } else if (newStatus === 'preparing' || newStatus === 'accepted' || newStatus === 'served') {
        soundAlerts.playCustomerAlert('status');
    }
}

async function loadOrder() {
    try {
        const headers: Record<string, string> = {};
        if (props.sessionToken) {
            headers['X-Session-Token'] = props.sessionToken;
        }
        const { data } = await axios.get(`/api/public/orders/${props.orderNumber}`, { headers });
        const oldStatus = order.value?.status;
        order.value = data.data;
        if (oldStatus && data.data?.status) {
            notifyStatusChange(oldStatus, data.data.status);
        }
    } catch (e) {
        console.error(e);
    }
}

function subscribeToRealtime() {
    if (!window.Echo) return;

    window.Echo.channel(`order.${props.orderNumber}`).listen(
        '.order.status_changed',
        (payload: { order: any }) => {
            if (payload.order) {
                const oldStatus = order.value?.status;
                const newStatus = payload.order.status;
                order.value = {
                    ...order.value,
                    ...payload.order,
                };
                notifyStatusChange(oldStatus, newStatus);
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

const isPrepaid = computed(() => order.value?.restaurant_type === 'prepaid');

const steps = computed(() => {
    if (isPrepaid.value) {
        return [
            { key: 'pending', label: 'بانتظار الدفع', desc: 'يرجى التوجه للكاشير و اخباره برقم طلبك لتدفع الفاتورة ليتم تحضير طلبك' },
            { key: 'accepted', label: 'تم الدفع', desc: 'تم استلام الدفع لدى الكاشير وبانتظار استلام إحدى النوافذ للطلب.' },
            { 
                key: 'preparing', 
                label: order.value?.pickup_window ? `قيد التحضير (نافذة ${order.value.pickup_window})` : 'قيد التحضير',
                desc: order.value?.pickup_window ? `تم استلام طلبك من قِبَل النافذة رقم ${order.value.pickup_window}` : 'جاري التحضير في المطبخ...'
            },
            { 
                key: 'ready', 
                label: order.value?.pickup_window ? `جاهز للاستلام (نافذة ${order.value.pickup_window})` : 'جاهز للاستلام',
                desc: order.value?.pickup_window ? `تفضل بالتوجه إلى النافذة رقم ${order.value.pickup_window} للاستلام!` : 'طلبك جاهز للاستلام.'
            },
            { key: 'served', label: 'تم التسليم', desc: 'نتمنى لك وجبة شهية ويوم سعيد!' }
        ];
    }

    return [
        { key: 'pending', label: 'تم الاستلام', desc: 'تم استلام طلبك وجاري إرساله للمطبخ.' },
        { key: 'accepted', label: 'مقبول', desc: 'تم قبول الطلب وبدأ تجهيزه في المطبخ.' },
        { key: 'preparing', label: 'جاري التحضير', desc: 'طلبك قيد التحضير الآن...' },
        { key: 'ready', label: 'جاهز', desc: 'طلبك جاهز وسيقدم لك حالاً.' },
        { key: 'served', label: 'تم التقديم', desc: 'بالهناء والشفاء!' }
    ];
});

const currentStepIndex = computed(() => {
    if (!order.value) return 0;
    if (order.value.status === 'cancelled') return -1;
    const index = steps.value.findIndex(s => s.key === order.value!.status);
    return index >= 0 ? index : 0;
});
</script>

<template>
    <CustomerLayout :navbar-image="restaurant.navbar_image" :logo="restaurant.logo">
        <template #title>
            <div class="flex flex-col min-w-0">
                <h1 class="text-base sm:text-lg font-black text-stone-900 dark:text-white truncate leading-tight">
                    {{ restaurant.name }}
                </h1>
                <p class="text-xs font-semibold text-stone-500 dark:text-amber-400/90 flex items-center gap-1.5 mt-0.5 truncate">
                    <span class="inline-block w-2 h-2 rounded-full bg-emerald-500 animate-pulse shrink-0"></span>
                    <span>متابعة حالة الطلب</span>
                    <span class="text-stone-300 dark:text-slate-700">·</span>
                    <span>{{ table.name }}</span>
                </p>
            </div>
        </template>
        <template #actions>
            <div class="flex items-center gap-2">
                <button
                    @click="soundAlerts.playCustomerAlert('ready')"
                    type="button"
                    class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 text-xs font-bold hover:bg-amber-500/20 active:scale-95 transition-all select-none cursor-pointer"
                    title="التنبيه الصوتي مفعّل، انقر لتجربة نغمة جاهزية الطلب"
                >
                    <span>🔔</span>
                    <span class="text-[11px] hidden xs:inline">تنبيه صوتي</span>
                </button>

                <div v-if="order" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-gradient-to-r from-amber-500 to-amber-400 text-stone-950 font-black text-xs shadow-md border border-amber-300 select-none">
                    <span class="text-[10px] font-bold opacity-80">طلب</span>
                    <span class="text-base font-mono tracking-tight font-black">#{{ shortOrderNumber }}</span>
                </div>
            </div>
        </template>
        <div v-if="!order" class="py-12 flex flex-col items-center justify-center text-stone-500">
            <svg class="h-8 w-8 animate-spin text-amber-600 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p>جاري التحميل...</p>
        </div>

        <div v-else class="space-y-5 pb-20">
            <!-- تذكرة رقم الطلب المختصر المميز لهذا اليوم (واضحة وعريضة للكاشير عند الدفع) -->
            <div class="rounded-3xl bg-gradient-to-br from-amber-400 via-amber-500 to-amber-600 dark:from-amber-500 dark:via-amber-600 dark:to-amber-700 text-stone-950 p-6 sm:p-7 text-center shadow-2xl relative overflow-hidden border-2 border-amber-300 select-none">
                <!-- لمسات إضاءة خلفية -->
                <div class="absolute -right-8 -top-8 w-32 h-32 rounded-full bg-white/20 blur-xl pointer-events-none"></div>
                <div class="absolute -left-8 -bottom-8 w-32 h-32 rounded-full bg-black/15 blur-xl pointer-events-none"></div>

                <!-- شارة التوجيه للكاشير -->
                <div class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-full bg-stone-950/15 backdrop-blur-xs text-stone-950 font-black text-xs sm:text-sm tracking-wide mb-1 border border-stone-950/15">
                    <span class="text-base">📢</span>
                    <span>رقم طلبك اليوم (اذكره للكاشير عند الدفع):</span>
                </div>

                <!-- رقم الطلب المختصر الضخم والعريض جداً -->
                <div class="text-7xl sm:text-8xl font-black font-mono tracking-tight text-stone-950 my-1 drop-shadow-sm select-all">
                    #{{ shortOrderNumber }}
                </div>

                <!-- إشعار الدفع المسبق التنبيهي إن كان الطلب قيد انتظار الدفع -->
                <div v-if="isPrepaid && order.status === 'pending'" class="mt-3 inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-2xl bg-stone-950 text-amber-300 text-xs sm:text-sm font-black shadow-xl animate-pulse w-full max-w-md mx-auto">
                    <span>💳</span>
                    <span>توجّه للكاشير واذكر رقم <span class="font-mono underline text-white text-base">#{{ shortOrderNumber }}</span> لدفع الفاتورة وبدء التحضير</span>
                </div>

                <p class="text-[11px] font-bold text-stone-950/75 mt-2.5">
                    الرمز المرجعي للطلب: <span class="font-mono font-black">{{ orderNumber }}</span>
                </p>
            </div>

            <!-- بطاقة إعلان جاهزية الطلب ونافذة الاستلام -->
            <div v-if="order.status === 'ready' && order.pickup_window" class="rounded-3xl bg-gradient-to-r from-emerald-600 to-teal-600 text-white p-6 text-center shadow-xl shadow-emerald-500/20">
                <span class="inline-block px-3 py-1 bg-white/20 rounded-full text-xs font-bold tracking-wider mb-2">🎉 طلبك جاهز الآن للاستلام</span>
                <h3 class="text-xl font-bold">تفضل بالتوجه إلى النافذة رقم</h3>
                <div class="mt-3 inline-flex items-center justify-center gap-3 bg-white text-emerald-950 rounded-2xl px-6 py-2.5 shadow-lg">
                    <span class="text-xs font-bold text-emerald-700">نافذة رقم</span>
                    <span class="text-3xl font-black text-emerald-600">{{ order.pickup_window }}</span>
                </div>
            </div>

            <!-- بطاقة إعلام رقم النافذة أثناء التحضير -->
            <div v-else-if="order.status === 'preparing' && order.pickup_window" class="rounded-3xl bg-sky-50 dark:bg-sky-950/40 border-2 border-sky-300 dark:border-sky-800 p-5 text-center shadow-md">
                <div class="text-2xl mb-1">👨‍🍳</div>
                <h4 class="text-base font-extrabold text-sky-800 dark:text-sky-300">
                    تم استلام طلبك من قِبَل النافذة رقم {{ order.pickup_window }}
                </h4>
                <p class="text-xs text-sky-600 dark:text-sky-400 mt-1">جاري تجهيز طلبك الآن بعناية، ويظهر أدناه تتبع حالة طلبك اللحظية.</p>
            </div>

            <!-- Order Header Card -->
            <div class="rounded-3xl bg-white dark:bg-[#0a0f1d] p-6 shadow-xs ring-1 ring-stone-100 dark:ring-slate-800 text-center relative overflow-hidden transition-colors">
                <div class="absolute -right-4 -top-4 h-24 w-24 rounded-full bg-amber-50 dark:bg-amber-950/20 opacity-50"></div>
                <div class="absolute -left-4 -bottom-4 h-16 w-16 rounded-full bg-stone-50 dark:bg-slate-900/30 opacity-50"></div>

                <div class="mb-2 relative z-10">
                    <span class="inline-flex items-center gap-1.5 text-xs font-bold text-amber-800 dark:text-amber-300 bg-amber-50 dark:bg-amber-950/40 px-3 py-1 rounded-full border border-amber-200/60 dark:border-amber-800/50 shadow-2xs">
                        <span>🏪</span>
                        <span>{{ restaurant.name }}</span>
                    </span>
                </div>
                
                <p class="text-xs font-bold text-amber-700 dark:text-amber-400 relative z-10">رقم الطلب اليوم (للكاشير)</p>
                <p class="mt-1 text-4xl sm:text-5xl font-black tracking-tight text-amber-600 dark:text-amber-400 font-mono relative z-10">#{{ shortOrderNumber }}</p>
                <p class="mt-1 text-[11px] font-medium text-stone-400 dark:text-slate-500 font-mono relative z-10">الرمز المرجعي: {{ orderNumber }}</p>
                
                <!-- نوع الطلب والنافذة -->
                <div class="mt-3 flex items-center justify-center gap-2 flex-wrap relative z-10">
                    <span class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-bold bg-stone-100 dark:bg-slate-800 text-stone-700 dark:text-slate-300">
                        {{ (order.service_type ?? 'dine_in') === 'takeaway' ? '🥡 سفري / استلام' : `🍽️ محلي (${table.name})` }}
                    </span>
                    <span v-if="order.pickup_window" class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-bold bg-sky-100 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300">
                        🏢 نافذة رقم {{ order.pickup_window }}
                    </span>
                    <span v-if="order.paid_at" class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-bold bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300">
                        ✓ مدفوع للكاشير
                    </span>
                </div>

                <div v-if="order.status === 'cancelled'" class="mt-4 inline-flex items-center gap-2 rounded-full bg-red-100 dark:bg-red-950/60 px-4 py-2 text-sm font-bold text-red-700 dark:text-red-300 relative z-10">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    تم إلغاء الطلب
                </div>
            </div>

            <!-- Tracker Stepper (Only show if not cancelled) -->
            <div v-if="order.status !== 'cancelled'" class="rounded-3xl bg-white dark:bg-[#0a0f1d] p-6 shadow-xs ring-1 ring-stone-100 dark:ring-slate-800 transition-colors">
                <h3 class="font-bold text-lg mb-6 text-stone-800 dark:text-white">تتبع حالة الطلب</h3>
                
                <div class="relative pl-2 pr-4 space-y-8">
                    <!-- Vertical Line -->
                    <div class="absolute right-[23px] top-4 bottom-4 w-[2px] bg-stone-100 dark:bg-slate-800 rounded-full"></div>
                    <!-- Active Progress Line -->
                    <div class="absolute right-[23px] top-4 w-[2px] bg-amber-500 rounded-full transition-all duration-700 ease-in-out"
                         :style="{ height: currentStepIndex > 0 ? `calc(${currentStepIndex * (100 / (steps.length - 1))}% - 10px)` : '0%' }"></div>

                    <div v-for="(step, index) in steps" :key="index" class="relative z-10 flex items-center gap-4">
                        <!-- Step Indicator -->
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full transition-colors duration-500"
                             :class="[
                                 index < currentStepIndex ? 'bg-amber-500 text-white shadow-md' : '',
                                 index === currentStepIndex ? 'bg-amber-100 dark:bg-amber-950/60 ring-4 ring-amber-50 dark:ring-amber-900/30 text-amber-600 dark:text-amber-400 shadow-2xs' : '',
                                 index > currentStepIndex ? 'bg-white dark:bg-slate-800 ring-2 ring-stone-200 dark:ring-slate-700 text-stone-300 dark:text-slate-600' : ''
                             ]">
                            
                            <!-- Check Icon for Completed -->
                            <svg v-if="index < currentStepIndex" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                            </svg>
                            <!-- Dot for Current/Future -->
                            <div v-else class="h-2.5 w-2.5 rounded-full" 
                                 :class="index === currentStepIndex ? 'bg-amber-500 animate-pulse' : 'bg-stone-300 dark:bg-slate-600'">
                            </div>
                        </div>

                        <!-- Step Text -->
                        <div class="flex-1" :class="index <= currentStepIndex ? 'opacity-100' : 'opacity-40'">
                            <p class="font-bold transition-colors" 
                                :class="index === currentStepIndex ? 'text-amber-600 dark:text-amber-400 text-base' : 'text-stone-800 dark:text-slate-200'">
                                {{ step.label }}
                            </p>
                            <p v-if="index === currentStepIndex" class="text-xs text-stone-500 dark:text-slate-400 mt-0.5">
                                {{ step.desc }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Details -->
            <div class="rounded-3xl bg-white dark:bg-[#0a0f1d] p-6 shadow-xs ring-1 ring-stone-100 dark:ring-slate-800 transition-colors">
                <h3 class="mb-4 font-bold text-lg text-stone-800 dark:text-white">تفاصيل الفاتورة</h3>
                <ul class="space-y-3">
                    <li v-for="(item, i) in order.items" :key="i" class="border-b border-stone-100 dark:border-slate-800/80 pb-3 last:border-0 last:pb-0">
                        <div class="flex justify-between items-center text-sm">
                            <div class="flex items-center gap-3">
                                <span class="flex h-6 w-6 items-center justify-center rounded-md bg-stone-100 dark:bg-slate-800 font-bold text-stone-600 dark:text-slate-300">{{ item.quantity }}</span>
                                <span class="font-medium text-stone-800 dark:text-slate-200">{{ item.item_name_snapshot }}</span>
                            </div>
                            <span class="font-semibold text-stone-600 dark:text-slate-300">{{ formatPrice(item.subtotal) }}</span>
                        </div>
                        <div v-if="item.note" class="mr-9 mt-1 text-xs text-amber-700 dark:text-amber-400 font-medium">
                            ↳ ملاحظة: {{ item.note }}
                        </div>
                    </li>
                </ul>

                <!-- ملاحظة الزبون -->
                <div v-if="order.customer_note" class="mt-4 rounded-2xl bg-amber-50 dark:bg-amber-950/40 p-3.5 text-xs text-amber-900 dark:text-amber-200 border border-amber-200/80 dark:border-amber-700/60">
                    <span class="font-bold block mb-1 text-amber-800 dark:text-amber-300">ملاحظتك على الطلب:</span>
                    <p class="font-medium text-stone-700 dark:text-slate-300 whitespace-pre-wrap">{{ order.customer_note }}</p>
                </div>

                <div class="mt-4 flex justify-between rounded-2xl bg-stone-50 dark:bg-slate-900 p-4 font-bold border border-stone-100 dark:border-slate-800">
                    <span class="text-stone-600 dark:text-slate-300">المبلغ الإجمالي</span>
                    <span class="text-amber-600 dark:text-amber-400 text-lg">{{ formatPrice(order.total) }}</span>
                </div>
            </div>

            <!-- Powered by Tedallal Brand Footer -->
            <div class="mt-6 text-center pb-24">
                <a
                    href="https://tedallal.tech"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-stone-100 dark:bg-slate-800/80 hover:bg-stone-200 dark:hover:bg-slate-700 text-[11px] font-semibold text-stone-500 dark:text-slate-400 hover:text-amber-600 dark:hover:text-amber-400 transition-all shadow-xs"
                >
                    <span class="text-amber-500">⚡</span>
                    <span>Powered by:</span>
                    <span class="font-bold underline decoration-amber-500/50">www.tedallal.tech</span>
                </a>
            </div>

            <!-- Sticky Back Button -->
            <div class="fixed inset-x-0 bottom-0 z-30 bg-gradient-to-t from-stone-50 via-stone-50 to-transparent dark:from-[#060913] dark:via-[#060913] dark:to-transparent pt-8 pb-4 px-4 transition-colors">
                <div class="mx-auto max-w-lg">
                    <Link
                        :href="`/t/${publicToken}`"
                        class="flex w-full items-center justify-center gap-2 rounded-2xl bg-stone-900 dark:bg-amber-600 dark:hover:bg-amber-500 py-4 font-bold text-white shadow-lg transition active:scale-95"
                    >
                        <span>العودة لقائمة الطعام</span>
                        <svg class="h-5 w-5 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                    </Link>
                </div>
            </div>
        </div>
    </CustomerLayout>
</template>
