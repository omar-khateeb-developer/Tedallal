<script setup lang="ts">
import CustomerLayout from '@/Layouts/CustomerLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import axios from 'axios';

interface CartItem {
    id: number;
    quantity: number;
    note: string | null;
    menu_item: { name: string; price: string };
}

const props = defineProps<{
    restaurant: { name: string; currency: string };
    table: { name: string };
    publicToken: string;
}>();

const items = ref<CartItem[]>([]);
const subtotal = ref('0.00');
const loading = ref(true);
const submitting = ref(false);
const customerNote = ref('');

async function loadCart() {
    loading.value = true;
    try {
        const { data } = await axios.get('/api/public/cart');
        items.value = data.data.items ?? [];
        subtotal.value = data.data.subtotal ?? '0.00';
    } finally {
        loading.value = false;
    }
}

async function updateQty(item: CartItem, quantity: number) {
    await axios.patch(`/api/public/cart/items/${item.id}`, { quantity });
    await loadCart();
}

async function submitOrder() {
    submitting.value = true;
    try {
        const { data } = await axios.post('/api/public/orders', {
            customer_note: customerNote.value || null,
        });
        const orderNumber = data.data.order_number;
        router.visit(`/t/${props.publicToken}/orders/${orderNumber}`);
    } catch (e: unknown) {
        alert('تعذر إرسال الطلب. حاول مرة أخرى.');
    } finally {
        submitting.value = false;
    }
}

onMounted(loadCart);
</script>

<template>
    <CustomerLayout title="السلة">
        <template #actions>
            <Link
                :href="`/t/${publicToken}`"
                class="text-sm font-medium text-amber-700"
            >
                العودة للقائمة
            </Link>
        </template>

        <div v-if="loading" class="py-12 text-center text-stone-500">جاري التحميل...</div>

        <div v-else-if="items.length === 0" class="py-12 text-center">
            <p class="text-stone-500">السلة فارغة</p>
            <Link
                :href="`/t/${publicToken}`"
                class="mt-4 inline-block rounded-xl bg-amber-600 px-6 py-3 text-white"
            >
                تصفح القائمة
            </Link>
        </div>

        <div v-else class="space-y-4">
            <article
                v-for="item in items"
                :key="item.id"
                class="rounded-2xl bg-white p-4 shadow-sm"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="font-semibold">{{ item.menu_item.name }}</h3>
                        <p class="text-sm text-amber-700">
                            {{ item.menu_item.price }} {{ restaurant.currency }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="h-9 w-9 rounded-lg bg-stone-100"
                            @click="updateQty(item, item.quantity - 1)"
                        >
                            −
                        </button>
                        <span class="w-6 text-center font-medium">{{ item.quantity }}</span>
                        <button
                            type="button"
                            class="h-9 w-9 rounded-lg bg-stone-100"
                            @click="updateQty(item, item.quantity + 1)"
                        >
                            +
                        </button>
                    </div>
                </div>
            </article>

            <div class="rounded-2xl bg-white p-4">
                <label class="mb-2 block text-sm text-stone-600">ملاحظات الطلب</label>
                <textarea
                    v-model="customerNote"
                    rows="2"
                    class="w-full rounded-xl border-stone-200 text-sm"
                    placeholder="اختياري..."
                />
            </div>

            <div class="rounded-2xl bg-amber-50 p-4">
                <div class="flex justify-between text-lg font-bold">
                    <span>المجموع</span>
                    <span>{{ subtotal }} {{ restaurant.currency }}</span>
                </div>
            </div>

            <button
                type="button"
                class="w-full rounded-2xl bg-amber-600 py-4 text-lg font-bold text-white disabled:opacity-50"
                :disabled="submitting"
                @click="submitOrder"
            >
                {{ submitting ? 'جاري الإرسال...' : 'تأكيد الطلب' }}
            </button>
        </div>
    </CustomerLayout>
</template>
