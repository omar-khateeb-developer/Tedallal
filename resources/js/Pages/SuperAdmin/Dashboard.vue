<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted, nextTick, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import axios from 'axios';

const page = usePage();

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

// وضع الصيانة
const maintenanceMode = ref(false);
const maintenanceLoading = ref(false);
const maintenanceMessage = ref('');
const maintenanceSuccess = ref(false);
const maintenanceStatusLoaded = ref(false);

// صلاحية إيقاف/تشغيل الصيانة (متاحة حصرياً للمالك super@tedallal.test ومحجوبة عن كل المستخدمين الآخرين)
const canToggleMaintenance = computed(() => {
    const user = (page.props.auth as any)?.user;
    if (!user) return false;
    return user.email === 'super@tedallal.test';
});

const maintenanceBtnClass = computed(() => {
    if (maintenanceMode.value) {
        return 'bg-green-600 text-white hover:bg-green-700 focus:ring-green-500';
    }
    return 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500';
});

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
        if (restaurants.value.length > 0 && !diagRestaurantId.value) {
            diagRestaurantId.value = restaurants.value[0].id;
        }
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

const fetchMaintenanceStatus = async () => {
    try {
        const response = await axios.get('/api/super-admin/maintenance');
        maintenanceMode.value = response.data.data?.maintenance_mode ?? false;
        maintenanceStatusLoaded.value = true;
    } catch (e) {
        console.error('Failed to fetch maintenance status');
    }
};

const toggleMaintenance = async () => {
    const confirmMsg = maintenanceMode.value
        ? 'هل تريد إيقاف وضع الصيانة وتشغيل الموقع؟\nDo you want to disable maintenance mode?'
        : 'هل تريد تفعيل وضع الصيانة وإيقاف الموقع للعموم؟\nDo you want to enable maintenance mode and take the site offline?';

    if (!confirm(confirmMsg)) return;

    maintenanceLoading.value = true;
    maintenanceMessage.value = '';
    try {
        const response = await axios.post('/api/super-admin/maintenance/toggle', {
            enabled: !maintenanceMode.value,
        });
        maintenanceMode.value = response.data.data?.maintenance_mode ?? false;
        maintenanceMessage.value = response.data.data?.message ?? '';
        maintenanceSuccess.value = true;
        setTimeout(() => {
            maintenanceMessage.value = '';
            maintenanceSuccess.value = false;
        }, 3000);
    } catch (e) {
        maintenanceMessage.value = 'حدث خطأ أثناء تغيير حالة الصيانة';
        maintenanceSuccess.value = false;
    } finally {
        maintenanceLoading.value = false;
    }
};

// وضع التجريب وفحص الكوبونات
const promoTestingMode = ref(false);
const promoTestingLoading = ref(false);
const promoTestingLoaded = ref(false);
const promoTestingMessage = ref('');
const promoTestingSuccess = ref(false);

// أداة تشخيص الكوبونات
const diagCode = ref('');
const diagRestaurantId = ref<number | null>(null);
const diagCartTotal = ref<number>(100);
const diagLoading = ref(false);
const diagResult = ref<any>(null);
const diagActionLoading = ref(false);

const fetchPromoTestingStatus = async () => {
    try {
        const response = await axios.get('/api/super-admin/promo-testing');
        promoTestingMode.value = response.data.data?.testing_mode ?? false;
        promoTestingLoaded.value = true;
    } catch (e) {
        console.error('Failed to fetch promo testing status', e);
    }
};

const togglePromoTesting = async () => {
    promoTestingLoading.value = true;
    promoTestingMessage.value = '';
    try {
        const response = await axios.post('/api/super-admin/promo-testing/toggle', {
            enabled: !promoTestingMode.value,
        });
        promoTestingMode.value = response.data.data?.testing_mode ?? false;
        promoTestingMessage.value = response.data.data?.message ?? '';
        promoTestingSuccess.value = true;
        setTimeout(() => {
            promoTestingMessage.value = '';
        }, 5000);
    } catch (e: any) {
        promoTestingMessage.value = e.response?.data?.message || 'حدث خطأ أثناء تغيير حالة التجريب';
        promoTestingSuccess.value = false;
    } finally {
        promoTestingLoading.value = false;
    }
};

const runDiagnosis = async () => {
    if (!diagCode.value.trim() || !diagRestaurantId.value) return;
    diagLoading.value = true;
    diagResult.value = null;
    try {
        const response = await axios.post('/api/super-admin/promo-testing/diagnose', {
            code: diagCode.value.trim(),
            restaurant_id: diagRestaurantId.value,
            cart_total: diagCartTotal.value,
        });
        diagResult.value = response.data.data;
    } catch (e: any) {
        alert(e.response?.data?.message || 'فشل تشخيص الكوبون');
    } finally {
        diagLoading.value = false;
    }
};

const forceActivateFromDashboard = async () => {
    if (!diagCode.value.trim() || !diagRestaurantId.value) return;
    diagActionLoading.value = true;
    try {
        const response = await axios.post('/api/super-admin/promo-testing/force-activate', {
            code: diagCode.value.trim(),
            restaurant_id: diagRestaurantId.value,
        });
        alert(response.data.data?.message || 'تم التفعيل الفوري');
        await runDiagnosis();
    } catch (e: any) {
        alert(e.response?.data?.message || 'فشل التفعيل الفوري');
    } finally {
        diagActionLoading.value = false;
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

// ==========================================
// 📲 واتساب إدارة المنصة والمساعد التنفيذي الذكي
// ==========================================
const platformWaStatus = ref<'disconnected' | 'connecting' | 'scan_qr' | 'connected'>('disconnected');
const platformWaQr = ref<string | null>(null);
const platformWaPhone = ref<string | null>(null);
const platformWaConnectedAt = ref<string | null>(null);
const platformWaAiEnabled = ref(true);
const platformAiCustomInstructions = ref('');
const savingAiInstructions = ref(false);
const aiInstructionsSavedSuccess = ref(false);
const showAiTrainingSection = ref(true);
const showPlatformAiPanel = ref(false);
const activeAiTab = ref<'ai' | 'whatsapp'>('ai');

const togglePlatformAiPanel = () => {
    showPlatformAiPanel.value = !showPlatformAiPanel.value;
    try {
        localStorage.setItem('superadmin_show_ai_panel', String(showPlatformAiPanel.value));
    } catch (e) {}
};

const selectAiTab = (tab: 'ai' | 'whatsapp') => {
    activeAiTab.value = tab;
    showPlatformAiPanel.value = true;
    try {
        localStorage.setItem('superadmin_show_ai_panel', 'true');
    } catch (e) {}
};
const platformWaLoading = ref(false);
const platformTestPhone = ref('');
const platformTestLoading = ref(false);
let platformWaPollTimer: any = null;

const fetchPlatformWaStatus = async () => {
    try {
        const { data } = await axios.get('/api/super-admin/platform-whatsapp/status');
        if (data.success) {
            platformWaStatus.value = data.data.status;
            platformWaQr.value = data.data.qr_code;
            platformWaPhone.value = data.data.phone;
            platformWaConnectedAt.value = data.data.connected_at;
            platformWaAiEnabled.value = data.data.ai_enabled;
            if (data.data.custom_instructions !== undefined && !savingAiInstructions.value) {
                platformAiCustomInstructions.value = data.data.custom_instructions;
            }

            if (platformWaStatus.value === 'scan_qr' || platformWaStatus.value === 'connecting') {
                if (!platformWaPollTimer) {
                    platformWaPollTimer = setInterval(fetchPlatformWaStatus, 3000);
                }
            } else if (platformWaPollTimer) {
                clearInterval(platformWaPollTimer);
                platformWaPollTimer = null;
            }
        }
    } catch (e) {
        console.error('Failed to fetch platform WhatsApp status', e);
    }
};

const connectPlatformWa = async () => {
    platformWaLoading.value = true;
    try {
        const { data } = await axios.post('/api/super-admin/platform-whatsapp/connect');
        if (data.success) {
            platformWaStatus.value = data.data.status;
            platformWaQr.value = data.data.qr_code;
            fetchPlatformWaStatus();
        }
    } catch (e: any) {
        alert(e.response?.data?.message || 'تعذر بدء جلسة واتساب إدارة المنصة');
    } finally {
        platformWaLoading.value = false;
    }
};

const disconnectPlatformWa = async () => {
    if (!confirm('هل أنت متأكد من فصل رقم واتساب إدارة المنصة؟ لن يعمل المساعد الذكي حتى تعيد ربط الرقم.')) return;
    platformWaLoading.value = true;
    try {
        await axios.post('/api/super-admin/platform-whatsapp/disconnect');
        platformWaStatus.value = 'disconnected';
        platformWaQr.value = null;
        platformWaPhone.value = null;
        if (platformWaPollTimer) {
            clearInterval(platformWaPollTimer);
            platformWaPollTimer = null;
        }
    } catch (e: any) {
        alert(e.response?.data?.message || 'تعذر فصل الجلسة');
    } finally {
        platformWaLoading.value = false;
    }
};

const togglePlatformAi = async () => {
    const nextVal = !platformWaAiEnabled.value;
    try {
        await axios.post('/api/super-admin/platform-whatsapp/settings', {
            ai_enabled: nextVal,
        });
        platformWaAiEnabled.value = nextVal;
    } catch (e) {
        alert('تعذر تحديث إعدادات المساعد الذكي');
    }
};

const saveAiInstructions = async () => {
    savingAiInstructions.value = true;
    try {
        const { data } = await axios.post('/api/super-admin/platform-whatsapp/settings', {
            custom_instructions: platformAiCustomInstructions.value,
        });
        if (data.success) {
            aiInstructionsSavedSuccess.value = true;
            setTimeout(() => {
                aiInstructionsSavedSuccess.value = false;
            }, 3500);
        }
    } catch (e: any) {
        alert(e.response?.data?.message || 'تعذر حفظ توجيهات المساعد الذكي');
    } finally {
        savingAiInstructions.value = false;
    }
};

const insertInstructionTemplate = (snippet: string) => {
    if (!platformAiCustomInstructions.value.trim()) {
        platformAiCustomInstructions.value = snippet;
    } else {
        platformAiCustomInstructions.value += '\n' + snippet;
    }
};

const sendPlatformTestMsg = async () => {
    if (!platformTestPhone.value.trim()) return;
    platformTestLoading.value = true;
    try {
        const { data } = await axios.post('/api/super-admin/platform-whatsapp/send-test', {
            phone: platformTestPhone.value.trim(),
        });
        alert(data.data?.message || 'تم إرسال الرسالة بنجاح!');
        platformTestPhone.value = '';
    } catch (e: any) {
        alert(e.response?.data?.message || 'تعذر إرسال الرسالة التجريبية');
    } finally {
        platformTestLoading.value = false;
    }
};

onMounted(() => {
    try {
        const saved = localStorage.getItem('superadmin_show_ai_panel');
        if (saved !== null) {
            showPlatformAiPanel.value = saved === 'true';
        }
    } catch (e) {}
    fetchStats();
    fetchRestaurants();
    fetchPromoTestingStatus();
    fetchLeadsStats();
    fetchPlatformWaStatus();
    if (canToggleMaintenance.value) {
        fetchMaintenanceStatus();
    }
});

onUnmounted(() => {
    if (platformWaPollTimer) {
        clearInterval(platformWaPollTimer);
        platformWaPollTimer = null;
    }
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

                <!-- ===== لوحة التحكم في وضع الصيانة ===== -->
                <div v-if="canToggleMaintenance" class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6" dir="rtl">
                        <div class="flex items-center justify-between flex-wrap gap-4">
                            <div class="flex items-center gap-4">
                                <div
                                    class="p-3 rounded-xl"
                                    :class="maintenanceMode ? 'bg-red-100 text-red-600' : 'bg-green-100 text-green-600'"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                        <path v-if="!maintenanceMode" stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                        <path v-else stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437 1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008Z" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="font-bold text-gray-800 text-base">وضع الصيانة | Maintenance Mode</h3>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span
                                            class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold"
                                            :class="maintenanceMode
                                                ? 'bg-red-100 text-red-700'
                                                : 'bg-green-100 text-green-700'"
                                        >
                                            <span
                                                class="inline-block w-1.5 h-1.5 rounded-full"
                                                :class="maintenanceMode ? 'bg-red-500 animate-pulse' : 'bg-green-500'"
                                            ></span>
                                            <template v-if="maintenanceStatusLoaded">
                                                {{ maintenanceMode ? 'الموقع تحت الصيانة · Under Maintenance' : 'الموقع يعمل بشكل طبيعي · Site is Online' }}
                                            </template>
                                            <template v-else>جاري التحميل...</template>
                                        </span>
                                    </div>
                                    <!-- رسالة النتيجة -->
                                    <Transition
                                        enter-active-class="transition ease-out duration-200"
                                        enter-from-class="opacity-0 translate-y-1"
                                        enter-to-class="opacity-100 translate-y-0"
                                        leave-active-class="transition ease-in duration-150"
                                        leave-from-class="opacity-100"
                                        leave-to-class="opacity-0"
                                    >
                                        <p
                                            v-if="maintenanceMessage"
                                            class="text-xs mt-1 font-medium"
                                            :class="maintenanceSuccess ? 'text-green-600' : 'text-red-600'"
                                        >{{ maintenanceMessage }}</p>
                                    </Transition>
                                </div>
                            </div>
                            <button
                                id="maintenance-toggle-btn"
                                @click="toggleMaintenance"
                                :disabled="maintenanceLoading || !maintenanceStatusLoaded"
                                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-medium text-sm transition-all duration-200 shadow-sm focus:ring-2 focus:ring-offset-2 disabled:opacity-50"
                                :class="maintenanceBtnClass"
                            >
                                <svg v-if="maintenanceLoading" class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                <svg v-else-if="!maintenanceMode" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5.636 5.636a9 9 0 1 0 12.728 0M12 3v9" />
                                </svg>
                                <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5.636 5.636a9 9 0 1 0 12.728 0M12 3v9" />
                                </svg>
                                <span v-if="maintenanceLoading">جاري التغيير...</span>
                                <span v-else-if="maintenanceMode">تشغيل الموقع · Bring Online</span>
                                <span v-else>إيقاف الموقع للصيانة · Enable Maintenance</span>
                            </button>
                        </div>
                    </div>
                </div>
                <!-- ===== نهاية لوحة الصيانة ===== -->

                <!-- ===== لوحة التحكم في وضع التجريب وفحص الكوبونات | Promo Testing & Diagnostics Mode ===== -->
                <div class="bg-white dark:bg-[#0a0f1d] overflow-hidden shadow-sm sm:rounded-2xl border border-stone-200 dark:border-slate-800">
                    <div class="p-6" dir="rtl">
                        <div class="flex items-center justify-between flex-wrap gap-4 border-b border-stone-100 dark:border-slate-800 pb-5">
                            <div class="flex items-center gap-4">
                                <div
                                    class="p-3.5 rounded-2xl"
                                    :class="promoTestingMode ? 'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300' : 'bg-stone-100 text-stone-600 dark:bg-slate-800 dark:text-slate-400'"
                                >
                                    <span class="text-2xl">🧪</span>
                                </div>
                                <div>
                                    <h3 class="font-bold text-gray-900 dark:text-white text-base flex items-center gap-2">
                                        <span>وضع التجريب وفحص الكوبونات</span>
                                        <span class="text-xs font-normal text-stone-500">| Testing & Diagnostics Mode</span>
                                    </h3>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span
                                            class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold"
                                            :class="promoTestingMode
                                                ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300'
                                                : 'bg-stone-100 text-stone-600 dark:bg-slate-800 dark:text-slate-400'"
                                        >
                                            <span
                                                class="inline-block w-2 h-2 rounded-full"
                                                :class="promoTestingMode ? 'bg-amber-500 animate-pulse' : 'bg-stone-400'"
                                            ></span>
                                            <template v-if="promoTestingLoaded">
                                                {{ promoTestingMode ? 'وضع التجريب مفعل (يتم إظهار أسباب الرفض بالتفصيل للزبائن مع إمكانية التفعيل الفوري)' : 'وضع التجريب معطل (تعمل الرسائل بنظام الإنتاج العادي)' }}
                                            </template>
                                            <template v-else>جاري التحقق...</template>
                                        </span>
                                    </div>
                                    <p
                                        v-if="promoTestingMessage"
                                        class="text-xs mt-1.5 font-bold"
                                        :class="promoTestingSuccess ? 'text-emerald-600' : 'text-rose-600'"
                                    >{{ promoTestingMessage }}</p>
                                </div>
                            </div>
                            <button
                                @click="togglePromoTesting"
                                :disabled="promoTestingLoading || !promoTestingLoaded"
                                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-bold text-sm transition-all duration-200 shadow-sm focus:ring-2 focus:ring-offset-2 disabled:opacity-50"
                                :class="promoTestingMode ? 'bg-rose-600 hover:bg-rose-700 text-white' : 'bg-amber-600 hover:bg-amber-700 text-white'"
                            >
                                <span v-if="promoTestingLoading">جاري التحديث...</span>
                                <span v-else-if="promoTestingMode">إيقاف وضع التجريب ⏹️</span>
                                <span v-else>تشغيل وضع التجريب 🧪</span>
                            </button>
                        </div>

                        <!-- أداة فحص وتجريب الكوبونات المباشرة لمدير المنصة -->
                        <div class="mt-5 pt-2">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="text-sm font-bold text-gray-800 dark:text-slate-200 flex items-center gap-2">
                                    <span>🔍 أداة فحص وتشخيص أي كوبون مباشر</span>
                                    <span class="text-xs text-stone-500 font-normal">(أدخل أي كود وافحص حالته بالتفصيل)</span>
                                </h4>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
                                <div class="sm:col-span-4">
                                    <label class="block text-xs font-semibold text-stone-600 dark:text-slate-400 mb-1">المطعم:</label>
                                    <select
                                        v-model="diagRestaurantId"
                                        class="w-full text-xs font-bold rounded-xl border-stone-200 dark:border-slate-700 bg-stone-50 dark:bg-slate-900 text-stone-900 dark:text-white focus:border-amber-500 focus:ring-amber-500"
                                    >
                                        <option v-for="r in restaurants" :key="r.id" :value="r.id">
                                            {{ r.name }} ({{ r.currency || 'SYP' }})
                                        </option>
                                    </select>
                                </div>
                                <div class="sm:col-span-4">
                                    <label class="block text-xs font-semibold text-stone-600 dark:text-slate-400 mb-1">كود الكوبون:</label>
                                    <input
                                        v-model="diagCode"
                                        type="text"
                                        placeholder="مثال: LUCK-9KR7ZU أو WELCOME20"
                                        class="w-full text-xs font-bold uppercase rounded-xl border-stone-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-stone-900 dark:text-white placeholder:text-stone-400 focus:border-amber-500 focus:ring-amber-500"
                                        dir="ltr"
                                    />
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-semibold text-stone-600 dark:text-slate-400 mb-1">قيمة سلة افتراضية:</label>
                                    <input
                                        v-model.number="diagCartTotal"
                                        type="number"
                                        min="0"
                                        class="w-full text-xs font-bold rounded-xl border-stone-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-stone-900 dark:text-white focus:border-amber-500 focus:ring-amber-500"
                                    />
                                </div>
                                <div class="sm:col-span-2">
                                    <button
                                        @click="runDiagnosis"
                                        :disabled="diagLoading || !diagCode.trim() || !diagRestaurantId"
                                        class="w-full py-2.5 rounded-xl bg-stone-900 dark:bg-slate-800 text-white font-bold text-xs hover:bg-stone-800 dark:hover:bg-slate-700 transition disabled:opacity-50"
                                    >
                                        {{ diagLoading ? 'جاري الفحص...' : 'فحص الكوبون 🔍' }}
                                    </button>
                                </div>
                            </div>

                            <!-- نتيجة التشخيص -->
                            <div v-if="diagResult" class="mt-4 p-4 rounded-xl border text-xs" :class="diagResult.diagnostics.valid ? 'bg-emerald-50 dark:bg-emerald-950/30 border-emerald-200 dark:border-emerald-800' : 'bg-amber-50 dark:bg-amber-950/30 border-amber-200 dark:border-amber-800'">
                                <div class="flex items-start justify-between flex-wrap gap-2 mb-2">
                                    <div class="flex items-center gap-2">
                                        <span class="text-base">{{ diagResult.diagnostics.valid ? '✅' : '⚠️' }}</span>
                                        <span class="font-bold text-sm" :class="diagResult.diagnostics.valid ? 'text-emerald-800 dark:text-emerald-300' : 'text-amber-800 dark:text-amber-300'">
                                            {{ diagResult.diagnostics.valid ? 'الكوبون صالح ومقبول' : 'الكوبون مرفوض / غير قابل للتطبيق حالياً' }}
                                        </span>
                                    </div>
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold" :class="diagResult.diagnostics.valid ? 'bg-emerald-200 text-emerald-900' : 'bg-rose-200 text-rose-900'">
                                        رمز الحالة: {{ diagResult.diagnostics.reason_code }}
                                    </span>
                                </div>

                                <p class="text-stone-800 dark:text-slate-200 font-bold mb-3 leading-relaxed">
                                    📢 السبب: {{ diagResult.diagnostics.reason_message }}
                                </p>

                                <div v-if="diagResult.promo" class="grid grid-cols-2 sm:grid-cols-4 gap-2 p-2.5 rounded-lg bg-white/70 dark:bg-slate-900/60 border border-stone-200/60 dark:border-slate-800/80 mb-3 text-[11px]">
                                    <div><span class="text-stone-500">النوع والقيمة:</span> <strong class="text-stone-900 dark:text-white">{{ diagResult.promo.discount_type === 'percentage' ? '%' : diagResult.restaurant.currency }} {{ diagResult.promo.discount_value }}</strong></div>
                                    <div><span class="text-stone-500">الحد الأدنى للطلب:</span> <strong class="text-stone-900 dark:text-white">{{ diagResult.promo.min_order_value }} {{ diagResult.restaurant.currency }}</strong></div>
                                    <div><span class="text-stone-500">بداية الصلاحية:</span> <strong class="text-stone-900 dark:text-white">{{ diagResult.promo.starts_at || 'فوري' }}</strong></div>
                                    <div><span class="text-stone-500">نهاية الصلاحية:</span> <strong class="text-stone-900 dark:text-white">{{ diagResult.promo.expires_at || 'غير محدد' }}</strong></div>
                                </div>

                                <div class="flex items-center justify-between flex-wrap gap-2 pt-2 border-t border-stone-200/60 dark:border-slate-800/80">
                                    <div class="flex items-center gap-2">
                                        <button
                                            v-if="diagResult.diagnostics.can_force_activate || (diagResult.promo && !diagResult.promo.is_active)"
                                            type="button"
                                            @click="forceActivateFromDashboard"
                                            :disabled="diagActionLoading"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs transition shadow-xs disabled:opacity-50"
                                        >
                                            <span>⚡</span>
                                            <span>{{ diagActionLoading ? 'جاري التفعيل...' : 'تفعيل الكوبون للبدء فوراً الآن' }}</span>
                                        </button>
                                    </div>
                                    <a
                                        v-if="diagResult.test_url"
                                        :href="diagResult.test_url"
                                        target="_blank"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-stone-800 hover:bg-stone-900 text-white font-bold text-xs transition"
                                    >
                                        <span>🛒 فتح شاشة طلب وسلة تجريبية في هذا المطعم</span>
                                        <span>↗</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- ===== نهاية لوحة وضع التجريب وفحص الكوبونات ===== -->

                <!-- ===== لوحة واتساب إدارة المنصة ومجيب الذكاء التنفيذي (Collapsible with Tabs) ===== -->
                <div class="bg-white dark:bg-[#0a0f1d] overflow-hidden shadow-sm sm:rounded-2xl border border-stone-200 dark:border-slate-800 transition-all duration-300">
                    <div class="p-4 sm:p-6" dir="rtl">
                        <!-- Top Header Bar (Always visible & interactive) -->
                        <div
                            class="flex items-center justify-between flex-wrap gap-4 transition-all"
                            :class="showPlatformAiPanel ? 'border-b border-stone-100 dark:border-slate-800 pb-5' : ''"
                        >
                            <!-- Clickable Title & Live Status -->
                            <div
                                class="flex items-center gap-3.5 cursor-pointer select-none group"
                                @click="togglePlatformAiPanel"
                                title="انقر لإظهار أو إخفاء قسم مجيب الذكاء"
                            >
                                <div
                                    class="p-3 rounded-2xl shrink-0 transition-transform group-hover:scale-105 active:scale-95"
                                    :class="platformWaStatus === 'connected' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300' : 'bg-stone-100 text-stone-600 dark:bg-slate-800 dark:text-slate-400'"
                                >
                                    <span class="text-2xl">🤖</span>
                                </div>
                                <div>
                                    <h3 class="font-bold text-gray-900 dark:text-white text-base flex items-center gap-2 flex-wrap">
                                        <span>مجيب الذكاء التنفيذي وواتساب المنصة</span>
                                        <span class="text-xs font-normal text-stone-500">| Executive AI Assistant & WhatsApp</span>
                                    </h3>
                                    <div class="flex items-center gap-2 mt-1 flex-wrap">
                                        <span
                                            class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold"
                                            :class="platformWaStatus === 'connected'
                                                ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300'
                                                : platformWaStatus === 'scan_qr'
                                                ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 animate-pulse'
                                                : 'bg-stone-100 text-stone-600 dark:bg-slate-800 dark:text-slate-400'"
                                        >
                                            <span
                                                class="inline-block w-2 h-2 rounded-full"
                                                :class="platformWaStatus === 'connected' ? 'bg-emerald-500 animate-pulse' : (platformWaStatus === 'scan_qr' ? 'bg-amber-500' : 'bg-stone-400')"
                                            ></span>
                                            <span v-if="platformWaStatus === 'connected'">متصل برقم المنصة (+{{ platformWaPhone }}) 🟢</span>
                                            <span v-else-if="platformWaStatus === 'scan_qr'">بانتظار مسح الباركود 🟡</span>
                                            <span v-else>غير مرتبط حالياً ⚪</span>
                                        </span>

                                        <span
                                            v-if="platformWaStatus === 'connected'"
                                            class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold"
                                            :class="platformWaAiEnabled ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-300' : 'bg-stone-100 text-stone-600 dark:bg-slate-800 dark:text-slate-400'"
                                        >
                                            <span>{{ platformWaAiEnabled ? 'الرد الذكي مفعّل ⚡' : 'الرد الذكي متوقف' }}</span>
                                        </span>

                                        <span v-if="!showPlatformAiPanel" class="text-[11px] text-stone-400 dark:text-slate-500 hidden sm:inline">
                                            (انقر للتوسيع أو التعديل)
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Action Buttons & Tabs Control -->
                            <div class="flex items-center gap-2 flex-wrap">
                                <!-- Fast Tabs Switcher -->
                                <div class="inline-flex p-1 bg-stone-100 dark:bg-slate-800/80 rounded-xl border border-stone-200/80 dark:border-slate-700/60">
                                    <button
                                        type="button"
                                        @click="selectAiTab('ai')"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer select-none"
                                        :class="showPlatformAiPanel && activeAiTab === 'ai'
                                            ? 'bg-white dark:bg-slate-900 text-indigo-600 dark:text-indigo-400 shadow-xs'
                                            : 'text-stone-600 dark:text-slate-400 hover:text-stone-900 dark:hover:text-white'"
                                    >
                                        <span>🧠</span>
                                        <span>مجيب الذكاء</span>
                                    </button>
                                    <button
                                        type="button"
                                        @click="selectAiTab('whatsapp')"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer select-none"
                                        :class="showPlatformAiPanel && activeAiTab === 'whatsapp'
                                            ? 'bg-white dark:bg-slate-900 text-emerald-600 dark:text-emerald-400 shadow-xs'
                                            : 'text-stone-600 dark:text-slate-400 hover:text-stone-900 dark:hover:text-white'"
                                    >
                                        <span>📲</span>
                                        <span>ربط الواتساب</span>
                                    </button>
                                </div>

                                <!-- Smooth Toggle Button -->
                                <button
                                    type="button"
                                    @click="togglePlatformAiPanel"
                                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold transition-all shadow-xs cursor-pointer select-none active:scale-95"
                                    :class="showPlatformAiPanel
                                        ? 'bg-indigo-600 text-white hover:bg-indigo-700 shadow-indigo-600/20'
                                        : 'bg-white dark:bg-slate-800 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-slate-700 hover:bg-indigo-50 dark:hover:bg-slate-700'"
                                >
                                    <span>{{ showPlatformAiPanel ? 'إخفاء القسم ▲' : 'إظهار القسم 🤖 ▼' }}</span>
                                </button>
                            </div>
                        </div>

                        <!-- Smooth Expandable Body -->
                        <transition
                            enter-active-class="transition-all duration-300 ease-out overflow-hidden"
                            enter-from-class="opacity-0 -translate-y-2 max-h-0"
                            enter-to-class="opacity-100 translate-y-0 max-h-[3000px]"
                            leave-active-class="transition-all duration-200 ease-in overflow-hidden"
                            leave-from-class="opacity-100 translate-y-0 max-h-[3000px]"
                            leave-to-class="opacity-0 -translate-y-2 max-h-0"
                        >
                            <div v-show="showPlatformAiPanel" class="mt-6 space-y-6">
                                <!-- ============================================================ -->
                                <!-- التبويب الأول: مجيب الذكاء التنفيذي وتلقين العقل (AI & Training) -->
                                <!-- ============================================================ -->
                                <div v-show="activeAiTab === 'ai'" class="space-y-4">
                                    <!-- AI Toggle & Status Banner -->
                                    <div class="p-4 rounded-2xl bg-emerald-50/60 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-800/40 flex items-center justify-between flex-wrap gap-3">
                                        <div class="flex items-center gap-3">
                                            <span class="text-2xl">🤖</span>
                                            <div>
                                                <div class="text-sm font-black text-emerald-900 dark:text-emerald-200">
                                                    المساعد التنفيذي الذكي لصاحب المنصة (Google Gemini)
                                                </div>
                                                <div class="text-xs text-emerald-700 dark:text-emerald-400 mt-0.5">
                                                    يرد تلقائياً وبلباقة على استفسارات أصحاب المطاعم، يشرح الميزات والأسعار ونموذج 0% عمولة، ويحفظ بياناتهم فوراً في CRM.
                                                </div>
                                            </div>
                                        </div>
                                        <button
                                            @click="togglePlatformAi"
                                            type="button"
                                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition shadow-xs cursor-pointer"
                                            :class="platformWaAiEnabled
                                                ? 'bg-emerald-600 text-white hover:bg-emerald-500'
                                                : 'bg-stone-200 dark:bg-slate-700 text-stone-700 dark:text-stone-300 hover:bg-stone-300'"
                                        >
                                            <span>{{ platformWaAiEnabled ? 'المساعد الذكي: مفعّل (ON) 🟢' : 'المساعد الذكي: متوقف (OFF) ⚪' }}</span>
                                        </button>
                                    </div>

                                    <!-- Custom AI Knowledge & Training (تلقين وتوجيه عقل المساعد الذكي) -->
                                    <div class="rounded-2xl bg-gradient-to-br from-indigo-50/70 via-purple-50/40 to-white dark:from-indigo-950/20 dark:via-purple-950/10 dark:to-slate-900 border border-indigo-200/80 dark:border-indigo-800/40 shadow-xs overflow-hidden transition-all">
                                        <div class="p-4 sm:p-5 border-b border-indigo-100 dark:border-indigo-900/60 flex items-center justify-between flex-wrap gap-3">
                                            <div class="flex items-center gap-3">
                                                <span class="text-2xl">🧠</span>
                                                <div>
                                                    <h4 class="text-sm font-black text-indigo-950 dark:text-indigo-200 flex items-center gap-2">
                                                        <span>تلقين وتوجيه المساعد الذكي المخصص (Custom AI Knowledge & Training)</span>
                                                        <span class="text-[10px] px-2 py-0.5 rounded-full bg-indigo-100 dark:bg-indigo-900 text-indigo-700 dark:text-indigo-300 font-bold">يُحقن فورياً في Gemini</span>
                                                    </h4>
                                                    <p class="text-xs text-indigo-800/80 dark:text-indigo-300/80 mt-0.5">
                                                        تحديد أسعار الباقات، عروض التدشين، وشروط التعاقد التي يلتزم بها المساعد التنفيذي عند الرد.
                                                    </p>
                                                </div>
                                            </div>

                                            <div v-if="aiInstructionsSavedSuccess" class="text-xs font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-100 dark:bg-emerald-950/80 px-3 py-1 rounded-lg border border-emerald-300 dark:border-emerald-800 flex items-center gap-1 animate-pulse">
                                                <span>✓</span>
                                                <span>تم الحفظ وتحديث عقل المساعد بنجاح!</span>
                                            </div>
                                        </div>

                                        <div class="p-5 space-y-3">
                                            <!-- Quick Preset Chips -->
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                <span class="text-[11px] font-bold text-gray-500 dark:text-gray-400">إدراج نموذج سريع:</span>
                                                <button
                                                    type="button"
                                                    @click="insertInstructionTemplate('- باقة الأساسي (Basic): 50$ شهرياً (منيو QR + شاشات مطبخ + كاشير).\n- باقة المحترفين (Pro): 100$ شهرياً (تشمل الويتر الذكي، عجلة الحظ، وبوابة الواتساب الذكية).\n- باقة المؤسسات (Enterprise): مخصصة للسلاسل الكبيرة بحسب عدد الفروع.')"
                                                    class="text-[11px] font-bold px-2.5 py-1 rounded-lg bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 text-gray-700 dark:text-gray-300 hover:border-indigo-400 hover:text-indigo-600 transition cursor-pointer"
                                                >
                                                    + أسعار الباقات
                                                </button>
                                                <button
                                                    type="button"
                                                    @click="insertInstructionTemplate('- لدينا حالياً عرض خاص لأول 10 مطاعم تسجل هذا الشهر: تركيب وتدريب ميداني مجاني بالكامل مع إعفاء من رسوم الإعداد.')"
                                                    class="text-[11px] font-bold px-2.5 py-1 rounded-lg bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 text-gray-700 dark:text-gray-300 hover:border-indigo-400 hover:text-indigo-600 transition cursor-pointer"
                                                >
                                                    + عرض ترويجي
                                                </button>
                                                <button
                                                    type="button"
                                                    @click="insertInstructionTemplate('- اطلب دائماً من صاحب المطعم معرفة اسم مطعمه الحالي والمدينة لمساعدته بدقة.\n- ركّز دائماً على أن المنصة تلغي عمولة تطبيقات التوصيل (0% عمولة) وتحفظ أرباح المبيعات كاملة لصاحب المطعم.')"
                                                    class="text-[11px] font-bold px-2.5 py-1 rounded-lg bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 text-gray-700 dark:text-gray-300 hover:border-indigo-400 hover:text-indigo-600 transition cursor-pointer"
                                                >
                                                    + أسلوب الرد وقواعد التركيز
                                                </button>
                                            </div>

                                            <!-- Textarea -->
                                            <textarea
                                                v-model="platformAiCustomInstructions"
                                                rows="5"
                                                placeholder="اكتب هنا أي تعليمات مخصصة، أسعار محددة، عروض جديدة، أو تفاصيل ترغب بتلقينها للمساعد الذكي..."
                                                class="w-full text-xs font-normal leading-relaxed rounded-xl border border-indigo-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-gray-900 dark:text-white p-3.5 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition shadow-inner"
                                            ></textarea>

                                            <!-- Save Action Bar -->
                                            <div class="flex items-center justify-between flex-wrap gap-2 pt-1">
                                                <span class="text-[11px] text-gray-500 dark:text-gray-400">
                                                    💡 أي تعديل تكتبه هنا يتم أخذه بعين الاعتبار في نفس اللحظة بكل ردود الواتساب والموقع دون الحاجة لإعادة تشغيل.
                                                </span>
                                                <button
                                                    type="button"
                                                    @click="saveAiInstructions"
                                                    :disabled="savingAiInstructions"
                                                    class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-xl text-xs font-bold bg-indigo-600 hover:bg-indigo-700 text-white shadow-md shadow-indigo-600/20 transition cursor-pointer disabled:opacity-50"
                                                >
                                                    <span v-if="savingAiInstructions" class="animate-spin text-sm">⏳</span>
                                                    <span v-else>💾</span>
                                                    <span>{{ savingAiInstructions ? 'جارٍ الحفظ وتحديث العقل...' : 'حفظ وتحديث عقل المساعد فوراً' }}</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- ============================================================ -->
                                <!-- التبويب الثاني: إدارة خط الواتساب والربط (WhatsApp Connection) -->
                                <!-- ============================================================ -->
                                <div v-show="activeAiTab === 'whatsapp'" class="space-y-4">
                                    <!-- Connection Card -->
                                    <div class="p-5 rounded-2xl bg-stone-50 dark:bg-slate-900/60 border border-stone-200 dark:border-slate-800 flex items-center justify-between flex-wrap gap-4">
                                        <div>
                                            <h4 class="font-bold text-stone-900 dark:text-white text-sm">
                                                حالة جلسة الواتساب لجهاز المنصة الرسمي
                                            </h4>
                                            <p class="text-xs text-stone-500 dark:text-slate-400 mt-1">
                                                الربط يتيح للمنصة الرد المباشر وإرسال التنبيهات والإشعارات دون الاعتماد على تطبيقات طرف ثالث.
                                            </p>
                                        </div>

                                        <div class="flex items-center gap-2">
                                            <button
                                                v-if="platformWaStatus !== 'connected'"
                                                @click="connectPlatformWa"
                                                :disabled="platformWaLoading"
                                                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-bold text-sm bg-emerald-600 hover:bg-emerald-700 text-white transition-all shadow-sm focus:ring-2 focus:ring-offset-2 disabled:opacity-50 cursor-pointer"
                                            >
                                                <span v-if="platformWaLoading">جارٍ التوليد...</span>
                                                <span v-else>📲 مسح رمز الاستجابة (QR Code) للربط</span>
                                            </button>
                                            <button
                                                v-else
                                                @click="disconnectPlatformWa"
                                                :disabled="platformWaLoading"
                                                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl font-bold text-xs bg-rose-50 hover:bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400 border border-rose-200 dark:border-rose-800 transition cursor-pointer"
                                            >
                                                <span>فصل الجلسة</span>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Scan QR State -->
                                    <div v-if="platformWaStatus === 'scan_qr'" class="text-center py-6 bg-white dark:bg-slate-900 rounded-2xl border border-stone-200 dark:border-slate-800">
                                        <p class="text-sm font-bold text-stone-800 dark:text-white mb-4">
                                            امسح رمز الاستجابة السريعة (QR) من تطبيق واتساب في هاتفك المخصص للمنصة
                                        </p>
                                        <div v-if="platformWaQr" class="inline-block p-4 bg-white rounded-2xl shadow-lg border border-stone-200">
                                            <img :src="platformWaQr" alt="Platform WhatsApp QR Code" class="w-60 h-60 mx-auto object-contain" />
                                        </div>
                                        <div class="mt-5 max-w-sm mx-auto text-xs text-stone-600 dark:text-slate-400 space-y-1.5 text-right bg-stone-50 dark:bg-slate-800 p-4 rounded-xl border border-stone-200 dark:border-slate-700">
                                            <div class="font-bold text-stone-900 dark:text-white mb-1">خطوات الربط لمرة واحدة:</div>
                                            <div>1. افتح تطبيق واتساب على هاتفك المخصص للمنصة.</div>
                                            <div>2. اضغط على خيارات (الثلاث نقاط) ⬅️ <strong>«الأجهزة المرتبطة»</strong>.</div>
                                            <div>3. اضغط <strong>«ربط جهاز»</strong> ووجّه الكاميرا نحو الباركود أعلاه.</div>
                                        </div>
                                    </div>

                                    <!-- Test Message & Leads Quick Access -->
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div class="p-4 rounded-2xl bg-stone-50 dark:bg-slate-900/40 border border-stone-200 dark:border-slate-800 flex flex-col justify-between">
                                            <div>
                                                <div class="text-xs font-bold text-stone-800 dark:text-white mb-2">تجربة إرسال رسالة فحص من رقم المنصة:</div>
                                                <div class="flex items-center gap-2">
                                                    <input
                                                        v-model="platformTestPhone"
                                                        type="text"
                                                        placeholder="رقم الهاتف (مثال: 0933123456)"
                                                        class="flex-1 rounded-xl text-xs border border-stone-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-stone-800 dark:text-white"
                                                    />
                                                    <button
                                                        @click="sendPlatformTestMsg"
                                                        :disabled="platformTestLoading || !platformTestPhone.trim()"
                                                        type="button"
                                                        class="px-3 py-2 rounded-xl text-xs font-bold bg-stone-900 hover:bg-stone-800 text-white dark:bg-slate-700 dark:hover:bg-slate-600 transition disabled:opacity-50 cursor-pointer"
                                                    >
                                                        {{ platformTestLoading ? 'جارٍ الإرسال...' : 'إرسال فحص' }}
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="p-4 rounded-2xl bg-amber-50/60 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-800/40 flex items-center justify-between">
                                            <div>
                                                <div class="text-xs font-bold text-amber-900 dark:text-amber-200">سجل أصحاب المطاعم والعملاء المحتملين:</div>
                                                <div class="text-xs text-amber-700 dark:text-amber-400 mt-1">كل من يراسل واتساب المنصة يُسجل فوراً في CRM للمتابعة.</div>
                                            </div>
                                            <Link
                                                href="/super-admin/leads"
                                                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold bg-amber-600 hover:bg-amber-500 text-white transition shadow-xs"
                                            >
                                                <span>عرض العملاء</span>
                                                <span>↗</span>
                                            </Link>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </transition>
                    </div>
                </div>
                <!-- ===== نهاية لوحة واتساب إدارة المنصة ومجيب الذكاء ===== -->

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900" dir="rtl">
                        <!-- Stats Grid -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                            <div class="bg-amber-50 p-5 rounded-2xl border border-amber-100 flex items-center justify-between">
                                <div>
                                    <p class="text-xs text-amber-800 font-semibold mb-1">إجمالي المطاعم</p>
                                    <p class="text-2xl font-black text-amber-600">{{ stats.total_restaurants }}</p>
                                </div>
                                <div class="bg-amber-200 text-amber-700 p-2.5 rounded-full">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72l1.189-1.19A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72M6.75 18h3.75a.75.75 0 0 0 .75-.75V13.5a.75.75 0 0 0-.75-.75H6.75a.75.75 0 0 0-.75.75v3.75c0 .414.336.75.75.75Z" /></svg>
                                </div>
                            </div>
                            <div class="bg-green-50 p-5 rounded-2xl border border-green-100 flex items-center justify-between">
                                <div>
                                    <p class="text-xs text-green-800 font-semibold mb-1">المطاعم النشطة</p>
                                    <p class="text-2xl font-black text-green-600">{{ stats.active_restaurants }}</p>
                                </div>
                                <div class="bg-green-200 text-green-700 p-2.5 rounded-full">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                                </div>
                            </div>
                            <Link :href="route('super_admin.users')" class="bg-blue-50 p-5 rounded-2xl border border-blue-100 flex items-center justify-between hover:bg-blue-100 transition-colors">
                                <div>
                                    <p class="text-xs text-blue-800 font-semibold mb-1">المستخدمين (إدارة)</p>
                                    <p class="text-2xl font-black text-blue-600">{{ stats.total_users }}</p>
                                </div>
                                <div class="bg-blue-200 text-blue-700 p-2.5 rounded-full">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" /></svg>
                                </div>
                            </Link>

                            <!-- Leads CRM Card -->
                            <Link :href="route('super_admin.leads')" class="bg-gradient-to-br from-amber-50 to-orange-50 p-5 rounded-2xl border-2 border-amber-300 flex items-center justify-between hover:border-amber-400 hover:shadow-md transition-all group">
                                <div>
                                    <div class="flex items-center gap-1.5 mb-1">
                                        <p class="text-xs text-amber-900 font-bold">طلبات الاستشارة (Leads)</p>
                                        <span v-if="leadsStats?.new > 0" class="text-[10px] px-1.5 py-0.2 rounded-full font-bold bg-rose-500 text-white animate-pulse">
                                            {{ leadsStats.new }} جديد
                                        </span>
                                    </div>
                                    <p class="text-2xl font-black text-amber-700">
                                        {{ leadsStats?.total ?? '0' }}
                                    </p>
                                </div>
                                <div class="bg-amber-200 text-amber-900 p-2.5 rounded-2xl group-hover:scale-110 transition-transform">
                                    <span class="text-xl">📋</span>
                                </div>
                            </Link>
                        </div>

                        <!-- Restaurant List Header + Add Button -->
                        <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
                            <h3 class="text-lg font-bold text-gray-800">قائمة المطاعم في النظام</h3>
                            <div class="flex items-center gap-2">
                                <Link
                                    :href="route('super_admin.leads')"
                                    class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl font-bold text-xs sm:text-sm transition-all duration-200 border border-amber-400 text-amber-900 bg-amber-100 hover:bg-amber-200 shadow-2xs"
                                >
                                    <span>📋 متابعة طلبات الاستشارة (Leads)</span>
                                    <span v-if="leadsStats?.new > 0" class="bg-rose-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full">
                                        {{ leadsStats.new }}
                                    </span>
                                </Link>
                                <Link
                                    :href="route('super_admin.restaurants.create')"
                                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl font-medium text-xs sm:text-sm transition-all duration-200 border border-amber-300 text-amber-700 bg-amber-50 hover:bg-amber-100"
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
