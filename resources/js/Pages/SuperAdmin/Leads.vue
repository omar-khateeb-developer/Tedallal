<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import axios from 'axios';

interface Lead {
    id: number;
    name: string;
    restaurant_name: string;
    phone: string;
    city: string | null;
    service_type: string | null;
    payment_model: string | null;
    kitchen_setup: string | null;
    desired_features: string[] | null;
    notes: string | null;
    status: 'new' | 'contacted' | 'qualified' | 'closed_won' | 'closed_lost';
    created_at: string;
    ai_paused?: boolean;
    togglingAi?: boolean;
}

const leads = ref<Lead[]>([]);
const loading = ref(true);
const search = ref('');
const statusFilter = ref('all');
const editingNotesId = ref<number | null>(null);
const currentNotes = ref('');
const savingNotes = ref(false);

const selectedLeadForProvision = ref<Lead | null>(null);
const provisioning = ref(false);
const provisionResult = ref<any | null>(null);
const provisionError = ref<string | null>(null);

const openProvisionModal = (lead: Lead) => {
    selectedLeadForProvision.value = lead;
    provisionResult.value = null;
    provisionError.value = null;
};

const closeProvisionModal = () => {
    selectedLeadForProvision.value = null;
    provisionResult.value = null;
    provisionError.value = null;
};

const confirmProvision = async () => {
    if (!selectedLeadForProvision.value) return;
    provisioning.value = true;
    provisionError.value = null;
    try {
        const { data } = await axios.post(`/api/super-admin/leads/${selectedLeadForProvision.value.id}/provision`);
        if (data.success) {
            provisionResult.value = data.data;
            const idx = leads.value.findIndex(l => l.id === selectedLeadForProvision.value?.id);
            if (idx !== -1) {
                leads.value[idx].status = 'closed_won';
            }
            fetchLeads();
        } else {
            provisionError.value = data.message || 'حدث خطأ أثناء تدشين المنشأة.';
        }
    } catch (e: any) {
        provisionError.value = e.response?.data?.message || 'تعذر تدشين المطعم، يرجى المحاولة لاحقاً.';
    } finally {
        provisioning.value = false;
    }
};

const copyToClipboard = (text: string) => {
    navigator.clipboard.writeText(text);
    alert('تم نسخ البيانات للحافظة بنجاح! 📋');
};

// Smart Prospect Pitch Card state (بطاقة الاتصال الذكية)
const selectedLeadForPitch = ref<Lead | null>(null);
const pitchCardData = ref<any>(null);
const loadingPitchCard = ref(false);
const pitchCardError = ref('');

const openPitchCardModal = async (lead: Lead) => {
    selectedLeadForPitch.value = lead;
    pitchCardData.value = null;
    pitchCardError.value = '';
    loadingPitchCard.value = true;
    try {
        const { data } = await axios.get(`/api/super-admin/leads/${lead.id}/pitch-card`);
        if (data.success) {
            pitchCardData.value = data.data;
        } else {
            pitchCardError.value = 'تعذر تجهيز بطاقة الاتصال.';
        }
    } catch (e: any) {
        pitchCardError.value = e.response?.data?.message || 'تعذر تحميل بطاقة الاتصال الذكية.';
    } finally {
        loadingPitchCard.value = false;
    }
};

const closePitchCardModal = () => {
    selectedLeadForPitch.value = null;
    pitchCardData.value = null;
    pitchCardError.value = '';
};

const stats = ref({
    total: 0,
    new: 0,
    contacted: 0,
    qualified: 0,
    closed_won: 0,
    closed_lost: 0,
});

const fetchLeads = async () => {
    loading.value = true;
    try {
        const response = await axios.get('/api/super-admin/leads', {
            params: {
                search: search.value,
                status: statusFilter.value,
            },
        });
        leads.value = response.data.data;
        stats.value = response.data.stats;
    } catch (e) {
        console.error('Failed to load leads', e);
    } finally {
        loading.value = false;
    }
};

const updateStatus = async (lead: Lead, newStatus: string) => {
    try {
        await axios.patch(`/api/super-admin/leads/${lead.id}`, { status: newStatus });
        lead.status = newStatus as any;
        // Refresh stats
        const response = await axios.get('/api/super-admin/leads');
        stats.value = response.data.stats;
    } catch (e) {
        alert('حدث خطأ أثناء تحديث الحالة');
    }
};

const openEditNotes = (lead: Lead) => {
    editingNotesId.value = lead.id;
    currentNotes.value = lead.notes || '';
};

const saveNotes = async (lead: Lead) => {
    savingNotes.value = true;
    try {
        await axios.patch(`/api/super-admin/leads/${lead.id}`, { notes: currentNotes.value });
        lead.notes = currentNotes.value;
        editingNotesId.value = null;
    } catch (e) {
        alert('فشل حفظ الملاحظات');
    } finally {
        savingNotes.value = false;
    }
};

const deleteLead = async (lead: Lead) => {
    if (!confirm(`هل أنت متأكد من حذف استشارة «${lead.name} - ${lead.restaurant_name}»؟`)) return;
    try {
        await axios.delete(`/api/super-admin/leads/${lead.id}`);
        leads.value = leads.value.filter(l => l.id !== lead.id);
        fetchLeads();
    } catch (e) {
        alert('فشل حذف السجل');
    }
};

const toggleLeadAi = async (lead: Lead) => {
    lead.togglingAi = true;
    try {
        const { data } = await axios.post(`/api/super-admin/leads/${lead.id}/toggle-ai`);
        lead.ai_paused = data.data.ai_paused;
    } catch (e) {
        alert('تعذر تحديث حالة المساعد الذكي');
    } finally {
        lead.togglingAi = false;
    }
};

const cleanPhoneForWhatsApp = (phone: string) => {
    let clean = phone.replace(/[^0-9]/g, '');
    if (clean.startsWith('09')) {
        clean = '963' + clean.substring(1);
    } else if (clean.startsWith('00963')) {
        clean = '963' + clean.substring(5);
    } else if (clean.startsWith('+963')) {
        clean = '963' + clean.substring(4);
    }
    return clean;
};

const getWhatsAppUrl = (lead: Lead) => {
    const phone = cleanPhoneForWhatsApp(lead.phone);
    const message = encodeURIComponent(`مرحباً أستاذ ${lead.name}، معك عمر خطيب من منصة تدلّل بخصوص طلب التقييم والاستشارة الفنية لمطعم «${lead.restaurant_name}». يسعدني التواصل معك لمناقشة الخطة التشغيلية وتفعيل النظام.`);
    return `https://wa.me/${phone}?text=${message}`;
};

const formatDate = (dateStr: string) => {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleDateString('ar-SY-u-nu-latn', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

onMounted(fetchLeads);
</script>

<template>
    <Head title="إدارة العملاء المحتملين (Leads CRM) - منصة تدلّل" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between flex-wrap gap-4">
                <div class="flex items-center gap-3">
                    <div class="h-9 w-2.5 bg-gradient-to-b from-amber-400 to-orange-500 rounded-full"></div>
                    <div>
                        <h2 class="font-black text-xl text-gray-900 dark:text-white leading-tight flex items-center gap-2">
                            <span>إدارة طلبات الاستشارة والعملاء المحتملين</span>
                            <span class="text-xs font-mono font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-900 border border-amber-300">
                                Leads CRM
                            </span>
                        </h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            متابعة أصحاب المطاعم الذين قاموا بتعبئة استمارة التقييم والتشغيل.
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <Link
                        :href="route('super_admin.dashboard')"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold bg-white dark:bg-slate-800 text-gray-700 dark:text-gray-200 border border-gray-200 dark:border-slate-700 hover:bg-gray-50 transition shadow-2xs"
                    >
                        <span>← العودة لإدارة المطاعم</span>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8" dir="rtl">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Stats KPIs Grid -->
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                    <div class="bg-white dark:bg-[#0a0f1d] p-4 rounded-2xl border border-stone-200 dark:border-slate-800 shadow-2xs">
                        <p class="text-xs font-bold text-gray-500 dark:text-gray-400 mb-1">إجمالي الاستشارات</p>
                        <p class="text-2xl font-black text-gray-900 dark:text-white">{{ stats.total }}</p>
                    </div>

                    <div class="bg-amber-50/70 dark:bg-amber-950/30 p-4 rounded-2xl border border-amber-200 dark:border-amber-800/60 shadow-2xs">
                        <div class="flex items-center justify-between mb-1">
                            <p class="text-xs font-bold text-amber-800 dark:text-amber-300">طلبات جديدة</p>
                            <span v-if="stats.new > 0" class="h-2 w-2 rounded-full bg-amber-500 animate-ping"></span>
                        </div>
                        <p class="text-2xl font-black text-amber-600 dark:text-amber-400">{{ stats.new }}</p>
                    </div>

                    <div class="bg-blue-50/70 dark:bg-blue-950/30 p-4 rounded-2xl border border-blue-200 dark:border-blue-800/60 shadow-2xs">
                        <p class="text-xs font-bold text-blue-800 dark:text-blue-300 mb-1">تم التواصل</p>
                        <p class="text-2xl font-black text-blue-600 dark:text-blue-400">{{ stats.contacted }}</p>
                    </div>

                    <div class="bg-purple-50/70 dark:bg-purple-950/30 p-4 rounded-2xl border border-purple-200 dark:border-purple-800/60 shadow-2xs">
                        <p class="text-xs font-bold text-purple-800 dark:text-purple-300 mb-1">عملاء جادين ⭐</p>
                        <p class="text-2xl font-black text-purple-600 dark:text-purple-400">{{ stats.qualified }}</p>
                    </div>

                    <div class="bg-emerald-50/70 dark:bg-emerald-950/30 p-4 rounded-2xl border border-emerald-200 dark:border-emerald-800/60 shadow-2xs">
                        <p class="text-xs font-bold text-emerald-800 dark:text-emerald-300 mb-1">تم الاشتراك 🎉</p>
                        <p class="text-2xl font-black text-emerald-600 dark:text-emerald-400">{{ stats.closed_won }}</p>
                    </div>

                    <div class="bg-stone-50 dark:bg-stone-900/40 p-4 rounded-2xl border border-stone-200 dark:border-slate-800 shadow-2xs">
                        <p class="text-xs font-bold text-stone-500 dark:text-stone-400 mb-1">مؤجل / غير مهتم</p>
                        <p class="text-2xl font-black text-stone-600 dark:text-stone-400">{{ stats.closed_lost }}</p>
                    </div>
                </div>

                <!-- Controls & Filters -->
                <div class="bg-white dark:bg-[#0a0f1d] p-5 rounded-2xl border border-stone-200 dark:border-slate-800 shadow-xs flex flex-col md:flex-row items-center justify-between gap-4">
                    <!-- Search Input -->
                    <div class="w-full md:w-96 relative">
                        <input
                            v-model="search"
                            @input="fetchLeads"
                            type="text"
                            placeholder="بحث بالاسم، المطعم، الهاتف، أو المدينة..."
                            class="w-full rounded-xl border-gray-300 dark:border-slate-700 bg-gray-50 dark:bg-slate-900 text-sm py-2.5 pr-10 pl-4 focus:border-amber-500 focus:ring-amber-500 text-gray-900 dark:text-white"
                        />
                        <svg class="w-4 h-4 text-gray-400 absolute right-3.5 top-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>

                    <!-- Status Filter Tabs -->
                    <div class="flex items-center gap-1.5 overflow-x-auto w-full md:w-auto pb-1 md:pb-0 hide-scrollbar">
                        <button
                            v-for="filter in [
                                { id: 'all', label: 'الكل' },
                                { id: 'new', label: 'جديد' },
                                { id: 'contacted', label: 'تم التواصل' },
                                { id: 'qualified', label: 'جاد' },
                                { id: 'closed_won', label: 'مشترك' },
                                { id: 'closed_lost', label: 'مؤجل' },
                            ]"
                            :key="filter.id"
                            type="button"
                            @click="statusFilter = filter.id; fetchLeads()"
                            class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition whitespace-nowrap"
                            :class="statusFilter === filter.id
                                ? 'bg-amber-600 text-white shadow-xs'
                                : 'bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-gray-300 hover:bg-gray-200'"
                        >
                            {{ filter.label }}
                        </button>
                    </div>
                </div>

                <!-- Leads Table -->
                <div class="bg-white dark:bg-[#0a0f1d] rounded-2xl border border-stone-200 dark:border-slate-800 shadow-xs overflow-hidden">
                    <div v-if="loading" class="text-center py-16">
                        <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-amber-500 border-t-transparent"></div>
                        <p class="text-xs text-gray-500 mt-2">جاري تحميل الاستشارات...</p>
                    </div>

                    <div v-else-if="leads.length === 0" class="text-center py-16 px-4">
                        <div class="text-4xl mb-3">📭</div>
                        <h4 class="text-base font-bold text-gray-800 dark:text-white">لا توجد طلبات استشارة حالياً</h4>
                        <p class="text-xs text-gray-500 mt-1">الطلبات التي يقدمها الزوار عبر صفحة الاستشارة ستظهر هنا مباشرة.</p>
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-right border-collapse whitespace-nowrap text-sm">
                            <thead>
                                <tr class="bg-gray-50/80 dark:bg-slate-900/60 border-b border-gray-200 dark:border-slate-800 text-xs font-bold text-gray-600 dark:text-gray-400">
                                    <th class="p-4">صاحب المطعم والمطعم</th>
                                    <th class="p-4">الاتصال السريع</th>
                                    <th class="p-4">طبيعة التشغيل والمحاسبة</th>
                                    <th class="p-4">الميزات المطلوبة</th>
                                    <th class="p-4">ملاحظات المتابعة</th>
                                    <th class="p-4 text-center">حالة العميل</th>
                                    <th class="p-4 text-center">إجراءات</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-slate-800/80">
                                <tr v-for="lead in leads" :key="lead.id" class="hover:bg-amber-50/30 dark:hover:bg-slate-900/30 transition">
                                    <!-- Lead Details -->
                                    <td class="p-4">
                                        <div class="flex items-center gap-2.5">
                                            <div class="h-10 w-10 rounded-xl bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400 font-bold flex items-center justify-center text-sm shrink-0">
                                                {{ lead.name.charAt(0) }}
                                            </div>
                                            <div>
                                                <p class="font-bold text-gray-900 dark:text-white">{{ lead.name }}</p>
                                                <p class="text-xs font-semibold text-amber-700 dark:text-amber-400">{{ lead.restaurant_name }}</p>
                                                <p class="text-[11px] text-gray-400 mt-0.5 flex items-center gap-1">
                                                    <span v-if="lead.city">📍 {{ lead.city }} ·</span>
                                                    <span>{{ formatDate(lead.created_at) }}</span>
                                                </p>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Quick Contact & AI Takeover Control -->
                                    <td class="p-4">
                                        <div class="flex flex-col gap-1.5">
                                            <div class="flex items-center gap-2">
                                                <!-- WhatsApp Button -->
                                                <a
                                                    :href="getWhatsAppUrl(lead)"
                                                    target="_blank"
                                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white shadow-2xs transition"
                                                    title="مراسلة العميل عبر واتساب مباشرة"
                                                >
                                                    <span>💬 واتساب</span>
                                                </a>
                                                <!-- Phone Link -->
                                                <a
                                                    :href="`tel:${lead.phone}`"
                                                    class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl text-xs font-bold bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-gray-200 hover:bg-gray-200 transition"
                                                    dir="ltr"
                                                >
                                                    <span>📞</span>
                                                    <span>{{ lead.phone }}</span>
                                                </a>
                                            </div>

                                            <!-- Human Takeover / AI Auto-Reply Badge & Switcher -->
                                            <button
                                                type="button"
                                                @click="toggleLeadAi(lead)"
                                                :disabled="lead.togglingAi"
                                                class="inline-flex items-center justify-between gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-bold transition shadow-2xs w-fit cursor-pointer"
                                                :class="lead.ai_paused
                                                    ? 'bg-amber-100 dark:bg-amber-950/60 text-amber-900 dark:text-amber-300 border border-amber-300 dark:border-amber-800 hover:bg-amber-200'
                                                    : 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 hover:bg-emerald-100'"
                                                :title="lead.ai_paused ? 'المساعد الذكي صامت لهذه المحادثة لتتحدث بشرياً. انقر لإعادة تفعيله.' : 'المساعد الذكي يرد تلقائياً. انقر لإيقافه مؤقتاً لتتحدث بشرياً.'"
                                            >
                                                <span v-if="lead.togglingAi" class="animate-spin text-[10px]">⏳</span>
                                                <span v-else>{{ lead.ai_paused ? '⏸️ محادثة بشرية (صامت)' : '🤖 المساعد نشط (تلقائي)' }}</span>
                                                <span class="text-[9px] opacity-70 underline">{{ lead.ai_paused ? 'تشغيل' : 'إيقاف' }}</span>
                                            </button>
                                        </div>
                                    </td>

                                    <!-- Operating model -->
                                    <td class="p-4">
                                        <div class="flex flex-col gap-1 text-xs">
                                            <span class="font-bold text-gray-800 dark:text-gray-200">{{ lead.service_type || 'غير محدد' }}</span>
                                            <span class="text-gray-500 text-[11px]">{{ lead.payment_model }} · {{ lead.kitchen_setup }}</span>
                                        </div>
                                    </td>

                                    <!-- Desired Features -->
                                    <td class="p-4">
                                        <div class="flex flex-wrap gap-1 max-w-xs">
                                            <span
                                                v-for="(feat, fIdx) in (lead.desired_features || [])"
                                                :key="fIdx"
                                                class="text-[10px] font-bold px-2 py-0.5 rounded-md bg-stone-100 dark:bg-slate-800 text-stone-700 dark:text-slate-300 border border-stone-200 dark:border-slate-700"
                                            >
                                                {{ feat }}
                                            </span>
                                            <span v-if="!lead.desired_features || lead.desired_features.length === 0" class="text-xs text-gray-400">
                                                الأساسية فقط
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Notes -->
                                    <td class="p-4 max-w-xs">
                                        <div v-if="editingNotesId === lead.id" class="flex flex-col gap-1.5">
                                            <textarea
                                                v-model="currentNotes"
                                                rows="2"
                                                placeholder="اكتب ملاحظاتك عن العميل..."
                                                class="w-full text-xs rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-900"
                                            ></textarea>
                                            <div class="flex items-center gap-1">
                                                <button
                                                    type="button"
                                                    @click="saveNotes(lead)"
                                                    :disabled="savingNotes"
                                                    class="px-2 py-1 rounded bg-amber-600 text-white text-[10px] font-bold"
                                                >
                                                    حفظ
                                                </button>
                                                <button
                                                    type="button"
                                                    @click="editingNotesId = null"
                                                    class="px-2 py-1 rounded bg-gray-200 text-gray-700 text-[10px]"
                                                >
                                                    إلغاء
                                                </button>
                                            </div>
                                        </div>
                                        <div v-else @click="openEditNotes(lead)" class="cursor-pointer group flex items-start gap-1">
                                            <p class="text-xs text-gray-600 dark:text-gray-400 line-clamp-2">
                                                {{ lead.notes || 'انقر لإضافة ملاحظات متابعة...' }}
                                            </p>
                                            <span class="text-gray-300 group-hover:text-amber-600 text-xs shrink-0">✏️</span>
                                        </div>
                                    </td>

                                    <!-- Status Selector -->
                                    <td class="p-4 text-center">
                                        <select
                                            :value="lead.status"
                                            @change="updateStatus(lead, ($event.target as HTMLSelectElement).value)"
                                            class="text-xs font-bold rounded-xl px-2.5 py-1.5 border shadow-2xs transition cursor-pointer"
                                            :class="{
                                                'bg-amber-100 text-amber-900 border-amber-300': lead.status === 'new',
                                                'bg-blue-100 text-blue-900 border-blue-300': lead.status === 'contacted',
                                                'bg-purple-100 text-purple-900 border-purple-300': lead.status === 'qualified',
                                                'bg-emerald-100 text-emerald-900 border-emerald-300': lead.status === 'closed_won',
                                                'bg-stone-100 text-stone-700 border-stone-300': lead.status === 'closed_lost',
                                            }"
                                        >
                                            <option value="new">🟢 جديد (New)</option>
                                            <option value="contacted">📞 تم التواصل (Contacted)</option>
                                            <option value="qualified">⭐ جاد ومؤهل (Qualified)</option>
                                            <option value="closed_won">🎉 تم الاشتراك (Closed Won)</option>
                                            <option value="closed_lost">⏸️ مؤجل / غير مهتم (Lost)</option>
                                        </select>
                                    </td>

                                    <!-- Actions -->
                                    <td class="p-4 text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <!-- Smart Prospect Pitch Card Button -->
                                            <button
                                                type="button"
                                                @click="openPitchCardModal(lead)"
                                                class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl text-xs font-bold bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800/60 hover:bg-amber-100 dark:hover:bg-amber-900/40 transition shadow-2xs"
                                                title="بطاقة الاتصال الذكية: ماذا أقول لهذا العميل؟"
                                            >
                                                <span>💡</span>
                                                <span>بطاقة الاتصال</span>
                                            </button>

                                            <!-- 1-Click Provisioning Button -->
                                            <button
                                                v-if="lead.status !== 'closed_won'"
                                                type="button"
                                                @click="openProvisionModal(lead)"
                                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-black bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-white shadow-xs transition hover:scale-105 active:scale-95"
                                                title="تدشين حساب المطعم والمالك فوراً وإرسال البيانات بالواتساب"
                                            >
                                                <span>🚀</span>
                                                <span>تدشين المطعم</span>
                                            </button>
                                            <span
                                                v-else
                                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-bold bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300"
                                            >
                                                <span>✓</span> تم التدشين
                                            </span>

                                            <!-- Delete Button -->
                                            <button
                                                type="button"
                                                @click="deleteLead(lead)"
                                                class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/40 transition"
                                                title="حذف الاستشارة"
                                            >
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 1-Click Provisioning Modal -->
                <div
                    v-if="selectedLeadForProvision"
                    class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4"
                >
                    <div class="bg-white dark:bg-[#0f172a] rounded-3xl max-w-lg w-full border border-stone-200 dark:border-slate-800 shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
                        <!-- Modal Header -->
                        <div class="bg-gradient-to-r from-amber-600 to-amber-700 p-6 text-white text-center relative">
                            <span class="inline-block p-2.5 rounded-2xl bg-white/10 text-2xl mb-2">🚀</span>
                            <h3 class="text-lg font-black">تدشين مساحة المطعم الفورية</h3>
                            <p class="text-xs text-amber-100 mt-1">توليد الحساب، المنيو الافتراضي، وإرسال البيانات للواتساب بنقرة واحدة</p>
                            <button
                                @click="closeProvisionModal"
                                class="absolute top-4 left-4 p-1.5 rounded-full bg-white/10 hover:bg-white/20 text-white text-xs"
                            >
                                ✕
                            </button>
                        </div>

                        <!-- Modal Body -->
                        <div class="p-6">
                            <!-- If not yet provisioned -->
                            <div v-if="!provisionResult">
                                <div class="bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/60 rounded-2xl p-4 mb-5 text-xs text-amber-900 dark:text-amber-200 space-y-2">
                                    <p><strong>🏢 اسم المطعم:</strong> {{ selectedLeadForProvision.restaurant_name }}</p>
                                    <p><strong>👤 المسؤول / المالك:</strong> {{ selectedLeadForProvision.name }}</p>
                                    <p><strong>📞 هاتف التواصل:</strong> <span dir="ltr">{{ selectedLeadForProvision.phone }}</span></p>
                                    <p><strong>📍 المدينة:</strong> {{ selectedLeadForProvision.city || 'دمشق' }}</p>
                                </div>

                                <div class="text-xs text-gray-600 dark:text-gray-300 space-y-2 mb-6">
                                    <p class="font-bold text-gray-900 dark:text-white">ما سيحدث فور التأكيد:</p>
                                    <ul class="list-disc list-inside space-y-1 pr-2 text-stone-600 dark:text-slate-400">
                                        <li>إنشاء سجل مطعم متكامل بباقة <strong class="text-amber-600">Pro الاحترافية</strong> بصلاحية 30 يوماً.</li>
                                        <li>توليد 5 طاولات افتراضية مجهزة برموز QR الرقمية للطلبات.</li>
                                        <li>إنشاء قائمة منيو أولية بأصناف حقيقية قابلة للتعديل فوراً.</li>
                                        <li>توليد حساب مدير المطعم بكلمة مرور مؤقتة وتعيين صلاحية Admin.</li>
                                        <li><strong>إرسال رسالة ترحيب وبيانات الدخول للواتساب تلقائياً</strong> إلى هاتف المالك!</li>
                                    </ul>
                                </div>

                                <div v-if="provisionError" class="p-3 bg-red-50 text-red-700 rounded-xl text-xs font-bold mb-4">
                                    {{ provisionError }}
                                </div>

                                <div class="flex items-center justify-end gap-2.5">
                                    <button
                                        type="button"
                                        @click="closeProvisionModal"
                                        :disabled="provisioning"
                                        class="px-4 py-2.5 rounded-xl border border-gray-300 dark:border-slate-700 text-xs font-bold text-gray-700 dark:text-gray-300 hover:bg-gray-100"
                                    >
                                        إلغاء
                                    </button>
                                    <button
                                        type="button"
                                        @click="confirmProvision"
                                        :disabled="provisioning"
                                        class="px-6 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-black shadow-md flex items-center gap-2"
                                    >
                                        <span v-if="provisioning" class="inline-block animate-spin h-3.5 w-3.5 border-2 border-white border-t-transparent rounded-full"></span>
                                        <span>{{ provisioning ? 'جاري التدشين وإرسال البيانات...' : 'تأكيد التدشين وتوليد الحساب' }}</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Success Result Screen -->
                            <div v-else class="text-center space-y-4">
                                <div class="w-12 h-12 bg-emerald-100 text-emerald-600 rounded-2xl flex items-center justify-center text-2xl mx-auto">
                                    ✓
                                </div>
                                <h4 class="text-base font-black text-gray-900 dark:text-white">تم تدشين المطعم بنجاح تام! 🎉</h4>
                                <p class="text-xs text-gray-500">تم تجهيز الحساب والمنيو وإرسال رسالة الترحيب إلى واتساب المالك.</p>

                                <div class="bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl p-4 text-xs text-right space-y-2 font-mono">
                                    <div>
                                        <span class="text-gray-500 font-sans">رابط لوحة الإدارة:</span>
                                        <p class="font-bold text-amber-600 select-all" dir="ltr">{{ provisionResult.credentials.login_url }}</p>
                                    </div>
                                    <div>
                                        <span class="text-gray-500 font-sans">اسم المستخدم (البريد):</span>
                                        <p class="font-bold text-gray-800 dark:text-gray-200 select-all" dir="ltr">{{ provisionResult.credentials.email }}</p>
                                    </div>
                                    <div>
                                        <span class="text-gray-500 font-sans">كلمة المرور المؤقتة:</span>
                                        <p class="font-bold text-emerald-600 select-all" dir="ltr">{{ provisionResult.credentials.password }}</p>
                                    </div>
                                    <div>
                                        <span class="text-gray-500 font-sans">رابط منيو الزبائن الذكي:</span>
                                        <p class="font-bold text-blue-600 select-all" dir="ltr">{{ provisionResult.restaurant.menu_url }}</p>
                                    </div>
                                </div>

                                <div class="flex items-center justify-center gap-2 pt-2">
                                    <button
                                        type="button"
                                        @click="copyToClipboard(`🏢 مطعم: ${provisionResult.restaurant.name}\n🔗 لوحة التحكم: ${provisionResult.credentials.login_url}\n👤 اسم المستخدم: ${provisionResult.credentials.email}\n🔑 كلمة المرور: ${provisionResult.credentials.password}\n📱 المنيو الذكي: ${provisionResult.restaurant.menu_url}`)"
                                        class="px-4 py-2 rounded-xl bg-gray-100 dark:bg-slate-800 text-xs font-bold text-gray-700 dark:text-gray-300 hover:bg-gray-200"
                                    >
                                        📋 نسخ البيانات
                                    </button>
                                    <button
                                        type="button"
                                        @click="closeProvisionModal"
                                        class="px-5 py-2 rounded-xl bg-amber-600 text-white text-xs font-bold hover:bg-amber-700"
                                    >
                                        تم الانتهاء
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Smart Prospect Pitch Card Modal (بطاقة الاتصال الذكية) -->
                <div
                    v-if="selectedLeadForPitch"
                    class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto"
                >
                    <div class="bg-white dark:bg-[#0f172a] rounded-3xl max-w-2xl w-full border border-stone-200 dark:border-slate-800 shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200 my-8">
                        <!-- Header -->
                        <div class="bg-gradient-to-r from-amber-600 via-orange-600 to-amber-700 p-6 text-white text-right relative">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <span class="p-2.5 rounded-2xl bg-white/10 text-2xl">💡</span>
                                    <div>
                                        <h3 class="text-lg font-black flex items-center gap-2">
                                            <span>بطاقة الاتصال الذكية</span>
                                            <span class="text-[11px] font-mono px-2 py-0.5 rounded-full bg-white/20">Pitch Card</span>
                                        </h3>
                                        <p class="text-xs text-amber-100 mt-0.5">
                                            دليلك التنفيذي السريع: ماذا تقول لهذا العميل بالتحديد لإغلاق الصفقة؟
                                        </p>
                                    </div>
                                </div>
                                <button
                                    @click="closePitchCardModal"
                                    class="p-2 rounded-full bg-white/10 hover:bg-white/20 text-white text-sm"
                                >
                                    ✕
                                </button>
                            </div>
                        </div>

                        <!-- Body -->
                        <div class="p-6 space-y-5 text-right max-h-[75vh] overflow-y-auto">
                            <!-- Loading State -->
                            <div v-if="loadingPitchCard" class="text-center py-12">
                                <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-amber-500 border-t-transparent"></div>
                                <p class="text-xs text-gray-500 mt-2">جاري توليد خطة الاتصال وفق الأركان الذهبية...</p>
                            </div>

                            <!-- Error State -->
                            <div v-else-if="pitchCardError" class="p-4 bg-red-50 text-red-700 rounded-2xl text-xs font-bold">
                                {{ pitchCardError }}
                            </div>

                            <!-- Content -->
                            <div v-else-if="pitchCardData" class="space-y-5">
                                <!-- Prospect Mini Card -->
                                <div class="bg-amber-50/60 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-800/60 rounded-2xl p-4 text-xs flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <p class="font-bold text-gray-900 dark:text-white text-sm">{{ pitchCardData.lead_summary.restaurant_name }}</p>
                                        <p class="text-gray-500 dark:text-gray-400 mt-0.5">
                                            👤 {{ pitchCardData.lead_summary.name }} · 📍 {{ pitchCardData.lead_summary.city }} · 🍽️ {{ pitchCardData.lead_summary.service_type }} ({{ pitchCardData.lead_summary.tables }} طاولة)
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <a
                                            :href="`https://wa.me/${cleanPhoneForWhatsApp(pitchCardData.lead_summary.phone)}?text=${encodeURIComponent(pitchCardData.opening_script)}`"
                                            target="_blank"
                                            class="px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-2xs"
                                        >
                                            💬 إرسال الافتتاحية واتساب
                                        </a>
                                        <a
                                            :href="`tel:${pitchCardData.lead_summary.phone}`"
                                            class="px-3 py-1.5 rounded-xl bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-gray-200 font-bold text-xs"
                                        >
                                            📞 اتصال
                                        </a>
                                    </div>
                                </div>

                                <!-- 1. Opening Hook -->
                                <div class="bg-white dark:bg-slate-900/60 border border-gray-200 dark:border-slate-800 rounded-2xl p-4 shadow-2xs">
                                    <div class="flex items-center justify-between mb-2">
                                        <h4 class="text-xs font-black text-amber-700 dark:text-amber-400 flex items-center gap-1.5">
                                            <span>🎯</span>
                                            <span>الخطوة 1: افتتاحية المكالمة المقترحة (Opening Hook)</span>
                                        </h4>
                                        <button
                                            type="button"
                                            @click="copyToClipboard(pitchCardData.opening_script)"
                                            class="text-[11px] font-bold text-gray-500 hover:text-amber-600"
                                        >
                                            📋 نسخ
                                        </button>
                                    </div>
                                    <p class="text-xs text-gray-700 dark:text-gray-300 leading-relaxed bg-gray-50 dark:bg-slate-950/40 p-3 rounded-xl border border-gray-100 dark:border-slate-800">
                                        {{ pitchCardData.opening_script }}
                                    </p>
                                </div>

                                <!-- 2. Diagnostic Question -->
                                <div class="bg-white dark:bg-slate-900/60 border border-gray-200 dark:border-slate-800 rounded-2xl p-4 shadow-2xs">
                                    <div class="flex items-center justify-between mb-2">
                                        <h4 class="text-xs font-black text-blue-700 dark:text-blue-400 flex items-center gap-1.5">
                                            <span>🩺</span>
                                            <span>الخطوة 2: سؤال التشخيص الحاسم (لا تسرد الميزات!)</span>
                                        </h4>
                                        <button
                                            type="button"
                                            @click="copyToClipboard(pitchCardData.diagnostic_script)"
                                            class="text-[11px] font-bold text-gray-500 hover:text-blue-600"
                                        >
                                            📋 نسخ
                                        </button>
                                    </div>
                                    <p class="text-xs text-gray-700 dark:text-gray-300 leading-relaxed bg-blue-50/40 dark:bg-blue-950/20 p-3 rounded-xl border border-blue-100 dark:border-blue-900/40">
                                        {{ pitchCardData.diagnostic_script }}
                                    </p>
                                </div>

                                <!-- 3. Recommended Golden Pillar -->
                                <div class="bg-gradient-to-br from-amber-50/50 to-orange-50/30 dark:from-slate-900 dark:to-slate-950 border border-amber-200 dark:border-amber-800/60 rounded-2xl p-5 shadow-2xs">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-xs font-black text-amber-800 dark:text-amber-300 flex items-center gap-1.5">
                                            <span>👑</span>
                                            <span>الركن الذهبي المرشح بقوة لهذا المطعم:</span>
                                        </span>
                                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-black bg-amber-600 text-white">
                                            {{ pitchCardData.primary_pillar.badge }}
                                        </span>
                                    </div>
                                    <p class="text-sm font-black text-gray-900 dark:text-white mb-1">
                                        {{ pitchCardData.primary_pillar.name }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
                                        {{ pitchCardData.primary_pillar.why }}
                                    </p>
                                    <div class="bg-white/80 dark:bg-slate-900/80 p-3 rounded-xl border border-amber-100 dark:border-slate-800 space-y-1.5 mb-3">
                                        <p class="text-xs font-bold text-stone-800 dark:text-stone-200">النقاط التي تذكرها له:</p>
                                        <ul class="text-xs text-gray-600 dark:text-gray-300 space-y-1 list-disc list-inside pr-1">
                                            <li v-for="(pt, pIdx) in pitchCardData.primary_pillar.talking_points" :key="pIdx">
                                                {{ pt }}
                                            </li>
                                        </ul>
                                    </div>
                                    <div class="text-[11px] text-amber-800 dark:text-amber-300 font-bold">
                                        💼 الباقة المقترحة له: {{ pitchCardData.recommended_plan }}
                                    </div>
                                </div>

                                <!-- 4. The 60-Second Demo Closing Weapon -->
                                <div class="bg-purple-50/60 dark:bg-purple-950/20 border border-purple-200 dark:border-purple-800/60 rounded-2xl p-4 shadow-2xs">
                                    <div class="flex items-center justify-between mb-2">
                                        <h4 class="text-xs font-black text-purple-700 dark:text-purple-400 flex items-center gap-1.5">
                                            <span>⚡</span>
                                            <span>الخطوة 3: سلاح الإثبات الفوري في 60 ثانية (The Demo Weapon)</span>
                                        </h4>
                                        <button
                                            type="button"
                                            @click="copyToClipboard(pitchCardData.demo_pitch_script)"
                                            class="text-[11px] font-bold text-gray-500 hover:text-purple-600"
                                        >
                                            📋 نسخ النص
                                        </button>
                                    </div>
                                    <p class="text-xs text-gray-700 dark:text-gray-300 leading-relaxed bg-white/70 dark:bg-slate-900/60 p-3 rounded-xl border border-purple-100 dark:border-purple-900/40 mb-2">
                                        {{ pitchCardData.demo_pitch_script }}
                                    </p>
                                    <div class="flex items-center gap-2">
                                        <a
                                            :href="pitchCardData.demo_url"
                                            target="_blank"
                                            class="text-xs font-bold text-purple-700 dark:text-purple-300 underline"
                                        >
                                            🔗 رابط المحاكي التفاعلي (tedallal.tech/demo)
                                        </a>
                                    </div>
                                </div>

                                <!-- 5. Objection Handlers -->
                                <div class="bg-white dark:bg-slate-900/60 border border-gray-200 dark:border-slate-800 rounded-2xl p-4 shadow-2xs space-y-3">
                                    <h4 class="text-xs font-black text-gray-900 dark:text-white flex items-center gap-1.5">
                                        <span>🛡️</span>
                                        <span>التعامل مع الاعتراضات الشائعة (Objection Handlers)</span>
                                    </h4>
                                    <div
                                        v-for="(obj, oIdx) in pitchCardData.objection_handlers"
                                        :key="oIdx"
                                        class="bg-gray-50 dark:bg-slate-950/40 p-3 rounded-xl border border-gray-100 dark:border-slate-800 text-xs space-y-1"
                                    >
                                        <p class="font-bold text-red-700 dark:text-red-400">إذا قال: {{ obj.objection }}</p>
                                        <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                                            <strong class="text-emerald-700 dark:text-emerald-400">ردك الحاسم:</strong> {{ obj.response }}
                                        </p>
                                    </div>
                                </div>

                                <!-- Pilot Offer Box -->
                                <div class="bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800/60 rounded-2xl p-3 text-xs text-emerald-900 dark:text-emerald-200 flex items-center justify-between">
                                    <span>🎁 <strong>عرض الإغلاق:</strong> {{ pitchCardData.pilot_offer }}</span>
                                    <button
                                        type="button"
                                        @click="copyToClipboard(`🎁 مرحباً أستاذ ${pitchCardData.lead_summary.name}، يسعدنا في منصة تَدلّل أن نقدم لمطعم «${pitchCardData.lead_summary.restaurant_name}» فترة تدشين وتشغيل اختباري مدعومة بالكامل 7 أيام داخل صالتكم ومطبخكم مجاناً لتتأكدوا من النتائج عملياً قبل تثبيت الاشتراك!`)"
                                        class="text-[11px] font-bold text-emerald-700 hover:underline"
                                    >
                                        نسخ عرض التجربة
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="bg-gray-50 dark:bg-slate-900/80 p-4 border-t border-gray-200 dark:border-slate-800 flex items-center justify-between">
                            <button
                                type="button"
                                @click="closePitchCardModal"
                                class="px-4 py-2 rounded-xl border border-gray-300 dark:border-slate-700 text-xs font-bold text-gray-700 dark:text-gray-300 hover:bg-gray-100"
                            >
                                إغلاق
                            </button>
                            <button
                                v-if="pitchCardData"
                                type="button"
                                @click="copyToClipboard(`🏢 مطعم: ${pitchCardData.lead_summary.restaurant_name}\n👤 المالك: ${pitchCardData.lead_summary.name}\n\n🎯 الافتتاحية:\n${pitchCardData.opening_script}\n\n🩺 سؤال التشخيص:\n${pitchCardData.diagnostic_script}\n\n👑 الركن الذهبي: ${pitchCardData.primary_pillar.name}\n⚡ المحاكي: ${pitchCardData.demo_url}`)"
                                class="px-5 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold shadow-xs flex items-center gap-1.5"
                            >
                                <span>📋</span>
                                <span>نسخ كامل بطاقة المكالمة</span>
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

