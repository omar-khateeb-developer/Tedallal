<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import type { CustomerRow, PromoItem, WaConnectionStatus } from '@/types/customers';

const props = defineProps<{
    pendingReminderCustomer: CustomerRow | null;
    pendingReminderPromo: PromoItem | null;
    autoConnect?: boolean;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'status-changed', payload: { status: WaConnectionStatus; phone: string | null }): void;
    (e: 'connected-with-pending', payload: { customer: CustomerRow; promo: PromoItem }): void;
    (e: 'force-manual'): void;
}>();

const waLoading = ref(false);
const waStatus = ref<WaConnectionStatus>('disconnected');
const waQrCode = ref<string | null>(null);
const waPhone = ref<string | null>(null);
const waAutoReplyEnabled = ref(false);
const waAutoReplyMessage = ref('');
const waExpiringRemindersEnabled = ref(true);
const waSavingSettings = ref(false);
const waSettingsNotice = ref<string | null>(null);

const waTestPhone = ref('');
const waSendingTest = ref(false);
const waTestNotice = ref<string | null>(null);
const waTestError = ref<string | null>(null);
const waConnectSuccessCelebration = ref(false);

let waPollingTimer: ReturnType<typeof setInterval> | null = null;
let celebrationTimer: ReturnType<typeof setTimeout> | null = null;

function stopWaPolling() {
    if (waPollingTimer) {
        clearInterval(waPollingTimer);
        waPollingTimer = null;
    }
}

function startWaPolling() {
    if (waPollingTimer) return;
    waPollingTimer = setInterval(async () => {
        try {
            const res = await axios.get('/api/admin/whatsapp/status');
            const data = res.data.data;
            waStatus.value = data.status;
            waQrCode.value = data.qr_code;
            waPhone.value = data.phone;
            emit('status-changed', { status: waStatus.value, phone: waPhone.value });

            if (data.status === 'connected') {
                stopWaPolling();
                router.reload({ only: ['auth'] });

                if (props.pendingReminderCustomer && props.pendingReminderPromo) {
                    waConnectSuccessCelebration.value = true;
                    const customer = props.pendingReminderCustomer;
                    const promo = props.pendingReminderPromo;
                    celebrationTimer = setTimeout(() => {
                        waConnectSuccessCelebration.value = false;
                        emit('connected-with-pending', { customer, promo });
                    }, 1500);
                }
            }
        } catch (_) {}
    }, 3000);
}

async function fetchStatus() {
    try {
        const res = await axios.get('/api/admin/whatsapp/status');
        const data = res.data.data;
        waStatus.value = data.status || 'disconnected';
        waQrCode.value = data.qr_code || null;
        waPhone.value = data.phone || null;
        waAutoReplyEnabled.value = Boolean(data.auto_reply_enabled);
        waAutoReplyMessage.value = data.auto_reply_message || '';
        waExpiringRemindersEnabled.value = data.expiring_reminders_enabled !== false;
        emit('status-changed', { status: waStatus.value, phone: waPhone.value });

        if (waStatus.value === 'scan_qr' || waStatus.value === 'connecting') {
            startWaPolling();
        }
    } catch (_) {}
}

async function connectWhatsapp() {
    waLoading.value = true;
    try {
        const res = await axios.post('/api/admin/whatsapp/connect');
        const data = res.data.data;
        waStatus.value = data.status;
        waQrCode.value = data.qr_code;
        waPhone.value = data.phone;
        emit('status-changed', { status: waStatus.value, phone: waPhone.value });
        if (waStatus.value === 'scan_qr' || waStatus.value === 'connecting') {
            startWaPolling();
        }
    } catch (e: any) {
        alert(e.response?.data?.message || 'تعذر بدء جلسة الواتساب');
    } finally {
        waLoading.value = false;
    }
}

async function disconnectWhatsapp() {
    if (!confirm('هل أنت متأكد من فصل رقم واتساب المطعم؟ لن يتم إرسال الرسائل والتذكيرات تلقائياً حتى تعيد ربطه.')) return;
    waLoading.value = true;
    try {
        await axios.post('/api/admin/whatsapp/disconnect');
        waStatus.value = 'disconnected';
        waQrCode.value = null;
        waPhone.value = null;
        emit('status-changed', { status: 'disconnected', phone: null });
        stopWaPolling();
    } catch (e: any) {
        alert(e.response?.data?.message || 'تعذر فصل الجلسة');
    } finally {
        waLoading.value = false;
    }
}

async function saveWhatsappSettings() {
    waSavingSettings.value = true;
    waSettingsNotice.value = null;
    try {
        await axios.post('/api/admin/whatsapp/settings', {
            whatsapp_auto_reply_enabled: waAutoReplyEnabled.value,
            whatsapp_auto_reply_message: waAutoReplyMessage.value,
            whatsapp_expiring_reminders_enabled: waExpiringRemindersEnabled.value,
        });
        waSettingsNotice.value = 'تم حفظ إعدادات الأتمتة بنجاح ✓';
        setTimeout(() => { waSettingsNotice.value = null; }, 3500);
    } catch (e: any) {
        alert(e.response?.data?.message || 'تعذر حفظ الإعدادات');
    } finally {
        waSavingSettings.value = false;
    }
}

async function sendTestMessage() {
    if (!waTestPhone.value) return;
    waSendingTest.value = true;
    waTestNotice.value = null;
    waTestError.value = null;
    try {
        const res = await axios.post('/api/admin/whatsapp/send-test', {
            phone: waTestPhone.value,
        });
        waTestNotice.value = res.data.data?.message || 'تم الإرسال بنجاح ✓';
    } catch (e: any) {
        waTestError.value = e.response?.data?.message || 'تعذر إرسال الرسالة التجريبية.';
    } finally {
        waSendingTest.value = false;
    }
}

onMounted(async () => {
    await fetchStatus();
    if (props.autoConnect && waStatus.value === 'disconnected') {
        await connectWhatsapp();
    }
});

onUnmounted(() => {
    stopWaPolling();
    if (celebrationTimer) {
        clearTimeout(celebrationTimer);
        celebrationTimer = null;
    }
});
</script>

<template>
    <div
        class="fixed inset-0 z-50 bg-black/75 backdrop-blur-xs flex items-center justify-center p-3 sm:p-6 overflow-y-auto"
        dir="rtl"
        @click.self="emit('close')"
    >
        <div
            class="bg-white dark:bg-[#0f172a] rounded-3xl max-w-xl w-full border border-slate-200 dark:border-slate-700 shadow-2xl flex flex-col max-h-[90vh] overflow-hidden my-auto"
            @click.stop
        >
            <!-- Sticky Header -->
            <div class="flex items-center justify-between p-5 border-b border-slate-100 dark:border-slate-800 shrink-0 bg-white dark:bg-[#0f172a] z-10">
                <div class="flex items-center gap-2.5">
                    <span class="text-2xl">📲</span>
                    <div>
                        <h3 class="text-lg font-black text-slate-900 dark:text-white">بوابة واتساب المطعم الذاتية</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">إرسال كوبونات العجلة وتذكيرات CRM تلقائياً في الخلفية (0$ تكلفة)</p>
                    </div>
                </div>
                <button
                    @click="emit('close')"
                    type="button"
                    class="w-9 h-9 rounded-full bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white flex items-center justify-center font-bold text-base transition cursor-pointer shadow-xs"
                    title="إغلاق النافذة (Esc)"
                >
                    ✕
                </button>
            </div>

            <!-- Alert if linking was prompted by reminder action -->
            <div v-if="pendingReminderCustomer" class="p-4 bg-gradient-to-r from-amber-500/15 via-amber-500/5 to-transparent border-b border-amber-200 dark:border-amber-800/80 text-xs font-bold text-amber-900 dark:text-amber-200 flex flex-wrap items-center justify-between gap-3 shrink-0">
                <div class="flex items-start gap-2.5">
                    <span class="text-2xl">⚠️</span>
                    <div>
                        <p class="font-black text-sm text-amber-900 dark:text-amber-100">يجب ربط رقم واتساب المطعم أولاً لإرسال التذكير تلقائياً!</p>
                        <p class="text-xs text-amber-800 dark:text-amber-300 font-normal mt-0.5 leading-relaxed">
                            امسح رمز الاستجابة السريعة (QR) أدناه لمرة واحدة، وسيتم إرسال التذكير تلقائياً للزبون <span class="font-bold underline text-amber-950 dark:text-white">{{ pendingReminderCustomer.name }}</span> فور اكتمال الربط.
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <Link :href="route('admin.settings') + '#whatsapp'" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-white dark:bg-slate-800 border border-amber-300 dark:border-amber-700 text-amber-800 dark:text-amber-300 hover:bg-amber-100 transition text-xs font-bold shadow-2xs">
                        <span>⚙️ صفحة الإعدادات</span>
                    </Link>
                    <button @click="emit('force-manual')" type="button" class="px-3 py-1.5 rounded-xl bg-amber-600 hover:bg-amber-500 text-white transition text-xs font-bold cursor-pointer shadow-2xs" title="تجاوز الربط والفتح في واتساب ويب الشخصي">
                        فتح بواتساب ويب مؤقتاً
                    </button>
                </div>
            </div>

            <!-- Connection Celebration Banner -->
            <div v-if="waConnectSuccessCelebration" class="p-3.5 bg-emerald-500 text-white text-xs font-black text-center flex items-center justify-center gap-2 animate-pulse shrink-0">
                <span>🎉</span>
                <span>تم ربط واتساب المطعم بنجاح! جارٍ إرسال التذكير للعميل الآن تلقائياً...</span>
            </div>

            <!-- Scrollable Content -->
            <div class="p-5 sm:p-6 space-y-5 overflow-y-auto flex-1">
                <!-- Live Status & Connection -->
                <div class="p-4 rounded-2xl border" :class="waStatus === 'connected' ? 'bg-emerald-50/70 dark:bg-emerald-950/30 border-emerald-300 dark:border-emerald-700/50' : 'bg-slate-50 dark:bg-slate-900/60 border-slate-200 dark:border-slate-800'">
                    <!-- State: Connected -->
                    <div v-if="waStatus === 'connected'" class="space-y-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <span class="w-3.5 h-3.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                <div>
                                    <div class="text-sm font-black text-emerald-800 dark:text-emerald-300">متصل بنجاح وبجاهزية كاملة 🟢</div>
                                    <div class="text-xs text-slate-600 dark:text-slate-400 font-mono mt-0.5" dir="ltr">+{{ waPhone }}</div>
                                </div>
                            </div>
                            <button
                                @click="disconnectWhatsapp"
                                :disabled="waLoading"
                                type="button"
                                class="px-3 py-1.5 rounded-xl text-xs font-bold bg-rose-50 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-800 hover:bg-rose-100 transition cursor-pointer"
                            >
                                {{ waLoading ? 'جارٍ الفصل...' : 'فصل الجلسة' }}
                            </button>
                        </div>

                        <div class="pt-3 border-t border-emerald-200 dark:border-emerald-800/40 space-y-2">
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                                تجربة الإرسال (أدخل رقم هاتفك لتأكيد وصول الرسائل):
                            </label>
                            <div class="flex gap-2">
                                <input
                                    v-model="waTestPhone"
                                    type="tel"
                                    dir="ltr"
                                    placeholder="09XXXXXXXX"
                                    class="w-full rounded-xl text-sm font-mono border-slate-200 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                />
                                <button
                                    @click="sendTestMessage"
                                    :disabled="waSendingTest || !waTestPhone"
                                    type="button"
                                    class="px-4 py-2 rounded-xl text-xs font-bold bg-emerald-600 hover:bg-emerald-500 text-white shrink-0 transition disabled:opacity-50 cursor-pointer"
                                >
                                    {{ waSendingTest ? 'جارٍ الإرسال...' : 'إرسال تجربة' }}
                                </button>
                            </div>
                            <div v-if="waTestNotice" class="text-xs font-bold text-emerald-700 dark:text-emerald-400">
                                {{ waTestNotice }}
                            </div>
                            <div v-if="waTestError" class="text-xs font-bold text-rose-600 dark:text-rose-400">
                                {{ waTestError }}
                            </div>
                        </div>
                    </div>

                    <!-- State: Scan QR -->
                    <div v-else-if="waStatus === 'scan_qr'" class="text-center space-y-4">
                        <div class="text-xs font-bold text-amber-700 dark:text-amber-400 flex items-center justify-center gap-1.5">
                            <span class="animate-spin">⏳</span>
                            <span>امسح رمز الاستجابة السريعة (QR) من تطبيق واتساب في هاتفك</span>
                        </div>

                        <div v-if="waQrCode" class="inline-block p-3 rounded-2xl bg-white shadow-md border border-slate-200">
                            <img :src="waQrCode" alt="WhatsApp QR Code" class="w-56 h-56 mx-auto object-contain" />
                        </div>

                        <div class="max-w-xs mx-auto space-y-1.5">
                            <div class="flex items-center justify-between text-[11px] text-amber-600 dark:text-amber-400 font-bold px-1">
                                <span>صلاحية الباركود النشطة</span>
                                <span class="font-mono text-emerald-600 dark:text-emerald-400" dir="ltr">نشط 🟢</span>
                            </div>
                            <div class="h-1.5 w-full bg-slate-200 dark:bg-slate-800 rounded-full overflow-hidden">
                                <div class="h-full bg-gradient-to-r from-amber-500 to-emerald-500 rounded-full animate-pulse w-full"></div>
                            </div>
                            <p class="text-[10px] text-slate-400">يتجدد الباركود تلقائياً لحماية أمان حساب واتساب مطعمك</p>
                        </div>

                        <div class="text-xs text-slate-600 dark:text-slate-400 max-w-xs mx-auto leading-relaxed text-start space-y-1 bg-white dark:bg-slate-900/80 p-3 rounded-xl border border-slate-200 dark:border-slate-800">
                            <div class="font-bold text-slate-800 dark:text-white mb-1">خطوات الربط:</div>
                            <div>1. افتح تطبيق واتساب على هاتفك.</div>
                            <div>2. الإعدادات ⚙️ أو القائمة ⋮ ➔ <strong>الأجهزة المرتبطة</strong>.</div>
                            <div>3. اضغط <strong>«ربط جهاز»</strong> ووجّه الكاميرا نحو الباركود أعلاه.</div>
                        </div>

                        <div>
                            <button
                                @click="connectWhatsapp"
                                :disabled="waLoading"
                                type="button"
                                class="px-4 py-2 rounded-xl text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 transition cursor-pointer"
                            >
                                🔄 تحديث الباركود
                            </button>
                        </div>
                    </div>

                    <!-- State: Disconnected -->
                    <div v-else class="text-center space-y-3 py-3">
                        <div class="text-3xl">📱</div>
                        <h4 class="text-sm font-black text-slate-900 dark:text-white">واتساب المطعم غير مرتبط حالياً</h4>
                        <p class="text-xs text-slate-500 dark:text-slate-400 max-w-md mx-auto leading-relaxed">
                            اربط رقم هاتف المطعم عبر مسح الباركود لمرة واحدة فقط، لتبدأ كوبونات عجلة الحظ وتذكيرات الزبائن بالانطلاق تلقائياً في الخلفية دون أي جهد منك.
                        </p>
                        <button
                            @click="connectWhatsapp"
                            :disabled="waLoading"
                            type="button"
                            class="px-5 py-2.5 rounded-xl text-xs font-black bg-emerald-600 hover:bg-emerald-500 text-white shadow-md transition disabled:opacity-50 cursor-pointer"
                        >
                            {{ waLoading ? 'جارٍ توليد الباركود...' : '📲 توليد رمز الاستجابة السريعة (QR Code)' }}
                        </button>
                    </div>
                </div>

                <!-- Automation Settings -->
                <div class="space-y-4 pt-2">
                    <h4 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-wider">⚙️ إعدادات الأتمتة الذكية</h4>

                    <label class="flex items-start gap-3 p-3.5 rounded-2xl border border-slate-200 dark:border-slate-700/70 bg-slate-50/60 dark:bg-slate-900/50 cursor-pointer">
                        <input
                            v-model="waExpiringRemindersEnabled"
                            type="checkbox"
                            class="mt-1 rounded text-emerald-600 focus:ring-emerald-500"
                        />
                        <div>
                            <div class="text-xs font-bold text-slate-900 dark:text-white">
                                تفعيل تنبيه انتهاء الصلاحية التلقائي قبل 24 ساعة (FOMO Alert) ⏳
                            </div>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 leading-relaxed">
                                يرسل النظام رسالة واتساب لطيفة وتلقائية في الخلفية للزبائن الذين ينتهي خصمهم غداً لتشجيعهم على زيارة المطعم قبل انتهاء الكوبون.
                            </p>
                        </div>
                    </label>

                    <div class="p-3.5 rounded-2xl border border-slate-200 dark:border-slate-700/70 bg-slate-50/60 dark:bg-slate-900/50 space-y-3">
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input
                                v-model="waAutoReplyEnabled"
                                type="checkbox"
                                class="mt-1 rounded text-emerald-600 focus:ring-emerald-500"
                            />
                            <div>
                                <div class="text-xs font-bold text-slate-900 dark:text-white">
                                    تفعيل الرد التلقائي الذكي على استفسارات الزبائن في واتساب 💬
                                </div>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 leading-relaxed">
                                    يرد النظام آلياً فوراً عندما يرسل أي زبون رسالة إلى رقم المطعم على واتساب (يمكنك وضع رابط المنيو وموقع المطعم).
                                </p>
                            </div>
                        </label>

                        <div v-if="waAutoReplyEnabled" class="pt-2">
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">نص الرد التلقائي:</label>
                            <textarea
                                v-model="waAutoReplyMessage"
                                rows="3"
                                placeholder="أهلاً بك في {restaurant_name}! لتصفح المنيو: {menu_url} | لأوقات الدوام..."
                                class="w-full rounded-xl text-xs border-slate-200 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                            ></textarea>
                            <p class="text-[10px] text-slate-400 mt-1">
                                يمكنك استخدام: <span dir="ltr">{restaurant_name}</span> لاسم المطعم، و <span dir="ltr">{menu_url}</span> لرابط المنيو الرقمي.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sticky Footer -->
            <div class="p-4 sm:p-5 border-t border-slate-100 dark:border-slate-800 bg-slate-50/90 dark:bg-slate-900/90 backdrop-blur-xs flex flex-wrap items-center justify-between gap-3 shrink-0">
                <div class="flex items-center gap-3">
                    <button
                        @click="emit('close')"
                        type="button"
                        class="px-4 sm:px-5 py-2.5 rounded-xl text-xs font-bold text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700 transition cursor-pointer flex items-center gap-1.5 shadow-xs"
                    >
                        <span>✕</span>
                        <span>إغلاق النافذة</span>
                    </button>
                    <Link :href="route('admin.settings') + '#whatsapp'" class="text-xs font-bold text-amber-600 dark:text-amber-400 hover:underline flex items-center gap-1">
                        <span>⚙️ فتح في صفحة الإعدادات</span>
                        <span>↗</span>
                    </Link>
                </div>

                <div class="flex items-center gap-3">
                    <span v-if="waSettingsNotice" class="text-xs font-bold text-emerald-600 dark:text-emerald-400">
                        {{ waSettingsNotice }}
                    </span>
                    <button
                        @click="saveWhatsappSettings"
                        :disabled="waSavingSettings"
                        type="button"
                        class="px-5 py-2.5 rounded-xl text-xs font-black bg-emerald-600 hover:bg-emerald-500 text-white shadow-md transition disabled:opacity-50 cursor-pointer"
                    >
                        {{ waSavingSettings ? 'جارٍ الحفظ...' : '💾 حفظ إعدادات الأتمتة' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
