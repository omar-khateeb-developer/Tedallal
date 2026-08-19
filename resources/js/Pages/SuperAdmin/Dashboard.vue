<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import axios from 'axios';

const restaurants = ref<any[]>([]);
const stats = ref({
    total_restaurants: 0,
    active_restaurants: 0,
    total_users: 0,
});
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
    try {
        await axios.post('/api/super-admin/restaurants', form.value);
        message.value = 'تم إضافة المطعم والمدير بنجاح!';
        form.value = {
            name: '', phone: '', address: '', currency: 'SAR',
            manager_name: '', manager_email: '', manager_password: ''
        };
        await fetchStats();
    } catch (e: any) {
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
    <Head title="إدارة المطاعم - مدير النظام" />

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
                            <div class="bg-blue-50 p-6 rounded-2xl border border-blue-100 flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-blue-800 font-semibold mb-1">إجمالي المستخدمين</p>
                                    <p class="text-3xl font-bold text-blue-600">{{ stats.total_users }}</p>
                                </div>
                                <div class="bg-blue-200 text-blue-700 p-3 rounded-full">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" /></svg>
                                </div>
                            </div>
                        </div>
                        <div class="mb-8 rounded-2xl bg-stone-50 p-6 shadow-sm border border-stone-200">
                            <h3 class="text-lg font-bold mb-4 text-amber-700">إضافة مطعم جديد</h3>
                            <form @submit.prevent="createRestaurant" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="col-span-1 md:col-span-2 border-b pb-2 mb-2"><h4 class="font-semibold">بيانات المطعم</h4></div>
                                <div><label class="block text-sm">اسم المطعم</label><input v-model="form.name" required class="w-full rounded border-gray-300" /></div>
                                <div><label class="block text-sm">العملة (ISO)</label><input v-model="form.currency" required class="w-full rounded border-gray-300" dir="ltr" /></div>
                                <div><label class="block text-sm">رقم الهاتف</label><input v-model="form.phone" class="w-full rounded border-gray-300" dir="ltr" /></div>
                                <div><label class="block text-sm">العنوان</label><input v-model="form.address" class="w-full rounded border-gray-300" /></div>
                                
                                <div class="col-span-1 md:col-span-2 border-b pb-2 mb-2 mt-4"><h4 class="font-semibold">بيانات الحساب الإداري الأول (مدير المطعم)</h4></div>
                                <div><label class="block text-sm">اسم المدير</label><input v-model="form.manager_name" required class="w-full rounded border-gray-300" /></div>
                                <div><label class="block text-sm">البريد الإلكتروني</label><input type="email" v-model="form.manager_email" required class="w-full rounded border-gray-300" dir="ltr" /></div>
                                <div class="col-span-1 md:col-span-2">
                                    <label class="block text-sm">كلمة المرور (اتركها فارغة لتعيين "password" كافتراضي)</label>
                                    <div class="relative md:w-1/2">
                                        <input :type="showPassword ? 'text' : 'password'" v-model="form.manager_password" class="w-full rounded border-gray-300 pl-10" dir="ltr" />
                                        <button type="button" @click="showPassword = !showPassword" class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500 hover:text-gray-700">
                                            <svg v-if="!showPassword" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                                            <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="col-span-1 md:col-span-2 mt-4">
                                    <button type="submit" :disabled="loading" class="bg-amber-600 text-white px-6 py-2 rounded shadow hover:bg-amber-700 disabled:opacity-50">
                                        {{ loading ? 'جاري الإضافة...' : 'إضافة المطعم والمدير' }}
                                    </button>
                                    <p v-if="message" class="mt-2 text-sm" :class="message.includes('نجاح') ? 'text-green-600' : 'text-red-600'">{{ message }}</p>
                                </div>
                            </form>
                        </div>

                        <h3 class="text-lg font-bold mb-4">قائمة المطاعم في النظام</h3>
                        <table class="w-full text-right border-collapse">
                            <thead>
                                <tr class="bg-gray-100">
                                    <th class="p-3 border">الاسم</th>
                                    <th class="p-3 border">الحالة</th>
                                    <th class="p-3 border">ميزة التحليلات</th>
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
                                        <div class="flex items-center gap-2">
                                            <button @click="toggleFeature(restaurant, 'analytics')"
                                                :class="restaurant.features?.analytics ? 'bg-amber-100 text-amber-700 border-amber-200' : 'bg-stone-100 text-stone-500 border-stone-200'"
                                                class="px-3 py-1.5 rounded-lg border text-xs font-bold transition-all hover:scale-105">
                                                {{ restaurant.features?.analytics ? 'التحليلات ✓' : 'التحليلات ✗' }}
                                            </button>
                                            <button @click="toggleFeature(restaurant, 'offers')"
                                                :class="restaurant.features?.offers ? 'bg-blue-100 text-blue-700 border-blue-200' : 'bg-stone-100 text-stone-500 border-stone-200'"
                                                class="px-3 py-1.5 rounded-lg border text-xs font-bold transition-all hover:scale-105">
                                                {{ restaurant.features?.offers ? 'العروض ✓' : 'العروض ✗' }}
                                            </button>
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
    </AuthenticatedLayout>
</template>
