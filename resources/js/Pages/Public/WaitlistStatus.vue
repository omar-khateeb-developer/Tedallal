<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted, computed } from 'vue';
import axios from 'axios';

interface WaitlistEntryData {
    id: number;
    uuid: string;
    guest_name: string;
    queue_number: number;
    party_size: number;
    status: 'waiting' | 'notified' | 'seated' | 'cancelled' | 'no_show';
    seating_preference: string;
    estimated_wait_minutes: number;
    queue_position: number;
    notified_at?: string;
    assigned_table?: string;
    created_at: string;
}

const props = defineProps<{
    entry: WaitlistEntryData;
    restaurant: {
        id: number;
        name: string;
        slug: string;
        logo_url?: string;
        currency: string;
    };
}>();

const currentStatus = ref<string>(props.entry.status);
const queuePosition = ref<number>(props.entry.queue_position);
const assignedTable = ref<string | undefined>(props.entry.assigned_table);
const isCancelling = ref(false);
const showCancelConfirm = ref(false);
const isPolling = ref(true);

let pollTimer: any = null;

async function checkStatus() {
    if (!isPolling.value || currentStatus.value === 'seated' || currentStatus.value === 'cancelled') return;
    try {
        const { data } = await axios.get(`/api/public/waitlist/${props.entry.uuid}/status`);
        currentStatus.value = data.status;
        queuePosition.value = data.queue_position;
        if (data.assigned_table) {
            assignedTable.value = data.assigned_table;
        }
        if (data.status === 'notified') {
            // Trigger device vibration if supported
            if ('vibrate' in navigator) {
                navigator.vibrate([200, 100, 200, 100, 400]);
            }
        }
    } catch (e) {
        console.error('Waitlist poll error:', e);
    }
}

async function cancelMyTurn() {
    isCancelling.value = true;
    try {
        await axios.post(`/api/public/waitlist/${props.entry.uuid}/cancel`);
        currentStatus.value = 'cancelled';
        showCancelConfirm.value = false;
    } catch (e) {
        alert('تعذر إلغاء الدور حالياً، يرجى إعلام الاستقبال.');
    } finally {
        isCancelling.value = false;
    }
}

function openMenu() {
    window.location.href = `/r/${props.restaurant.slug}`;
}

onMounted(() => {
    pollTimer = setInterval(checkStatus, 8000);
});

onUnmounted(() => {
    if (pollTimer) clearInterval(pollTimer);
});

const preferenceLabel = computed(() => {
    switch (props.entry.seating_preference) {
        case 'indoor': return 'صالة داخلية';
        case 'terrace': return 'تراس خارجي';
        case 'vip': return 'جناح VIP';
        case 'family': return 'قسم عائلي';
        default: return 'أي جلسة متاحة';
    }
});
</script>

<template>
    <Head :title="`طابور الانتظار - ${restaurant.name}`" />

    <div class="min-h-screen bg-slate-950 text-slate-100 flex flex-col justify-between selection:bg-amber-500 selection:text-slate-950 font-sans" dir="rtl">
        <!-- Top App Header -->
        <header class="border-b border-slate-800/80 bg-slate-900/60 backdrop-blur-md sticky top-0 z-30 px-4 py-3.5 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div v-if="restaurant.logo_url" class="w-10 h-10 rounded-xl overflow-hidden border border-amber-500/30 bg-slate-800 p-0.5">
                    <img :src="restaurant.logo_url" :alt="restaurant.name" class="w-full h-full object-cover rounded-lg" />
                </div>
                <div v-else class="w-10 h-10 rounded-xl bg-amber-500/20 border border-amber-500/40 flex items-center justify-center font-black text-amber-400 text-lg">
                    {{ restaurant.name.charAt(0) }}
                </div>
                <div>
                    <h1 class="font-black text-sm text-white tracking-tight">{{ restaurant.name }}</h1>
                    <span class="text-[11px] text-amber-400 flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                        نظام الانتظار الرقمي المباشر
                    </span>
                </div>
            </div>

            <div class="px-2.5 py-1 rounded-full bg-slate-800 border border-slate-700 text-xs font-mono text-slate-300">
                #{{ entry.queue_number }}
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-1 max-w-md w-full mx-auto p-4 flex flex-col justify-center my-auto">
            <!-- STATE 1: TABLE READY (NOTIFIED) -->
            <transition enter-active-class="transition duration-300 ease-out" enter-from-class="scale-95 opacity-0">
                <div v-if="currentStatus === 'notified'" class="bg-gradient-to-b from-emerald-950/80 to-slate-900 border-2 border-emerald-500 rounded-3xl p-6 text-center shadow-[0_0_40px_rgba(16,185,129,0.3)] relative overflow-hidden animate-bounce-short">
                    <div class="w-20 h-20 mx-auto rounded-2xl bg-emerald-500/20 border border-emerald-400/50 flex items-center justify-center text-4xl shadow-inner mb-4">
                        🎉
                    </div>
                    <span class="px-3 py-1 rounded-full bg-emerald-500/30 border border-emerald-400 text-emerald-300 text-xs font-bold uppercase tracking-wider inline-block mb-2">
                        طاولتك جاهزة الآن!
                    </span>
                    <h2 class="text-2xl font-black text-white mb-2">
                        أهلاً بحضرتك {{ entry.guest_name }}
                    </h2>
                    <p class="text-sm text-emerald-200/90 leading-relaxed mb-4">
                        طاولة حضرتك <span v-if="assignedTable" class="font-black text-white px-2 py-0.5 rounded bg-emerald-800/80 border border-emerald-500">({{ assignedTable }})</span> أصبحت جاهزة في انتظاركم.
                        يرجى التوجه لموظف الاستقبال لتأكيد الجلوس.
                    </p>
                    <div class="p-3 bg-emerald-950/60 rounded-xl border border-emerald-800/60 text-xs text-emerald-300">
                        ⏱️ يُرجى الحضور خلال 5 دقائق لضمان حجز الطاولة.
                    </div>
                </div>
            </transition>

            <!-- STATE 2: WAITING IN QUEUE -->
            <div v-if="currentStatus === 'waiting'" class="space-y-4">
                <!-- Queue Big Badge Card -->
                <div class="bg-slate-900/90 border border-slate-800 rounded-3xl p-6 text-center relative overflow-hidden shadow-2xl backdrop-blur-md">
                    <!-- Subtle ambient background glow -->
                    <div class="absolute -top-16 -right-16 w-36 h-36 bg-amber-500/10 rounded-full blur-2xl pointer-events-none"></div>

                    <span class="text-xs text-slate-400 font-medium">رقمك في قائمة الانتظار</span>
                    <div class="text-6xl font-black text-amber-400 my-2 font-mono tracking-tight drop-shadow-md">
                        #{{ entry.queue_number }}
                    </div>

                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-800/80 border border-slate-700 text-xs text-slate-300 mb-6">
                        <span class="w-2 h-2 rounded-full bg-amber-400 animate-ping"></span>
                        <span>قيد الانتظار</span>
                        <span class="text-slate-500">•</span>
                        <span>{{ entry.party_size }} أفراد</span>
                        <span class="text-slate-500">•</span>
                        <span>{{ preferenceLabel }}</span>
                    </div>

                    <!-- Progress & Ahead Info -->
                    <div class="grid grid-cols-2 gap-3 p-4 rounded-2xl bg-slate-950/60 border border-slate-800/80">
                        <div class="text-center border-l border-slate-800/80">
                            <span class="text-[11px] text-slate-400 block mb-1">أمامك في الطابور</span>
                            <span class="text-2xl font-black text-white font-mono">
                                {{ queuePosition > 0 ? queuePosition - 1 : 0 }}
                            </span>
                            <span class="text-[10px] text-slate-500 block">مجموعة</span>
                        </div>
                        <div class="text-center">
                            <span class="text-[11px] text-slate-400 block mb-1">الوقت التقديري</span>
                            <span class="text-2xl font-black text-amber-400 font-mono">
                                ~{{ entry.estimated_wait_minutes }}
                            </span>
                            <span class="text-[10px] text-slate-500 block">دقيقة</span>
                        </div>
                    </div>

                    <p class="text-xs text-slate-400 mt-4 leading-relaxed">
                        سنرسل لك إشعاراً عبر الواتساب فور تجهيز طاولتك. يمكنك التجول بحرية في الجوار!
                    </p>
                </div>

                <!-- Call to Action: Explore Menu While Waiting -->
                <button
                    type="button"
                    @click="openMenu"
                    class="w-full py-3.5 px-4 rounded-2xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-black text-sm flex items-center justify-center gap-2 shadow-lg shadow-amber-500/20 hover:scale-[1.02] active:scale-[0.98] transition-all cursor-pointer"
                >
                    <span>📖</span>
                    <span>تصفح قائمة الطعام لاختيار وجبتك الآن</span>
                    <span class="mr-auto text-xs opacity-75 font-mono">←</span>
                </button>

                <!-- Cancel My Turn Button -->
                <button
                    type="button"
                    @click="showCancelConfirm = true"
                    class="w-full py-2.5 text-xs text-rose-400 hover:text-rose-300 transition-colors flex items-center justify-center gap-1 cursor-pointer opacity-75 hover:opacity-100"
                >
                    <span>✕</span>
                    <span>إلغاء دوري في الانتظار</span>
                </button>
            </div>

            <!-- STATE 3: SEATED -->
            <div v-if="currentStatus === 'seated'" class="bg-slate-900 border border-slate-800 rounded-3xl p-6 text-center">
                <div class="w-16 h-16 mx-auto rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-3xl mb-3">
                    ✓
                </div>
                <h3 class="text-xl font-bold text-white mb-2">تم الجلوس بنجاح</h3>
                <p class="text-xs text-slate-400 mb-6">نتمنى لك ولضيوفك تجربة تناول طعام استثنائية في {{ restaurant.name }}.</p>
                <button
                    type="button"
                    @click="openMenu"
                    class="w-full py-3 rounded-xl bg-slate-800 hover:bg-slate-700 text-white text-xs font-bold"
                >
                    عرض المنيو للطلب
                </button>
            </div>

            <!-- STATE 4: CANCELLED -->
            <div v-if="currentStatus === 'cancelled' || currentStatus === 'no_show'" class="bg-slate-900 border border-slate-800 rounded-3xl p-6 text-center">
                <div class="w-16 h-16 mx-auto rounded-full bg-rose-500/20 text-rose-400 flex items-center justify-center text-3xl mb-3">
                    ✕
                </div>
                <h3 class="text-xl font-bold text-white mb-2">تم إلغاء الدور</h3>
                <p class="text-xs text-slate-400 mb-6">تم إلغاء تسجيلك في قائمة الانتظار بناءً على طلبك أو لعدم الحضور.</p>
                <a
                    :href="`/r/${restaurant.slug}/waitlist`"
                    class="inline-block py-2.5 px-6 rounded-xl bg-amber-500 text-slate-950 font-bold text-xs"
                >
                    التسجيل من جديد
                </a>
            </div>
        </main>

        <!-- Cancel Confirmation Modal -->
        <div v-if="showCancelConfirm" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-xs w-full p-5 text-center shadow-2xl">
                <h4 class="font-bold text-base text-white mb-2">هل أنت متأكد من إلغاء دورك؟</h4>
                <p class="text-xs text-slate-400 mb-5 leading-relaxed">
                    سيتم التنازل عن دورك رقم #{{ entry.queue_number }} لصالح الزبائن التاليين.
                </p>
                <div class="grid grid-cols-2 gap-2">
                    <button
                        type="button"
                        @click="showCancelConfirm = false"
                        class="py-2.5 rounded-xl bg-slate-800 text-slate-300 text-xs font-bold hover:bg-slate-700"
                    >
                        تراجع
                    </button>
                    <button
                        type="button"
                        :disabled="isCancelling"
                        @click="cancelMyTurn"
                        class="py-2.5 rounded-xl bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold"
                    >
                        {{ isCancelling ? 'جاري الإلغاء...' : 'نعم، إلغاء دوري' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="p-4 text-center border-t border-slate-800/60 bg-slate-900/40">
            <a
                href="https://tedallal.tech"
                target="_blank"
                rel="noopener noreferrer"
                dir="ltr"
                class="inline-flex items-center gap-1.5 text-[11px] text-slate-500 hover:text-amber-400 font-mono transition-colors"
            >
                <span class="text-amber-500">⚡</span>
                <span>Powered by:</span>
                <span class="font-bold underline decoration-amber-500/50">www.tedallal.tech</span>
            </a>
        </footer>
    </div>
</template>

<style scoped>
@keyframes bounceShort {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-6px); }
}
.animate-bounce-short {
    animation: bounceShort 2s ease-in-out infinite;
}
</style>
