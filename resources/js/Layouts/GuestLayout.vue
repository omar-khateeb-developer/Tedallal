<script setup lang="ts">
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
import { useTheme } from '@/composables/useTheme';
import { onMounted } from 'vue';

const { isDark, toggleTheme, initTheme } = useTheme();

onMounted(() => {
    initTheme();
});
</script>

<template>
    <div
        :class="[
            'flex min-h-screen flex-col items-center pt-8 sm:justify-center sm:pt-0 transition-colors duration-300 relative px-4 overflow-hidden',
            isDark ? 'bg-[#08080A] text-stone-100' : 'bg-[#FAF8F5] text-stone-900'
        ]"
    >
        <!-- Background Ambient Restaurant Wallpaper & Golden Radial Glows -->
        <div class="fixed inset-0 pointer-events-none z-0">
            <div
                class="absolute inset-0 opacity-[0.035] mix-blend-overlay"
                style="background-image: url('/images/luxury-restaurant-hero-bg.jpg'); background-size: cover; background-position: center;"
            ></div>
            <div
                class="absolute -top-32 left-1/2 -translate-x-1/2 w-[800px] h-[500px] rounded-full blur-3xl pointer-events-none"
                :class="isDark ? 'bg-[radial-gradient(ellipse_at_top,rgba(212,175,55,0.18)_0%,transparent_70%)]' : 'bg-[radial-gradient(ellipse_at_top,rgba(212,175,55,0.12)_0%,transparent_70%)]'"
            ></div>
        </div>

        <!-- Theme Toggle Button in top corner -->
        <div class="absolute top-4 start-4 sm:top-6 sm:start-6 z-20">
            <button
                type="button"
                class="flex h-10 items-center gap-1.5 rounded-2xl px-3 text-xs font-bold transition-all duration-200 border active:scale-95 shadow-sm select-none"
                :class="isDark ? 'bg-stone-900/90 border-stone-800 text-stone-200 hover:border-amber-500/40' : 'bg-white/90 border-stone-200 text-stone-700 hover:border-amber-400'"
                :title="isDark ? 'التبديل إلى الألوان الفاتحة' : 'التبديل إلى الألوان الداكنة الفاخرة'"
                @click="toggleTheme"
            >
                <span class="flex items-center gap-1.5">
                    <svg v-if="isDark" class="h-4 w-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                    <svg v-else class="h-4 w-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                    </svg>
                    <span class="text-[11px] font-bold" :class="isDark ? 'text-amber-300' : 'text-amber-700'">
                        {{ isDark ? 'فاتح' : 'داكن' }}
                    </span>
                </span>
            </button>
        </div>

        <!-- Brand Centerpiece: Animated Royal Tedallal Emblem -->
        <div class="relative z-10 mb-2">
            <Link href="/" class="flex flex-col items-center group">
                <div class="w-60 sm:w-72 h-auto filter drop-shadow-[0_12px_30px_rgba(212,175,55,0.3)] group-hover:scale-105 transition-transform duration-300">
                    <ApplicationLogo variant="full" :animated="true" />
                </div>
                <span class="text-[11px] font-bold tracking-wider -mt-1" :class="isDark ? 'text-amber-400/90' : 'text-amber-800'">
                    بوابة الإدارة والضيافة الملكية
                </span>
            </Link>
        </div>

        <!-- Glassmorphic Login Card -->
        <div
            class="relative z-10 w-full overflow-hidden backdrop-blur-2xl px-7 py-8 shadow-2xl border sm:max-w-md sm:rounded-3xl transition-all"
            :class="isDark ? 'bg-[#0C0C0E]/90 border-[rgba(226,196,133,0.3)] text-white shadow-[0_25px_60px_rgba(0,0,0,0.95)]' : 'bg-white/95 border-stone-200 text-stone-900 shadow-[0_20px_50px_rgba(180,140,60,0.12)]'"
        >
            <div class="absolute -top-12 -right-12 w-36 h-36 bg-amber-500/10 rounded-full blur-2xl pointer-events-none"></div>
            <slot />
        </div>
    </div>
</template>
