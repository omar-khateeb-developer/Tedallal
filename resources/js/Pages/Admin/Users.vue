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
}

const users = ref<UserRow[]>([]);
const form = ref({ name: '', email: '', password: '', role: 'staff' });
const message = ref('');

async function load() {
    const { data } = await axios.get('/api/admin/users');
    users.value = data.data ?? [];
}

async function createUser() {
    message.value = '';
    try {
        await axios.post('/api/admin/users', form.value);
        form.value = { name: '', email: '', password: '', role: 'staff' };
        message.value = 'تم إنشاء المستخدم';
        await load();
    } catch {
        message.value = 'تعذر إنشاء المستخدم';
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

            <form class="mb-6 grid gap-2 rounded-xl bg-white p-4 shadow sm:grid-cols-2" @submit.prevent="createUser">
                <input v-model="form.name" required class="rounded-lg border-stone-300" placeholder="الاسم" />
                <input v-model="form.email" required type="email" class="rounded-lg border-stone-300" placeholder="البريد" dir="ltr" />
                <input v-model="form.password" required type="password" class="rounded-lg border-stone-300" placeholder="كلمة المرور" dir="ltr" />
                <select v-model="form.role" class="rounded-lg border-stone-300">
                    <option value="staff">موظف</option>
                    <option value="admin">مدير</option>
                </select>
                <button type="submit" class="rounded-lg bg-amber-600 py-2 text-white sm:col-span-2">إضافة مستخدم</button>
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
                        <button class="rounded-lg bg-red-100 px-2 py-1 text-red-700" @click="deleteUser(user)">حذف</button>
                    </div>
                </li>
            </ul>
        </div>
    </AuthenticatedLayout>
</template>
