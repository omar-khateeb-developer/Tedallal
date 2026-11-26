<script setup lang="ts">
import { ref, computed } from 'vue';

const tablesCount = ref(18);
const monthlyRevenueM = ref(25); // In Millions SYP
const usesDeliveryApps = ref(true);
const deliveryPercentage = ref(30); // 30% of sales from delivery

// Calculations
const monthlyRevenue = computed(() => monthlyRevenueM.value * 1_000_000);

// Delivery commissions lost: 20% average fee on delivery orders
const monthlyDeliveryLost = computed(() => {
    if (!usesDeliveryApps.value) return 0;
    const deliveryVolume = monthlyRevenue.value * (deliveryPercentage.value / 100);
    return Math.round(deliveryVolume * 0.20);
});

// Cash leakage & kitchen paper order errors without Z-Reports & KDS: estimated 5%
const monthlyLeakageLost = computed(() => {
    return Math.round(monthlyRevenue.value * 0.05);
});

// Total monthly & annual loss without Tedallal
const totalMonthlyWaste = computed(() => monthlyDeliveryLost.value + monthlyLeakageLost.value);
const totalAnnualWaste = computed(() => totalMonthlyWaste.value * 12);

// Tedallal Annual Pro Plan Cost (approx 350$ * 15,000 SYP = ~5,250,000 SYP)
const tedallalAnnualCost = computed(() => 5_250_000);

// Net annual savings
const netAnnualSavings = computed(() => Math.max(0, totalAnnualWaste.value - tedallalAnnualCost.value));

// ROI Percentage
const roiPercentage = computed(() => {
    if (tedallalAnnualCost.value === 0) return 100;
    return Math.round((netAnnualSavings.value / tedallalAnnualCost.value) * 100);
});

const formatSyp = (amount: number) => {
    if (amount >= 1_000_000) {
        return (amount / 1_000_000).toFixed(1) + ' مليون ل.س';
    }
    return new Intl.NumberFormat('ar-SY-u-nu-latn').format(amount) + ' ل.س';
};
</script>

<template>
    <div class="w-full max-w-5xl mx-auto rounded-3xl bg-radial from-stone-900 to-[#0a0f1d] border border-amber-500/20 shadow-2xl p-6 sm:p-10 text-white relative overflow-hidden">
        <!-- Background Ambient Glow -->
        <div class="absolute -top-32 -left-32 w-80 h-80 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-32 -right-32 w-80 h-80 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>

        <!-- Header -->
        <div class="text-center max-w-2xl mx-auto mb-10">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black bg-amber-500/10 text-amber-400 border border-amber-500/30 mb-3">
                <span>📊</span> حاسبة العائد المالي ومنع الهدر
            </span>
            <h2 class="text-2xl sm:text-3xl font-black text-white tracking-tight">
                كم تنزف منشأتك شهرياً بدون منظومة سحابية متكاملة؟
            </h2>
            <p class="text-stone-400 text-xs sm:text-sm mt-2">
                حرّك المؤشرات بحسب حجم مطعمك أو مقهاك، وشاهد بالأرقام الحقيقية كيف تحمي تَدلّل أرباحك وتستعيد ملايين الليرات الضائعة.
            </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
            <!-- Left Controls Column -->
            <div class="lg:col-span-7 space-y-6">
                <!-- Control 1: Tables count -->
                <div class="bg-white/5 border border-white/10 rounded-2xl p-5">
                    <div class="flex items-center justify-between mb-3">
                        <label for="roi-tables-count" class="text-xs sm:text-sm font-bold text-stone-200">
                            🪑 عدد الطاولات في الصالة:
                        </label>
                        <span class="text-base font-black text-amber-400 px-3 py-1 bg-amber-500/10 rounded-lg border border-amber-500/20">
                            {{ tablesCount }} طاولة
                        </span>
                    </div>
                    <input
                        id="roi-tables-count"
                        type="range"
                        v-model.number="tablesCount"
                        min="4"
                        max="60"
                        step="2"
                        class="w-full accent-amber-500 cursor-pointer"
                    />
                    <div class="flex justify-between text-[11px] text-stone-500 mt-1">
                        <span>كافيه صغير (4)</span>
                        <span>صالة متوسطة (20)</span>
                        <span>مجمع فاخر (60)</span>
                    </div>
                </div>

                <!-- Control 2: Monthly revenue -->
                <div class="bg-white/5 border border-white/10 rounded-2xl p-5">
                    <div class="flex items-center justify-between mb-3">
                        <label for="roi-monthly-revenue" class="text-xs sm:text-sm font-bold text-stone-200">
                            💰 متوسط المبيعات الشهرية التقديري:
                        </label>
                        <span class="text-base font-black text-emerald-400 px-3 py-1 bg-emerald-500/10 rounded-lg border border-emerald-500/20">
                            {{ monthlyRevenueM }} مليون ل.س
                        </span>
                    </div>
                    <input
                        id="roi-monthly-revenue"
                        type="range"
                        v-model.number="monthlyRevenueM"
                        min="5"
                        max="100"
                        step="5"
                        class="w-full accent-emerald-500 cursor-pointer"
                    />
                    <div class="flex justify-between text-[11px] text-stone-400 mt-1">
                        <span>5 ملايين</span>
                        <span>50 مليون</span>
                        <span>100 مليون ل.س</span>
                    </div>
                </div>

                <!-- Control 3: Delivery Apps toggle -->
                <div class="bg-white/5 border border-white/10 rounded-2xl p-5 flex items-center justify-between">
                    <div>
                        <p id="roi-delivery-apps-label" class="text-xs sm:text-sm font-bold text-stone-200">تعتمد على تطبيقات التوصيل والوساطة؟</p>
                        <p class="text-[11px] text-stone-400 mt-0.5">تقتطع معظم التطبيقات عمولات تتراوح بين 18% إلى 25% من كل فاتورة</p>
                    </div>
                    <button
                        type="button"
                        role="switch"
                        :aria-checked="usesDeliveryApps"
                        aria-labelledby="roi-delivery-apps-label"
                        @click="usesDeliveryApps = !usesDeliveryApps"
                        class="relative inline-flex h-7 w-13 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-hidden"
                        :class="usesDeliveryApps ? 'bg-amber-600' : 'bg-stone-700'"
                    >
                        <span
                            class="pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow-lg ring-0 transition duration-200 ease-in-out"
                            :class="usesDeliveryApps ? '-translate-x-6' : 'translate-x-0'"
                        />
                    </button>
                </div>
            </div>

            <!-- Right Results Column -->
            <div class="lg:col-span-5 bg-gradient-to-b from-stone-800/80 to-stone-900/90 border border-amber-500/30 rounded-3xl p-6 sm:p-7 relative shadow-xl">
                <div class="space-y-4">
                    <p class="text-xs font-bold uppercase tracking-wider text-amber-400 text-center">
                        تقرير استعادة الأرباح الحصري لمطعمك
                    </p>

                    <!-- Leakage 1: Delivery waste -->
                    <div class="p-3.5 rounded-xl bg-red-950/30 border border-red-500/20 flex items-center justify-between">
                        <div>
                            <p class="text-xs text-red-300 font-semibold">عمولات التوصيل الضائعة سنوياً:</p>
                            <p class="text-[10px] text-stone-400">تذهب لمنصات التوصيل بدلاً من جيبك</p>
                        </div>
                        <span class="text-sm font-black text-red-400" dir="ltr">
                            - {{ formatSyp(monthlyDeliveryLost * 12) }}
                        </span>
                    </div>

                    <!-- Leakage 2: Cashier and kitchen leakage -->
                    <div class="p-3.5 rounded-xl bg-amber-950/30 border border-amber-500/20 flex items-center justify-between">
                        <div>
                            <p class="text-xs text-amber-300 font-semibold">هدر الصندوق والورق التقديري سنوياً:</p>
                            <p class="text-[10px] text-stone-400">تمنعه شاشات KDS وتقارير Z-Report</p>
                        </div>
                        <span class="text-sm font-black text-amber-400" dir="ltr">
                            - {{ formatSyp(monthlyLeakageLost * 12) }}
                        </span>
                    </div>

                    <!-- The Grand Net Savings -->
                    <div class="p-5 rounded-2xl bg-gradient-to-r from-emerald-950/60 to-emerald-900/40 border border-emerald-500/40 text-center space-y-1">
                        <p class="text-xs font-bold text-emerald-300">صافي الأرباح المحمية سنوياً مع تَدلّل:</p>
                        <p class="text-2xl sm:text-3xl font-black text-emerald-400 py-1" dir="ltr">
                            + {{ formatSyp(netAnnualSavings) }}
                        </p>
                        <p class="text-[11px] text-emerald-200/80 font-medium">
                            عائد استثماري فلكي بنسبة <span class="font-bold text-emerald-300">+{{ roiPercentage }}%</span> على اشتراكك!
                        </p>
                    </div>

                    <!-- CTAs -->
                    <div class="pt-2 space-y-2">
                        <a
                            href="/consultation"
                            class="w-full py-3.5 px-4 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-white font-black text-xs sm:text-sm text-center block shadow-lg shadow-amber-500/20 transition transform hover:scale-[1.02] active:scale-[0.98]"
                        >
                            ✍️ اطلب تدشين مطعمك واستعد هذه الأرباح
                        </a>
                        <a
                            href="/demo"
                            class="w-full py-2.5 px-4 rounded-xl bg-white/5 hover:bg-white/10 text-stone-300 font-bold text-xs text-center block border border-white/10 transition"
                        >
                            ⚡ شاهد المحاكي التفاعلي الحي في 60 ثانية
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
