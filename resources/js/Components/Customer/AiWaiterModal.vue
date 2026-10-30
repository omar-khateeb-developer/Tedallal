<script setup lang="ts">
import { ref, watch, nextTick } from 'vue';
import axios from 'axios';

const props = defineProps<{
    restaurantName: string;
    currency: string;
}>();

const emit = defineEmits<{
    (e: 'item-added'): void;
}>();

const isOpen = ref(false);
const inputMessage = ref('');
const loading = ref(false);
const addingItemId = ref<number | null>(null);
const messagesContainer = ref<HTMLElement | null>(null);

interface RecommendedItem {
    id: number;
    name: string;
    price: number;
    image: string | null;
    category_name?: string;
}

interface ChatMessage {
    role: 'user' | 'assistant';
    content: string;
    recommendedItems?: RecommendedItem[];
    time: string;
}

const messages = ref<ChatMessage[]>([
    {
        role: 'assistant',
        content: `أهلاً وسهلاً بك في مطعم ${props.restaurantName}! 🌸\nأنا «ويتر تدلل الذكي»، يسعدني أن أساعدك في اختيار أشهى الأطباق حسب رغبتك أو ميزانيتك أو عدد أفرادك. كيف يمكنني تدليلك اليوم؟`,
        time: getCurrentTime(),
    },
]);

function getCurrentTime(): string {
    const d = new Date();
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

const quickPrompts = [
    { label: '⭐ أشهر الأطباق', prompt: 'ما هي الأطباق الأكثر طلباً وشهرة في المطعم؟' },
    { label: '💰 وجبة بميزانية محددة', prompt: 'اقترح لي وجبة لذيذة متكاملة بسعر اقتصادي مناسب.' },
    { label: '👨‍👩‍👧‍👦 وجبة عائلية لـ 4 أشخاص', prompt: 'نحن 4 أشخاص، اقترح لنا تشكيلة أطباق ومقبلات مشبعة ومتنوعة.' },
    { label: '🥗 خيارات صحية وخفيفة', prompt: 'أريد خيارات صحية وخفيفة قليلة الدسم أو مناسبة للدايت.' },
];

function scrollToBottom() {
    nextTick(() => {
        if (messagesContainer.value) {
            messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
        }
    });
}

function openModal() {
    isOpen.value = true;
    scrollToBottom();
}

function closeModal() {
    isOpen.value = false;
}

async function sendMessage(textToSend?: string) {
    const text = textToSend || inputMessage.value.trim();
    if (!text || loading.value) return;

    // Push user message
    messages.value.push({
        role: 'user',
        content: text,
        time: getCurrentTime(),
    });

    inputMessage.value = '';
    loading.value = true;
    scrollToBottom();

    // Prepare history
    const historyPayload = messages.value.slice(-6).map((m) => ({
        role: m.role === 'user' ? 'user' : 'model',
        content: m.content,
    }));

    try {
        const { data } = await axios.post('/api/public/ai-waiter/chat', {
            message: text,
            history: historyPayload,
        });

        messages.value.push({
            role: 'assistant',
            content: data.data.reply,
            recommendedItems: data.data.recommended_items || [],
            time: getCurrentTime(),
        });
    } catch (e: any) {
        messages.value.push({
            role: 'assistant',
            content: e.response?.data?.message || 'عذراً، حدث خطأ مؤقت في الاتصال. يمكنك إعادة السؤال أو تصفح المنيو مباشرة.',
            time: getCurrentTime(),
        });
    } finally {
        loading.value = false;
        scrollToBottom();
    }
}

async function addItemToCart(item: RecommendedItem) {
    addingItemId.value = item.id;
    try {
        await axios.post('/api/public/cart/items', {
            menu_item_id: item.id,
            quantity: 1,
        });
        emit('item-added');
    } catch (e) {
        alert('تعذر إضافة الصنف للسلة، يرجى المحاولة ثانية');
    } finally {
        addingItemId.value = null;
    }
}

defineExpose({
    openModal,
    closeModal,
});
</script>

<template>
    <div>
        <!-- زر فتح المحادثة العائم في صفحة المنيو -->
        <button
            type="button"
            @click="openModal"
            class="fixed bottom-20 left-4 z-30 flex items-center gap-2 rounded-full bg-gradient-to-r from-purple-600 via-indigo-600 to-purple-700 px-4 py-3 text-white shadow-xl shadow-purple-600/30 hover:shadow-purple-600/50 hover:scale-105 active:scale-95 transition-all duration-300 border border-purple-400/40"
            dir="rtl"
        >
            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-white/20 text-lg">
                👨‍🍳
            </span>
            <div class="text-right">
                <span class="block text-xs font-black tracking-wide leading-none">اسأل الويتر الذكي</span>
                <span class="block text-[10px] text-purple-200 mt-0.5 leading-none">اقتراحات فورية · AI</span>
            </div>
            <span class="flex h-2 w-2 rounded-full bg-emerald-400 animate-pulse"></span>
        </button>

        <!-- نافذة المحادثة المنبثقة (Modal / Drawer) -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-300 ease-out"
                enter-from-class="opacity-0 translate-y-6 sm:scale-95"
                enter-to-class="opacity-100 translate-y-0 sm:scale-100"
                leave-active-class="transition duration-200 ease-in"
                leave-from-class="opacity-100 translate-y-0 sm:scale-100"
                leave-to-class="opacity-0 translate-y-6 sm:scale-95"
            >
                <div
                    v-if="isOpen"
                    class="fixed inset-0 z-[70] flex items-end sm:items-center justify-center bg-black/60 backdrop-blur-xs p-0 sm:p-4"
                    dir="rtl"
                    @click.self="closeModal"
                >
                    <div class="flex flex-col w-full max-w-lg h-[85vh] sm:h-[650px] bg-white dark:bg-[#0a0f1d] rounded-t-3xl sm:rounded-3xl shadow-2xl border border-stone-200 dark:border-slate-800 overflow-hidden transition-colors">
                        <!-- رأس المحادثة -->
                        <div class="flex items-center justify-between p-4 bg-gradient-to-r from-purple-700 via-indigo-700 to-purple-800 text-white shrink-0 shadow-sm">
                            <div class="flex items-center gap-3">
                                <div class="relative flex h-11 w-11 items-center justify-center rounded-2xl bg-white/15 border border-white/20 text-2xl shadow-inner">
                                    👨‍🍳
                                    <span class="absolute -bottom-0.5 -left-0.5 flex h-3 w-3 rounded-full bg-emerald-400 ring-2 ring-purple-800"></span>
                                </div>
                                <div>
                                    <h3 class="font-bold text-sm sm:text-base leading-tight flex items-center gap-1.5">
                                        <span>ويتر تدلل الذكي</span>
                                        <span class="bg-white/20 text-[10px] px-2 py-0.5 rounded-full font-mono font-normal">AI Assistant</span>
                                    </h3>
                                    <p class="text-xs text-purple-200 mt-0.5 font-medium">
                                        مساعد الضيافة لمطعم {{ restaurantName }}
                                    </p>
                                </div>
                            </div>
                            <button
                                type="button"
                                @click="closeModal"
                                class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/10 hover:bg-white/20 text-white transition-colors"
                            >
                                ✕
                            </button>
                        </div>

                        <!-- أزرار البدء السريع (Quick Prompts) -->
                        <div class="flex gap-2 p-2.5 overflow-x-auto bg-purple-50/60 dark:bg-slate-900/40 border-b border-purple-100 dark:border-slate-800/80 shrink-0 no-scrollbar">
                            <button
                                v-for="(chip, idx) in quickPrompts"
                                :key="idx"
                                type="button"
                                @click="sendMessage(chip.prompt)"
                                :disabled="loading"
                                class="whitespace-nowrap px-3 py-1.5 rounded-full text-xs font-semibold bg-white dark:bg-slate-800 text-purple-900 dark:text-purple-200 border border-purple-200/80 dark:border-purple-800/60 hover:bg-purple-100 dark:hover:bg-purple-950/60 active:scale-95 transition-all shadow-2xs shrink-0 disabled:opacity-50"
                            >
                                {{ chip.label }}
                            </button>
                        </div>

                        <!-- قائمة الرسائل المتدفقة -->
                        <div
                            ref="messagesContainer"
                            class="flex-1 p-4 overflow-y-auto space-y-4 bg-stone-50/60 dark:bg-[#070b14]"
                        >
                            <div
                                v-for="(msg, idx) in messages"
                                :key="idx"
                                class="flex flex-col"
                                :class="msg.role === 'user' ? 'items-start' : 'items-end'"
                            >
                                <!-- فقاعة الرسالة -->
                                <div
                                    class="max-w-[85%] rounded-2xl p-3.5 text-xs sm:text-sm leading-relaxed whitespace-pre-line shadow-xs"
                                    :class="msg.role === 'user'
                                        ? 'bg-purple-600 text-white rounded-tr-xs'
                                        : 'bg-white dark:bg-slate-800/90 text-stone-800 dark:text-slate-100 border border-stone-200/80 dark:border-slate-700/80 rounded-tl-xs'"
                                >
                                    {{ msg.content }}
                                </div>

                                <!-- بطاقات الأصناف المقترحة القابلة للإضافة المباشرة -->
                                <div
                                    v-if="msg.recommendedItems && msg.recommendedItems.length > 0"
                                    class="w-full max-w-[95%] mt-2.5 space-y-2"
                                >
                                    <div
                                        v-for="item in msg.recommendedItems"
                                        :key="item.id"
                                        class="flex items-center justify-between p-2.5 rounded-xl bg-white dark:bg-slate-800 border border-purple-200 dark:border-purple-900/50 shadow-2xs hover:border-purple-300 transition-colors"
                                    >
                                        <div class="flex items-center gap-2.5">
                                            <div
                                                v-if="item.image"
                                                class="w-11 h-11 rounded-lg overflow-hidden shrink-0 bg-stone-100"
                                            >
                                                <img :src="item.image" :alt="item.name" class="w-full h-full object-cover" />
                                            </div>
                                            <div v-else class="w-11 h-11 rounded-lg bg-purple-100 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 flex items-center justify-center text-lg shrink-0">
                                                🍽️
                                            </div>
                                            <div>
                                                <h4 class="font-bold text-xs text-stone-900 dark:text-white">{{ item.name }}</h4>
                                                <p class="text-[11px] font-bold text-purple-600 dark:text-purple-400 mt-0.5" dir="ltr">
                                                    {{ item.price }} {{ currency }}
                                                </p>
                                            </div>
                                        </div>

                                        <button
                                            type="button"
                                            @click="addItemToCart(item)"
                                            :disabled="addingItemId === item.id"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition-colors shadow-2xs active:scale-95 disabled:opacity-50"
                                        >
                                            <span>{{ addingItemId === item.id ? '...' : '+ إضافة' }}</span>
                                            <span class="text-sm">🛒</span>
                                        </button>
                                    </div>
                                </div>

                                <span class="text-[10px] text-stone-400 mt-1 px-1">
                                    {{ msg.time }}
                                </span>
                            </div>

                            <!-- مؤشر جاري التفكير -->
                            <div v-if="loading" class="flex items-center gap-2 text-xs text-purple-600 dark:text-purple-400 font-bold p-2 bg-purple-50 dark:bg-purple-950/40 rounded-xl w-fit">
                                <span class="animate-spin text-base">👨‍🍳</span>
                                <span>الويتر الذكي يجهز لك أفضل الاقتراحات...</span>
                            </div>
                        </div>

                        <!-- حقل الإدخال والإرسال -->
                        <div class="p-3 bg-white dark:bg-[#0a0f1d] border-t border-stone-200 dark:border-slate-800 shrink-0">
                            <form @submit.prevent="sendMessage()" class="flex items-center gap-2">
                                <input
                                    v-model="inputMessage"
                                    type="text"
                                    placeholder="اسأل عن أي صنف، ميزانية، أو اقتراح..."
                                    :disabled="loading"
                                    class="flex-1 rounded-xl border-stone-200 dark:border-slate-700 bg-stone-50 dark:bg-slate-900 text-stone-900 dark:text-white placeholder:text-stone-400 text-xs sm:text-sm py-2.5 px-3.5 focus:border-purple-500 focus:ring-purple-500 transition-colors"
                                />
                                <button
                                    type="submit"
                                    :disabled="!inputMessage.trim() || loading"
                                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-purple-600 text-white font-bold hover:bg-purple-700 active:scale-95 transition-all disabled:opacity-50 shrink-0 shadow-sm shadow-purple-600/30"
                                >
                                    <svg class="h-4 w-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
