<script setup lang="ts">
import AddItemModal from '@/Components/Customer/AddItemModal.vue';
import CartDrawer from '@/Components/Customer/CartDrawer.vue';
import CheckoutModal from '@/Components/Customer/CheckoutModal.vue';
import CustomerLayout from '@/Layouts/CustomerLayout.vue';
import { router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';

interface MenuItem {
    id: number;
    name: string;
    description: string | null;
    price: string;
    image: string | null;
}

interface Category {
    id: number;
    name: string;
    active_items?: MenuItem[];
}

const props = defineProps<{
    restaurant: { name: string; currency: string; navbar_image?: string | null; logo?: string | null };
    table: { name: string; public_token: string };
    categories: Category[];
    openCart?: boolean;
}>();

const activeCategory = ref(props.categories[0]?.id ?? 0);
const cartCount = ref(0);
const cartOpen = ref(false);
const checkoutOpen = ref(false);
const selectedItem = ref<MenuItem | null>(null);
const adding = ref(false);
const submitting = ref(false);
const customerNote = ref('');
const cartSubtotal = ref('0.00');
const cartDrawerRef = ref<InstanceType<typeof CartDrawer> | null>(null);
const errorMessage = ref('');

const visibleItems = computed(() => {
    const cat = props.categories.find((c) => c.id === activeCategory.value);
    return cat?.active_items ?? [];
});

async function refreshCartSummary() {
    try {
        const { data } = await axios.get('/api/public/cart');
        cartCount.value = data.data?.count ?? 0;
        cartSubtotal.value = data.data?.subtotal ?? '0.00';
        cartDrawerRef.value?.load();
    } catch {
        cartCount.value = 0;
    }
}

function openAddModal(item: MenuItem) {
    selectedItem.value = item;
}

async function confirmAdd(payload: { quantity: number; note: string | null }) {
    if (!selectedItem.value) return;
    adding.value = true;
    errorMessage.value = '';
    try {
        await axios.post('/api/public/cart/items', {
            menu_item_id: selectedItem.value.id,
            quantity: payload.quantity,
            note: payload.note,
        });
        selectedItem.value = null;
        await refreshCartSummary();
        cartOpen.value = true;
    } catch {
        errorMessage.value = 'تعذر إضافة الصنف. قد يكون غير متاح.';
    } finally {
        adding.value = false;
    }
}

function openCheckout() {
    cartOpen.value = false;
    checkoutOpen.value = true;
}

async function submitOrder() {
    submitting.value = true;
    errorMessage.value = '';
    try {
        const { data } = await axios.post('/api/public/orders', {
            customer_note: customerNote.value || null,
        });
        checkoutOpen.value = false;
        customerNote.value = '';
        router.visit(`/t/${props.table.public_token}/orders/${data.data.order_number}`);
    } catch {
        errorMessage.value = 'تعذر إرسال الطلب. تحقق من السلة وحاول مرة أخرى.';
        checkoutOpen.value = false;
        cartOpen.value = true;
    } finally {
        submitting.value = false;
    }
}

function formatPrice(price: string) {
    return `${price} ${props.restaurant.currency}`;
}

onMounted(() => {
    refreshCartSummary();
    if (props.openCart) {
        cartOpen.value = true;
    }
});
</script>

<template>
    <CustomerLayout :navbar-image="restaurant.navbar_image" :logo="restaurant.logo">
        <template #title>
            <h1 class="text-lg font-bold">{{ restaurant.name }}</h1>
            <p class="text-sm text-stone-500">{{ table.name }}</p>
        </template>

        <div
            v-if="errorMessage"
            class="mb-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700"
        >
            {{ errorMessage }}
        </div>

        <div class="mb-4 flex gap-2 overflow-x-auto pb-1">
            <button
                v-for="category in categories"
                :key="category.id"
                type="button"
                class="whitespace-nowrap rounded-full px-4 py-2 text-sm font-medium transition"
                :class="
                    activeCategory === category.id
                        ? 'bg-amber-600 text-white'
                        : 'bg-white text-stone-600 ring-1 ring-stone-200'
                "
                @click="activeCategory = category.id"
            >
                {{ category.name }}
            </button>
        </div>

        <div class="space-y-3">
            <article
                v-for="item in visibleItems"
                :key="item.id"
                class="flex items-center gap-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-stone-100"
            >
                <div class="flex-1">
                    <h3 class="font-semibold">{{ item.name }}</h3>
                    <p v-if="item.description" class="mt-1 text-sm text-stone-500">
                        {{ item.description }}
                    </p>
                    <p class="mt-2 font-bold text-amber-700">{{ formatPrice(item.price) }}</p>
                </div>
                <button
                    type="button"
                    class="rounded-xl bg-amber-600 px-4 py-3 text-sm font-medium text-white"
                    @click="openAddModal(item)"
                >
                    أضف
                </button>
            </article>
        </div>

        <!-- Bottom cart bar -->
        <div
            v-if="cartCount > 0"
            class="fixed inset-x-0 bottom-0 z-30 mx-auto max-w-lg px-4 pb-4"
        >
            <button
                type="button"
                class="flex w-full items-center justify-between rounded-2xl bg-stone-900 px-5 py-4 text-white shadow-lg"
                @click="cartOpen = true"
            >
                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-amber-600 text-sm font-bold">
                    {{ cartCount }}
                </span>
                <span class="font-bold">عرض السلة</span>
                <span class="font-bold">{{ cartSubtotal }} {{ restaurant.currency }}</span>
            </button>
        </div>

        <AddItemModal
            :item="selectedItem"
            :currency="restaurant.currency"
            :loading="adding"
            @close="selectedItem = null"
            @confirm="confirmAdd"
        />

        <CartDrawer
            ref="cartDrawerRef"
            v-model:open="cartOpen"
            :currency="restaurant.currency"
            @checkout="openCheckout"
        />

        <CheckoutModal
            v-model:customer-note="customerNote"
            :open="checkoutOpen"
            :subtotal="cartSubtotal"
            :currency="restaurant.currency"
            :item-count="cartCount"
            :loading="submitting"
            @close="checkoutOpen = false"
            @confirm="submitOrder"
        />
    </CustomerLayout>
</template>
