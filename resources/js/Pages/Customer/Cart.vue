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
                class="text-sm font-semibold text-amber-700 dark:text-amber-400 hover:underline"
            >
                العودة للقائمة
            </Link>
        </template>

        <div v-if="loading" class="py-16 text-center text-stone-500 dark:text-slate-400">جاري التحميل...</div>

        <div v-else-if="items.length === 0" class="py-16 text-center">
            <p class="text-stone-500 dark:text-slate-400">السلة فارغة</p>
            <Link
                :href="`/t/${publicToken}`"
                class="mt-4 inline-block rounded-xl bg-amber-600 dark:bg-amber-500 hover:bg-amber-700 dark:hover:bg-amber-400 px-6 py-3 text-white font-bold shadow-md shadow-amber-200 dark:shadow-amber-950/50"
            >
                تصفح القائمة
            </Link>
        </div>

        <div v-else class="space-y-4">
            <article
                v-for="item in items"
                :key="item.id"
                class="rounded-2xl bg-white dark:bg-[#0a0f1d] p-4 shadow-xs border border-stone-100 dark:border-slate-800 transition-colors"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="font-bold text-stone-900 dark:text-white">{{ item.menu_item.name }}</h3>
                        <p class="text-sm font-black text-amber-700 dark:text-amber-400 mt-0.5">
                            {{ item.menu_item.price }} {{ restaurant.currency }}
                        </p>
                    </div>
                    <div class="flex items-center gap-1.5 bg-stone-50 dark:bg-slate-800/80 border border-stone-200 dark:border-slate-700 rounded-full p-0.5 shadow-2xs">
                        <button
                            type="button"
                            class="h-8 w-8 rounded-full bg-white dark:bg-slate-700 text-stone-800 dark:text-slate-100 font-bold flex items-center justify-center hover:bg-stone-200 dark:hover:bg-slate-600 active:scale-90 transition-all text-sm"
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
            </article>

            <div class="rounded-2xl bg-white dark:bg-[#0a0f1d] p-4 border border-stone-100 dark:border-slate-800 transition-colors">
                <label class="mb-2 block text-sm font-medium text-stone-600 dark:text-slate-300">ملاحظات الطلب</label>
                <textarea
                    v-model="customerNote"
                    rows="2"
                    class="w-full rounded-xl border-stone-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-stone-900 dark:text-white placeholder:text-stone-400 dark:placeholder:text-slate-500 text-sm focus:border-amber-500 focus:ring-amber-500"
                    placeholder="اختياري..."
                />
            </div>

            <div class="rounded-2xl bg-white dark:bg-[#0a0f1d] p-4 border border-stone-100 dark:border-slate-800 transition-colors">
                <label class="mb-2 block text-sm font-semibold text-stone-700 dark:text-slate-300">كود الخصم</label>
                <div class="flex gap-2">
                    <input
                        v-model="promoCode"
                        type="text"
                        placeholder="أدخل الكود هنا"
                        class="w-full rounded-xl border-stone-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-stone-900 dark:text-white placeholder:text-stone-400 dark:placeholder:text-slate-500 text-sm uppercase text-left"
                        dir="ltr"
                        :disabled="!!appliedPromoCode"
                    />
                    <button
                        v-if="!appliedPromoCode"
                        @click="validatePromoCode"
                        :disabled="!promoCode || validatingPromo"
                        class="shrink-0 rounded-xl bg-stone-800 dark:bg-slate-800 px-4 text-sm font-bold text-white hover:bg-stone-700 dark:hover:bg-slate-700 disabled:opacity-50"
                    >
                        {{ validatingPromo ? '...' : 'تطبيق' }}
                    </button>
                    <button
                        v-else
                        @click="appliedPromoCode = ''; discountAmount = '0.00'; promoCode = ''"
                        class="shrink-0 rounded-xl bg-red-100 dark:bg-red-950/60 text-red-600 dark:text-red-400 px-4 text-sm font-bold hover:bg-red-200"
                    >
                        إلغاء
                    </button>
                </div>
                <p v-if="promoError" class="mt-2 text-xs text-red-600 dark:text-red-400 font-medium">{{ promoError }}</p>
                <p v-if="appliedPromoCode" class="mt-2 text-xs text-green-600 dark:text-green-400 font-medium">تم تطبيق الخصم بنجاح!</p>
            </div>

            <div class="rounded-2xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200/80 dark:border-amber-800/60 p-4 space-y-2 transition-colors">
                <div class="flex justify-between text-sm text-stone-600 dark:text-slate-300" v-if="appliedPromoCode">
                    <span>المجموع الفرعي</span>
                    <span>{{ subtotal }} {{ restaurant.currency }}</span>
                </div>
                <div class="flex justify-between text-sm text-green-600 dark:text-green-400" v-if="appliedPromoCode">
                    <span>الخصم</span>
                    <span>- {{ discountAmount }} {{ restaurant.currency }}</span>
                </div>
                <div class="flex justify-between text-lg font-bold border-t border-amber-200/50 dark:border-amber-800/50 pt-2 text-stone-900 dark:text-white" :class="{'mt-2': appliedPromoCode}">
                    <span>الإجمالي</span>
                    <span class="text-amber-600 dark:text-amber-400 font-black">{{ Math.max(0, parseFloat(subtotal) - parseFloat(discountAmount)).toFixed(2) }} {{ restaurant.currency }}</span>
                </div>
            </div>

            <button
                type="button"
                class="w-full rounded-2xl bg-amber-600 dark:bg-amber-500 hover:bg-amber-700 dark:hover:bg-amber-400 py-4 text-lg font-bold text-white shadow-lg shadow-amber-200 dark:shadow-amber-950/50 active:scale-[0.98] transition-all disabled:opacity-50"
                :disabled="submitting"
                @click="submitOrder"
            >
                {{ submitting ? 'جاري الإرسال...' : 'تأكيد الطلب ⚡' }}
            </button>
        </div>
    </CustomerLayout>
</template>
