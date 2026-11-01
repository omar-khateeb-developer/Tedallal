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
}

const leads = ref<Lead[]>([]);
const loading = ref(true);
const search = ref('');
const statusFilter = ref('all');
const editingNotesId = ref<number | null>(null);
const currentNotes = ref('');
const savingNotes = ref(false);

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
    return date.toLocaleDateString('ar-SY', {
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

                                    <!-- Quick Contact -->
                                    <td class="p-4">
                                        <div class="flex items-center gap-2">
                                            <!-- WhatsApp Button -->
                                            <a
                                                :href="getWhatsAppUrl(lead)"
                                                target="_blank"
                                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white shadow-2xs transition"
                                                title="مراسلة العميل عبر واتساب مباشرة برك رسالة جاهزة"
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
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
