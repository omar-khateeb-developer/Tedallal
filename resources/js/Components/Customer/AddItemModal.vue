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
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 sm:items-center"
            @click.self="emit('close')"
        >
            <div class="w-full max-w-lg rounded-t-3xl bg-white p-6 shadow-xl sm:rounded-3xl" dir="rtl">
                <h3 class="text-xl font-bold">{{ item.name }}</h3>
                <p class="mt-1 text-amber-700">{{ item.price }} {{ currency }}</p>

                <div class="mt-5 flex items-center justify-center gap-4">
                    <button
                        type="button"
                        class="flex h-11 w-11 items-center justify-center rounded-xl bg-stone-100 text-lg"
                        @click="quantity = Math.max(1, quantity - 1)"
                    >
                        −
                    </button>
                    <span class="min-w-[2rem] text-center text-xl font-bold">{{ quantity }}</span>
                    <button
                        type="button"
                        class="flex h-11 w-11 items-center justify-center rounded-xl bg-stone-100 text-lg"
                        @click="quantity += 1"
                    >
                        +
                    </button>
                </div>

                <div class="mt-5">
                    <label class="mb-1 block text-sm text-stone-600">ملاحظة على الصنف (اختياري)</label>
                    <textarea
                        v-model="note"
                        rows="2"
                        class="w-full rounded-xl border-stone-200 text-sm"
                        placeholder="مثال: بدون بصل"
                    />
                </div>

                <div class="mt-6 flex gap-3">
                    <button
                        type="button"
                        class="flex-1 rounded-xl bg-stone-100 py-3 font-medium"
                        @click="emit('close')"
                    >
                        إلغاء
                    </button>
                    <button
                        type="button"
                        class="flex-1 rounded-xl bg-amber-600 py-3 font-medium text-white disabled:opacity-50"
                        :disabled="loading"
                        @click="emit('confirm', { quantity, note: note || null })"
                    >
                        {{ loading ? '...' : 'أضف للسلة' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
