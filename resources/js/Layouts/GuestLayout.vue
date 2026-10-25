<script setup lang="ts">
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
import { useTheme } from '@/composables/useTheme';
import { onMounted } from 'vue';

const { isDark, toggleTheme, initTheme } = useTheme();

onMounted(() => {
    initTheme('dark');
});
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center bg-slate-50 dark:bg-[#060913] pt-6 sm:justify-center sm:pt-0 transition-colors duration-300 relative text-slate-900 dark:text-slate-100 px-4"
    >
        <!-- Theme Toggle Button in top corner -->
        <div class="absolute top-4 start-4 sm:top-6 sm:start-6 z-20">
            <button
                type="button"
                class="flex h-10 items-center gap-1.5 rounded-2xl px-3 text-xs font-bold transition-all duration-200 border active:scale-95 shadow-xs select-none bg-white/90 border-slate-200 text-slate-700 hover:bg-slate-100 dark:bg-slate-800/90 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-700 dark:hover:border-amber-500/40"
                :title="isDark ? 'التبديل إلى الألوان الفاتحة' : 'التبديل إلى الألوان الداكنة الفاخرة'"
                @click="toggleTheme"
            >
                <span v-if="isDark" class="flex items-center gap-1.5">
                    <svg class="h-4 w-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                    <span class="text-[11px] text-amber-300 font-bold">فاتح</span>
                </span>
                <span v-else class="flex items-center gap-1.5">
                    <svg class="h-4 w-4 text-slate-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                    </svg>
                    <span class="text-[11px] text-slate-700 font-bold">داكن</span>
                </span>
            </button>
        </div>

        <div>
            <Link href="/" class="flex flex-col items-center gap-2 group">
                <ApplicationLogo class="h-16 w-16 fill-current text-amber-500 transition-transform duration-300 group-hover:scale-105" />
                <span class="text-xl font-extrabold bg-gradient-to-l from-amber-500 to-orange-400 bg-clip-text text-transparent">تدلّل</span>
            </Link>
        </div>

        <div
            class="mt-6 w-full overflow-hidden bg-white dark:bg-[#0a0f1d] px-6 py-6 shadow-xl border border-slate-200/80 dark:border-slate-800/80 sm:max-w-md sm:rounded-2xl transition-colors"
        >
            <slot />
        </div>
    </div>
</template>
