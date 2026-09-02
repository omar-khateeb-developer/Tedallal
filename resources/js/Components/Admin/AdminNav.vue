<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const userRole = computed(() => (page.props.auth as any).user.role);
const userPermissions = computed(() => (page.props.auth as any).user.permissions || []);
const isSuperAdmin = computed(() => userRole.value === 'super_admin');
const isAdmin = computed(() => userRole.value === 'admin');

const allLinks = [
    { href: 'admin.dashboard', label: 'لوحة التحكم', roles: ['admin'] },
    { href: 'admin.analytics', label: 'التحليلات (جديد)', roles: ['admin'] },
    { href: 'admin.offers', label: 'العروض والكوبونات (جديد)', roles: ['admin'] },
    { href: 'staff.orders', label: 'الطلبات', roles: ['staff'], permission: 'manage_orders' },
    { href: 'admin.orders', label: 'الطلبات', roles: ['admin'] },
    { href: 'admin.tables', label: 'الطاولات', roles: ['admin'], permission: 'manage_tables' },
    { href: 'admin.menu.categories', label: 'التصنيفات', roles: ['admin'], permission: 'manage_menu' },
    { href: 'admin.menu.items', label: 'الأصناف', roles: ['admin'], permission: 'manage_menu' },
    { href: 'admin.settings', label: 'الإعدادات', roles: ['admin'] },
    { href: 'admin.users', label: 'المستخدمون', roles: ['admin'] },
];

const links = computed(() => allLinks.filter(link => {
    if (isAdmin.value || isSuperAdmin.value) return link.roles.includes(userRole.value);
    if (link.roles.includes(userRole.value)) return true;
    if (link.permission && userPermissions.value.includes(link.permission)) return true;
    return false;
}));
</script>

<template>
    <div class="relative mb-8">
        <!-- Add a subtle fade on the left side to indicate scrollability -->
        <div class="pointer-events-none absolute left-0 top-0 bottom-0 w-8 bg-gradient-to-r from-white to-transparent z-10 rounded-l-2xl"></div>
        <nav class="flex gap-2 overflow-x-auto hide-scrollbar bg-white p-2 rounded-2xl shadow-sm border border-slate-100 items-center" dir="rtl">
            <Link
                v-for="link in links"
                :key="link.href"
                :href="route(link.href)"
                class="shrink-0 rounded-xl px-5 py-2.5 text-sm font-semibold transition-all duration-300"
                :class="route().current(link.href)
                    ? 'bg-gradient-to-r from-amber-500 to-orange-500 text-white shadow-md shadow-amber-500/30'
                    : 'text-slate-600 bg-transparent hover:bg-slate-50 hover:text-slate-900'"
            >
                {{ link.label }}
            </Link>
        </nav>
    </div>
</template>

<style scoped>
.hide-scrollbar::-webkit-scrollbar {
    display: none;
}
.hide-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
