<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted, nextTick } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
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
    phone: '',
    address: '',
    currency: 'SAR',
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
        name: '', phone: '', address: '', currency: 'SAR',
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
        restaurants.value = response.data;
    } catch (e) {
        console.error(e);
    }
};

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

const toggleFeature = async (restaurant: any, featureName: string) => {
    try {
        const currentStatus = restaurant.features?.[featureName] || false;
        await axios.patch(`/api/super-admin/restaurants/${restaurant.id}`, {
            features: { [featureName]: !currentStatus }
        });
        await fetchRestaurants();
    } catch (e) {
        console.error(`Failed to toggle ${featureName}`);
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

onMounted(() => {
    fetchStats();
    fetchRestaurants();
});
</script>

<template>
    <Head title="إدارة مطاعم - مدير النظام" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">إدارة المطاعم</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900" dir="rtl">
                        <!-- Stats Grid -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                            <div class="bg-amber-50 p-6 rounded-2xl border border-amber-100 flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-amber-800 font-semibold mb-1">إجمالي المطاعم</p>
                                    <p class="text-3xl font-bold text-amber-600">{{ stats.total_restaurants }}</p>
                                </div>
                                <div class="bg-amber-200 text-amber-700 p-3 rounded-full">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72l1.189-1.19A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72M6.75 18h3.75a.75.75 0 0 0 .75-.75V13.5a.75.75 0 0 0-.75-.75H6.75a.75.75 0 0 0-.75.75v3.75c0 .414.336.75.75.75Z" /></svg>
                                </div>
                            </div>
                            <div class="bg-green-50 p-6 rounded-2xl border border-green-100 flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-green-800 font-semibold mb-1">المطاعم النشطة</p>
                                    <p class="text-3xl font-bold text-green-600">{{ stats.active_restaurants }}</p>
                                </div>
                                <div class="bg-green-200 text-green-700 p-3 rounded-full">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                                </div>
                            </div>
                            <Link :href="route('super_admin.users')" class="bg-blue-50 p-6 rounded-2xl border border-blue-100 flex items-center justify-between hover:bg-blue-100 transition-colors">
                                <div>
                                    <p class="text-sm text-blue-800 font-semibold mb-1">إجمالي المستخدمين (إدارة)</p>
                                    <p class="text-3xl font-bold text-blue-600">{{ stats.total_users }}</p>
                                </div>
                                <div class="bg-blue-200 text-blue-700 p-3 rounded-full">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" /></svg>
                                </div>
                            </Link>
                        </div>

                        <!-- Restaurant List Header + Add Button -->
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-bold text-gray-800">قائمة المطاعم في النظام</h3>
                            <button
                                @click="toggleCreateForm"
                                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-medium text-sm transition-all duration-300 shadow-sm focus:ring-2 focus:ring-offset-2"
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
                                                        <option value="SAR">SAR - ريال سعودي</option>
                                                        <option value="SYP">SYP - ليرة سورية</option>
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
                                                        placeholder="+966 5XXXXXXXX" />
                                                </div>
                                                <div>
                                                    <label for="sa-restaurant-address" class="block text-sm font-medium text-gray-700 mb-1">العنوان</label>
                                                    <input id="sa-restaurant-address" v-model="form.address"
                                                        class="w-full rounded-xl border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 text-sm transition"
                                                        placeholder="المدينة، الحي، الشارع" />
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

                        <!-- Restaurants Table -->
                        <div class="overflow-x-auto">
                            <table class="w-full text-right border-collapse whitespace-nowrap">
                                <thead>
                                <tr class="bg-gray-100">
                                    <th class="p-3 border">الاسم</th>
                                    <th class="p-3 border">الحالة</th>
                                    <th class="p-3 border">الميزات الإضافية</th>
                                    <th class="p-3 border">الهاتف</th>
                                    <th class="p-3 border text-center">إجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="restaurant in restaurants" :key="restaurant.id">
                                    <td class="p-3 border">{{ restaurant.name }}</td>
                                    <td class="p-3 border">
                                        <span class="px-2 py-1 rounded text-xs" :class="restaurant.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
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
