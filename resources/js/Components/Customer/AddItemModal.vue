<script setup lang="ts">
import { ref, computed, watch, onUnmounted } from 'vue';
import { useVisualViewportModal } from '@/composables/useVisualViewport';
import { formatAmount } from '@/utils/money';

export interface MenuItemOption {
    id: number;
    name: string;
    price: string;
    compare_at_price?: string | null;
}

const props = defineProps<{
    item: MenuItemOption | null;
    currency: string;
    loading: boolean;
}>();

const isOpen = computed(() => !!props.item);
const { viewportBackdropStyle } = useVisualViewportModal(isOpen);

const formatUnitPrice = formatAmount;

const emit = defineEmits<{
    close: [];
    confirm: [payload: { quantity: number; note: string | null }];
}>();

const quantity = ref(1);
const note = ref('');

const itemTotal = computed(() => {
    if (!props.item?.price) return '0';
    const cleanStr = String(props.item.price).replace(/,/g, '');
    const num = parseFloat(cleanStr);
    const total = isNaN(num) ? 0 : Math.round(num * quantity.value * 100) / 100;
    return formatAmount(total);
});

watch(() => props.item, (newItem) => {
    quantity.value = 1;
    note.value = '';
    if (newItem) {
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = '';
    }
});

onUnmounted(() => {
    document.body.style.overflow = '';
});

function onImageError(event: Event) {
    const img = event.target as HTMLImageElement;
    if (img && !img.dataset.fallbackApplied) {
        img.dataset.fallbackApplied = 'true';
        img.src = '/images/tedallal-golden-logo-pure.png';
        img.classList.remove('object-cover');
        img.classList.add('object-contain', 'p-6', 'bg-gradient-to-br', 'from-amber-950/30', 'via-stone-900', 'to-black');
    }
}
</script>

<template>
    <Teleport to="body">
        <div
            v-if="item"
            :style="viewportBackdropStyle"
            class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/65 backdrop-blur-xs p-0 sm:p-4 overflow-hidden"
            @click.self="emit('close')"
        >
            <div class="w-full max-w-lg rounded-t-3xl sm:rounded-3xl bg-white dark:bg-[#0a0f1d] p-5 sm:p-6 shadow-2xl border-t sm:border border-stone-200/80 dark:border-slate-800 transition-colors max-h-[90%] flex flex-col pb-[max(1.25rem,env(safe-area-inset-bottom))]" dir="rtl">
                <!-- مقبض السحب المرئي للموبايل -->
                <div class="w-12 h-1.5 rounded-full bg-stone-300 dark:bg-stone-700 mx-auto -mt-2 mb-3 sm:hidden shrink-0"></div>
                
                <div class="flex-1 overflow-y-auto space-y-4 pr-0.5">
                    <div class="aspect-video w-full overflow-hidden rounded-2xl border border-stone-100 dark:border-slate-800 flex items-center justify-center bg-gradient-to-br from-amber-50 to-stone-100 dark:from-slate-900 dark:to-[#070709]">
                        <img
                            :src="(item as any).image || '/images/tedallal-golden-logo-pure.png'"
                            :alt="item.name"
                            :class="[
                                ((item as any).image && !(item as any).image.includes('tedallal-golden-logo'))
                                    ? 'h-full w-full object-cover'
                                    : 'h-full w-full object-contain p-6 bg-gradient-to-br from-amber-500/10 via-stone-900 to-[#070709] drop-shadow-[0_4px_16px_rgba(218,165,32,0.35)]'
                            ]"
                            @error="onImageError"
                        />
                    </div>
                    <h3 class="text-xl font-bold text-stone-900 dark:text-white">{{ item.name }}</h3>
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-amber-700 dark:text-amber-400 font-black text-lg">{{ formatUnitPrice(item.price) }} {{ currency }}</p>
                        <p
                            v-if="item.compare_at_price && Number(item.compare_at_price) > Number(item.price)"
                            class="text-sm line-through text-stone-400 dark:text-slate-500 font-semibold"
                        >
                            {{ formatUnitPrice(item.compare_at_price) }} {{ currency }}
                        </p>
                        <span
                            v-if="item.compare_at_price && Number(item.compare_at_price) > Number(item.price)"
                            class="rounded-full bg-rose-600 text-white px-2 py-0.5 text-[11px] font-black"
                        >
                            🔥 خصم {{ Math.round(((Number(item.compare_at_price) - Number(item.price)) / Number(item.compare_at_price)) * 100) }}%
                        </span>
                    </div>

                    <div class="mt-2 flex items-center justify-center gap-4">
                        <button
                            type="button"
                            class="flex h-11 w-11 items-center justify-center rounded-xl bg-stone-100 dark:bg-slate-800 text-stone-900 dark:text-white hover:bg-stone-200 dark:hover:bg-slate-700 active:scale-95 transition-all text-lg font-bold select-none"
                            @click="quantity = Math.max(1, quantity - 1)"
                        >
                            −
                        </button>
                        <span class="min-w-[2rem] text-center text-xl font-black text-stone-900 dark:text-white">{{ quantity }}</span>
                        <button
                            type="button"
                            class="flex h-11 w-11 items-center justify-center rounded-xl bg-stone-100 dark:bg-slate-800 text-stone-900 dark:text-white hover:bg-stone-200 dark:hover:bg-slate-700 active:scale-95 transition-all text-lg font-bold select-none"
                            @click="quantity += 1"
                        >
                            +
                        </button>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-stone-600 dark:text-slate-300">ملاحظة على الصنف (اختياري)</label>
                        <textarea
                            v-model="note"
                            rows="2"
                            class="w-full rounded-xl border-stone-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-stone-900 dark:text-white placeholder:text-stone-400 dark:placeholder:text-slate-500 text-base sm:text-sm focus:border-amber-500 focus:ring-amber-500 transition-colors"
                            placeholder="مثال: بدون بصل، زيادة صوص..."
                        />
                    </div>
                </div>

                <!-- الفوتر الثابت بالأسفل (ظاهر دائماً 100%) -->
                <div class="mt-4 pt-3 border-t border-stone-100 dark:border-slate-800 flex gap-3 shrink-0">
                    <button
                        type="button"
                        class="flex-1 rounded-xl bg-stone-100 dark:bg-slate-800 hover:bg-stone-200 dark:hover:bg-slate-700 py-3 font-semibold text-stone-700 dark:text-slate-300 active:scale-95 transition-all text-sm"
                        @click="emit('close')"
                    >
                        إلغاء
                    </button>
                    <button
                        type="button"
                        class="flex-1 rounded-xl bg-gradient-to-r from-amber-600 to-amber-500 hover:from-amber-700 hover:to-amber-600 py-3.5 font-bold text-white shadow-lg shadow-amber-500/20 active:scale-95 transition-all disabled:opacity-50 flex items-center justify-center gap-2 text-sm"
                        :disabled="loading"
                        @click="emit('confirm', { quantity, note: note || null })"
                    >
                        <span>{{ loading ? 'جاري الإضافة...' : 'أضف للسلة' }}</span>
                        <span v-if="!loading" class="text-xs bg-black/20 px-2 py-0.5 rounded-full font-black">
                            • {{ itemTotal }} {{ currency }}
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
