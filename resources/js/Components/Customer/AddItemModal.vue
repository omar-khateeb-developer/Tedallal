<script setup lang="ts">
import { ref, watch } from 'vue';

export interface MenuItemOption {
    id: number;
    name: string;
    price: string;
}

const props = defineProps<{
    item: MenuItemOption | null;
    currency: string;
    loading: boolean;
}>();

const emit = defineEmits<{
    close: [];
    confirm: [payload: { quantity: number; note: string | null }];
}>();

const quantity = ref(1);
const note = ref('');

watch(() => props.item, () => {
    quantity.value = 1;
    note.value = '';
});
</script>

<template>
    <Teleport to="body">
        <div
            v-if="item"
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/60 backdrop-blur-xs sm:items-center"
            @click.self="emit('close')"
        >
            <div class="w-full max-w-lg rounded-t-3xl bg-white dark:bg-[#0a0f1d] p-6 shadow-2xl sm:rounded-3xl border-t sm:border border-stone-200/80 dark:border-slate-800 transition-colors" dir="rtl">
                <div v-if="(item as any).image" class="mb-4 aspect-video w-full overflow-hidden rounded-2xl border border-stone-100 dark:border-slate-800">
                    <img :src="(item as any).image" :alt="item.name" class="h-full w-full object-cover" />
                </div>
                <h3 class="text-xl font-bold text-stone-900 dark:text-white">{{ item.name }}</h3>
                <p class="mt-1 text-amber-700 dark:text-amber-400 font-bold text-lg">{{ item.price }} {{ currency }}</p>

                <div class="mt-5 flex items-center justify-center gap-4">
                    <button
                        type="button"
                        class="flex h-11 w-11 items-center justify-center rounded-xl bg-stone-100 dark:bg-slate-800 text-stone-900 dark:text-white hover:bg-stone-200 dark:hover:bg-slate-700 active:scale-95 transition-all text-lg font-bold"
                        @click="quantity = Math.max(1, quantity - 1)"
                    >
                        −
                    </button>
                    <span class="min-w-[2rem] text-center text-xl font-black text-stone-900 dark:text-white">{{ quantity }}</span>
                    <button
                        type="button"
                        class="flex h-11 w-11 items-center justify-center rounded-xl bg-stone-100 dark:bg-slate-800 text-stone-900 dark:text-white hover:bg-stone-200 dark:hover:bg-slate-700 active:scale-95 transition-all text-lg font-bold"
                        @click="quantity += 1"
                    >
                        +
                    </button>
                </div>

                <div class="mt-5">
                    <label class="mb-1 block text-sm font-medium text-stone-600 dark:text-slate-300">ملاحظة على الصنف (اختياري)</label>
                    <textarea
                        v-model="note"
                        rows="2"
                        class="w-full rounded-xl border-stone-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-stone-900 dark:text-white placeholder:text-stone-400 dark:placeholder:text-slate-500 text-sm focus:border-amber-500 focus:ring-amber-500 transition-colors"
                        placeholder="مثال: بدون بصل، زيادة صوص..."
                    />
                </div>

                <div class="mt-6 flex gap-3">
                    <button
                        type="button"
                        class="flex-1 rounded-xl bg-stone-100 dark:bg-slate-800 hover:bg-stone-200 dark:hover:bg-slate-700 py-3 font-semibold text-stone-700 dark:text-slate-300 active:scale-95 transition-all"
                        @click="emit('close')"
                    >
                        إلغاء
                    </button>
                    <button
                        type="button"
                        class="flex-1 rounded-xl bg-amber-600 dark:bg-amber-500 hover:bg-amber-700 dark:hover:bg-amber-400 py-3 font-bold text-white shadow-md shadow-amber-200 dark:shadow-amber-950/50 active:scale-95 transition-all disabled:opacity-50"
                        :disabled="loading"
                        @click="emit('confirm', { quantity, note: note || null })"
                    >
                        {{ loading ? 'جاري الإضافة...' : 'أضف للسلة' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
