<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted, nextTick, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import BackupManagerPanel from '@/Components/SuperAdmin/BackupManagerPanel.vue';
import MaintenancePanel from '@/Components/SuperAdmin/MaintenancePanel.vue';
import PlatformWhatsAppAiPanel from '@/Components/SuperAdmin/PlatformWhatsAppAiPanel.vue';
import PromoDiagnosticsPanel from '@/Components/SuperAdmin/PromoDiagnosticsPanel.vue';
import axios from 'axios';

const restaurants = ref<any[]>([]);
const stats = ref({
    total_restaurants: 0,
    active_restaurants: 0,
    total_users: 0,
});

const showCreateForm = ref(false);
const form = ref({
    name: '',
    type: 'postpaid',
    plan: 'basic',
    subscription_days: 30,
    windows_count: 1,
    tables_count: 10,
    phone: '',
    address: '',
    currency: 'SYP',
    manager_name: '',
    manager_email: '',
    manager_password: '',
});
const showPassword = ref(false);
const loading = ref(false);
const message = ref('');
const isSuccess = ref(false);
const errors = ref<Record<string, string[]>>({});
const formRef = ref<HTMLElement | null>(null);

const toggleCreateForm = async () => {
    showCreateForm.value = !showCreateForm.value;
    if (showCreateForm.value) {
        // Reset state when opening
        message.value = '';
        isSuccess.value = false;
        errors.value = {};
        await nextTick();
        formRef.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
};

const resetForm = () => {
    form.value = {
        name: '', type: 'postpaid', plan: 'basic', subscription_days: 30, windows_count: 1, tables_count: 10,
        phone: '', address: '', currency: 'SYP',
        manager_name: '', manager_email: '', manager_password: ''
    };
    errors.value = {};
    message.value = '';
    isSuccess.value = false;
};

function fieldError(field: string): string | undefined {
    return errors.value[field]?.[0];
}

const fetchRestaurants = async () => {
    try {
        const response = await axios.get('/api/super-admin/restaurants');
        restaurants.value = response.data.data ?? response.data;
    } catch (e) {
        console.error(e);
    }
};

// ==========================================
// الفلترة والترتيب الذكي لقائمة المطاعم
// ==========================================
type RestaurantSortField = 'created_at' | 'name' | 'type' | 'plan' | 'days_remaining' | 'status' | 'phone';

const searchQuery = ref('');
const filterStatus = ref<'all' | 'active' | 'inactive'>('all');
const filterType = ref<'all' | 'postpaid' | 'prepaid'>('all');
const filterPlan = ref<'all' | 'basic' | 'pro' | 'max'>('all');
const filterSubscription = ref<'all' | 'valid' | 'expiring_soon' | 'expired' | 'lifetime'>('all');
const filterFeature = ref<string>('all');
const sortBy = ref<RestaurantSortField>('created_at');
const sortOrder = ref<'asc' | 'desc'>('desc');

const toggleSort = (field: RestaurantSortField) => {
    if (sortBy.value === field) {
        sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = field;
        if (field === 'name') {
            sortOrder.value = 'asc';
        } else if (field === 'days_remaining') {
            sortOrder.value = 'asc'; // الأقرب انتهاءً أولاً
        } else {
            sortOrder.value = 'desc';
        }
    }
};

const resetFilters = () => {
    searchQuery.value = '';
    filterStatus.value = 'all';
    filterType.value = 'all';
    filterPlan.value = 'all';
    filterSubscription.value = 'all';
    filterFeature.value = 'all';
    sortBy.value = 'created_at';
    sortOrder.value = 'desc';
};

const hasActiveFilters = computed(() => {
    return (
        searchQuery.value.trim() !== '' ||
        filterStatus.value !== 'all' ||
        filterType.value !== 'all' ||
        filterPlan.value !== 'all' ||
        filterSubscription.value !== 'all' ||
        filterFeature.value !== 'all' ||
        sortBy.value !== 'created_at' ||
        sortOrder.value !== 'desc'
    );
});

const filterCounts = computed(() => {
    const list = restaurants.value || [];
    return {
        all: list.length,
        active: list.filter(r => r.status === 'active').length,
        inactive: list.filter(r => r.status === 'inactive').length,
        postpaid: list.filter(r => (r.type || 'postpaid') === 'postpaid').length,
        prepaid: list.filter(r => r.type === 'prepaid').length,
        expiredOrExpiring: list.filter(r => r.is_expired || (r.days_remaining !== null && r.days_remaining <= 7)).length,
        planBasic: list.filter(r => (r.plan || 'basic') === 'basic').length,
        planPro: list.filter(r => r.plan === 'pro').length,
        planMax: list.filter(r => r.plan === 'max').length,
    };
});

const planRankMap: Record<string, number> = {
    'max': 3,
    'pro': 2,
    'basic': 1,
};

const filteredAndSortedRestaurants = computed(() => {
    let result = [...(restaurants.value || [])];

    // 1. فلترة بالبحث النصي
    const q = searchQuery.value.trim().toLowerCase();
    if (q) {
        result = result.filter(r => {
            const nameMatch = (r.name || '').toLowerCase().includes(q);
            const phoneMatch = (r.phone || '').toLowerCase().includes(q);
            const slugMatch = (r.slug || '').toLowerCase().includes(q);
            const currencyMatch = (r.currency || '').toLowerCase().includes(q);
            return nameMatch || phoneMatch || slugMatch || currencyMatch;
        });
    }

    // 2. فلترة بحالة المطعم
    if (filterStatus.value !== 'all') {
        result = result.filter(r => r.status === filterStatus.value);
    }

    // 3. فلترة بنوع النظام
    if (filterType.value !== 'all') {
        result = result.filter(r => (r.type || 'postpaid') === filterType.value);
    }

    // 4. فلترة بالباقة
    if (filterPlan.value !== 'all') {
        result = result.filter(r => (r.plan || 'basic') === filterPlan.value);
    }

    // 5. فلترة بحالة الاشتراك والصلاحية
    if (filterSubscription.value !== 'all') {
        if (filterSubscription.value === 'expired') {
            result = result.filter(r => !!r.is_expired);
        } else if (filterSubscription.value === 'expiring_soon') {
            result = result.filter(r => !r.is_expired && r.days_remaining !== null && r.days_remaining <= 7);
        } else if (filterSubscription.value === 'valid') {
            result = result.filter(r => !r.is_expired && (r.days_remaining === null || r.days_remaining > 7));
        } else if (filterSubscription.value === 'lifetime') {
            result = result.filter(r => !r.is_expired && r.days_remaining === null);
        }
    }

    // 6. فلترة بالميزات الإضافية
    if (filterFeature.value !== 'all') {
        result = result.filter(r => {
            const feats = r.effective_features || r.features || {};
            return !!feats[filterFeature.value];
        });
    }

    // 7. الترتيب الديناميكي حسب الحقل والاتجاه
    result.sort((a, b) => {
        let cmp = 0;
        const field = sortBy.value;

        if (field === 'name') {
            cmp = (a.name || '').localeCompare(b.name || '', 'ar', { sensitivity: 'base' });
        } else if (field === 'created_at') {
            const dateA = a.created_at ? new Date(a.created_at).getTime() : (a.id || 0);
            const dateB = b.created_at ? new Date(b.created_at).getTime() : (b.id || 0);
            cmp = dateA - dateB;
        } else if (field === 'type') {
            cmp = (a.type || 'postpaid').localeCompare(b.type || 'postpaid');
        } else if (field === 'plan') {
            const rankA = planRankMap[a.plan || 'basic'] || 0;
            const rankB = planRankMap[b.plan || 'basic'] || 0;
            cmp = rankA - rankB;
        } else if (field === 'days_remaining') {
            const daysA = a.is_expired ? -1 : (a.days_remaining === null ? 999999 : Number(a.days_remaining));
            const daysB = b.is_expired ? -1 : (b.days_remaining === null ? 999999 : Number(b.days_remaining));
            cmp = daysA - daysB;
        } else if (field === 'status') {
            cmp = (a.status || '').localeCompare(b.status || '');
        } else if (field === 'phone') {
            cmp = (a.phone || '').localeCompare(b.phone || '');
        }

        return sortOrder.value === 'asc' ? cmp : -cmp;
    });

    return result;
});

const fetchStats = async () => {
    try {
        const response = await axios.get('/api/super-admin/dashboard/stats');
        stats.value = response.data.data;
    } catch (e) {
        console.error(e);
    }
};

const toggleStatus = async (restaurant: any) => {
    try {
        const newStatus = restaurant.status === 'active' ? 'inactive' : 'active';
        await axios.patch(`/api/super-admin/restaurants/${restaurant.id}`, { status: newStatus });
        await fetchRestaurants();
        await fetchStats();
    } catch (e) {
        console.error('Failed to toggle status');
    }
};

const toggleRestaurantType = async (restaurant: any) => {
    const newType = restaurant.type === 'prepaid' ? 'postpaid' : 'prepaid';
    const confirmMsg = newType === 'prepaid'
        ? `هل تريد تحويل نظام «${restaurant.name}» إلى دفع مسبق (كاشير ونوافذ تسليم)؟`
        : `هل تريد تحويل نظام «${restaurant.name}» إلى دفع لاحق (طاولات وضيافة)؟`;
    if (!confirm(confirmMsg)) return;
    try {
        await axios.patch(`/api/super-admin/restaurants/${restaurant.id}`, {
            type: newType,
            windows_count: newType === 'prepaid' ? (restaurant.windows_count || 3) : 1,
        });
        await fetchRestaurants();
    } catch (e) {
        console.error('Failed to change restaurant type');
    }
};

const toggleFeature = async (restaurant: any, featureName: string) => {
    try {
        const features = restaurant.effective_features || restaurant.features || {};
        const currentStatus = !!features[featureName];
        await axios.patch(`/api/super-admin/restaurants/${restaurant.id}`, {
            features: { [featureName]: !currentStatus }
        });
        await fetchRestaurants();
    } catch (e) {
        console.error(`Failed to toggle ${featureName}`);
    }
};

const changePlan = async (restaurant: any, newPlan: string) => {
    try {
        await axios.patch(`/api/super-admin/restaurants/${restaurant.id}`, { plan: newPlan });
        await fetchRestaurants();
    } catch (e) {
        console.error('Failed to change plan');
    }
};

const extendSubscription = async (restaurant: any, days: number) => {
    try {
        await axios.patch(`/api/super-admin/restaurants/${restaurant.id}`, { extend_days: days });
        await fetchRestaurants();
    } catch (e) {
        console.error('Failed to extend subscription');
    }
};

const createRestaurant = async () => {
    loading.value = true;
    message.value = '';
    errors.value = {};
    isSuccess.value = false;
    try {
        await axios.post('/api/super-admin/restaurants', form.value);
        message.value = 'تم إضافة المطعم والمدير بنجاح!';
        isSuccess.value = true;
        resetForm();
        isSuccess.value = true;
        message.value = 'تم إضافة المطعم والمدير بنجاح!';
        await fetchRestaurants();
        await fetchStats();
        // Auto-collapse after 2s
        setTimeout(() => {
            showCreateForm.value = false;
            setTimeout(() => {
                isSuccess.value = false;
                message.value = '';
            }, 400);
        }, 2000);
    } catch (e: any) {
        if (e.response?.status === 422 && e.response?.data?.errors) {
            errors.value = e.response.data.errors;
        }
        message.value = e.response?.data?.message || 'حدث خطأ أثناء الإضافة';
    } finally {
        loading.value = false;
    }
};

const leadsStats = ref<any>(null);

const fetchLeadsStats = async () => {
    try {
        const response = await axios.get('/api/super-admin/leads');
        leadsStats.value = response.data.stats;
    } catch (e) {
        console.error('Failed to fetch leads stats');
    }
};

onMounted(() => {
    fetchStats();
    fetchRestaurants();
    fetchLeadsStats();
});
</script>

<template>
    <Head title="إدارة مطاعم - مدير النظام" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">إدارة المطاعم</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <MaintenancePanel />

                <BackupManagerPanel />

                <PromoDiagnosticsPanel :restaurants="restaurants" />

                <PlatformWhatsAppAiPanel />

                <div class="bg-white dark:bg-[#0a0f1d] border border-transparent dark:border-slate-800 overflow-hidden shadow-sm sm:rounded-2xl transition-colors">
                    <div class="p-6 text-gray-900 dark:text-slate-100" dir="rtl">
                        <!-- Stats Grid -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                            <div class="bg-amber-50 dark:bg-amber-950/30 p-5 rounded-2xl border border-amber-100 dark:border-amber-500/30 flex items-center justify-between">
                                <div>
                                    <p class="text-xs text-amber-800 dark:text-amber-300 font-semibold mb-1">إجمالي المطاعم</p>
                                    <p class="text-2xl font-black text-amber-600 dark:text-amber-400">{{ stats.total_restaurants }}</p>
                                </div>
                                <div class="bg-amber-200 dark:bg-amber-900/50 text-amber-700 dark:text-amber-300 p-2.5 rounded-full">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72l1.189-1.19A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72M6.75 18h3.75a.75.75 0 0 0 .75-.75V13.5a.75.75 0 0 0-.75-.75H6.75a.75.75 0 0 0-.75.75v3.75c0 .414.336.75.75.75Z" /></svg>
                                </div>
                            </div>
                            <div class="bg-green-50 dark:bg-emerald-950/30 p-5 rounded-2xl border border-green-100 dark:border-emerald-500/30 flex items-center justify-between">
                                <div>
                                    <p class="text-xs text-green-800 dark:text-emerald-300 font-semibold mb-1">المطاعم النشطة</p>
                                    <p class="text-2xl font-black text-green-600 dark:text-emerald-400">{{ stats.active_restaurants }}</p>
                                </div>
                                <div class="bg-green-200 dark:bg-emerald-900/50 text-green-700 dark:text-emerald-300 p-2.5 rounded-full">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                                </div>
                            </div>
                            <Link :href="route('super_admin.users')" class="bg-blue-50 dark:bg-blue-950/30 p-5 rounded-2xl border border-blue-100 dark:border-blue-500/30 flex items-center justify-between hover:bg-blue-100 dark:hover:bg-blue-900/40 transition-colors">
                                <div>
                                    <p class="text-xs text-blue-800 dark:text-blue-300 font-semibold mb-1">المستخدمين (إدارة)</p>
                                    <p class="text-2xl font-black text-blue-600 dark:text-blue-400">{{ stats.total_users }}</p>
                                </div>
                                <div class="bg-blue-200 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300 p-2.5 rounded-full">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" /></svg>
                                </div>
                            </Link>

                            <!-- Leads CRM Card -->
                            <Link :href="route('super_admin.leads')" class="bg-gradient-to-br from-amber-50 to-orange-50 dark:from-amber-950/40 dark:to-orange-950/30 p-5 rounded-2xl border-2 border-amber-300 dark:border-amber-500/40 flex items-center justify-between hover:border-amber-400 hover:shadow-md transition-all group">
                                <div>
                                    <div class="flex items-center gap-1.5 mb-1">
                                        <p class="text-xs text-amber-900 dark:text-amber-200 font-bold">طلبات الاستشارة (Leads)</p>
                                        <span v-if="leadsStats?.new > 0" class="text-[10px] px-1.5 py-0.2 rounded-full font-bold bg-rose-500 text-white animate-pulse">
                                            {{ leadsStats.new }} جديد
                                        </span>
                                    </div>
                                    <p class="text-2xl font-black text-amber-700 dark:text-amber-400">
                                        {{ leadsStats?.total ?? '0' }}
                                    </p>
                                </div>
                                <div class="bg-amber-200 dark:bg-amber-900/50 text-amber-900 dark:text-amber-300 p-2.5 rounded-2xl group-hover:scale-110 transition-transform">
                                    <span class="text-xl">📋</span>
                                </div>
                            </Link>
                        </div>

                        <!-- Restaurant List Header + Add Button -->
                        <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
                            <h3 class="text-lg font-bold text-gray-800 dark:text-white">قائمة المطاعم في النظام</h3>
                            <div class="flex items-center gap-2 flex-wrap">
                                <Link
                                    :href="route('super_admin.leads')"
                                    class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl font-bold text-xs sm:text-sm transition-all duration-200 border border-amber-400 dark:border-amber-500/40 text-amber-900 dark:text-amber-200 bg-amber-100 dark:bg-amber-950/50 hover:bg-amber-200 dark:hover:bg-amber-900/60 shadow-2xs"
                                >
                                    <span>📋 متابعة طلبات الاستشارة (Leads)</span>
                                    <span v-if="leadsStats?.new > 0" class="bg-rose-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full">
                                        {{ leadsStats.new }}
                                    </span>
                                </Link>
                                <Link
                                    :href="route('super_admin.restaurants.create')"
                                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl font-medium text-xs sm:text-sm transition-all duration-200 border border-amber-300 dark:border-amber-500/40 text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-950/30 hover:bg-amber-100 dark:hover:bg-amber-900/50"
                                >
                                    <span>إنشاء مطعم تفصيلي</span>
                                    <span>↗</span>
                                </Link>
                                <button
                                    @click="toggleCreateForm"
                                    class="inline-flex items-center gap-2 px-4 sm:px-5 py-2 sm:py-2.5 rounded-xl font-medium text-xs sm:text-sm transition-all duration-300 shadow-sm focus:ring-2 focus:ring-offset-2"
                                    :class="showCreateForm
                                        ? 'bg-stone-100 text-stone-600 hover:bg-stone-200 focus:ring-stone-400'
                                        : 'bg-amber-600 text-white hover:bg-amber-700 hover:shadow-md focus:ring-amber-500'"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                                        class="size-4 transition-transform duration-300"
                                        :class="{ 'rotate-45': showCreateForm }">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                    </svg>
                                    {{ showCreateForm ? 'إلغاء' : 'إضافة مطعم جديد' }}
                                </button>
                            </div>
                        </div>

                        <!-- Collapsible Create Restaurant Form -->
                        <Transition
                            enter-active-class="transition-all duration-400 ease-out"
                            enter-from-class="opacity-0 max-h-0 -translate-y-2"
                            enter-to-class="opacity-100 max-h-[800px] translate-y-0"
                            leave-active-class="transition-all duration-300 ease-in"
                            leave-from-class="opacity-100 max-h-[800px] translate-y-0"
                            leave-to-class="opacity-0 max-h-0 -translate-y-2"
                        >
                            <div v-show="showCreateForm" ref="formRef" class="overflow-hidden mb-8">
                                <div class="rounded-2xl border-2 border-dashed border-amber-200 bg-gradient-to-b from-amber-50/80 to-white p-6">
                                    <!-- Form Header -->
                                    <div class="flex items-center gap-3 mb-6">
                                        <div class="bg-amber-100 text-amber-600 p-2.5 rounded-xl">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72l1.189-1.19A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72M6.75 18h3.75a.75.75 0 0 0 .75-.75V13.5a.75.75 0 0 0-.75-.75H6.75a.75.75 0 0 0-.75.75v3.75c0 .414.336.75.75.75Z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <h4 class="font-bold text-gray-800">تسجيل مطعم جديد</h4>
                                            <p class="text-xs text-gray-500">أدخل بيانات المطعم والمدير لإنشائه في المنصة</p>
                                        </div>
                                    </div>

                                    <form @submit.prevent="createRestaurant" class="space-y-6">
                                        <!-- Restaurant Section -->
                                        <fieldset>
                                            <legend class="text-sm font-bold text-amber-700 border-b border-amber-100 pb-2 mb-4 w-full flex items-center gap-2">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 0h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z" />
                                                </svg>
                                                بيانات المطعم
                                            </legend>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div>
                                                    <label for="sa-restaurant-name" class="block text-sm font-medium text-gray-700 mb-1">اسم المطعم <span class="text-red-500">*</span></label>
                                                    <input id="sa-restaurant-name" v-model="form.name" required
                                                        class="w-full rounded-xl border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 text-sm transition"
                                                        :class="{ 'border-red-400 focus:border-red-500 focus:ring-red-500': fieldError('name') }"
                                                        placeholder="مثال: مطعم الديوان" />
                                                    <p v-if="fieldError('name')" class="text-xs text-red-500 mt-1">{{ fieldError('name') }}</p>
                                                </div>
                                                <div>
                                                    <label for="sa-restaurant-currency" class="block text-sm font-medium text-gray-700 mb-1">العملة <span class="text-red-500">*</span></label>
                                                    <select id="sa-restaurant-currency" v-model="form.currency" required
                                                        class="w-full rounded-xl border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 text-sm transition" dir="ltr">
                                                        <option value="SYP">SYP - ليرة سورية</option>
                                                        <option value="SAR">SAR - ريال سعودي</option>
                                                        <option value="AED">AED - درهم إماراتي</option>
                                                        <option value="KWD">KWD - دينار كويتي</option>
                                                        <option value="QAR">QAR - ريال قطري</option>
                                                        <option value="BHD">BHD - دينار بحريني</option>
                                                        <option value="OMR">OMR - ريال عماني</option>
                                                        <option value="EGP">EGP - جنيه مصري</option>
                                                        <option value="JOD">JOD - دينار أردني</option>
                                                        <option value="USD">USD - دولار أمريكي</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label for="sa-restaurant-phone" class="block text-sm font-medium text-gray-700 mb-1">رقم الهاتف</label>
                                                    <input id="sa-restaurant-phone" v-model="form.phone"
                                                        class="w-full rounded-xl border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 text-sm transition" dir="ltr"
                                                        placeholder="+963 5XXXXXXXX" />
                                                </div>
                                                <div>
                                                    <label for="sa-restaurant-address" class="block text-sm font-medium text-gray-700 mb-1">العنوان</label>
                                                    <input id="sa-restaurant-address" v-model="form.address"
                                                        class="w-full rounded-xl border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 text-sm transition"
                                                        placeholder="المدينة، الحي، الشارع" />
                                                </div>

                                                <!-- خيار نوع المطعم وطريقة المحاسبة (دفع لاحق أم دفع مسبق) -->
                                                <div class="col-span-full mt-2">
                                                    <label class="block text-sm font-bold text-gray-800 mb-2">نوع نظام المطعم وطريقة المحاسبة <span class="text-red-500">*</span></label>
                                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                                                        <!-- دفع لاحق -->
                                                        <button
                                                            type="button"
                                                            @click="form.type = 'postpaid'"
                                                            :class="[
                                                                'p-3.5 rounded-2xl border-2 text-right transition-all flex items-start gap-3 cursor-pointer',
                                                                form.type === 'postpaid'
                                                                    ? 'border-amber-600 bg-amber-50/80 shadow-xs ring-2 ring-amber-500/20'
                                                                    : 'border-gray-200 hover:border-gray-300 bg-white'
                                                            ]"
                                                        >
                                                            <div class="text-2xl p-1.5 rounded-xl bg-white shadow-xs shrink-0">🍽️</div>
                                                            <div>
                                                                <p class="font-bold text-sm text-gray-900">مطعم دفع لاحق (طاولات وضيافة صالة)</p>
                                                                <p class="text-xs text-gray-500 mt-0.5 leading-relaxed">
                                                                    يصل الطلب مباشرة إلى المطبخ ويحاسب الزبون بعد انتهاء الوجبة قبل المغادرة.
                                                                </p>
                                                            </div>
                                                        </button>

                                                        <!-- دفع مسبق -->
                                                        <button
                                                            type="button"
                                                            @click="form.type = 'prepaid'"
                                                            :class="[
                                                                'p-3.5 rounded-2xl border-2 text-right transition-all flex items-start gap-3 cursor-pointer',
                                                                form.type === 'prepaid'
                                                                    ? 'border-amber-600 bg-amber-50/80 shadow-xs ring-2 ring-amber-500/20'
                                                                    : 'border-gray-200 hover:border-gray-300 bg-white'
                                                            ]"
                                                        >
                                                            <div class="text-2xl p-1.5 rounded-xl bg-white shadow-xs shrink-0">⚡</div>
                                                            <div>
                                                                <p class="font-bold text-sm text-gray-900">مطعم دفع مسبق (كاشير ونوافذ تسليم)</p>
                                                                <p class="text-xs text-gray-500 mt-0.5 leading-relaxed">
                                                                    يدفع الزبون عند الكاشير أولاً، ثم يُحوّل الطلب لنوافذ المطبخ للاستلام والتتبع.
                                                                </p>
                                                            </div>
                                                        </button>
                                                    </div>
                                                </div>

                                                <!-- حقول خاصة بنظام الدفع المسبق: عدد النوافذ وعدد الطاولات -->
                                                <div v-if="form.type === 'prepaid'" class="col-span-full grid grid-cols-1 sm:grid-cols-2 gap-4 p-4 rounded-2xl bg-amber-50/60 border border-amber-200">
                                                    <div>
                                                        <label for="sa-restaurant-windows" class="block text-xs font-bold text-amber-900 mb-1">
                                                            عدد نوافذ التحضير والاستلام المتاحة <span class="text-red-500">*</span>
                                                        </label>
                                                        <input
                                                            id="sa-restaurant-windows"
                                                            v-model.number="form.windows_count"
                                                            type="number"
                                                            min="1"
                                                            max="50"
                                                            required
                                                            class="w-full rounded-xl border-amber-300 bg-white shadow-xs focus:border-amber-500 focus:ring-amber-500 text-sm font-bold"
                                                            placeholder="مثال: 3"
                                                        />
                                                        <p class="text-[11px] text-amber-700 mt-1">عدد نوافذ تحضير المطبخ واستلام الزبائن.</p>
                                                    </div>

                                                    <div>
                                                        <label for="sa-restaurant-tables" class="block text-xs font-bold text-amber-900 mb-1">
                                                            عدد الطاولات المراد إنشاؤها تلقائياً (اختياري)
                                                        </label>
                                                        <input
                                                            id="sa-restaurant-tables"
                                                            v-model.number="form.tables_count"
                                                            type="number"
                                                            min="0"
                                                            max="200"
                                                            class="w-full rounded-xl border-amber-300 bg-white shadow-xs focus:border-amber-500 focus:ring-amber-500 text-sm font-bold"
                                                            placeholder="مثال: 10 (أو 0 إن كان سفري فقط)"
                                                        />
                                                        <p class="text-[11px] text-amber-700 mt-1">سيتم إنشاء الطاولات مع رموز الـ QR تلقائياً.</p>
                                                    </div>
                                                </div>

                                                <!-- حقل عدد الطاولات لنظام الدفع اللاحق -->
                                                <div v-else class="col-span-full">
                                                    <label for="sa-restaurant-tables-postpaid" class="block text-sm font-medium text-gray-700 mb-1">
                                                        عدد الطاولات المراد إنشاؤها تلقائياً (اختياري)
                                                    </label>
                                                    <input
                                                        id="sa-restaurant-tables-postpaid"
                                                        v-model.number="form.tables_count"
                                                        type="number"
                                                        min="0"
                                                        max="200"
                                                        class="w-full rounded-xl border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 transition text-sm"
                                                        placeholder="مثال: 15 (سيتم إنشاء الطاولات مع رموز الـ QR تلقائياً)"
                                                    />
                                                </div>
                                            </div>
                                        </fieldset>

                                        <!-- Subscription Plan & Duration Section -->
                                        <fieldset>
                                            <legend class="text-sm font-bold text-emerald-700 border-b border-emerald-100 pb-2 mb-4 w-full flex items-center gap-2">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6H2.25m0 0H3m-1.5 0h.008v.008H1.5V6Zm19.5 0H21a.75.75 0 0 1-.75-.75V4.5m0 0h1.5m-1.5 0h.008v.008H21V4.5Zm-16.5 6.75h15m-15 4.5h15" />
                                                </svg>
                                                باقة الاشتراك وفترة الصلاحية
                                            </legend>

                                            <div class="space-y-4">
                                                <div>
                                                    <label class="block text-sm font-bold text-gray-800 mb-2">اختر باقة المطعم <span class="text-red-500">*</span></label>
                                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                                        <!-- Basic -->
                                                        <button
                                                            type="button"
                                                            @click="form.plan = 'basic'"
                                                            :class="[
                                                                'p-3 rounded-xl border-2 text-right transition-all cursor-pointer flex flex-col justify-between',
                                                                form.plan === 'basic'
                                                                    ? 'border-slate-800 bg-slate-50 ring-2 ring-slate-800/10'
                                                                    : 'border-gray-200 hover:border-gray-300 bg-white'
                                                            ]"
                                                        >
                                                            <div class="flex items-center justify-between mb-1">
                                                                <span class="font-bold text-xs text-slate-900">الأساسية (Basic)</span>
                                                                <span class="text-[10px] px-1.5 py-0.2 rounded font-bold bg-slate-200 text-slate-800">إدارة الطلب</span>
                                                            </div>
                                                            <p class="text-[11px] text-gray-500">QR منيو، كاشير، شاشة مطبخ KDS، تقارير مبيعات.</p>
                                                        </button>

                                                        <!-- Pro -->
                                                        <button
                                                            type="button"
                                                            @click="form.plan = 'pro'"
                                                            :class="[
                                                                'p-3 rounded-xl border-2 text-right transition-all cursor-pointer flex flex-col justify-between',
                                                                form.plan === 'pro'
                                                                    ? 'border-amber-500 bg-amber-50/70 ring-2 ring-amber-500/20'
                                                                    : 'border-gray-200 hover:border-gray-300 bg-white'
                                                            ]"
                                                        >
                                                            <div class="flex items-center justify-between mb-1">
                                                                <span class="font-bold text-xs text-amber-950">المتقدمة (Pro)</span>
                                                                <span class="text-[10px] px-1.5 py-0.2 rounded font-bold bg-amber-200 text-amber-900">الأكثر طلباً</span>
                                                            </div>
                                                            <p class="text-[11px] text-gray-500">كل مزايا Basic + الكوبونات والعروض + التحليلات المتقدمة.</p>
                                                        </button>

                                                        <!-- Max -->
                                                        <button
                                                            type="button"
                                                            @click="form.plan = 'max'"
                                                            :class="[
                                                                'p-3 rounded-xl border-2 text-right transition-all cursor-pointer flex flex-col justify-between',
                                                                form.plan === 'max'
                                                                    ? 'border-purple-600 bg-purple-50/70 ring-2 ring-purple-600/20'
                                                                    : 'border-gray-200 hover:border-gray-300 bg-white'
                                                            ]"
                                                        >
                                                            <div class="flex items-center justify-between mb-1">
                                                                <span class="font-bold text-xs text-purple-950">الشاملة (Max)</span>
                                                                <span class="text-[10px] px-1.5 py-0.2 rounded font-bold bg-purple-200 text-purple-900">ذكاء اصطناعي</span>
                                                            </div>
                                                            <p class="text-[11px] text-gray-500">كل مزايا Pro + الويتر الذكي (AI Waiter) + هوية مخصصة بالكامل.</p>
                                                        </button>
                                                    </div>
                                                </div>

                                                <div>
                                                    <label for="sa-subscription-duration" class="block text-sm font-medium text-gray-700 mb-1">مدة الاشتراك المبدئية</label>
                                                    <select
                                                        id="sa-subscription-duration"
                                                        v-model.number="form.subscription_days"
                                                        class="w-full rounded-xl border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 transition text-sm font-semibold"
                                                    >
                                                        <option :value="14">تجربة مجانية (14 يوماً)</option>
                                                        <option :value="30">شهر واحد (30 يوماً)</option>
                                                        <option :value="90">3 أشهر (ربع سنوي)</option>
                                                        <option :value="180">6 أشهر (نصف سنوي)</option>
                                                        <option :value="365">سنة كاملة (365 يوماً)</option>
                                                        <option :value="0">اشتراك مفتوح / غير محدد المدة</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </fieldset>

                                        <!-- Manager Section -->
                                        <fieldset>
                                            <legend class="text-sm font-bold text-blue-700 border-b border-blue-100 pb-2 mb-4 w-full flex items-center gap-2">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                                </svg>
                                                حساب مدير المطعم
                                            </legend>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div>
                                                    <label for="sa-manager-name" class="block text-sm font-medium text-gray-700 mb-1">اسم المدير <span class="text-red-500">*</span></label>
                                                    <input id="sa-manager-name" v-model="form.manager_name" required
                                                        class="w-full rounded-xl border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 text-sm transition"
                                                        :class="{ 'border-red-400 focus:border-red-500 focus:ring-red-500': fieldError('manager_name') }"
                                                        placeholder="الاسم الكامل" />
                                                    <p v-if="fieldError('manager_name')" class="text-xs text-red-500 mt-1">{{ fieldError('manager_name') }}</p>
                                                </div>
                                                <div>
                                                    <label for="sa-manager-email" class="block text-sm font-medium text-gray-700 mb-1">البريد الإلكتروني <span class="text-red-500">*</span></label>
                                                    <input id="sa-manager-email" type="email" v-model="form.manager_email" required
                                                        class="w-full rounded-xl border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 text-sm transition" dir="ltr"
                                                        :class="{ 'border-red-400 focus:border-red-500 focus:ring-red-500': fieldError('manager_email') }"
                                                        placeholder="admin@restaurant.com" />
                                                    <p v-if="fieldError('manager_email')" class="text-xs text-red-500 mt-1">{{ fieldError('manager_email') }}</p>
                                                </div>
                                                <div class="md:col-span-2">
                                                    <label for="sa-manager-password" class="block text-sm font-medium text-gray-700 mb-1">كلمة المرور</label>
                                                    <div class="relative md:w-1/2">
                                                        <input id="sa-manager-password" :type="showPassword ? 'text' : 'password'" v-model="form.manager_password"
                                                            class="w-full rounded-xl border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 pl-10 text-sm transition" dir="ltr"
                                                            :class="{ 'border-red-400': fieldError('manager_password') }"
                                                            placeholder="اتركها فارغة لتعيين &quot;password&quot;" />
                                                        <button type="button" @click="showPassword = !showPassword"
                                                            class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 hover:text-gray-600 transition">
                                                            <svg v-if="!showPassword" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                                                            <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                                                        </button>
                                                    </div>
                                                    <p v-if="fieldError('manager_password')" class="text-xs text-red-500 mt-1">{{ fieldError('manager_password') }}</p>
                                                    <p class="text-xs text-gray-400 mt-1">إذا تركتها فارغة سيتم تعيين "password" ككلمة مرور افتراضية.</p>
                                                </div>
                                            </div>
                                        </fieldset>

                                        <!-- Error Message -->
                                        <Transition
                                            enter-active-class="transition ease-out duration-200"
                                            enter-from-class="opacity-0 translate-y-1"
                                            enter-to-class="opacity-100 translate-y-0"
                                            leave-active-class="transition ease-in duration-150"
                                            leave-from-class="opacity-100"
                                            leave-to-class="opacity-0"
                                        >
                                            <div v-if="message && !isSuccess" class="rounded-xl bg-red-50 border border-red-200 p-3 flex items-center gap-2">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5 text-red-500 shrink-0">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                                                </svg>
                                                <p class="text-sm text-red-700">{{ message }}</p>
                                            </div>
                                        </Transition>

                                        <!-- Success Message -->
                                        <Transition
                                            enter-active-class="transition ease-out duration-300"
                                            enter-from-class="opacity-0 scale-95"
                                            enter-to-class="opacity-100 scale-100"
                                            leave-active-class="transition ease-in duration-200"
                                            leave-from-class="opacity-100 scale-100"
                                            leave-to-class="opacity-0 scale-95"
                                        >
                                            <div v-if="isSuccess" class="rounded-xl bg-green-50 border border-green-200 p-3 flex items-center gap-2">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5 text-green-600 shrink-0">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                </svg>
                                                <p class="text-sm text-green-700 font-medium">{{ message }}</p>
                                            </div>
                                        </Transition>

                                        <!-- Actions -->
                                        <div class="flex items-center gap-3 pt-1">
                                            <button type="submit" :disabled="loading"
                                                class="inline-flex items-center gap-2 bg-amber-600 text-white px-6 py-2.5 rounded-xl shadow-sm hover:bg-amber-700 focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 disabled:opacity-50 transition-all font-medium text-sm">
                                                <svg v-if="loading" class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                                </svg>
                                                <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                                </svg>
                                                {{ loading ? 'جاري الإضافة...' : 'إضافة المطعم والمدير' }}
                                            </button>
                                            <button type="button" @click="showCreateForm = false"
                                                class="text-sm text-gray-500 hover:text-gray-700 transition px-3 py-2.5">
                                                إلغاء
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </Transition>

                        <!-- Filter & Sort Toolbar (الفلترة والترتيب الذكي) -->
                        <div class="mb-5 bg-gradient-to-b from-gray-50/90 to-white p-4 sm:p-5 rounded-2xl border border-gray-200 shadow-2xs space-y-3.5">
                            <!-- الصف الأول: شريط البحث + معيار الترتيب + اتجاه الترتيب -->
                            <div class="flex flex-col md:flex-row gap-3 items-stretch md:items-center justify-between">
                                <!-- البحث الحي -->
                                <div class="relative flex-1">
                                    <div class="absolute inset-y-0 right-0 pr-3.5 flex items-center pointer-events-none text-gray-400">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                                        </svg>
                                    </div>
                                    <input
                                        v-model="searchQuery"
                                        type="text"
                                        placeholder="ابحث باسم المطعم، رقم الهاتف، أو المعرف..."
                                        class="w-full pr-10 pl-9 py-2 text-xs sm:text-sm rounded-xl border border-gray-300 bg-white placeholder:text-gray-400 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 transition shadow-2xs font-medium"
                                    />
                                    <button
                                        v-if="searchQuery"
                                        @click="searchQuery = ''"
                                        type="button"
                                        class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400 hover:text-gray-600 transition"
                                        title="تفريغ البحث"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                </div>

                                <!-- أدوات الترتيب المباشر -->
                                <div class="flex items-center gap-2 flex-wrap">
                                    <div class="flex items-center gap-1.5 bg-white border border-gray-300 rounded-xl px-3 py-1.5 shadow-2xs">
                                        <span class="text-xs font-bold text-gray-500 whitespace-nowrap">الترتيب حسب:</span>
                                        <select
                                            v-model="sortBy"
                                            class="border-0 bg-transparent py-0 pr-1 pl-6 text-xs sm:text-sm font-bold text-gray-800 focus:ring-0 cursor-pointer"
                                        >
                                            <option value="created_at">تاريخ الإضافة (الأحدث)</option>
                                            <option value="name">اسم المطعم (أبجدياً)</option>
                                            <option value="type">نوع النظام (مسبق / لاحق)</option>
                                            <option value="plan">الباقة (الأعلى باقة أولاً)</option>
                                            <option value="days_remaining">مدة الصلاحية (الأقرب انتهاءً)</option>
                                            <option value="status">الحالة (نشط / معطّل)</option>
                                            <option value="phone">رقم الهاتف</option>
                                        </select>
                                    </div>

                                    <button
                                        @click="sortOrder = sortOrder === 'asc' ? 'desc' : 'asc'"
                                        type="button"
                                        class="px-3 py-2 rounded-xl border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 transition flex items-center gap-1.5 shadow-2xs text-xs font-bold"
                                        :title="sortOrder === 'asc' ? 'الترتيب تصاعدي (انقر للتبديل لتنازلي)' : 'الترتيب تنازلي (انقر للتبديل لتصاعدي)'"
                                    >
                                        <span class="text-amber-600 font-extrabold">{{ sortOrder === 'asc' ? '▲ تصاعدي' : '▼ تنازلي' }}</span>
                                    </button>
                                </div>
                            </div>

                            <!-- الصف الثاني: بطاقات الفلترة السريعة مع العدادات -->
                            <div class="flex flex-wrap items-center gap-1.5 pt-1 border-t border-gray-200/70">
                                <span class="text-xs font-bold text-gray-500 ml-1">فلترة سريعة:</span>

                                <!-- الكل -->
                                <button
                                    type="button"
                                    @click="filterStatus = 'all'; filterType = 'all'; filterSubscription = 'all'"
                                    class="px-2.5 py-1 rounded-xl text-xs font-bold transition flex items-center gap-1.5 cursor-pointer"
                                    :class="filterStatus === 'all' && filterType === 'all' && filterSubscription === 'all'
                                        ? 'bg-amber-600 text-white shadow-2xs'
                                        : 'bg-gray-100 hover:bg-gray-200 text-gray-700'"
                                >
                                    <span>الكل</span>
                                    <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono"
                                        :class="filterStatus === 'all' && filterType === 'all' && filterSubscription === 'all' ? 'bg-amber-700 text-white' : 'bg-gray-200 text-gray-700'">
                                        {{ filterCounts.all }}
                                    </span>
                                </button>

                                <!-- نشط -->
                                <button
                                    type="button"
                                    @click="filterStatus = (filterStatus === 'active' ? 'all' : 'active'); if (filterStatus === 'active') { sortBy = 'status'; sortOrder = 'asc'; }"
                                    class="px-2.5 py-1 rounded-xl text-xs font-bold transition flex items-center gap-1.5 cursor-pointer"
                                    :class="filterStatus === 'active'
                                        ? 'bg-emerald-600 text-white shadow-2xs'
                                        : 'bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200/80'"
                                >
                                    <span>🟢 نشط</span>
                                    <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono"
                                        :class="filterStatus === 'active' ? 'bg-emerald-700 text-white' : 'bg-emerald-200/80 text-emerald-900'">
                                        {{ filterCounts.active }}
                                    </span>
                                </button>

                                <!-- معطّل -->
                                <button
                                    type="button"
                                    @click="filterStatus = (filterStatus === 'inactive' ? 'all' : 'inactive'); if (filterStatus === 'inactive') { sortBy = 'status'; sortOrder = 'desc'; }"
                                    class="px-2.5 py-1 rounded-xl text-xs font-bold transition flex items-center gap-1.5 cursor-pointer"
                                    :class="filterStatus === 'inactive'
                                        ? 'bg-rose-600 text-white shadow-2xs'
                                        : 'bg-rose-50 hover:bg-rose-100 text-rose-800 border border-rose-200/80'"
                                >
                                    <span>🔴 معطّل</span>
                                    <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono"
                                        :class="filterStatus === 'inactive' ? 'bg-rose-700 text-white' : 'bg-rose-200/80 text-rose-900'">
                                        {{ filterCounts.inactive }}
                                    </span>
                                </button>

                                <!-- دفع لاحق -->
                                <button
                                    type="button"
                                    @click="filterType = (filterType === 'postpaid' ? 'all' : 'postpaid'); if (filterType === 'postpaid') { sortBy = 'type'; sortOrder = 'desc'; }"
                                    class="px-2.5 py-1 rounded-xl text-xs font-bold transition flex items-center gap-1.5 cursor-pointer"
                                    :class="filterType === 'postpaid'
                                        ? 'bg-teal-600 text-white shadow-2xs'
                                        : 'bg-teal-50 hover:bg-teal-100 text-teal-800 border border-teal-200/80'"
                                >
                                    <span>🍽️ دفع لاحق</span>
                                    <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono"
                                        :class="filterType === 'postpaid' ? 'bg-teal-700 text-white' : 'bg-teal-200/80 text-teal-900'">
                                        {{ filterCounts.postpaid }}
                                    </span>
                                </button>

                                <!-- دفع مسبق -->
                                <button
                                    type="button"
                                    @click="filterType = (filterType === 'prepaid' ? 'all' : 'prepaid'); if (filterType === 'prepaid') { sortBy = 'type'; sortOrder = 'asc'; }"
                                    class="px-2.5 py-1 rounded-xl text-xs font-bold transition flex items-center gap-1.5 cursor-pointer"
                                    :class="filterType === 'prepaid'
                                        ? 'bg-amber-600 text-white shadow-2xs'
                                        : 'bg-amber-50 hover:bg-amber-100 text-amber-900 border border-amber-300'"
                                >
                                    <span>⚡ دفع مسبق</span>
                                    <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono"
                                        :class="filterType === 'prepaid' ? 'bg-amber-700 text-white' : 'bg-amber-200 text-amber-900'">
                                        {{ filterCounts.prepaid }}
                                    </span>
                                </button>

                                <!-- اشتراك يوشك على الانتهاء / منتهي -->
                                <button
                                    type="button"
                                    @click="filterSubscription = (filterSubscription === 'expiring_soon' ? 'all' : 'expiring_soon'); if (filterSubscription === 'expiring_soon') { sortBy = 'days_remaining'; sortOrder = 'asc'; }"
                                    class="px-2.5 py-1 rounded-xl text-xs font-bold transition flex items-center gap-1.5 cursor-pointer"
                                    :class="filterSubscription === 'expiring_soon'
                                        ? 'bg-red-600 text-white shadow-2xs'
                                        : 'bg-red-50 hover:bg-red-100 text-red-800 border border-red-200/80'"
                                    title="فلترة وترتيب الاشتراكات الأكثر إلحاحاً أولاً"
                                >
                                    <span>⚠️ يوشك على الانتهاء</span>
                                    <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono"
                                        :class="filterSubscription === 'expiring_soon' ? 'bg-red-700 text-white' : 'bg-red-200/80 text-red-900'">
                                        {{ filterCounts.expiredOrExpiring }}
                                    </span>
                                </button>
                            </div>

                            <!-- الصف الثالث: قوائم الفلترة المتقدمة + عداد النتائج وزر إعادة الضبط -->
                            <div class="flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-gray-100">
                                <div class="flex flex-wrap items-center gap-2 text-xs">
                                    <!-- باقة المطعم -->
                                    <div class="flex items-center gap-1">
                                        <span class="text-gray-500 font-semibold">الباقة:</span>
                                        <select
                                            v-model="filterPlan"
                                            @change="if (filterPlan !== 'all') { sortBy = 'plan'; sortOrder = 'desc'; }"
                                            class="rounded-lg border-gray-300 bg-white py-1 pr-2 pl-6 text-xs font-bold text-gray-700 focus:border-amber-500 focus:ring-amber-500 cursor-pointer"
                                        >
                                            <option value="all">كل الباقات ({{ filterCounts.all }})</option>
                                            <option value="basic">الأساسية Basic ({{ filterCounts.planBasic }})</option>
                                            <option value="pro">المتقدمة Pro ({{ filterCounts.planPro }})</option>
                                            <option value="max">الشاملة Max ({{ filterCounts.planMax }})</option>
                                        </select>
                                    </div>

                                    <!-- صلاحية الاشتراك -->
                                    <div class="flex items-center gap-1">
                                        <span class="text-gray-500 font-semibold">الصلاحية:</span>
                                        <select
                                            v-model="filterSubscription"
                                            @change="if (filterSubscription === 'expired' || filterSubscription === 'expiring_soon') { sortBy = 'days_remaining'; sortOrder = 'asc'; }"
                                            class="rounded-lg border-gray-300 bg-white py-1 pr-2 pl-6 text-xs font-bold text-gray-700 focus:border-amber-500 focus:ring-amber-500 cursor-pointer"
                                        >
                                            <option value="all">جميع الاشتراكات</option>
                                            <option value="valid">ساري الصلاحية</option>
                                            <option value="expiring_soon">يوشك على الانتهاء (≤ 7 أيام)</option>
                                            <option value="expired">منتهي الصلاحية</option>
                                            <option value="lifetime">مفتوح / دائم</option>
                                        </select>
                                    </div>

                                    <!-- الميزات المفعلة -->
                                    <div class="flex items-center gap-1">
                                        <span class="text-gray-500 font-semibold">الميزات:</span>
                                        <select
                                            v-model="filterFeature"
                                            class="rounded-lg border-gray-300 bg-white py-1 pr-2 pl-6 text-xs font-bold text-gray-700 focus:border-amber-500 focus:ring-amber-500 cursor-pointer"
                                        >
                                            <option value="all">كل الميزات</option>
                                            <option value="ai_waiter">🤖 الويتر الذكي (AI)</option>
                                            <option value="whatsapp_ai_reply">⚡🤖 الرد الذكي للواتساب (AI)</option>
                                            <option value="analytics">📈 التحليلات الذكية</option>
                                            <option value="offers">🎁 نظام العروض والكوبونات</option>
                                            <option value="whatsapp_reminders">💬 تذكيرات الواتساب</option>
                                            <option value="menu_engineering">📊 هندسة المنيو</option>
                                            <option value="floor_plan">🗺️ مخطط الصالة</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- ملخص الإحصائيات وزر إعادة الضبط -->
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-bold px-2.5 py-1 rounded-xl bg-gray-100 text-gray-700 border border-gray-200">
                                        عرض <span class="text-amber-600 font-extrabold">{{ filteredAndSortedRestaurants.length }}</span> من أصل {{ restaurants.length }} مطعم
                                    </span>

                                    <button
                                        v-if="hasActiveFilters"
                                        @click="resetFilters"
                                        type="button"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl text-xs font-bold bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 transition cursor-pointer"
                                        title="إلغاء كافة الفلاتر والبحث واسترجاع القائمة الكاملة"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd" />
                                        </svg>
                                        <span>إعادة ضبط الفلاتر</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Restaurants Table -->
                        <div class="overflow-x-auto">
                            <table class="w-full text-right border-collapse whitespace-nowrap">
                                <thead>
                                <tr class="bg-gray-100 text-gray-700 select-none text-xs">
                                    <th class="p-3 border cursor-pointer hover:bg-gray-200/80 transition" @click="toggleSort('name')" title="انقر لترتيب الاسم أبجدياً">
                                        <div class="inline-flex items-center gap-1.5">
                                            <span class="font-bold">الاسم</span>
                                            <span class="text-[11px]" :class="sortBy === 'name' ? 'text-amber-600 font-black' : 'text-gray-400'">
                                                {{ sortBy === 'name' ? (sortOrder === 'asc' ? '▲' : '▼') : '↕' }}
                                            </span>
                                        </div>
                                    </th>
                                    <th class="p-3 border text-center cursor-pointer hover:bg-gray-200/80 transition" @click="toggleSort('type')" title="انقر للترتيب بنوع النظام">
                                        <div class="inline-flex items-center justify-center gap-1.5">
                                            <span class="font-bold">نوع النظام</span>
                                            <span class="text-[11px]" :class="sortBy === 'type' ? 'text-amber-600 font-black' : 'text-gray-400'">
                                                {{ sortBy === 'type' ? (sortOrder === 'asc' ? '▲' : '▼') : '↕' }}
                                            </span>
                                        </div>
                                    </th>
                                    <th class="p-3 border text-center cursor-pointer hover:bg-gray-200/80 transition" @click="toggleSort('plan')" title="انقر للترتيب حسب الباقة">
                                        <div class="inline-flex items-center justify-center gap-1.5">
                                            <span class="font-bold">الباقة الحالية</span>
                                            <span class="text-[11px]" :class="sortBy === 'plan' ? 'text-amber-600 font-black' : 'text-gray-400'">
                                                {{ sortBy === 'plan' ? (sortOrder === 'asc' ? '▲' : '▼') : '↕' }}
                                            </span>
                                        </div>
                                    </th>
                                    <th class="p-3 border text-center cursor-pointer hover:bg-gray-200/80 transition" @click="toggleSort('days_remaining')" title="انقر للترتيب بمدة الصلاحية المتبقية">
                                        <div class="inline-flex items-center justify-center gap-1.5">
                                            <span class="font-bold">الاشتراك والصلاحية</span>
                                            <span class="text-[11px]" :class="sortBy === 'days_remaining' ? 'text-amber-600 font-black' : 'text-gray-400'">
                                                {{ sortBy === 'days_remaining' ? (sortOrder === 'asc' ? '▲' : '▼') : '↕' }}
                                            </span>
                                        </div>
                                    </th>
                                    <th class="p-3 border text-center cursor-pointer hover:bg-gray-200/80 transition" @click="toggleSort('status')" title="انقر للترتيب بحالة النشاط">
                                        <div class="inline-flex items-center justify-center gap-1.5">
                                            <span class="font-bold">الحالة</span>
                                            <span class="text-[11px]" :class="sortBy === 'status' ? 'text-amber-600 font-black' : 'text-gray-400'">
                                                {{ sortBy === 'status' ? (sortOrder === 'asc' ? '▲' : '▼') : '↕' }}
                                            </span>
                                        </div>
                                    </th>
                                    <th class="p-3 border font-bold">الميزات الإضافية</th>
                                    <th class="p-3 border cursor-pointer hover:bg-gray-200/80 transition" @click="toggleSort('phone')" title="انقر للترتيب برقم الهاتف">
                                        <div class="inline-flex items-center gap-1.5">
                                            <span class="font-bold">الهاتف</span>
                                            <span class="text-[11px]" :class="sortBy === 'phone' ? 'text-amber-600 font-black' : 'text-gray-400'">
                                                {{ sortBy === 'phone' ? (sortOrder === 'asc' ? '▲' : '▼') : '↕' }}
                                            </span>
                                        </div>
                                    </th>
                                    <th class="p-3 border text-center font-bold">إجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- في حال عدم وجود نتائج بعد الفلترة والبحث -->
                                <tr v-if="filteredAndSortedRestaurants.length === 0">
                                    <td colspan="8" class="p-10 text-center bg-gray-50/60">
                                        <div class="flex flex-col items-center justify-center gap-2.5">
                                            <span class="text-3xl">🔍</span>
                                            <p class="font-bold text-gray-800 text-sm">لا توجد مطاعم مطابقة لمعايير البحث والفلترة المحددة</p>
                                            <p class="text-xs text-gray-500">جرب تعديل كلمات البحث أو إلغاء بعض الفلاتر لعرض المطاعم</p>
                                            <button
                                                @click="resetFilters"
                                                type="button"
                                                class="mt-2 px-3.5 py-1.5 rounded-xl text-xs font-bold bg-amber-600 hover:bg-amber-700 text-white transition shadow-2xs cursor-pointer"
                                            >
                                                إعادة ضبط الفلاتر وعرض الكل
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-for="restaurant in filteredAndSortedRestaurants" :key="restaurant.id">
                                    <td class="p-3 border font-semibold text-gray-900">{{ restaurant.name }}</td>
                                    <td class="p-3 border text-center">
                                        <div class="inline-flex items-center gap-2">
                                            <span
                                                v-if="restaurant.type === 'prepaid'"
                                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-900 border border-amber-300"
                                                title="دفع مسبق: كاشير ونوافذ تحضير وتسليم"
                                            >
                                                <span>⚡ دفع مسبق</span>
                                                <span v-if="restaurant.windows_count" class="bg-amber-200 text-amber-900 px-1.5 py-0.5 rounded-md font-mono text-[11px]">
                                                    {{ restaurant.windows_count }} نوافذ
                                                </span>
                                            </span>
                                            <span
                                                v-else
                                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-900 border border-emerald-300"
                                                title="دفع لاحق: ضيافة وطاولات صالة"
                                            >
                                                <span>🍽️ دفع لاحق</span>
                                            </span>
                                            <button
                                                @click="toggleRestaurantType(restaurant)"
                                                type="button"
                                                class="p-1 rounded-lg text-gray-400 hover:text-amber-700 hover:bg-amber-50 transition"
                                                title="تبديل نوع النظام (مسبق / لاحق)"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                    <td class="p-3 border text-center">
                                        <select
                                            :value="restaurant.plan || 'basic'"
                                            @change="changePlan(restaurant, ($event.target as HTMLSelectElement).value)"
                                            class="text-xs font-bold rounded-lg px-2.5 py-1 border shadow-2xs transition cursor-pointer"
                                            :class="{
                                                'bg-slate-100 text-slate-800 border-slate-300': (restaurant.plan || 'basic') === 'basic',
                                                'bg-amber-100 text-amber-900 border-amber-400': restaurant.plan === 'pro',
                                                'bg-purple-100 text-purple-900 border-purple-400': restaurant.plan === 'max',
                                            }"
                                        >
                                            <option value="basic">الأساسية (Basic)</option>
                                            <option value="pro">المتقدمة (Pro)</option>
                                            <option value="max">الشاملة (Max)</option>
                                        </select>
                                    </td>
                                    <td class="p-3 border text-center">
                                        <div class="flex flex-col items-center gap-1">
                                            <span
                                                v-if="restaurant.is_expired"
                                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-800 border border-red-300"
                                                title="انتهت فترة الاشتراك"
                                            >
                                                <span>⚠️ منتهي</span>
                                            </span>
                                            <span
                                                v-else-if="restaurant.days_remaining !== null"
                                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold"
                                                :class="restaurant.days_remaining <= 7 ? 'bg-amber-100 text-amber-900 border border-amber-300' : 'bg-emerald-100 text-emerald-900 border border-emerald-300'"
                                                :title="`ينتهي في: ${restaurant.subscription_expires_at || ''}`"
                                            >
                                                <span>باقي {{ restaurant.days_remaining }} يوم</span>
                                            </span>
                                            <span
                                                v-else
                                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold bg-blue-100 text-blue-900 border border-blue-300"
                                            >
                                                <span>♾️ مفتوح / دائم</span>
                                            </span>

                                            <div class="flex items-center gap-1 mt-1 text-[11px]">
                                                <button
                                                    @click="extendSubscription(restaurant, 30)"
                                                    class="px-1.5 py-0.5 rounded bg-gray-100 hover:bg-emerald-100 hover:text-emerald-800 text-gray-600 font-semibold border transition text-[10px]"
                                                    title="تمديد 30 يوماً إضافية"
                                                >
                                                    +30 يوم
                                                </button>
                                                <button
                                                    @click="extendSubscription(restaurant, 365)"
                                                    class="px-1.5 py-0.5 rounded bg-gray-100 hover:bg-purple-100 hover:text-purple-800 text-gray-600 font-semibold border transition text-[10px]"
                                                    title="تمديد سنة كاملة"
                                                >
                                                    +سنة
                                                </button>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-3 border text-center">
                                        <span class="px-2 py-1 rounded text-xs font-medium" :class="restaurant.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
                                            {{ restaurant.status === 'active' ? 'نشط' : 'معطّل' }}
                                        </span>
                                    </td>
                                    <td class="p-3 border">
                                        <div class="flex flex-col gap-2">
                                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                                <input type="checkbox"
                                                    :checked="restaurant.features?.analytics"
                                                    @change="toggleFeature(restaurant, 'analytics')"
                                                    class="rounded border-gray-300 text-amber-600 shadow-sm focus:border-amber-300 focus:ring focus:ring-amber-200 focus:ring-opacity-50"
                                                >
                                                <span class="text-sm font-medium text-gray-700">التحليلات الذكية</span>
                                            </label>
                                            
                                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                                <input type="checkbox"
                                                    :checked="restaurant.features?.offers"
                                                    @change="toggleFeature(restaurant, 'offers')"
                                                    class="rounded border-gray-300 text-amber-600 shadow-sm focus:border-amber-300 focus:ring focus:ring-amber-200 focus:ring-opacity-50"
                                                >
                                                <span class="text-sm font-medium text-gray-700">نظام العروض</span>
                                            </label>

                                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                                <input type="checkbox"
                                                    :checked="restaurant.features?.ai_waiter"
                                                    @change="toggleFeature(restaurant, 'ai_waiter')"
                                                    class="rounded border-gray-300 text-purple-600 shadow-sm focus:border-purple-300 focus:ring focus:ring-purple-200 focus:ring-opacity-50"
                                                >
                                                <span class="text-sm font-bold text-purple-700 dark:text-purple-300 flex items-center gap-1">
                                                    <span>🤖</span>
                                                    <span>الويتر الذكي (AI)</span>
                                                </span>
                                            </label>

                                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                                <input type="checkbox"
                                                    :checked="!!((restaurant.effective_features || restaurant.features)?.whatsapp_reminders)"
                                                    @change="toggleFeature(restaurant, 'whatsapp_reminders')"
                                                    class="rounded border-gray-300 text-emerald-600 shadow-sm focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                                                >
                                                <span class="text-sm font-medium text-emerald-700 flex items-center gap-1">
                                                    <span>💬</span>
                                                    <span>تذكيرات الواتساب</span>
                                                </span>
                                            </label>

                                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                                <input type="checkbox"
                                                    :checked="!!((restaurant.effective_features || restaurant.features)?.whatsapp_ai_reply)"
                                                    @change="toggleFeature(restaurant, 'whatsapp_ai_reply')"
                                                    class="rounded border-gray-300 text-purple-600 shadow-sm focus:border-purple-300 focus:ring focus:ring-purple-200 focus:ring-opacity-50"
                                                >
                                                <span class="text-sm font-bold text-purple-700 dark:text-purple-300 flex items-center gap-1">
                                                    <span>⚡🤖</span>
                                                    <span>الرد الذكي للواتساب (AI)</span>
                                                </span>
                                            </label>

                                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                                <input type="checkbox"
                                                    :checked="!!((restaurant.effective_features || restaurant.features)?.menu_engineering)"
                                                    @change="toggleFeature(restaurant, 'menu_engineering')"
                                                    class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                                                >
                                                <span class="text-sm font-medium text-blue-700 flex items-center gap-1">
                                                    <span>📊</span>
                                                    <span>هندسة المنيو والربحية</span>
                                                </span>
                                            </label>

                                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                                <input type="checkbox"
                                                    :checked="!!((restaurant.effective_features || restaurant.features)?.floor_plan)"
                                                    @change="toggleFeature(restaurant, 'floor_plan')"
                                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                                >
                                                <span class="text-sm font-medium text-indigo-700 dark:text-indigo-300 flex items-center gap-1">
                                                    <span>🗺️</span>
                                                    <span>مخطط الصالة الذكي</span>
                                                </span>
                                            </label>
                                        </div>
                                    </td>
                                    <td class="p-3 border" dir="ltr">{{ restaurant.phone }}</td>
                                    <td class="p-3 border text-center">
                                        <button
                                            @click="toggleStatus(restaurant)"
                                            class="px-3 py-1 rounded text-sm transition"
                                            :class="restaurant.status === 'active' ? 'bg-red-50 text-red-600 hover:bg-red-100' : 'bg-green-50 text-green-600 hover:bg-green-100'"
                                        >
                                            {{ restaurant.status === 'active' ? 'إيقاف' : 'تفعيل' }}
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
