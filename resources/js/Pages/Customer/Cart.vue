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
const promoCode = ref('');
const appliedPromoCode = ref('');
const discountAmount = ref('0.00');
const validatingPromo = ref(false);
const promoError = ref('');

async function loadCart() {
    loading.value = true;
    try {
        const { data } = await axios.get('/api/public/cart');
        items.value = data.data.items ?? [];
        subtotal.value = data.data.subtotal ?? '0.00';
        if (appliedPromoCode.value) {
            await validatePromoCode(); // Re-validate if subtotal changes
        }
    } finally {
        loading.value = false;
    }
}

async function validatePromoCode() {
    if (!promoCode.value) return;
    validatingPromo.value = true;
    promoError.value = '';
    
    try {
        const { data } = await axios.post('/api/public/promo-codes/validate', {
            code: promoCode.value
        });
        
        appliedPromoCode.value = data.data.code;
        discountAmount.value = data.data.discount_amount.toString();
    } catch (e: any) {
        promoError.value = e.response?.data?.message || 'الكوبون غير صالح';
        appliedPromoCode.value = '';
        discountAmount.value = '0.00';
    } finally {
        validatingPromo.value = false;
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
            promo_code: appliedPromoCode.value || null,
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

            <div class="rounded-2xl bg-white p-4">
                <label class="mb-2 block text-sm font-semibold text-stone-700">كود الخصم</label>
                <div class="flex gap-2">
                    <input
                        v-model="promoCode"
                        type="text"
                        placeholder="أدخل الكود هنا"
                        class="w-full rounded-xl border-stone-200 text-sm uppercase text-left"
                        dir="ltr"
                        :disabled="!!appliedPromoCode"
                    />
                    <button
                        v-if="!appliedPromoCode"
                        @click="validatePromoCode"
                        :disabled="!promoCode || validatingPromo"
                        class="shrink-0 rounded-xl bg-stone-800 px-4 text-sm font-medium text-white hover:bg-stone-700 disabled:opacity-50"
                    >
                        {{ validatingPromo ? '...' : 'تطبيق' }}
                    </button>
                    <button
                        v-else
                        @click="appliedPromoCode = ''; discountAmount = '0.00'; promoCode = ''"
                        class="shrink-0 rounded-xl bg-red-100 text-red-600 px-4 text-sm font-medium hover:bg-red-200"
                    >
                        إلغاء
                    </button>
                </div>
                <p v-if="promoError" class="mt-2 text-xs text-red-600">{{ promoError }}</p>
                <p v-if="appliedPromoCode" class="mt-2 text-xs text-green-600">تم تطبيق الخصم بنجاح!</p>
            </div>

            <div class="rounded-2xl bg-amber-50 p-4 space-y-2">
                <div class="flex justify-between text-sm text-stone-600" v-if="appliedPromoCode">
                    <span>المجموع الفرعي</span>
                    <span>{{ subtotal }} {{ restaurant.currency }}</span>
                </div>
                <div class="flex justify-between text-sm text-green-600" v-if="appliedPromoCode">
                    <span>الخصم</span>
                    <span>- {{ discountAmount }} {{ restaurant.currency }}</span>
                </div>
                <div class="flex justify-between text-lg font-bold border-t border-amber-200/50 pt-2" :class="{'mt-2': appliedPromoCode}">
                    <span>الإجمالي</span>
                    <span>{{ Math.max(0, parseFloat(subtotal) - parseFloat(discountAmount)).toFixed(2) }} {{ restaurant.currency }}</span>
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
