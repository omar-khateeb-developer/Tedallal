<script setup lang="ts">
interface Props {
    animated?: boolean;
    variant?: 'crop' | 'pure' | 'full';
    alt?: string;
}

withDefaults(defineProps<Props>(), {
    animated: true,
    variant: 'crop',
    alt: 'تدلّل - Tedallal',
});
</script>

<template>
    <div
        class="relative inline-flex items-center justify-center select-none"
        :class="{ 'tedallal-logo-wrapper': animated }"
    >
        <!-- Ambient Golden Backlight Aura -->
        <div
            v-if="animated"
            class="tedallal-golden-aura absolute -inset-1 rounded-2xl pointer-events-none"
            aria-hidden="true"
        ></div>

        <!-- The Golden Emblem Image -->
        <div
            class="relative overflow-hidden rounded-xl inline-flex items-center justify-center"
            :class="{ 'tedallal-floating': animated }"
        >
            <img
                v-if="variant === 'pure'"
                src="/images/tedallal-luxury-transparent.png"
                :alt="alt"
                class="w-full h-full object-contain filter drop-shadow-md"
                onerror="this.src='/images/tedallal-luxury-transparent.png'"
            />
            <img
                v-else-if="variant === 'full'"
                src="/images/tedallal-golden-logo.png"
                :alt="alt"
                class="w-full h-full object-contain filter drop-shadow-md"
                onerror="this.src='/images/tedallal-luxury-transparent.png'"
            />
            <img
                v-else
                src="/images/tedallal-luxury-transparent.png"
                :alt="alt"
                class="w-full h-full object-contain filter drop-shadow-md"
                onerror="this.src='/images/logo.jpg'"
            />


        </div>
    </div>
</template>

<style scoped>
/* 1. Fluid Levitation & Floating Motion */
.tedallal-floating {
    animation: tedallalFloat 4.2s cubic-bezier(0.45, 0.05, 0.55, 0.95) infinite;
    will-change: transform;
}

@keyframes tedallalFloat {
    0%, 100% {
        transform: translateY(0px) rotate(0deg);
    }
    50% {
        transform: translateY(-3px) rotate(0.4deg);
    }
}

/* 2. Soft Ambient Golden Glow Pulse */
.tedallal-golden-aura {
    background: radial-gradient(
        circle,
        rgba(212, 175, 55, 0.35) 0%,
        rgba(245, 158, 11, 0.15) 50%,
        transparent 75%
    );
    filter: blur(8px);
    animation: tedallalAuraPulse 3.8s ease-in-out infinite;
    will-change: opacity, transform;
}

@keyframes tedallalAuraPulse {
    0%, 100% {
        opacity: 0.45;
        transform: scale(0.95);
    }
    50% {
        opacity: 0.85;
        transform: scale(1.08);
    }
}



/* Respect User Reduced Motion Preference */
@media (prefers-reduced-motion: reduce) {
    .tedallal-floating,
    .tedallal-golden-aura {
        animation: none !important;
        transform: none !important;
    }
}
</style>
