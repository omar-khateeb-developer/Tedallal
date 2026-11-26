<script setup lang="ts">
import { ref } from 'vue';
import type { ReservationItem, TableItem } from '@/types/floorPlan';

defineProps<{
    table: TableItem;
    reservation?: ReservationItem;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'set-stage', table: TableItem, stage: string): void;
    (e: 'rotate'): void;
    (e: 'duplicate'): void;
    (e: 'attend-call', table: TableItem): void;
    (e: 'close-session', table: TableItem): void;
    (e: 'seat-reservation', reservation: ReservationItem): void;
    (e: 'toggle-status', table: TableItem): void;
    (e: 'regenerate-qr', table: TableItem): void;
    (e: 'save'): void;
    (e: 'delete'): void;
}>();

const isQrCopied = ref(false);

function copyTableQrLink(table: TableItem) {
    const url = table.public_url || `${window.location.origin}/t/${table.public_token}`;
    navigator.clipboard.writeText(url).then(() => {
        isQrCopied.value = true;
        setTimeout(() => { isQrCopied.value = false; }, 2500);
    });
}

const stages = [
    { key: 'available', label: 'شاغرة', icon: '🟢' },
    { key: 'seated', label: 'جلوس', icon: '🪑' },
    { key: 'preparing', label: 'تحضير', icon: '🍳' },
    { key: 'served', label: 'تقديم', icon: '🍽️' },
    { key: 'bill_requested', label: 'حساب', icon: '🧾' },
    { key: 'cleaning', label: 'تنظيف', icon: '🧹' },
];
</script>

<template>
    <div
        class="fixed inset-y-0 left-0 z-50 w-full sm:w-96 bg-white dark:bg-slate-900 shadow-2xl border-r border-slate-200 dark:border-slate-800 p-6 flex flex-col justify-between overflow-y-auto animate-slideRight"
        dir="rtl"
    >
        <div class="space-y-6">
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-4">
                <div>
                    <h3 class="text-lg font-extrabold text-slate-900 dark:text-white">إعدادات الطاولة</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ table.name }}</p>
                </div>
                <button
                    @click="emit('close')"
                    type="button"
                    class="p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer"
                >
                    ✕
                </button>
            </div>

            <!-- Form Fields -->
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">اسم أو رقم الطاولة</label>
                    <input
                        v-model="table.name"
                        type="text"
                        class="w-full px-3.5 py-2.5 rounded-xl text-sm border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-amber-500"
                    />
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">شكل الطاولة الهندسي</label>
                    <select
                        v-model="table.shape"
                        class="w-full px-3.5 py-2.5 rounded-xl text-sm border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-amber-500"
                    >
                        <option value="circle">دائرية (Standard Circle)</option>
                        <option value="rectangle">مستطيلة (Family Rectangle)</option>
                        <option value="booth">كابينة خاصة (Booth Seat)</option>
                        <option value="vip">جناح فاخر (VIP Lounge)</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">سعة المقاعد</label>
                        <input
                            v-model.number="table.capacity"
                            type="number"
                            min="1"
                            max="30"
                            class="w-full px-3.5 py-2.5 rounded-xl text-sm border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-amber-500"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">المنطقة</label>
                        <select
                            v-model="table.zone"
                            class="w-full px-3.5 py-2.5 rounded-xl text-sm border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-amber-500"
                        >
                            <option value="main">الصالة الرئيسية</option>
                            <option value="vip">جناح النخبة (VIP Lounge)</option>
                            <option value="terrace">الشرفة الخارجية</option>
                            <option value="family">القسم العائلي</option>
                        </select>
                    </div>
                </div>

                <!-- Table Operational Stage -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">المرحلة التشغيلية للطاولة</label>
                    <div class="grid grid-cols-3 gap-1.5">
                        <button
                            v-for="stg in stages"
                            :key="stg.key"
                            type="button"
                            @click="emit('set-stage', table, stg.key)"
                            class="py-2 px-1 rounded-xl text-xs font-bold border transition-all cursor-pointer flex items-center justify-center gap-1"
                            :class="table.operational_stage === stg.key ? 'bg-amber-500 text-stone-950 border-amber-400 font-black shadow-xs' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700 hover:border-slate-400'"
                        >
                            <span>{{ stg.icon }}</span>
                            <span>{{ stg.label }}</span>
                        </button>
                    </div>
                </div>

                <!-- Rotation & Quick Actions -->
                <div class="grid grid-cols-2 gap-2 pt-1">
                    <button
                        type="button"
                        @click="emit('rotate')"
                        class="py-2.5 px-3 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 text-xs font-bold flex items-center justify-center gap-1.5 transition cursor-pointer"
                    >
                        <span>🔄</span>
                        <span>تدوير ({{ table.rotation || 0 }}°)</span>
                    </button>
                    <button
                        type="button"
                        @click="emit('duplicate')"
                        class="py-2.5 px-3 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 text-xs font-bold flex items-center justify-center gap-1.5 transition cursor-pointer"
                    >
                        <span>📋</span>
                        <span>تكرار الطاولة</span>
                    </button>
                </div>

                <!-- QR Card & Availability Binding -->
                <div class="bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/80 rounded-2xl p-4 space-y-3">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-1.5">
                            <span class="text-base">📱</span>
                            <span class="text-xs font-extrabold text-slate-800 dark:text-slate-200">بطاقة QR وحالة الطاولة</span>
                        </div>
                        <button
                            type="button"
                            @click="emit('toggle-status', table)"
                            :class="[
                                'px-2.5 py-1 rounded-full text-[11px] font-black border transition cursor-pointer',
                                table.status === 'active'
                                    ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-500/40 hover:bg-emerald-500/25'
                                    : 'bg-rose-500/15 text-rose-700 dark:text-rose-300 border-rose-500/40 hover:bg-rose-500/25'
                            ]"
                            title="تفعيل أو تعطيل مسح QR لهذه الطاولة"
                        >
                            {{ table.status === 'active' ? '🟢 مفعّلة (انقر للتعطيل)' : '🚫 معطّلة (انقر للتفعيل)' }}
                        </button>
                    </div>

                    <div class="flex items-center gap-3">
                        <div class="w-16 h-16 rounded-xl bg-white p-1 border border-slate-200 dark:border-slate-700 shrink-0 shadow-2xs">
                            <img :src="table.qr_url" :alt="`QR ${table.name}`" class="w-full h-full object-contain" />
                        </div>
                        <div class="grid grid-cols-2 gap-1.5 flex-1 text-[11px] font-bold">
                            <a
                                :href="route('admin.tables.print', { table_id: table.id })"
                                target="_blank"
                                class="py-1.5 px-2 rounded-lg bg-amber-500/15 hover:bg-amber-500/25 text-amber-800 dark:text-amber-300 border border-amber-500/30 text-center transition"
                            >
                                🖨️ طباعة
                            </a>
                            <button
                                type="button"
                                @click="copyTableQrLink(table)"
                                class="py-1.5 px-2 rounded-lg bg-white dark:bg-slate-900 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 transition cursor-pointer"
                            >
                                {{ isQrCopied ? '✓ تم النسخ' : '🔗 نسخ' }}
                            </button>
                            <button
                                type="button"
                                @click="emit('regenerate-qr', table)"
                                class="py-1.5 px-2 rounded-lg bg-white dark:bg-slate-900 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 transition cursor-pointer"
                            >
                                🔄 QR جديد
                            </button>
                            <a
                                :href="`/t/${table.public_token}`"
                                target="_blank"
                                class="py-1.5 px-2 rounded-lg bg-white dark:bg-slate-900 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 text-center transition"
                            >
                                👁️ معاينة
                            </a>
                        </div>
                    </div>
                </div>

                <!-- IoT Chipset Binding -->
                <div class="bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/80 rounded-2xl p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-base">💡</span>
                        <label class="text-xs font-extrabold text-slate-800 dark:text-slate-200">
                            معرّف شريحة الإضاءة الذكية (ESP32)
                        </label>
                    </div>
                    <input
                        v-model="table.iot_device_id"
                        type="text"
                        dir="ltr"
                        placeholder="e.g. ESP32_TBL_08 أو MAC"
                        class="w-full px-3 py-2 rounded-xl text-xs font-mono border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:ring-2 focus:ring-cyan-500"
                    />
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1.5">
                        أدخل معرّف الجهاز لمزامنة إضاءة الليدات الفيزيائية مع حالة هذه الطاولة.
                    </p>
                </div>

                <!-- Active Service Call Alert Card -->
                <div
                    v-if="table.has_waiter_call || table.operational_stage === 'bill_requested' || table.service_call"
                    class="p-3.5 rounded-2xl border space-y-2.5 shadow-md"
                    :class="table.operational_stage === 'bill_requested'
                        ? 'bg-purple-950/40 border-purple-500/60 text-purple-200'
                        : 'bg-amber-950/40 border-amber-500/60 text-amber-200'"
                >
                    <div class="flex items-center justify-between text-xs font-black">
                        <span class="flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-full bg-current animate-ping"></span>
                            <span>{{ table.operational_stage === 'bill_requested' ? '🧾 طلب حساب وفاتورة' : '🛎️ استدعاء نادل مباشر' }}</span>
                        </span>
                        <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-black/40">بانتظار التلبية</span>
                    </div>

                    <p v-if="table.service_call?.reason || table.service_call?.notes || table.service_call?.payment_method" class="text-xs text-stone-300">
                        {{ table.service_call?.reason || (table.service_call?.payment_method === 'card' ? '💳 دفع بالبطاقة / مدى' : (table.service_call?.payment_method === 'cash' ? '💵 دفع نقداً' : '')) || table.service_call?.notes }}
                    </p>

                    <button
                        type="button"
                        @click="emit('attend-call', table)"
                        class="w-full py-2 px-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-black shadow-md active:scale-95 transition flex items-center justify-center gap-1.5 cursor-pointer"
                    >
                        <span>✓ تسجيل تمت التلبية</span>
                    </button>
                </div>

                <!-- Active Session Card -->
                <div v-if="table.active_session" class="p-3.5 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 space-y-2">
                    <div class="flex items-center justify-between text-xs font-bold text-emerald-800 dark:text-emerald-300">
                        <span class="flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span>جلسة نشطة حالياً</span>
                        </span>
                        <span class="text-[10px] font-mono opacity-80">معرّف: #{{ table.active_session.id }}</span>
                    </div>
                    <button
                        type="button"
                        @click="emit('close-session', table)"
                        class="w-full py-2 px-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold shadow-xs transition cursor-pointer"
                    >
                        🧹 إخلاء الطاولة وإنهاء الجلسة
                    </button>
                </div>

                <!-- VIP Booking Card -->
                <div v-if="reservation" class="p-3.5 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 space-y-2">
                    <div class="flex items-center justify-between text-xs font-bold text-amber-800 dark:text-amber-300">
                        <span class="flex items-center gap-1.5">
                            <span>👑</span>
                            <span>حجز VIP مؤكد اليوم</span>
                        </span>
                        <span class="text-[10px] font-mono">{{ reservation.time_slot }}</span>
                    </div>
                    <p class="text-xs text-amber-900 dark:text-amber-200 font-semibold">
                        الضيف: {{ reservation.guest_name }} ({{ reservation.guests_count }} أشخاص)
                    </p>
                    <button
                        v-if="reservation.status !== 'seated'"
                        type="button"
                        @click="emit('seat-reservation', reservation)"
                        class="w-full py-2 px-3 rounded-xl bg-amber-500 hover:bg-amber-400 text-stone-950 text-xs font-bold shadow-xs transition cursor-pointer"
                    >
                        🚶‍♂️ تسجيل وصول الضيف والجلوس
                    </button>
                </div>
            </div>
        </div>

        <!-- Bottom Drawer Buttons -->
        <div class="pt-6 border-t border-slate-100 dark:border-slate-800 space-y-2">
            <button
                type="button"
                @click="emit('save')"
                class="w-full py-3 rounded-xl bg-amber-500 hover:bg-amber-400 text-stone-950 font-black text-sm shadow-md transition cursor-pointer"
            >
                حفظ تعديلات الطاولة
            </button>
            <button
                type="button"
                @click="emit('delete')"
                class="w-full py-2.5 rounded-xl bg-rose-50 hover:bg-rose-100 dark:bg-rose-950/40 dark:hover:bg-rose-950/60 text-rose-600 dark:text-rose-400 font-bold text-xs transition cursor-pointer"
            >
                حذف الطاولة من المخطط
            </button>
        </div>
    </div>
</template>

<style scoped>
@keyframes slideRight {
    from { transform: translateX(-100%); }
    to { transform: translateX(0); }
}

.animate-slideRight {
    animation: slideRight 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
</style>
