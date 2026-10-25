<script setup lang="ts">
import { ref, watch } from 'vue';
import axios from 'axios';

export interface CartLine {
    id: number;
    quantity: number;
    note: string | null;
    menu_item: { name: string; price: string };
}

const props = defineProps<{
    open: boolean;
    currency: string;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    checkout: [];
    updated: [];
}>();

const items = ref<CartLine[]>([]);
const subtotal = ref('0.00');
const count = ref(0);
const loading = ref(false);

async function load() {
    loading.value = true;
    try {
        const { data } = await axios.get('/api/public/cart');
        items.value = data.data?.items ?? [];
        subtotal.value = data.data?.subtotal ?? '0.00';
        count.value = data.data?.count ?? 0;
    } finally {
        loading.value = false;
    }
}

async function updateQty(item: CartLine, quantity: number) {
    await axios.patch(`/api/public/cart/items/${item.id}`, {
        quantity,
        note: item.note,
    });
    await load();
    emit('updated');
}

async function updateNote(item: CartLine, note: string) {
    await axios.patch(`/api/public/cart/items/${item.id}`, {
        quantity: item.quantity,
        note: note || null,
    });
    item.note = note || null;
    emit('updated');
}

function close() {
    emit('update:open', false);
    emit('updated');
}

watch(() => props.open, (isOpen) => {
    if (isOpen) load();
});

defineExpose({ load, count, subtotal });
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-40 bg-black/60 backdrop-blur-xs transition-opacity"
            @click="close"
        />
        <div
            v-if="open"
            class="fixed inset-x-0 bottom-0 z-50 max-h-[85vh] overflow-y-auto rounded-t-3xl bg-white dark:bg-[#0a0f1d] text-stone-900 dark:text-white shadow-2xl border-t border-stone-200/80 dark:border-slate-800 transition-colors"
            dir="rtl"
        >
            <div class="sticky top-0 z-10 flex items-center justify-between border-b border-stone-100 dark:border-slate-800 bg-white/95 dark:bg-[#0a0f1d]/95 backdrop-blur-md px-4 py-4">
                <h2 class="text-lg font-bold text-stone-900 dark:text-white flex items-center gap-2">
                    <span>السلة</span>
                    <span class="text-xs px-2 py-0.5 rounded-full bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 font-black">({{ count }})</span>
                </h2>
                <button type="button" class="h-8 w-8 rounded-full flex items-center justify-center text-stone-500 hover:text-stone-800 dark:text-slate-400 dark:hover:text-white hover:bg-stone-100 dark:hover:bg-slate-800 transition-colors" @click="close">✕</button>
            </div>

            <div v-if="loading" class="py-16 text-center text-stone-500 dark:text-slate-400">جاري التحميل...</div>

            <div v-else-if="items.length === 0" class="py-16 text-center text-stone-500 dark:text-slate-400">
                السلة فارغة
            </div>

            <div v-else class="space-y-3 p-4 pb-32">
                <article
                    v-for="item in items"
                    :key="item.id"
                    class="rounded-2xl bg-stone-50 dark:bg-slate-900/80 p-4 border border-stone-100 dark:border-slate-800 transition-colors"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex-1">
                            <h3 class="font-bold text-stone-900 dark:text-white">{{ item.menu_item.name }}</h3>
                            <p class="text-sm font-black text-amber-700 dark:text-amber-400 mt-0.5">
                                {{ item.menu_item.price }} {{ currency }}
                            </p>
                        </div>
                        <div class="flex items-center gap-1.5 bg-white dark:bg-slate-800/80 border border-stone-200 dark:border-slate-700 rounded-full p-0.5 shadow-2xs">
                            <button
                                type="button"
                                class="h-8 w-8 rounded-full bg-stone-50 dark:bg-slate-700 text-stone-800 dark:text-slate-100 font-bold flex items-center justify-center hover:bg-stone-200 dark:hover:bg-slate-600 active:scale-90 transition-all text-sm"
                                @click="updateQty(item, item.quantity - 1)"
                            >
                                −
                            </button>
                            <span class="w-6 text-center font-black text-sm text-stone-900 dark:text-white">{{ item.quantity }}</span>
                            <button
                                type="button"
                                class="h-8 w-8 rounded-full bg-amber-600 dark:bg-amber-500 text-white font-bold flex items-center justify-center hover:bg-amber-700 dark:hover:bg-amber-400 active:scale-90 transition-all text-sm"
                                @click="updateQty(item, item.quantity + 1)"
                            >
                                +
                            </button>
                        </div>
                    </div>
                    <input
                        :value="item.note ?? ''"
                        type="text"
                        class="mt-3 w-full rounded-xl border-stone-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-stone-900 dark:text-white placeholder:text-stone-400 dark:placeholder:text-slate-500 text-xs focus:border-amber-500 focus:ring-amber-500"
                        placeholder="ملاحظة على الصنف (مثال: بدون ثوم)..."
                        @change="updateNote(item, ($event.target as HTMLInputElement).value)"
                    />
                </article>
            </div>

            <div
                v-if="items.length > 0 && !loading"
                class="fixed inset-x-0 bottom-0 border-t border-stone-200 dark:border-slate-800 bg-white/95 dark:bg-[#0a0f1d]/95 backdrop-blur-md p-4 transition-colors"
            >
                <div class="mb-3 flex justify-between text-lg font-bold text-stone-900 dark:text-white">
                    <span>المجموع الإجمالي</span>
                    <span class="text-amber-600 dark:text-amber-400 font-black">{{ subtotal }} {{ currency }}</span>
                </div>
                <button
                    type="button"
                    class="w-full rounded-2xl bg-amber-600 dark:bg-amber-500 hover:bg-amber-700 dark:hover:bg-amber-400 py-3.5 text-base sm:text-lg font-bold text-white shadow-lg shadow-amber-200 dark:shadow-amber-950/50 active:scale-[0.98] transition-all"
                    @click="emit('checkout')"
                >
                    متابعة الطلب
                </button>
            </div>
        </div>
    </Teleport>
</template>
