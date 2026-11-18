<script setup lang="ts">
import { usePwa } from '@/composables/usePwa';

const props = defineProps<{
    show: boolean;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const { isIOS, promptInstall } = usePwa();

async function handleInstallClick() {
    const res = await promptInstall();
    if (res === 'accepted') {
        emit('close');
    }
}
</script>

<template>
<template>
    <Teleport to="body">
        <div
            v-if="show"
            class="fixed inset-0 z-[999999] flex items-center justify-center p-3 sm:p-6"
            aria-modal="true"
            role="dialog"
        >
            <!-- Backdrop -->
            <div
                class="fixed inset-0 bg-black/85 backdrop-blur-md transition-opacity"
                @click="emit('close')"
            ></div>

            <!-- Modal Box -->
            <div
                class="relative w-full max-w-sm sm:max-w-md max-h-[92vh] overflow-y-auto rounded-3xl border border-amber-500/30 bg-[#0c1222] p-5 sm:p-7 text-center text-white shadow-2xl shadow-amber-500/10 z-10"
                dir="rtl"
            >
                <!-- Ambient Glow -->
                <div class="pointer-events-none absolute -top-24 left-1/2 h-48 w-48 -translate-x-1/2 rounded-full bg-gradient-to-b from-amber-400/20 to-transparent blur-3xl"></div>

                <!-- Close Button -->
                <button
                    type="button"
                    @click="emit('close')"
                    class="absolute left-3 top-3 rounded-full p-2 text-zinc-400 hover:bg-white/10 hover:text-white transition"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                <!-- App Emblem -->
                <div class="mx-auto mb-3 flex h-16 w-16 sm:h-20 sm:w-20 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-500/20 via-black to-amber-950/40 border border-amber-500/40 p-2 shadow-lg shadow-amber-500/10">
                    <img src="/favicon-192x192.png" alt="تدلّل" class="h-12 w-12 sm:h-16 sm:w-16 object-contain" />
                </div>

                <div class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-amber-500/10 border border-amber-500/30 text-amber-400 text-[10px] sm:text-xs font-bold mb-2 font-cinzel tracking-wider">
                    TEDALLAL ROYAL APP
                </div>

                <h3 class="text-lg font-bold text-zinc-100 sm:text-2xl font-cairo">
                    تثبيت تطبيق تدلّل كـ تطبيق أصلي
                </h3>

                <p class="mt-1.5 text-xs text-zinc-400 leading-relaxed font-cairo">
                    تجربة ملكية فائقة السرعة، شاشة كاملة، وتنبيهات صوتية فورية بالطلبات وحجوزات الطاولات حتى عند قفل الهاتف.
                </p>

                <!-- Android & Desktop Install -->
                <div v-if="!isIOS" class="mt-5 space-y-2.5">
                    <button
                        type="button"
                        @click="handleInstallClick"
                        class="w-full flex items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-amber-500 via-amber-400 to-yellow-500 py-3.5 px-6 font-bold text-zinc-950 shadow-lg shadow-amber-500/25 transition hover:brightness-110 active:scale-[0.98] font-cairo text-sm sm:text-base"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        <span>تثبيت التطبيق على جهازك الآن</span>
                    </button>
                    <p class="text-[11px] text-zinc-500">
                        يعمل كتطبيق مستقل بدون استهلاك لمساحة التخزين
                    </p>
                </div>

                <!-- iOS Safari Instructions -->
                <div v-else class="mt-5 space-y-3 text-right">
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-3.5 space-y-3 text-xs text-zinc-300">
                        <div class="flex items-start gap-3">
                            <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-amber-500/20 text-amber-400 font-bold text-xs border border-amber-500/40 mt-0.5">1</span>
                            <div class="flex-1">
                                <div>اضغط على زر المشاركة أسفل متصفح سفاري:</div>
                                <div class="mt-0.5 font-bold text-amber-300 flex items-center gap-1.5" dir="ltr">
                                    <svg class="h-4 w-4 text-blue-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                                    <span>Share icon</span>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-amber-500/20 text-amber-400 font-bold text-xs border border-amber-500/40 mt-0.5">2</span>
                            <div class="flex-1">
                                <div>مرر لأسفل القائمة واختر:</div>
                                <div class="mt-0.5 font-bold text-white">«إضافة إلى الشاشة الرئيسية»</div>
                                <div class="text-[11px] text-zinc-400 font-mono" dir="ltr">Add to Home Screen</div>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-amber-500/20 text-amber-400 font-bold text-xs border border-amber-500/40 mt-0.5">3</span>
                            <div class="flex-1">
                                <div>اضغط على الزر العلوي لتثبيت التطبيق فوراً:</div>
                                <div class="mt-0.5 font-bold text-white">«إضافة» <span class="font-mono text-zinc-400" dir="ltr">(Add)</span></div>
                            </div>
                        </div>
                    </div>

                    <button
                        type="button"
                        @click="emit('close')"
                        class="w-full rounded-2xl bg-white/10 py-2.5 text-xs font-bold text-white hover:bg-white/15 transition font-cairo"
                    >
                        فهمت ذلك، شكراً
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
</template>
