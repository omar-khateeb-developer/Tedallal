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
            class="fixed inset-0 z-40 bg-black/40"
            @click="close"
        />
        <div
            v-if="open"
            class="fixed inset-x-0 bottom-0 z-50 max-h-[85vh] overflow-y-auto rounded-t-3xl bg-white shadow-2xl"
            dir="rtl"
        >
            <div class="sticky top-0 flex items-center justify-between border-b bg-white px-4 py-4">
                <h2 class="text-lg font-bold">السلة ({{ count }})</h2>
                <button type="button" class="rounded-lg px-3 py-1 text-stone-500" @click="close">✕</button>
            </div>

            <div v-if="loading" class="py-12 text-center text-stone-500">جاري التحميل...</div>

            <div v-else-if="items.length === 0" class="py-12 text-center text-stone-500">
                السلة فارغة
            </div>

            <div v-else class="space-y-3 p-4 pb-32">
                <article
                    v-for="item in items"
                    :key="item.id"
                    class="rounded-2xl bg-stone-50 p-4"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex-1">
                            <h3 class="font-semibold">{{ item.menu_item.name }}</h3>
                            <p class="text-sm text-amber-700">
                                {{ item.menu_item.price }} {{ currency }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="h-9 w-9 rounded-lg bg-white"
                                @click="updateQty(item, item.quantity - 1)"
                            >
                                −
                            </button>
                            <span class="w-6 text-center font-medium">{{ item.quantity }}</span>
                            <button
                                type="button"
                                class="h-9 w-9 rounded-lg bg-white"
                                @click="updateQty(item, item.quantity + 1)"
                            >
                                +
                            </button>
                        </div>
                    </div>
                    <input
                        :value="item.note ?? ''"
                        type="text"
                        class="mt-3 w-full rounded-lg border-stone-200 text-sm"
                        placeholder="ملاحظة على الصنف..."
                        @change="updateNote(item, ($event.target as HTMLInputElement).value)"
                    />
                </article>
            </div>

            <div
                v-if="items.length > 0 && !loading"
                class="fixed inset-x-0 bottom-0 border-t bg-white p-4"
            >
                <div class="mb-3 flex justify-between text-lg font-bold">
                    <span>المجموع</span>
                    <span>{{ subtotal }} {{ currency }}</span>
                </div>
                <button
                    type="button"
                    class="w-full rounded-2xl bg-amber-600 py-4 text-lg font-bold text-white"
                    @click="emit('checkout')"
                >
                    متابعة الطلب
                </button>
            </div>
        </div>
    </Teleport>
</template>
