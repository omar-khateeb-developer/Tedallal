<script setup lang="ts">
import AdminNav from '@/Components/Admin/AdminNav.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import axios from 'axios';

interface UserRow {
    id: number;
    name: string;
    email: string;
    role: string;
    status: string;
    permissions?: string[];
}

const users = ref<UserRow[]>([]);
const form = ref({ id: null as number | null, name: '', email: '', password: '', role: 'staff', permissions: [] as string[] });
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
    const { data } = await axios.get('/api/admin/users');
    users.value = data.data ?? [];
}

function editUser(user: UserRow) {
    form.value = {
        id: user.id,
        name: user.name,
        email: user.email,
        password: '',
        role: user.role,
        permissions: user.permissions || [],
    };
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function cancelEdit() {
    form.value = { id: null, name: '', email: '', password: '', role: 'staff', permissions: [] };
}

async function saveUser() {
    message.value = '';
    try {
        if (form.value.id) {
            await axios.patch(`/api/admin/users/${form.value.id}`, form.value);
            message.value = 'تم تحديث المستخدم بنجاح';
        } else {
            await axios.post('/api/admin/users', form.value);
            message.value = 'تم إنشاء المستخدم بنجاح';
        }
        cancelEdit();
        await load();
    } catch {
        message.value = 'تعذر الحفظ، يرجى التحقق من صحة البيانات (البريد قد يكون مستخدماً)';
    }
}

async function toggleStatus(user: UserRow) {
    const status = user.status === 'active' ? 'inactive' : 'active';
    await axios.patch(`/api/admin/users/${user.id}`, { status });
    await load();
}

async function deleteUser(user: UserRow) {
    if (!confirm(`حذف ${user.name}؟`)) return;
    try {
        await axios.delete(`/api/admin/users/${user.id}`);
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
        const { data } = await axios.get(`/api/admin/users/${user.id}/logs`);
        userLogs.value = data.data ?? [];
    } catch {
        alert('تعذر جلب السجلات');
    }
}

onMounted(load);
</script>

<template>
    <Head title="المستخدمون" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">إدارة المستخدمين</h2>
        </template>
        <div class="mx-auto max-w-3xl p-6" dir="rtl">
            <AdminNav />
            <p v-if="message" class="mb-4 text-sm text-green-600">{{ message }}</p>

            <form class="mb-6 grid gap-2 rounded-xl bg-white p-4 shadow sm:grid-cols-2" @submit.prevent="saveUser">
                <div class="sm:col-span-2 flex justify-between items-center mb-2" v-if="form.id">
                    <h3 class="font-bold text-amber-600">تعديل المستخدم: {{ form.name }}</h3>
                    <button type="button" @click="cancelEdit" class="text-sm text-stone-500 underline">إلغاء التعديل</button>
                </div>
                <input v-model="form.name" required class="rounded-lg border-stone-300" placeholder="الاسم" />
                <input v-model="form.email" required type="email" class="rounded-lg border-stone-300" placeholder="البريد" dir="ltr" />
                <div class="relative">
                    <input v-model="form.password" :required="!form.id" :type="showPassword ? 'text' : 'password'" class="w-full rounded-lg border-stone-300 pl-10" :placeholder="form.id ? 'كلمة المرور (اتركها فارغة لعدم التغيير)' : 'كلمة المرور'" dir="ltr" />
                    <button type="button" @click="showPassword = !showPassword" class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500 hover:text-gray-700">
                        <svg v-if="!showPassword" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                        <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                    </button>
                </div>
                <select v-model="form.role" class="rounded-lg border-stone-300">
                    <option value="staff">موظف</option>
                    <option value="admin">مدير</option>
                </select>

                <div v-if="form.role === 'staff'" class="sm:col-span-2 mt-2 border-t pt-3">
                    <p class="mb-2 text-sm font-semibold">صلاحيات الموظف:</p>
                    <div class="flex gap-4 flex-wrap">
                        <label v-for="perm in permissionsList" :key="perm.value" class="flex items-center gap-2 text-sm cursor-pointer select-none">
                            <input type="checkbox" :value="perm.value" v-model="form.permissions" class="rounded border-stone-300 text-amber-600 focus:ring-amber-600">
                            {{ perm.label }}
                        </label>
                    </div>
                </div>

                <button type="submit" class="mt-2 rounded-lg bg-amber-600 py-2 text-white sm:col-span-2">
                    {{ form.id ? 'حفظ التعديلات' : 'إضافة مستخدم' }}
                </button>
            </form>

            <ul class="space-y-2">
                <li
                    v-for="user in users"
                    :key="user.id"
                    class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-white p-4 shadow"
                >
                    <div>
                        <p class="font-medium">{{ user.name }}</p>
                        <p class="text-sm text-stone-500" dir="ltr">{{ user.email }}</p>
                    </div>
                    <div class="flex items-center gap-2 text-sm">
                        <span class="rounded-full bg-stone-100 px-2 py-1">{{ user.role === 'admin' ? 'مدير' : 'موظف' }}</span>
                        <button
                            class="rounded-lg px-2 py-1"
                            :class="user.status === 'active' ? 'bg-green-100' : 'bg-red-100'"
                            @click="toggleStatus(user)"
                        >
                            {{ user.status === 'active' ? 'نشط' : 'معطّل' }}
                        </button>
                        <button class="rounded-lg bg-stone-100 px-2 py-1 text-stone-700" @click="showLogs(user)">السجل</button>
                        <button class="rounded-lg bg-blue-100 px-2 py-1 text-blue-700" @click="editUser(user)">تعديل</button>
                        <button class="rounded-lg bg-red-100 px-2 py-1 text-red-700" @click="deleteUser(user)">حذف</button>
                    </div>
                </li>
            </ul>
        </div>
        <!-- Logs Modal -->
        <div v-if="logsModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" dir="rtl">
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-bold">سجل نشاطات: {{ logsUser?.name }}</h3>
                    <button @click="logsModalOpen = false" class="text-stone-500 hover:text-stone-700 text-xl font-bold">✕</button>
                </div>
                
                <div class="max-h-[60vh] overflow-y-auto space-y-3 pr-2">
                    <div v-if="userLogs.length === 0" class="text-stone-500 text-sm py-4 text-center">لا توجد نشاطات مسجلة لهذا الموظف.</div>
                    <div v-for="log in userLogs" :key="log.id" class="border-b border-stone-100 pb-2 last:border-0">
                        <p class="text-sm font-medium">{{ log.description }}</p>
                        <p class="text-xs text-stone-400 mt-1" dir="ltr">{{ new Date(log.created_at).toLocaleString('ar-EG') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
