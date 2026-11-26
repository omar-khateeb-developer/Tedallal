<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import axios from 'axios';
import type { CustomerRow, WaConnectionStatus } from '@/types/customers';

const props = defineProps<{
    customer: CustomerRow;
    currency: string;
    waStatus: WaConnectionStatus;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'sent', payload: { sentDirectly: boolean; customerName: string }): void;
}>();

const campaignForm = ref({
    campaign_type: 'win_back' as 'win_back' | 'vip_reward' | 'welcome_lead' | 'custom',
    custom_message: '',
    create_voucher: true,
    discount_type: 'percentage' as 'percentage' | 'fixed',
    discount_value: 15,
    valid_days: 7,
    send_direct: false,
});
const sendingCampaign = ref(false);
const campaignError = ref<string | null>(null);

watch(
    () => props.customer,
    (customer) => {
        campaignError.value = null;
        let defaultType: 'win_back' | 'vip_reward' | 'welcome_lead' | 'custom' = 'win_back';
        let defaultValue = 15;
        if (customer.segment === 'vip') {
            defaultType = 'vip_reward';
            defaultValue = 20;
        } else if (customer.segment === 'at_risk') {
            defaultType = 'win_back';
            defaultValue = 15;
        } else if (customer.segment === 'new_lead') {
            defaultType = 'welcome_lead';
            defaultValue = 10;
        }

        campaignForm.value = {
            campaign_type: defaultType,
            custom_message: '',
            create_voucher: true,
            discount_type: 'percentage',
            discount_value: defaultValue,
            valid_days: 7,
            send_direct: props.waStatus === 'connected',
        };
    },
    { immediate: true },
);

const previewCampaignMessage = computed(() => {
    const name = props.customer.name;
    const discountVal = campaignForm.value.create_voucher
        ? (campaignForm.value.discount_type === 'percentage'
            ? `خصم ${campaignForm.value.discount_value}%`
            : `خصم ${campaignForm.value.discount_value} ${props.currency}`)
        : '';
    const validDays = campaignForm.value.valid_days;

    if (campaignForm.value.campaign_type === 'custom') {
        return campaignForm.value.custom_message || 'أدخل نص رسالتك المخصصة هنا...';
    }

    if (campaignForm.value.campaign_type === 'vip_reward') {
        return `🌟 أهلاً بحضرتك ${name}! ✨\n\nتقديراً لولائك الدائم وتشريف حضرتك المتكرر لمطعمنا، يسعدنا تقديم هذه الهدية الخاصة:\n${campaignForm.value.create_voucher ? `🎯 الهدية: ${discountVal}\n🎟️ كود الخصم الحصري: VIP-XXXXX\n📅 صالح لمدة ${validDays} أيام\n📲 للطلب وتطبيق الخصم مباشرة في المنيو\n\n` : ''}يسعدنا ويشرفنا دائماً وجود حضرتك بيننا، ونتطلع لاستقبالكم في أقرب وقت! 👑`;
    }

    if (campaignForm.value.campaign_type === 'win_back') {
        return `🌸 أهلاً بحضرتك ${name} في مطعمنا! 👋\n\nمر وقت منذ آخر زيارة تشرّفنا بها، ويسعدنا إهداء حضرتك كود خصم خاص للترحيب بكم مجدداً:\n${campaignForm.value.create_voucher ? `🎯 كود العودة: ${discountVal}\n🎟️ الكود: BACK-XXXXX\n📅 صالح لمدة ${validDays} أيام\n📲 للطلب المباشر أو زيارتنا\n\n` : ''}نسعد دائماً بتشريف حضرتك وأشهى الأطباق جاهزة لذوقكم الرفيع ✨`;
    }

    return `🎉 أهلاً بحضرتك ${name} في عائلتنا! ✨\n\nسعدنا جداً بزيارة حضرتك الأولى لنا، ويسعدنا تقديم كود خصم خاص للزيارة القادمة:\n${campaignForm.value.create_voucher ? `🎯 الهدية: ${discountVal}\n🎟️ كود الخصم: HI-XXXXX\n📅 صالح لمدة ${validDays} أيام\n📲 رابط المنيو وتطبيق الخصم\n\n` : ''}نتطلع لخدمة حضرتك مجدداً بأعلى معايير الضيافة! 🍽️`;
});

async function launchCampaign(sendDirect: boolean) {
    sendingCampaign.value = true;
    campaignError.value = null;
    campaignForm.value.send_direct = sendDirect;

    try {
        const res = await axios.post(`/api/admin/customers/${props.customer.id}/send-campaign`, campaignForm.value);
        const data = res.data.data;

        if (!data.sent_directly && data.whatsapp_url) {
            window.open(data.whatsapp_url, '_blank', 'noopener,noreferrer');
        }

        emit('sent', {
            sentDirectly: Boolean(data.sent_directly),
            customerName: props.customer.name,
        });
        emit('close');
    } catch (e: any) {
        campaignError.value = e.response?.data?.message || 'تعذر إطلاق الحملة.';
    } finally {
        sendingCampaign.value = false;
    }
}
</script>

<template>
    <div
        class="fixed inset-0 z-50 bg-black/75 backdrop-blur-xs flex items-center justify-center p-3 sm:p-6 overflow-y-auto"
        dir="rtl"
        @click.self="emit('close')"
    >
        <div
            class="bg-white dark:bg-[#0f172a] rounded-3xl max-w-xl w-full border border-slate-200 dark:border-slate-700 shadow-2xl flex flex-col max-h-[90vh] overflow-hidden my-auto"
            @click.stop
        >
            <!-- Header -->
            <div class="flex items-center justify-between p-5 border-b border-slate-100 dark:border-slate-800 bg-white dark:bg-[#0f172a] z-10 shrink-0">
                <div class="flex items-center gap-2.5">
                    <span class="text-2xl">🚀</span>
                    <div>
                        <h3 class="text-lg font-black text-slate-900 dark:text-white">إطلاق حملة واتساب للعميل</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            العميل: <span class="font-bold text-slate-800 dark:text-slate-200">{{ customer.name }}</span> ({{ customer.phone }})
                        </p>
                    </div>
                </div>
                <button
                    @click="emit('close')"
                    type="button"
                    class="w-9 h-9 rounded-full bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white flex items-center justify-center font-bold text-base transition cursor-pointer"
                    title="إغلاق (Esc)"
                >
                    ✕
                </button>
            </div>

            <!-- Body -->
            <div class="p-5 sm:p-6 space-y-5 overflow-y-auto flex-1">
                <!-- Segment Banner -->
                <div class="p-3.5 rounded-2xl border flex items-center justify-between gap-3 bg-slate-50 dark:bg-slate-900/60 border-slate-200 dark:border-slate-800">
                    <div class="text-xs space-y-0.5">
                        <div class="font-bold text-slate-800 dark:text-slate-200">تصنيف العميل في النظام:</div>
                        <div class="text-slate-500 dark:text-slate-400">{{ customer.segment_tagline }}</div>
                    </div>
                    <span
                        class="text-xs font-black px-3 py-1 rounded-full shadow-xs"
                        :class="customer.segment === 'vip' ? 'bg-gradient-to-r from-amber-500 to-orange-500 text-white' : (customer.segment === 'at_risk' ? 'bg-rose-100 text-rose-800 dark:bg-rose-950/70 dark:text-rose-300' : (customer.segment === 'new_lead' ? 'bg-sky-100 text-sky-800 dark:bg-sky-950/70 dark:text-sky-300' : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/70 dark:text-emerald-300'))"
                    >
                        {{ customer.segment_label }}
                    </span>
                </div>

                <!-- Campaign Type Selector -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-2">نوع الحملة المقترحة:</label>
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            @click="campaignForm.campaign_type = 'win_back'"
                            class="p-3 rounded-xl border text-right transition cursor-pointer"
                            :class="campaignForm.campaign_type === 'win_back'
                                ? 'border-rose-500 bg-rose-50/70 dark:bg-rose-950/40 text-rose-900 dark:text-rose-200 font-black ring-2 ring-rose-500/20'
                                : 'border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/60'"
                        >
                            <div class="text-xs">⏳ استعادة عميل منقطع</div>
                            <div class="text-[10px] text-slate-500 dark:text-slate-400 font-normal mt-0.5">دعوة للعودة مع كود خاص</div>
                        </button>

                        <button
                            type="button"
                            @click="campaignForm.campaign_type = 'vip_reward'"
                            class="p-3 rounded-xl border text-right transition cursor-pointer"
                            :class="campaignForm.campaign_type === 'vip_reward'
                                ? 'border-amber-500 bg-amber-50/70 dark:bg-amber-950/40 text-amber-900 dark:text-amber-200 font-black ring-2 ring-amber-500/20'
                                : 'border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/60'"
                        >
                            <div class="text-xs">👑 تكريم عميل VIP</div>
                            <div class="text-[10px] text-slate-500 dark:text-slate-400 font-normal mt-0.5">شكر على الولاء وهدية حصرية</div>
                        </button>

                        <button
                            type="button"
                            @click="campaignForm.campaign_type = 'welcome_lead'"
                            class="p-3 rounded-xl border text-right transition cursor-pointer"
                            :class="campaignForm.campaign_type === 'welcome_lead'
                                ? 'border-sky-500 bg-sky-50/70 dark:bg-sky-950/40 text-sky-900 dark:text-sky-200 font-black ring-2 ring-sky-500/20'
                                : 'border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/60'"
                        >
                            <div class="text-xs">🎁 ترحيب بالزيارة الثانية</div>
                            <div class="text-[10px] text-slate-500 dark:text-slate-400 font-normal mt-0.5">تحفيز العميل على تكرار الزيارة</div>
                        </button>

                        <button
                            type="button"
                            @click="campaignForm.campaign_type = 'custom'"
                            class="p-3 rounded-xl border text-right transition cursor-pointer"
                            :class="campaignForm.campaign_type === 'custom'
                                ? 'border-purple-500 bg-purple-50/70 dark:bg-purple-950/40 text-purple-900 dark:text-purple-200 font-black ring-2 ring-purple-500/20'
                                : 'border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/60'"
                        >
                            <div class="text-xs">✍️ رسالة مخصصة</div>
                            <div class="text-[10px] text-slate-500 dark:text-slate-400 font-normal mt-0.5">كتابة نص حر بالكامل</div>
                        </button>
                    </div>
                </div>

                <!-- Custom Message Input (if custom) -->
                <div v-if="campaignForm.campaign_type === 'custom'">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">نص الرسالة المخصصة:</label>
                    <textarea
                        v-model="campaignForm.custom_message"
                        rows="3"
                        placeholder="اكتب رسالتك للعميل هنا..."
                        class="w-full rounded-xl text-xs border-slate-200 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                    ></textarea>
                </div>

                <!-- Promo Code Generation Switch & Inputs -->
                <div class="p-4 rounded-2xl border border-amber-200/80 dark:border-amber-700/40 bg-amber-50/40 dark:bg-amber-950/15 space-y-3">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input
                            v-model="campaignForm.create_voucher"
                            type="checkbox"
                            class="rounded text-amber-600 focus:ring-amber-500"
                        />
                        <div>
                            <span class="text-xs font-black text-amber-950 dark:text-amber-200">
                                🎟️ توليد كود خصم حصري للعميل وتضمينه بالرسالة تلقائياً
                            </span>
                            <p class="text-[11px] text-amber-800/80 dark:text-amber-300/70">
                                يُصدر النظام كوداً مخصصاً لمرة واحدة مربوطاً بالعميل ويطبقه في المنيو بضغطة زر.
                            </p>
                        </div>
                    </label>

                    <div v-if="campaignForm.create_voucher" class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">نوع الخصم:</label>
                            <select
                                v-model="campaignForm.discount_type"
                                class="w-full rounded-xl text-xs border-slate-200 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                            >
                                <option value="percentage">نسبة مئوية (%)</option>
                                <option value="fixed">مبلغ نقدي ثابت ({{ currency }})</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">
                                {{ campaignForm.discount_type === 'percentage' ? 'قيمة الخصم (%):' : `المبلغ (${currency}):` }}
                            </label>
                            <input
                                v-model.number="campaignForm.discount_value"
                                type="number"
                                min="1"
                                :max="campaignForm.discount_type === 'percentage' ? 100 : undefined"
                                class="w-full rounded-xl text-xs border-slate-200 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                            />
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">صلاحية الكود:</label>
                            <select
                                v-model.number="campaignForm.valid_days"
                                class="w-full rounded-xl text-xs border-slate-200 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                            >
                                <option :value="3">3 أيام (عاجل)</option>
                                <option :value="7">7 أيام (أسبوع)</option>
                                <option :value="14">14 يوماً (أسبوعين)</option>
                                <option :value="30">30 يوماً (شهر)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Live Message Preview Box -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                        معاينة الرسالة كما ستصل للعميل على واتساب:
                    </label>
                    <div class="p-4 rounded-2xl bg-emerald-50/60 dark:bg-[#062016] border border-emerald-200 dark:border-emerald-800/60 text-xs text-slate-800 dark:text-slate-200 whitespace-pre-wrap leading-relaxed font-sans shadow-inner">
                        {{ previewCampaignMessage }}
                    </div>
                </div>

                <div v-if="campaignError" class="p-3 rounded-xl bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 text-xs font-bold">
                    ⚠️ {{ campaignError }}
                </div>
            </div>

            <!-- Footer Actions -->
            <div class="p-4 sm:p-5 border-t border-slate-100 dark:border-slate-800 bg-slate-50/90 dark:bg-slate-900/90 backdrop-blur-xs flex flex-wrap items-center justify-between gap-3 shrink-0">
                <button
                    @click="emit('close')"
                    type="button"
                    class="px-4 py-2.5 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700 transition cursor-pointer"
                >
                    إلغاء
                </button>

                <div class="flex items-center gap-2">
                    <button
                        v-if="waStatus === 'connected'"
                        @click="launchCampaign(true)"
                        :disabled="sendingCampaign"
                        type="button"
                        class="px-4 py-2.5 rounded-xl text-xs font-black bg-emerald-600 hover:bg-emerald-500 text-white shadow-md transition disabled:opacity-50 cursor-pointer flex items-center gap-1.5"
                    >
                        <span>📲</span>
                        <span>{{ sendingCampaign ? 'جارٍ الإرسال...' : 'إرسال فوري بالخلفية' }}</span>
                    </button>

                    <button
                        @click="launchCampaign(false)"
                        :disabled="sendingCampaign"
                        type="button"
                        class="px-4 py-2.5 rounded-xl text-xs font-black transition shadow-md disabled:opacity-50 cursor-pointer flex items-center gap-1.5"
                        :class="waStatus === 'connected'
                            ? 'bg-slate-200 hover:bg-slate-300 dark:bg-slate-800 text-slate-800 dark:text-slate-200'
                            : 'bg-emerald-600 hover:bg-emerald-500 text-white'"
                    >
                        <span>💬</span>
                        <span>{{ waStatus === 'connected' ? 'فتح بواتساب' : (sendingCampaign ? 'جارٍ التجهيز...' : 'إرسال عبر تطبيق واتساب') }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
