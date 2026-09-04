<script setup lang="ts">
defineProps<{
    title?: string;
    navbarImage?: string | null;
    logo?: string | null;
}>();
</script>

<template>
    <div class="min-h-screen bg-stone-50 text-stone-900" dir="rtl">
        <header class="sticky top-0 z-20 border-b border-stone-200 bg-white/95 backdrop-blur">
            <div
                v-if="navbarImage"
                class="h-24 w-full bg-cover bg-center"
                :style="{ backgroundImage: `url(${navbarImage})` }"
            ></div>
            <div class="mx-auto flex max-w-lg items-center justify-between px-4 py-3">
                <div class="flex items-center gap-3">
                    <img v-if="logo" :src="logo" class="h-10 w-10 object-contain rounded-lg border border-stone-100" alt="Restaurant Logo" />
                    <div>
                        <p class="text-xs text-stone-500">تدلّل</p>
                        <slot name="title">
                            <h1 class="text-lg font-bold">{{ title }}</h1>
                        </slot>
                    </div>
                </div>
                <slot name="actions" />
            </div>
        </header>
        <main class="mx-auto max-w-lg px-4 pb-28 pt-4">
            <slot />
        </main>
        <slot name="footer" />
    </div>
</template>
