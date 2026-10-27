<script setup lang="ts">
import AdminNav from '@/Components/Admin/AdminNav.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';

interface UserRow {
    id: number;
    name: string;
    email: string;
    role: string;
    default_window?: number | null;
    status: string;
    permissions?: string[];
}

const page = usePage();
const restaurant = computed(() => (page.props.auth as any)?.restaurant);
const windowsCount = computed(() => Math.max(1, (restaurant.value?.windows_count as number) || 4));

const users = ref<UserRow[]>([]);
const form = ref({
    id: null as number | null,
    name: '',
    email: '',
    password: '',
    role: 'cashier',
    default_window: 1 as number | null,
    status: 'active',
    permissions: [] as string[],
});

const message = ref('');
const errorMessage = ref('');
const showPassword = ref(false);
const showAdvancedPermissions = ref(false);

const logsModalOpen = ref(false);
const logsUser = ref<UserRow | null>(null);
const userLogs = ref<any[]>([]);

const rolesList = [
    { value: 'cashier', label: 'كاشير (محاسبة وفواتير)', icon: '💳', desc: 'استلام الدفع، البحث عن الطلبات، وطباعة الفواتير' },
    { value: 'chef', label: 'شيف / نافذة المطبخ', icon: '👨‍🍳', desc: 'تحضير الطلبات واستلامها على نافذة محددة' },
    { value: 'supervisor', label: 'مشرف تشغيل', icon: '👔', desc: 'متابعة الصالة والكاشير والمطبخ وتعديل الطلبات' },
    { value: 'waiter', label: 'ويتر / مضيف صالة', icon: '🍽️', desc: 'متابعة الطاولات وتقديم الوجبات الجاهزة' },
    { value: 'admin', label: 'مدير المطعم', icon: '👑', desc: 'صلاحيات كاملة للمنيو، الأسعار، الطاولات، والمستخدمين' },
];

const permissionsList = [
    { value: 'manage_orders', label: 'إدارة الطلبات' },
    { value: 'manage_menu', label: 'إدارة المنيو' },
    { value: 'manage_tables', label: 'إدارة الطاولات' },
];

function onRoleChange() {
    if (form.value.role === 'chef') {
        if (!form.value.default_window) {
            form.value.default_window = 1;
        }
    } else {
        form.value.default_window = null;
    }
}

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
        role: user.role || 'cashier',
        default_window: user.role === 'chef' ? (user.default_window || 1) : null,
        status: user.status || 'active',
        permissions: user.permissions || [],
    };
    message.value = '';
    errorMessage.value = '';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function cancelEdit() {
    form.value = {
        id: null,
        name: '',
        email: '',
        password: '',
        role: 'cashier',
        default_window: null,
        status: 'active',
        permissions: [],
    };
    message.value = '';
    errorMessage.value = '';
    showAdvancedPermissions.value = false;
}

async function saveUser() {
    message.value = '';
    errorMessage.value = '';

    // التحقق من تحديد النافذة إذا كان المستخدم شيف
    if (form.value.role === 'chef' && (!form.value.default_window || form.value.default_window < 1)) {
        errorMessage.value = 'يجب تحديد رقم النافذة التابع لها الشيف.';
        return;
    }

    try {
        if (form.value.id) {
            await axios.patch(`/api/admin/users/${form.value.id}`, form.value);
            message.value = 'تم تحديث بيانات المستخدم بنجاح';
        } else {
            await axios.post('/api/admin/users', form.value);
            message.value = 'تم إنشاء المستخدم بنجاح';
        }
        cancelEdit();
        await load();
    } catch (err: any) {
        errorMessage.value = err.response?.data?.message || 'تعذر الحفظ، يرجى التحقق من صحة البيانات (البريد قد يكون مستخدماً)';
    }
}

async function toggleStatus(user: UserRow) {
    const status = user.status === 'active' ? 'inactive' : 'active';
    await axios.patch(`/api/admin/users/${user.id}`, { status });
    await load();
}

async function deleteUser(user: UserRow) {
    if (!confirm(`هل أنت متأكد من حذف ${user.name}؟`)) return;
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

function getRoleInfo(role: string, defaultWindow?: number | null) {
    if (role === 'chef') {
        const win = defaultWindow ? `نافذة رقم ${defaultWindow}` : 'غير محدد';
        return {
            label: `شيف (${win})`,
            icon: '👨‍🍳',
            badgeClass: 'bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 border-blue-200/60 dark:border-blue-800/40',
        };
    }
    if (role === 'cashier') {
        return {
            label: 'كاشير',
            icon: '💳',
            badgeClass: 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border-emerald-200/60 dark:border-emerald-800/40',
        };
    }
    if (role === 'supervisor') {
        return {
            label: 'مشرف تشغيل',
            icon: '👔',
            badgeClass: 'bg-purple-50 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 border-purple-200/60 dark:border-purple-800/40',
        };
    }
    if (role === 'waiter') {
        return {
            label: 'ويتر / مضيف',
            icon: '🍽️',
            badgeClass: 'bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border-amber-200/60 dark:border-amber-800/40',
        };
    }
    if (role === 'admin') {
        return {
            label: 'مدير المطعم',
            icon: '👑',
            badgeClass: 'bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border-rose-200/60 dark:border-rose-800/40',
        };
    }
    return {
        label: 'موظف',
        icon: '👤',
        badgeClass: 'bg-stone-100 dark:bg-slate-800 text-stone-700 dark:text-stone-300 border-stone-200 dark:border-slate-700',
    };
}

onMounted(load);
</script>

<template>
    <Head title="المستخدمون" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800 dark:text-white">إدارة المستخدمين والموظفين</h2>
        </template>
        <div class="mx-auto max-w-4xl p-6" dir="rtl">
            <AdminNav />

            <!-- رسائل النجاح أو الخطأ -->
            <div v-if="message" class="mb-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 p-3 text-sm font-semibold text-emerald-700 dark:text-emerald-300 flex items-center gap-2">
                <span>✓</span> {{ message }}
            </div>
            <div v-if="errorMessage" class="mb-4 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 p-3 text-sm font-semibold text-rose-700 dark:text-rose-300 flex items-center gap-2">
                <span>⚠️</span> {{ errorMessage }}
            </div>

            <!-- نموذج إضافة / تعديل مستخدم -->
            <form class="mb-8 grid gap-4 rounded-2xl bg-white dark:bg-[#0a0f1d] p-6 shadow-xs border border-stone-200/80 dark:border-slate-800 sm:grid-cols-2" @submit.prevent="saveUser">
                <div class="sm:col-span-2 flex justify-between items-center border-b border-stone-100 dark:border-slate-800 pb-3" v-if="form.id">
                    <h3 class="font-bold text-amber-600 dark:text-amber-400">تعديل بيانات: {{ form.name }}</h3>
                    <button type="button" @click="cancelEdit" class="text-sm text-stone-500 dark:text-stone-400 hover:underline">إلغاء التعديل</button>
                </div>
                <div v-else class="sm:col-span-2 border-b border-stone-100 dark:border-slate-800 pb-3">
                    <h3 class="font-bold text-gray-900 dark:text-white text-base">إضافة مستخدم جديد لطاقم العمل</h3>
                    <p class="text-xs text-stone-500 dark:text-stone-400 mt-0.5">حدد الدور الوظيفي للموظف بدقة لإعطائه الواجهة والصلاحيات المناسبة تلقائياً.</p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-stone-600 dark:text-stone-400 mb-1.5">اسم الموظف *</label>
                    <input v-model="form.name" required class="w-full rounded-xl border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-stone-900 dark:text-white px-3.5 py-2.5 focus:border-amber-500 focus:ring-amber-500" placeholder="مثال: الشيف أحمد / الكاشير سارة" />
                </div>

                <div>
                    <label class="block text-xs font-semibold text-stone-600 dark:text-stone-400 mb-1.5">البريد الإلكتروني (لتسجيل الدخول) *</label>
                    <input v-model="form.email" required type="email" class="w-full rounded-xl border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-stone-900 dark:text-white px-3.5 py-2.5 focus:border-amber-500 focus:ring-amber-500" placeholder="user@tedallal.test" dir="ltr" />
                </div>

                <div>
                    <label class="block text-xs font-semibold text-stone-600 dark:text-stone-400 mb-1.5">كلمة المرور {{ form.id ? '(اتركها فارغة لعدم التغيير)' : '*' }}</label>
                    <div class="relative">
                        <input v-model="form.password" :required="!form.id" :type="showPassword ? 'text' : 'password'" class="w-full rounded-xl border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-stone-900 dark:text-white pl-10 pr-3.5 py-2.5 focus:border-amber-500 focus:ring-amber-500" placeholder="كلمة المرور" dir="ltr" />
                        <button type="button" @click="showPassword = !showPassword" class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500 hover:text-gray-700">
                            <svg v-if="!showPassword" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                            <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                        </button>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-stone-600 dark:text-stone-400 mb-1.5">حالة الحساب *</label>
                    <select v-model="form.status" class="w-full rounded-xl border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-stone-900 dark:text-white px-3.5 py-2.5 focus:border-amber-500 focus:ring-amber-500">
                        <option value="active">نشط (يمكنه الدخول)</option>
                        <option value="inactive">موقوف (معطّل)</option>
                    </select>
                </div>

                <!-- اختيار الدور الوظيفي -->
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-stone-600 dark:text-stone-400 mb-2">الدور الوظيفي في المطعم *</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2.5">
                        <label
                            v-for="role in rolesList"
                            :key="role.value"
                            class="flex flex-col p-3 rounded-xl border cursor-pointer transition-all select-none"
                            :class="form.role === role.value
                                ? 'bg-amber-50/70 dark:bg-amber-950/40 border-amber-500 ring-2 ring-amber-500/30'
                                : 'bg-white dark:bg-slate-900/60 border-stone-200 dark:border-slate-800 hover:border-amber-300'"
                        >
                            <div class="flex items-center gap-2">
                                <input
                                    type="radio"
                                    name="user_role"
                                    :value="role.value"
                                    v-model="form.role"
                                    @change="onRoleChange"
                                    class="text-amber-600 focus:ring-amber-500"
                                />
                                <span class="text-base">{{ role.icon }}</span>
                                <span class="font-bold text-sm text-stone-900 dark:text-white">{{ role.label }}</span>
                            </div>
                            <span class="text-[11px] text-stone-500 dark:text-stone-400 mt-1.5 pr-6 leading-tight">
                                {{ role.desc }}
                            </span>
                        </label>
                    </div>
                </div>

                <!-- حقل تحديد النافذة للشيف (إلزامي إذا كان شيف) -->
                <div v-if="form.role === 'chef'" class="sm:col-span-2 rounded-2xl bg-gradient-to-r from-blue-50 to-sky-50 dark:from-blue-950/40 dark:to-sky-950/30 border border-blue-200 dark:border-blue-800/60 p-4 transition-all">
                    <div class="flex items-start gap-3">
                        <div class="rounded-xl bg-blue-500 text-white p-2.5 text-xl shadow-xs shrink-0">
                            🪟
                        </div>
                        <div class="flex-1">
                            <h4 class="text-sm font-bold text-blue-950 dark:text-blue-200">
                                النافذة التابع لها الشيف (إلزامي):
                            </h4>
                            <p class="text-xs text-blue-800 dark:text-blue-300/80 mt-0.5">
                                حدد النافذة التي سيعمل عليها هذا الشيف. <strong class="underline">ملاحظة:</strong> يمكن لأكثر من شيف العمل على نفس النافذة في نفس الوقت.
                            </p>

                            <div class="mt-3 grid sm:grid-cols-2 gap-3 items-center">
                                <select
                                    v-model.number="form.default_window"
                                    required
                                    class="rounded-xl border-blue-300 dark:border-blue-700 bg-white dark:bg-slate-900 text-blue-950 dark:text-blue-100 px-4 py-2.5 font-bold focus:border-blue-500 focus:ring-blue-500 text-sm shadow-xs"
                                >
                                    <option v-for="w in windowsCount" :key="w" :value="w">
                                        نافذة رقم {{ w }}
                                    </option>
                                </select>
                                <span class="text-xs text-blue-700 dark:text-blue-300 font-medium">
                                    💡 عند تسجيل دخوله، ستُفتح له شاشة المطبخ محددة على نافذة رقم {{ form.default_window || 1 }} تلقائياً.
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- زر عرض الصلاحيات المتقدمة اختيارياً -->
                <div class="sm:col-span-2 pt-1">
                    <button
                        type="button"
                        @click="showAdvancedPermissions = !showAdvancedPermissions"
                        class="text-xs font-semibold text-stone-500 dark:text-stone-400 hover:text-amber-600 dark:hover:text-amber-400 flex items-center gap-1.5 transition-colors"
                    >
                        <span>{{ showAdvancedPermissions ? '▼ إخفاء الصلاحيات المتقدمة' : '◀ تخصيص صلاحيات إضافية (متقدم)' }}</span>
                    </button>

                    <div v-if="showAdvancedPermissions" class="mt-3 p-3.5 rounded-xl bg-stone-50 dark:bg-slate-900 border border-stone-200 dark:border-slate-800">
                        <p class="mb-2 text-xs font-semibold text-stone-700 dark:text-stone-300">
                            صلاحيات إضافية محددة يدوياً:
                        </p>
                        <div class="flex gap-4 flex-wrap">
                            <label v-for="perm in permissionsList" :key="perm.value" class="flex items-center gap-2 text-xs text-stone-700 dark:text-stone-300 cursor-pointer select-none">
                                <input type="checkbox" :value="perm.value" v-model="form.permissions" class="rounded border-stone-300 dark:border-slate-700 text-amber-600 focus:ring-amber-600">
                                {{ perm.label }}
                            </label>
                        </div>
                    </div>
                </div>

                <button type="submit" class="mt-2 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 py-3 text-white font-bold shadow-md active:scale-98 sm:col-span-2 transition-all">
                    {{ form.id ? 'حفظ التعديلات' : 'إضافة المستخدم لطاقم العمل' }}
                </button>
            </form>

            <!-- قائمة المستخدمين الحاليين -->
            <div class="rounded-2xl bg-white dark:bg-[#0a0f1d] p-5 shadow-xs border border-stone-200/80 dark:border-slate-800">
                <div class="flex items-center justify-between mb-4 border-b border-stone-100 dark:border-slate-800 pb-3">
                    <h3 class="font-bold text-gray-900 dark:text-white text-base">طاقم العمل والمستخدمون ({{ users.length }})</h3>
                    <span class="text-xs text-stone-500 dark:text-stone-400">إجمالي النوافذ المفعلة: {{ windowsCount }}</span>
                </div>

                <ul class="space-y-2.5">
                    <li
                        v-for="user in users"
                        :key="user.id"
                        class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-stone-50/70 dark:bg-slate-900/60 p-4 border border-stone-200/60 dark:border-slate-800 transition-colors"
                    >
                        <div class="flex items-center gap-3">
                            <div class="h-10 w-10 rounded-full flex items-center justify-center text-lg bg-white dark:bg-slate-800 shadow-xs border border-stone-200/60 dark:border-slate-700">
                                {{ getRoleInfo(user.role, user.default_window).icon }}
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <p class="font-bold text-gray-900 dark:text-white text-sm">{{ user.name }}</p>
                                    <span
                                        class="rounded-full px-2.5 py-0.5 text-xs font-bold border"
                                        :class="getRoleInfo(user.role, user.default_window).badgeClass"
                                    >
                                        {{ getRoleInfo(user.role, user.default_window).label }}
                                    </span>
                                </div>
                                <p class="text-xs text-stone-500 dark:text-stone-400 mt-0.5" dir="ltr">{{ user.email }}</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 text-sm">
                            <button
                                class="rounded-lg px-2.5 py-1 text-xs font-semibold transition-colors"
                                :class="user.status === 'active' ? 'bg-green-100 dark:bg-emerald-950/60 text-green-700 dark:text-emerald-300 border border-green-200/50 dark:border-emerald-800/40' : 'bg-red-100 dark:bg-red-950/60 text-red-700 dark:text-red-300 border border-red-200/50 dark:border-red-800/40'"
                                @click="toggleStatus(user)"
                            >
                                {{ user.status === 'active' ? 'نشط' : 'معطّل' }}
                            </button>
                            <button class="rounded-lg bg-white dark:bg-slate-800 text-stone-700 dark:text-stone-300 hover:bg-stone-200 dark:hover:bg-slate-700 px-2.5 py-1 text-xs font-semibold border border-stone-200 dark:border-slate-700 transition-colors" @click="showLogs(user)">السجل</button>
                            <button class="rounded-lg bg-blue-100 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 hover:bg-blue-200 dark:hover:bg-blue-900/50 px-2.5 py-1 text-xs font-semibold border border-blue-200 dark:border-blue-800/40 transition-colors" @click="editUser(user)">تعديل</button>
                            <button class="rounded-lg bg-red-100 dark:bg-red-950/60 text-red-700 dark:text-red-300 hover:bg-red-200 dark:hover:bg-red-900/50 px-2.5 py-1 text-xs font-semibold border border-red-200 dark:border-red-800/40 transition-colors" @click="deleteUser(user)">حذف</button>
                        </div>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Logs Modal -->
        <div v-if="logsModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4" dir="rtl">
            <div class="w-full max-w-md rounded-2xl bg-white dark:bg-[#0a0f1d] p-6 shadow-2xl border border-stone-200 dark:border-slate-800 text-stone-900 dark:text-stone-100">
                <div class="mb-4 flex items-center justify-between border-b border-stone-100 dark:border-slate-800 pb-3">
                    <h3 class="text-lg font-bold">سجل نشاطات: {{ logsUser?.name }}</h3>
                    <button @click="logsModalOpen = false" class="text-stone-400 hover:text-stone-700 dark:hover:text-stone-200 text-xl font-bold">✕</button>
                </div>
                
                <div class="max-h-[60vh] overflow-y-auto space-y-3 pr-2">
                    <div v-if="userLogs.length === 0" class="text-stone-500 dark:text-stone-400 text-sm py-4 text-center">لا توجد نشاطات مسجلة لهذا الموظف.</div>
                    <div v-for="log in userLogs" :key="log.id" class="border-b border-stone-100 dark:border-slate-800 pb-2 last:border-0">
                        <p class="text-sm font-medium">{{ log.description }}</p>
                        <p class="text-xs text-stone-400 mt-1" dir="ltr">{{ new Date(log.created_at).toLocaleString('ar-EG') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
