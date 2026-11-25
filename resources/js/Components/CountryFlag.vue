<script setup lang="ts">
import { computed } from 'vue';

interface Props {
    code: string;
    size?: 'sm' | 'md';
}

const props = withDefaults(defineProps<Props>(), {
    size: 'sm',
});

const normalizedCode = computed(() => (props.code || 'SY').toUpperCase().trim());

const sizeClass = computed(() =>
    props.size === 'md' ? 'w-6 h-4 rounded-[3px]' : 'w-5 h-3.5 rounded-[2.5px]',
);

function buildStarPoints(cx: number, cy: number, outerR = 2.55, innerR = 0.98): string {
    const pts: string[] = [];
    for (let i = 0; i < 10; i++) {
        const angle = (-Math.PI / 2) + (i * Math.PI) / 5;
        const r = i % 2 === 0 ? outerR : innerR;
        pts.push(`${(cx + r * Math.cos(angle)).toFixed(2)},${(cy + r * Math.sin(angle)).toFixed(2)}`);
    }
    return pts.join(' ');
}

const syriaStars = [10, 18, 26].map((cx) => buildStarPoints(cx, 12));
const jordanStar = buildStarPoints(5.8, 12, 2.0, 0.8);
</script>

<template>
    <span
        class="inline-flex items-center justify-center overflow-hidden shrink-0 ring-1 ring-black/15 dark:ring-white/20 shadow-2xs"
        :class="sizeClass"
        role="img"
        :aria-label="normalizedCode"
    >
        <!-- New Syrian Flag (Green - White with 3 Red Stars - Black) -->
        <svg v-if="normalizedCode === 'SY'" viewBox="0 0 36 24" class="w-full h-full block" aria-hidden="true">
            <rect width="36" height="8" y="0" fill="#007A3D" />
            <rect width="36" height="8" y="8" fill="#FFFFFF" />
            <rect width="36" height="8" y="16" fill="#000000" />
            <polygon v-for="(pts, idx) in syriaStars" :key="idx" :points="pts" fill="#CE1126" />
        </svg>

        <!-- Saudi Arabia -->
        <svg v-else-if="normalizedCode === 'SA'" viewBox="0 0 36 24" class="w-full h-full block" aria-hidden="true">
            <rect width="36" height="24" fill="#006C35" />
            <path d="M9 8.5h18M11 11.2h14M13.5 6.8c1.5 0 2.5 1.1 4.5 1.1s3-1.1 4.5-1.1" stroke="#FFFFFF" stroke-width="1.35" stroke-linecap="round" fill="none" />
            <path d="M8.5 16.5h17.5l1.5-1.1M11 15.3v2.4" stroke="#FFFFFF" stroke-width="1.4" stroke-linecap="round" fill="none" />
        </svg>

        <!-- United Arab Emirates -->
        <svg v-else-if="normalizedCode === 'AE'" viewBox="0 0 36 24" class="w-full h-full block" aria-hidden="true">
            <rect width="27" height="8" x="9" y="0" fill="#00732F" />
            <rect width="27" height="8" x="9" y="8" fill="#FFFFFF" />
            <rect width="27" height="8" x="9" y="16" fill="#000000" />
            <rect width="9" height="24" x="0" y="0" fill="#EF3340" />
        </svg>

        <!-- Kuwait -->
        <svg v-else-if="normalizedCode === 'KW'" viewBox="0 0 36 24" class="w-full h-full block" aria-hidden="true">
            <rect width="36" height="8" y="0" fill="#007A3D" />
            <rect width="36" height="8" y="8" fill="#FFFFFF" />
            <rect width="36" height="8" y="16" fill="#CE1126" />
            <polygon points="0,0 10.5,8 10.5,16 0,24" fill="#000000" />
        </svg>

        <!-- Qatar -->
        <svg v-else-if="normalizedCode === 'QA'" viewBox="0 0 36 24" class="w-full h-full block" aria-hidden="true">
            <rect width="36" height="24" fill="#8A1538" />
            <polygon
                points="0,0 11,0 14,1.33 11,2.67 14,4 11,5.33 14,6.67 11,8 14,9.33 11,10.67 14,12 11,13.33 14,14.67 11,16 14,17.33 11,18.67 14,20 11,21.33 14,22.67 11,24 0,24"
                fill="#FFFFFF"
            />
        </svg>

        <!-- Bahrain -->
        <svg v-else-if="normalizedCode === 'BH'" viewBox="0 0 36 24" class="w-full h-full block" aria-hidden="true">
            <rect width="36" height="24" fill="#CE1126" />
            <polygon points="0,0 10,0 14,2.4 10,4.8 14,7.2 10,9.6 14,12 10,14.4 14,16.8 10,19.2 14,21.6 10,24 0,24" fill="#FFFFFF" />
        </svg>

        <!-- Oman -->
        <svg v-else-if="normalizedCode === 'OM'" viewBox="0 0 36 24" class="w-full h-full block" aria-hidden="true">
            <rect width="26" height="8" x="10" y="0" fill="#FFFFFF" />
            <rect width="26" height="8" x="10" y="8" fill="#DB161B" />
            <rect width="26" height="8" x="10" y="16" fill="#008000" />
            <rect width="10" height="24" x="0" y="0" fill="#DB161B" />
            <circle cx="5" cy="5.2" r="2.1" fill="none" stroke="#FFFFFF" stroke-width="1" />
        </svg>

        <!-- Jordan -->
        <svg v-else-if="normalizedCode === 'JO'" viewBox="0 0 36 24" class="w-full h-full block" aria-hidden="true">
            <rect width="36" height="8" y="0" fill="#000000" />
            <rect width="36" height="8" y="8" fill="#FFFFFF" />
            <rect width="36" height="8" y="16" fill="#007A3D" />
            <polygon points="0,0 16.5,12 0,24" fill="#CE1126" />
            <polygon :points="jordanStar" fill="#FFFFFF" />
        </svg>

        <!-- Egypt -->
        <svg v-else-if="normalizedCode === 'EG'" viewBox="0 0 36 24" class="w-full h-full block" aria-hidden="true">
            <rect width="36" height="8" y="0" fill="#CE1126" />
            <rect width="36" height="8" y="8" fill="#FFFFFF" />
            <rect width="36" height="8" y="16" fill="#000000" />
            <path d="M16.2 9.5h3.6l-.6 4.8-1.2.8-1.2-.8z" fill="#C09300" />
        </svg>

        <!-- Iraq -->
        <svg v-else-if="normalizedCode === 'IQ'" viewBox="0 0 36 24" class="w-full h-full block" aria-hidden="true">
            <rect width="36" height="8" y="0" fill="#CE1126" />
            <rect width="36" height="8" y="8" fill="#FFFFFF" />
            <rect width="36" height="8" y="16" fill="#000000" />
            <path d="M11 12.2h4.2M17 12.2h3M21.5 12.2h3.5" stroke="#007A3D" stroke-width="1.6" stroke-linecap="round" />
        </svg>

        <!-- Fallback -->
        <span v-else class="text-[9px] font-black uppercase tracking-tighter">{{ normalizedCode }}</span>
    </span>
</template>
