<script setup lang="ts">
import AdminNav from '@/Components/Admin/AdminNav.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted, computed, defineAsyncComponent } from 'vue';
import axios from 'axios';
import { formatArabicCount } from '@/utils/arabic';
import type { CustomerRow, PromoItem, WaConnectionStatus } from '@/types/customers';

const AddCustomerModal = defineAsyncComponent(() => import('@/Components/Admin/Customers/AddCustomerModal.vue'));
const EditNotesModal = defineAsyncComponent(() => import('@/Components/Admin/Customers/EditNotesModal.vue'));
const RetentionCampaignModal = defineAsyncComponent(() => import('@/Components/Admin/Customers/RetentionCampaignModal.vue'));
const WhatsAppGatewayModal = defineAsyncComponent(() => import('@/Components/Admin/Customers/WhatsAppGatewayModal.vue'));

interface MenuItemOption {
    id: number;
    name: string;
    price: number | string;
}

const loading = ref(true);
const isLocked = ref(false);
const customers = ref<CustomerRow[]>([]);
const pagination = ref({ current_page: 1, last_page: 1, per_page: 30, total: 0 });
const menuItems = ref<MenuItemOption[]>([]);
const currency = ref('ل.س');
const stats = ref({
    total_customers: 0,
    returning_customers: 0,
    active_coupons: 0,
    needs_reminder: 0,
    loyalty_eligible: 0,
    vip_customers: 0,
    at_risk_customers: 0,
    new_leads_customers: 0,
});

const activeFilter = ref<'all' | 'vip' | 'at_risk' | 'new_leads' | 'needs_reminder' | 'has_active' | 'returning' | 'loyalty'>('all');
const searchQuery = ref('');

// Retention Campaign States
const showCampaignModal = ref(false);
const campaignCustomer = ref<CustomerRow | null>(null);
const campaignSuccessToast = ref<string | null>(null);

function openCampaignModal(customer: CustomerRow) {
    campaignCustomer.value = customer;
    showCampaignModal.value = true;
}

async function handleCampaignSent(payload: { sentDirectly: boolean; customerName: string }) {
    if (payload.sentDirectly) {
        campaignSuccessToast.value = `تم إرسال الحملة فوراً في الخلفية بنجاح إلى ${payload.customerName} 📲`;
        setTimeout(() => { campaignSuccessToast.value = null; }, 4500);
    }
    await fetchCustomers();
}

// Settings panel
const showSettings = ref(false);
const savingSettings = ref(false);
const settingsNotice = ref<string | null>(null);
const settingsForm = ref({
    wheel_require_whatsapp: true,
    wheel_reveal_code_after_save: false,
    wheel_activation_delay_hours: 2,
    loyalty_enabled: false,
    loyalty_target_visits: 5,
    loyalty_reward_type: 'percentage' as 'percentage' | 'fixed' | 'free_item',
    loyalty_reward_value: 50,
    loyalty_menu_item_id: null as number | null,
});

// Add / Edit Customer Modals
const showAddModal = ref(false);
const editingNotesCustomer = ref<CustomerRow | null>(null);
const reminderPendingId = ref<number | null>(null);

// WhatsApp Gateway Summary & Modal Triggers
const showWhatsappModal = ref(false);
const waAutoConnectOnOpen = ref(false);
const waStatus = ref<WaConnectionStatus>('disconnected');
const waPhone = ref<string | null>(null);

const directReminderPendingId = ref<number | null>(null);
const directReminderSuccessToast = ref<string | null>(null);

const pendingReminderCustomer = ref<CustomerRow | null>(null);
const pendingReminderPromo = ref<PromoItem | null>(null);

async function fetchWhatsappStatus() {
    try {
        const res = await axios.get('/api/admin/whatsapp/status');
        const data = res.data.data;
        waStatus.value = data.status || 'disconnected';
        waPhone.value = data.phone || null;
    } catch (_) {}
}

function openWhatsappModal(autoConnect = false) {
    waAutoConnectOnOpen.value = autoConnect;
    showWhatsappModal.value = true;
}

function closeWhatsappModal() {
    showWhatsappModal.value = false;
    waAutoConnectOnOpen.value = false;
    pendingReminderCustomer.value = null;
    pendingReminderPromo.value = null;
}

function handleWhatsappStatusChanged(payload: { status: WaConnectionStatus; phone: string | null }) {
    waStatus.value = payload.status;
    waPhone.value = payload.phone;
}

async function handleWhatsappConnectedWithPending(payload: { customer: CustomerRow; promo: PromoItem }) {
    closeWhatsappModal();
    await sendDirectBackgroundReminder(payload.customer, payload.promo);
}

async function sendDirectBackgroundReminder(customer: CustomerRow, promo: PromoItem) {
    if (waStatus.value !== 'connected') {
        pendingReminderCustomer.value = customer;
        pendingReminderPromo.value = promo;
        openWhatsappModal(waStatus.value === 'disconnected');
        return;
    }

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

async function forceManualWhatsAppReminder() {
    if (!pendingReminderCustomer.value || !pendingReminderPromo.value) return;
    const customer = pendingReminderCustomer.value;
    const promo = pendingReminderPromo.value;
    closeWhatsappModal();

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

async function fetchCustomers(pageNumber = pagination.value.current_page) {
    loading.value = true;
    try {
        const res = await axios.get('/api/admin/customers', {
            params: {
                filter: activeFilter.value,
                search: searchQuery.value,
                page: pageNumber,
                per_page: pagination.value.per_page,
            },
        });
        const data = res.data.data;
        customers.value = data.customers || [];
        if (data.pagination) {
            pagination.value = data.pagination;
        }
        stats.value = data.stats || stats.value;
        menuItems.value = data.menu_items || [];
        currency.value = data.currency || 'ل.س';

        if (data.crm_settings) {
            settingsForm.value.wheel_require_whatsapp = Boolean(data.crm_settings.wheel_require_whatsapp);
            settingsForm.value.wheel_reveal_code_after_save = Boolean(data.crm_settings.wheel_reveal_code_after_save);
            settingsForm.value.wheel_activation_delay_hours = Number(data.crm_settings.wheel_activation_delay_hours ?? 2);
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
        if (showCampaignModal.value) showCampaignModal.value = false;
        if (showWhatsappModal.value) closeWhatsappModal();
        if (showAddModal.value) showAddModal.value = false;
        if (editingNotesCustomer.value) editingNotesCustomer.value = null;
    }
}

onMounted(async () => {
    const params = new URLSearchParams(window.location.search);
    const shouldAutoConnectWa = params.get('connect_whatsapp') === '1';

    if (shouldAutoConnectWa) {
        params.delete('connect_whatsapp');
        const cleanQuery = params.toString();
        window.history.replaceState({}, '', `${window.location.pathname}${cleanQuery ? '?' + cleanQuery : ''}`);
    }

    fetchCustomers();
    await fetchWhatsappStatus();

    if (shouldAutoConnectWa) {
        openWhatsappModal(waStatus.value === 'disconnected' && !isLocked.value);
    }

    window.addEventListener('keydown', handleGlobalKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleGlobalKeydown);
});

let searchTimer: ReturnType<typeof setTimeout> | null = null;
function onSearchInput() {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => fetchCustomers(1), 350);
}

function setFilter(filter: typeof activeFilter.value) {
    activeFilter.value = filter;
    fetchCustomers(1);
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

function openEditCustomer(customer: CustomerRow) {
    editingNotesCustomer.value = customer;
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
    // التحقق من ربط واتساب المطعم أولاً
    if (waStatus.value !== 'connected') {
        pendingReminderCustomer.value = customer;
        pendingReminderPromo.value = promo;
        openWhatsappModal(waStatus.value === 'disconnected');
        return;
    }

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
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                    <button
                        @click="setFilter('all')"
                        type="button"
                        class="text-right bg-white dark:bg-[#0f172a] rounded-2xl p-3.5 border transition cursor-pointer shadow-xs hover:border-slate-300 dark:hover:border-slate-700"
                        :class="activeFilter === 'all' ? 'border-slate-900 dark:border-amber-500 ring-2 ring-slate-900/10 dark:ring-amber-500/20' : 'border-slate-100 dark:border-slate-800'"
                    >
                        <div class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">إجمالي العملاء</div>
                        <div class="text-xl font-black text-slate-900 dark:text-white mt-0.5">{{ stats.total_customers }}</div>
                    </button>

                    <button
                        @click="setFilter('vip')"
                        type="button"
                        class="text-right bg-white dark:bg-[#0f172a] rounded-2xl p-3.5 border transition cursor-pointer shadow-xs hover:border-amber-300 dark:hover:border-amber-700"
                        :class="activeFilter === 'vip' ? 'border-amber-500 ring-2 ring-amber-500/20' : 'border-amber-100 dark:border-amber-900/40'"
                    >
                        <div class="text-[11px] font-bold text-amber-700 dark:text-amber-400">👑 كبار العملاء VIP</div>
                        <div class="text-xl font-black text-amber-600 dark:text-amber-400 mt-0.5">{{ stats.vip_customers }}</div>
                    </button>

                    <button
                        @click="setFilter('at_risk')"
                        type="button"
                        class="text-right bg-white dark:bg-[#0f172a] rounded-2xl p-3.5 border transition cursor-pointer shadow-xs hover:border-rose-300 dark:hover:border-rose-700"
                        :class="activeFilter === 'at_risk' ? 'border-rose-500 ring-2 ring-rose-500/20' : 'border-rose-100 dark:border-rose-900/40'"
                    >
                        <div class="text-[11px] font-bold text-rose-700 dark:text-rose-400">⏳ في خطر الانقطاع</div>
                        <div class="text-xl font-black text-rose-600 dark:text-rose-400 mt-0.5">{{ stats.at_risk_customers }}</div>
                    </button>

                    <button
                        @click="setFilter('new_leads')"
                        type="button"
                        class="text-right bg-white dark:bg-[#0f172a] rounded-2xl p-3.5 border transition cursor-pointer shadow-xs hover:border-sky-300 dark:hover:border-sky-700"
                        :class="activeFilter === 'new_leads' ? 'border-sky-500 ring-2 ring-sky-500/20' : 'border-sky-100 dark:border-sky-900/40'"
                    >
                        <div class="text-[11px] font-bold text-sky-700 dark:text-sky-400">🎁 عملاء جدد (زيارة 1)</div>
                        <div class="text-xl font-black text-sky-600 dark:text-sky-400 mt-0.5">{{ stats.new_leads_customers }}</div>
                    </button>

                    <button
                        @click="setFilter('needs_reminder')"
                        type="button"
                        class="text-right bg-white dark:bg-[#0f172a] rounded-2xl p-3.5 border transition cursor-pointer shadow-xs hover:border-emerald-300 dark:hover:border-emerald-700"
                        :class="activeFilter === 'needs_reminder' ? 'border-emerald-500 ring-2 ring-emerald-500/20' : 'border-emerald-100 dark:border-emerald-900/40'"
                    >
                        <div class="text-[11px] font-bold text-emerald-700 dark:text-emerald-400">💬 بانتظار تذكير</div>
                        <div class="text-xl font-black text-emerald-600 dark:text-emerald-400 mt-0.5">{{ stats.needs_reminder }}</div>
                    </button>

                    <button
                        @click="setFilter('loyalty')"
                        type="button"
                        class="text-right bg-white dark:bg-[#0f172a] rounded-2xl p-3.5 border transition cursor-pointer shadow-xs hover:border-purple-300 dark:hover:border-purple-700"
                        :class="activeFilter === 'loyalty' ? 'border-purple-500 ring-2 ring-purple-500/20' : 'border-purple-100 dark:border-purple-900/40'"
                    >
                        <div class="text-[11px] font-bold text-purple-700 dark:text-purple-400">🏆 مستحقو الولاء</div>
                        <div class="text-xl font-black text-purple-600 dark:text-purple-400 mt-0.5">{{ stats.loyalty_eligible }}</div>
                    </button>
                </div>

                <!-- Action Bar & Settings Toggle -->
                <div class="bg-white dark:bg-[#0f172a] rounded-2xl p-4 border border-slate-100 dark:border-slate-800 shadow-xs space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <button
                                @click="openWhatsappModal(false)"
                                type="button"
                                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-black border transition cursor-pointer shadow-xs"
                                :class="waStatus === 'connected'
                                    ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-300 border-emerald-300 dark:border-emerald-700/60 hover:bg-emerald-100'
                                    : waStatus === 'scan_qr'
                                    ? 'bg-amber-500 text-stone-950 border-amber-500 animate-pulse'
                                    : 'bg-emerald-700 hover:bg-emerald-600 text-white border-emerald-700'"
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
                                class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl text-xs font-bold bg-emerald-700 hover:bg-emerald-600 text-white transition shadow-xs cursor-pointer"
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

                            <!-- Option 3: Wheel Promo Activation Timing -->
                            <div class="p-3.5 rounded-2xl border border-slate-200 dark:border-slate-700/70 bg-slate-50/60 dark:bg-slate-900/50 space-y-2 md:col-span-2">
                                <label class="block text-sm font-bold text-slate-900 dark:text-white">
                                    ⏱️ توقيت تفعيل كود خصم عجلة الحظ بعد اللعب
                                </label>
                                <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                                    <select
                                        v-model.number="settingsForm.wheel_activation_delay_hours"
                                        class="w-full sm:w-80 rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-sm font-bold text-slate-900 dark:text-white"
                                    >
                                        <option :value="2">بعد ساعتين (للزيارة القادمة — الافتراضي)</option>
                                        <option :value="0">تفعيل فوري مباشرةً (يمكن استخدامه في نفس الجلسة)</option>
                                        <option :value="12">بعد 12 ساعة (الزيارة التالية)</option>
                                        <option :value="24">بعد 24 ساعة (اليوم التالي)</option>
                                    </select>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">
                                        يحدد متى يصبح الكود صالحاً للتطبيق في السلة: «بعد ساعتين» يمنع استخدامه خلال نفس الجلسة، بينما «فوري» يسمح باستخدامه الآن.
                                    </p>
                                </div>
                            </div>
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
                                { id: 'vip', label: '👑 كبار العملاء VIP' },
                                { id: 'at_risk', label: '⏳ في خطر الانقطاع' },
                                { id: 'new_leads', label: '🎁 عملاء جدد' },
                                { id: 'needs_reminder', label: '💬 بانتظار التذكير' },
                                { id: 'has_active', label: '🎟️ لديهم كود فعّال' },
                                { id: 'returning', label: '🔁 عائدون (+2)' },
                                { id: 'loyalty', label: '🏆 مستحقو الجائزة' },
                            ]"
                            :key="tab.id"
                            @click="setFilter(tab.id as any)"
                            type="button"
                            class="px-3.5 py-2 rounded-xl text-xs font-bold transition cursor-pointer"
                            :class="activeFilter === tab.id
                                ? 'bg-slate-900 dark:bg-amber-500 text-white dark:text-stone-950 font-black shadow-xs'
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

                                    <!-- Smart Segment Badge -->
                                    <span
                                        v-if="customer.segment === 'vip'"
                                        class="text-xs font-black px-2.5 py-0.5 rounded-full bg-gradient-to-r from-amber-500 to-orange-500 text-white shadow-xs"
                                        title="أكثر من 5 زيارات مسجلة"
                                    >
                                        👑 عميل VIP
                                    </span>
                                    <span
                                        v-else-if="customer.segment === 'at_risk'"
                                        class="text-xs font-bold px-2.5 py-0.5 rounded-full bg-rose-100 text-rose-800 border border-rose-300 dark:bg-rose-950/70 dark:text-rose-300 dark:border-rose-700"
                                        :title="`لم يزر المطعم منذ ${customer.days_since_last_visit} يوماً`"
                                    >
                                        ⏳ في خطر الانقطاع (منذ {{ customer.days_since_last_visit }} يوماً)
                                    </span>
                                    <span
                                        v-else-if="customer.segment === 'new_lead'"
                                        class="text-xs font-bold px-2.5 py-0.5 rounded-full bg-sky-100 text-sky-800 border border-sky-300 dark:bg-sky-950/70 dark:text-sky-300 dark:border-sky-700"
                                    >
                                        🎁 عميل جديد
                                    </span>
                                    <span
                                        v-else
                                        class="text-xs font-bold px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 border border-emerald-300 dark:bg-emerald-950/70 dark:text-emerald-300 dark:border-emerald-700"
                                    >
                                        🔁 زبون متكرر
                                    </span>

                                    <span
                                        class="text-xs font-bold px-2.5 py-0.5 rounded-full border bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700"
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
                                        • متبقي {{ formatArabicCount(customer.remaining_visits_for_loyalty, 'زيارة', 'زيارتان', 'زيارات', 'زيارة', 'زيارة واحدة') }} للجائزة الكبرى
                                    </span>
                                </div>

                                <p v-if="customer.notes" class="text-xs text-slate-600 dark:text-slate-300 bg-slate-50 dark:bg-slate-900/70 px-3 py-1.5 rounded-lg border border-slate-100 dark:border-slate-800 mt-1">
                                    📝 {{ customer.notes }}
                                </p>
                            </div>

                            <div class="flex items-center gap-2">
                                <button
                                    @click="openCampaignModal(customer)"
                                    type="button"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-black bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white shadow-xs transition cursor-pointer"
                                    title="إرسال رسالة تواصل أو كود خصم خاص عبر الواتساب"
                                >
                                    <span>🚀</span>
                                    <span>حملة واتساب</span>
                                </button>
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
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-700 hover:bg-emerald-600 text-white text-xs font-black transition shadow-xs disabled:opacity-50 cursor-pointer"
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
                                                : 'bg-emerald-700 hover:bg-emerald-600 text-white font-black'"
                                        >
                                            <span>💬</span>
                                            <span>{{ waStatus === 'connected' ? 'فتح بواتساب' : (promo.reminded_at ? 'تذكير مجدداً بالواتساب' : 'إرسال تذكير واتساب') }}</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pagination Controls -->
                    <div v-if="pagination.last_page > 1" class="flex items-center justify-between gap-3 pt-2 bg-white dark:bg-[#0f172a] rounded-2xl p-3.5 border border-slate-100 dark:border-slate-800 shadow-xs">
                        <span class="text-xs font-bold text-slate-500 dark:text-slate-400">
                            صفحة {{ pagination.current_page }} من {{ pagination.last_page }} (إجمالي {{ pagination.total }} عميل)
                        </span>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                :disabled="pagination.current_page <= 1"
                                @click="fetchCustomers(pagination.current_page - 1)"
                                class="px-3.5 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 text-xs font-bold hover:bg-slate-200 dark:hover:bg-slate-700 disabled:opacity-40 cursor-pointer"
                            >
                                السابق
                            </button>
                            <button
                                type="button"
                                :disabled="pagination.current_page >= pagination.last_page"
                                @click="fetchCustomers(pagination.current_page + 1)"
                                class="px-3.5 py-1.5 rounded-xl bg-amber-500 text-stone-950 text-xs font-black hover:bg-amber-400 disabled:opacity-40 cursor-pointer"
                            >
                                التالي
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <AddCustomerModal
            v-if="showAddModal"
            @close="showAddModal = false"
            @created="fetchCustomers"
        />

        <EditNotesModal
            v-if="editingNotesCustomer"
            :customer="editingNotesCustomer"
            @close="editingNotesCustomer = null"
            @updated="fetchCustomers"
        />

        <!-- Floating Toast for Direct Reminder Success -->
        <transition enter-active-class="transition duration-300 ease-out" enter-from-class="transform translate-y-4 opacity-0" enter-to-class="transform translate-y-0 opacity-100" leave-active-class="transition duration-200 ease-in" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="directReminderSuccessToast" class="fixed bottom-6 left-6 z-50 p-4 rounded-2xl bg-emerald-700 text-white text-xs font-black shadow-2xl flex items-center gap-2 border border-emerald-500">
                <span>✓</span>
                <span>{{ directReminderSuccessToast }}</span>
            </div>
        </transition>

        <!-- Floating Toast for Campaign Success -->
        <transition enter-active-class="transition duration-300 ease-out" enter-from-class="transform translate-y-4 opacity-0" enter-to-class="transform translate-y-0 opacity-100" leave-active-class="transition duration-200 ease-in" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="campaignSuccessToast" class="fixed bottom-6 left-6 z-50 p-4 rounded-2xl bg-emerald-700 text-white text-xs font-black shadow-2xl flex items-center gap-2 border border-emerald-500">
                <span>✓</span>
                <span>{{ campaignSuccessToast }}</span>
            </div>
        </transition>

        <RetentionCampaignModal
            v-if="showCampaignModal && campaignCustomer"
            :customer="campaignCustomer"
            :currency="currency"
            :wa-status="waStatus"
            @close="showCampaignModal = false"
            @sent="handleCampaignSent"
        />

        <WhatsAppGatewayModal
            v-if="showWhatsappModal"
            :pending-reminder-customer="pendingReminderCustomer"
            :pending-reminder-promo="pendingReminderPromo"
            :auto-connect="waAutoConnectOnOpen"
            @close="closeWhatsappModal"
            @status-changed="handleWhatsappStatusChanged"
            @connected-with-pending="handleWhatsappConnectedWithPending"
            @force-manual="forceManualWhatsAppReminder"
        />
    </AuthenticatedLayout>
</template>

