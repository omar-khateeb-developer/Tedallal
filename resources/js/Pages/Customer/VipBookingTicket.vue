<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';

interface ReservationTicket {
    uuid: string;
    code: string;
    status: string;
    guest_name: string;
    guest_phone: string;
    table_name: string;
    table_shape?: string;
    zone: string;
    date: string;
    time_slot: string;
    guests_count: number;
    special_requests?: string;
    created_at: string;
}

const props = defineProps<{
    restaurant: {
        name: string;
        slug: string;
        logo?: string;
        phone?: string;
        address?: string;
    };
    reservation: ReservationTicket;
}>();

const isCopied = ref(false);

function printTicket() {
    window.print();
}

function shareViaWhatsapp() {
    const text = `👑 تذكرة حجز VIP في ${props.restaurant.name}%0Aرمز الحجز: ${props.reservation.code}%0Aالضيف: ${props.reservation.guest_name}%0Aالتاريخ: ${props.reservation.date}%0Aالوقت: ${props.reservation.time_slot}%0Aالطاولة: ${props.reservation.table_name}%0Aالرابط: ${window.location.href}`;
    window.open(`https://wa.me/?text=${text}`, '_blank');
}

function copyTicketLink() {
    navigator.clipboard.writeText(window.location.href).then(() => {
        isCopied.value = true;
        setTimeout(() => { isCopied.value = false; }, 3000);
    });
}
</script>

<template>
    <Head :title="`تذكرة حجز VIP - ${reservation.code} - ${restaurant.name}`" />

    <div class="min-h-screen bg-[#070b14] text-slate-100 flex flex-col justify-between p-4 sm:p-8" dir="rtl">
        <div class="max-w-md w-full mx-auto my-auto space-y-6">
            <!-- Brand Badge -->
            <div class="text-center space-y-2">
                <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-amber-500/15 border border-amber-500/30 text-amber-300 text-xs font-black shadow-lg">
                    <span>👑</span>
                    <span>VIP DINING PASS • بطاقة حجز النخبة</span>
                </div>
                <h1 class="text-2xl font-black text-white tracking-tight">{{ restaurant.name }}</h1>
                <p v-if="restaurant.address" class="text-xs text-slate-400">{{ restaurant.address }}</p>
            </div>

            <!-- Master VIP Ticket Card -->
            <div class="relative overflow-hidden rounded-3xl bg-gradient-to-b from-slate-900 via-slate-950 to-black border-2 border-amber-500/50 shadow-[0_0_50px_rgba(245,158,11,0.25)] p-6 sm:p-8 text-center space-y-6">
                <!-- Golden Ambient Corner Blurs -->
                <div class="absolute -top-12 -right-12 w-36 h-36 bg-amber-500/20 rounded-full blur-2xl pointer-events-none"></div>
                <div class="absolute -bottom-12 -left-12 w-36 h-36 bg-amber-500/20 rounded-full blur-2xl pointer-events-none"></div>

                <!-- Ticket Header -->
                <div class="flex items-center justify-between border-b border-slate-800/80 pb-4">
                    <div class="text-start">
                        <span class="text-[10px] uppercase font-mono text-slate-400 tracking-widest block">PASS CODE</span>
                        <span class="text-base font-black font-mono text-amber-300 tracking-wider">{{ reservation.code }}</span>
                    </div>
                    <span class="px-3 py-1 rounded-xl text-xs font-black bg-emerald-500/20 text-emerald-300 border border-emerald-500/40">
                        مؤكد ✓
                    </span>
                </div>

                <!-- Guest & Table Info Grid -->
                <div class="grid grid-cols-2 gap-4 text-start bg-slate-900/60 border border-slate-800/80 rounded-2xl p-4">
                    <div>
                        <span class="text-[11px] text-slate-400 block mb-0.5">اسم الضيف الكريم</span>
                        <span class="font-extrabold text-sm text-white">{{ reservation.guest_name }}</span>
                    </div>
                    <div>
                        <span class="text-[11px] text-slate-400 block mb-0.5">الطاولة المحجوزة</span>
                        <span class="font-extrabold text-sm text-amber-300">{{ reservation.table_name }}</span>
                    </div>
                    <div>
                        <span class="text-[11px] text-slate-400 block mb-0.5">تاريخ الحجز</span>
                        <span class="font-bold text-xs text-white font-mono">{{ reservation.date }}</span>
                    </div>
                    <div>
                        <span class="text-[11px] text-slate-400 block mb-0.5">وقت الوصول</span>
                        <span class="font-bold text-xs text-white font-mono">{{ reservation.time_slot }}</span>
                    </div>
                    <div>
                        <span class="text-[11px] text-slate-400 block mb-0.5">المنطقة</span>
                        <span class="font-bold text-xs text-slate-300">
                            {{ reservation.zone === 'vip' ? 'جناح النخبة (VIP Lounge)' : 'الصالة المعمارية' }}
                        </span>
                    </div>
                    <div>
                        <span class="text-[11px] text-slate-400 block mb-0.5">عدد الضيوف</span>
                        <span class="font-bold text-xs text-slate-300">{{ reservation.guests_count }} أشخاص</span>
                    </div>
                </div>

                <!-- Special Occasion / Notes (if present) -->
                <div v-if="reservation.special_requests" class="text-xs text-start bg-slate-950/80 border border-slate-800 p-3 rounded-xl italic text-slate-300">
                    <span class="text-slate-500 not-italic font-bold block mb-1">ملاحظات الضيافة الخاصة:</span>
                    "{{ reservation.special_requests }}"
                </div>

                <!-- Perforated Cut Line -->
                <div class="relative flex items-center justify-center my-4">
                    <div class="w-full border-t-2 border-dashed border-slate-800"></div>
                    <div class="absolute -left-10 w-6 h-6 rounded-full bg-[#070b14] border border-slate-800"></div>
                    <div class="absolute -right-10 w-6 h-6 rounded-full bg-[#070b14] border border-slate-800"></div>
                </div>

                <!-- Security Verification QR Mockup -->
                <div class="space-y-3">
                    <div class="w-36 h-36 bg-white rounded-2xl p-2.5 mx-auto shadow-2xl flex items-center justify-center">
                        <img
                            :src="`https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=${encodeURIComponent(reservation.uuid)}`"
                            alt="QR Verification"
                            class="w-full h-full object-contain"
                        />
                    </div>
                    <p class="text-[11px] text-slate-400 font-mono">
                        يُرجى إبراز هذا الرمز عند الاستقبال لتسجيل وصولكم الفوري
                    </p>
                    <div class="pt-2 text-center">
                        <a
                            href="https://tedallal.tech"
                            target="_blank"
                            rel="noopener noreferrer"
                            dir="ltr"
                            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-slate-900 border border-slate-800 text-[10px] font-semibold text-slate-400 hover:text-amber-400 transition-colors"
                        >
                            <span class="text-amber-500">⚡</span>
                            <span>Powered by:</span>
                            <span class="font-bold underline decoration-amber-500/50">www.tedallal.tech</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="grid grid-cols-2 gap-3 no-print">
                <button
                    type="button"
                    @click="shareViaWhatsapp"
                    class="py-3 px-4 rounded-2xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs flex items-center justify-center gap-2 shadow-lg transition"
                >
                    <span>💬</span>
                    <span>مشاركة عبر واتساب</span>
                </button>
                <button
                    type="button"
                    @click="copyTicketLink"
                    class="py-3 px-4 rounded-2xl bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs flex items-center justify-center gap-2 transition"
                >
                    <span>🔗</span>
                    <span>{{ isCopied ? 'تم النسخ!' : 'نسخ رابط التذكرة' }}</span>
                </button>
            </div>

            <div class="no-print text-center pt-2">
                <button
                    type="button"
                    @click="printTicket"
                    class="text-xs text-amber-400/80 hover:text-amber-300 font-bold underline"
                >
                    طباعة التذكرة أو الحفظ بصيغة PDF ↗
                </button>
            </div>
        </div>
    </div>
</template>

<style>
@media print {
    .no-print {
        display: none !important;
    }
    body {
        background-color: #000 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
}
</style>
