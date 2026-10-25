<script setup lang="ts">
import { useTheme } from '@/composables/useTheme';
import { onMounted } from 'vue';

defineProps<{
    title?: string;
    navbarImage?: string | null;
    logo?: string | null;
}>();

const { isDark, toggleTheme, initTheme } = useTheme();

onMounted(() => {
    initTheme('dark');
});
</script>

<template>
    <div class="min-h-screen bg-stone-50 text-stone-900 dark:bg-[#060913] dark:text-slate-100 transition-colors duration-300 font-sans" dir="rtl">
        <header class="sticky top-0 z-20 border-b border-stone-200/80 bg-white/95 dark:border-slate-800/80 dark:bg-[#0a0f1d]/90 backdrop-blur-md transition-colors duration-300">
            <div
                v-if="navbarImage"
                class="h-24 w-full bg-cover bg-center"
                :style="{ backgroundImage: `url(${navbarImage})` }"
            ></div>
            <div class="mx-auto flex max-w-lg items-center justify-between px-4 py-3">
                <div class="flex items-center gap-3 min-w-0">
                    <img v-if="logo" :src="logo" class="h-10 w-10 object-contain rounded-lg border border-stone-100 dark:border-slate-800 shrink-0" alt="Restaurant Logo" />
                    <div class="min-w-0">
                        <p class="text-xs font-semibold text-stone-500 dark:text-amber-400/90 tracking-wide">تدلّل</p>
                        <slot name="title">
                            <h1 class="text-lg font-bold text-stone-900 dark:text-white truncate">{{ title }}</h1>
                        </slot>
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <!-- زر تبديل المظهر الداكن / الفاتح -->
                    <button
                        type="button"
                        class="flex h-10 items-center gap-1.5 rounded-xl px-2.5 text-xs font-semibold transition-all duration-200 border active:scale-95 shadow-2xs select-none bg-stone-100/90 border-stone-200 text-stone-700 hover:bg-stone-200 dark:bg-slate-800/90 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-700 dark:hover:border-amber-500/40"
                        :title="isDark ? 'التبديل إلى الألوان الفاتحة' : 'التبديل إلى الألوان الداكنة الفاخرة'"
                        @click="toggleTheme"
                    >
                        <span v-if="isDark" class="flex items-center gap-1.5">
                            <svg class="h-4 w-4 text-amber-400 transition-transform duration-300 rotate-0 hover:rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                            <span class="text-[11px] text-amber-300/90 font-bold hidden sm:inline">فاتح</span>
                        </span>
                        <span v-else class="flex items-center gap-1.5">
                            <svg class="h-4 w-4 text-slate-700 transition-transform duration-300 hover:-rotate-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                            </svg>
                            <span class="text-[11px] text-stone-700 font-bold hidden sm:inline">داكن</span>
                        </span>
                    </button>

                    <slot name="actions" />
                </div>
            </div>
        </header>
        <main class="mx-auto max-w-lg px-4 pb-28 pt-4">
            <slot />
        </main>
        <slot name="footer" />
    </div>
</template>
