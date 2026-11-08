<script setup lang="ts">
import AdminNav from '@/Components/Admin/AdminNav.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import axios from 'axios';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const isLocked = ref(false);
const loading = ref(true);
const offers = ref<any[]>([]);
const activeTab = ref<'master' | 'wheel'>('master');
const stats = ref({
    active_master_count: 0,
    total_wheel_vouchers: 0,
    used_wheel_vouchers: 0,
});

const form = useForm({
    code: '',
    discount_type: 'percentage',
    discount_value: '',
    min_order_value: '' as any,
});

function formatDiscountVal(val: number | string): string {
    const num = parseFloat(String(val));
    if (isNaN(num)) return '0';
    return num % 1 === 0 ? String(Math.round(num)) : num.toFixed(2).replace(/\.00$/, '');
}

function formatCurr(curr?: string): string {
    if (!curr || curr.toUpperCase() === 'SYP') return 'ل.س';
    return curr;
}

const loadOffers = async () => {
    try {
        const response = await axios.get('/api/admin/promo-codes', {
            params: { type: activeTab.value },
        });
        offers.value = response.data.data;
        if (response.data.stats) {
            stats.value = response.data.stats;
        }
    } catch (e: any) {
        if (e.response?.status === 403) {
            isLocked.value = true;
        }
    } finally {
        loading.value = false;
    }
};

const switchTab = (tab: 'master' | 'wheel') => {
    activeTab.value = tab;
    loadOffers();
};

onMounted(loadOffers);

const createOffer = async () => {
    try {
        const payload = form.data();
        if (payload.min_order_value === '' || payload.min_order_value === null) {
            delete (payload as any).min_order_value;
        }
        await axios.post('/api/admin/promo-codes', payload);
        form.reset();
        await loadOffers();
    } catch (e: any) {
        alert(e.response?.data?.message || 'خطأ أثناء إضافة العرض');
    }
};

const toggleActive = async (offer: any) => {
    try {
        await axios.patch(`/api/admin/promo-codes/${offer.id}`, {
            code: offer.code,
            discount_type: offer.discount_type,
            discount_value: offer.discount_value,
            min_order_value: offer.min_order_value,
            expires_at: offer.expires_at,
            is_active: !offer.is_active,
        });
        await loadOffers();
    } catch (e: any) {
        alert(e.response?.data?.message || 'خطأ أثناء تحديث الحالة');
    }
};

const deleteOffer = async (id: number) => {
    if (!confirm('هل أنت متأكد من حذف هذا الكوبون؟')) return;
    try {
        await axios.delete(`/api/admin/promo-codes/${id}`);
        await loadOffers();
    } catch (e: any) {
        alert(e.response?.data?.message || 'خطأ أثناء الحذف');
    }
};
</script>

<template>
    <Head title="العروض والخصومات" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800 dark:text-white">العروض والكوبونات (نظام الولاء وعجلة الحظ)</h2>
        </template>
        <div class="mx-auto max-w-5xl p-6" dir="rtl">
            <AdminNav />
            
            <div v-if="loading" class="text-center py-10">
                <p class="text-stone-500 dark:text-stone-400">جاري تحميل البيانات...</p>
            </div>

            <!-- Locked State -->
            <div v-else-if="isLocked" class="bg-white dark:bg-[#0a0f1d] rounded-2xl p-10 text-center shadow-xs border border-stone-100 dark:border-slate-800 max-w-2xl mx-auto mt-10">
                <div class="bg-blue-100 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-10"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                </div>
                <h3 class="text-2xl font-bold text-gray-800 dark:text-white mb-2">ميزة الكوبونات والعروض متاحة في باقة Pro فما فوق</h3>
                <p class="text-stone-500 dark:text-stone-400 mb-6 leading-relaxed">
                    نظام العروض والكوبونات يتيح لك إنشاء أكواد خصم ذكية وتنشيط المبيعات وبناء ولاء الزبائن. للترقية والاستفادة من الميزة يرجى التواصل مع إدارة تدلّل.
                </p>
                <a
                    href="https://wa.me/963934578117?text=%D9%85%D8%B1%D8%AD%D8%A8%D8%A7%20%D8%A3%D8%B3%D8%AA%D8%A7%D8%B0%20%D8%B9%D9%85%D8%B1%D8%8C%20%D8%A3%D8%B1%D8%BA%D8%A8%20%D8%A8%D8%AA%D8%B1%D9%82%D9%8A%D8%A9%20%D8%A8%D8%A7%D9%82%D8%A9%20%D9%85%D8%B7%D8%B9%D9%85%D9%8A%20%D9%84%D8%AA%D9%81%D8%B9%D9%8A%D9%84%20%D9%85%D9%8A%D8%B2%D8%A9%20%D8%A7%D9%84%D9%83%D9%88%D8%A8%D9%88%D9%86%D8%A7%D8%AA"
                    target="_blank"
                    class="inline-flex items-center gap-2 bg-emerald-600 text-white px-8 py-3 rounded-xl font-bold hover:bg-emerald-700 transition shadow-lg shadow-emerald-600/20"
                >
                    <span>💬 تواصل مع الإدارة للترقية الفورية</span>
                </a>
            </div>

            <!-- Active State -->
            <div v-else class="space-y-6">
                <!-- Wheel Marketing System Banner -->
                <div class="border border-amber-500/30 rounded-2xl overflow-hidden">
                    <!-- Header -->
                    <div class="bg-gradient-to-r from-amber-500/10 via-amber-500/5 to-transparent p-5 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                        <div class="flex items-start gap-3.5">
                            <div class="w-12 h-12 rounded-xl bg-amber-500/20 text-amber-500 dark:text-amber-400 flex items-center justify-center text-2xl shrink-0">
                                🎡
                            </div>
                            <div>
                                <h4 class="text-base font-bold text-gray-800 dark:text-white flex items-center gap-2">
                                    <span>عجلة الحظ — كيف تعمل؟</span>
                                    <span class="bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 text-[11px] px-2 py-0.5 rounded-full font-bold">مفعلة تلقائياً</span>
                                </h4>
                                <p class="text-xs text-stone-600 dark:text-slate-400 mt-1 leading-relaxed">
                                    عند إتمام أي طلب، يحصل الزبون على رابط يتيح له تدوير عجلة الحظ مرة واحدة والفوز بكوبون لزيارته القادمة.
                                </p>
                            </div>
                        </div>
                        <a :href="`/wheel/demo${$page.props.auth?.restaurant?.id ? `?restaurant_id=${$page.props.auth.restaurant.id}` : ''}`" target="_blank"
                           class="shrink-0 inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-amber-500/20 hover:bg-amber-500/30 border border-amber-500/40 text-amber-700 dark:text-amber-300 text-sm font-semibold transition-colors">
                            <span>🎲</span>
                            <span>معاينة العجلة</span>
                        </a>
                    </div>

                    <!-- IMPORTANT: What the customer sees -->
                    <div class="bg-blue-50 dark:bg-blue-950/20 border-t border-blue-200 dark:border-blue-800/40 px-5 py-3.5">
                        <div class="flex items-start gap-2.5">
                            <span class="text-blue-500 dark:text-blue-400 mt-0.5 shrink-0">ℹ️</span>
                            <div class="text-xs text-blue-700 dark:text-blue-300 leading-relaxed">
                                <span class="font-bold">ما يراه الزبون على العجلة:</span>
                                القيمة الحقيقية للخصم فقط (مثال: <span class="font-mono font-bold">خصم 15%</span> أو <span class="font-mono font-bold">خصم 5000 ل.س</span>) — وليس كود الكوبون.
                                هذا يضمن الشفافية التامة مع الزبون بغض النظر عن اسم الكوبون الذي تختاره.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-white dark:bg-[#0a0f1d] border border-stone-100 dark:border-slate-800 rounded-2xl p-4 flex items-center justify-between">
                        <div>
                            <span class="text-xs text-stone-500 dark:text-slate-400 font-medium">جوائز العجلة النشطة</span>
                            <h5 class="text-2xl font-black text-amber-500 mt-1">{{ stats.active_master_count }}</h5>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-amber-100 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400 flex items-center justify-center text-lg">
                            🎡
                        </div>
                    </div>
                    <div class="bg-white dark:bg-[#0a0f1d] border border-stone-100 dark:border-slate-800 rounded-2xl p-4 flex items-center justify-between">
                        <div>
                            <span class="text-xs text-stone-500 dark:text-slate-400 font-medium">كوبونات الفواتير الصادرة</span>
                            <h5 class="text-2xl font-black text-blue-500 mt-1">{{ stats.total_wheel_vouchers }}</h5>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-blue-100 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 flex items-center justify-center text-lg">
                            🎟️
                        </div>
                    </div>
                    <div class="bg-white dark:bg-[#0a0f1d] border border-stone-100 dark:border-slate-800 rounded-2xl p-4 flex items-center justify-between">
                        <div>
                            <span class="text-xs text-stone-500 dark:text-slate-400 font-medium">كوبونات تم استخدامها بالزيارة التالية</span>
                            <h5 class="text-2xl font-black text-emerald-500 mt-1">{{ stats.used_wheel_vouchers }}</h5>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-lg">
                            ✓
                        </div>
                    </div>
                </div>

                <!-- Tabs Navigation -->
                <div class="flex items-center gap-2 border-b border-stone-200 dark:border-slate-800 pb-2">
                    <button
                        @click="switchTab('master')"
                        :class="activeTab === 'master' ? 'bg-amber-500 text-slate-950 font-bold shadow-md' : 'text-stone-600 dark:text-slate-400 hover:text-stone-900 dark:hover:text-white'"
                        class="px-4 py-2 rounded-xl text-sm transition"
                    >
                        الكوبونات الأساسية (شرائح عجلة الحظ)
                    </button>
                    <button
                        @click="switchTab('wheel')"
                        :class="activeTab === 'wheel' ? 'bg-amber-500 text-slate-950 font-bold shadow-md' : 'text-stone-600 dark:text-slate-400 hover:text-stone-900 dark:hover:text-white'"
                        class="px-4 py-2 rounded-xl text-sm transition flex items-center gap-2"
                    >
                        <span>الكوبونات الممنوحة عبر الفواتير</span>
                        <span v-if="stats.total_wheel_vouchers > 0" class="text-xs px-2 py-0.5 rounded-full bg-slate-900 text-white dark:bg-slate-800">
                            {{ stats.total_wheel_vouchers }}
                        </span>
                    </button>
                </div>

                <!-- Tab 1: Master Coupons (Creation + Table) -->
                <div v-if="activeTab === 'master'" class="space-y-6">
                    <!-- Add New Offer -->
                    <div class="bg-white dark:bg-[#0a0f1d] rounded-2xl shadow-xs border border-stone-100 dark:border-slate-800 p-6">
                        <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-1">إنشاء جائزة عجلة حظ جديدة</h3>
                        <p class="text-xs text-stone-500 dark:text-slate-400 mb-4">
                            🎡 ما سيظهر على العجلة للزبون هو <span class="font-bold text-amber-600 dark:text-amber-400">القيمة الحقيقية فقط</span> (نسبة أو مبلغ)، وليس كود الكوبون.
                        </p>
                        <form @submit.prevent="createOffer" class="grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">نوع الخصم</label>
                                <select v-model="form.discount_type" class="w-full border-gray-300 dark:border-slate-700 rounded-md shadow-xs bg-white dark:bg-slate-900 text-stone-900 dark:text-white focus:border-amber-500 focus:ring-amber-500 text-sm">
                                    <option value="percentage">نسبة مئوية (%)</option>
                                    <option value="fixed">مبلغ ثابت ({{ $page.props.auth.restaurant?.currency }})</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    قيمة الخصم
                                    <span class="text-[10px] text-amber-600 dark:text-amber-400 font-semibold mr-1">← على العجلة</span>
                                </label>
                                <TextInput v-model="form.discount_value" type="number" step="0.01" class="w-full" placeholder="مثال: 15" required />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    الحد الأدنى للفاتورة
                                    <span class="text-[10px] text-stone-400 font-normal mr-1">(أمان: 3x تلقائياً)</span>
                                </label>
                                <TextInput v-model="form.min_order_value" type="number" step="0.01" class="w-full" placeholder="تلقائي (اختياري)" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    كود داخلي
                                    <span class="text-[10px] text-stone-400 font-normal mr-1">(اختياري)</span>
                                </label>
                                <TextInput v-model="form.code" type="text" class="w-full" placeholder="تلقائي" />
                            </div>
                            <div>
                                <PrimaryButton type="submit" class="w-full justify-center h-10">إضافة للعجلة</PrimaryButton>
                            </div>
                        </form>
                    </div>

                    <!-- Offers List -->
                    <div class="bg-white dark:bg-[#0a0f1d] rounded-2xl shadow-xs border border-stone-100 dark:border-slate-800 overflow-x-auto">
                        <table class="w-full text-right text-sm">
                            <thead class="bg-stone-50 dark:bg-slate-800/80 text-stone-600 dark:text-slate-400 border-b border-stone-100 dark:border-slate-800">
                                <tr>
                                    <th class="py-3 px-4 font-semibold">🎡 ما يراه الزبون على العجلة</th>
                                    <th class="py-3 px-4 font-semibold">قيمة الخصم</th>
                                    <th class="py-3 px-4 font-semibold">الحد الأدنى للطلب</th>
                                    <th class="py-3 px-4 font-semibold">كود النظام</th>
                                    <th class="py-3 px-4 font-semibold">الانتهاء</th>
                                    <th class="py-3 px-4 font-semibold">الحالة</th>
                                    <th class="py-3 px-4 font-semibold">إجراءات</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stone-100 dark:divide-slate-800">
                                <tr v-if="offers.length === 0">
                                    <td colspan="7" class="py-6 text-center text-stone-400 dark:text-slate-500">لا توجد كوبونات مسجلة. أضف كوبوناً أعلاه ليظهر كجائزة في عجلة الحظ.</td>
                                </tr>
                                <tr v-for="offer in offers" :key="offer.id" class="hover:bg-stone-50/50 dark:hover:bg-slate-800/40 transition-colors">
                                    <!-- What customer sees on wheel — built from real values, never from code name -->
                                    <td class="py-3 px-4">
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg font-bold text-sm"
                                              :class="offer.is_active ? 'bg-amber-100 dark:bg-amber-950/50 text-amber-800 dark:text-amber-300 border border-amber-300/50 dark:border-amber-700/40' : 'bg-stone-100 dark:bg-slate-800 text-stone-400 dark:text-slate-500'">
                                            <span>🎁</span>
                                            <span>
                                                خصم
                                                {{ offer.discount_type === 'percentage'
                                                    ? `${formatDiscountVal(offer.discount_value)}%`
                                                    : `${formatDiscountVal(offer.discount_value)} ${formatCurr($page.props.auth.restaurant?.currency)}` }}
                                            </span>
                                        </span>
                                    </td>

                                    <!-- Actual discount value -->
                                    <td class="py-3 px-4 font-bold text-gray-800 dark:text-white">
                                        {{ formatDiscountVal(offer.discount_value) }}
                                        {{ offer.discount_type === 'percentage' ? '%' : formatCurr($page.props.auth.restaurant?.currency) }}
                                        <span class="text-[10px] font-normal text-stone-400 mr-1">({{ offer.discount_type === 'percentage' ? 'نسبة' : 'مبلغ ثابت' }})</span>
                                    </td>

                                    <!-- Minimum Order Safety Threshold -->
                                    <td class="py-3 px-4 text-xs font-semibold text-gray-700 dark:text-slate-300">
                                        <span v-if="parseFloat(offer.min_order_value) > 0" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-stone-100 dark:bg-slate-800/80 text-stone-800 dark:text-stone-200">
                                            <span>🛒</span>
                                            <span>{{ formatDiscountVal(offer.min_order_value) }} {{ formatCurr($page.props.auth.restaurant?.currency) }}</span>
                                        </span>
                                        <span v-else class="text-stone-400 dark:text-slate-500 font-normal">
                                            بدون حد أدنى
                                        </span>
                                    </td>

                                    <!-- Internal code (admin only) -->
                                    <td class="py-3 px-4 font-mono text-xs text-stone-400 dark:text-slate-500" dir="ltr">{{ offer.code }}</td>

                                    <td class="py-3 px-4 text-gray-600 dark:text-slate-400 text-xs">{{ offer.expires_at ? new Date(offer.expires_at).toLocaleDateString('ar') : 'مفتوح' }}</td>

                                    <!-- Toggle active/inactive (affects wheel visibility) -->
                                    <td class="py-3 px-4">
                                        <button @click="toggleActive(offer)"
                                                :class="offer.is_active ? 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-200 dark:hover:bg-emerald-900/60' : 'bg-stone-100 dark:bg-slate-800 text-stone-500 dark:text-slate-400 hover:bg-stone-200 dark:hover:bg-slate-700'"
                                                class="px-2.5 py-1 rounded-md text-xs font-semibold transition-colors">
                                            {{ offer.is_active ? '✓ نشط في العجلة' : '✕ معطل' }}
                                        </button>
                                    </td>

                                    <td class="py-3 px-4">
                                        <button @click="deleteOffer(offer.id)" class="text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 text-sm">حذف</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tab 2: Issued Wheel Vouchers -->
                <div v-else class="space-y-4">
                    <div class="bg-white dark:bg-[#0a0f1d] rounded-2xl shadow-xs border border-stone-100 dark:border-slate-800 overflow-x-auto">
                        <table class="w-full text-right text-sm">
                            <thead class="bg-stone-50 dark:bg-slate-800/80 text-stone-600 dark:text-slate-400 border-b border-stone-100 dark:border-slate-800">
                                <tr>
                                    <th class="py-3 px-4 font-semibold">كود الزبون الفريد</th>
                                    <th class="py-3 px-4 font-semibold">قيمة الخصم</th>
                                    <th class="py-3 px-4 font-semibold">الحد الأدنى للطلب</th>
                                    <th class="py-3 px-4 font-semibold">صالح ابتداءً من</th>
                                    <th class="py-3 px-4 font-semibold">تاريخ الانتهاء</th>
                                    <th class="py-3 px-4 font-semibold">حالة الاستخدام</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stone-100 dark:divide-slate-800">
                                <tr v-if="offers.length === 0">
                                    <td colspan="6" class="py-6 text-center text-stone-400 dark:text-slate-500">لا توجد كوبونات فواتير صادرة حتى الآن.</td>
                                </tr>
                                <tr v-for="voucher in offers" :key="voucher.id" class="hover:bg-stone-50/50 dark:hover:bg-slate-800/40 transition-colors">
                                    <td class="py-3 px-4 font-mono font-bold text-amber-500 dark:text-amber-400" dir="ltr">{{ voucher.code }}</td>
                                    <td class="py-3 px-4 text-gray-800 dark:text-slate-200 font-semibold">
                                        خصم
                                        {{ voucher.discount_type === 'percentage'
                                            ? `${formatDiscountVal(voucher.discount_value)}%`
                                            : `${formatDiscountVal(voucher.discount_value)} ${formatCurr($page.props.auth.restaurant?.currency)}` }}
                                    </td>
                                    <td class="py-3 px-4 text-xs font-semibold text-gray-700 dark:text-slate-300">
                                        <span v-if="parseFloat(voucher.min_order_value) > 0" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-stone-100 dark:bg-slate-800 text-stone-700 dark:text-stone-300">
                                            <span>🛒</span>
                                            <span>{{ formatDiscountVal(voucher.min_order_value) }} {{ formatCurr($page.props.auth.restaurant?.currency) }}</span>
                                        </span>
                                        <span v-else class="text-stone-400 dark:text-slate-500 font-normal">
                                            بدون حد أدنى
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-gray-600 dark:text-slate-400 text-xs">
                                        {{ voucher.starts_at ? new Date(voucher.starts_at).toLocaleString() : 'فوري' }}
                                    </td>
                                    <td class="py-3 px-4 text-gray-600 dark:text-slate-400 text-xs">
                                        {{ voucher.expires_at ? new Date(voucher.expires_at).toLocaleDateString() : '-' }}
                                    </td>
                                    <td class="py-3 px-4">
                                        <span v-if="voucher.times_used > 0" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300">
                                            تم استخدامه في طلب ✓
                                        </span>
                                        <span v-else class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300">
                                            بانتظار الزيارة القادمة
                                        </span>
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
