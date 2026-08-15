<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

const links = [
    { href: 'admin.dashboard', label: 'لوحة التحكم' },
    { href: 'admin.orders', label: 'الطلبات' },
    { href: 'admin.tables', label: 'الطاولات' },
    { href: 'admin.menu.categories', label: 'التصنيفات' },
    { href: 'admin.menu.items', label: 'الأصناف' },
    { href: 'admin.settings', label: 'الإعدادات' },
    { href: 'admin.users', label: 'المستخدمون' },
];
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
