<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps<{
    tables: Array<{ id: number; name: string; public_token: string; public_url?: string }>;
    restaurant: { name: string };
}>();
</script>

<template>
    <Head title="الطاولات" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">الطاولات ورموز QR</h2>
        </template>

        <div class="py-8" dir="rtl">
            <div class="mx-auto max-w-5xl px-4">
                <div class="mb-6 flex flex-wrap gap-3">
                    <a
                        :href="route('admin.tables.print')"
                        target="_blank"
                        class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white"
                    >
                        طباعة كل رموز QR
                    </a>
                </div>
            </div>
            <div class="mx-auto grid max-w-5xl gap-4 px-4 sm:grid-cols-2 lg:grid-cols-3">
                <article
                    v-for="table in tables"
                    :key="table.id"
                    class="rounded-2xl bg-white p-5 text-center shadow"
                >
                    <h3 class="text-lg font-bold">{{ table.name }}</h3>
                    <p class="mt-1 text-sm text-stone-500">{{ restaurant.name }}</p>
                    <img
                        :src="`/api/admin/tables/${table.id}/qr`"
                        :alt="`QR ${table.name}`"
                        class="mx-auto my-4 h-40 w-40"
                    />
                    <p class="text-xs text-stone-400 break-all">{{ table.public_url ?? `/t/${table.public_token}` }}</p>
                    <a
                        :href="`/api/admin/tables/${table.id}/qr`"
                        download
                        class="mt-4 inline-block rounded-lg bg-amber-600 px-4 py-2 text-sm text-white"
                    >
                        تحميل QR
                    </a>
                </article>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
