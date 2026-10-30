<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';

interface Slice {
    id: number;
    code: string;
    label: string;
    discount_value: number;
    discount_type: string;
    color: string;
}

interface Voucher {
    code: string;
    discount_text: string;
    starts_at: string | null;
    expires_at: string | null;
    min_order_value: number;
    is_used?: boolean;
}

const props = defineProps<{
    token: string;
    restaurant: {
        name: string;
        logo: string | null;
        currency: string;
    };
    order_number: string | null;
    is_claimed: boolean;
    is_expired: boolean;
    claimed_voucher: Voucher | null;
    slices: Slice[];
}>();

const canvasRef = ref<HTMLCanvasElement | null>(null);
const isSpinning = ref(false);
const hasWon = ref(props.is_claimed);
const voucher = ref<Voucher | null>(props.claimed_voucher);
const errorMessage = ref<string | null>(null);
const copied = ref(false);

let currentAngle = 0;
let animationFrameId: number | null = null;

// Audio context for sound effects using Web Audio API (no external files needed)
let audioCtx: (AudioContext | null) = null;
const getAudioContext = () => {
    if (!audioCtx) {
        const AudioContextClass = window.AudioContext || (window as any).webkitAudioContext;
        if (AudioContextClass) {
            audioCtx = new AudioContextClass();
        }
    }
    if (audioCtx && audioCtx.state === 'suspended') {
        audioCtx.resume();
    }
    return audioCtx;
};

const playTickSound = () => {
    try {
        const ctx = getAudioContext();
        if (!ctx) return;
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'triangle';
        osc.frequency.setValueAtTime(600, ctx.currentTime);
        osc.frequency.exponentialRampToValueAtTime(150, ctx.currentTime + 0.04);
        gain.gain.setValueAtTime(0.15, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.04);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start();
        osc.stop(ctx.currentTime + 0.04);
    } catch {
        // Audio error ignored
    }
};

const playFanfareSound = () => {
    try {
        const ctx = getAudioContext();
        if (!ctx) return;
        const notes = [261.63, 329.63, 392.00, 523.25];
        notes.forEach((freq, idx) => {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(freq, ctx.currentTime + idx * 0.1);
            gain.gain.setValueAtTime(0, ctx.currentTime + idx * 0.1);
            gain.gain.linearRampToValueAtTime(0.25, ctx.currentTime + idx * 0.1 + 0.05);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + idx * 0.1 + 0.4);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start(ctx.currentTime + idx * 0.1);
            osc.stop(ctx.currentTime + idx * 0.1 + 0.4);
        });
    } catch {
        // Audio error ignored
    }
};

// Canvas Drawing
const drawWheel = (angle: number) => {
    const canvas = canvasRef.value;
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const size = canvas.width;
    const center = size / 2;
    const radius = center - 16;
    const numSlices = props.slices.length;
    if (numSlices === 0) return;

    const sliceAngle = (2 * Math.PI) / numSlices;

    ctx.clearRect(0, 0, size, size);

    // Outer Glow / Border
    ctx.save();
    ctx.translate(center, center);
    ctx.rotate(angle);

    // Draw Slices
    for (let i = 0; i < numSlices; i++) {
        const slice = props.slices[i];
        const start = i * sliceAngle;
        const end = start + sliceAngle;

        ctx.beginPath();
        ctx.moveTo(0, 0);
        ctx.arc(0, 0, radius, start, end);
        ctx.closePath();

        // Slice background
        ctx.fillStyle = slice.color;
        ctx.fill();

        // Slice borders
        ctx.strokeStyle = 'rgba(255, 255, 255, 0.35)';
        ctx.lineWidth = 2;
        ctx.stroke();

        // Draw Slice Text
        ctx.save();
        ctx.rotate(start + sliceAngle / 2);
        ctx.textAlign = 'right';
        ctx.fillStyle = '#ffffff';
        ctx.font = 'bold 15px "Tajawal", sans-serif';
        ctx.shadowColor = 'rgba(0, 0, 0, 0.6)';
        ctx.shadowBlur = 4;
        ctx.fillText(slice.label, radius - 24, 5);
        ctx.restore();
    }

    // Outer Rim
    ctx.restore();

    ctx.beginPath();
    ctx.arc(center, center, radius, 0, 2 * Math.PI);
    ctx.strokeStyle = '#f59e0b';
    ctx.lineWidth = 8;
    ctx.stroke();

    ctx.beginPath();
    ctx.arc(center, center, radius + 5, 0, 2 * Math.PI);
    ctx.strokeStyle = '#78350f';
    ctx.lineWidth = 2;
    ctx.stroke();

    // Golden decorative dots around the rim
    const numDots = numSlices * 3;
    for (let i = 0; i < numDots; i++) {
        const dotAngle = (i * 2 * Math.PI) / numDots;
        const dotX = center + (radius + 2) * Math.cos(dotAngle);
        const dotY = center + (radius + 2) * Math.sin(dotAngle);
        ctx.beginPath();
        ctx.arc(dotX, dotY, 2.5, 0, 2 * Math.PI);
        ctx.fillStyle = '#fef08a';
        ctx.fill();
    }

    // Center Hub
    ctx.beginPath();
    ctx.arc(center, center, 28, 0, 2 * Math.PI);
    ctx.fillStyle = '#1e293b';
    ctx.fill();
    ctx.strokeStyle = '#f59e0b';
    ctx.lineWidth = 4;
    ctx.stroke();

    ctx.beginPath();
    ctx.arc(center, center, 8, 0, 2 * Math.PI);
    ctx.fillStyle = '#fbbf24';
    ctx.fill();
};

onMounted(() => {
    drawWheel(0);
});

// Spin Logic
const spinWheel = async () => {
    if (isSpinning.value || hasWon.value || props.is_expired || props.slices.length === 0) return;

    errorMessage.value = null;
    isSpinning.value = true;
    getAudioContext();

    try {
        const response = await axios.post(`/wheel/${props.token}/spin`);
        const { won_id, voucher: wonVoucher } = response.data;

        // Find index of winning slice
        const wonIndex = props.slices.findIndex((s) => s.id === won_id);
        const targetIndex = wonIndex >= 0 ? wonIndex : 0;

        // Calculate stop angle so the pointer at top (3 * PI / 2 or 270 deg) points to target slice
        const numSlices = props.slices.length;
        const sliceAngle = (2 * Math.PI) / numSlices;

        // In standard canvas: 0 is right (3 o'clock). Top pointer is at -PI/2 (or 3*PI/2, 12 o'clock).
        // A slice at index i spans [i * sliceAngle, (i+1) * sliceAngle].
        // Its center is (i + 0.5) * sliceAngle.
        // We want (centerAngle + finalAngle) mod 2PI = 3*PI / 2.
        // So finalAngle = 3*PI/2 - centerAngle.
        const centerAngle = (targetIndex + 0.5) * sliceAngle;
        const baseTarget = (3 * Math.PI) / 2 - centerAngle;

        // Add 5 to 7 full rotations for realistic spin
        const extraRotations = 6 * 2 * Math.PI;
        const startAngle = currentAngle % (2 * Math.PI);
        const finalAngle = startAngle + extraRotations + (baseTarget - (startAngle % (2 * Math.PI)) + 4 * Math.PI) % (2 * Math.PI);

        const duration = 4500; // 4.5 seconds
        const startTime = performance.now();
        let lastTickAngle = startAngle;

        const animate = (time: number) => {
            const elapsed = time - startTime;
            const progress = Math.min(elapsed / duration, 1);

            // Ease out cubic deceleration
            const easeOut = 1 - Math.pow(1 - progress, 3);
            currentAngle = startAngle + (finalAngle - startAngle) * easeOut;

            // Trigger tick sound on crossing slices
            if (currentAngle - lastTickAngle >= sliceAngle) {
                playTickSound();
                lastTickAngle = currentAngle;
            }

            drawWheel(currentAngle);

            if (progress < 1) {
                animationFrameId = requestAnimationFrame(animate);
            } else {
                isSpinning.value = false;
                voucher.value = wonVoucher;
                hasWon.value = true;
                playFanfareSound();
                triggerConfetti();
            }
        };

        animationFrameId = requestAnimationFrame(animate);
    } catch (err: any) {
        isSpinning.value = false;
        errorMessage.value = err.response?.data?.message || 'حدث خطأ أثناء تدوير العجلة، يرجى المحاولة ثانية.';
    }
};

// Copy voucher code
const copyCode = async () => {
    if (!voucher.value?.code) return;
    try {
        await navigator.clipboard.writeText(voucher.value.code);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2500);
    } catch {
        // Fallback
        copied.value = true;
    }
};

// Confetti burst on win
const triggerConfetti = () => {
    const confettiCount = 60;
    const container = document.getElementById('confetti-container');
    if (!container) return;

    for (let i = 0; i < confettiCount; i++) {
        const el = document.createElement('div');
        el.className = 'confetti';
        el.style.left = Math.random() * 100 + 'vw';
        el.style.animationDelay = Math.random() * 0.5 + 's';
        el.style.backgroundColor = ['#f59e0b', '#10b981', '#3b82f6', '#ec4899', '#8b5cf6'][Math.floor(Math.random() * 5)];
        container.appendChild(el);
        setTimeout(() => el.remove(), 3500);
    }
};
</script>

<template>
    <Head :title="`عجلة الحظ - ${restaurant.name}`" />

    <div class="min-h-screen bg-gradient-to-b from-[#0b0f19] via-[#111827] to-[#0f172a] text-white flex flex-col items-center justify-between p-4 selection:bg-amber-500 selection:text-black relative overflow-x-hidden font-sans" dir="rtl">
        <!-- Confetti container -->
        <div id="confetti-container" class="pointer-events-none fixed inset-0 overflow-hidden z-50"></div>

        <!-- Header -->
        <header class="w-full max-w-md pt-4 pb-2 text-center flex flex-col items-center">
            <div v-if="restaurant.logo" class="w-16 h-16 rounded-full overflow-hidden mb-3 border-2 border-amber-400/40 shadow-lg shadow-amber-500/10 bg-slate-900">
                <img :src="restaurant.logo" :alt="restaurant.name" class="w-full h-full object-cover" />
            </div>
            <h1 class="text-2xl font-black tracking-tight text-white flex items-center justify-center gap-2">
                <span>{{ restaurant.name }}</span>
            </h1>
            <div class="mt-1 flex items-center gap-2">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-300 border border-amber-500/30">
                    🎁 عجلة الحظ والهدايا
                </span>
                <span v-if="order_number" class="text-xs text-slate-400">
                    فاتورة طلب #{{ order_number }}
                </span>
            </div>
        </header>

        <!-- Main Content -->
        <main class="w-full max-w-md flex flex-col items-center justify-center my-auto py-2">
            <!-- State 1: Expired -->
            <div v-if="is_expired" class="bg-slate-900/80 border border-slate-800 rounded-3xl p-8 text-center backdrop-blur-md shadow-2xl w-full">
                <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-red-500/10 text-red-400 flex items-center justify-center text-3xl">
                    ⏳
                </div>
                <h2 class="text-xl font-bold text-white mb-2">عذراً، انتهت صلاحية الرابط</h2>
                <p class="text-sm text-slate-400 leading-relaxed">
                    انتهت صلاحية جلسة عجلة الحظ الخاصة بهذه الفاتورة. نتمنى لك حظاً سعيداً في زيارتك القادمة!
                </p>
            </div>

            <!-- State 2: No active slices -->
            <div v-else-if="slices.length === 0" class="bg-slate-900/80 border border-slate-800 rounded-3xl p-8 text-center backdrop-blur-md shadow-2xl w-full">
                <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-amber-500/10 text-amber-400 flex items-center justify-center text-3xl">
                    ✨
                </div>
                <h2 class="text-xl font-bold text-white mb-2">العروض قيد التحديث</h2>
                <p class="text-sm text-slate-400 leading-relaxed">
                    لا توجد عروض نشطة في العجلة حالياً. تفضل بمتابعة أحدث عروضنا عبر المنيو!
                </p>
            </div>

            <!-- State 3: Wheel Game Area -->
            <div v-else class="w-full flex flex-col items-center">
                <!-- Wheel Canvas Container -->
                <div class="relative w-[340px] h-[340px] flex items-center justify-center my-2">
                    <!-- Top Pointer -->
                    <div class="absolute -top-3 z-30 flex flex-col items-center drop-shadow-[0_4px_8px_rgba(0,0,0,0.8)]">
                        <div class="w-0 h-0 border-l-[14px] border-l-transparent border-r-[14px] border-r-transparent border-t-[28px] border-t-amber-400 filter drop-shadow"></div>
                    </div>

                    <!-- Canvas Wheel -->
                    <canvas
                        ref="canvasRef"
                        width="340"
                        height="340"
                        class="rounded-full shadow-[0_0_35px_rgba(245,158,11,0.25)] transition-transform duration-75 select-none"
                    ></canvas>

                    <!-- Center Spin Button -->
                    <button
                        v-if="!hasWon"
                        @click="spinWheel"
                        :disabled="isSpinning"
                        class="absolute z-20 w-16 h-16 rounded-full bg-gradient-to-tr from-amber-600 via-amber-400 to-yellow-300 text-slate-950 font-black text-sm tracking-wide shadow-xl flex items-center justify-center border-2 border-white/60 active:scale-95 transition hover:scale-105 disabled:opacity-80 disabled:cursor-not-allowed group cursor-pointer"
                    >
                        <span v-if="!isSpinning" class="group-hover:animate-pulse">دَوّرْ!</span>
                        <span v-else class="inline-block animate-spin text-lg">⚙</span>
                    </button>
                </div>

                <!-- Spin instructions / Action button -->
                <div v-if="!hasWon" class="mt-4 text-center w-full">
                    <p class="text-sm text-amber-200/80 mb-3 font-medium">
                        اضغط لتدوير العجلة وربح كود خصم حصري لزيارتك القادمة!
                    </p>
                    <button
                        @click="spinWheel"
                        :disabled="isSpinning"
                        class="w-full max-w-xs py-3.5 px-6 rounded-2xl bg-gradient-to-r from-amber-500 via-amber-400 to-yellow-400 text-slate-950 font-extrabold text-base shadow-lg shadow-amber-500/25 hover:brightness-110 active:scale-[0.98] transition disabled:opacity-60 disabled:cursor-not-allowed"
                    >
                        <span v-if="!isSpinning">🎁 تدوير عجلة الحظ</span>
                        <span v-else>العجلة تدور... بالتوفيق!</span>
                    </button>
                    <div v-if="errorMessage" class="mt-3 text-red-400 text-xs font-semibold bg-red-950/40 border border-red-800/50 rounded-xl p-2.5">
                        {{ errorMessage }}
                    </div>
                </div>

                <!-- Won Voucher Card Modal / Section -->
                <transition
                    enter-active-class="transition duration-500 ease-out"
                    enter-from-class="opacity-0 translate-y-6 scale-95"
                    enter-to-class="opacity-100 translate-y-0 scale-100"
                >
                    <div v-if="hasWon && voucher" class="mt-4 w-full bg-gradient-to-b from-slate-900 via-[#131b2e] to-slate-900 border border-amber-500/40 rounded-3xl p-6 text-center shadow-2xl relative overflow-hidden backdrop-blur-md">
                        <!-- Glow highlight -->
                        <div class="absolute -top-10 -right-10 w-32 h-32 bg-amber-500/10 rounded-full blur-2xl pointer-events-none"></div>
                        <div class="absolute -bottom-10 -left-10 w-32 h-32 bg-emerald-500/10 rounded-full blur-2xl pointer-events-none"></div>

                        <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-amber-400/20 text-amber-300 text-2xl mb-2">
                            🎉
                        </div>
                        <h3 class="text-xl font-black text-white">مبروك! لقد ربحت معنا</h3>
                        <p class="text-2xl font-black text-amber-400 my-1">
                            {{ voucher.discount_text }}
                        </p>

                        <!-- Single-use Voucher Box -->
                        <div class="my-4 p-4 rounded-2xl bg-black/40 border border-amber-500/30 flex flex-col items-center gap-2">
                            <span class="text-xs text-slate-400 font-medium">كود الخصم لزيارتك القادمة:</span>
                            <div class="flex items-center justify-center gap-3 w-full">
                                <span class="font-mono text-2xl font-bold tracking-widest text-emerald-400 select-all" dir="ltr">
                                    {{ voucher.code }}
                                </span>
                                <button
                                    @click="copyCode"
                                    class="p-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 transition active:scale-95 text-xs flex items-center gap-1 border border-slate-700"
                                    title="نسخ الكود"
                                >
                                    <span>{{ copied ? 'تم النسخ ✓' : 'نسخ' }}</span>
                                </button>
                            </div>
                        </div>

                        <!-- Voucher Rules & Validity -->
                        <div class="space-y-1.5 text-xs text-slate-300 text-right bg-slate-950/40 p-3 rounded-xl border border-slate-800">
                            <div class="flex items-center gap-2 text-amber-300 font-semibold">
                                <span>📅</span>
                                <span>صالح للزيارة القادمة ابتداءً من: {{ voucher.starts_at || 'قريباً' }}</span>
                            </div>
                            <div class="flex items-center gap-2 text-slate-400">
                                <span>⏳</span>
                                <span>ينتهي في: {{ voucher.expires_at }} (صالح لمرة واحدة فقط)</span>
                            </div>
                            <div v-if="voucher.min_order_value > 0" class="flex items-center gap-2 text-slate-400">
                                <span>🛒</span>
                                <span>الحد الأدنى للطلب: {{ voucher.min_order_value }} {{ restaurant.currency }}</span>
                            </div>
                            <div v-if="voucher.is_used" class="mt-2 text-center text-red-400 font-bold bg-red-950/30 p-1.5 rounded-lg border border-red-900/40">
                                تم استخدام هذا الكود بالفعل ✓
                            </div>
                        </div>

                        <p class="text-[11px] text-slate-400 mt-3">
                            احتفظ بهذا الكود أو التقط لقطة شاشة، وقم بتطبيقه في صفحة الدفع بزيارتك القادمة.
                        </p>
                    </div>
                </transition>
            </div>
        </main>

        <!-- Footer -->
        <footer class="w-full max-w-md pt-2 pb-3 text-center text-[11px] text-slate-500">
            نظام تدلّل الذكي للمطاعم • تجربة طلب سهلة وممتعة
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
        transform: translateY(100vh) rotate(720deg);
        opacity: 0;
    }
}

:deep(.confetti) {
    position: absolute;
    top: 0;
    width: 10px;
    height: 14px;
    border-radius: 2px;
    opacity: 0.9;
    animation: confettiFall 3s linear forwards;
}
</style>
