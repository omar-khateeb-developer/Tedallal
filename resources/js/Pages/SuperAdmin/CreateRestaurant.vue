<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import axios from 'axios';

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

const createRestaurant = async () => {
    loading.value = true;
    message.value = '';
    errors.value = {};
    isSuccess.value = false;
    try {
        await axios.post('/api/super-admin/restaurants', form.value);
        message.value = 'تم إضافة المطعم والمدير بنجاح!';
        isSuccess.value = true;
        form.value = {
            name: '', phone: '', address: '', currency: 'SAR',
            manager_name: '', manager_email: '', manager_password: ''
        };
    } catch (e: any) {
        if (e.response?.status === 422 && e.response?.data?.errors) {
            errors.value = e.response.data.errors;
        }
        message.value = e.response?.data?.message || 'حدث خطأ أثناء الإضافة';
    } finally {
        loading.value = false;
    }
};

function fieldError(field: string): string | undefined {
    return errors.value[field]?.[0];
}
</script>

<template>
    <Head title="إضافة مطعم جديد - مدير النظام" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">إضافة مطعم جديد</h2>
                <Link :href="route('super_admin.dashboard')"
                    class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-amber-700 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" class="size-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" />
                    </svg>
                    العودة للوحة التحكم
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <!-- Success Banner -->
                <Transition
                    enter-active-class="transition ease-out duration-300"
                    enter-from-class="opacity-0 -translate-y-2"
                    enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="transition ease-in duration-200"
                    leave-from-class="opacity-100 translate-y-0"
                    leave-to-class="opacity-0 -translate-y-2"
                >
                    <div v-if="isSuccess" class="mb-6 rounded-2xl bg-green-50 border border-green-200 p-4 flex items-center gap-3">
                        <div class="bg-green-200 text-green-700 p-2 rounded-full shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                        </div>
                        <div class="flex-1">
                            <p class="text-green-800 font-semibold text-sm">{{ message }}</p>
                            <p class="text-green-600 text-xs mt-0.5">يمكنك إضافة مطعم آخر أو العودة للوحة التحكم.</p>
                        </div>
                        <Link :href="route('super_admin.dashboard')"
                            class="text-xs text-green-700 hover:text-green-900 underline shrink-0">
                            العودة
                        </Link>
                    </div>
                </Transition>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-2xl">
                    <div class="p-8 text-gray-900" dir="rtl">
                        <!-- Header Icon -->
                        <div class="flex items-center gap-3 mb-8">
                            <div class="bg-amber-100 text-amber-600 p-3 rounded-xl">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-7">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72l1.189-1.19A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72M6.75 18h3.75a.75.75 0 0 0 .75-.75V13.5a.75.75 0 0 0-.75-.75H6.75a.75.75 0 0 0-.75.75v3.75c0 .414.336.75.75.75Z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-800">تسجيل مطعم جديد في المنصة</h3>
                                <p class="text-sm text-gray-500">أدخل بيانات المطعم ومدير الحساب لإنشاء مطعم جديد.</p>
                            </div>
                        </div>

                        <form @submit.prevent="createRestaurant" class="space-y-8">
                            <!-- Restaurant Info Section -->
                            <fieldset>
                                <legend class="text-sm font-bold text-amber-700 border-b border-amber-100 pb-2 mb-4 w-full flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 0h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z" />
                                    </svg>
                                    بيانات المطعم
                                </legend>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                    <div>
                                        <label for="restaurant-name" class="block text-sm font-medium text-gray-700 mb-1">اسم المطعم <span class="text-red-500">*</span></label>
                                        <input id="restaurant-name" v-model="form.name" required
                                            class="w-full rounded-xl border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 transition"
                                            :class="{ 'border-red-400': fieldError('name') }"
                                            placeholder="مثال: مطعم الديوان" />
                                        <p v-if="fieldError('name')" class="text-xs text-red-500 mt-1">{{ fieldError('name') }}</p>
                                    </div>
                                    <div>
                                        <label for="restaurant-currency" class="block text-sm font-medium text-gray-700 mb-1">العملة (ISO) <span class="text-red-500">*</span></label>
                                        <select id="restaurant-currency" v-model="form.currency" required
                                            class="w-full rounded-xl border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 transition" dir="ltr">
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
                                        <label for="restaurant-phone" class="block text-sm font-medium text-gray-700 mb-1">رقم الهاتف</label>
                                        <input id="restaurant-phone" v-model="form.phone"
                                            class="w-full rounded-xl border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 transition" dir="ltr"
                                            placeholder="+966 5XXXXXXXX" />
                                    </div>
                                    <div>
                                        <label for="restaurant-address" class="block text-sm font-medium text-gray-700 mb-1">العنوان</label>
                                        <input id="restaurant-address" v-model="form.address"
                                            class="w-full rounded-xl border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 transition"
                                            placeholder="المدينة، الحي، الشارع" />
                                    </div>
                                </div>
                            </fieldset>

                            <!-- Manager Info Section -->
                            <fieldset>
                                <legend class="text-sm font-bold text-blue-700 border-b border-blue-100 pb-2 mb-4 w-full flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                    </svg>
                                    الحساب الإداري الأول (مدير المطعم)
                                </legend>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                    <div>
                                        <label for="manager-name" class="block text-sm font-medium text-gray-700 mb-1">اسم المدير <span class="text-red-500">*</span></label>
                                        <input id="manager-name" v-model="form.manager_name" required
                                            class="w-full rounded-xl border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 transition"
                                            :class="{ 'border-red-400': fieldError('manager_name') }"
                                            placeholder="الاسم الكامل" />
                                        <p v-if="fieldError('manager_name')" class="text-xs text-red-500 mt-1">{{ fieldError('manager_name') }}</p>
                                    </div>
                                    <div>
                                        <label for="manager-email" class="block text-sm font-medium text-gray-700 mb-1">البريد الإلكتروني <span class="text-red-500">*</span></label>
                                        <input id="manager-email" type="email" v-model="form.manager_email" required
                                            class="w-full rounded-xl border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 transition" dir="ltr"
                                            :class="{ 'border-red-400': fieldError('manager_email') }"
                                            placeholder="admin@restaurant.com" />
                                        <p v-if="fieldError('manager_email')" class="text-xs text-red-500 mt-1">{{ fieldError('manager_email') }}</p>
                                    </div>
                                    <div class="md:col-span-2">
                                        <label for="manager-password" class="block text-sm font-medium text-gray-700 mb-1">كلمة المرور</label>
                                        <div class="relative md:w-1/2">
                                            <input id="manager-password" :type="showPassword ? 'text' : 'password'" v-model="form.manager_password"
                                                class="w-full rounded-xl border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 pl-10 transition" dir="ltr"
                                                :class="{ 'border-red-400': fieldError('manager_password') }"
                                                placeholder="اتركها فارغة لتعيين &quot;password&quot; كافتراضي" />
                                            <button type="button" @click="showPassword = !showPassword"
                                                class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 hover:text-gray-600 transition">
                                                <svg v-if="!showPassword" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                                </svg>
                                                <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                                                </svg>
                                            </button>
                                        </div>
                                        <p v-if="fieldError('manager_password')" class="text-xs text-red-500 mt-1">{{ fieldError('manager_password') }}</p>
                                        <p class="text-xs text-gray-400 mt-1.5">إذا تركتها فارغة سيتم تعيين "password" ككلمة مرور افتراضية.</p>
                                    </div>
                                </div>
                            </fieldset>

                            <!-- Error Message -->
                            <Transition
                                enter-active-class="transition ease-out duration-200"
                                enter-from-class="opacity-0"
                                enter-to-class="opacity-100"
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

                            <!-- Submit -->
                            <div class="flex items-center gap-3 pt-2">
                                <button type="submit" :disabled="loading"
                                    class="inline-flex items-center gap-2 bg-amber-600 text-white px-6 py-2.5 rounded-xl shadow-sm hover:bg-amber-700 focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 disabled:opacity-50 transition font-medium">
                                    <svg v-if="loading" class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                    <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                    </svg>
                                    {{ loading ? 'جاري الإضافة...' : 'إضافة المطعم والمدير' }}
                                </button>
                                <Link :href="route('super_admin.dashboard')"
                                    class="text-sm text-gray-500 hover:text-gray-700 transition">
                                    إلغاء
                                </Link>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
