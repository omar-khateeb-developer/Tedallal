<script setup lang="ts">
import AdminNav from '@/Components/Admin/AdminNav.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import axios from 'axios';

interface PromoItem {
    id: number;
    code: string;
    source: string;
    discount_label: string;
    discount_value: number;
    discount_type: string;
    starts_at: string | null;
    expires_at: string | null;
    status: 'active' | 'used' | 'expired' | 'inactive' | 'scheduled';
    is_usable: boolean;
    reminded_at: string | null;
    reminders_count: number;
    whatsapp_url: string | null;
    whatsapp_message: string | null;
}

interface CustomerRow {
    id: number;
    name: string;
    phone: string;
    visits_count: number;
    notes: string | null;
    last_visit_at: string | null;
    has_usable_promo: boolean;
    needs_reminder: boolean;
    has_active_loyalty_promo: boolean;
    remaining_visits_for_loyalty: number | null;
    promo_codes: PromoItem[];
}

interface MenuItemOption {
    id: number;
    name: string;
    price: number | string;
}

const loading = ref(true);
const isLocked = ref(false);
const customers = ref<CustomerRow[]>([]);
const menuItems = ref<MenuItemOption[]>([]);
const currency = ref('ل.س');
const stats = ref({
    total_customers: 0,
    returning_customers: 0,
    active_coupons: 0,
    needs_reminder: 0,
    loyalty_eligible: 0,
});

const activeFilter = ref<'all' | 'needs_reminder' | 'has_active' | 'returning' | 'loyalty'>('all');
const searchQuery = ref('');

// Settings panel
const showSettings = ref(false);
const savingSettings = ref(false);
const settingsNotice = ref<string | null>(null);
const settingsForm = ref({
    wheel_require_whatsapp: true,
    wheel_reveal_code_after_save: false,
    loyalty_enabled: false,
    loyalty_target_visits: 5,
    loyalty_reward_type: 'percentage' as 'percentage' | 'fixed' | 'free_item',
    loyalty_reward_value: 50,
    loyalty_menu_item_id: null as number | null,
});

// Add / Edit Customer Modals
const showAddModal = ref(false);
const addForm = ref({ name: '', phone: '', notes: '' });
const addError = ref<string | null>(null);
const savingAdd = ref(false);

const editingNotesCustomer = ref<CustomerRow | null>(null);
const editNotesForm = ref({ name: '', notes: '' });
const savingNotes = ref(false);
const reminderPendingId = ref<number | null>(null);

// WhatsApp Gateway States & Methods
const showWhatsappModal = ref(false);
const waLoading = ref(false);
const waStatus = ref<'disconnected' | 'connecting' | 'scan_qr' | 'connected'>('disconnected');
const waQrCode = ref<string | null>(null);
const waPhone = ref<string | null>(null);
const waConnectedAt = ref<string | null>(null);
const waAutoReplyEnabled = ref(false);
const waAutoReplyMessage = ref('');
const waExpiringRemindersEnabled = ref(true);
const waSavingSettings = ref(false);
const waSettingsNotice = ref<string | null>(null);

const waTestPhone = ref('');
const waSendingTest = ref(false);
const waTestNotice = ref<string | null>(null);
const waTestError = ref<string | null>(null);

const directReminderPendingId = ref<number | null>(null);
const directReminderSuccessToast = ref<string | null>(null);

let waPollingTimer: ReturnType<typeof setInterval> | null = null;

async function fetchWhatsappStatus() {
    try {
        const res = await axios.get('/api/admin/whatsapp/status');
        const data = res.data.data;
        waStatus.value = data.status || 'disconnected';
        waQrCode.value = data.qr_code || null;
        waPhone.value = data.phone || null;
        waConnectedAt.value = data.connected_at || null;
        waAutoReplyEnabled.value = Boolean(data.auto_reply_enabled);
        waAutoReplyMessage.value = data.auto_reply_message || '';
        waExpiringRemindersEnabled.value = data.expiring_reminders_enabled !== false;

        if (waStatus.value === 'scan_qr' || waStatus.value === 'connecting') {
            startWaPolling();
        } else {
            stopWaPolling();
        }
    } catch (_) {}
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
            waConnectedAt.value = data.connected_at;
            if (data.status === 'connected') {
                stopWaPolling();
            }
        } catch (_) {}
    }, 3000);
}

function stopWaPolling() {
    if (waPollingTimer) {
        clearInterval(waPollingTimer);
        waPollingTimer = null;
    }
}

async function connectWhatsapp() {
    waLoading.value = true;
    try {
        const res = await axios.post('/api/admin/whatsapp/connect');
        const data = res.data.data;
        waStatus.value = data.status;
        waQrCode.value = data.qr_code;
        waPhone.value = data.phone;
        waConnectedAt.value = data.connected_at;
        startWaPolling();
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
        waConnectedAt.value = null;
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

async function sendDirectBackgroundReminder(customer: CustomerRow, promo: PromoItem) {
    directReminderPendingId.value = promo.id;
    try {
        const res = await axios.post(`/api/admin/customers/${customer.id}/send-direct-reminder`, {
            promo_code_id: promo.id,
        });
        const d = res.data.data;
        promo.reminded_at = d.reminded_at || 'الآن';
        promo.reminders_count = d.reminders_count || (promo.reminders_count + 1);
        customer.needs_reminder = customer.promo_codes.some(c => c.is_usable && !c.reminded_at);

        if (d.sent_directly) {
            directReminderSuccessToast.value = `تم إرسال رسالة التذكير بنجاح في الخلفية إلى ${customer.name} (${customer.phone}) 📲`;
            setTimeout(() => { directReminderSuccessToast.value = null; }, 4000);
        } else if (d.whatsapp_url) {
            window.open(d.whatsapp_url, '_blank', 'noopener,noreferrer');
        }
    } catch (e: any) {
        alert(e.response?.data?.message || 'تعذر إرسال التذكير.');
    } finally {
        directReminderPendingId.value = null;
    }
}

const selectedFreeItemPrice = computed(() => {
    if (settingsForm.value.loyalty_reward_type !== 'free_item' || !settingsForm.value.loyalty_menu_item_id) return 0;
    const item = menuItems.value.find(i => i.id === settingsForm.value.loyalty_menu_item_id);
    return item ? parseFloat(String(item.price)) : 0;
});

function formatNum(val: number | string): string {
    const n = parseFloat(String(val));
    if (isNaN(n)) return '0';
    return n % 1 === 0 ? String(Math.round(n)) : n.toFixed(2).replace(/\.00$/, '');
}

async function fetchCustomers() {
    loading.value = true;
    try {
        const res = await axios.get('/api/admin/customers', {
            params: {
                filter: activeFilter.value,
                search: searchQuery.value,
            },
        });
        const data = res.data.data;
        customers.value = data.customers || [];
        stats.value = data.stats || stats.value;
        menuItems.value = data.menu_items || [];
        currency.value = data.currency || 'ل.س';

        if (data.crm_settings) {
            settingsForm.value.wheel_require_whatsapp = Boolean(data.crm_settings.wheel_require_whatsapp);
            settingsForm.value.wheel_reveal_code_after_save = Boolean(data.crm_settings.wheel_reveal_code_after_save);
            if (data.crm_settings.loyalty) {
                const l = data.crm_settings.loyalty;
                settingsForm.value.loyalty_enabled = Boolean(l.enabled);
                settingsForm.value.loyalty_target_visits = Number(l.target_visits || 5);
                settingsForm.value.loyalty_reward_type = l.reward_type || 'percentage';
                settingsForm.value.loyalty_reward_value = Number(l.reward_value || 50);
                settingsForm.value.loyalty_menu_item_id = l.menu_item_id ? Number(l.menu_item_id) : null;
            }
        }
    } catch (e: any) {
        if (e.response?.status === 403) {
            isLocked.value = true;
        }
    } finally {
        loading.value = false;
    }
}

function handleGlobalKeydown(e: KeyboardEvent) {
    if (e.key === 'Escape') {
        if (showWhatsappModal.value) showWhatsappModal.value = false;
        if (showAddModal.value) showAddModal.value = false;
        if (editingNotesCustomer.value) editingNotesCustomer.value = null;
    }
}

watch(showWhatsappModal, (isOpen) => {
    if (!isOpen) {
        stopWaPolling();
    } else if (waStatus.value === 'scan_qr' || waStatus.value === 'connecting') {
        startWaPolling();
    }
});

onMounted(() => {
    fetchCustomers();
    fetchWhatsappStatus();
    window.addEventListener('keydown', handleGlobalKeydown);
});

onUnmounted(() => {
    stopWaPolling();
    window.removeEventListener('keydown', handleGlobalKeydown);
});

let searchTimer: ReturnType<typeof setTimeout> | null = null;
function onSearchInput() {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(fetchCustomers, 350);
}

function setFilter(filter: typeof activeFilter.value) {
    activeFilter.value = filter;
    fetchCustomers();
}

async function saveSettings() {
    savingSettings.value = true;
    settingsNotice.value = null;
    try {
        await axios.post('/api/admin/crm-settings', settingsForm.value);
        settingsNotice.value = '✓ تم حفظ إعدادات الواتساب وبرنامج الولاء بنجاح.';
        await fetchCustomers();
    } catch (e: any) {
        alert(e.response?.data?.message || 'تعذر حفظ الإعدادات.');
    } finally {
        savingSettings.value = false;
    }
}

async function createCustomer() {
    addError.value = null;
    savingAdd.value = true;
    try {
        await axios.post('/api/admin/customers', addForm.value);
        addForm.value = { name: '', phone: '', notes: '' };
        showAddModal.value = false;
        await fetchCustomers();
    } catch (e: any) {
        const errors = e.response?.data?.errors;
        if (errors) {
            addError.value = Object.values(errors).flat()[0] as string;
        } else {
            addError.value = e.response?.data?.message || 'تعذر إضافة العميل.';
        }
    } finally {
        savingAdd.value = false;
    }
}

function openEditCustomer(customer: CustomerRow) {
    editingNotesCustomer.value = customer;
    editNotesForm.value = {
        name: customer.name,
        notes: customer.notes || '',
    };
}

async function saveCustomerNotes() {
    if (!editingNotesCustomer.value) return;
    savingNotes.value = true;
    try {
        await axios.patch(`/api/admin/customers/${editingNotesCustomer.value.id}`, editNotesForm.value);
        editingNotesCustomer.value = null;
        await fetchCustomers();
    } catch (e: any) {
        alert(e.response?.data?.message || 'تعذر التحديث.');
    } finally {
        savingNotes.value = false;
    }
}

async function deleteCustomer(customer: CustomerRow) {
    if (!confirm(`هل أنت متأكد من حذف ملف العميل «${customer.name}» (${customer.phone})؟`)) return;
    try {
        await axios.delete(`/api/admin/customers/${customer.id}`);
        await fetchCustomers();
    } catch (e: any) {
        alert(e.response?.data?.message || 'تعذر الحذف.');
    }
}

async function sendWhatsAppReminder(customer: CustomerRow, promo: PromoItem) {
    reminderPendingId.value = promo.id;
    try {
        const res = await axios.post(`/api/admin/customers/${customer.id}/mark-reminded`, {
            promo_code_id: promo.id,
        });
        const url = res.data.data?.whatsapp_url || promo.whatsapp_url;
        promo.reminded_at = res.data.data?.reminded_at || 'الآن';
        promo.reminders_count = res.data.data?.reminders_count || (promo.reminders_count + 1);
        customer.needs_reminder = customer.promo_codes.some(c => c.is_usable && !c.reminded_at);
        if (url) {
            window.open(url, '_blank', 'noopener,noreferrer');
        }
    } catch (e: any) {
        alert(e.response?.data?.message || 'تعذر تجهيز تذكير الواتساب.');
    } finally {
        reminderPendingId.value = null;
    }
}

function statusBadge(promo: PromoItem) {
    switch (promo.status) {
        case 'active':
            return { text: 'فعّال وجاهز', cls: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border-emerald-300 dark:border-emerald-700/50' };
        case 'scheduled':
            return { text: 'للزيارة القادمة', cls: 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border-amber-300 dark:border-amber-700/50' };
        case 'used':
            return { text: 'تم استخدامه ✓', cls: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400 border-slate-300 dark:border-slate-700' };
        case 'expired':
            return { text: 'منتهي الصلاحية', cls: 'bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300 border-rose-300 dark:border-rose-700/50' };
        default:
            return { text: 'معطّل', cls: 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400 border-gray-300' };
    }
}
</script>

<template>
    <Head title="إدارة العملاء والولاء (CRM)" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-bold text-gray-800 dark:text-white flex items-center gap-2">
                <span>💬 إدارة عملاء المطعم وبرنامج الولاء (CRM)</span>
            </h2>
        </template>

        <div class="mx-auto max-w-6xl p-4 sm:p-6 space-y-6" dir="rtl">
            <AdminNav />

            <!-- Feature Locked Banner -->
            <div v-if="isLocked" class="bg-white dark:bg-[#0f172a] rounded-3xl border border-amber-200 dark:border-amber-700/40 p-10 text-center shadow-sm space-y-4">
                <div class="w-16 h-16 mx-auto rounded-2xl bg-amber-100 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center text-3xl shadow-inner">
                    🔒
                </div>
                <h3 class="text-xl font-black text-slate-900 dark:text-white">نظام الـ CRM وتذكيرات الواتساب مقفل حالياً</h3>
                <p class="text-sm text-slate-600 dark:text-slate-400 max-w-lg mx-auto leading-relaxed">
                    هذه الميزة متاحة في الباقة الشاملة (<span dir="ltr">Max</span>) أو عند تفعيل ميزة <span dir="ltr">WhatsApp Reminders</span> لمطعمك من قِبل إدارة المنصة.
                    تتيح لك حفظ أرقام وأسماء زبائن مطعمك بعزل تام، متابعة زياراتهم، وإرسال تذكيرات خصوماتهم عبر واتساب بضغطة زر.
                </p>
            </div>

            <template v-else>
                <!-- KPI Stats Grid -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white dark:bg-[#0f172a] rounded-2xl p-4 border border-slate-100 dark:border-slate-800 shadow-xs">
                        <div class="text-xs font-semibold text-slate-500 dark:text-slate-400">إجمالي عملاء مطعمك</div>
                        <div class="text-2xl font-black text-slate-900 dark:text-white mt-1">{{ stats.total_customers }}</div>
                    </div>
                    <div class="bg-white dark:bg-[#0f172a] rounded-2xl p-4 border border-emerald-100 dark:border-emerald-900/40 shadow-xs">
                        <div class="text-xs font-semibold text-emerald-700 dark:text-emerald-400">بانتظار تذكير واتساب</div>
                        <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-1">{{ stats.needs_reminder }}</div>
                    </div>
                    <div class="bg-white dark:bg-[#0f172a] rounded-2xl p-4 border border-amber-100 dark:border-amber-900/40 shadow-xs">
                        <div class="text-xs font-semibold text-amber-700 dark:text-amber-400">عملاء عائدون (+2 زيارة)</div>
                        <div class="text-2xl font-black text-amber-600 dark:text-amber-400 mt-1">{{ stats.returning_customers }}</div>
                    </div>
                    <div class="bg-white dark:bg-[#0f172a] rounded-2xl p-4 border border-purple-100 dark:border-purple-900/40 shadow-xs">
                        <div class="text-xs font-semibold text-purple-700 dark:text-purple-400">مؤهلون لجائزة الولاء 🏆</div>
                        <div class="text-2xl font-black text-purple-600 dark:text-purple-400 mt-1">{{ stats.loyalty_eligible }}</div>
                    </div>
                </div>

                <!-- Action Bar & Settings Toggle -->
                <div class="bg-white dark:bg-[#0f172a] rounded-2xl p-4 border border-slate-100 dark:border-slate-800 shadow-xs space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <button
                                @click="showWhatsappModal = true"
                                type="button"
                                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-black border transition cursor-pointer shadow-xs"
                                :class="waStatus === 'connected'
                                    ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-300 border-emerald-300 dark:border-emerald-700/60 hover:bg-emerald-100'
                                    : waStatus === 'scan_qr'
                                    ? 'bg-amber-500 text-white border-amber-500 animate-pulse'
                                    : 'bg-emerald-600 hover:bg-emerald-500 text-white border-emerald-600'"
                            >
                                <span v-if="waStatus === 'connected'">🟢 واتساب المطعم متصل ({{ waPhone }})</span>
                                <span v-else-if="waStatus === 'scan_qr'">🟡 بانتظار مسح الباركود (QR)</span>
                                <span v-else>📲 ربط واتساب المطعم (QR Code)</span>
                            </button>

                            <button
                                @click="showSettings = !showSettings"
                                type="button"
                                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold border transition cursor-pointer"
                                :class="showSettings
                                    ? 'bg-amber-500 text-white border-amber-500 shadow-sm'
                                    : 'bg-amber-50 dark:bg-amber-950/30 text-amber-800 dark:text-amber-300 border-amber-200 dark:border-amber-700/50 hover:bg-amber-100'"
                            >
                                <span>⚙️ إعدادات العجلة والولاء الكبرى</span>
                                <span>{{ showSettings ? '▲' : '▼' }}</span>
                            </button>

                            <button
                                @click="showAddModal = true"
                                type="button"
                                class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl text-xs font-bold bg-emerald-600 hover:bg-emerald-500 text-white transition shadow-xs cursor-pointer"
                            >
                                <span>➕ إضافة عميل يدوياً</span>
                            </button>
                        </div>

                        <div class="w-full sm:w-72">
                            <input
                                v-model="searchQuery"
                                @input="onSearchInput"
                                type="search"
                                placeholder="🔍 بحث بالاسم، رقم 09، أو الكود..."
                                class="w-full text-sm rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-900 dark:text-white focus:border-amber-500 focus:ring-amber-500"
                            />
                        </div>
                    </div>

                    <!-- Collapsible Settings Panel -->
                    <div v-if="showSettings" class="pt-4 border-t border-slate-100 dark:border-slate-800 space-y-5">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Option 1: Wheel WhatsApp Gate -->
                            <label class="flex items-start gap-3 p-3.5 rounded-2xl border border-slate-200 dark:border-slate-700/70 bg-slate-50/60 dark:bg-slate-900/50 cursor-pointer">
                                <input
                                    v-model="settingsForm.wheel_require_whatsapp"
                                    type="checkbox"
                                    class="mt-1 rounded text-emerald-600 focus:ring-emerald-500"
                                />
                                <div>
                                    <div class="text-sm font-bold text-slate-900 dark:text-white">
                                        إلزام الزبون بإدخال الاسم ورقم الواتساب لاستلام كود العجلة
                                    </div>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                        عند تفعيله، يُحجب كود الخصم عن الشاشة بعد الدوران حتى يدخل الزبون اسمه ورقم الواتساب (<span dir="ltr">09XXXXXXXX</span>) ليُحفظ في الـ <span dir="ltr">CRM</span>.
                                    </p>
                                </div>
                            </label>

                            <!-- Option 2: Reveal Code on Screen After Save -->
                            <label
                                class="flex items-start gap-3 p-3.5 rounded-2xl border border-slate-200 dark:border-slate-700/70 bg-slate-50/60 dark:bg-slate-900/50 cursor-pointer"
                                :class="{ 'opacity-50 pointer-events-none': !settingsForm.wheel_require_whatsapp }"
                            >
                                <input
                                    v-model="settingsForm.wheel_reveal_code_after_save"
                                    type="checkbox"
                                    class="mt-1 rounded text-emerald-600 focus:ring-emerald-500"
                                />
                                <div>
                                    <div class="text-sm font-bold text-slate-900 dark:text-white">
                                        إظهار الكود على الشاشة أيضاً *بعد* حفظ الرقم
                                    </div>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                        إن كان معطّلاً: يُرسل الكود إلى رسالة الواتساب فقط. إن كان مفعّلاً: يظهر الكود على الشاشة أيضاً بمجرد توثيق الاسم والرقم.
                                    </p>
                                </div>
                            </label>
                        </div>

                        <!-- Loyalty Milestone Settings -->
                        <div class="p-4 rounded-2xl border border-amber-200/80 dark:border-amber-700/40 bg-amber-50/40 dark:bg-amber-950/15 space-y-4">
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input
                                    v-model="settingsForm.loyalty_enabled"
                                    type="checkbox"
                                    class="rounded text-amber-600 focus:ring-amber-500"
                                />
                                <div>
                                    <span class="text-sm font-extrabold text-amber-950 dark:text-amber-200">
                                        🏆 تفعيل برنامج تشجيع الزيارات المتكررة والجائزة الكبرى (محاسبة مالية تلقائية)
                                    </span>
                                    <p class="text-xs text-amber-800/80 dark:text-amber-300/70">
                                        يرحب بالزبون عند عودته في الزيارة الثانية وما بعدها، ويُصدر له تلقائياً كوبون جائزة كبرى عند بلوغ الهدف يُخصم آلياً في الفواتير والتقارير.
                                    </p>
                                </div>
                            </label>

                            <div v-if="settingsForm.loyalty_enabled" class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2">
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                        عدد الزيارات المطلوب للجائزة:
                                    </label>
                                    <input
                                        v-model.number="settingsForm.loyalty_target_visits"
                                        type="number"
                                        min="2"
                                        max="50"
                                        class="w-full rounded-xl text-sm border-slate-200 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                    />
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                        نوع الجائزة الكبرى (لتحديد قيمتها المالية):
                                    </label>
                                    <select
                                        v-model="settingsForm.loyalty_reward_type"
                                        class="w-full rounded-xl text-sm border-slate-200 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                    >
                                        <option value="percentage">خصم بنسبة مئوية (%)</option>
                                        <option value="fixed">قسيمة نقدية ثابتة ({{ currency }})</option>
                                        <option value="free_item">طبق / صنف مجاني من القائمة</option>
                                    </select>
                                </div>

                                <div v-if="settingsForm.loyalty_reward_type !== 'free_item'">
                                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                        {{ settingsForm.loyalty_reward_type === 'percentage' ? 'نسبة الخصم (%):' : `قيمة القسيمة (${currency}):` }}
                                    </label>
                                    <input
                                        v-model.number="settingsForm.loyalty_reward_value"
                                        type="number"
                                        min="1"
                                        :max="settingsForm.loyalty_reward_type === 'percentage' ? 100 : undefined"
                                        class="w-full rounded-xl text-sm border-slate-200 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                    />
                                </div>

                                <div v-else>
                                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                        اختر الطبق المجاني (بقيمته الفعلية):
                                    </label>
                                    <select
                                        v-model.number="settingsForm.loyalty_menu_item_id"
                                        class="w-full rounded-xl text-sm border-slate-200 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                    >
                                        <option :value="null">-- اختر صنفاً من مطعمك --</option>
                                        <option v-for="item in menuItems" :key="item.id" :value="item.id">
                                            {{ item.name }} ({{ formatNum(item.price) }} {{ currency }})
                                        </option>
                                    </select>
                                    <p v-if="selectedFreeItemPrice > 0" class="text-[11px] text-emerald-700 dark:text-emerald-400 font-bold mt-1">
                                        سيُخصم مبلغ {{ formatNum(selectedFreeItemPrice) }} {{ currency }} تلقائياً ويُسجّل في التقارير المالية.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-3">
                            <span v-if="settingsNotice" class="text-xs font-bold text-emerald-600 dark:text-emerald-400">
                                {{ settingsNotice }}
                            </span>
                            <span v-else></span>

                            <button
                                @click="saveSettings"
                                :disabled="savingSettings"
                                type="button"
                                class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 text-white text-xs font-black shadow-md hover:opacity-95 transition disabled:opacity-60 cursor-pointer"
                            >
                                {{ savingSettings ? 'جاري الحفظ...' : '💾 حفظ الإعدادات' }}
                            </button>
                        </div>
                    </div>

                    <!-- Filter Tabs -->
                    <div class="flex flex-wrap gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                        <button
                            v-for="tab in [
                                { id: 'all', label: 'كل العملاء' },
                                { id: 'needs_reminder', label: '💬 بانتظار التذكير' },
                                { id: 'has_active', label: '🎟️ لديهم كوبون فعّال' },
                                { id: 'returning', label: '🌟 عملاء عائدون (+2)' },
                                { id: 'loyalty', label: '🏆 مستحقو الجائزة الكبرى' },
                            ]"
                            :key="tab.id"
                            @click="setFilter(tab.id as any)"
                            type="button"
                            class="px-3.5 py-2 rounded-xl text-xs font-bold transition cursor-pointer"
                            :class="activeFilter === tab.id
                                ? 'bg-slate-900 dark:bg-amber-500 text-white shadow-xs'
                                : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'"
                        >
                            {{ tab.label }}
                        </button>
                    </div>
                </div>

                <!-- Loading / Empty -->
                <div v-if="loading" class="text-center py-12 text-slate-500 dark:text-slate-400 font-semibold">
                    جاري تحميل سجل عملاء مطعمك...
                </div>

                <div v-else-if="customers.length === 0" class="bg-white dark:bg-[#0f172a] rounded-3xl p-10 text-center border border-slate-100 dark:border-slate-800 text-slate-500 dark:text-slate-400">
                    لا توجد بيانات عملاء مطابقة للبحث أو الفلتر الحالي.
                </div>

                <!-- Customers List -->
                <div v-else class="space-y-4">
                    <div
                        v-for="customer in customers"
                        :key="customer.id"
                        class="bg-white dark:bg-[#0f172a] rounded-2xl p-5 border border-slate-100 dark:border-slate-800 shadow-xs space-y-4"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="space-y-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-base font-black text-slate-900 dark:text-white">{{ customer.name }}</h3>
                                    <span class="font-mono text-xs font-bold px-2.5 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300" dir="ltr">
                                        {{ customer.phone }}
                                    </span>
                                    <span
                                        class="text-xs font-bold px-2.5 py-0.5 rounded-full border"
                                        :class="customer.visits_count >= 2
                                            ? 'bg-amber-100 text-amber-800 border-amber-300 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-700/60'
                                            : 'bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700'"
                                    >
                                        زيارات: {{ customer.visits_count }}
                                    </span>
                                    <span v-if="customer.has_active_loyalty_promo" class="text-xs font-black px-2.5 py-0.5 rounded-full bg-purple-100 text-purple-800 border border-purple-300 dark:bg-purple-950/60 dark:text-purple-300 dark:border-purple-700">
                                        🏆 استحق جائزة الولاء
                                    </span>
                                </div>

                                <div class="text-xs text-slate-500 dark:text-slate-400 flex flex-wrap gap-3">
                                    <span v-if="customer.last_visit_at">آخر زيارة: <span dir="ltr">{{ customer.last_visit_at }}</span></span>
                                    <span v-if="customer.remaining_visits_for_loyalty !== null && customer.remaining_visits_for_loyalty > 0" class="text-amber-700 dark:text-amber-400 font-semibold">
                                        • متبقي {{ customer.remaining_visits_for_loyalty }} زيارات للجائزة الكبرى
                                    </span>
                                </div>

                                <p v-if="customer.notes" class="text-xs text-slate-600 dark:text-slate-300 bg-slate-50 dark:bg-slate-900/70 px-3 py-1.5 rounded-lg border border-slate-100 dark:border-slate-800 mt-1">
                                    📝 {{ customer.notes }}
                                </p>
                            </div>

                            <div class="flex items-center gap-2">
                                <button
                                    @click="openEditCustomer(customer)"
                                    type="button"
                                    class="px-3 py-1.5 rounded-xl text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition cursor-pointer"
                                >
                                    ✏️ تعديل / ملاحظة
                                </button>
                                <button
                                    @click="deleteCustomer(customer)"
                                    type="button"
                                    class="px-3 py-1.5 rounded-xl text-xs font-bold bg-rose-50 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 hover:bg-rose-100 transition cursor-pointer"
                                >
                                    🗑️ حذف
                                </button>
                            </div>
                        </div>

                        <!-- Associated Coupons -->
                        <div v-if="customer.promo_codes.length > 0" class="space-y-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                            <div class="text-xs font-bold text-slate-500 dark:text-slate-400">الكوبونات المرتبطة بهذا العميل:</div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5">
                                <div
                                    v-for="promo in customer.promo_codes"
                                    :key="promo.id"
                                    class="p-3 rounded-xl border border-slate-200 dark:border-slate-700/70 bg-slate-50/70 dark:bg-slate-900/60 flex flex-wrap items-center justify-between gap-2"
                                >
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="font-mono text-sm font-black text-slate-900 dark:text-white" dir="ltr">{{ promo.code }}</span>
                                            <span class="text-[11px] font-bold px-2 py-0.5 rounded-full border" :class="statusBadge(promo).cls">
                                                {{ statusBadge(promo).text }}
                                            </span>
                                            <span v-if="promo.source === 'loyalty'" class="text-[10px] font-black px-2 py-0.5 rounded-full bg-purple-600 text-white">
                                                🏆 جائزة ولاء
                                            </span>
                                        </div>
                                        <div class="text-xs font-bold text-amber-700 dark:text-amber-400">
                                            {{ promo.discount_label }}
                                            <span v-if="promo.expires_at" class="text-slate-500 dark:text-slate-400 font-normal">
                                                • ينتهي: <span dir="ltr">{{ promo.expires_at }}</span>
                                            </span>
                                        </div>
                                        <div v-if="promo.reminded_at" class="text-[11px] text-emerald-700 dark:text-emerald-400 font-semibold">
                                            ✓ تم التذكير ({{ promo.reminders_count }} مرة) — آخر تذكير: <span dir="ltr">{{ promo.reminded_at }}</span>
                                        </div>
                                    </div>

                                    <div v-if="promo.is_usable" class="flex items-center gap-1.5 flex-wrap">
                                        <!-- Direct Background Reminder (if WhatsApp connected) -->
                                        <button
                                            v-if="waStatus === 'connected'"
                                            @click="sendDirectBackgroundReminder(customer, promo)"
                                            :disabled="directReminderPendingId === promo.id"
                                            type="button"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-black transition shadow-xs disabled:opacity-50 cursor-pointer"
                                            title="إرسال رسالة تذكير فوري في الخلفية عبر رقم واتساب المطعم"
                                        >
                                            <span>📲</span>
                                            <span>{{ directReminderPendingId === promo.id ? 'جارٍ الإرسال...' : (promo.reminded_at ? 'تذكير فوري مجدداً' : 'تذكير فوري بالخلفية') }}</span>
                                        </button>

                                        <!-- Fallback / Manual WhatsApp Link -->
                                        <button
                                            @click="sendWhatsAppReminder(customer, promo)"
                                            :disabled="reminderPendingId === promo.id"
                                            type="button"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold transition shadow-xs disabled:opacity-60 cursor-pointer"
                                            :class="waStatus === 'connected'
                                                ? 'bg-slate-200 hover:bg-slate-300 dark:bg-slate-800 text-slate-700 dark:text-slate-300'
                                                : 'bg-emerald-600 hover:bg-emerald-500 text-white font-black'"
                                        >
                                            <span>💬</span>
                                            <span>{{ waStatus === 'connected' ? 'فتح بواتساب' : (promo.reminded_at ? 'تذكير مجدداً بالواتساب' : 'إرسال تذكير واتساب') }}</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- Add Customer Modal -->
        <div v-if="showAddModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4" dir="rtl" @click.self="showAddModal = false">
            <div class="bg-white dark:bg-[#0f172a] rounded-3xl max-w-md w-full p-6 border border-slate-200 dark:border-slate-700 shadow-2xl space-y-4" @click.stop>
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-black text-slate-900 dark:text-white">➕ إضافة عميل إلى سجل المطعم</h3>
                    <button @click="showAddModal = false" type="button" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white flex items-center justify-center font-bold text-sm cursor-pointer" title="إغلاق (Esc)">✕</button>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">الاسم الكامل:</label>
                        <input v-model="addForm.name" type="text" placeholder="مثال: أحمد محمد" class="w-full rounded-xl text-sm border-slate-200 dark:border-slate-700 dark:bg-slate-900 dark:text-white" />
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">رقم الواتساب (يبدأ بـ 09):</label>
                        <input v-model="addForm.phone" type="tel" dir="ltr" maxlength="10" placeholder="09XXXXXXXX" class="w-full rounded-xl text-sm font-mono border-slate-200 dark:border-slate-700 dark:bg-slate-900 dark:text-white" />
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">ملاحظات (اختياري):</label>
                        <textarea v-model="addForm.notes" rows="2" placeholder="تفضيلات الزبون أو ملاحظات خاصة..." class="w-full rounded-xl text-sm border-slate-200 dark:border-slate-700 dark:bg-slate-900 dark:text-white"></textarea>
                    </div>
                    <div v-if="addError" class="p-2.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 text-xs font-bold">
                        ⚠️ {{ addError }}
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button @click="showAddModal = false" type="button" class="px-4 py-2 rounded-xl text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 cursor-pointer">إلغاء</button>
                    <button @click="createCustomer" :disabled="savingAdd" type="button" class="px-5 py-2 rounded-xl text-xs font-black bg-emerald-600 text-white hover:bg-emerald-500 disabled:opacity-60 cursor-pointer">
                        {{ savingAdd ? 'جاري الحفظ...' : 'حفظ العميل' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Edit Notes Modal -->
        <div v-if="editingNotesCustomer" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4" dir="rtl" @click.self="editingNotesCustomer = null">
            <div class="bg-white dark:bg-[#0f172a] rounded-3xl max-w-md w-full p-6 border border-slate-200 dark:border-slate-700 shadow-2xl space-y-4" @click.stop>
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-black text-slate-900 dark:text-white">✏️ تحديث بيانات العميل</h3>
                    <button @click="editingNotesCustomer = null" type="button" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white flex items-center justify-center font-bold text-sm cursor-pointer" title="إغلاق (Esc)">✕</button>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">الاسم:</label>
                        <input v-model="editNotesForm.name" type="text" class="w-full rounded-xl text-sm border-slate-200 dark:border-slate-700 dark:bg-slate-900 dark:text-white" />
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">ملاحظات:</label>
                        <textarea v-model="editNotesForm.notes" rows="3" class="w-full rounded-xl text-sm border-slate-200 dark:border-slate-700 dark:bg-slate-900 dark:text-white"></textarea>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button @click="editingNotesCustomer = null" type="button" class="px-4 py-2 rounded-xl text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 cursor-pointer">إلغاء</button>
                    <button @click="saveCustomerNotes" :disabled="savingNotes" type="button" class="px-5 py-2 rounded-xl text-xs font-black bg-amber-500 text-white hover:bg-amber-600 disabled:opacity-60 cursor-pointer">
                        {{ savingNotes ? 'جاري الحفظ...' : 'حفظ التعديلات' }}
                    </button>
                </div>
            </div>
        </div>
        <!-- Floating Toast for Direct Reminder Success -->
        <transition enter-active-class="transition duration-300 ease-out" enter-from-class="transform translate-y-4 opacity-0" enter-to-class="transform translate-y-0 opacity-100" leave-active-class="transition duration-200 ease-in" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="directReminderSuccessToast" class="fixed bottom-6 left-6 z-50 p-4 rounded-2xl bg-emerald-700 text-white text-xs font-black shadow-2xl flex items-center gap-2 border border-emerald-500">
                <span>✓</span>
                <span>{{ directReminderSuccessToast }}</span>
            </div>
        </transition>

        <!-- WhatsApp Gateway Modal -->
        <div
            v-if="showWhatsappModal"
            class="fixed inset-0 z-50 bg-black/75 backdrop-blur-xs flex items-center justify-center p-3 sm:p-6 overflow-y-auto"
            dir="rtl"
            @click.self="showWhatsappModal = false"
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
                        @click="showWhatsappModal = false"
                        type="button"
                        class="w-9 h-9 rounded-full bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white flex items-center justify-center font-bold text-base transition cursor-pointer shadow-xs"
                        title="إغلاق النافذة (Esc)"
                    >
                        ✕
                    </button>
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

                            <!-- Test Message Input -->
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

                            <!-- QR Expiry & Live Refresh Indicator -->
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

                        <!-- 24h Expiring Alert Toggle -->
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

                        <!-- Auto-Reply Toggle & Message -->
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

                <!-- Sticky Footer with Explicit Close & Save -->
                <div class="p-4 sm:p-5 border-t border-slate-100 dark:border-slate-800 bg-slate-50/90 dark:bg-slate-900/90 backdrop-blur-xs flex items-center justify-between gap-3 shrink-0">
                    <button
                        @click="showWhatsappModal = false"
                        type="button"
                        class="px-4 sm:px-5 py-2.5 rounded-xl text-xs font-bold text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700 transition cursor-pointer flex items-center gap-1.5 shadow-xs"
                    >
                        <span>✕</span>
                        <span>إغلاق النافذة</span>
                    </button>

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
    </AuthenticatedLayout>
</template>

