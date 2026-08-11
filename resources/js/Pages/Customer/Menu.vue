<script setup lang="ts">
import CustomerLayout from '@/Layouts/CustomerLayout.vue';
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
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
    restaurant: { name: string; currency: string };
    table: { name: string; public_token: string };
    categories: Category[];
}>();

const activeCategory = ref(props.categories[0]?.id ?? 0);
const cartCount = ref(0);
const adding = ref<number | null>(null);

const visibleItems = computed(() => {
    const cat = props.categories.find((c) => c.id === activeCategory.value);
    return cat?.active_items ?? [];
});

async function refreshCartCount() {
    try {
        const { data } = await axios.get('/api/public/cart');
        cartCount.value = data.data?.count ?? 0;
    } catch {
        cartCount.value = 0;
    }
}

async function addToCart(item: MenuItem) {
    adding.value = item.id;
    try {
        await axios.post('/api/public/cart/items', {
            menu_item_id: item.id,
            quantity: 1,
        });
        await refreshCartCount();
    } finally {
        adding.value = null;
    }
}

function formatPrice(price: string) {
    return `${price} ${props.restaurant.currency}`;
}

refreshCartCount();
</script>

<template>
    <CustomerLayout>
        <template #title>
            <h1 class="text-lg font-bold">{{ restaurant.name }}</h1>
            <p class="text-sm text-stone-500">{{ table.name }}</p>
        </template>
        <template #actions>
            <Link
                :href="`/t/${table.public_token}/cart`"
                class="relative rounded-full bg-amber-600 px-4 py-2 text-sm font-medium text-white"
            >
                السلة
                <span
                    v-if="cartCount > 0"
                    class="absolute -left-2 -top-2 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-xs"
                >
                    {{ cartCount }}
                </span>
            </Link>
        </template>

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
                    class="rounded-xl bg-amber-600 px-4 py-3 text-sm font-medium text-white disabled:opacity-50"
                    :disabled="adding === item.id"
                    @click="addToCart(item)"
                >
                    {{ adding === item.id ? '...' : 'أضف' }}
                </button>
            </article>
        </div>
    </CustomerLayout>
</template>
