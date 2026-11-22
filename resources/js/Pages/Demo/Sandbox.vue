<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';

interface MenuItem {
    id: number;
    name: string;
    category: string;
    price: number;
    description: string;
    emoji: string;
}

interface CartItem extends MenuItem {
    quantity: number;
}

interface SimulatedOrder {
    id: number;
    orderNumber: number;
    tableNumber: number;
    items: { name: string; quantity: number; price: number }[];
    total: number;
    status: 'pending' | 'preparing' | 'ready';
    secondsAgo: number;
    timerInterval?: any;
}

const menuItems: MenuItem[] = [
    {
        id: 1,
        name: 'شاورما عربي دبل إكسترا',
        category: 'الأطباق الرئيسية',
        price: 38000,
        description: 'شاورما دجاج مميزة مع صوص الثومية، بطاطا مقرمشة، ومخلل خيار',
        emoji: '🌯',
    },
    {
        id: 2,
        name: 'برغر لحم أنجوس كلاسيك',
        category: 'الأطباق الرئيسية',
        price: 45000,
        description: 'شريحة لحم مشوية مع جبنة الشيدر وصلصة تَدلّل الخاصة في خبز البريوش',
        emoji: '🍔',
    },
    {
        id: 3,
        name: 'بطاطا ودجز مقرمشة بالأعشاب',
        category: 'المقبلات',
        price: 15000,
        description: 'أصابع بطاطا ودجز متبلة ببهارات الأعشاب وتقدم مع صوص الكوكتيل',
        emoji: '🍟',
    },
    {
        id: 4,
        name: 'موهيتو توت بري منعش',
        category: 'المشروبات',
        price: 16000,
        description: 'موهيتو مثلج مع التوت البري الطبيعي والنعناع الطازج والليمون',
        emoji: '🍹',
    },
    {
        id: 5,
        name: 'عصير برتقال طبيعي معصور',
        category: 'المشروبات',
        price: 18000,
        description: 'عصير برتقال طبيعي 100% طازج بدون أي إضافات',
        emoji: '🍊',
    },
];

const cart = ref<CartItem[]>([
    { ...menuItems[0], quantity: 1 },
    { ...menuItems[3], quantity: 1 },
]);

const activeTab = ref<'menu' | 'kds'>('menu');
const tableNumber = ref(4);
const orders = ref<SimulatedOrder[]>([
    {
        id: 1,
        orderNumber: 101,
        tableNumber: 2,
        items: [
            { name: 'برغر لحم أنجوس كلاسيك', quantity: 2, price: 45000 },
            { name: 'بطاطا ودجز مقرمشة', quantity: 1, price: 15000 },
        ],
        total: 105000,
        status: 'preparing',
        secondsAgo: 84,
    },
]);

const latestPrintedReceipt = ref<SimulatedOrder | null>(null);
const orderCountSequence = ref(102);
const totalDailySales = ref(210000);

// Web Audio API Chime Synth
const playNotificationSound = () => {
    try {
        const AudioContext = window.AudioContext || (window as any).webkitAudioContext;
        if (!AudioContext) return;
        const ctx = new AudioContext();

        // High crystal chime (Bell 1)
        const osc1 = ctx.createOscillator();
        const gain1 = ctx.createGain();
        osc1.type = 'sine';
        osc1.frequency.setValueAtTime(880, ctx.currentTime); // A5
        osc1.frequency.exponentialRampToValueAtTime(1760, ctx.currentTime + 0.15); // A6
        gain1.gain.setValueAtTime(0.3, ctx.currentTime);
        gain1.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.6);
        osc1.connect(gain1);
        gain1.connect(ctx.destination);
        osc1.start();
        osc1.stop(ctx.currentTime + 0.6);

        // Warm sub-chime (Bell 2)
        const osc2 = ctx.createOscillator();
        const gain2 = ctx.createGain();
        osc2.type = 'triangle';
        osc2.frequency.setValueAtTime(659.25, ctx.currentTime + 0.1); // E5
        gain2.gain.setValueAtTime(0.25, ctx.currentTime + 0.1);
        gain2.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.8);
        osc2.connect(gain2);
        gain2.connect(ctx.destination);
        osc2.start(ctx.currentTime + 0.1);
        osc2.stop(ctx.currentTime + 0.8);
    } catch (e) {
        // Fallback silently if audio restricted
    }
};

const cartTotal = computed(() => {
    return cart.value.reduce((sum, item) => sum + item.price * item.quantity, 0);
});

const addToCart = (item: MenuItem) => {
    const existing = cart.value.find(i => i.id === item.id);
    if (existing) {
        existing.quantity++;
    } else {
        cart.value.push({ ...item, quantity: 1 });
    }
};

const removeFromCart = (itemId: number) => {
    const idx = cart.value.findIndex(i => i.id === itemId);
    if (idx !== -1) {
        if (cart.value[idx].quantity > 1) {
            cart.value[idx].quantity--;
        } else {
            cart.value.splice(idx, 1);
        }
    }
};

const sendOrderToKitchen = () => {
    if (cart.value.length === 0) return;

    const newOrder: SimulatedOrder = {
        id: Date.now(),
        orderNumber: orderCountSequence.value++,
        tableNumber: tableNumber.value,
        items: cart.value.map(i => ({ name: i.name, quantity: i.quantity, price: i.price })),
        total: cartTotal.value,
        status: 'pending',
        secondsAgo: 1,
    };

    orders.value.unshift(newOrder);
    latestPrintedReceipt.value = newOrder;
    totalDailySales.value += newOrder.total;
    playNotificationSound();

    // Auto-clear cart for next simulation
    cart.value = [];

    // Switch to KDS tab automatically on mobile to see result
    if (window.innerWidth < 1024) {
        activeTab.value = 'kds';
    }
};

const updateOrderStatus = (order: SimulatedOrder, newStatus: 'preparing' | 'ready') => {
    order.status = newStatus;
    if (newStatus === 'ready') {
        playNotificationSound();
    }
};

const formatCurrency = (val: number) => {
    return new Intl.NumberFormat('ar-SY').format(val) + ' ل.س';
};

const formatSeconds = (sec: number) => {
    const m = Math.floor(sec / 60);
    const s = sec % 60;
    return `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
};

let ticker: any = null;
onMounted(() => {
    ticker = setInterval(() => {
        orders.value.forEach(o => o.secondsAgo++);
    }, 1000);
});

onUnmounted(() => {
    if (ticker) clearInterval(ticker);
});
</script>

<template>
    <Head title="المطعم التجريبي التفاعلي الحي في 60 ثانية • تَدلّل" />

    <div class="min-h-screen bg-[#070b14] text-white selection:bg-amber-500 selection:text-black font-sans antialiased flex flex-col">
        <!-- Top Nav & CTA Bar -->
        <header class="border-b border-amber-500/20 bg-[#0a0f1d]/90 backdrop-blur-md sticky top-0 z-50 px-4 sm:px-8 py-3.5">
            <div class="max-w-7xl mx-auto flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <Link href="/" class="flex items-center gap-2">
                        <span class="text-xl">👑</span>
                        <span class="font-black text-base sm:text-lg tracking-tight bg-gradient-to-r from-amber-200 via-amber-400 to-amber-100 bg-clip-text text-transparent">
                            تَدلّل
                        </span>
                    </Link>
                    <span class="hidden sm:inline-block h-4 w-px bg-white/20"></span>
                    <span class="hidden sm:inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-500/10 text-amber-400 border border-amber-500/30">
                        <span>⚡</span> المحاكي التفاعلي الحي في 60 ثانية
                    </span>
                </div>

                <div class="flex items-center gap-3">
                    <span class="hidden md:inline-flex items-center gap-1 text-xs text-stone-300">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        0% عمولة • لا أجهزة مخصصة • طباعة حرارية فورية
                    </span>
                    <Link
                        href="/consultation"
                        class="px-4 sm:px-5 py-2 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-white font-black text-xs sm:text-sm shadow-md shadow-amber-500/20 transition transform hover:scale-105 active:scale-95"
                    >
                        🚀 اطلب تدشين مطعمك
                    </Link>
                </div>
            </div>
        </header>

        <!-- Instructions Banner -->
        <div class="bg-gradient-to-r from-amber-950/40 via-amber-900/20 to-amber-950/40 border-b border-amber-500/20 px-4 py-2 text-center text-xs text-amber-200 font-medium">
            <span>💡 <strong>كيف يعمل المحاكي؟</strong> اضف أطباقك من منيو الزبون على اليمين واضغط «إرسال الطلب»، لتشهد بنفسك كيف يظهر فوراً في شاشة المطبخ KDS والكاشير على اليسار مع صوت التنبيه وتوليد الفاتورة دون أي تأخير!</span>
        </div>

        <!-- Mobile Tab Switcher (visible on mobile only) -->
        <div class="lg:hidden flex border-b border-white/10 bg-[#0d1424]">
            <button
                type="button"
                @click="activeTab = 'menu'"
                class="flex-1 py-3 text-xs font-bold text-center border-b-2 transition"
                :class="activeTab === 'menu' ? 'border-amber-500 text-amber-400 bg-amber-500/5' : 'border-transparent text-stone-400'"
            >
                📱 منيو الزبون (طاولة {{ tableNumber }})
                <span v-if="cart.length > 0" class="mr-1 px-1.5 py-0.5 rounded-full bg-amber-500 text-black text-[10px] font-black">
                    {{ cart.length }}
                </span>
            </button>
            <button
                type="button"
                @click="activeTab = 'kds'"
                class="flex-1 py-3 text-xs font-bold text-center border-b-2 transition flex items-center justify-center gap-1.5"
                :class="activeTab === 'kds' ? 'border-emerald-500 text-emerald-400 bg-emerald-500/5' : 'border-transparent text-stone-400'"
            >
                <span>🍳 شاشة المطبخ KDS والكاشير</span>
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
            </button>
        </div>

        <!-- Simulator Workspace -->
        <main class="flex-1 max-w-7xl w-full mx-auto p-4 sm:p-6 lg:p-8 grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            
            <!-- PANE 1: Customer Mobile Menu (5 Cols on Large) -->
            <div
                class="lg:col-span-5 flex flex-col space-y-4"
                :class="activeTab === 'menu' ? 'block' : 'hidden lg:block'"
            >
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="p-2 rounded-xl bg-amber-500/10 text-amber-400 text-lg">📱</span>
                        <div>
                            <h3 class="font-black text-sm sm:text-base text-white">منيو الزبون الذكي بالـ QR</h3>
                            <p class="text-[11px] text-stone-400">ما يراه زبونك على هاتفه في الصالة فور مسح الكود</p>
                        </div>
                    </div>
                    <span class="px-2.5 py-1 rounded-lg bg-white/10 text-amber-300 text-xs font-mono font-bold">
                        طاولة #{{ tableNumber }}
                    </span>
                </div>

                <!-- Phone Frame Mockup -->
                <div class="rounded-3xl border-2 border-stone-700 bg-[#0d1424] shadow-2xl overflow-hidden flex flex-col h-[650px] relative">
                    <!-- Phone Header -->
                    <div class="bg-gradient-to-r from-stone-900 to-[#111827] p-4 border-b border-white/10 flex items-center justify-between">
                        <div>
                            <p class="font-black text-sm text-white flex items-center gap-1.5">
                                <span>مطعم ورد الشام</span>
                                <span class="text-[10px] px-1.5 py-0.5 rounded bg-emerald-500/20 text-emerald-400 font-bold">مفتوح</span>
                            </p>
                            <p class="text-[10px] text-stone-400">طلب رقمي مباشر • طاولة رقم {{ tableNumber }}</p>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="px-2 py-1 rounded-md bg-white/5 border border-white/10 text-[10px] text-amber-300 font-bold">
                                🤖 الويتر الذكي نشط
                            </span>
                        </div>
                    </div>

                    <!-- Items List (Scrollable) -->
                    <div class="flex-1 overflow-y-auto p-4 space-y-3">
                        <div
                            v-for="item in menuItems"
                            :key="item.id"
                            class="p-3 rounded-2xl bg-white/5 border border-white/10 hover:border-amber-500/40 transition flex items-center justify-between gap-3 group"
                        >
                            <div class="flex items-center gap-3">
                                <span class="text-3xl p-2 rounded-xl bg-white/5">{{ item.emoji }}</span>
                                <div>
                                    <h4 class="font-bold text-xs sm:text-sm text-white group-hover:text-amber-300 transition">
                                        {{ item.name }}
                                    </h4>
                                    <p class="text-[10px] text-stone-400 line-clamp-1 mt-0.5">{{ item.description }}</p>
                                    <p class="text-xs font-black text-amber-400 mt-1" dir="ltr">{{ formatCurrency(item.price) }}</p>
                                </div>
                            </div>

                            <button
                                type="button"
                                @click="addToCart(item)"
                                class="w-8 h-8 rounded-xl bg-amber-500/20 hover:bg-amber-500 text-amber-400 hover:text-black font-black text-base flex items-center justify-center transition active:scale-90 shrink-0"
                                title="إضافة للطلب"
                            >
                                +
                            </button>
                        </div>
                    </div>

                    <!-- Cart Summary Drawer inside Phone -->
                    <div class="border-t border-white/10 bg-[#090d18] p-4 space-y-3">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-bold text-stone-300">سلة الطلبات (طاولة {{ tableNumber }}):</span>
                            <span class="font-black text-emerald-400 font-mono text-sm" dir="ltr">
                                {{ formatCurrency(cartTotal) }}
                            </span>
                        </div>

                        <!-- Mini Cart Items -->
                        <div v-if="cart.length > 0" class="max-h-24 overflow-y-auto space-y-1.5 text-xs">
                            <div v-for="cItem in cart" :key="cItem.id" class="flex items-center justify-between py-1 border-b border-white/5 text-[11px]">
                                <span class="text-stone-300">{{ cItem.quantity }}x {{ cItem.name }}</span>
                                <div class="flex items-center gap-2">
                                    <span class="text-stone-400" dir="ltr">{{ formatCurrency(cItem.price * cItem.quantity) }}</span>
                                    <button @click="removeFromCart(cItem.id)" class="text-red-400 hover:text-red-300 font-bold px-1">✕</button>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-2 text-[11px] text-stone-500">
                            السلة فارغة. انقر (+) لإضافة أطباق للتجربة.
                        </div>

                        <!-- Action Button -->
                        <button
                            type="button"
                            @click="sendOrderToKitchen"
                            :disabled="cart.length === 0"
                            class="w-full py-3 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-600 hover:to-teal-600 disabled:opacity-40 text-black font-black text-xs sm:text-sm flex items-center justify-center gap-2 shadow-lg shadow-emerald-500/20 transition transform active:scale-95 cursor-pointer"
                        >
                            <span>🍳</span>
                            <span>إرسال الطلب للمطبخ والكاشير ({{ formatCurrency(cartTotal) }})</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- PANE 2: Kitchen Display System (KDS) & Cashier (7 Cols on Large) -->
            <div
                class="lg:col-span-7 flex flex-col space-y-4"
                :class="activeTab === 'kds' ? 'block' : 'hidden lg:block'"
            >
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="p-2 rounded-xl bg-emerald-500/10 text-emerald-400 text-lg">🍳</span>
                        <div>
                            <h3 class="font-black text-sm sm:text-base text-white">شاشات المطبخ الفورية (KDS) والكاشير</h3>
                            <p class="text-[11px] text-stone-400">استقبال الطلبات لحظياً بدون أوراق وبأعلى سرعة</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 text-xs">
                        <span class="px-3 py-1 rounded-xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-bold">
                            💰 إجمالي اليوم: {{ formatCurrency(totalDailySales) }}
                        </span>
                    </div>
                </div>

                <!-- KDS Screen Simulation -->
                <div class="rounded-3xl border-2 border-emerald-500/30 bg-[#0d1424] shadow-2xl p-5 min-h-[650px] flex flex-col justify-between">
                    
                    <!-- KDS Header Bar -->
                    <div class="flex items-center justify-between pb-4 border-b border-white/10">
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full bg-emerald-400 animate-pulse"></span>
                            <span class="font-bold text-xs sm:text-sm text-stone-200">محطة تحضير المطبخ الرئيسي</span>
                        </div>
                        <span class="text-xs text-stone-400">
                            الطلبات النشطة الآن: <strong class="text-white">{{ orders.filter(o => o.status !== 'ready').length }}</strong>
                        </span>
                    </div>

                    <!-- Orders Cards Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 py-4 flex-1 items-start">
                        <div
                            v-for="order in orders"
                            :key="order.id"
                            class="rounded-2xl border p-4 transition-all duration-300 shadow-md relative overflow-hidden animate-in fade-in zoom-in-95"
                            :class="{
                                'bg-red-950/20 border-red-500/40': order.status === 'pending',
                                'bg-amber-950/20 border-amber-500/40': order.status === 'preparing',
                                'bg-emerald-950/20 border-emerald-500/40 opacity-75': order.status === 'ready',
                            }"
                        >
                            <!-- Card Header -->
                            <div class="flex items-center justify-between border-b border-white/10 pb-2 mb-3">
                                <div>
                                    <span class="text-sm font-black text-white">طاولة #{{ order.tableNumber }}</span>
                                    <span class="text-[10px] text-stone-400 mr-2 font-mono">طلب #{{ order.orderNumber }}</span>
                                </div>
                                <span
                                    class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold"
                                    :class="order.secondsAgo > 120 ? 'bg-red-500 text-white' : 'bg-white/10 text-stone-300'"
                                >
                                    ⏱️ {{ formatSeconds(order.secondsAgo) }}
                                </span>
                            </div>

                            <!-- Items -->
                            <ul class="space-y-1.5 text-xs mb-4">
                                <li v-for="(it, itIdx) in order.items" :key="itIdx" class="flex justify-between items-center text-stone-200">
                                    <span class="font-bold"><span class="text-amber-400 font-black">{{ it.quantity }}x</span> {{ it.name }}</span>
                                    <span class="text-stone-400 text-[10px]" dir="ltr">{{ formatCurrency(it.price * it.quantity) }}</span>
                                </li>
                            </ul>

                            <!-- Status Footer Actions -->
                            <div class="pt-2 border-t border-white/10 flex items-center justify-between gap-2">
                                <span class="text-xs font-black text-emerald-400" dir="ltr">
                                    {{ formatCurrency(order.total) }}
                                </span>

                                <div class="flex gap-1.5">
                                    <button
                                        v-if="order.status === 'pending'"
                                        type="button"
                                        @click="updateOrderStatus(order, 'preparing')"
                                        class="px-3 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-600 text-black text-xs font-black transition active:scale-90"
                                    >
                                        🍳 تحضير
                                    </button>
                                    <button
                                        v-if="order.status === 'preparing'"
                                        type="button"
                                        @click="updateOrderStatus(order, 'ready')"
                                        class="px-3 py-1.5 rounded-lg bg-emerald-500 hover:bg-emerald-600 text-black text-xs font-black transition active:scale-90"
                                    >
                                        ✅ جاهز للتقديم
                                    </button>
                                    <span
                                        v-if="order.status === 'ready'"
                                        class="px-2.5 py-1 rounded-lg bg-emerald-500/20 text-emerald-300 text-xs font-bold"
                                    >
                                        ✓ تم التسليم
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Simulated Cloud Thermal Receipt Printout Widget -->
                    <div v-if="latestPrintedReceipt" class="mt-4 p-4 rounded-2xl bg-[#090d18] border border-stone-700 flex flex-col sm:flex-row items-center justify-between gap-4 font-mono text-xs">
                        <div class="flex items-center gap-3">
                            <span class="text-2xl p-2 rounded-xl bg-white/5">🖨️</span>
                            <div>
                                <p class="text-white font-bold font-sans">محاكاة الطباعة الحرارية اللحظية (Cloud ESC/POS):</p>
                                <p class="text-[11px] text-stone-400 font-sans">فاتورة طاولة {{ latestPrintedReceipt.tableNumber }} • طباعة مباشرة للصندوق والمطبخ</p>
                            </div>
                        </div>
                        <div class="px-3 py-1.5 rounded-xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 text-xs font-bold font-sans">
                            0% عمولة • وفرت {{ formatCurrency(Math.round(latestPrintedReceipt.total * 0.20)) }}
                        </div>
                    </div>

                </div>
            </div>

        </main>
    </div>
</template>
