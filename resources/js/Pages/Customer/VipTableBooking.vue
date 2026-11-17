<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed, onMounted, watch } from 'vue';
import axios from 'axios';

interface TableGeometry {
    id: number;
    name: string;
    pos_x: number;
    pos_y: number;
    shape: 'circle' | 'rectangle' | 'booth' | 'vip';
    rotation: number;
    capacity: number;
    zone: string;
    width: number;
    height: number;
    iot_device_id?: string | null;
}

const props = defineProps<{
    restaurant: {
        id: number;
        name: string;
        slug: string;
        logo?: string;
        phone?: string;
        address?: string;
        currency?: string;
    };
    tables: TableGeometry[];
    timeSlots: string[];
    isFeatureAvailable: boolean;
    minDate: string;
    maxDate: string;
}>();

// State
const tables = ref<TableGeometry[]>(props.tables.map(t => ({
    ...t,
    pos_x: Number(t.pos_x) || 80,
    pos_y: Number(t.pos_y) || 80,
    shape: t.shape || 'circle',
    rotation: Number(t.rotation) || 0,
    capacity: Number(t.capacity) || 4,
    zone: t.zone || 'main',
    width: Number(t.width) || (t.shape === 'vip' ? 140 : (t.shape === 'rectangle' ? 130 : 90)),
    height: Number(t.height) || (t.shape === 'rectangle' ? 80 : 90),
})));

const selectedDate = ref<string>(props.minDate);
const selectedTimeSlot = ref<string>(props.timeSlots[4] || props.timeSlots[0] || '19:00');
const guestsCount = ref<number>(2);
const unavailableTableIds = ref<number[]>([]);
const isCheckingAvailability = ref(false);
const selectedTable = ref<TableGeometry | null>(null);
const zoomLevel = ref<number>(1);

// Booking Modal State
const isModalOpen = ref(false);
const bookingStep = ref<'details' | 'otp' | 'confirmed'>('details');
const isSubmitting = ref(false);
const errorMessage = ref('');
const holdUuid = ref('');
const otpCode = ref('');
const maskedPhone = ref('');
const devOtp = ref<string | null>(null);
const timerSeconds = ref(600);
let timerInterval: any = null;

// Confirmed Ticket info
const confirmedData = ref<{
    code: string;
    ticket_url: string;
    guest_name: string;
    table_name: string;
    time_slot: string;
    date: string;
} | null>(null);

// Form inputs
const form = ref({
    guest_name: '',
    guest_phone: '',
    guest_email: '',
    special_requests: '',
    vip_bot_trap: '', // Honeypot field
});

// Check availability when date or time slot changes
async function checkAvailability() {
    if (!props.isFeatureAvailable) return;
    isCheckingAvailability.value = true;
    errorMessage.value = '';
    try {
        const { data } = await axios.get(`/api/public/restaurants/${props.restaurant.slug}/booking-availability`, {
            params: {
                date: selectedDate.value,
                time_slot: selectedTimeSlot.value,
            }
        });
        unavailableTableIds.value = data.unavailable_table_ids || [];
        if (selectedTable.value && unavailableTableIds.value.includes(selectedTable.value.id)) {
            selectedTable.value = null;
        }
    } catch (e: any) {
        console.error('Availability check failed', e);
    } finally {
        isCheckingAvailability.value = false;
    }
}

watch([selectedDate, selectedTimeSlot], () => {
    checkAvailability();
});

onMounted(() => {
    checkAvailability();
});

// Helper for availability
function isTableAvailable(tableId: number): boolean {
    return !unavailableTableIds.value.includes(tableId);
}

// Select a table
function onSelectTable(table: TableGeometry) {
    if (!isTableAvailable(table.id)) return;
    selectedTable.value = table;
    bookingStep.value = 'details';
    errorMessage.value = '';
    isModalOpen.value = true;
}

// Submit Hold & Request OTP
async function submitHoldRequest() {
    if (!selectedTable.value) return;
    if (!form.value.guest_name.trim() || !form.value.guest_phone.trim()) {
        errorMessage.value = 'يرجى إدخال الاسم ورقم الهاتف للمتابعة.';
        return;
    }

    isSubmitting.value = true;
    errorMessage.value = '';
    try {
        const { data } = await axios.post(`/api/public/restaurants/${props.restaurant.slug}/reservations/hold`, {
            table_id: selectedTable.value.id,
            guest_name: form.value.guest_name.trim(),
            guest_phone: form.value.guest_phone.trim(),
            guest_email: form.value.guest_email.trim() || null,
            reservation_date: selectedDate.value,
            time_slot: selectedTimeSlot.value,
            guests_count: guestsCount.value,
            special_requests: form.value.special_requests.trim() || null,
            vip_bot_trap: form.value.vip_bot_trap,
        });

        holdUuid.value = data.uuid;
        maskedPhone.value = data.phone_masked;
        devOtp.value = data.dev_otp;
        timerSeconds.value = data.expires_in_seconds || 600;
        bookingStep.value = 'otp';

        // Start countdown
        clearInterval(timerInterval);
        timerInterval = setInterval(() => {
            if (timerSeconds.value > 0) {
                timerSeconds.value--;
            } else {
                clearInterval(timerInterval);
                errorMessage.value = 'انتهت مهلة التأكيد (10 دقائق). يرجى تقديم طلب جديد.';
            }
        }, 1000);

    } catch (e: any) {
        errorMessage.value = e.response?.data?.message || 'حدث خطأ أثناء حجز الطاولة. يرجى المحاولة ثانية.';
    } finally {
        isSubmitting.value = false;
    }
}

// Verify OTP
async function verifyOtpCode() {
    if (!holdUuid.value || otpCode.value.length !== 4) {
        errorMessage.value = 'يرجى إدخال رمز التحقق المكون من 4 أرقام.';
        return;
    }

    isSubmitting.value = true;
    errorMessage.value = '';
    try {
        const { data } = await axios.post(`/api/public/restaurants/${props.restaurant.slug}/reservations/verify-otp`, {
            uuid: holdUuid.value,
            otp_code: otpCode.value,
        });

        clearInterval(timerInterval);
        confirmedData.value = {
            code: data.booking_code,
            ticket_url: data.ticket_url,
            guest_name: form.value.guest_name,
            table_name: selectedTable.value?.name || '',
            time_slot: selectedTimeSlot.value,
            date: selectedDate.value,
        };
        bookingStep.value = 'confirmed';
        checkAvailability(); // Refresh floor plan
    } catch (e: any) {
        errorMessage.value = e.response?.data?.message || 'رمز التحقق غير صحيح أو منتهي الصلاحية.';
    } finally {
        isSubmitting.value = false;
    }
}

function formatCountdown(sec: number): string {
    const m = Math.floor(sec / 60);
    const s = sec % 60;
    return `${m}:${s < 10 ? '0' : ''}${s}`;
}

// Zoom
function zoomIn() {
    if (zoomLevel.value < 1.3) zoomLevel.value = +(zoomLevel.value + 0.1).toFixed(1);
}
function zoomOut() {
    if (zoomLevel.value > 0.7) zoomLevel.value = +(zoomLevel.value - 0.1).toFixed(1);
}
function resetZoom() {
    zoomLevel.value = 1;
}
</script>

<template>
    <Head :title="`حجز طاولة VIP مسبق - ${restaurant.name}`" />

    <div class="min-h-screen bg-[#070b14] text-slate-100 font-sans" dir="rtl">
        <!-- Top Luxury Header Banner -->
        <header class="border-b border-slate-800/80 bg-slate-950/80 backdrop-blur-md sticky top-0 z-40">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3.5 flex items-center justify-between">
                <!-- Restaurant Brand & VIP Tag -->
                <div class="flex items-center gap-3">
                    <img
                        v-if="restaurant.logo"
                        :src="restaurant.logo"
                        :alt="restaurant.name"
                        class="w-10 h-10 rounded-2xl object-cover ring-2 ring-amber-500/30"
                    />
                    <div v-else class="w-10 h-10 rounded-2xl bg-amber-500/10 border border-amber-500/30 flex items-center justify-center text-amber-400 font-bold text-lg">
                        👑
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="text-base sm:text-lg font-black text-white tracking-tight">{{ restaurant.name }}</h1>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-amber-500/20 text-amber-300 border border-amber-500/40">
                                VIP LOUNGE
                            </span>
                        </div>
                        <p class="text-xs text-slate-400 flex items-center gap-2 mt-0.5">
                            <span>🏛️ الحجز المرئي التفاعلي للطاولات</span>
                            <span v-if="restaurant.phone" class="font-mono text-slate-500" dir="ltr">{{ restaurant.phone }}</span>
                        </p>
                    </div>
                </div>

                <!-- Back to Menu Button -->
                <a
                    :href="`/t/${tables[0]?.public_token || 'demo'}`"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-slate-900 border border-slate-800 text-xs font-bold text-slate-300 hover:text-white hover:border-slate-700 transition"
                >
                    <span>📖</span>
                    <span class="hidden sm:inline">قائمة الطعام</span>
                </a>
            </div>
        </header>

        <!-- Main Viewport -->
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
            <!-- Filter Bar: Date & Time & Guests -->
            <div class="bg-slate-900/90 border border-slate-800 rounded-3xl p-4 sm:p-5 shadow-2xl backdrop-blur-md">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                    <!-- Date Selection -->
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1.5 flex items-center gap-1.5">
                            <span>📅</span>
                            <span>تاريخ الحجز المفضل</span>
                        </label>
                        <input
                            v-model="selectedDate"
                            type="date"
                            :min="minDate"
                            :max="maxDate"
                            class="w-full px-3.5 py-2.5 rounded-2xl bg-slate-950 border border-slate-800 text-white text-sm font-semibold focus:ring-2 focus:ring-amber-500 focus:outline-hidden"
                        />
                    </div>

                    <!-- Time Slot Selection -->
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1.5 flex items-center gap-1.5">
                            <span>⏰</span>
                            <span>التوقيت المسائي أو النهاري</span>
                        </label>
                        <select
                            v-model="selectedTimeSlot"
                            class="w-full px-3.5 py-2.5 rounded-2xl bg-slate-950 border border-slate-800 text-white text-sm font-semibold focus:ring-2 focus:ring-amber-500 focus:outline-hidden"
                        >
                            <option v-for="slot in timeSlots" :key="slot" :value="slot">
                                {{ slot }} (توقيت محلي)
                            </option>
                        </select>
                    </div>

                    <!-- Guests Counter -->
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1.5 flex items-center gap-1.5">
                            <span>👥</span>
                            <span>عدد الضيوف</span>
                        </label>
                        <div class="flex items-center bg-slate-950 border border-slate-800 rounded-2xl p-1 justify-between">
                            <button
                                type="button"
                                @click="guestsCount = Math.max(1, guestsCount - 1)"
                                class="w-9 h-9 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-base flex items-center justify-center transition"
                            >
                                -
                            </button>
                            <span class="font-black text-sm text-amber-300 font-mono">{{ guestsCount }} أشخاص</span>
                            <button
                                type="button"
                                @click="guestsCount = Math.min(20, guestsCount + 1)"
                                class="w-9 h-9 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-base flex items-center justify-center transition"
                            >
                                +
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Legend & Availability Bar -->
                <div class="mt-4 pt-4 border-t border-slate-800/80 flex flex-wrap items-center justify-between gap-3 text-xs">
                    <div class="flex items-center gap-4">
                        <span class="inline-flex items-center gap-1.5 text-emerald-400 font-medium">
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.8)]"></span>
                            <span>طاولة متاحة للحجز الفوري</span>
                        </span>
                        <span class="inline-flex items-center gap-1.5 text-amber-400 font-medium">
                            <span class="w-2.5 h-2.5 rounded-full bg-amber-500 shadow-[0_0_8px_rgba(245,158,11,0.8)]"></span>
                            <span>جناح VIP الفاخر</span>
                        </span>
                        <span class="inline-flex items-center gap-1.5 text-slate-500 font-medium">
                            <span class="w-2.5 h-2.5 rounded-full bg-slate-700"></span>
                            <span>محجوزة في هذا التوقيت</span>
                        </span>
                    </div>

                    <!-- Zoom Controls -->
                    <div class="flex items-center gap-2">
                        <span class="text-slate-400 text-[11px] font-mono">Zoom: {{ Math.round(zoomLevel * 100) }}%</span>
                        <div class="flex items-center bg-slate-950 border border-slate-800 rounded-xl px-1 py-0.5 text-xs font-mono">
                            <button @click="zoomOut" class="px-2 py-1 hover:text-amber-400 font-bold">-</button>
                            <button @click="resetZoom" class="px-1.5 hover:text-amber-400 font-bold">100%</button>
                            <button @click="zoomIn" class="px-2 py-1 hover:text-amber-400 font-bold">+</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Interactive Blueprint Canvas -->
            <div class="relative w-full rounded-3xl bg-[#050810] border-2 border-slate-800/90 shadow-2xl overflow-hidden min-h-[640px] select-none">
                <!-- CAD Grid Background -->
                <div
                    class="absolute inset-0 pointer-events-none opacity-20"
                    style="background-size: 30px 30px; background-image: linear-gradient(to right, #334155 1px, transparent 1px), linear-gradient(to bottom, #334155 1px, transparent 1px);"
                ></div>

                <!-- Architectural Border Frame -->
                <div class="absolute inset-3 border border-slate-800/60 pointer-events-none rounded-2xl"></div>

                <!-- Dimension Header Tag -->
                <div class="absolute top-3 left-6 text-[9px] font-mono text-slate-700 pointer-events-none tracking-widest uppercase">
                    [ VIP RESERVATION BLUEPRINT • SELECT YOUR DESIRED TABLE ]
                </div>

                <!-- Canvas Scalable Container -->
                <div
                    id="cad-canvas"
                    class="relative w-[1140px] h-[640px] origin-top-right transition-transform duration-100 mx-auto"
                    :style="{ transform: `scale(${zoomLevel})` }"
                >
                    <!-- ================= ARCHITECTURAL WALLS & CORRIDORS ================= -->
                    <div class="absolute top-6 left-6 right-6 bottom-6 border-2 border-slate-700/80 rounded-2xl pointer-events-none">
                        <div class="absolute inset-1 border border-slate-800/60 rounded-xl"></div>
                    </div>

                    <!-- Left Main Entrance -->
                    <div class="absolute top-1/2 left-4 -translate-y-1/2 w-8 h-28 bg-[#050810] border-y-2 border-slate-600 pointer-events-none flex flex-col items-center justify-center z-10">
                        <div class="w-1.5 h-12 bg-emerald-500/80 rounded-full animate-pulse shadow-[0_0_10px_rgba(16,185,129,0.8)]"></div>
                        <span class="text-[8px] font-mono text-emerald-400 rotate-90 whitespace-nowrap mt-2 tracking-wider">ENTRANCE</span>
                    </div>

                    <!-- Host Reception Counter -->
                    <div class="absolute top-[180px] left-[75px] w-24 h-12 bg-stone-900 border border-stone-700/80 rounded-lg pointer-events-none flex flex-col items-center justify-center shadow-lg">
                        <span class="text-[9px] font-bold text-amber-400/90 font-mono">HOST DESK</span>
                        <span class="text-[8px] text-stone-500 font-mono">Reception</span>
                    </div>

                    <!-- Central Dining Hall Guideline -->
                    <div class="absolute top-1/2 left-28 right-[360px] -translate-y-1/2 h-[130px] border-y border-dashed border-slate-800/80 pointer-events-none flex items-center justify-center">
                        <span class="text-[10px] uppercase font-mono tracking-widest text-slate-700/60">M a i n &nbsp; D i n i n g &nbsp; H a l l</span>
                    </div>

                    <!-- ================= VIP PRIVATE LOUNGE SALON (Right Wing) ================= -->
                    <div class="absolute top-8 right-8 bottom-8 w-[310px] border-2 border-amber-500/90 rounded-2xl bg-slate-950/70 shadow-[0_0_35px_rgba(245,158,11,0.22)] pointer-events-none p-4 flex flex-col justify-between">
                        <!-- VIP Room Tag -->
                        <div class="bg-black/85 backdrop-blur-md border border-amber-500/40 px-3.5 py-2 rounded-xl self-start shadow-xl">
                            <div class="flex items-center gap-1.5 text-xs font-black text-amber-300">
                                <span>👑</span>
                                <span>VIP Lounge</span>
                            </div>
                            <div class="text-[9px] text-slate-400 font-mono mt-0.5">Private dining salon</div>
                        </div>

                        <!-- Top VIP Luxury Sofa Suite -->
                        <div class="relative w-full h-16 bg-stone-900/90 border border-stone-700/80 rounded-xl flex items-center justify-between px-3 shadow-inner">
                            <div class="w-4 h-4 rounded-full bg-amber-400/80 shadow-[0_0_12px_rgba(245,158,11,0.9)] flex items-center justify-center text-[8px]">💡</div>
                            <div class="text-[10px] font-bold text-stone-400 font-mono">🛋️ Luxury Sofa Suite</div>
                            <div class="w-8 h-8 rounded-md bg-stone-950 border border-stone-800 flex items-center justify-center text-[8px] text-stone-500">Table</div>
                        </div>

                        <div class="w-full border-t border-dashed border-amber-500/30 my-1"></div>

                        <!-- Bottom VIP Luxury Sofa Suite -->
                        <div class="relative w-full h-16 bg-stone-900/90 border border-stone-700/80 rounded-xl flex items-center justify-between px-3 shadow-inner">
                            <div class="w-4 h-4 rounded-full bg-amber-400/80 shadow-[0_0_12px_rgba(245,158,11,0.9)] flex items-center justify-center text-[8px]">💡</div>
                            <div class="text-[10px] font-bold text-stone-400 font-mono">🛋️ Luxury Sofa Suite</div>
                            <div class="w-8 h-8 rounded-md bg-stone-950 border border-stone-800 flex items-center justify-center text-[8px] text-stone-500">Table</div>
                        </div>
                    </div>

                    <!-- VIP Door Openings -->
                    <div class="absolute top-[260px] right-[316px] w-3 h-14 bg-[#050810] border-y border-amber-500/60 pointer-events-none z-10 flex items-center justify-center">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-ping"></span>
                    </div>
                    <div class="absolute top-[370px] right-[316px] w-3 h-14 bg-[#050810] border-y border-amber-500/60 pointer-events-none z-10 flex items-center justify-center">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-ping"></span>
                    </div>

                    <!-- ================= INTERACTIVE TABLES ON BLUEPRINT ================= -->
                    <div
                        v-for="table in tables"
                        :key="table.id"
                        class="absolute transition-all duration-200 group z-20 select-none"
                        :class="[
                            isTableAvailable(table.id)
                                ? 'cursor-pointer hover:scale-105 active:scale-95'
                                : 'cursor-not-allowed opacity-45 grayscale-60',
                            selectedTable?.id === table.id ? 'z-30' : ''
                        ]"
                        :style="{
                            left: table.pos_x + 'px',
                            top: table.pos_y + 'px',
                            width: (table.shape === 'vip' ? 140 : (table.shape === 'rectangle' ? 130 : 90)) + 'px',
                            height: (table.shape === 'rectangle' ? 80 : 90) + 'px',
                            transform: `rotate(${table.rotation || 0}deg)`,
                        }"
                        @click="onSelectTable(table)"
                    >
                        <!-- Chairs -->
                        <template v-if="table.shape === 'circle'">
                            <div class="absolute -top-3.5 left-1/2 -translate-x-1/2 w-6 h-2.5 rounded-full bg-stone-800 border border-stone-600/80 pointer-events-none"></div>
                            <div class="absolute -bottom-3.5 left-1/2 -translate-x-1/2 w-6 h-2.5 rounded-full bg-stone-800 border border-stone-600/80 pointer-events-none"></div>
                            <div class="absolute top-1/2 -left-3.5 -translate-y-1/2 w-2.5 h-6 rounded-full bg-stone-800 border border-stone-600/80 pointer-events-none"></div>
                            <div class="absolute top-1/2 -right-3.5 -translate-y-1/2 w-2.5 h-6 rounded-full bg-stone-800 border border-stone-600/80 pointer-events-none"></div>
                        </template>

                        <template v-else-if="table.shape === 'rectangle'">
                            <div class="absolute -top-3.5 left-3 w-6 h-2.5 rounded-full bg-stone-800 border border-stone-600/80 pointer-events-none"></div>
                            <div class="absolute -top-3.5 left-1/2 -translate-x-1/2 w-6 h-2.5 rounded-full bg-stone-800 border border-stone-600/80 pointer-events-none"></div>
                            <div class="absolute -top-3.5 right-3 w-6 h-2.5 rounded-full bg-stone-800 border border-stone-600/80 pointer-events-none"></div>
                            <div class="absolute -bottom-3.5 left-3 w-6 h-2.5 rounded-full bg-stone-800 border border-stone-600/80 pointer-events-none"></div>
                            <div class="absolute -bottom-3.5 left-1/2 -translate-x-1/2 w-6 h-2.5 rounded-full bg-stone-800 border border-stone-600/80 pointer-events-none"></div>
                            <div class="absolute -bottom-3.5 right-3 w-6 h-2.5 rounded-full bg-stone-800 border border-stone-600/80 pointer-events-none"></div>
                        </template>

                        <!-- Table Core & Halo -->
                        <div
                            class="w-full h-full flex flex-col items-center justify-center text-center p-2 transition-all duration-300 relative shadow-2xl"
                            :class="[
                                table.shape === 'circle' ? 'rounded-full' : (table.shape === 'vip' ? 'rounded-3xl' : 'rounded-2xl'),
                                !isTableAvailable(table.id)
                                    ? 'bg-slate-950 ring-2 ring-slate-800 border border-slate-800'
                                    : (selectedTable?.id === table.id
                                        ? 'bg-amber-950/90 ring-4 ring-amber-400 shadow-[0_0_35px_rgba(251,191,36,0.8)] border-2 border-amber-300 scale-105'
                                        : (table.zone === 'vip'
                                            ? 'bg-slate-900/90 ring-3 ring-amber-500 shadow-[0_0_25px_rgba(245,158,11,0.5)] border border-amber-400'
                                            : 'bg-slate-900/90 ring-2 ring-emerald-500 shadow-[0_0_20px_rgba(16,185,129,0.4)] border border-emerald-400'))
                            ]"
                        >
                            <!-- Label Tooltip matching reference image -->
                            <div
                                class="absolute -top-7 whitespace-nowrap px-2.5 py-0.5 rounded-md text-[10px] font-black flex items-center gap-1 shadow-xl pointer-events-none transition-all z-30"
                                :style="{ transform: `rotate(${-(table.rotation || 0)}deg)` }"
                                :class="!isTableAvailable(table.id)
                                    ? 'bg-slate-900/90 text-slate-500 border border-slate-800'
                                    : (table.zone === 'vip'
                                        ? 'bg-amber-950/95 text-amber-300 border border-amber-500 shadow-amber-900/40'
                                        : 'bg-emerald-950/95 text-emerald-300 border border-emerald-500 shadow-emerald-900/40')"
                            >
                                <span>{{ table.name }}</span>
                                <span class="opacity-80">
                                    • {{ isTableAvailable(table.id) ? (table.zone === 'vip' ? 'VIP' : 'Available') : 'Reserved' }}
                                </span>
                            </div>

                            <!-- Table Number -->
                            <span class="font-black text-sm text-white drop-shadow-md tracking-tight">
                                {{ table.name.replace(/[^0-9]/g, '') ? '#' + table.name.replace(/[^0-9]/g, '') : table.name }}
                            </span>

                            <!-- Capacity -->
                            <span class="text-[9px] text-slate-400 font-mono mt-0.5">
                                {{ table.capacity }} Seats
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Booking Modal (Details -> OTP -> Confirmed) -->
        <div
            v-if="isModalOpen && selectedTable"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-md animate-fadeIn"
            dir="rtl"
        >
            <div class="w-full max-w-lg bg-slate-900 border border-amber-500/30 rounded-3xl p-6 sm:p-8 shadow-2xl text-slate-100 space-y-6">
                <!-- Modal Header -->
                <div class="flex items-center justify-between border-b border-slate-800 pb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-amber-500/20 border border-amber-500/40 flex items-center justify-center text-amber-300 text-lg">
                            👑
                        </div>
                        <div>
                            <h3 class="text-lg font-black text-white">حجز طاولة VIP مسبق</h3>
                            <p class="text-xs text-amber-300/80">
                                {{ selectedTable.name }} • {{ selectedTable.capacity }} مقاعد ({{ selectedTable.zone === 'vip' ? 'جناح النخبة' : 'الصالة الرئيسية' }})
                            </p>
                        </div>
                    </div>
                    <button
                        @click="isModalOpen = false"
                        class="p-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 transition"
                    >
                        ✕
                    </button>
                </div>

                <!-- Error Alert -->
                <div v-if="errorMessage" class="p-3.5 rounded-2xl bg-rose-500/15 border border-rose-500/30 text-rose-300 text-xs font-bold">
                    ⚠️ {{ errorMessage }}
                </div>

                <!-- STEP 1: Guest Information -->
                <div v-if="bookingStep === 'details'" class="space-y-4">
                    <div class="p-3.5 rounded-2xl bg-slate-950/60 border border-slate-800 flex items-center justify-between text-xs">
                        <div>
                            <span class="text-slate-400">التاريخ والوقت: </span>
                            <span class="font-bold text-white">{{ selectedDate }} • الساعة {{ selectedTimeSlot }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400">الضيوف: </span>
                            <span class="font-bold text-amber-300">{{ guestsCount }} أشخاص</span>
                        </div>
                    </div>

                    <!-- Honeypot hidden input for anti-bot protection -->
                    <input type="text" v-model="form.vip_bot_trap" class="hidden" tabindex="-1" autocomplete="off" />

                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1">الاسم الكامل (كبار الشخصيات / VIP)</label>
                        <input
                            v-model="form.guest_name"
                            type="text"
                            placeholder="مثال: السيد عبد الرحمن الأحمد"
                            class="w-full px-4 py-3 rounded-2xl bg-slate-950 border border-slate-800 text-white text-sm focus:ring-2 focus:ring-amber-500 focus:outline-hidden"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1">رقم الهاتف (لتلقي رمز التحقق عبر واتساب)</label>
                        <input
                            v-model="form.guest_phone"
                            type="tel"
                            dir="ltr"
                            placeholder="e.g. 0933123456 أو +9639..."
                            class="w-full px-4 py-3 rounded-2xl bg-slate-950 border border-slate-800 text-white text-sm font-mono focus:ring-2 focus:ring-amber-500 focus:outline-hidden"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1">مناسبة خاصة أو ملاحظات للضيافة (اختياري)</label>
                        <textarea
                            v-model="form.special_requests"
                            rows="2"
                            placeholder="مثال: عشاء عمل هادئ، ذكرى سنوية، ترتيب زهور على الطاولة..."
                            class="w-full px-4 py-2.5 rounded-2xl bg-slate-950 border border-slate-800 text-white text-xs focus:ring-2 focus:ring-amber-500 focus:outline-hidden"
                        ></textarea>
                    </div>

                    <button
                        type="button"
                        @click="submitHoldRequest"
                        :disabled="isSubmitting"
                        class="w-full py-3.5 rounded-2xl bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-400 hover:to-orange-400 text-stone-950 font-black text-sm shadow-xl shadow-amber-500/20 active:scale-98 transition disabled:opacity-50 flex items-center justify-center gap-2"
                    >
                        <span v-if="isSubmitting">جاري حجز الطاولة مؤقتاً...</span>
                        <template v-else>
                            <span>🔐</span>
                            <span>تأكيد الحجز وتلقي رمز التحقق</span>
                        </template>
                    </button>
                </div>

                <!-- STEP 2: OTP Verification -->
                <div v-else-if="bookingStep === 'otp'" class="space-y-5 text-center">
                    <div class="w-16 h-16 rounded-3xl bg-amber-500/20 border border-amber-500/40 flex items-center justify-center text-3xl mx-auto">
                        📲
                    </div>
                    <div>
                        <h4 class="text-base font-extrabold text-white">التحقق من رقم الهاتف</h4>
                        <p class="text-xs text-slate-400 mt-1">
                            تم إرسال رمز التحقق المكون من 4 أرقام إلى <span dir="ltr" class="font-mono text-amber-300">{{ maskedPhone }}</span>
                        </p>
                    </div>

                    <!-- Local Dev OTP helper -->
                    <div v-if="devOtp" class="p-2.5 rounded-xl bg-cyan-950/60 border border-cyan-500/40 text-cyan-300 text-xs font-mono">
                        [بيئة التطوير]: رمز التحقق هو: <strong>{{ devOtp }}</strong>
                    </div>

                    <!-- 4-digit code input -->
                    <div>
                        <input
                            v-model="otpCode"
                            type="text"
                            maxlength="4"
                            dir="ltr"
                            placeholder="0000"
                            class="w-48 py-3 text-center text-2xl tracking-[0.5em] font-mono font-black rounded-2xl bg-slate-950 border-2 border-amber-500/60 text-amber-300 focus:ring-4 focus:ring-amber-500/30 focus:outline-hidden mx-auto block"
                        />
                    </div>

                    <div class="text-xs text-slate-500 font-mono">
                        صلاحية الرمز تنتهي خلال: <span class="text-amber-400 font-bold">{{ formatCountdown(timerSeconds) }}</span>
                    </div>

                    <div class="flex items-center gap-3 pt-2">
                        <button
                            type="button"
                            @click="verifyOtpCode"
                            :disabled="isSubmitting || otpCode.length !== 4"
                            class="flex-1 py-3.5 rounded-2xl bg-emerald-600 hover:bg-emerald-500 text-white font-black text-sm shadow-xl shadow-emerald-600/20 disabled:opacity-40 transition flex items-center justify-center gap-2"
                        >
                            <span>✓</span>
                            <span>{{ isSubmitting ? 'جاري التحقق...' : 'تأكيد الحجز النهائي' }}</span>
                        </button>
                        <button
                            type="button"
                            @click="bookingStep = 'details'"
                            class="px-5 py-3.5 rounded-2xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-xs"
                        >
                            رجوع
                        </button>
                    </div>
                </div>

                <!-- STEP 3: Confirmed VIP Pass -->
                <div v-else-if="bookingStep === 'confirmed' && confirmedData" class="space-y-5 text-center">
                    <div class="w-16 h-16 rounded-full bg-emerald-500/20 border border-emerald-500/40 flex items-center justify-center text-3xl mx-auto text-emerald-400">
                        ✨
                    </div>
                    <div>
                        <h4 class="text-lg font-black text-white">تم تأكيد حجز طاولة VIP بنجاح!</h4>
                        <p class="text-xs text-slate-400 mt-1">نتطلع لاستقبالكم في {{ restaurant.name }}</p>
                    </div>

                    <!-- Digital VIP Card Preview -->
                    <div class="bg-gradient-to-br from-amber-500/15 via-slate-950 to-slate-900 border-2 border-amber-500/40 rounded-3xl p-5 text-start space-y-3 shadow-2xl">
                        <div class="flex items-center justify-between border-b border-amber-500/20 pb-3">
                            <span class="text-xs font-bold text-amber-300">بطاقة دخول VIP الرقمية</span>
                            <span class="font-mono text-sm font-black text-white px-2 py-0.5 rounded-md bg-amber-500/20 border border-amber-500/40">
                                {{ confirmedData.code }}
                            </span>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <div>
                                <span class="text-slate-400 block text-[11px]">اسم الضيف:</span>
                                <span class="font-bold text-white">{{ confirmedData.guest_name }}</span>
                            </div>
                            <div>
                                <span class="text-slate-400 block text-[11px]">الطاولة:</span>
                                <span class="font-bold text-amber-300">{{ confirmedData.table_name }}</span>
                            </div>
                            <div>
                                <span class="text-slate-400 block text-[11px]">التاريخ:</span>
                                <span class="font-bold text-white">{{ confirmedData.date }}</span>
                            </div>
                            <div>
                                <span class="text-slate-400 block text-[11px]">الوقت:</span>
                                <span class="font-bold text-white">{{ confirmedData.time_slot }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="pt-2">
                        <a
                            :href="confirmedData.ticket_url"
                            class="w-full py-3.5 rounded-2xl bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-400 hover:to-orange-400 text-stone-950 font-black text-sm shadow-xl shadow-amber-500/25 flex items-center justify-center gap-2 transition"
                        >
                            <span>🎫</span>
                            <span>عرض تذكرة الحجز الرسمية ورمز الـ QR</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes fadeIn {
    from { opacity: 0; transform: scale(0.97); }
    to { opacity: 1; transform: scale(1); }
}

.animate-fadeIn {
    animation: fadeIn 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
</style>
