<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted, computed } from 'vue';
import axios from 'axios';

interface Slice {
    id: number;
    code: string;
    label: string;
    discount_value: number;
    discount_type: string;
    color: string;
    icon?: string;
}

interface Voucher {
    code: string;
    discount_text: string;
    starts_at: string | null;
    expires_at: string | null;
    min_order_value: number;
    is_used?: boolean;
    is_demo?: boolean;
}

const props = defineProps<{
    token: string;
    is_demo?: boolean;
    restaurant_id?: number;
    has_whatsapp_feature?: boolean;
    restaurant: {
        id?: number;
        name: string;
        logo: string | null;
        currency: string;
        menu_url?: string | null;
        order_url?: string | null;
    };
    order_number: string | null;
    is_claimed: boolean;
    is_expired: boolean;
    order_error_message?: string | null;
    claimed_voucher: Voucher | null;
    won_slice_index: number | null;
    slices: Slice[];
}>();

const canvasRef = ref<HTMLCanvasElement | null>(null);
const isSpinning = ref(false);
const hasWon = ref(props.is_claimed);
const voucher = ref<Voucher | null>(props.claimed_voucher);
const errorMessage = ref<string | null>(null);
const copied = ref(false);
const soundEnabled = ref(true);
const pointerDeflection = ref(0);
const lightBulbState = ref(0);
const whatsappPhone = ref('');

const whatsappShareUrl = computed(() => {
    if (!voucher.value) return '#';
    const rawDigits = whatsappPhone.value.replace(/\D/g, '');
    let cleanPhone = rawDigits;
    if (cleanPhone.startsWith('09') && cleanPhone.length === 10) {
        cleanPhone = '963' + cleanPhone.substring(1);
    }
    const restaurantName = props.restaurant.name || 'المطعم';
    const discountText = formatVoucherDisplay(voucher.value.discount_text);
    const text = `🎁 مبارك! لقد حصلت على ${discountText} من ${restaurantName}.\n` +
        `🎟️ كود الخصم: ${voucher.value.code}\n` +
        `⏳ صالح ابتداءً من: ${voucher.value.starts_at || 'الآن'}\n` +
        `📅 ينتهي في: ${voucher.value.expires_at}\n` +
        (voucher.value.min_order_value > 0 ? `🛒 الحد الأدنى للطلب: ${voucher.value.min_order_value} ${props.restaurant.currency}\n` : '') +
        (props.restaurant.menu_url ? `🍽️ رابط قائمة الطعام: ${props.restaurant.menu_url}\n` : '') +
        `✨ نتطلع لزيارتكم القادمة!`;
    const encoded = encodeURIComponent(text);
    return cleanPhone ? `https://wa.me/${cleanPhone}?text=${encoded}` : `https://wa.me/?text=${encoded}`;
});

// Physics & Animation state
let currentAngle = 0;
let animationFrameId: number | null = null;
let bulbInterval: ReturnType<typeof setInterval> | null = null;

// =========================================================================
// Synthesized Web Audio API (Zero external assets required)
// =========================================================================
let audioCtx: AudioContext | null = null;

function getAudioContext(): AudioContext | null {
    if (!audioCtx && typeof window !== 'undefined') {
        const AudioClass = window.AudioContext || (window as any).webkitAudioContext;
        if (AudioClass) {
            audioCtx = new AudioClass();
        }
    }
    if (audioCtx && audioCtx.state === 'suspended') {
        audioCtx.resume();
    }
    return audioCtx;
}

function playTickSound(pitchMultiplier = 1.0) {
    if (!soundEnabled.value) return;
    try {
        const ctx = getAudioContext();
        if (!ctx) return;
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();

        osc.type = 'triangle';
        const baseFreq = 520 * pitchMultiplier;
        osc.frequency.setValueAtTime(baseFreq, ctx.currentTime);
        osc.frequency.exponentialRampToValueAtTime(120, ctx.currentTime + 0.035);

        gain.gain.setValueAtTime(0.12, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.005, ctx.currentTime + 0.035);

        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start();
        osc.stop(ctx.currentTime + 0.035);
    } catch {
        // Audio error gracefully ignored
    }
}

function playFanfareSound() {
    if (!soundEnabled.value) return;
    try {
        const ctx = getAudioContext();
        if (!ctx) return;
        const chord = [261.63, 329.63, 392.00, 523.25, 659.25, 783.99]; // C Major Royal Arpeggio
        chord.forEach((freq, idx) => {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';

            const startTime = ctx.currentTime + idx * 0.08;
            osc.frequency.setValueAtTime(freq, startTime);

            gain.gain.setValueAtTime(0, startTime);
            gain.gain.linearRampToValueAtTime(0.2, startTime + 0.04);
            gain.gain.exponentialRampToValueAtTime(0.001, startTime + 0.8);

            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start(startTime);
            osc.stop(startTime + 0.85);
        });
    } catch {
        // Audio error gracefully ignored
    }
}

// =========================================================================
// Ultra-Crisp Canvas Wheel Drawing (Retina / High-DPI Display Scaled)
// =========================================================================
function drawWheel(angle: number) {
    const canvas = canvasRef.value;
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const dpr = window.devicePixelRatio || 1;
    const logicalSize = 360;
    const size = logicalSize * dpr;

    if (canvas.width !== size || canvas.height !== size) {
        canvas.width = size;
        canvas.height = size;
    }

    ctx.save();
    ctx.scale(dpr, dpr);
    ctx.clearRect(0, 0, logicalSize, logicalSize);

    const center = logicalSize / 2;
    const outerRadius = center - 8;
    const wheelRadius = outerRadius - 16;
    const numSlices = props.slices.length;
    if (numSlices === 0) {
        ctx.restore();
        return;
    }

    const sliceAngle = (2 * Math.PI) / numSlices;

    // 1. Ambient Outer Shadow & Gold Halo
    ctx.save();
    ctx.beginPath();
    ctx.arc(center, center, outerRadius, 0, 2 * Math.PI);
    ctx.shadowColor = 'rgba(212, 175, 55, 0.4)';
    ctx.shadowBlur = 24;
    ctx.fillStyle = '#0F0E0C';
    ctx.fill();
    ctx.restore();

    // 2. Outer Bezel (Brushed 3D Gold Ring with metallic border)
    const bezelGradient = ctx.createLinearGradient(0, 0, logicalSize, logicalSize);
    bezelGradient.addColorStop(0, '#FFF2B2');
    bezelGradient.addColorStop(0.3, '#D4AF37');
    bezelGradient.addColorStop(0.7, '#8C6D1F');
    bezelGradient.addColorStop(1, '#FCF6BA');

    ctx.beginPath();
    ctx.arc(center, center, outerRadius, 0, 2 * Math.PI);
    ctx.arc(center, center, wheelRadius, 0, 2 * Math.PI, true);
    ctx.fillStyle = bezelGradient;
    ctx.fill();

    // Inner rim dark groove
    ctx.beginPath();
    ctx.arc(center, center, wheelRadius, 0, 2 * Math.PI);
    ctx.strokeStyle = '#2A200B';
    ctx.lineWidth = 3;
    ctx.stroke();

    // 3. Animated Chasing LED Light Bulbs around the perimeter (24 bulbs)
    const numBulbs = 24;
    for (let i = 0; i < numBulbs; i++) {
        const bulbAngle = (i * 2 * Math.PI) / numBulbs;
        const bulbDistance = outerRadius - 8;
        const bx = center + bulbDistance * Math.cos(bulbAngle);
        const by = center + bulbDistance * Math.sin(bulbAngle);

        const isLit = (i + lightBulbState.value) % 2 === 0;

        ctx.beginPath();
        ctx.arc(bx, by, isLit ? 3.5 : 2.5, 0, 2 * Math.PI);
        if (isLit) {
            ctx.fillStyle = '#FFFDE7';
            ctx.shadowColor = '#FFD54F';
            ctx.shadowBlur = 8;
        } else {
            ctx.fillStyle = '#8C6D1F';
            ctx.shadowBlur = 0;
        }
        ctx.fill();
        ctx.shadowBlur = 0;
    }

    // 4. Rotating Slices
    ctx.save();
    ctx.translate(center, center);
    ctx.rotate(angle);

    for (let i = 0; i < numSlices; i++) {
        const slice = props.slices[i];
        const start = i * sliceAngle;
        const end = start + sliceAngle;

        ctx.beginPath();
        ctx.moveTo(0, 0);
        ctx.arc(0, 0, wheelRadius, start, end);
        ctx.closePath();

        // Slice background with subtle radial depth
        const sliceGrad = ctx.createRadialGradient(0, 0, 20, 0, 0, wheelRadius);
        sliceGrad.addColorStop(0, slice.color);
        sliceGrad.addColorStop(1, shadeColor(slice.color, -25));
        ctx.fillStyle = sliceGrad;
        ctx.fill();

        // Slice separator line
        ctx.strokeStyle = 'rgba(255, 255, 255, 0.28)';
        ctx.lineWidth = 1.8;
        ctx.stroke();

        // Edge golden stud at separator
        const studX = (wheelRadius - 4) * Math.cos(start);
        const studY = (wheelRadius - 4) * Math.sin(start);
        ctx.beginPath();
        ctx.arc(studX, studY, 2, 0, 2 * Math.PI);
        ctx.fillStyle = '#FCF6BA';
        ctx.fill();

        // Text & Icon Rendering inside slice
        ctx.save();
        ctx.rotate(start + sliceAngle / 2);
        ctx.textAlign = 'right';
        ctx.textBaseline = 'middle';

        // Prize Text
        ctx.fillStyle = '#FFFFFF';
        ctx.font = 'bold 13px "Tajawal", "Cairo", sans-serif';
        ctx.shadowColor = 'rgba(0, 0, 0, 0.8)';
        ctx.shadowBlur = 5;

        const iconStr = slice.icon ? `${slice.icon} ` : '';
        ctx.fillText(`${iconStr}${formatSliceLabel(slice.label)}`, wheelRadius - 16, 0);

        ctx.restore();
    }

    ctx.restore(); // Restore center rotation

    // 5. Golden Center Inner Hub Outline
    ctx.beginPath();
    ctx.arc(center, center, 42, 0, 2 * Math.PI);
    ctx.fillStyle = '#1A1814';
    ctx.fill();
    ctx.strokeStyle = bezelGradient;
    ctx.lineWidth = 4;
    ctx.stroke();

    ctx.restore(); // Restore global canvas scale
}

// Helper to darken hex colors for radial slice gradient
function shadeColor(color: string, percent: number): string {
    let num = parseInt(color.replace('#', ''), 16);
    let r = (num >> 16) + percent;
    let b = ((num >> 8) & 0x00ff) + percent;
    let g = (num & 0x0000ff) + percent;
    return (
        '#' +
        (
            0x1000000 +
            (r < 255 ? (r < 1 ? 0 : r) : 255) * 0x10000 +
            (b < 255 ? (b < 1 ? 0 : b) : 255) * 0x100 +
            (g < 255 ? (g < 1 ? 0 : g) : 255)
        )
            .toString(16)
            .slice(1)
    );
}

// =========================================================================
// Real-time Physics Engine: Instant-Launch + Deceleration Easing
// =========================================================================
async function spinWheel() {
    if (isSpinning.value || hasWon.value || props.is_expired || props.is_claimed || props.order_error_message || props.slices.length === 0) return;

    errorMessage.value = null;
    isSpinning.value = true;
    getAudioContext();

    const numSlices = props.slices.length;
    const sliceAngle = (2 * Math.PI) / numSlices;

    let currentSliceTracker = Math.floor(currentAngle / sliceAngle);

    // Track API call state
    let apiCompleted = false;
    let wonIndex = 0;
    let wonVoucherData: Voucher | null = null;

    // Send API spin request
    axios.post(`/wheel/${props.token}/spin`, {
        restaurant_id: props.restaurant_id || props.restaurant?.id,
    })
        .then(res => {
            const data = res.data;
            wonIndex = data.won_index !== undefined ? data.won_index : 0;
            wonVoucherData = data.voucher;
            apiCompleted = true;
        })
        .catch(err => {
            isSpinning.value = false;
            if (animationFrameId) cancelAnimationFrame(animationFrameId);
            errorMessage.value = err.response?.data?.message || 'حدث خطأ أثناء تدوير العجلة، يرجى المحاولة ثانية.';
        });

    const spinStartTime = performance.now();
    let lastTime = spinStartTime;

    // Animation phases:
    // 'spinning': High-speed rotation while waiting for API
    // 'decelerating': Smooth glide with friction directly onto winning slice
    let phase: 'spinning' | 'decelerating' = 'spinning';
    let decelStartTime = 0;
    const decelDuration = 4600; // 4.6 seconds of luxury deceleration
    let decelStartAngle = 0;
    let totalDecelDistance = 0;

    const freeSpinSpeed = 26; // High angular velocity (rad/s) ~ 4.1 rev/s

    const animate = (time: number) => {
        if (!isSpinning.value) return;

        const dt = Math.min((time - lastTime) / 1000, 0.05);
        lastTime = time;

        if (phase === 'spinning') {
            // Instant, exhilarating rotation on the very first frame!
            currentAngle += freeSpinSpeed * dt;
            lightBulbState.value = Math.floor(time / 100) % 2;

            const elapsed = time - spinStartTime;
            // When API has responded and at least 500ms of free-spin elapsed
            if (apiCompleted && elapsed >= 500) {
                phase = 'decelerating';
                decelStartTime = time;
                decelStartAngle = currentAngle;

                // 12 o'clock pointer is at 3*PI/2 (270 deg)
                const targetPointerAngle = 3 * Math.PI / 2;
                const sliceCenter = (wonIndex + 0.5) * sliceAngle;
                // Add tiny organic jitter within slice (±18% of slice) for authentic mechanical feel
                const jitter = (Math.random() - 0.5) * (sliceAngle * 0.36);
                const desiredFinalMod = ((targetPointerAngle - sliceCenter + jitter) % (2 * Math.PI) + 2 * Math.PI) % (2 * Math.PI);

                const currentMod = ((decelStartAngle % (2 * Math.PI)) + 2 * Math.PI) % (2 * Math.PI);
                let forwardDistance = (desiredFinalMod - currentMod) % (2 * Math.PI);
                if (forwardDistance < 0) forwardDistance += 2 * Math.PI;

                // 5 full rotations during deceleration
                const extraFullTurns = 5 * (2 * Math.PI);
                totalDecelDistance = extraFullTurns + forwardDistance;
            }
        } else if (phase === 'decelerating') {
            const decelElapsed = time - decelStartTime;
            const progress = Math.min(decelElapsed / decelDuration, 1);

            // Quartic ease-out curve: starts at speed, gradually glides to a stop
            const easeOutProgress = 1 - Math.pow(1 - progress, 4);

            currentAngle = decelStartAngle + totalDecelDistance * easeOutProgress;

            // Lights slow down with the wheel
            lightBulbState.value = Math.floor(time / (100 + progress * 250)) % 2;

            if (progress >= 1) {
                // Settle firmly on target angle
                currentAngle = decelStartAngle + totalDecelDistance;
                drawWheel(currentAngle);
                isSpinning.value = false;
                hasWon.value = true;
                voucher.value = wonVoucherData;
                playFanfareSound();
                triggerConfetti();
                return;
            }
        }

        // Mechanical Ticking audio & Needle Deflection on slice borders
        const newTracker = Math.floor(currentAngle / sliceAngle);
        if (newTracker !== currentSliceTracker) {
            currentSliceTracker = newTracker;
            const speedFactor = phase === 'spinning' ? 1.0 : Math.max(0, 1 - (time - decelStartTime) / decelDuration);
            const pitch = Math.max(0.7, 0.75 + speedFactor * 0.65);
            playTickSound(pitch);

            // Needle mechanical wobble
            pointerDeflection.value = -Math.max(6, 22 * speedFactor);
            setTimeout(() => {
                pointerDeflection.value = 0;
            }, 45);
        }

        drawWheel(currentAngle);
        animationFrameId = requestAnimationFrame(animate);
    };

    animationFrameId = requestAnimationFrame(animate);
}

// Copy voucher code
async function copyCode() {
    if (!voucher.value?.code) return;
    try {
        await navigator.clipboard.writeText(voucher.value.code);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2500);
    } catch {
        copied.value = true;
    }
}

// Reset for Demo or Testing
async function resetDemoSpin() {
    if (props.is_demo) {
        hasWon.value = false;
        voucher.value = null;
        errorMessage.value = null;
        drawWheel(currentAngle);
        return;
    }

    try {
        await axios.post(`/wheel/${props.token}/reset`);
        hasWon.value = false;
        voucher.value = null;
        errorMessage.value = null;
        drawWheel(currentAngle);
    } catch {
        router.reload();
    }
}

// Helper formatting functions for Syrian currency without decimals and professional wording
function formatSliceLabel(label: string): string {
    if (!label) return '';
    let clean = label.trim();
    // Normalize SYP or syp to ل.س
    clean = clean.replace(/SYP/gi, 'ل.س');
    // Remove .00 decimals (Syrian currency has no decimals)
    clean = clean.replace(/(\d+)\.00(?!\d)/g, '$1');

    // If slice label is like "25 ل.س" or "25.00" or "SYP 25", ensure it has "خصم" prefix
    if (/^\d+\s*ل\.س$/.test(clean)) {
        return `خصم ${clean}`;
    }
    if (/^\d+$/.test(clean)) {
        return `خصم ${clean} ل.س`;
    }
    // If it starts with "خصم بقيمة ", on the tight wheel slice shorten to "خصم "
    if (clean.startsWith('خصم بقيمة ')) {
        return `خصم ${clean.substring(10).trim()}`;
    }
    return clean;
}

function formatVoucherDisplay(text?: string | null): string {
    if (!text) return 'خصم خاص';
    let clean = text.trim();

    // Standardize SYP to ل.س
    clean = clean.replace(/SYP/gi, 'ل.س');
    // Remove trailing .00
    clean = clean.replace(/(\d+)\.00(?!\d)/g, '$1');

    // Percentage check (e.g., "15%" or "خصم 15%")
    const pctMatch = clean.match(/(\d+)\s*%/);
    if (pctMatch) {
        return `خصم بقيمة ${pctMatch[1]}%`;
    }

    // Number + Currency or amount check (e.g., "25 ل.س", "25", "خصم 25 ل.س")
    const numMatch = clean.match(/(\d+)/);
    if (numMatch && (clean.includes('ل.س') || clean.includes('خصم') || !isNaN(Number(clean)))) {
        return `خصم بقيمة ${numMatch[1]} ل.س`;
    }

    if (clean.startsWith('خصم بقيمة ')) {
        return clean;
    }
    if (clean.startsWith('خصم ')) {
        return `خصم بقيمة ${clean.substring(4).trim()}`;
    }

    return `خصم بقيمة ${clean}`;
}

function formatNumber(num: number | string): string {
    const val = parseFloat(String(num));
    if (isNaN(val)) return '0';
    return val % 1 === 0 ? String(Math.round(val)) : val.toFixed(2).replace(/\.00$/, '');
}

function normalizeCurrency(curr?: string): string {
    if (!curr || curr.toUpperCase() === 'SYP') return 'ل.س';
    return curr;
}

// Share via WhatsApp
function shareVoucherWhatsApp() {
    if (!voucher.value?.code) return;
    const discountFormatted = formatVoucherDisplay(voucher.value.discount_text);
    const msg = encodeURIComponent(
        `🎉 لقد ربحت للتو ${discountFormatted} في ${props.restaurant.name} عبر عجلة الحظ الذكية! استخدم كود الخصم: ${voucher.value.code}`
    );
    window.open(`https://wa.me/?text=${msg}`, '_blank');
}

// Confetti burst on win
function triggerConfetti() {
    const confettiCount = 80;
    const container = document.getElementById('confetti-container');
    if (!container) return;

    for (let i = 0; i < confettiCount; i++) {
        const el = document.createElement('div');
        el.className = 'confetti';
        el.style.left = Math.random() * 100 + 'vw';
        el.style.animationDelay = Math.random() * 0.4 + 's';
        el.style.backgroundColor = ['#D4AF37', '#10b981', '#3b82f6', '#ec4899', '#f59e0b', '#ffffff'][
            Math.floor(Math.random() * 6)
        ];
        container.appendChild(el);
        setTimeout(() => el.remove(), 4000);
    }
}

onMounted(() => {
    // If already claimed, align wheel immediately to winning slice
    if (props.won_slice_index !== null && props.won_slice_index !== undefined && props.slices.length > 0) {
        const sliceAngle = (2 * Math.PI) / props.slices.length;
        currentAngle = 3 * Math.PI / 2 - (props.won_slice_index + 0.5) * sliceAngle;
    }

    drawWheel(currentAngle);

    // Alternating LED bulb flasher
    bulbInterval = setInterval(() => {
        lightBulbState.value = (lightBulbState.value + 1) % 2;
        if (!isSpinning.value) {
            drawWheel(currentAngle);
        }
    }, 450);
});

onUnmounted(() => {
    if (animationFrameId) cancelAnimationFrame(animationFrameId);
    if (bulbInterval) clearInterval(bulbInterval);
});
</script>

<template>
    <Head :title="`عجلة الحظ التفاعلية - ${restaurant.name}`" />

    <div
        dir="rtl"
        class="min-h-screen bg-[#070707] text-[#F3F4F6] flex flex-col items-center justify-between p-4 selection:bg-[#D4AF37] selection:text-black relative overflow-x-hidden font-tajawal antialiased"
    >
        <!-- Confetti container -->
        <div id="confetti-container" class="pointer-events-none fixed inset-0 overflow-hidden z-50"></div>

        <!-- Atmospheric Ambient Gold Spotlight Background -->
        <div class="fixed inset-0 pointer-events-none z-0 overflow-hidden">
            <div class="absolute -top-32 left-1/2 -translate-x-1/2 w-[700px] h-[500px] rounded-full bg-[radial-gradient(ellipse_at_top,rgba(212,175,55,0.12),transparent_70%)] blur-3xl"></div>
            <div class="absolute bottom-0 left-1/2 -translate-x-1/2 w-[600px] h-[400px] rounded-full bg-[radial-gradient(ellipse_at_bottom,rgba(212,175,55,0.06),transparent_70%)] blur-3xl"></div>
        </div>

        <!-- Top Navigation Bar -->
        <header class="w-full max-w-md pt-3 pb-2 flex items-center justify-between relative z-10">
            <!-- Brand Logo & Name (Clickable Link to Restaurant Menu) -->
            <component
                :is="restaurant.menu_url ? 'a' : 'div'"
                :href="restaurant.menu_url || undefined"
                class="flex items-center gap-3 group transition-transform active:scale-95"
                :title="restaurant.menu_url ? 'العودة لقائمة طعام المطعم' : restaurant.name"
            >
                <div class="w-12 h-12 rounded-2xl bg-black/60 border border-[#D4AF37]/40 p-1 shadow-[0_0_15px_rgba(212,175,55,0.15)] flex items-center justify-center overflow-hidden shrink-0 group-hover:border-[#D4AF37] transition">
                    <img
                        :src="restaurant.logo || '/images/tedallal-golden-crop.png'"
                        :alt="restaurant.name"
                        class="w-full h-full object-contain"
                        onerror="this.src='/images/tedallal-golden-logo.png'"
                    />
                </div>
                <div class="flex flex-col text-start">
                    <div class="flex items-center gap-1.5">
                        <h1 class="text-base sm:text-lg font-bold font-serif text-white tracking-tight leading-tight group-hover:text-[#FCF6BA] transition">
                            {{ restaurant.name }}
                        </h1>
                        <span v-if="restaurant.menu_url" class="text-xs text-[#D4AF37] opacity-80 group-hover:opacity-100 transition-opacity">↗</span>
                    </div>
                    <div class="flex items-center gap-2 mt-0.5">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-[#D4AF37]/15 text-[#FCF6BA] border border-[#D4AF37]/30">
                            ✨ عجلة الحظ والهدايا
                        </span>
                        <span v-if="order_number" class="text-[11px] text-[#9CA3AF] font-mono">
                            #{{ order_number }}
                        </span>
                    </div>
                </div>
            </component>

            <!-- Controls (Sound Toggle & Demo Indicator) -->
            <div class="flex items-center gap-2">
                <span
                    v-if="is_demo"
                    class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/40"
                >
                    وضع المعاينة التجريبي
                </span>

                <button
                    @click="soundEnabled = !soundEnabled"
                    type="button"
                    class="p-2.5 rounded-xl bg-white/5 border border-white/10 hover:border-white/20 text-[#9CA3AF] hover:text-white transition-all cursor-pointer"
                    :title="soundEnabled ? 'كتم المؤثرات الصوتية' : 'تفعيل المؤثرات الصوتية'"
                >
                    <span v-if="soundEnabled" class="text-xs">🔊</span>
                    <span v-else class="text-xs">🔇</span>
                </button>
            </div>
        </header>

        <!-- Main Gameplay Arena -->
        <main class="w-full max-w-md flex flex-col items-center justify-center my-auto py-2 relative z-10">

            <!-- State 1: Expired Token or Order Error -->
            <div
                v-if="is_expired || order_error_message"
                class="bg-[#121212]/90 border border-red-500/30 rounded-3xl p-8 text-center backdrop-blur-xl shadow-2xl w-full"
            >
                <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-red-500/10 text-red-400 flex items-center justify-center text-3xl">
                    {{ order_error_message ? '🚫' : '⏳' }}
                </div>
                <h2 class="text-xl font-bold font-serif text-white mb-2">
                    {{ order_error_message ? 'تنبيه بشأن الطلب' : 'عذراً، انتهت صلاحية الرابط' }}
                </h2>
                <p class="text-sm text-[#9CA3AF] leading-relaxed">
                    {{ order_error_message || 'انتهت صلاحية جلسة عجلة الحظ الخاصة بهذه الفاتورة. نتمنى لك حظاً سعيداً في زيارتك القادمة لمطعمنا!' }}
                </p>
            </div>

            <!-- State 2: Wheel Arena -->
            <div v-else class="w-full flex flex-col items-center">

                <!-- The Interactive Wheel Container -->
                <div class="relative w-[340px] h-[340px] sm:w-[380px] sm:h-[380px] flex items-center justify-center my-3 select-none">

                    <!-- Slanted Ribbon / Void Stamp Banner when QR is already claimed -->
                    <div
                        v-if="props.is_claimed"
                        class="absolute z-40 inset-x-[-6%] top-1/2 -translate-y-1/2 -rotate-12 pointer-events-none select-none drop-shadow-[0_15px_30px_rgba(0,0,0,0.95)]"
                    >
                        <div class="bg-gradient-to-r from-red-800 via-red-600 to-red-800 text-white font-black text-center py-2.5 px-3 border-y-4 border-dashed border-red-200/90 shadow-2xl flex items-center justify-center gap-2 tracking-wide">
                            <span class="text-base sm:text-lg">🚫</span>
                            <span class="text-xs sm:text-sm font-black uppercase whitespace-nowrap">كود تالف — تم استخدام هذا الرمز مسبقاً</span>
                            <span class="text-base sm:text-lg">🚫</span>
                        </div>
                    </div>

                    <!-- Top Mechanical Ticker / Indicator Needle (With Dynamic Kickback Physics) -->
                    <div
                        class="absolute -top-4 z-30 flex flex-col items-center drop-shadow-[0_8px_16px_rgba(0,0,0,0.9)] transition-transform duration-75 origin-top"
                        :class="{ 'opacity-60 filter grayscale-[50%]': props.is_claimed }"
                        :style="{ transform: `rotate(${pointerDeflection}deg)` }"
                    >
                        <div class="w-6 h-6 rounded-full bg-gradient-to-b from-[#FFF2B2] via-[#D4AF37] to-[#8C6D1F] border-2 border-black flex items-center justify-center shadow-md">
                            <span class="w-2 h-2 rounded-full bg-red-500"></span>
                        </div>
                        <div class="w-0 h-0 border-l-[11px] border-l-transparent border-r-[11px] border-r-transparent border-t-[28px] border-t-[#D4AF37] -mt-1 filter drop-shadow"></div>
                    </div>

                    <!-- The High-DPI Canvas Wheel -->
                    <canvas
                        ref="canvasRef"
                        class="w-full h-full rounded-full select-none transition-all duration-500"
                        :class="[
                            props.is_claimed
                                ? 'filter grayscale-[85%] contrast-75 opacity-40 cursor-not-allowed pointer-events-none'
                                : 'cursor-pointer'
                        ]"
                        @click="spinWheel"
                    ></canvas>

                    <!-- Center 3D Spin Button Hub (Always Visible & Dimensional) -->
                    <button
                        @click="spinWheel"
                        :disabled="isSpinning || hasWon || props.is_claimed"
                        type="button"
                        class="absolute z-20 w-20 h-20 rounded-full bg-gradient-to-br from-[#FFF2B2] via-[#D4AF37] to-[#8C6D1F] text-black font-extrabold text-sm tracking-wide shadow-[0_8px_25px_rgba(0,0,0,0.8),inset_0_2px_2px_rgba(255,255,255,0.6)] flex flex-col items-center justify-center border-4 border-[#1A1814] active:scale-95 transition-all group disabled:cursor-not-allowed"
                        :class="[
                            !hasWon && !isSpinning && !props.is_claimed ? 'hover:scale-105 cursor-pointer' : '',
                            props.is_claimed ? 'opacity-70 filter grayscale-[40%]' : (hasWon ? 'opacity-95' : '')
                        ]"
                    >
                        <!-- Claimed State: Locked -->
                        <template v-if="props.is_claimed">
                            <span class="text-base leading-none">🔒</span>
                            <span class="font-serif font-black text-[10px] mt-0.5 text-stone-900">مُستخدم</span>
                        </template>

                        <!-- Ready to Spin State -->
                        <template v-else-if="!hasWon && !isSpinning">
                            <span class="absolute -inset-2 rounded-full border border-[#D4AF37]/50 animate-ping pointer-events-none"></span>
                            <span class="text-base leading-none">🎡</span>
                            <span class="font-serif font-black text-xs mt-0.5">دَوّرْ!</span>
                        </template>

                        <!-- Spinning State: Golden Crown with subtle pulse (No Gear) -->
                        <template v-else-if="isSpinning">
                            <span class="text-xl animate-pulse">👑</span>
                            <span class="text-[9px] font-bold mt-0.5 text-black/90">حظاً موفقاً</span>
                        </template>

                        <!-- Already Claimed State (won in current session) -->
                        <template v-else>
                            <span class="text-lg">👑</span>
                            <span class="text-[10px] font-bold">مبروك!</span>
                        </template>
                    </button>
                </div>

                <!-- Action Controls Under the Wheel -->
                <div class="mt-4 text-center w-full max-w-sm px-2">

                    <!-- Unclaimed State: Prompt and Primary Spin CTA -->
                    <div v-if="!hasWon" class="space-y-3">
                        <p class="text-xs text-[#FCF6BA] font-medium leading-relaxed">
                            اضغط لتدوير العجلة الآن واربح كود خصم حصري مضمون لزيارتك القادمة!
                        </p>

                        <button
                            @click="spinWheel"
                            :disabled="isSpinning"
                            type="button"
                            class="w-full py-4 px-8 rounded-full bg-gradient-to-r from-[#e5b85c] via-[#f7d58b] to-[#d89f38] text-black font-extrabold text-base shadow-[0_4px_30px_rgba(229,184,92,0.4)] hover:shadow-[0_6px_35px_rgba(229,184,92,0.6)] hover:scale-105 active:scale-95 transition-all disabled:opacity-60 disabled:cursor-not-allowed cursor-pointer flex items-center justify-center gap-2"
                        >
                            <span v-if="!isSpinning">🎁 اضغط لتدوير عجلة الحظ</span>
                            <span v-else class="flex items-center gap-2">
                                <span class="animate-pulse text-lg">✨</span>
                                <span>العجلة تدور الآن... حظاً موفقاً!</span>
                            </span>
                        </button>

                        <div
                            v-if="errorMessage"
                            class="text-red-400 text-xs font-semibold bg-red-950/40 border border-red-800/50 rounded-xl p-3"
                        >
                            {{ errorMessage }}
                        </div>
                    </div>

                    <!-- Already Claimed Celebration / Voucher Details -->
                    <div v-else-if="voucher" class="space-y-4">
                        <!-- Success / Notice Banner -->
                        <div
                            v-if="props.is_claimed"
                            class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-red-500/20 border border-red-500/40 text-red-200 text-xs font-bold"
                        >
                            <span>🚫 هذا الرمز تالف — تم تدوير العجلة لهذا الطلب سابقاً</span>
                        </div>
                        <div
                            v-else
                            class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-emerald-500/15 border border-emerald-500/30 text-emerald-300 text-xs font-bold"
                        >
                            <span>✓ تم تدوير العجلة واستلام جائزتك بنجاح</span>
                        </div>

                        <!-- Ticket / Voucher Card (Bespoke Luxury Ticket Design) -->
                        <div class="relative bg-gradient-to-b from-[#161616] via-[#111111] to-[#0A0A0A] border-2 border-[#D4AF37]/50 rounded-3xl p-6 shadow-[0_20px_50px_rgba(0,0,0,0.8),inset_0_1px_1px_rgba(255,255,255,0.1)] text-center overflow-hidden">
                            <!-- Side Perforations / Ticket Notches -->
                            <div class="absolute -start-3 top-1/2 -translate-y-1/2 w-6 h-6 rounded-full bg-[#070707] border-e-2 border-[#D4AF37]/50"></div>
                            <div class="absolute -end-3 top-1/2 -translate-y-1/2 w-6 h-6 rounded-full bg-[#070707] border-s-2 border-[#D4AF37]/50"></div>

                            <span class="text-3xl block mb-2">{{ props.is_claimed ? '🎟️' : '🎉' }}</span>
                            <h3 class="text-base sm:text-lg font-bold font-serif text-white">
                                {{ props.is_claimed ? '🏆 لقد حصلت بالفعل على جائزتك لهذا الطلب:' : 'مبروك! لقد ربحت:' }}
                            </h3>
                            <div class="font-serif text-3xl sm:text-4xl font-extrabold bg-gradient-to-r from-[#D4AF37] via-[#F3E5AB] to-[#B38728] text-transparent bg-clip-text my-2">
                                {{ formatVoucherDisplay(voucher.discount_text) }}
                            </div>

                            <!-- Single-Use Voucher Code Container -->
                            <div class="my-4 p-4 rounded-2xl bg-black/60 border border-dashed border-[#D4AF37]/40 flex flex-col items-center gap-2">
                                <span class="text-xs text-[#9CA3AF] font-medium">كوبون خصم زيارتك القادمة:</span>
                                <div class="flex items-center justify-center gap-3 w-full">
                                    <span class="font-mono text-2xl font-black tracking-widest text-emerald-400 select-all" dir="ltr">
                                        {{ voucher.code }}
                                    </span>
                                    <button
                                        @click="copyCode"
                                        type="button"
                                        class="px-3 py-1.5 rounded-xl bg-white/10 hover:bg-white/20 text-white transition active:scale-95 text-xs font-bold flex items-center gap-1 border border-white/10 cursor-pointer"
                                    >
                                        <span>{{ copied ? 'تم النسخ ✓' : 'نسخ الكود' }}</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Validity Rules -->
                            <div class="space-y-1.5 text-xs text-[#9CA3AF] text-start bg-black/40 p-3 rounded-xl border border-white/5">
                                <div class="flex items-center gap-2 text-[#FCF6BA]">
                                    <span>📅</span>
                                    <span>صالح لزيارتك القادمة ابتداءً من: {{ voucher.starts_at || 'الآن' }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span>⏳</span>
                                    <span>ينتهي في: {{ voucher.expires_at }} (صالح لمرة واحدة)</span>
                                </div>
                                <div v-if="voucher.min_order_value > 0" class="flex items-center gap-2">
                                    <span>🛒</span>
                                    <span>الحد الأدنى للطلب: {{ formatNumber(voucher.min_order_value) }} {{ normalizeCurrency(restaurant.currency) }}</span>
                                </div>
                                <div v-if="voucher.is_used" class="mt-2 p-2 rounded-lg bg-red-950/50 border border-red-800/60 text-red-300 font-bold text-center text-xs flex items-center justify-center gap-1.5">
                                    <span>⚠️</span>
                                    <span>تم استهلاك هذا الكوبون مسبقاً في عملية شراء (تالف)</span>
                                </div>
                            </div>

                            <!-- WhatsApp Save / Share Box (When whatsapp_reminders is enabled for the restaurant) -->
                            <div v-if="props.has_whatsapp_feature" class="mt-3 p-3 rounded-2xl bg-gradient-to-r from-emerald-950/70 via-emerald-900/50 to-emerald-950/70 border border-emerald-500/50 text-center shadow-lg shadow-emerald-950/40">
                                <div class="flex items-center justify-center gap-1.5 text-emerald-300 font-bold text-xs mb-1.5">
                                    <span class="text-base">💬</span>
                                    <span>احفظ هديتك في واتساب لزيارتك القادمة</span>
                                </div>
                                <p class="text-[11px] text-emerald-100/70 mb-2 leading-relaxed">
                                    أدخل رقم هاتفك لتصلك تفاصيل الكوبون، أو اضغط مباشرة لإرساله لنفسك:
                                </p>
                                <div class="flex items-center gap-2">
                                    <input
                                        v-model="whatsappPhone"
                                        type="tel"
                                        dir="ltr"
                                        placeholder="رقم الهاتف (اختياري)"
                                        class="w-full text-center text-xs py-2 px-3 rounded-xl bg-black/60 border border-emerald-500/40 text-white placeholder-stone-500 focus:outline-none focus:border-emerald-400 font-mono"
                                    />
                                    <a
                                        :href="whatsappShareUrl"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="shrink-0 inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition shadow-md shadow-emerald-950/50 cursor-pointer active:scale-95"
                                    >
                                        <span>📱</span>
                                        <span>واتساب</span>
                                    </a>
                                </div>
                            </div>

                            <!-- Action Buttons: Return to Menu & Copy Code -->
                            <div class="grid grid-cols-2 gap-2 mt-4 pt-3 border-t border-white/10">
                                <a
                                    v-if="restaurant.menu_url"
                                    :href="restaurant.menu_url"
                                    class="py-2.5 px-3 rounded-xl bg-gradient-to-r from-[#D4AF37]/25 via-[#FCF6BA]/20 to-[#D4AF37]/25 hover:from-[#D4AF37]/35 hover:to-[#D4AF37]/35 text-[#FCF6BA] border border-[#D4AF37]/50 text-xs font-bold transition flex items-center justify-center gap-1.5 cursor-pointer shadow-sm hover:scale-[1.02] active:scale-95"
                                >
                                    <span>🍽️ تصفح قائمة الطعام</span>
                                </a>
                                <button
                                    v-else
                                    @click="copyCode"
                                    type="button"
                                    class="py-2.5 px-3 rounded-xl bg-white/10 hover:bg-white/20 text-white border border-white/10 text-xs font-bold transition flex items-center justify-center gap-1 cursor-pointer active:scale-95"
                                >
                                    <span>📋 {{ copied ? 'تم النسخ ✓' : 'حفظ الكود' }}</span>
                                </button>

                                <button
                                    @click="copyCode"
                                    type="button"
                                    class="py-2.5 px-3 rounded-xl bg-white/10 hover:bg-white/20 text-white border border-white/10 text-xs font-bold transition flex items-center justify-center gap-1 cursor-pointer active:scale-95"
                                >
                                    <span>📋 {{ copied ? 'تم النسخ ✓' : 'نسخ كود الخصم' }}</span>
                                </button>
                            </div>
                        </div>

                        <!-- Reset / Re-test Option for Demo or Testing -->
                        <div class="pt-2">
                            <button
                                @click="resetDemoSpin"
                                type="button"
                                class="text-xs text-[#D4AF37] hover:text-[#FCF6BA] underline cursor-pointer"
                            >
                                🔄 تدوير مرة أخرى (وضع المعاينة والتدريب)
                            </button>
                        </div>
                    </div>

                </div>

            </div>

        </main>

        <!-- Footer -->
        <footer class="w-full max-w-md pt-3 pb-3 text-center text-[11px] text-[#9CA3AF]/60 relative z-10">
            منظومة تدلّل الذكية لأتمتة المطاعم • ضيافة فاخرة وعروض مبهجة
        </footer>
    </div>
</template>

<style scoped>
@keyframes confettiFall {
    0% {
        transform: translateY(-20px) rotate(0deg);
        opacity: 1;
    }
    100% {
        transform: translateY(105vh) rotate(720deg);
        opacity: 0;
    }
}

:deep(.confetti) {
    position: absolute;
    top: 0;
    width: 10px;
    height: 14px;
    border-radius: 2px;
    opacity: 0.95;
    animation: confettiFall 3.5s linear forwards;
}
</style>
