<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import axios from 'axios';

interface RestaurantRow {
    id: number;
    name: string;
}

interface UserRow {
    id: number;
    name: string;
    email: string;
    role: string;
    status: string;
    restaurant_id: number | null;
    restaurant?: { id: number; name: string };
    permissions?: string[];
}

const users = ref<UserRow[]>([]);
const restaurants = ref<RestaurantRow[]>([]);
const form = ref({ id: null as number | null, name: '', email: '', password: '', role: 'staff', status: 'active', restaurant_id: null as number | null, permissions: [] as string[] });
const message = ref('');
const showPassword = ref(false);

const logsModalOpen = ref(false);
const logsUser = ref<UserRow | null>(null);
const userLogs = ref<any[]>([]);

const permissionsList = [
    { value: 'manage_orders', label: 'إدارة الطلبات' },
    { value: 'manage_menu', label: 'إدارة المنيو' },
    { value: 'manage_tables', label: 'إدارة الطاولات' },
];

async function load() {
    const [usersRes, restRes] = await Promise.all([
        axios.get('/api/super-admin/users'),
        axios.get('/api/super-admin/restaurants')
    ]);
    users.value = usersRes.data.data ?? [];
    restaurants.value = restRes.data.data ?? [];
}

function editUser(user: UserRow) {
    form.value = {
        id: user.id,
        name: user.name,
        email: user.email,
        password: '',
        role: user.role,
        status: user.status || 'active',
        restaurant_id: user.restaurant_id,
        permissions: user.permissions || [],
    };
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function cancelEdit() {
    form.value = { id: null, name: '', email: '', password: '', role: 'staff', status: 'active', restaurant_id: null, permissions: [] };
}

async function saveUser() {
    message.value = '';
    try {
        if (form.value.id) {
            await axios.patch(`/api/super-admin/users/${form.value.id}`, form.value);
            message.value = 'تم تحديث المستخدم بنجاح';
        } else {
            await axios.post('/api/super-admin/users', form.value);
            message.value = 'تم إنشاء المستخدم بنجاح';
        }
        cancelEdit();
        await load();
    } catch (e: any) {
        message.value = e.response?.data?.message || 'تعذر الحفظ، يرجى التحقق من صحة البيانات';
    }
}

async function toggleStatus(user: UserRow) {
    const status = user.status === 'active' ? 'inactive' : 'active';
    await axios.patch(`/api/super-admin/users/${user.id}`, { status });
    await load();
}

async function deleteUser(user: UserRow) {
    if (!confirm(`حذف ${user.name}؟`)) return;
    try {
        await axios.delete(`/api/super-admin/users/${user.id}`);
        await load();
    } catch {
        alert('لا يمكن حذف هذا المستخدم');
    }
}

async function showLogs(user: UserRow) {
    logsUser.value = user;
    logsModalOpen.value = true;
    userLogs.value = [];
    try {
        const { data } = await axios.get(`/api/super-admin/users/${user.id}/logs`);
        userLogs.value = data.data ?? [];
    } catch {
        alert('تعذر جلب السجلات');
    }
}

onMounted(load);
</script>

<template>
    <Head title="المستخدمون - مدير النظام" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-bold text-stone-900 dark:text-white">إدارة كافة المستخدمين</h2>
        </template>
        <div class="mx-auto max-w-5xl p-4 sm:p-6" dir="rtl">
            <div class="mb-4 flex items-center justify-between">
                <Link :href="route('super_admin.dashboard')" class="inline-flex items-center gap-1.5 text-sm font-semibold text-amber-600 dark:text-amber-400 hover:underline">
                    <span>←</span>
                    <span>العودة للوحة التحكم</span>
                </Link>
                <span class="text-xs font-semibold px-2.5 py-1 rounded-xl bg-stone-100 dark:bg-slate-800 text-stone-600 dark:text-stone-300 border border-stone-200 dark:border-slate-700">
                    إجمالي المستخدمين: {{ users.length }}
                </span>
            </div>
            <p v-if="message" class="mb-4 text-sm font-semibold text-emerald-800 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800/80 p-3.5 rounded-xl shadow-2xs flex items-center gap-2">
                <span>✓</span>
                <span>{{ message }}</span>
            </p>

            <form class="mb-6 grid gap-3 rounded-2xl bg-white dark:bg-[#0a0f1d] border border-stone-200 dark:border-slate-800 p-5 shadow-xs sm:grid-cols-2" @submit.prevent="saveUser">
                <div class="sm:col-span-2 flex justify-between items-center mb-1 pb-2 border-b border-stone-100 dark:border-slate-800" v-if="form.id">
                    <h3 class="font-bold text-amber-600 dark:text-amber-400 text-sm flex items-center gap-1.5">
                        <span>✏️</span>
                        <span>تعديل المستخدم: {{ form.name }}</span>
                    </h3>
                    <button type="button" @click="cancelEdit" class="text-xs text-stone-500 hover:text-stone-700 dark:text-slate-400 dark:hover:text-slate-200 underline font-semibold">إلغاء التعديل</button>
                </div>

                <div class="space-y-1">
                    <label class="block text-xs font-bold text-stone-700 dark:text-stone-300">الاسم الكامل</label>
                    <input v-model="form.name" required class="w-full rounded-xl border-stone-300 dark:border-slate-700 bg-stone-50 dark:bg-slate-900 text-stone-900 dark:text-white placeholder:text-stone-400 focus:border-amber-500 focus:ring-amber-500 text-sm py-2 px-3" placeholder="أدخل اسم المستخدم" />
                </div>

                <div class="space-y-1">
                    <label class="block text-xs font-bold text-stone-700 dark:text-stone-300">البريد الإلكتروني</label>
                    <input v-model="form.email" required type="email" class="w-full rounded-xl border-stone-300 dark:border-slate-700 bg-stone-50 dark:bg-slate-900 text-stone-900 dark:text-white placeholder:text-stone-400 focus:border-amber-500 focus:ring-amber-500 text-sm py-2 px-3 font-mono" placeholder="user@example.com" dir="ltr" />
                </div>

                <div class="space-y-1 sm:col-span-2">
                    <label class="block text-xs font-bold text-stone-700 dark:text-stone-300">كلمة المرور</label>
                    <div class="relative">
                        <input v-model="form.password" :required="!form.id" :type="showPassword ? 'text' : 'password'" class="w-full rounded-xl border-stone-300 dark:border-slate-700 bg-stone-50 dark:bg-slate-900 text-stone-900 dark:text-white placeholder:text-stone-400 focus:border-amber-500 focus:ring-amber-500 text-sm py-2 pl-10 pr-3 font-mono" :placeholder="form.id ? 'اتركها فارغة إذا كنت لا ترغب بتغييرها' : 'كلمة المرور للدخول'" dir="ltr" />
                        <button type="button" @click="showPassword = !showPassword" class="absolute inset-y-0 left-0 flex items-center pl-3 text-stone-400 hover:text-stone-600 dark:hover:text-stone-200">
                            <svg v-if="!showPassword" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                            <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                        </button>
                    </div>
                </div>
                
                <div class="space-y-1">
                    <label class="block text-xs font-bold text-stone-700 dark:text-stone-300">الدور الوظيفي</label>
                    <select v-model="form.role" class="w-full rounded-xl border-stone-300 dark:border-slate-700 bg-stone-50 dark:bg-slate-900 text-stone-900 dark:text-white text-sm py-2 px-3 focus:border-amber-500 focus:ring-amber-500">
                        <option value="staff">موظف</option>
                        <option value="admin">مدير مطعم</option>
                        <option value="super_admin">مدير النظام (Super Admin)</option>
                    </select>
                </div>

                <div class="space-y-1">
                    <label class="block text-xs font-bold text-stone-700 dark:text-stone-300">الحالة</label>
                    <select v-model="form.status" class="w-full rounded-xl border-stone-300 dark:border-slate-700 bg-stone-50 dark:bg-slate-900 text-stone-900 dark:text-white text-sm py-2 px-3 focus:border-amber-500 focus:ring-amber-500">
                        <option value="active">نشط</option>
                        <option value="inactive">موقوف</option>
                    </select>
                </div>

                <div v-if="form.role !== 'super_admin'" class="space-y-1 sm:col-span-2">
                    <label class="block text-xs font-bold text-stone-700 dark:text-stone-300">المطعم التابع له</label>
                    <select v-model="form.restaurant_id" required class="w-full rounded-xl border-stone-300 dark:border-slate-700 bg-stone-50 dark:bg-slate-900 text-stone-900 dark:text-white text-sm py-2 px-3 focus:border-amber-500 focus:ring-amber-500">
                        <option :value="null" disabled>-- اختر المطعم --</option>
                        <option v-for="rest in restaurants" :key="rest.id" :value="rest.id">{{ rest.name }}</option>
                    </select>
                </div>

                <div v-if="form.role === 'staff'" class="sm:col-span-2 mt-2 border-t border-stone-200 dark:border-slate-800 pt-3">
                    <p class="mb-2 text-xs font-bold text-stone-800 dark:text-stone-200">صلاحيات الموظف:</p>
                    <div class="flex gap-4 flex-wrap">
                        <label v-for="perm in permissionsList" :key="perm.value" class="flex items-center gap-2 text-xs font-medium text-stone-700 dark:text-stone-300 cursor-pointer select-none">
                            <input type="checkbox" :value="perm.value" v-model="form.permissions" class="rounded border-stone-300 dark:border-slate-700 text-amber-600 focus:ring-amber-600 bg-stone-50 dark:bg-slate-900">
                            {{ perm.label }}
                        </label>
                    </div>
                </div>

                <button type="submit" class="mt-2 rounded-xl bg-amber-600 hover:bg-amber-500 py-2.5 text-white font-bold text-sm transition sm:col-span-2 shadow-xs flex items-center justify-center gap-2">
                    <span>{{ form.id ? '💾 حفظ التعديلات' : '➕ إضافة مستخدم' }}</span>
                </button>
            </form>

            <div class="space-y-2.5">
                <div
                    v-for="user in users"
                    :key="user.id"
                    class="flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-white dark:bg-[#0a0f1d] border border-stone-200 dark:border-slate-800/80 p-4 shadow-xs transition hover:border-amber-500/40"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="h-10 w-10 rounded-xl flex items-center justify-center font-bold text-sm shrink-0"
                            :class="user.role === 'super_admin' ? 'bg-purple-100 dark:bg-purple-950/80 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800/80' :
                                    user.role === 'admin' ? 'bg-amber-100 dark:bg-amber-950/80 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800/80' :
                                    'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700'"
                        >
                            {{ user.name ? user.name.charAt(0) : 'U' }}
                        </div>
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="font-bold text-stone-900 dark:text-white text-sm">{{ user.name }}</p>
                                <span v-if="user.restaurant" class="text-xs bg-stone-100 dark:bg-slate-800 text-stone-700 dark:text-stone-300 border border-stone-200 dark:border-slate-700 px-2 py-0.5 rounded-lg font-medium">
                                    {{ user.restaurant.name }}
                                </span>
                            </div>
                            <p class="text-xs text-stone-500 dark:text-slate-400 font-mono mt-0.5" dir="ltr">{{ user.email }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 text-xs font-semibold flex-wrap">
                        <span
                            class="rounded-xl px-2.5 py-1"
                            :class="user.role === 'super_admin' ? 'bg-purple-100 dark:bg-purple-950/80 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800/80' :
                                    user.role === 'admin' ? 'bg-amber-100 dark:bg-amber-950/80 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800/80' :
                                    'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700'"
                        >
                            {{ user.role === 'super_admin' ? 'مدير النظام' : (user.role === 'admin' ? 'مدير مطعم' : 'موظف') }}
                        </span>
                        
                        <button
                            class="rounded-xl px-2.5 py-1 transition flex items-center gap-1.5"
                            :class="user.status === 'active' 
                                ? 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800 hover:bg-emerald-200 dark:hover:bg-emerald-900/60' 
                                : 'bg-rose-100 dark:bg-rose-950/60 text-rose-800 dark:text-rose-300 border border-rose-300 dark:border-rose-800 hover:bg-rose-200 dark:hover:bg-rose-900/60'"
                            @click="toggleStatus(user)"
                            :title="user.status === 'active' ? 'انقر للتعطيل' : 'انقر للتفعيل'"
                        >
                            <span class="h-1.5 w-1.5 rounded-full" :class="user.status === 'active' ? 'bg-emerald-500 animate-pulse' : 'bg-rose-500'"></span>
                            {{ user.status === 'active' ? 'نشط' : 'معطّل' }}
                        </button>
                        
                        <button class="rounded-xl bg-stone-100 dark:bg-slate-800 text-stone-700 dark:text-stone-300 border border-stone-200 dark:border-slate-700 px-2.5 py-1 hover:bg-stone-200 dark:hover:bg-slate-700 transition" @click="showLogs(user)">السجل</button>
                        <button class="rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800/60 px-2.5 py-1 hover:bg-blue-100 dark:hover:bg-blue-900/60 transition" @click="editUser(user)">تعديل</button>
                        <button class="rounded-xl bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800/60 px-2.5 py-1 hover:bg-rose-100 dark:hover:bg-rose-900/60 transition" @click="deleteUser(user)">حذف</button>
                    </div>
                </div>
            </div>
        </div>
        <!-- Logs Modal -->
        <div v-if="logsModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4" dir="rtl">
            <div class="w-full max-w-md rounded-2xl bg-white dark:bg-[#0a0f1d] border border-stone-200 dark:border-slate-800 p-6 shadow-2xl">
                <div class="mb-4 flex items-center justify-between border-b border-stone-100 dark:border-slate-800 pb-3">
                    <h3 class="text-base font-bold text-stone-900 dark:text-white flex items-center gap-2">
                        <span>📋</span>
                        <span>سجل نشاطات: {{ logsUser?.name }}</span>
                    </h3>
                    <button @click="logsModalOpen = false" class="text-stone-400 hover:text-stone-700 dark:hover:text-stone-200 text-xl font-bold p-1">✕</button>
                </div>
                
                <div class="max-h-[60vh] overflow-y-auto space-y-3 pr-1 divide-y divide-stone-100 dark:divide-slate-800/60">
                    <div v-if="userLogs.length === 0" class="text-stone-500 dark:text-slate-400 text-sm py-8 text-center">لا توجد نشاطات مسجلة لهذا المستخدم.</div>
                    <div v-for="log in userLogs" :key="log.id" class="pt-2.5 first:pt-0">
                        <p class="text-sm font-medium text-stone-800 dark:text-stone-200">{{ log.description }}</p>
                        <p class="text-xs text-stone-400 dark:text-slate-500 mt-1 font-mono" dir="ltr">{{ new Date(log.created_at).toLocaleString('ar-EG') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
