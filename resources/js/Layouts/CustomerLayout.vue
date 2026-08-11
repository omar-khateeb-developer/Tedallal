<script setup lang="ts">
defineProps<{
    title?: string;
}>();
</script>

<template>
    <div class="min-h-screen bg-stone-50 text-stone-900" dir="rtl">
        <header class="sticky top-0 z-20 border-b border-stone-200 bg-white/95 backdrop-blur">
            <div class="mx-auto flex max-w-lg items-center justify-between px-4 py-3">
                <div>
                    <p class="text-xs text-stone-500">تدلّل</p>
                    <slot name="title">
                        <h1 class="text-lg font-bold">{{ title }}</h1>
                    </slot>
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
