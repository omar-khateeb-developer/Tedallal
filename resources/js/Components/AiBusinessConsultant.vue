<script setup lang="ts">
import { ref, computed, nextTick, watch } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';

interface Props {
    isDark?: boolean;
    locale?: 'ar' | 'en';
}

const props = withDefaults(defineProps<Props>(), {
    isDark: true,
    locale: 'ar',
});

const emit = defineEmits<{
    (e: 'open-demo'): void;
}>();

interface Message {
    id: string;
    role: 'user' | 'assistant';
    content: string;
    timestamp: Date;
    suggestedActions?: Array<{
        label: string;
        action: string;
        url?: string;
    }>;
}

const isOpen = ref(false);
const isMinimized = ref(false);
const inputMessage = ref('');
const isLoading = ref(false);
const messagesContainer = ref<HTMLDivElement | null>(null);

// النغمة التنبيهية الفاخرة للاستجابة الذكية (Pure Web Audio API)
const soundEnabled = ref(true);
let aiAudioCtx: AudioContext | null = null;

function playLuxuryChime() {
    if (!soundEnabled.value || typeof window === 'undefined') return;
    try {
        const AudioClass = window.AudioContext || (window as any).webkitAudioContext;
        if (!AudioClass) return;
        if (!aiAudioCtx) {
            aiAudioCtx = new AudioClass();
        }
        if (aiAudioCtx.state === 'suspended') {
            aiAudioCtx.resume();
        }

        const now = aiAudioCtx.currentTime;

        // النغمة الأولى: E5 (659.25 Hz) رنة زجاجية رخيمة
        const osc1 = aiAudioCtx.createOscillator();
        const gain1 = aiAudioCtx.createGain();
        osc1.type = 'sine';
        osc1.frequency.setValueAtTime(659.25, now);
        gain1.gain.setValueAtTime(0.001, now);
        gain1.gain.exponentialRampToValueAtTime(0.08, now + 0.02);
        gain1.gain.exponentialRampToValueAtTime(0.0001, now + 0.55);
        osc1.connect(gain1);
        gain1.connect(aiAudioCtx.destination);
        osc1.start(now);
        osc1.stop(now + 0.55);

        // النغمة الثانية: B5 (987.77 Hz) رنة تأكيد فاخرة بعد 95ms
        const osc2 = aiAudioCtx.createOscillator();
        const gain2 = aiAudioCtx.createGain();
        osc2.type = 'sine';
        osc2.frequency.setValueAtTime(987.77, now + 0.095);
        gain2.gain.setValueAtTime(0.001, now + 0.095);
        gain2.gain.exponentialRampToValueAtTime(0.09, now + 0.12);
        gain2.gain.exponentialRampToValueAtTime(0.0001, now + 0.75);
        osc2.connect(gain2);
        gain2.connect(aiAudioCtx.destination);
        osc2.start(now + 0.095);
        osc2.stop(now + 0.75);

        // نغمة توافقية خافتة: E6 (1318.51 Hz) لإضفاء لمسة فندقية ملكية
        const osc3 = aiAudioCtx.createOscillator();
        const gain3 = aiAudioCtx.createGain();
        osc3.type = 'triangle';
        osc3.frequency.setValueAtTime(1318.51, now + 0.14);
        gain3.gain.setValueAtTime(0.001, now + 0.14);
        gain3.gain.exponentialRampToValueAtTime(0.02, now + 0.16);
        gain3.gain.exponentialRampToValueAtTime(0.0001, now + 0.65);
        osc3.connect(gain3);
        gain3.connect(aiAudioCtx.destination);
        osc3.start(now + 0.14);
        osc3.stop(now + 0.65);
    } catch {
        // Audio error gracefully ignored
    }
}

const starterPrompts = [
    'كيف تزيد تدلّل مبيعات وأرباح مطعمي وتلغي الهدر؟',
    'هل أحتاج لشراء أجهزة كاشير مخصصة ومكلفة؟',
    'كيف تعمل ميزة عجلة الحظ ورسائل الواتساب الذاتية؟',
    'ما الفرق بين تدلّل وتطبيقات التوصيل ذات العمولات؟',
    'هل تدلّل مناسبة لكافيه صغير أو عربة طعام؟',
];

const welcomeText = computed(() => {
    return props.locale === 'ar'
        ? 'أهلاً بك يا سيدي! أنا «مستشار تدلّل الذكي للأعمال» ✦ خبير منظومة الضيافة الفاخرة.\n\nيسعدني جداً الإجابة عن أي استفسار حول كيفية تطوير مطعمك، مضاعفة أرباحك الصافية، أو استكشاف أي ميزة في المنظومة. تفضل باختيار أحد الأسئلة السريعة أدناه أو اكتب استفسارك مباشرة!'
        : 'Welcome! I am the Tedallal AI Business & Hospitality Consultant ✦.\n\nAsk me anything about how Tedallal can elevate your restaurant, boost your net profit with 0% commission, or explore our smart QR, KDS & WhatsApp CRM suite.';
});

const messages = ref<Message[]>([
    {
        id: 'msg-welcome',
        role: 'assistant',
        content: welcomeText.value,
        timestamp: new Date(),
    },
]);

function scrollToBottom() {
    nextTick(() => {
        if (messagesContainer.value) {
            messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
        }
    });
}

function toggleChat() {
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
        scrollToBottom();
    }
}

async function sendMessage(textToSend?: string) {
    const text = (textToSend ?? inputMessage.value).trim();
    if (!text || isLoading.value) return;

    inputMessage.value = '';

    // Add user message
    const userMsg: Message = {
        id: 'msg-' + Date.now(),
        role: 'user',
        content: text,
        timestamp: new Date(),
    };
    messages.value.push(userMsg);
    scrollToBottom();

    isLoading.value = true;

    try {
        // Build history (last 6 messages)
        const history = messages.value
            .slice(-7, -1)
            .map((m) => ({
                role: m.role === 'user' ? 'user' : 'model',
                content: m.content,
            }));

        const response = await fetch('/api/public/ai-consultant/chat', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                message: text,
                history,
            }),
        });

        if (!response.ok) {
            throw new Error(`Server returned ${response.status}`);
        }

        const data = await response.json();
        const replyText = data?.data?.reply || 'أهلاً بك! تم استلام رسالتك، ويسعدنا الإجابة عن أي استفسار آخر.';
        const actions = data?.data?.suggested_actions || [];

        messages.value.push({
            id: 'msg-' + Date.now(),
            role: 'assistant',
            content: replyText,
            timestamp: new Date(),
            suggestedActions: actions,
        });
        playLuxuryChime();
    } catch (err) {
        console.error('AI Consultant Error:', err);
        messages.value.push({
            id: 'msg-' + Date.now(),
            role: 'assistant',
            content: props.locale === 'ar'
                ? 'أهلاً بك! «تَدلّل» منظومة سحابية متكاملة بلا عمولات (0% Commission) توفر لك منيو QR ذكي، شاشات مطبخ KDS، كاشير وورديات Z-Report، وعجلة ولاء بالواتساب. يسعدنا تواصلك المباشر مع فريقنا عبر الواتساب أو استكشاف الملف التعريفي!'
                : 'Tedallal is a zero-commission royal hospitality OS with smart QR, kitchen KDS, POS Z-reports, and WhatsApp loyalty CRM. Contact us on WhatsApp or explore our pitch deck for full details!',
            timestamp: new Date(),
            suggestedActions: [
                {
                    label: 'تحدث مع فريق المبيعات عبر واتساب ✆',
                    action: 'open_whatsapp',
                    url: 'https://wa.me/963934578117',
                },
                {
                    label: 'استكشف الملف التعريفي ↗',
                    action: 'open_presentation',
                    url: '/presentation',
                },
            ],
        });
        playLuxuryChime();
    } finally {
        isLoading.value = false;
        scrollToBottom();
    }
}

function handleAction(action: { label: string; action: string; url?: string }) {
    if (action.action === 'open_demo_modal') {
        emit('open-demo');
    } else if (action.url) {
        window.open(action.url, '_blank', 'noopener,noreferrer');
    }
}

function handleStarterPrompt(prompt: string) {
    sendMessage(prompt);
}

function formatText(text: string): string {
    // Basic Markdown support for bolding and bullet points
    let formatted = text
        .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
        .replace(/\n\n/g, '<br><br>')
        .replace(/\n- /g, '<br>• ')
        .replace(/\n\d+\. /g, '<br>• ');
    return formatted;
}
</script>

<template>
    <div class="fixed z-50" :class="locale === 'ar' ? 'right-4 sm:right-6 bottom-5 sm:bottom-6' : 'left-4 sm:left-6 bottom-5 sm:bottom-6'">
        <!-- ========================================================
             1. THE FLOATING LAUNCHER PILL (Always Visible)
             ======================================================== -->
        <button
            type="button"
            @click="toggleChat"
            class="group relative inline-flex items-center gap-2 px-3 py-2.5 sm:px-4 sm:py-3 rounded-full border shadow-2xl transition-all duration-300 hover:scale-105 active:scale-95 cursor-pointer select-none"
            :class="[
                isOpen
                    ? (isDark ? 'bg-amber-500/25 border-amber-400 text-amber-200' : 'bg-amber-100 border-[#C5A059] text-stone-900')
                    : (isDark
                        ? 'bg-[#0E0E12]/92 hover:bg-[#16161D] border-amber-500/40 text-stone-100 hover:border-amber-400 shadow-amber-500/10'
                        : 'bg-white/95 hover:bg-[#FAF8F5] border-[#C5A059]/60 text-stone-900 hover:border-[#9B7328] shadow-amber-700/10')
            ]"
            :title="isOpen ? 'إغلاق مستشار تدلّل الذكي' : 'استشر ذكاء تدلّل الاصطناعي حول مزايا وأرباح مطعمك'"
        >
            <!-- Ambient Golden Halo -->
            <span class="absolute -inset-1 rounded-full bg-radial from-amber-500/25 to-transparent blur-md pointer-events-none group-hover:opacity-100 opacity-60 transition-opacity"></span>

            <!-- Official Royal Animated Emblem Thumbnail -->
            <div class="relative w-5 h-5 sm:w-6 sm:h-6 shrink-0">
                <ApplicationLogo variant="emblem" :animated="true" class="w-full h-full" />
            </div>

            <!-- Pulsing AI Active Dot -->
            <span class="relative flex h-2 w-2 sm:h-2.5 sm:w-2.5 shrink-0">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2 w-2 sm:h-2.5 sm:w-2.5 bg-amber-500"></span>
            </span>

            <!-- Label -->
            <div class="flex items-center gap-1 sm:gap-1.5 text-xs sm:text-sm font-black tracking-wide">
                <span class="hidden sm:inline">{{ locale === 'ar' ? 'مستشار تدلّل للأعمال' : 'Tedallal AI Consultant' }}</span>
                <span class="inline sm:hidden">{{ locale === 'ar' ? 'مستشار تدلّل' : 'AI Consultant' }}</span>
                <span class="text-amber-400 font-bold text-xs">✦</span>
            </div>

            <!-- Toggle Icon -->
            <span
                class="text-xs transition-transform duration-300 opacity-70 group-hover:opacity-100"
                :class="isOpen ? 'rotate-180 text-amber-400' : ''"
            >
                {{ isOpen ? '✕' : '💬' }}
            </span>
        </button>

        <!-- ========================================================
             2. THE LUXURY GLASSMORPHIC CHAT DRAWER / WINDOW
             ======================================================== -->
        <transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="opacity-0 translate-y-6 scale-95"
            enter-to-class="opacity-100 translate-y-0 scale-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="opacity-100 translate-y-0 scale-100"
            leave-to-class="opacity-0 translate-y-6 scale-95"
        >
            <div
                v-if="isOpen"
                class="absolute bottom-16 w-[calc(100vw-2rem)] sm:w-[420px] max-w-[420px] h-[580px] max-h-[82vh] rounded-3xl border shadow-2xl flex flex-col overflow-hidden backdrop-blur-2xl z-50 select-text"
                :class="[
                    locale === 'ar' ? 'right-0' : 'left-0',
                    isDark
                        ? 'bg-[#0A0A0E]/96 border-amber-500/35 text-stone-100 shadow-[0_25px_70px_rgba(0,0,0,0.95)]'
                        : 'bg-[#FAF8F5]/98 border-[#C5A059]/50 text-stone-900 shadow-[0_20px_60px_rgba(180,140,60,0.22)]'
                ]"
            >
                <!-- Header -->
                <div
                    class="p-4 border-b flex items-center justify-between shrink-0"
                    :class="isDark ? 'bg-white/[0.03] border-amber-500/20' : 'bg-black/[0.02] border-[#C5A059]/30'"
                >
                    <div class="flex items-center gap-3">
                        <div class="relative w-9 h-9 shrink-0 filter drop-shadow-[0_2px_8px_rgba(218,165,32,0.4)]">
                            <ApplicationLogo variant="emblem" :animated="true" class="w-full h-full" />
                        </div>
                        <div class="flex flex-col">
                            <div class="flex items-center gap-1.5">
                                <span class="font-black text-sm sm:text-base tracking-tight" :class="isDark ? 'text-white' : 'text-stone-950'">
                                    {{ locale === 'ar' ? 'مستشار تدلّل الذكي' : 'Tedallal AI Consultant' }}
                                </span>
                                <span class="text-[9px] font-black uppercase tracking-wider px-1.5 py-0.5 rounded bg-amber-500/20 text-amber-400 border border-amber-500/35">
                                    AI B2B
                                </span>
                            </div>
                            <span class="text-[10px] font-bold text-emerald-400 flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                {{ locale === 'ar' ? 'متصل الآن • خبير ربحية ومنظومة المطاعم' : 'Online • Hospitality & ROI Expert' }}
                            </span>
                        </div>
                    </div>

                    <!-- Header Controls: Sound & Close -->
                    <div class="flex items-center gap-1">
                        <button
                            type="button"
                            @click="soundEnabled = !soundEnabled"
                            class="p-2 rounded-xl text-stone-400 hover:text-stone-200 transition-colors"
                            :class="isDark ? 'hover:bg-white/10' : 'hover:bg-stone-200/60'"
                            :title="soundEnabled ? 'كتم الرنة الفاخرة' : 'تشغيل الرنة الفاخرة'"
                        >
                            <span class="text-xs">{{ soundEnabled ? '🔔' : '🔕' }}</span>
                        </button>
                        <button
                            type="button"
                            @click="isOpen = false"
                            class="p-2 rounded-xl text-stone-400 hover:text-stone-200 transition-colors"
                            :class="isDark ? 'hover:bg-white/10' : 'hover:bg-stone-200/60'"
                            title="إغلاق المحادثة"
                        >
                            ✕
                        </button>
                    </div>
                </div>

                <!-- Messages Container -->
                <div
                    ref="messagesContainer"
                    class="flex-1 p-4 overflow-y-auto space-y-4 text-xs sm:text-sm font-sans scroll-smooth"
                >
                    <div
                        v-for="msg in messages"
                        :key="msg.id"
                        class="flex flex-col gap-1.5"
                        :class="msg.role === 'user' ? 'items-end' : 'items-start'"
                    >
                        <!-- Role / Sender -->
                        <div class="flex items-center gap-1.5 text-[10px] text-stone-400 px-1 font-bold">
                            <span>{{ msg.role === 'user' ? (locale === 'ar' ? 'أنت' : 'You') : (locale === 'ar' ? 'مستشار تدلّل' : 'Tedallal Consultant') }}</span>
                            <span>•</span>
                            <span>{{ msg.timestamp.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}</span>
                        </div>

                        <!-- Bubble -->
                        <div
                            class="p-3.5 sm:p-4 rounded-2xl max-w-[88%] leading-relaxed shadow-sm transition-all"
                            :class="[
                                msg.role === 'user'
                                    ? 'bg-gradient-to-r from-[#BF953F] via-[#DDB758] to-[#AA771C] text-stone-950 font-bold rounded-br-xs'
                                    : (isDark
                                        ? 'bg-white/[0.05] border border-amber-500/25 text-stone-200 rounded-bl-xs'
                                        : 'bg-white border border-[#C5A059]/40 text-stone-800 rounded-bl-xs shadow-xs')
                            ]"
                        >
                            <div v-html="formatText(msg.content)"></div>

                            <!-- Suggested Actions (Buttons) -->
                            <div v-if="msg.suggestedActions?.length" class="mt-3 pt-2.5 border-t border-amber-500/20 flex flex-wrap gap-2">
                                <button
                                    v-for="(act, idx) in msg.suggestedActions"
                                    :key="idx"
                                    type="button"
                                    @click="handleAction(act)"
                                    class="px-3 py-1.5 rounded-lg text-xs font-black transition-all hover:scale-105 active:scale-95 cursor-pointer flex items-center gap-1"
                                    :class="isDark ? 'bg-amber-500/15 text-amber-300 hover:bg-amber-500/25 border border-amber-500/30' : 'bg-amber-100 text-[#7A5208] hover:bg-amber-200 border border-[#C5A059]/50'"
                                >
                                    <span>{{ act.label }}</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Loading / Typing Indicator -->
                    <div v-if="isLoading" class="flex items-center gap-2 text-xs text-amber-400 font-bold p-2">
                        <span class="w-2 h-2 rounded-full bg-amber-400 animate-bounce" style="animation-delay: 0s;"></span>
                        <span class="w-2 h-2 rounded-full bg-amber-400 animate-bounce" style="animation-delay: 0.2s;"></span>
                        <span class="w-2 h-2 rounded-full bg-amber-400 animate-bounce" style="animation-delay: 0.4s;"></span>
                        <span>{{ locale === 'ar' ? 'جاري تحليل استفسارك وصياغة الرد الاستشاري...' : 'Analyzing and preparing recommendation...' }}</span>
                    </div>

                    <!-- Starter Prompts (Visible when only welcome message is present) -->
                    <div v-if="messages.length === 1 && !isLoading" class="pt-2">
                        <p class="text-[11px] font-bold text-amber-400/90 mb-2">
                            {{ locale === 'ar' ? 'أسئلة شائعة يطرحها أصحاب المطاعم:' : 'Popular questions by restaurant owners:' }}
                        </p>
                        <div class="flex flex-col gap-1.5">
                            <button
                                v-for="(p, i) in starterPrompts"
                                :key="i"
                                type="button"
                                @click="handleStarterPrompt(p)"
                                class="text-right px-3 py-2 rounded-xl text-xs font-bold transition-all hover:scale-[1.01] active:scale-98 flex items-center justify-between gap-2 border"
                                :class="isDark
                                    ? 'bg-white/[0.04] hover:bg-amber-500/10 border-white/10 hover:border-amber-500/35 text-stone-300 hover:text-amber-300'
                                    : 'bg-white hover:bg-amber-50 border-stone-200 hover:border-amber-400 text-stone-700 hover:text-amber-900 shadow-2xs'"
                            >
                                <span>{{ p }}</span>
                                <span class="text-amber-400 shrink-0">←</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Input Footer -->
                <div
                    class="p-3 border-t shrink-0"
                    :class="isDark ? 'bg-white/[0.02] border-amber-500/20' : 'bg-black/[0.02] border-[#C5A059]/30'"
                >
                    <form @submit.prevent="() => sendMessage()" class="flex items-center gap-2">
                        <input
                            v-model="inputMessage"
                            type="text"
                            :placeholder="locale === 'ar' ? 'اسأل عن ميزات المنظومة، التكاليف، أو حل مشاكل مطعمك...' : 'Ask about features, pricing, or restaurant ROI...'"
                            :disabled="isLoading"
                            class="flex-1 px-4 py-2.5 rounded-full text-xs sm:text-sm border transition-all focus:outline-none focus:ring-1 focus:ring-amber-400"
                            :class="isDark
                                ? 'bg-black/50 border-white/15 text-white placeholder-stone-500 focus:border-amber-400'
                                : 'bg-white border-stone-300 text-stone-900 placeholder-stone-400 focus:border-amber-500'"
                        />
                        <button
                            type="submit"
                            :disabled="!inputMessage.trim() || isLoading"
                            class="px-4 py-2.5 rounded-full bg-gradient-to-r from-[#BF953F] to-[#AA771C] text-stone-950 font-black text-xs sm:text-sm shadow-md transition-all hover:scale-105 active:scale-95 disabled:opacity-40 disabled:pointer-events-none cursor-pointer flex items-center gap-1.5"
                        >
                            <span>{{ locale === 'ar' ? 'إرسال' : 'Send' }}</span>
                            <span>✦</span>
                        </button>
                    </form>
                    <p class="text-[9px] text-center text-stone-500 mt-2 font-medium">
                        {{ locale === 'ar' ? 'مستشار ذكي فوري مدرب على معايير تشغيل ومنظومة تدلّل الملكية' : 'AI Business Consultant grounded on Tedallal Hospitality OS' }}
                    </p>
                </div>
            </div>
        </transition>
    </div>
</template>
