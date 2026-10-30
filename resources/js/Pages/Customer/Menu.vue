<script setup lang="ts">
import AddItemModal from '@/Components/Customer/AddItemModal.vue';
import AiWaiterModal from '@/Components/Customer/AiWaiterModal.vue';
import CartDrawer from '@/Components/Customer/CartDrawer.vue';
import CheckoutModal from '@/Components/Customer/CheckoutModal.vue';
import CustomerLayout from '@/Layouts/CustomerLayout.vue';
import { router } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
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
    restaurant: {
        name: string;
        currency: string;
        type?: string;
        windows_count?: number;
        navbar_image?: string | null;
        logo?: string | null;
        features?: Record<string, any>;
    };
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
const serviceType = ref<'dine_in' | 'takeaway'>('dine_in');
const promoCode = ref('');
const cartSubtotal = ref('0.00');
const cartDrawerRef = ref<InstanceType<typeof CartDrawer> | null>(null);
const errorMessage = ref('');

// خريطة لتتبع الأصناف المضافة في السلة وكمياتها لعرضها مباشرة على البطاقة
const cartItemsByMenuItemId = ref<Record<number, { id: number; quantity: number; note: string | null }>>({});
const updatingItemIds = ref<Set<number>>(new Set());

// حالات التأثيرات الحركية والتنبيه العائم السلس
const isCartBumping = ref(false);
const toastInfo = ref<{ show: boolean; name: string } | null>(null);
let toastTimeout: any = null;

const visibleItems = computed(() => {
    const cat = props.categories.find((c) => c.id === activeCategory.value);
    return cat?.active_items ?? [];
});

async function refreshCartSummary() {
    try {
        const { data } = await axios.get('/api/public/cart');
        cartCount.value = data.data?.count ?? 0;
        cartSubtotal.value = data.data?.subtotal ?? '0.00';

        const map: Record<number, { id: number; quantity: number; note: string | null }> = {};
        (data.data?.items ?? []).forEach((ci: any) => {
            map[ci.menu_item_id] = {
                id: ci.id,
                quantity: ci.quantity,
                note: ci.note,
            };
        });
        cartItemsByMenuItemId.value = map;
        cartDrawerRef.value?.load();
    } catch {
        cartCount.value = 0;
        cartItemsByMenuItemId.value = {};
    }
}

function triggerCartAnimation() {
    isCartBumping.value = true;
    setTimeout(() => {
        isCartBumping.value = false;
    }, 450);
}

function showAddFeedback(itemName: string) {
    triggerCartAnimation();
    if (toastTimeout) clearTimeout(toastTimeout);
    toastInfo.value = { show: true, name: itemName };
    toastTimeout = setTimeout(() => {
        if (toastInfo.value) {
            toastInfo.value.show = false;
        }
    }, 2400);
}

function openAddModal(item: MenuItem) {
    selectedItem.value = item;
}

// تأكيد الإضافة من نافذة تفاصيل الصنف والملاحظات
async function confirmAdd(payload: { quantity: number; note: string | null }) {
    if (!selectedItem.value) return;
    const item = selectedItem.value;
    adding.value = true;
    errorMessage.value = '';
    try {
        await axios.post('/api/public/cart/items', {
            menu_item_id: item.id,
            quantity: payload.quantity,
            note: payload.note,
        });
        selectedItem.value = null;
        await refreshCartSummary();
        showAddFeedback(item.name);
        // تجربة مستخدم عصرية: نبقي السلة عائمة دون حجب الشاشة بالنافذة الكبيرة
    } catch (e: any) {
        errorMessage.value = e.response?.data?.message || 'تعذر إضافة الصنف. قد يكون غير متاح.';
    } finally {
        adding.value = false;
    }
}

// إضافة سريعة بلمسة واحدة من بطاقة الصنف
async function quickAdd(item: MenuItem) {
    if (updatingItemIds.value.has(item.id)) return;
    updatingItemIds.value.add(item.id);
    errorMessage.value = '';
    try {
        await axios.post('/api/public/cart/items', {
            menu_item_id: item.id,
            quantity: 1,
        });
        await refreshCartSummary();
        showAddFeedback(item.name);
    } catch (e: any) {
        errorMessage.value = e.response?.data?.message || 'تعذر إضافة الصنف.';
    } finally {
        updatingItemIds.value.delete(item.id);
    }
}

// زيادة سريعة للكمية
async function quickIncrement(item: MenuItem) {
    const existing = cartItemsByMenuItemId.value[item.id];
    if (!existing) {
        return quickAdd(item);
    }
    if (updatingItemIds.value.has(item.id)) return;
    updatingItemIds.value.add(item.id);
    errorMessage.value = '';
    try {
        await axios.patch(`/api/public/cart/items/${existing.id}`, {
            quantity: existing.quantity + 1,
            note: existing.note,
        });
        await refreshCartSummary();
        triggerCartAnimation();
    } catch (e: any) {
        errorMessage.value = e.response?.data?.message || 'تعذر زيادة الكمية.';
    } finally {
        updatingItemIds.value.delete(item.id);
    }
}

// تقليل سريع للكمية
async function quickDecrement(item: MenuItem) {
    const existing = cartItemsByMenuItemId.value[item.id];
    if (!existing) return;
    if (updatingItemIds.value.has(item.id)) return;
    updatingItemIds.value.add(item.id);
    errorMessage.value = '';
    try {
        const newQty = existing.quantity - 1;
        if (newQty <= 0) {
            await axios.delete(`/api/public/cart/items/${existing.id}`);
        } else {
            await axios.patch(`/api/public/cart/items/${existing.id}`, {
                quantity: newQty,
                note: existing.note,
            });
        }
        await refreshCartSummary();
        triggerCartAnimation();
    } catch (e: any) {
        errorMessage.value = e.response?.data?.message || 'تعذر تقليل الكمية.';
    } finally {
        updatingItemIds.value.delete(item.id);
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
            service_type: serviceType.value,
            promo_code: promoCode.value || null,
        });
        checkoutOpen.value = false;
        customerNote.value = '';
        promoCode.value = '';
        router.visit(`/t/${props.table.public_token}/orders/${data.data.order_number}`);
    } catch (e: any) {
        errorMessage.value = e.response?.data?.message || 'تعذر إرسال الطلب. تحقق من السلة وحاول مرة أخرى.';
        checkoutOpen.value = false;
        cartOpen.value = true;
    } finally {
        submitting.value = false;
    }
}

function formatPrice(price: string) {
    return `${price} ${props.restaurant.currency}`;
}

watch(cartOpen, (isOpen) => {
    if (!isOpen) {
        refreshCartSummary();
    }
});

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
            <h1 class="text-lg font-bold text-stone-900 dark:text-white">{{ restaurant.name }}</h1>
            <p class="text-sm text-stone-500 dark:text-slate-400">{{ table.name }}</p>
        </template>

        <!-- زر السلة العلوي في شريط التنقل -->
        <template #actions>
            <button
                v-if="cartCount > 0"
                type="button"
                class="relative flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-700 hover:bg-amber-100 dark:bg-amber-950/40 dark:text-amber-300 dark:hover:bg-amber-900/60 transition-colors border border-amber-200/80 dark:border-amber-700/60 active:scale-95 shadow-2xs"
                title="عرض السلة"
                @click="cartOpen = true"
            >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                </svg>
                <span class="absolute -top-1 -right-1 flex h-4 min-w-[16px] items-center justify-center rounded-full bg-amber-600 px-1 text-[10px] font-black text-white shadow">
                    {{ cartCount }}
                </span>
            </button>
        </template>

        <!-- خطأ -->
        <div v-if="errorMessage" class="mb-4 rounded-2xl bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-800/60 px-4 py-3 text-sm text-red-700 dark:text-red-300 flex items-center gap-2 shadow-2xs">
            <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
            {{ errorMessage }}
        </div>

        <!-- طلبات نشطة -->
        <div v-if="activeOrders && activeOrders.length > 0" class="mb-4 space-y-2">
            <button
                v-for="order in activeOrders"
                :key="order.id"
                class="w-full flex items-center justify-between rounded-2xl bg-gradient-to-l from-amber-500 to-orange-500 dark:from-amber-600 dark:to-amber-700 p-4 shadow-lg shadow-amber-200 dark:shadow-amber-950/40 text-white transition-all active:scale-95"
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
        <div class="sticky top-0 z-10 -mx-4 bg-stone-50/95 dark:bg-[#060913]/95 backdrop-blur-md px-4 pt-2 pb-3 mb-4 border-b border-stone-200/60 dark:border-slate-800/80 transition-colors">
            <div class="flex gap-2 overflow-x-auto pb-1 scrollbar-hide">
                <button
                    v-for="category in categories"
                    :key="category.id"
                    type="button"
                    class="whitespace-nowrap rounded-full px-4 py-2 text-sm font-semibold transition-all duration-200 shrink-0"
                    :class="activeCategory === category.id
                        ? 'bg-amber-600 text-white shadow-md shadow-amber-200 dark:shadow-amber-900/50'
                        : 'bg-white text-stone-600 ring-1 ring-stone-200 dark:bg-slate-900 dark:text-slate-300 dark:ring-slate-800 hover:dark:bg-slate-800'"
                    @click="activeCategory = category.id"
                >
                    {{ category.name }}
                </button>
            </div>
        </div>

        <!-- قائمة الأصناف -->
        <div class="grid grid-cols-1 gap-3 pb-32">
            <article
                v-for="item in visibleItems"
                :key="item.id"
                class="group flex gap-4 rounded-2xl bg-white dark:bg-[#0a0f1d] p-3.5 shadow-2xs ring-1 ring-stone-100/90 dark:ring-slate-800/80 hover:shadow-md hover:dark:ring-amber-500/40 transition-all duration-200 cursor-pointer"
                @click="openAddModal(item)"
            >
                <!-- صورة الصنف -->
                <div class="shrink-0 relative">
                    <div v-if="item.image" class="h-24 w-24 rounded-xl overflow-hidden shadow-2xs border border-stone-100 dark:border-slate-800">
                        <img
                            :src="item.image"
                            :alt="item.name"
                            class="h-full w-full object-cover group-hover:scale-105 transition-transform duration-300"
                        />
                    </div>
                    <div v-else class="h-24 w-24 rounded-xl bg-gradient-to-br from-amber-50 to-stone-100 dark:from-slate-900 dark:to-slate-800 border border-stone-100 dark:border-slate-800 flex items-center justify-center">
                        <svg class="w-10 h-10 text-amber-300 dark:text-amber-500/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                        </svg>
                    </div>

                    <!-- شارة الكمية في زاوية الصورة إن كانت مضافة -->
                    <span
                        v-if="cartItemsByMenuItemId[item.id]"
                        class="absolute -top-1.5 -right-1.5 flex h-5 min-w-[20px] items-center justify-center rounded-full bg-amber-500 px-1 text-[11px] font-black text-stone-950 shadow-md ring-2 ring-white dark:ring-slate-900"
                    >
                        {{ cartItemsByMenuItemId[item.id].quantity }}
                    </span>
                </div>

                <!-- تفاصيل الصنف -->
                <div class="flex-1 flex flex-col justify-between min-w-0">
                    <div>
                        <h3 class="font-bold text-gray-900 dark:text-white text-base leading-tight group-hover:text-amber-700 dark:group-hover:text-amber-400 transition-colors">{{ item.name }}</h3>
                        <p v-if="item.description" class="mt-1 text-xs text-stone-500 dark:text-slate-400 line-clamp-2 leading-relaxed">
                            {{ item.description }}
                        </p>
                    </div>

                    <div class="flex items-center justify-between mt-2 pt-1 border-t border-stone-50 dark:border-slate-800/60">
                        <p class="font-black text-amber-600 dark:text-amber-400 text-sm sm:text-base">{{ formatPrice(item.price) }}</p>

                        <!-- أزرار الإضافة السريعة أو العداد المباشر في البطاقة -->
                        <div @click.stop>
                            <!-- إذا كان الصنف مضافاً في السلة: يظهر عداد فوري [ - ] [ quantity ] [ + ] -->
                            <div
                                v-if="cartItemsByMenuItemId[item.id]"
                                class="flex items-center gap-1.5 bg-amber-50 dark:bg-amber-950/40 border border-amber-200/90 dark:border-amber-700/60 rounded-full p-0.5 shadow-2xs"
                            >
                                <button
                                    type="button"
                                    class="h-7 w-7 rounded-full bg-white dark:bg-slate-800 text-amber-800 dark:text-amber-300 font-bold flex items-center justify-center hover:bg-amber-100 dark:hover:bg-slate-700 active:scale-90 transition-all shadow-2xs disabled:opacity-50"
                                    :disabled="updatingItemIds.has(item.id)"
                                    title="تقليل"
                                    @click="quickDecrement(item)"
                                >
                                    −
                                </button>
                                <span class="text-xs font-black text-amber-900 dark:text-amber-200 min-w-[16px] text-center">
                                    {{ cartItemsByMenuItemId[item.id].quantity }}
                                </span>
                                <button
                                    type="button"
                                    class="h-7 w-7 rounded-full bg-amber-600 dark:bg-amber-500 text-white font-bold flex items-center justify-center hover:bg-amber-700 dark:hover:bg-amber-400 active:scale-90 transition-all shadow-2xs disabled:opacity-50"
                                    :disabled="updatingItemIds.has(item.id)"
                                    title="زيادة"
                                    @click="quickIncrement(item)"
                                >
                                    +
                                </button>
                            </div>

                            <!-- إذا لم يكن في السلة: زر إضافة سريع (+) مع الاحتفاظ بإمكانية الضغط على البطاقة للملاحظات -->
                            <button
                                v-else
                                type="button"
                                class="h-8 w-8 rounded-full bg-amber-600 dark:bg-amber-500 text-white flex items-center justify-center shadow-md shadow-amber-200 dark:shadow-amber-950/50 hover:bg-amber-700 dark:hover:bg-amber-400 active:scale-90 transition-transform disabled:opacity-50"
                                :disabled="updatingItemIds.has(item.id)"
                                title="إضافة سريعة للطلب"
                                @click="quickAdd(item)"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </article>

            <!-- حالة فارغة -->
            <div v-if="visibleItems.length === 0" class="col-span-full py-16 text-center text-stone-400 dark:text-slate-500">
                <svg class="w-16 h-16 mx-auto mb-3 text-stone-200 dark:text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="font-medium">لا توجد أصناف في هذا القسم حالياً</p>
            </div>
        </div>

        <!-- تنبيه عائم سريع وأنيق عند الإضافة لا يحجب الأصناف -->
        <Transition name="toast-slide">
            <div
                v-if="toastInfo && toastInfo.show"
                class="fixed bottom-24 inset-x-0 mx-auto max-w-sm z-40 px-4 pointer-events-none"
            >
                <div class="flex items-center justify-between gap-3 bg-stone-900/95 text-white backdrop-blur-md px-4 py-3 rounded-2xl shadow-2xl border border-stone-700/60 pointer-events-auto">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-500 text-white text-xs font-bold shadow-sm">
                            ✓
                        </span>
                        <p class="text-xs sm:text-sm font-medium truncate">
                            تمت إضافة <strong class="text-amber-400 font-bold">«{{ toastInfo.name }}»</strong> للسلة
                        </p>
                    </div>
                    <button
                        type="button"
                        class="text-xs font-bold text-amber-400 hover:text-amber-300 underline shrink-0 px-1 py-0.5"
                        @click="cartOpen = true"
                    >
                        عرض السلة
                    </button>
                </div>
            </div>
        </Transition>

        <!-- سلة عائمة عصرية وأنيقة تظهر بالأسفل دون حجب الشاشة -->
        <Transition name="cart-bar">
            <div
                v-if="cartCount > 0"
                class="fixed inset-x-0 bottom-3 z-30 mx-auto max-w-lg px-4"
            >
                <button
                    type="button"
                    class="group relative flex w-full items-center justify-between rounded-2xl bg-gradient-to-r from-stone-900 via-stone-850 to-stone-900 px-4 py-3 text-white shadow-2xl shadow-stone-900/40 border border-stone-700/50 active:scale-[0.98] transition-all duration-300"
                    :class="{ 'ring-2 ring-amber-500/80 scale-[1.02] shadow-amber-500/20': isCartBumping }"
                    @click="cartOpen = true"
                >
                    <!-- الأيقونة والعدد المختار المنبثق منها -->
                    <div class="flex items-center gap-3">
                        <div class="relative flex h-11 w-11 items-center justify-center rounded-xl bg-amber-500/15 border border-amber-500/30 text-amber-400">
                            <!-- أيقونة حقيبة التسوق / السلة -->
                            <svg class="h-6 w-6 transition-transform group-hover:scale-110" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                            </svg>
                            <!-- شارة العدد المنبثقة من السلة -->
                            <span
                                :key="cartCount"
                                class="absolute -top-1.5 -right-1.5 flex h-5 min-w-[20px] items-center justify-center rounded-full bg-amber-500 px-1 text-[11px] font-black text-stone-950 shadow-md animate-bounce-short"
                            >
                                {{ cartCount }}
                            </span>
                        </div>
                        <div class="text-right">
                            <p class="font-bold text-sm sm:text-base leading-tight flex items-center gap-1.5">
                                <span>عرض السلة</span>
                                <span class="text-xs font-semibold text-stone-400">({{ cartCount }} {{ cartCount === 1 ? 'صنف' : 'أصناف' }})</span>
                            </p>
                            <p class="text-[11px] text-amber-400/90 font-medium">اضغط للمراجعة وتأكيد الطلب</p>
                        </div>
                    </div>

                    <!-- السعر الإجمالي وسهم التوسيع -->
                    <div class="flex items-center gap-2 bg-white/10 px-3.5 py-2 rounded-xl border border-white/10">
                        <span class="font-black text-sm sm:text-base text-amber-400" dir="ltr">
                            {{ cartSubtotal }} {{ restaurant.currency }}
                        </span>
                        <svg class="h-4 w-4 text-stone-300 transition-transform group-hover:-translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                        </svg>
                    </div>
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
            @updated="refreshCartSummary"
            @checkout="openCheckout"
        />

        <CheckoutModal
            v-model:customer-note="customerNote"
            v-model:service-type="serviceType"
            v-model:promo-code="promoCode"
            :open="checkoutOpen"
            :subtotal="cartSubtotal"
            :currency="restaurant.currency"
            :item-count="cartCount"
            :loading="submitting"
            :is-prepaid="restaurant.type === 'prepaid'"
            @close="checkoutOpen = false"
            @confirm="submitOrder"
        />

        <AiWaiterModal
            v-if="restaurant.features?.ai_waiter"
            :restaurant-name="restaurant.name"
            :currency="restaurant.currency"
            @item-added="refreshCartSummary"
        />
    </CustomerLayout>
</template>

<style scoped>
.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }

@keyframes bounce-short {
    0%, 100% {
        transform: translateY(0) scale(1);
    }
    50% {
        transform: translateY(-4px) scale(1.2);
    }
}
.animate-bounce-short {
    animation: bounce-short 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.toast-slide-enter-active, .toast-slide-leave-active {
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.toast-slide-enter-from, .toast-slide-leave-to {
    opacity: 0;
    transform: translateY(15px) scale(0.95);
}

.cart-bar-enter-active, .cart-bar-leave-active {
    transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.cart-bar-enter-from, .cart-bar-leave-to {
    opacity: 0;
    transform: translateY(100%) scale(0.95);
}
</style>
