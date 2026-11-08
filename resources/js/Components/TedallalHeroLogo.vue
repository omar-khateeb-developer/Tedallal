<script setup lang="ts">
import { ref, onMounted, onUnmounted, nextTick } from 'vue';

interface Props {
    widthClass?: string;
}

withDefaults(defineProps<Props>(), {
    widthClass: 'w-64 sm:w-80 md:w-96 lg:w-[420px]',
});

const isRevealed = ref(false);
const isAnimating = ref(false);
const svgKey = ref(0);
const svgContainerRef = ref<HTMLDivElement | null>(null);

let cycleTimer: ReturnType<typeof setTimeout> | null = null;
let loopTimer: ReturnType<typeof setTimeout> | null = null;

// Durations
const DRAW_DURATION = 3200;     // 3.2 seconds for progressive vector drawing
const DISPLAY_DURATION = 6500;  // 6.5 seconds for 3D transparent emblem display

function initStrokes(container: HTMLElement) {
    const strokes = container.querySelectorAll<SVGGeometryElement>('.v-stroke');
    strokes.forEach((el) => {
        let len = 400;
        try {
            if (el.getTotalLength) {
                len = Math.ceil(el.getTotalLength());
            }
        } catch (e) {}
        el.style.setProperty('--path-len', `${len}px`);
        el.style.strokeDasharray = `${len}px`;
        el.style.strokeDashoffset = `${len}px`;
    });
}

function startCycle() {
    if (cycleTimer) clearTimeout(cycleTimer);
    if (loopTimer) clearTimeout(loopTimer);

    isRevealed.value = false;
    isAnimating.value = false;
    svgKey.value++; // Clean reset of the DOM tree

    nextTick(() => {
        if (svgContainerRef.value) {
            // 1. Initialize exact lengths & offsets to zero-visible state
            initStrokes(svgContainerRef.value);

            // 2. Force browser layout reflow so the zero state is committed to GPU
            void svgContainerRef.value.getBoundingClientRect();

            // 3. Double requestAnimationFrame guarantees compositor commits the blank slate before drawing
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    isAnimating.value = true;
                });
            });
        }

        // 4. At 3.2s: transition seamlessly into 3D transparent PNG
        cycleTimer = setTimeout(() => {
            isRevealed.value = true;

            // 5. Loop: repeat the drawing sequence every cycle
            loopTimer = setTimeout(() => {
                startCycle();
            }, DISPLAY_DURATION);
        }, DRAW_DURATION);
    });
}

function handleReplay() {
    startCycle();
}

onMounted(() => {
    startCycle();
});

onUnmounted(() => {
    if (cycleTimer) clearTimeout(cycleTimer);
    if (loopTimer) clearTimeout(loopTimer);
});
</script>

<template>
    <div
        class="relative mx-auto select-none group cursor-pointer aspect-[1024/764]"
        :class="widthClass"
        @click="handleReplay"
        title="انقر لإعادة تشغيل حركة الشعار"
    >
        <!-- Ambient Royal Backlight Glow (No harsh shimmer streaks) -->
        <div
            class="absolute -inset-4 sm:-inset-6 rounded-full bg-[radial-gradient(circle,rgba(212,175,55,0.32)_0%,rgba(245,158,11,0.08)_55%,transparent_75%)] blur-2xl pointer-events-none transition-all duration-700"
            :class="isRevealed ? 'opacity-90 scale-105' : 'opacity-40 scale-95'"
            aria-hidden="true"
        ></div>

        <!-- ========================================================
             LAYER 1: ANIMATED VECTOR SVG (Starts Strictly from 0)
             ======================================================== -->
        <div
            ref="svgContainerRef"
            :key="svgKey"
            class="absolute inset-0 w-full h-full transition-opacity duration-700 ease-in-out pointer-events-none"
            :class="[
                isRevealed ? 'opacity-0 scale-98' : 'opacity-100 scale-100',
                isAnimating ? 'draw-active' : 'draw-idle'
            ]"
        >
            <svg
                viewBox="0 0 1024 764"
                xmlns="http://www.w3.org/2000/svg"
                class="w-full h-full select-none"
                style="overflow: visible;"
            >
                <defs>
                    <!-- Linear Metallic Gold Gradients -->
                    <linearGradient id="goldGradSheen" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="#FFF8DC"></stop>
                        <stop offset="25%" stop-color="#FCF6BA"></stop>
                        <stop offset="50%" stop-color="#D4AF37"></stop>
                        <stop offset="75%" stop-color="#AA771C"></stop>
                        <stop offset="100%" stop-color="#805A10"></stop>
                    </linearGradient>

                    <linearGradient id="goldGradHorizontal" x1="0%" y1="0%" x2="100%" y2="0%">
                        <stop offset="0%" stop-color="#AA771C"></stop>
                        <stop offset="30%" stop-color="#FCF6BA"></stop>
                        <stop offset="50%" stop-color="#FFF8DC"></stop>
                        <stop offset="70%" stop-color="#FCF6BA"></stop>
                        <stop offset="100%" stop-color="#AA771C"></stop>
                    </linearGradient>

                    <linearGradient id="goldGradVertical" x1="0%" y1="0%" x2="0%" y2="100%">
                        <stop offset="0%" stop-color="#FFF8DC"></stop>
                        <stop offset="35%" stop-color="#FCF6BA"></stop>
                        <stop offset="70%" stop-color="#D4AF37"></stop>
                        <stop offset="100%" stop-color="#805A10"></stop>
                    </linearGradient>

                    <!-- Radial Glow for Pearls & Knobs -->
                    <radialGradient id="pearlRadial" cx="35%" cy="35%" r="65%">
                        <stop offset="0%" stop-color="#FFFFFF"></stop>
                        <stop offset="40%" stop-color="#FCF6BA"></stop>
                        <stop offset="75%" stop-color="#D4AF37"></stop>
                        <stop offset="100%" stop-color="#805A10"></stop>
                    </radialGradient>

                    <!-- Glowing Ambient Drop Shadow Filter -->
                    <filter id="goldGlow" x="-20%" y="-20%" width="140%" height="140%">
                        <feGaussianBlur in="SourceAlpha" stdDeviation="4" result="blur"></feGaussianBlur>
                        <feFlood flood-color="#D4AF37" flood-opacity="0.65" result="flood"></feFlood>
                        <feComposite in2="blur" operator="in" result="glow"></feComposite>
                        <feMerge>
                            <feMergeNode in="glow"></feMergeNode>
                            <feMergeNode in="SourceGraphic"></feMergeNode>
                        </feMerge>
                    </filter>

                    <!-- Subtle Specular Reflection Filter -->
                    <filter id="luxurySpecular">
                        <feDropShadow dx="0" dy="4" stdDeviation="8" flood-color="#000000" flood-opacity="0.85"></feDropShadow>
                        <feDropShadow dx="0" dy="0" stdDeviation="12" flood-color="#D4AF37" flood-opacity="0.35"></feDropShadow>
                    </filter>
                </defs>

                <!-- LAYER 1: COSMIC ORBITAL RINGS & AURA -->
                <g id="layer-orbits" class="phase-orbits">
                    <circle
                        cx="512"
                        cy="420"
                        r="285"
                        class="v-stroke"
                        stroke="url(#goldGradHorizontal)"
                        stroke-width="1.5"
                        stroke-opacity="0.35"
                        
                        style="--path-len: 1791px; stroke-dasharray: 1791px; stroke-dashoffset: 1791px;"
                    ></circle>

                    <g class="spin-orbit-1">
                        <ellipse
                            cx="512"
                            cy="420"
                            rx="315"
                            ry="210"
                            transform="rotate(-22 512 420)"
                            class="v-stroke"
                            stroke="url(#goldGradSheen)"
                            stroke-width="2.5"
                            stroke-opacity="0.85"
                            filter="url(#goldGlow)"
                            style="--path-len: 1666px; stroke-dasharray: 1666px; stroke-dashoffset: 1666px;"
                        ></ellipse>
                    </g>

                    <g class="spin-orbit-2">
                        <ellipse
                            cx="512"
                            cy="420"
                            rx="295"
                            ry="195"
                            transform="rotate(38 512 420)"
                            class="v-stroke"
                            stroke="url(#goldGradHorizontal)"
                            stroke-width="2.0"
                            stroke-opacity="0.75"
                            filter="url(#goldGlow)"
                            style="--path-len: 1555px; stroke-dasharray: 1555px; stroke-dashoffset: 1555px;"
                        ></ellipse>
                    </g>
                </g>

                <!-- LAYER 2: SERVING PLATTER TRAY & 3 BALANCING PEARLS -->
                <g id="layer-plate" class="phase-plate" filter="url(#luxurySpecular)">
                    <path class="v-stroke" stroke="url(#goldGradHorizontal)" stroke-width="6" d="M 305 520 L 719 520" style="--path-len: 414px; stroke-dasharray: 414px; stroke-dashoffset: 414px;"></path>
                    <path class="v-stroke" stroke="url(#goldGradHorizontal)" stroke-width="4" d="M 322 531 L 702 531" style="--path-len: 380px; stroke-dasharray: 380px; stroke-dashoffset: 380px;"></path>
                    <path class="v-stroke" stroke="url(#goldGradSheen)" stroke-width="5" d="M 305 520 C 298 523, 308 531, 322 531" style="--path-len: 27px; stroke-dasharray: 27px; stroke-dashoffset: 27px;"></path>
                    <path class="v-stroke" stroke="url(#goldGradSheen)" stroke-width="5" d="M 719 520 C 726 523, 716 531, 702 531" style="--path-len: 27px; stroke-dasharray: 27px; stroke-dashoffset: 27px;"></path>
                    <polygon class="v-fill-target" points="305,520 719,520 702,531 322,531" fill="url(#goldGradSheen)"></polygon>
                </g>

                <!-- 3 Balancing Pearls Beneath Platter -->
                <g id="layer-pearls" class="phase-pearls">
                    <circle cx="474" cy="542" r="5.5" fill="url(#pearlRadial)" stroke="#AA771C" stroke-width="1.2" filter="url(#goldGlow)"></circle>
                    <circle cx="512" cy="542" r="7.5" fill="url(#pearlRadial)" stroke="#AA771C" stroke-width="1.5" filter="url(#goldGlow)"></circle>
                    <circle cx="550" cy="542" r="5.5" fill="url(#pearlRadial)" stroke="#AA771C" stroke-width="1.2" filter="url(#goldGlow)"></circle>
                </g>

                <!-- LAYER 3: CLOCHE DOME SILHOUETTE & ROYAL HANDLE KNOB -->
                <g id="layer-dome" class="phase-dome" filter="url(#luxurySpecular)">
                    <path class="v-stroke" stroke="url(#goldGradVertical)" stroke-width="12" d="M 505 304 C 445 315, 360 365, 328 440 C 314 472, 318 505, 324 520" style="--path-len: 314px; stroke-dasharray: 314px; stroke-dashoffset: 314px;"></path>
                    <path class="v-stroke" stroke="url(#goldGradSheen)" stroke-width="4" d="M 505 320 C 455 332, 385 380, 355 450 C 342 478, 345 505, 348 520" style="--path-len: 277px; stroke-dasharray: 277px; stroke-dashoffset: 277px;"></path>
                    <path class="v-stroke" stroke="url(#goldGradVertical)" stroke-width="7" d="M 519 304 C 585 315, 680 375, 710 455 C 720 482, 720 505, 718 520" style="--path-len: 320px; stroke-dasharray: 320px; stroke-dashoffset: 320px;"></path>
                    <path class="v-fill-target" d="M 505 304 C 445 315, 360 365, 328 440 C 314 472, 318 505, 324 520 L 348 520 C 345 505, 342 478, 355 450 C 385 380, 455 332, 505 320 Z" fill="url(#goldGradSheen)"></path>
                </g>

                <!-- Cloche Handle Knob -->
                <g id="layer-knob" class="phase-knob">
                    <path class="v-stroke" stroke="url(#goldGradVertical)" stroke-width="4" d="M 508 290 L 508 304 M 516 290 L 516 304" style="--path-len: 28px; stroke-dasharray: 28px; stroke-dashoffset: 28px;"></path>
                    <circle cx="512" cy="274" r="17.5" class="v-stroke" stroke="url(#goldGradSheen)" stroke-width="4" style="--path-len: 110px; stroke-dasharray: 110px; stroke-dashoffset: 110px;"></circle>
                    <circle cx="512" cy="274" r="16" class="v-fill-target" fill="url(#pearlRadial)" filter="url(#goldGlow)"></circle>
                </g>

                <!-- LAYER 4: DIGITAL NETWORK GRID & DATA NODES -->
                <g id="layer-grid" class="phase-grid">
                    <path class="v-stroke" stroke="url(#goldGradHorizontal)" stroke-width="3" stroke-opacity="0.8" d="M 580 405 C 625 415, 668 435, 692 460" style="--path-len: 127px; stroke-dasharray: 127px; stroke-dashoffset: 127px;"></path>
                    <path class="v-stroke" stroke="url(#goldGradHorizontal)" stroke-width="3.5" stroke-opacity="0.85" d="M 550 450 C 610 460, 670 475, 710 500" style="--path-len: 169px; stroke-dasharray: 169px; stroke-dashoffset: 169px;"></path>
                    <path class="v-stroke" stroke="url(#goldGradHorizontal)" stroke-width="3" stroke-opacity="0.8" d="M 535 490 C 595 498, 655 508, 715 520" style="--path-len: 183px; stroke-dasharray: 183px; stroke-dashoffset: 183px;"></path>
                    <path class="v-stroke" stroke="url(#goldGradVertical)" stroke-width="3" stroke-opacity="0.75" d="M 605 385 C 612 435, 616 480, 618 520" style="--path-len: 136px; stroke-dasharray: 136px; stroke-dashoffset: 136px;"></path>
                    <path class="v-stroke" stroke="url(#goldGradVertical)" stroke-width="3" stroke-opacity="0.75" d="M 660 420 C 665 460, 670 495, 672 520" style="--path-len: 101px; stroke-dasharray: 101px; stroke-dashoffset: 101px;"></path>
                </g>

                <!-- Network Data Nodes -->
                <g id="layer-nodes" class="phase-nodes">
                    <circle cx="605" cy="410" r="4.5" fill="url(#pearlRadial)" filter="url(#goldGlow)"></circle>
                    <circle cx="660" cy="432" r="4.5" fill="url(#pearlRadial)" filter="url(#goldGlow)"></circle>
                    <circle cx="692" cy="460" r="5.0" fill="url(#pearlRadial)" filter="url(#goldGlow)"></circle>
                    <circle cx="612" cy="455" r="4.5" fill="url(#pearlRadial)" filter="url(#goldGlow)"></circle>
                    <circle cx="665" cy="470" r="4.5" fill="url(#pearlRadial)" filter="url(#goldGlow)"></circle>
                    <circle cx="710" cy="500" r="5.0" fill="url(#pearlRadial)" filter="url(#goldGlow)"></circle>
                    <circle cx="616" cy="495" r="4.5" fill="url(#pearlRadial)" filter="url(#goldGlow)"></circle>
                    <circle cx="670" cy="505" r="4.5" fill="url(#pearlRadial)" filter="url(#goldGlow)"></circle>
                </g>

                <!-- LAYER 5: ROYAL ARABIC CALLIGRAPHY "تدلّل" INSIDE DOME -->
                <g id="layer-calligraphy" filter="url(#luxurySpecular)">
                    <!-- Taa Dots -->
                    <g id="callig_taa_dots" class="phase-callig-dots">
                        <circle cx="594" cy="288" r="5" fill="url(#pearlRadial)" stroke="#AA771C" stroke-width="1.2" filter="url(#goldGlow)"></circle>
                        <circle cx="612" cy="288" r="5" fill="url(#pearlRadial)" stroke="#AA771C" stroke-width="1.2" filter="url(#goldGlow)"></circle>
                    </g>

                    <!-- Taa Head & Body -->
                    <path id="callig_taa" class="v-stroke phase-calligraphy" stroke="url(#goldGradSheen)" stroke-width="7" stroke-linecap="round" stroke-linejoin="round" d="M 626 325 C 632 360, 616 396, 584 408 L 562 410" style="--path-len: 123px; stroke-dasharray: 123px; stroke-dashoffset: 123px;"></path>

                    <!-- Dal -->
                    <path id="callig_dal" class="v-stroke phase-calligraphy" stroke="url(#goldGradSheen)" stroke-width="7" stroke-linecap="round" stroke-linejoin="round" d="M 562 410 C 556 380, 544 366, 536 368 C 528 372, 524 395, 520 408" style="--path-len: 97px; stroke-dasharray: 97px; stroke-dashoffset: 97px;"></path>

                    <!-- First Lam -->
                    <path id="callig_lam1" class="v-stroke phase-calligraphy" stroke="url(#goldGradVertical)" stroke-width="7.5" stroke-linecap="round" stroke-linejoin="round" d="M 520 408 L 520 264 C 520 256, 514 256, 512 264" style="--path-len: 160px; stroke-dasharray: 160px; stroke-dashoffset: 160px;"></path>

                    <!-- Shaddah -->
                    <path id="callig_shaddah" class="v-stroke phase-shaddah" stroke="url(#goldGradSheen)" stroke-width="4.5" stroke-linecap="round" stroke-linejoin="round" d="M 540 252 Q 547 262 553 250 Q 559 262 566 252 Q 553 274 540 252 Z" style="--path-len: 72px; stroke-dasharray: 72px; stroke-dashoffset: 72px;"></path>

                    <!-- Second Lam -->
                    <path id="callig_lam2" class="v-stroke phase-calligraphy" stroke="url(#goldGradSheen)" stroke-width="7.5" stroke-linecap="round" stroke-linejoin="round" d="M 520 408 C 488 350, 448 300, 410 285 C 360 330, 340 405, 355 455 C 375 488, 450 490, 495 465" style="--path-len: 506px; stroke-dasharray: 506px; stroke-dashoffset: 506px;"></path>
                </g>

                <!-- LAYER 6: 5-POINT ROYAL CROWN & JEWEL FINIALS -->
                <g id="layer-crown" class="phase-crown" filter="url(#luxurySpecular)">
                    <path class="v-stroke" stroke="url(#goldGradHorizontal)" stroke-width="5" d="M 388 268 C 440 252, 584 252, 636 268" style="--path-len: 250px; stroke-dasharray: 250px; stroke-dashoffset: 250px;"></path>
                    <path class="v-stroke" stroke="url(#goldGradSheen)" stroke-width="3.5" d="M 488 262 C 496 248, 528 248, 536 262" style="--path-len: 55px; stroke-dasharray: 55px; stroke-dashoffset: 55px;"></path>
                    <path class="v-stroke" stroke="url(#goldGradSheen)" stroke-width="5.5" d="M 388 268 L 374 209 L 415 238 L 445 189 L 480 226 L 512 159 L 544 226 L 580 189 L 610 238 L 650 209 L 636 268" style="--path-len: 587px; stroke-dasharray: 587px; stroke-dashoffset: 587px;"></path>
                    <path class="v-stroke" stroke="url(#goldGradSheen)" stroke-width="2.5" stroke-opacity="0.85" d="M 512 159 L 512 254 M 445 189 L 465 258 M 374 209 L 405 264 M 580 189 L 559 258 M 650 209 L 619 264" style="--path-len: 366px; stroke-dasharray: 366px; stroke-dashoffset: 366px;"></path>
                    <polygon class="v-fill-target" points="388,268 374,209 415,238 445,189 480,226 512,159 544,226 580,189 610,238 650,209 636,268" fill="url(#goldGradSheen)"></polygon>
                </g>

                <!-- Crown Jewels -->
                <g id="layer-crown-jewels" class="phase-crown-jewels">
                    <circle cx="374" cy="203" r="7.5" fill="url(#pearlRadial)" stroke="#AA771C" stroke-width="1.2" filter="url(#goldGlow)"></circle>
                    <circle cx="445" cy="181" r="9.0" fill="url(#pearlRadial)" stroke="#AA771C" stroke-width="1.5" filter="url(#goldGlow)"></circle>
                    <circle cx="512" cy="148" r="12.5" fill="url(#pearlRadial)" stroke="#AA771C" stroke-width="2.0" filter="url(#goldGlow)"></circle>
                    <circle cx="580" cy="182" r="9.0" fill="url(#pearlRadial)" stroke="#AA771C" stroke-width="1.5" filter="url(#goldGlow)"></circle>
                    <circle cx="650" cy="203" r="7.5" fill="url(#pearlRadial)" stroke="#AA771C" stroke-width="1.2" filter="url(#goldGlow)"></circle>
                </g>

                <!-- LAYER 7: LUXURY BRAND TYPOGRAPHY -->
                <g id="layer-text" class="phase-text" filter="url(#luxurySpecular)">
                    <text x="512" y="608" text-anchor="middle" font-family="'Cinzel', 'Cairo', sans-serif" font-size="52" font-weight="900" letter-spacing="0.22em" fill="url(#goldGradHorizontal)" stroke="#FCF6BA" stroke-width="1.2">TEDALLAL</text>
                    <text x="512" y="688" text-anchor="middle" font-family="'Cairo', sans-serif" font-size="50" font-weight="900" letter-spacing="0.04em" fill="url(#goldGradHorizontal)" stroke="#FCF6BA" stroke-width="1.2">تدلّل</text>
                </g>
            </svg>
        </div>

        <!-- ========================================================
             LAYER 2: 3D LUXURY TRANSPARENT PNG EMBLEM (Final Climax)
             (Clean Luxury 3D Depth - No Harsh Light Sweep Glare)
             ======================================================== -->
        <div
            class="absolute inset-0 w-full h-full flex items-center justify-center transition-all duration-700 ease-in-out"
            :class="isRevealed ? 'opacity-100 scale-100 blur-0' : 'opacity-0 scale-95 blur-[2px] pointer-events-none'"
        >
            <img
                src="/images/tedallal-luxury-transparent.png"
                alt="تدلّل - Tedallal Bespoke Luxury Hospitality"
                class="w-full h-full object-contain filter drop-shadow-[0_12px_45px_rgba(212,175,55,0.75)] group-hover:scale-105 group-hover:drop-shadow-[0_16px_55px_rgba(212,175,55,0.9)] transition-all duration-500"
            />
        </div>
    </div>
</template>

<style scoped>
/* ========================================================
   SVG TRIM PATHS ANIMATION RULES (Starts Strictly from 0)
   ======================================================== */

/* Idle / Zero State: Nothing is visible, all offsets equal length */
.v-stroke {
    fill: none;
    stroke-linecap: round;
    stroke-linejoin: round;
    stroke-dasharray: var(--path-len, 3500px);
    stroke-dashoffset: var(--path-len, 3500px);
    will-change: stroke-dashoffset;
}

.draw-idle .v-stroke {
    stroke-dashoffset: var(--path-len, 3500px) !important;
    animation: none !important;
}

.draw-idle .v-fill-target,
.draw-idle .phase-pearls,
.draw-idle .phase-nodes,
.draw-idle .phase-callig-dots,
.draw-idle .phase-crown-jewels,
.draw-idle .phase-text {
    opacity: 0 !important;
    animation: none !important;
}

/* When draw-active is enabled, trigger progressive drawing */
.draw-active .v-stroke {
    animation: drawTrimPath var(--duration, 1.2s) cubic-bezier(0.35, 0, 0.25, 1) both;
    animation-delay: var(--delay, 0s);
}

@keyframes drawTrimPath {
    0% {
        stroke-dashoffset: var(--path-len, 3500px);
    }
    100% {
        stroke-dashoffset: 0;
    }
}

/* Phase 1: Cosmic Orbits */
.phase-orbits {
    --duration: 1.4s;
    --delay: 0.1s;
}

/* Phase 2: Platter & Pearls */
.phase-plate {
    --duration: 1.0s;
    --delay: 0.3s;
}
.phase-pearls {
    opacity: 0;
}
.draw-active .phase-pearls {
    animation: pearlPopIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) both;
    animation-delay: 0.9s;
}

/* Phase 3: Cloche Dome & Handle Knob */
.phase-dome {
    --duration: 1.2s;
    --delay: 0.7s;
}
.phase-knob {
    --duration: 0.8s;
    --delay: 1.0s;
}

/* Phase 4: Digital Network Grid & Nodes */
.phase-grid {
    --duration: 1.0s;
    --delay: 1.2s;
}
.phase-nodes {
    opacity: 0;
}
.draw-active .phase-nodes {
    animation: nodePulse 0.5s ease-out both;
    animation-delay: 1.6s;
}

/* Phase 5: Royal Arabic Calligraphy "تدلّل" */
.phase-callig-dots {
    opacity: 0;
}
.draw-active .phase-callig-dots {
    animation: pearlPopIn 0.5s ease-out both;
    animation-delay: 1.5s;
}
.phase-calligraphy {
    --duration: 1.2s;
    --delay: 1.6s;
}
.phase-shaddah {
    --duration: 0.7s;
    --delay: 2.1s;
}

/* Phase 6: Royal Crown & Jewels */
.phase-crown {
    --duration: 1.1s;
    --delay: 2.0s;
}
.phase-crown-jewels {
    opacity: 0;
}
.draw-active .phase-crown-jewels {
    animation: jewelGlow 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275) both;
    animation-delay: 2.5s;
}

/* Phase 7: Typography */
.phase-text {
    opacity: 0;
}
.draw-active .phase-text {
    animation: textReveal 0.7s ease-out both;
    animation-delay: 2.4s;
}

/* Phase 8: Fill target blooms */
.v-fill-target {
    opacity: 0;
}
.draw-active .v-fill-target {
    animation: fillBloom 0.7s ease both;
    animation-delay: 2.7s;
}

@keyframes fillBloom {
    0% {
        opacity: 0;
    }
    100% {
        opacity: 0.95;
    }
}

@keyframes pearlPopIn {
    0% { opacity: 0; transform: scale(0); transform-origin: center; }
    70% { opacity: 1; transform: scale(1.25); transform-origin: center; }
    100% { opacity: 1; transform: scale(1); transform-origin: center; }
}

@keyframes nodePulse {
    0% { opacity: 0; transform: scale(0); transform-origin: center; }
    100% { opacity: 1; transform: scale(1); transform-origin: center; }
}

@keyframes jewelGlow {
    0% { opacity: 0; transform: scale(0); filter: brightness(2.5); transform-origin: center; }
    100% { opacity: 1; transform: scale(1); filter: brightness(1); transform-origin: center; }
}

@keyframes textReveal {
    0% { opacity: 0; transform: translateY(4px); }
    100% { opacity: 1; transform: translateY(0); }
}

/* Orbit rotations */
@keyframes spinOrbitCW {
    from { transform: rotate(-22deg); }
    to { transform: rotate(338deg); }
}

@keyframes spinOrbitCCW {
    from { transform: rotate(38deg); }
    to { transform: rotate(-322deg); }
}

.spin-orbit-1 {
    transform-origin: 512px 420px;
    animation: spinOrbitCW 32s linear infinite;
}

.spin-orbit-2 {
    transform-origin: 512px 420px;
    animation: spinOrbitCCW 24s linear infinite;
}

@media (prefers-reduced-motion: reduce) {
    .v-stroke {
        stroke-dashoffset: 0 !important;
        animation: none !important;
    }
    .phase-pearls,
    .phase-nodes,
    .phase-crown-jewels,
    .phase-text,
    .v-fill-target {
        opacity: 1 !important;
        animation: none !important;
    }
    .spin-orbit-1,
    .spin-orbit-2 {
        animation: none !important;
    }
}
</style>
