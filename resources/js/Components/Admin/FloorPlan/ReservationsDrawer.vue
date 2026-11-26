<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { ReservationItem } from '@/types/floorPlan';

defineProps<{
    reservations: ReservationItem[];
    restaurantName: string;
    isWhatsappConnected: boolean;
    isCopiedLink: boolean;
    isAdmin?: boolean;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'seat-reservation', reservation: ReservationItem): void;
    (e: 'copy-link'): void;
}>();
</script>

<template>
    <div
        class="fixed inset-y-0 right-0 z-50 w-full sm:w-[440px] bg-white dark:bg-slate-900 shadow-2xl border-l border-slate-200 dark:border-slate-800 p-6 flex flex-col justify-between overflow-y-auto animate-slideLeft"
        dir="rtl"
    >
        <div class="space-y-6">
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-4">
                <div class="flex items-center gap-2">
                    <span class="text-xl">👑</span>
                    <div>
                        <h3 class="text-lg font-black text-slate-900 dark:text-white">حجوزات زبائن VIP</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">قائمة الحجوزات المؤكدة اليوم</p>
                    </div>
                </div>
                <button
                    @click="emit('close')"
                    type="button"
                    class="p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer"
                >
                    ✕
                </button>
            </div>

            <!-- Reservations List -->
            <div v-if="reservations.length" class="space-y-3">
                <div
                    v-for="res in reservations"
                    :key="res.id"
                    class="p-4 rounded-2xl border transition-all"
                    :class="res.status === 'seated'
                        ? 'bg-emerald-50/60 dark:bg-emerald-950/20 border-emerald-300 dark:border-emerald-800'
                        : 'bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-700'"
                >
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-2">
                            <span class="font-black text-sm text-slate-900 dark:text-white">{{ res.guest_name }}</span>
                            <span class="px-2 py-0.5 rounded-md text-[10px] font-mono font-bold bg-amber-500/20 text-amber-600 dark:text-amber-400">
                                {{ res.code }}
                            </span>
                        </div>
                        <span class="text-xs font-mono font-bold text-slate-600 dark:text-slate-300">
                            ⏰ {{ res.time_slot }}
                        </span>
                    </div>

                    <div class="flex items-center gap-3 text-xs text-slate-500 dark:text-slate-400 mb-3">
                        <span>🍽️ {{ res.table_name || ('طاولة #' + res.table_id) }}</span>
                        <span>👥 {{ res.guests_count }} ضيوف</span>
                        <span v-if="res.guest_phone" dir="ltr" class="font-mono">{{ res.guest_phone }}</span>
                    </div>

                    <div v-if="res.special_requests" class="text-xs text-slate-600 dark:text-slate-300 bg-white/70 dark:bg-slate-900/70 p-2.5 rounded-xl border border-slate-100 dark:border-slate-800 mb-3 italic">
                        "{{ res.special_requests }}"
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            v-if="res.status !== 'seated'"
                            type="button"
                            @click="emit('seat-reservation', res)"
                            class="flex-1 py-2 px-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition flex items-center justify-center gap-1 cursor-pointer"
                        >
                            <span>🚶‍♂️</span>
                            <span>تسجيل وصول الضيف</span>
                        </button>
                        <span v-else class="text-xs font-bold text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                            <span>✓</span>
                            <span>تم جلوس الضيف في الصالة</span>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="text-center py-12 px-4">
                <span class="text-4xl">🎫</span>
                <h4 class="font-bold text-slate-700 dark:text-slate-300 text-sm mt-3">لا توجد حجوزات VIP اليوم</h4>
                <p class="text-xs text-slate-400 mt-1">شارك رابط الحجز المسبق لتمكين عملائك من اختيار طاولاتهم بصرياً.</p>
            </div>
        </div>

        <!-- Footer -->
        <div class="pt-4 border-t border-slate-100 dark:border-slate-800 space-y-3">
            <div
                v-if="!isWhatsappConnected"
                class="p-3 rounded-xl bg-amber-50 dark:bg-amber-950/50 border border-amber-500/40 text-amber-900 dark:text-amber-200 text-xs font-semibold space-y-2"
            >
                <div>⚠️ تنبيه: يجب ربط «{{ restaurantName }}» برقم واتساب أولاً قبل مشاركة رابط الحجز المسبق ليتمكن الزبائن من استلام رمز التحقق (OTP).</div>
                <Link
                    v-if="isAdmin"
                    href="/admin/customers?connect_whatsapp=1"
                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-400 text-stone-950 text-[11px] font-black transition"
                >
                    <span>ربط رقم الواتساب الآن</span>
                    <span>←</span>
                </Link>
                <div
                    v-else
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/15 border border-amber-500/40 text-amber-900 dark:text-amber-200 text-[11px] font-bold"
                >
                    <span>🔒</span>
                    <span>ربط الواتساب صلاحية خاصة بمدير المطعم (admin) فقط — يرجى الطلب من المدير تفعيله.</span>
                </div>
            </div>
            <button
                type="button"
                @click="emit('copy-link')"
                class="w-full py-3 rounded-xl bg-amber-500 hover:bg-amber-400 text-stone-950 font-black text-xs sm:text-sm shadow-md transition flex items-center justify-center gap-2 cursor-pointer"
            >
                <span>🔗</span>
                <span>{{ isCopiedLink ? 'تم نسخ الرابط بنجاح!' : 'نسخ رابط حجز VIP للعملاء' }}</span>
            </button>
        </div>
    </div>
</template>

<style scoped>
@keyframes slideLeft {
    from { transform: translateX(100%); }
    to { transform: translateX(0); }
}

.animate-slideLeft {
    animation: slideLeft 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
</style>
