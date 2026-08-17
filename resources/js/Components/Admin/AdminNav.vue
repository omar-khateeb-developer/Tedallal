<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const userRole = computed(() => (page.props.auth as any).user.role);

const allLinks = [
    { href: 'admin.dashboard', label: 'لوحة التحكم', roles: ['admin'] },
    { href: 'staff.orders', label: 'الطلبات', roles: ['staff'] },
    { href: 'admin.orders', label: 'الطلبات', roles: ['admin'] },
    { href: 'admin.tables', label: 'الطاولات', roles: ['admin'] },
    { href: 'admin.menu.categories', label: 'التصنيفات', roles: ['admin', 'staff'] },
    { href: 'admin.menu.items', label: 'الأصناف', roles: ['admin', 'staff'] },
    { href: 'admin.settings', label: 'الإعدادات', roles: ['admin'] },
    { href: 'admin.users', label: 'المستخدمون', roles: ['admin'] },
];

const links = computed(() => allLinks.filter(link => link.roles.includes(userRole.value)));
</script>

<template>
    <nav class="mb-6 flex flex-wrap gap-2" dir="rtl">
        <Link
            v-for="link in links"
            :key="link.href"
            :href="route(link.href)"
            class="rounded-lg px-4 py-2 text-sm font-medium transition"
            :class="route().current(link.href)
                ? 'bg-amber-600 text-white'
                : 'bg-stone-800 text-white hover:bg-stone-700'"
        >
            {{ link.label }}
        </Link>
    </nav>
</template>
