<script setup lang="ts">
import { ref } from 'vue';
import { usePwa } from '@/composables/usePwa';
import PwaInstallModal from './PwaInstallModal.vue';

const props = withDefaults(
    defineProps<{
        variant?: 'compact' | 'full' | 'floating';
        className?: string;
    }>(),
    {
        variant: 'compact',
        className: ''
    }
);

const { isInstallable, isStandalone } = usePwa();
const showModal = ref(false);
</script>

<template>
    <!-- Only show if not already installed and browser supports installation -->
    <div v-if="!isStandalone && isInstallable" class="inline-block">
        <!-- Compact Pill Button (for Navigation Bars) -->
        <button
            v-if="variant === 'compact'"
            type="button"
            @click="showModal = true"
            :class="[
                'group flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-gradient-to-r from-amber-500/15 to-yellow-500/15 hover:from-amber-500/25 hover:to-yellow-500/25 border border-amber-500/30 hover:border-amber-500/50 text-amber-300 text-xs font-bold transition shadow-sm font-cairo active:scale-95 whitespace-nowrap shrink-0',
                className
            ]"
            title="تثبيت منصة تدلّل كتطبيق أصلي على جهازك"
        >
            <span class="text-amber-400 group-hover:scale-110 transition-transform">📲</span>
            <span>تثبيت التطبيق</span>
        </button>

        <!-- Full Button (for Sidebars or Hero Sections) -->
        <button
            v-else-if="variant === 'full'"
            type="button"
            @click="showModal = true"
            :class="[
                'w-full flex items-center justify-center gap-2 px-4 py-3 rounded-2xl bg-gradient-to-r from-amber-500 via-amber-400 to-yellow-500 text-zinc-950 font-bold text-sm shadow-lg shadow-amber-500/20 hover:brightness-110 active:scale-[0.98] transition font-cairo',
                className
            ]"
        >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
            <span>تثبيت تطبيق تدلّل كـ تطبيق أصلي</span>
        </button>

        <!-- Floating Action Button (Mobile Corner) -->
        <button
            v-else-if="variant === 'floating'"
            type="button"
            @click="showModal = true"
            :class="[
                'fixed bottom-20 left-4 z-40 flex items-center gap-2 rounded-full bg-gradient-to-r from-amber-500 to-amber-600 px-4 py-2.5 text-zinc-950 font-bold text-xs shadow-xl shadow-amber-500/30 hover:scale-105 active:scale-95 transition font-cairo border border-amber-300',
                className
            ]"
        >
            <span class="animate-bounce">📲</span>
            <span>ثبّت التطبيق</span>
        </button>

        <!-- Install Instruction / Confirmation Modal -->
        <PwaInstallModal :show="showModal" @close="showModal = false" />
    </div>
</template>
