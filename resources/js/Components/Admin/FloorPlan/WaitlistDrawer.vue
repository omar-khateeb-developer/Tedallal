<script setup lang="ts">
import { computed, ref } from 'vue';
import axios from 'axios';
import { getFloorZoneLabel, TABLE_STAGE_LABELS, type TableItem, type WaitlistItem } from '@/types/floorPlan';

const props = defineProps<{
    waitlist: WaitlistItem[];
    tables: TableItem[];
    restaurantSlug: string;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'update:waitlist', value: WaitlistItem[]): void;
    (e: 'table-seated', table: TableItem): void;
    (e: 'saved-toast'): void;
}>();

const isAddModalOpen = ref(false);
const isSeatModalOpen = ref(false);
const seatTargetEntry = ref<WaitlistItem | null>(null);
const selectedTableIdForSeating = ref<number | null>(null);
const isSubmittingWaitlist = ref(false);
const isCopiedWaitlistLink = ref(false);

const newWaitlistForm = ref({
    guest_name: '',
    guest_phone: '',
    party_size: 2,
    seating_preference: 'any',
    estimated_wait_minutes: 15,
    notes: '',
});

const activeWaitingCount = computed(() =>
    props.waitlist.filter(e => e.status === 'waiting' || e.status === 'notified').length,
);

function getTableStageLabel(table: TableItem): string {
    if (table.operational_stage && TABLE_STAGE_LABELS[table.operational_stage]) {
        return TABLE_STAGE_LABELS[table.operational_stage];
    }
    if (table.active_session) return 'جلسة نشطة';
    return 'شاغرة';
}

async function refreshWaitlist() {
    try {
        const { data } = await axios.get('/api/admin/waitlist');
        emit('update:waitlist', data.data.entries);
    } catch (e) {
        console.error('Waitlist fetch error:', e);
    }
}

async function addWaitlistEntry() {
    if (!newWaitlistForm.value.guest_name.trim() || !newWaitlistForm.value.guest_phone.trim()) return;
    isSubmittingWaitlist.value = true;
    try {
        await axios.post('/api/admin/waitlist', newWaitlistForm.value);
        await refreshWaitlist();
        isAddModalOpen.value = false;
        newWaitlistForm.value = {
            guest_name: '',
            guest_phone: '',
            party_size: 2,
            seating_preference: 'any',
            estimated_wait_minutes: 15,
            notes: '',
        };
        emit('saved-toast');
    } catch (e: any) {
        alert(e.response?.data?.message || 'تعذر إضافة الزبون للانتظار');
    } finally {
        isSubmittingWaitlist.value = false;
    }
}

async function notifyWaitlistGuest(entry: WaitlistItem) {
    try {
        await axios.patch(`/api/admin/waitlist/${entry.id}/notify`);
        entry.status = 'notified';
        entry.notified_at = new Date().toISOString();
        emit('saved-toast');
    } catch (e: any) {
        alert(e.response?.data?.message || 'تعذر إرسال إشعار الواتساب للزبون');
    }
}

function openSeatModal(entry: WaitlistItem) {
    seatTargetEntry.value = entry;
    const available = props.tables.find(t => (t.operational_stage === 'available' || !t.active_session) && t.capacity >= entry.party_size);
    selectedTableIdForSeating.value = available ? available.id : (props.tables[0]?.id || null);
    isSeatModalOpen.value = true;
}

async function confirmSeating() {
    if (!seatTargetEntry.value || !selectedTableIdForSeating.value) return;
    try {
        const { data } = await axios.patch(`/api/admin/waitlist/${seatTargetEntry.value.id}/seat`, {
            table_id: selectedTableIdForSeating.value,
        });
        seatTargetEntry.value.status = 'seated';
        emit('table-seated', data.data.table);
        isSeatModalOpen.value = false;
        seatTargetEntry.value = null;
        await refreshWaitlist();
        emit('saved-toast');
    } catch (e: any) {
        alert(e.response?.data?.message || 'تعذر إجلاس الزبون على الطاولة');
    }
}

async function cancelWaitlistEntry(entry: WaitlistItem) {
    if (!confirm(`هل أنت متأكد من إلغاء دور ${entry.guest_name}؟`)) return;
    try {
        await axios.patch(`/api/admin/waitlist/${entry.id}/status`, { status: 'cancelled' });
        entry.status = 'cancelled';
        await refreshWaitlist();
    } catch (e: any) {
        alert(e.response?.data?.message || 'تعذر إلغاء الدور');
    }
}

function copyWaitlistJoinLink() {
    const url = `${window.location.origin}/r/${props.restaurantSlug}/waitlist`;
    navigator.clipboard.writeText(url).then(() => {
        isCopiedWaitlistLink.value = true;
        setTimeout(() => { isCopiedWaitlistLink.value = false; }, 3000);
    });
}
</script>

<template>
    <div
        class="fixed inset-y-0 right-0 z-50 w-full sm:w-[480px] bg-white dark:bg-slate-900 shadow-2xl border-l border-slate-200 dark:border-slate-800 p-6 flex flex-col justify-between overflow-y-auto animate-slideLeft"
        dir="rtl"
    >
        <div class="space-y-5">
            <!-- Drawer Header -->
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-4">
                <div class="flex items-center gap-2.5">
                    <div class="w-10 h-10 rounded-2xl bg-blue-500/20 border border-blue-500/40 text-blue-400 flex items-center justify-center text-xl shadow-xs">
                        📋
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-lg font-black text-slate-900 dark:text-white">طابور الانتظار الذكي</h3>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-500/20 text-blue-400 border border-blue-500/30">
                                {{ activeWaitingCount }} بالانتظار
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400">إشعارات الواتساب الفورية ومتابعة الأدوار</p>
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

            <!-- Top Action Controls -->
            <div class="grid grid-cols-2 gap-2">
                <button
                    type="button"
                    @click="isAddModalOpen = true"
                    class="py-2.5 px-3 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-black text-xs shadow-md shadow-blue-600/20 flex items-center justify-center gap-1.5 transition cursor-pointer"
                >
                    <span>➕</span>
                    <span>إضافة زبون (Walk-in)</span>
                </button>
                <button
                    type="button"
                    @click="copyWaitlistJoinLink"
                    class="py-2.5 px-3 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-xs border border-slate-200 dark:border-slate-700 flex items-center justify-center gap-1.5 transition cursor-pointer"
                >
                    <span>🔗</span>
                    <span>{{ isCopiedWaitlistLink ? 'تم نسخ الرابط!' : 'رابط QR للمدخل' }}</span>
                </button>
            </div>

            <!-- Queue List -->
            <div v-if="waitlist.length" class="space-y-3">
                <div
                    v-for="entry in waitlist"
                    :key="entry.id"
                    class="p-4 rounded-2xl border transition-all"
                    :class="[
                        entry.status === 'notified'
                            ? 'bg-emerald-50/70 dark:bg-emerald-950/30 border-emerald-300 dark:border-emerald-700 shadow-sm'
                            : (entry.status === 'seated'
                                ? 'bg-slate-50 dark:bg-slate-800/30 border-slate-200 dark:border-slate-800 opacity-60'
                                : 'bg-white dark:bg-slate-800/60 border-slate-200 dark:border-slate-700/80 shadow-xs')
                    ]"
                >
                    <div class="flex items-start justify-between gap-2 mb-2">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-black text-sm text-slate-900 dark:text-white">{{ entry.guest_name }}</span>
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-mono font-black bg-blue-500/20 text-blue-600 dark:text-blue-300">
                                    #{{ entry.queue_number }}
                                </span>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 flex items-center gap-2">
                                <span>👥 {{ entry.party_size }} أفراد</span>
                                <span>•</span>
                                <span dir="ltr" class="font-mono">{{ entry.guest_phone }}</span>
                            </p>
                        </div>

                        <div class="text-end">
                            <span
                                class="inline-block px-2.5 py-1 rounded-full text-[10px] font-bold font-mono"
                                :class="[
                                    entry.status === 'notified'
                                        ? 'bg-emerald-500 text-slate-950 font-black animate-pulse'
                                        : (entry.status === 'seated'
                                            ? 'bg-slate-700 text-slate-300'
                                            : 'bg-amber-500/20 text-amber-600 dark:text-amber-400 border border-amber-500/40')
                                ]"
                            >
                                {{ entry.status === 'notified' ? '🔔 تم إشعار الجاهزية' : (entry.status === 'seated' ? '✓ تم الجلوس' : '⏱️ بانتظار دور') }}
                            </span>
                            <span class="block text-[10px] text-slate-400 font-mono mt-0.5">
                                ينتظر منذ {{ entry.waiting_minutes }}د
                            </span>
                        </div>
                    </div>

                    <div v-if="entry.notes" class="text-xs text-slate-600 dark:text-slate-300 bg-slate-50 dark:bg-slate-900/60 p-2 rounded-xl mb-3 border border-slate-100 dark:border-slate-800 italic">
                        "{{ entry.notes }}"
                    </div>

                    <div v-if="entry.status === 'waiting' || entry.status === 'notified'" class="flex items-center gap-2 pt-1 border-t border-slate-100 dark:border-slate-800">
                        <button
                            type="button"
                            @click="notifyWaitlistGuest(entry)"
                            class="flex-1 py-2 px-2.5 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1 cursor-pointer"
                            :class="entry.status === 'notified'
                                ? 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200'
                                : 'bg-emerald-600 hover:bg-emerald-500 text-white shadow-xs'"
                        >
                            <span>🔔</span>
                            <span>{{ entry.status === 'notified' ? 'إعادة الإشعار' : 'إشعار بالجاهزية' }}</span>
                        </button>
                        <button
                            type="button"
                            @click="openSeatModal(entry)"
                            class="flex-1 py-2 px-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-stone-950 text-xs font-black transition flex items-center justify-center gap-1 shadow-xs cursor-pointer"
                        >
                            <span>🪑</span>
                            <span>تسكين على طاولة</span>
                        </button>
                        <button
                            type="button"
                            @click="cancelWaitlistEntry(entry)"
                            class="p-2 rounded-xl text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition cursor-pointer"
                            title="إلغاء الدور"
                        >
                            ✕
                        </button>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="text-center py-12 px-4 bg-slate-50 dark:bg-slate-800/30 rounded-3xl border border-dashed border-slate-200 dark:border-slate-800">
                <span class="text-4xl">🌟</span>
                <h4 class="font-bold text-slate-800 dark:text-slate-200 text-sm mt-3">لا يوجد زبائن في الانتظار حالياً</h4>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-xs mx-auto leading-relaxed">
                    عندما تمتلئ الصالة، يمكنك إضافة الزوار الوافدين بنقرة زر أو مشاركة رمز الـ QR مع الاستقبال.
                </p>
            </div>
        </div>

        <!-- Footer Link Copy -->
        <div class="pt-4 border-t border-slate-100 dark:border-slate-800">
            <button
                type="button"
                @click="copyWaitlistJoinLink"
                class="w-full py-3 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 font-bold text-xs transition flex items-center justify-center gap-2 cursor-pointer"
            >
                <span>📲</span>
                <span>{{ isCopiedWaitlistLink ? 'تم نسخ الرابط بنجاح!' : 'نسخ رابط تسجيل الانتظار الذاتي' }}</span>
            </button>
        </div>

        <!-- Nested Add Walk-in Guest Modal -->
        <div
            v-if="isAddModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs animate-fadeIn"
            dir="rtl"
            @click.self="isAddModalOpen = false"
        >
            <div class="w-full max-w-md bg-white dark:bg-slate-900 rounded-3xl p-6 shadow-2xl border border-slate-200 dark:border-slate-800 space-y-4" @click.stop>
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                    <h3 class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                        <span>➕</span>
                        <span>إضافة زبون إلى قائمة الانتظار</span>
                    </h3>
                    <button @click="isAddModalOpen = false" type="button" class="text-slate-400 hover:text-slate-600 cursor-pointer">✕</button>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">اسم الزبون *</label>
                        <input
                            v-model="newWaitlistForm.guest_name"
                            type="text"
                            placeholder="مثال: المهندس وسيم"
                            class="w-full px-3.5 py-2.5 rounded-xl text-sm border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-hidden"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">رقم الواتساب (لإرسال إشعار الطاولة) *</label>
                        <input
                            v-model="newWaitlistForm.guest_phone"
                            type="tel"
                            placeholder="09xxxxxxxx"
                            dir="ltr"
                            class="w-full px-3.5 py-2.5 rounded-xl text-sm text-right border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-hidden"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">عدد الأفراد</label>
                            <input
                                v-model.number="newWaitlistForm.party_size"
                                type="number"
                                min="1"
                                max="30"
                                class="w-full px-3.5 py-2.5 rounded-xl text-sm border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-hidden"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">الوقت التقديري (دقيقة)</label>
                            <input
                                v-model.number="newWaitlistForm.estimated_wait_minutes"
                                type="number"
                                min="1"
                                max="120"
                                class="w-full px-3.5 py-2.5 rounded-xl text-sm border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-hidden"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">تفضيل الجلسة</label>
                        <select
                            v-model="newWaitlistForm.seating_preference"
                            class="w-full px-3.5 py-2.5 rounded-xl text-sm border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-hidden"
                        >
                            <option value="any">أي طاولة متاحة أولاً</option>
                            <option value="indoor">صالة داخلية</option>
                            <option value="terrace">تراس خارجي</option>
                            <option value="family">قسم عائلي</option>
                            <option value="vip">جناح VIP</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">ملاحظات إضافية</label>
                        <input
                            v-model="newWaitlistForm.notes"
                            type="text"
                            placeholder="كرسي أطفال، طاولة هادئة..."
                            class="w-full px-3.5 py-2 rounded-xl text-xs border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-hidden"
                        />
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <button
                        type="button"
                        :disabled="isSubmittingWaitlist"
                        @click="addWaitlistEntry"
                        class="flex-1 py-3 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-black text-sm shadow-md transition cursor-pointer disabled:opacity-50"
                    >
                        {{ isSubmittingWaitlist ? 'جاري التسجيل...' : 'تسجيل وإرسال إشعار الواتساب' }}
                    </button>
                    <button
                        type="button"
                        @click="isAddModalOpen = false"
                        class="px-5 py-3 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold text-sm cursor-pointer"
                    >
                        إلغاء
                    </button>
                </div>
            </div>
        </div>

        <!-- Nested Seat Guest Modal -->
        <div
            v-if="isSeatModalOpen && seatTargetEntry"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs animate-fadeIn"
            dir="rtl"
            @click.self="isSeatModalOpen = false"
        >
            <div class="w-full max-w-sm bg-white dark:bg-slate-900 rounded-3xl p-6 shadow-2xl border border-slate-200 dark:border-slate-800 space-y-4" @click.stop>
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                    <h3 class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                        <span>🪑</span>
                        <span>تسكين الزبون على طاولة</span>
                    </h3>
                    <button @click="isSeatModalOpen = false" type="button" class="text-slate-400 hover:text-slate-600 cursor-pointer">✕</button>
                </div>

                <div class="p-3 bg-blue-50 dark:bg-blue-950/40 rounded-xl border border-blue-200 dark:border-blue-800/60 text-xs text-blue-900 dark:text-blue-200">
                    الزبون: <span class="font-black">{{ seatTargetEntry.guest_name }}</span> ({{ seatTargetEntry.party_size }} أفراد)
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-2">اختر الطاولة لتسكين الزبون عليها:</label>
                    <div class="max-h-56 overflow-y-auto space-y-1.5 pr-1">
                        <label
                            v-for="tbl in tables"
                            :key="tbl.id"
                            class="flex items-center justify-between p-3 rounded-xl border cursor-pointer transition"
                            :class="selectedTableIdForSeating === tbl.id
                                ? 'bg-amber-50 dark:bg-amber-950/40 border-amber-500 ring-2 ring-amber-500/30'
                                : 'bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-700 hover:border-slate-400'"
                        >
                            <div class="flex items-center gap-2.5">
                                <input
                                    type="radio"
                                    :value="tbl.id"
                                    v-model="selectedTableIdForSeating"
                                    class="text-amber-500 focus:ring-amber-500"
                                />
                                <span class="font-bold text-sm text-slate-900 dark:text-white">{{ tbl.name }}</span>
                                <span class="text-xs text-slate-400">({{ tbl.capacity }} مقاعد - {{ getFloorZoneLabel(tbl.zone) }})</span>
                            </div>
                            <span class="text-[10px] px-2 py-0.5 rounded-full font-mono" :class="tbl.operational_stage === 'available' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-slate-700 text-slate-300'">
                                {{ getTableStageLabel(tbl) }}
                            </span>
                        </label>
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <button
                        type="button"
                        @click="confirmSeating"
                        class="flex-1 py-3 rounded-xl bg-amber-500 hover:bg-amber-400 text-stone-950 font-black text-sm shadow-md transition cursor-pointer"
                    >
                        تأكيد التسكين والجلوس
                    </button>
                    <button
                        type="button"
                        @click="isSeatModalOpen = false"
                        class="px-5 py-3 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold text-sm cursor-pointer"
                    >
                        إلغاء
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes fadeIn {
    from { opacity: 0; transform: scale(0.98); }
    to { opacity: 1; transform: scale(1); }
}

@keyframes slideLeft {
    from { transform: translateX(100%); }
    to { transform: translateX(0); }
}

.animate-fadeIn {
    animation: fadeIn 0.2s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.animate-slideLeft {
    animation: slideLeft 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
</style>
