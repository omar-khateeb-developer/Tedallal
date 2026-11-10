<script setup lang="ts">
import { ref } from 'vue';
import { useTheme } from '@/composables/useTheme';

interface Props {
    animated?: boolean;
    variant?: 'full' | 'emblem';
    replayOnClick?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    animated: true,
    variant: 'full',
    replayOnClick: true,
});

const { isDark } = useTheme();

// Unique ID per instance to prevent SVG defs collision
const uid = 'tld_logo_' + Math.random().toString(36).substring(2, 8);
const isReplaying = ref(false);

function replay() {
    if (!props.replayOnClick || !props.animated) return;
    isReplaying.value = true;
    setTimeout(() => {
        isReplaying.value = false;
    }, 50);
}
</script>

<template>
    <div
        class="inline-flex items-center justify-center select-none relative"
        :class="{ 'cursor-pointer': replayOnClick }"
        @click="replay"
        :title="replayOnClick ? 'انقر لإعادة تشغيل حركة الشعار الملكي' : undefined"
    >
        <svg
            v-if="!isReplaying"
            xmlns="http://www.w3.org/2000/svg"
            :viewBox="variant === 'emblem' ? '180 50 640 510' : '0 0 1000 780'"
            class="w-full h-full block"
            style="background: transparent; overflow: visible;"
        >
            <defs>
                <!-- التدرج الذهبي الفاخر للشعار - متكيف مع الوضعين الداكن والفاتح -->
                <linearGradient :id="'gold-gradient-' + uid" x1="0%" y1="0%" x2="100%" y2="100%">
                    <template v-if="isDark">
                        <stop offset="0%" stop-color="#B38728" />
                        <stop offset="25%" stop-color="#FBF5B7" />
                        <stop offset="50%" stop-color="#DAA520" />
                        <stop offset="75%" stop-color="#FBF5B7" />
                        <stop offset="100%" stop-color="#AA771C" />
                    </template>
                    <template v-else>
                        <stop offset="0%" stop-color="#8C6314" />
                        <stop offset="25%" stop-color="#E5BE62" />
                        <stop offset="50%" stop-color="#B8860B" />
                        <stop offset="75%" stop-color="#F3D581" />
                        <stop offset="100%" stop-color="#734F07" />
                    </template>
                </linearGradient>

                <!-- قناع مخصص لتأثير اللمعة الذهبية الختامية -->
                <linearGradient :id="'shine-gradient-' + uid" x1="0%" y1="0%" x2="100%" y2="0%">
                    <stop offset="0%" stop-color="rgba(255,255,255,0)" />
                    <stop offset="50%" :stop-color="isDark ? 'rgba(255,255,255,0.7)' : 'rgba(255,255,255,0.9)'" />
                    <stop offset="100%" stop-color="rgba(255,255,255,0)" />
                </linearGradient>

                <!-- قناع المسح النصي التدريجي للنصوص السفلية -->
                <clipPath :id="'text-clip-' + uid">
                    <rect :id="'clip-rect-' + uid" x="100" y="550" :width="animated ? 0 : 800" height="200" />
                </clipPath>
            </defs>

            <!-- الكود الهيكلي ومجموعات العناصر المجهزة للمحرك -->
            <g :id="'main-logo-container-' + uid">
                <!-- 1. القوس الدائري والخطوط الأساسية القاعدية -->
                <g :class="animated ? 'tld-base-lines' : 'tld-base-lines-static'">
                    <path d="M 250,500 A 250,250 0 0,1 750,500 Z" />
                    <line x1="220" y1="510" x2="780" y2="510" />
                    <line x1="240" y1="525" x2="760" y2="525" />
                    <circle cx="470" cy="540" r="8" />
                    <circle cx="500" cy="540" r="8" />
                    <circle cx="530" cy="540" r="8" />
                </g>

                <!-- 2. المخطوطة العربية الفاخرة تدلل -->
                <g :class="animated ? 'tld-arabic-calligraphy' : 'tld-calligraphy-static'">
                    <path d="M 620,400 C 650,300 580,260 540,320 C 500,370 450,480 380,480 C 330,480 290,400 320,330 C 340,280 390,290 410,340 C 430,400 370,450 450,450 C 510,450 560,340 500,240" />
                </g>

                <!-- النقاط، الشدة، الحركات الزخرفية للخط -->
                <g :class="animated ? 'tld-dots-accents' : 'tld-dots-static'">
                    <circle cx="610" cy="230" r="10" />
                    <circle cx="640" cy="245" r="10" />
                    <path d="M 550,210 Q 560,195 570,210 T 590,210" stroke-width="4" :stroke="'url(#gold-gradient-' + uid + ')'" fill="none" />
                </g>

                <!-- 3. التاج الملكي الفخم في الأعلى والوميض المستهدف -->
                <g :class="animated ? 'tld-royal-crown' : 'tld-crown-static'">
                    <path d="M 370,230 L 410,120 L 460,180 L 500,80 L 540,180 L 590,120 L 630,230 C 630,230 500,260 370,230 Z" />
                    <circle cx="410" cy="110" r="12" />
                    <circle cx="500" cy="68" r="15" />
                    <circle cx="590" cy="110" r="12" />
                </g>
                <circle v-if="animated" class="tld-crown-flare" cx="500" cy="235" r="40" />

                <!-- 4. النصوص السفلية (TEDALLAL والترجمة العربية) - تظهر فقط في الوضع الكامل full -->
                <g v-if="variant === 'full'" class="tld-bottom-text" :style="{ clipPath: animated ? `url(#text-clip-${uid})` : undefined }">
                    <text x="500" y="610" font-family="'Montserrat', 'Cairo', 'Arial Black', sans-serif" font-size="75" font-weight="900" letter-spacing="10" text-anchor="middle">TEDALLAL</text>
                    <text x="500" y="700" font-family="'Cairo', 'Traditional Arabic', sans-serif" font-size="50" font-weight="bold" letter-spacing="4" text-anchor="middle">تـدلّـل</text>
                </g>

                <!-- 5. طبقة المسح الضوئي العلوية المسؤولة عن لمعة الذهب -->
                <rect v-if="animated" class="tld-shine-overlay" x="0" y="50" width="150" height="700" :fill="'url(#shine-gradient-' + uid + ')'" />
            </g>
        </svg>
    </div>
</template>

<style scoped>
/* 1. حركة رسم الخطوط الأساسية والقوس (0s - 1s) */
.tld-base-lines {
    stroke: v-bind("'url(#gold-gradient-' + uid + ')'");
    stroke-width: 4;
    fill: none;
    stroke-dasharray: 1200;
    stroke-dashoffset: 1200;
    animation: tldDrawLines 1s cubic-bezier(0.4, 0, 0.2, 1) forwards;
}

.tld-base-lines-static {
    stroke: v-bind("'url(#gold-gradient-' + uid + ')'");
    stroke-width: 4;
    fill: v-bind("'url(#gold-gradient-' + uid + ')'");
    fill-opacity: 0.1;
}

/* 2. حركة كتابة المخطوطة العربية الحية (1s - 2s) */
.tld-arabic-calligraphy {
    stroke: v-bind("'url(#gold-gradient-' + uid + ')'");
    stroke-width: 5;
    fill: none;
    stroke-dasharray: 1500;
    stroke-dashoffset: 1500;
    animation: tldDrawCalligraphy 1s cubic-bezier(0.4, 0, 0.2, 1) 1s forwards;
}

.tld-calligraphy-static {
    stroke: v-bind("'url(#gold-gradient-' + uid + ')'");
    stroke-width: 5;
    fill: v-bind("'url(#gold-gradient-' + uid + ')'");
}

/* تأثير قفزة النقاط والتشكيل (1.8s - 2.2s) */
.tld-dots-accents {
    fill: v-bind("'url(#gold-gradient-' + uid + ')'");
    opacity: 0;
    transform-origin: 500px 300px;
    animation: tldPopBounce 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) 1.8s forwards;
}

.tld-dots-static {
    fill: v-bind("'url(#gold-gradient-' + uid + ')'");
    opacity: 1;
}

/* 3. حركة هبوط التاج الملكي واستقراره (2s - 2.8s) */
.tld-royal-crown {
    fill: v-bind("'url(#gold-gradient-' + uid + ')'");
    opacity: 0;
    animation: tldDropCrown 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94) 2s forwards;
}

.tld-crown-static {
    fill: v-bind("'url(#gold-gradient-' + uid + ')'");
    opacity: 1;
}

/* الوميض الخفيف عند ملامسة التاج للقوس */
.tld-crown-flare {
    fill: #ffffff;
    opacity: 0;
    mix-blend-mode: overlay;
    animation: tldFlashFlare 0.5s ease-out 2.7s forwards;
}

/* 4. حركة ظهور النصوص السفلية بالمسح التدريجي (2.8s - 3.5s) */
.tld-bottom-text {
    fill: v-bind("'url(#gold-gradient-' + uid + ')'");
}

/* 5. تأثير لمعة الذهب الانعكاسية الختامية (3.5s - 4s) */
.tld-shine-overlay {
    mix-blend-mode: overlay;
    transform: skewX(-30deg) translateX(-300px);
    animation: tldGoldenSweep 0.5s ease-in-out 3.5s forwards;
}

/* تعريفات الـ Keyframes للحركات الصرفة */
@keyframes tldDrawLines {
    to {
        stroke-dashoffset: 0;
        fill: v-bind("'url(#gold-gradient-' + uid + ')'");
        fill-opacity: 0.1;
    }
}

@keyframes tldDrawCalligraphy {
    to {
        stroke-dashoffset: 0;
        fill: v-bind("'url(#gold-gradient-' + uid + ')'");
    }
}

@keyframes tldPopBounce {
    0% {
        opacity: 0;
        transform: scale(0.3);
    }
    70% {
        transform: scale(1.1);
    }
    100% {
        opacity: 1;
        transform: scale(1);
    }
}

@keyframes tldDropCrown {
    0% {
        opacity: 0;
        transform: translateY(-80px);
    }
    100% {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes tldFlashFlare {
    0% {
        opacity: 0;
        transform: scale(0.5);
    }
    50% {
        opacity: 0.8;
    }
    100% {
        opacity: 0;
        transform: scale(1.5);
    }
}

@keyframes tldGoldenSweep {
    0% {
        transform: skewX(-30deg) translateX(-300px);
    }
    100% {
        transform: skewX(-30deg) translateX(1200px);
    }
}

/* حركة المسح التدريجي للمستطيل */
:deep([id^="clip-rect-"]) {
    animation: tldRevealText 0.7s ease-out 2.8s forwards;
}

@keyframes tldRevealText {
    to {
        width: 800px;
    }
}

/* Respect user preferences */
@media (prefers-reduced-motion: reduce) {
    .tld-base-lines,
    .tld-arabic-calligraphy,
    .tld-dots-accents,
    .tld-royal-crown,
    .tld-crown-flare,
    .tld-shine-overlay {
        animation: none !important;
        opacity: 1 !important;
        stroke-dashoffset: 0 !important;
        transform: none !important;
    }
}
</style>
