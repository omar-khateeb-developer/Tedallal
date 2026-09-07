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
    activeOrders?: {
        id: number;
        order_number: string;
        status: string;
        total: string;
    }[];
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

        <!-- خطأ -->
        <div v-if="errorMessage" class="mb-4 rounded-2xl bg-red-50 px-4 py-3 text-sm text-red-700 flex items-center gap-2">
            <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
            {{ errorMessage }}
        </div>

        <!-- طلبات نشطة -->
        <div v-if="activeOrders && activeOrders.length > 0" class="mb-4 space-y-2">
            <button
                v-for="order in activeOrders"
                :key="order.id"
                class="w-full flex items-center justify-between rounded-2xl bg-gradient-to-l from-amber-500 to-orange-500 p-4 shadow-lg shadow-amber-200 text-white transition-all active:scale-95"
                @click="router.visit(`/t/${props.table.public_token}/orders/${order.order_number}`)"
            >
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 flex items-center justify-center rounded-full bg-white/20 animate-pulse">
                        <svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="text-right">
                        <p class="font-bold text-sm">طلب {{ order.order_number }}</p>
                        <p class="text-xs text-white/80">اضغط لمتابعة حالة طلبك</p>
                    </div>
                </div>
                <svg class="h-5 w-5 text-white/70 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </button>
        </div>

        <!-- تبويبات الأصناف - تثبت عند التمرير -->
        <div class="sticky top-0 z-10 -mx-4 bg-stone-50/95 backdrop-blur-sm px-4 pt-2 pb-3 mb-4 border-b border-stone-100">
            <div class="flex gap-2 overflow-x-auto pb-1 scrollbar-hide">
                <button
                    v-for="category in categories"
                    :key="category.id"
                    type="button"
                    class="whitespace-nowrap rounded-full px-4 py-2 text-sm font-semibold transition-all duration-200 shrink-0"
                    :class="activeCategory === category.id
                        ? 'bg-amber-600 text-white shadow-md shadow-amber-200'
                        : 'bg-white text-stone-600 ring-1 ring-stone-200'"
                    @click="activeCategory = category.id"
                >
                    {{ category.name }}
                </button>
            </div>
        </div>

        <!-- قائمة الأصناف -->
        <div class="grid grid-cols-1 gap-3 pb-28">
            <article
                v-for="item in visibleItems"
                :key="item.id"
                class="group flex gap-4 rounded-2xl bg-white p-3 shadow-sm ring-1 ring-stone-100 active:scale-[0.98] transition-all duration-150 cursor-pointer"
                @click="openAddModal(item)"
            >
                <!-- صورة الصنف -->
                <div class="shrink-0">
                    <div v-if="item.image" class="h-24 w-24 rounded-xl overflow-hidden shadow-sm">
                        <img
                            :src="item.image"
                            :alt="item.name"
                            class="h-full w-full object-cover group-active:scale-110 transition-transform duration-300"
                        />
                    </div>
                    <div v-else class="h-24 w-24 rounded-xl bg-gradient-to-br from-amber-50 to-stone-100 flex items-center justify-center">
                        <svg class="w-10 h-10 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                        </svg>
                    </div>
                </div>

                <!-- تفاصيل الصنف -->
                <div class="flex-1 flex flex-col justify-between min-w-0">
                    <div>
                        <h3 class="font-bold text-gray-900 text-base leading-tight">{{ item.name }}</h3>
                        <p v-if="item.description" class="mt-1 text-sm text-stone-500 line-clamp-2 leading-relaxed">
                            {{ item.description }}
                        </p>
                    </div>
                    <div class="flex items-center justify-between mt-2">
                        <p class="font-black text-amber-600 text-base">{{ formatPrice(item.price) }}</p>
                        <div class="h-8 w-8 rounded-full bg-amber-600 flex items-center justify-center shadow-md shadow-amber-200 group-active:scale-90 transition-transform">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </article>

            <!-- حالة فارغة -->
            <div v-if="visibleItems.length === 0" class="col-span-full py-16 text-center text-stone-400">
                <svg class="w-16 h-16 mx-auto mb-3 text-stone-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="font-medium">لا توجد أصناف في هذا القسم حالياً</p>
            </div>
        </div>

        <!-- شريط السلة العائم -->
        <Transition name="cart-bar">
            <div v-if="cartCount > 0" class="fixed inset-x-0 bottom-0 z-30 mx-auto max-w-lg px-4 pb-5">
                <button
                    type="button"
                    class="flex w-full items-center justify-between rounded-2xl bg-stone-900 px-5 py-4 text-white shadow-2xl shadow-stone-900/30 active:scale-95 transition-transform"
                    @click="cartOpen = true"
                >
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-amber-500 text-sm font-black shadow">
                        {{ cartCount }}
                    </span>
                    <span class="font-bold text-base">عرض السلة</span>
                    <span class="font-bold text-amber-400">{{ cartSubtotal }} {{ restaurant.currency }}</span>
                </button>
            </div>
        </Transition>

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

<style scoped>
.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }

.cart-bar-enter-active, .cart-bar-leave-active {
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.cart-bar-enter-from, .cart-bar-leave-to {
    opacity: 0;
    transform: translateY(100%);
}
</style>

