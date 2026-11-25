<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';
import axios from 'axios';

const platformWaStatus = ref<'disconnected' | 'connecting' | 'scan_qr' | 'connected'>('disconnected');
const platformWaQr = ref<string | null>(null);
const platformWaPhone = ref<string | null>(null);
const platformWaAiEnabled = ref(true);
const platformAiCustomInstructions = ref('');
const savingAiInstructions = ref(false);
const aiInstructionsSavedSuccess = ref(false);
const showPlatformAiPanel = ref(false);
const activeAiTab = ref<'ai' | 'whatsapp'>('ai');
const platformWaLoading = ref(false);
const platformTestPhone = ref('');
const platformTestLoading = ref(false);
let platformWaPollTimer: any = null;

const togglePlatformAiPanel = () => {
    showPlatformAiPanel.value = !showPlatformAiPanel.value;
    try {
        localStorage.setItem('superadmin_show_ai_panel', String(showPlatformAiPanel.value));
    } catch (e) {}
};

const selectAiTab = (tab: 'ai' | 'whatsapp') => {
    activeAiTab.value = tab;
    showPlatformAiPanel.value = true;
    try {
        localStorage.setItem('superadmin_show_ai_panel', 'true');
    } catch (e) {}
};

const fetchPlatformWaStatus = async () => {
    try {
        const { data } = await axios.get('/api/super-admin/platform-whatsapp/status');
        if (data.success) {
            platformWaStatus.value = data.data.status;
            platformWaQr.value = data.data.qr_code;
            platformWaPhone.value = data.data.phone;
            platformWaAiEnabled.value = data.data.ai_enabled;
            if (data.data.custom_instructions !== undefined && !savingAiInstructions.value) {
                platformAiCustomInstructions.value = data.data.custom_instructions;
            }

            if (platformWaStatus.value === 'scan_qr' || platformWaStatus.value === 'connecting') {
                if (!platformWaPollTimer) {
                    platformWaPollTimer = setInterval(fetchPlatformWaStatus, 3000);
                }
            } else if (platformWaPollTimer) {
                clearInterval(platformWaPollTimer);
                platformWaPollTimer = null;
            }
        }
    } catch (e) {
        console.error('Failed to fetch platform WhatsApp status', e);
    }
};

const connectPlatformWa = async () => {
    platformWaLoading.value = true;
    try {
        const { data } = await axios.post('/api/super-admin/platform-whatsapp/connect');
        if (data.success) {
            platformWaStatus.value = data.data.status;
            platformWaQr.value = data.data.qr_code;
            fetchPlatformWaStatus();
        }
    } catch (e: any) {
        alert(e.response?.data?.message || 'تعذر بدء جلسة واتساب إدارة المنصة');
    } finally {
        platformWaLoading.value = false;
    }
};

const disconnectPlatformWa = async () => {
    if (!confirm('هل أنت متأكد من فصل رقم واتساب إدارة المنصة؟ لن يعمل المساعد الذكي حتى تعيد ربط الرقم.')) return;
    platformWaLoading.value = true;
    try {
        await axios.post('/api/super-admin/platform-whatsapp/disconnect');
        platformWaStatus.value = 'disconnected';
        platformWaQr.value = null;
        platformWaPhone.value = null;
        if (platformWaPollTimer) {
            clearInterval(platformWaPollTimer);
            platformWaPollTimer = null;
        }
    } catch (e: any) {
        alert(e.response?.data?.message || 'تعذر فصل الجلسة');
    } finally {
        platformWaLoading.value = false;
    }
};

const togglePlatformAi = async () => {
    const nextVal = !platformWaAiEnabled.value;
    try {
        await axios.post('/api/super-admin/platform-whatsapp/settings', {
            ai_enabled: nextVal,
        });
        platformWaAiEnabled.value = nextVal;
    } catch (e) {
        alert('تعذر تحديث إعدادات المساعد الذكي');
    }
};

const saveAiInstructions = async () => {
    savingAiInstructions.value = true;
    try {
        const { data } = await axios.post('/api/super-admin/platform-whatsapp/settings', {
            custom_instructions: platformAiCustomInstructions.value,
        });
        if (data.success) {
            aiInstructionsSavedSuccess.value = true;
            setTimeout(() => {
                aiInstructionsSavedSuccess.value = false;
            }, 3500);
        }
    } catch (e: any) {
        alert(e.response?.data?.message || 'تعذر حفظ توجيهات المساعد الذكي');
    } finally {
        savingAiInstructions.value = false;
    }
};

const insertInstructionTemplate = (snippet: string) => {
    if (!platformAiCustomInstructions.value.trim()) {
        platformAiCustomInstructions.value = snippet;
    } else {
        platformAiCustomInstructions.value += '\n' + snippet;
    }
};

const sendPlatformTestMsg = async () => {
    if (!platformTestPhone.value.trim()) return;
    platformTestLoading.value = true;
    try {
        const { data } = await axios.post('/api/super-admin/platform-whatsapp/send-test', {
            phone: platformTestPhone.value.trim(),
        });
        alert(data.data?.message || 'تم إرسال الرسالة بنجاح!');
        platformTestPhone.value = '';
    } catch (e: any) {
        alert(e.response?.data?.message || 'تعذر إرسال الرسالة التجريبية');
    } finally {
        platformTestLoading.value = false;
    }
};

onMounted(() => {
    try {
        const saved = localStorage.getItem('superadmin_show_ai_panel');
        if (saved !== null) {
            showPlatformAiPanel.value = saved === 'true';
        }
    } catch (e) {}
    fetchPlatformWaStatus();
});

onUnmounted(() => {
    if (platformWaPollTimer) {
        clearInterval(platformWaPollTimer);
        platformWaPollTimer = null;
    }
});
</script>

<template>
    <div class="bg-white dark:bg-[#0a0f1d] overflow-hidden shadow-sm sm:rounded-2xl border border-stone-200 dark:border-slate-800 transition-all duration-300">
        <div class="p-4 sm:p-6" dir="rtl">
            <div
                class="flex items-center justify-between flex-wrap gap-4 transition-all"
                :class="showPlatformAiPanel ? 'border-b border-stone-100 dark:border-slate-800 pb-5' : ''"
            >
                <div
                    class="flex items-center gap-3.5 cursor-pointer select-none group"
                    @click="togglePlatformAiPanel"
                    title="انقر لإظهار أو إخفاء قسم مجيب الذكاء"
                >
                    <div
                        class="p-3 rounded-2xl shrink-0 transition-transform group-hover:scale-105 active:scale-95"
                        :class="platformWaStatus === 'connected' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300' : 'bg-stone-100 text-stone-600 dark:bg-slate-800 dark:text-slate-400'"
                    >
                        <span class="text-2xl">🤖</span>
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-900 dark:text-white text-base flex items-center gap-2 flex-wrap">
                            <span>مجيب الذكاء التنفيذي وواتساب المنصة</span>
                            <span class="text-xs font-normal text-stone-500">| Executive AI Assistant & WhatsApp</span>
                        </h3>
                        <div class="flex items-center gap-2 mt-1 flex-wrap">
                            <span
                                class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold"
                                :class="platformWaStatus === 'connected'
                                    ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300'
                                    : platformWaStatus === 'scan_qr'
                                    ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 animate-pulse'
                                    : 'bg-stone-100 text-stone-600 dark:bg-slate-800 dark:text-slate-400'"
                            >
                                <span
                                    class="inline-block w-2 h-2 rounded-full"
                                    :class="platformWaStatus === 'connected' ? 'bg-emerald-500 animate-pulse' : (platformWaStatus === 'scan_qr' ? 'bg-amber-500' : 'bg-stone-400')"
                                ></span>
                                <span v-if="platformWaStatus === 'connected'">متصل برقم المنصة (+{{ platformWaPhone }}) 🟢</span>
                                <span v-else-if="platformWaStatus === 'scan_qr'">بانتظار مسح الباركود 🟡</span>
                                <span v-else>غير مرتبط حالياً ⚪</span>
                            </span>

                            <span
                                v-if="platformWaStatus === 'connected'"
                                class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold"
                                :class="platformWaAiEnabled ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-300' : 'bg-stone-100 text-stone-600 dark:bg-slate-800 dark:text-slate-400'"
                            >
                                <span>{{ platformWaAiEnabled ? 'الرد الذكي مفعّل ⚡' : 'الرد الذكي متوقف' }}</span>
                            </span>

                            <span v-if="!showPlatformAiPanel" class="text-[11px] text-stone-400 dark:text-slate-500 hidden sm:inline">
                                (انقر للتوسيع أو التعديل)
                            </span>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2 flex-wrap">
                    <div class="inline-flex p-1 bg-stone-100 dark:bg-slate-800/80 rounded-xl border border-stone-200/80 dark:border-slate-700/60">
                        <button
                            type="button"
                            @click="selectAiTab('ai')"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer select-none"
                            :class="showPlatformAiPanel && activeAiTab === 'ai'
                                ? 'bg-white dark:bg-slate-900 text-indigo-600 dark:text-indigo-400 shadow-xs'
                                : 'text-stone-600 dark:text-slate-400 hover:text-stone-900 dark:hover:text-white'"
                        >
                            <span>🧠</span>
                            <span>مجيب الذكاء</span>
                        </button>
                        <button
                            type="button"
                            @click="selectAiTab('whatsapp')"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer select-none"
                            :class="showPlatformAiPanel && activeAiTab === 'whatsapp'
                                ? 'bg-white dark:bg-slate-900 text-emerald-600 dark:text-emerald-400 shadow-xs'
                                : 'text-stone-600 dark:text-slate-400 hover:text-stone-900 dark:hover:text-white'"
                        >
                            <span>📲</span>
                            <span>ربط الواتساب</span>
                        </button>
                    </div>

                    <button
                        type="button"
                        @click="togglePlatformAiPanel"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold transition-all shadow-xs cursor-pointer select-none active:scale-95"
                        :class="showPlatformAiPanel
                            ? 'bg-indigo-600 text-white hover:bg-indigo-700 shadow-indigo-600/20'
                            : 'bg-white dark:bg-slate-800 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-slate-700 hover:bg-indigo-50 dark:hover:bg-slate-700'"
                    >
                        <span>{{ showPlatformAiPanel ? 'إخفاء القسم ▲' : 'إظهار القسم 🤖 ▼' }}</span>
                    </button>
                </div>
            </div>

            <transition
                enter-active-class="transition-all duration-300 ease-out overflow-hidden"
                enter-from-class="opacity-0 -translate-y-2 max-h-0"
                enter-to-class="opacity-100 translate-y-0 max-h-[3000px]"
                leave-active-class="transition-all duration-200 ease-in overflow-hidden"
                leave-from-class="opacity-100 translate-y-0 max-h-[3000px]"
                leave-to-class="opacity-0 -translate-y-2 max-h-0"
            >
                <div v-show="showPlatformAiPanel" class="mt-6 space-y-6">
                    <div v-show="activeAiTab === 'ai'" class="space-y-4">
                        <div class="p-4 rounded-2xl bg-emerald-50/60 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-800/40 flex items-center justify-between flex-wrap gap-3">
                            <div class="flex items-center gap-3">
                                <span class="text-2xl">🤖</span>
                                <div>
                                    <div class="text-sm font-black text-emerald-900 dark:text-emerald-200">
                                        المساعد التنفيذي الذكي لصاحب المنصة (Google Gemini)
                                    </div>
                                    <div class="text-xs text-emerald-700 dark:text-emerald-400 mt-0.5">
                                        يرد تلقائياً وبلباقة على استفسارات أصحاب المطاعم، يشرح الميزات والأسعار ونموذج 0% عمولة، ويحفظ بياناتهم فوراً في CRM.
                                    </div>
                                </div>
                            </div>
                            <button
                                @click="togglePlatformAi"
                                type="button"
                                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition shadow-xs cursor-pointer"
                                :class="platformWaAiEnabled
                                    ? 'bg-emerald-600 text-white hover:bg-emerald-500'
                                    : 'bg-stone-200 dark:bg-slate-700 text-stone-700 dark:text-stone-300 hover:bg-stone-300'"
                            >
                                <span>{{ platformWaAiEnabled ? 'المساعد الذكي: مفعّل (ON) 🟢' : 'المساعد الذكي: متوقف (OFF) ⚪' }}</span>
                            </button>
                        </div>

                        <div class="rounded-2xl bg-gradient-to-br from-indigo-50/70 via-purple-50/40 to-white dark:from-indigo-950/20 dark:via-purple-950/10 dark:to-slate-900 border border-indigo-200/80 dark:border-indigo-800/40 shadow-xs overflow-hidden transition-all">
                            <div class="p-4 sm:p-5 border-b border-indigo-100 dark:border-indigo-900/60 flex items-center justify-between flex-wrap gap-3">
                                <div class="flex items-center gap-3">
                                    <span class="text-2xl">🧠</span>
                                    <div>
                                        <h4 class="text-sm font-black text-indigo-950 dark:text-indigo-200 flex items-center gap-2">
                                            <span>تلقين وتوجيه المساعد الذكي المخصص (Custom AI Knowledge & Training)</span>
                                            <span class="text-[10px] px-2 py-0.5 rounded-full bg-indigo-100 dark:bg-indigo-900 text-indigo-700 dark:text-indigo-300 font-bold">يُحقن فورياً في Gemini</span>
                                        </h4>
                                        <p class="text-xs text-indigo-800/80 dark:text-indigo-300/80 mt-0.5">
                                            تحديد أسعار الباقات، عروض التدشين، وشروط التعاقد التي يلتزم بها المساعد التنفيذي عند الرد.
                                        </p>
                                    </div>
                                </div>

                                <div v-if="aiInstructionsSavedSuccess" class="text-xs font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-100 dark:bg-emerald-950/80 px-3 py-1 rounded-lg border border-emerald-300 dark:border-emerald-800 flex items-center gap-1 animate-pulse">
                                    <span>✓</span>
                                    <span>تم الحفظ وتحديث عقل المساعد بنجاح!</span>
                                </div>
                            </div>

                            <div class="p-5 space-y-3">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="text-[11px] font-bold text-gray-500 dark:text-gray-400">إدراج نموذج سريع:</span>
                                    <button
                                        type="button"
                                        @click="insertInstructionTemplate('- باقة الأساسي (Basic): 50$ شهرياً (منيو QR + شاشات مطبخ + كاشير).\n- باقة المحترفين (Pro): 100$ شهرياً (تشمل الويتر الذكي، عجلة الحظ، وبوابة الواتساب الذكية).\n- باقة المؤسسات (Enterprise): مخصصة للسلاسل الكبيرة بحسب عدد الفروع.')"
                                        class="text-[11px] font-bold px-2.5 py-1 rounded-lg bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 text-gray-700 dark:text-gray-300 hover:border-indigo-400 hover:text-indigo-600 transition cursor-pointer"
                                    >
                                        + أسعار الباقات
                                    </button>
                                    <button
                                        type="button"
                                        @click="insertInstructionTemplate('- لدينا حالياً عرض خاص لأول 10 مطاعم تسجل هذا الشهر: تركيب وتدريب ميداني مجاني بالكامل مع إعفاء من رسوم الإعداد.')"
                                        class="text-[11px] font-bold px-2.5 py-1 rounded-lg bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 text-gray-700 dark:text-gray-300 hover:border-indigo-400 hover:text-indigo-600 transition cursor-pointer"
                                    >
                                        + عرض ترويجي
                                    </button>
                                    <button
                                        type="button"
                                        @click="insertInstructionTemplate('- اطلب دائماً من صاحب المطعم معرفة اسم مطعمه الحالي والمدينة لمساعدته بدقة.\n- ركّز دائماً على أن المنصة تلغي عمولة تطبيقات التوصيل (0% عمولة) وتحفظ أرباح المبيعات كاملة لصاحب المطعم.')"
                                        class="text-[11px] font-bold px-2.5 py-1 rounded-lg bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 text-gray-700 dark:text-gray-300 hover:border-indigo-400 hover:text-indigo-600 transition cursor-pointer"
                                    >
                                        + أسلوب الرد وقواعد التركيز
                                    </button>
                                </div>

                                <textarea
                                    v-model="platformAiCustomInstructions"
                                    rows="5"
                                    placeholder="اكتب هنا أي تعليمات مخصصة، أسعار محددة، عروض جديدة، أو تفاصيل ترغب بتلقينها للمساعد الذكي..."
                                    class="w-full text-xs font-normal leading-relaxed rounded-xl border border-indigo-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-gray-900 dark:text-white p-3.5 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition shadow-inner"
                                ></textarea>

                                <div class="flex items-center justify-between flex-wrap gap-2 pt-1">
                                    <span class="text-[11px] text-gray-500 dark:text-gray-400">
                                        💡 أي تعديل تكتبه هنا يتم أخذه بعين الاعتبار في نفس اللحظة بكل ردود الواتساب والموقع دون الحاجة لإعادة تشغيل.
                                    </span>
                                    <button
                                        type="button"
                                        @click="saveAiInstructions"
                                        :disabled="savingAiInstructions"
                                        class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-xl text-xs font-bold bg-indigo-600 hover:bg-indigo-700 text-white shadow-md shadow-indigo-600/20 transition cursor-pointer disabled:opacity-50"
                                    >
                                        <span v-if="savingAiInstructions" class="animate-spin text-sm">⏳</span>
                                        <span v-else>💾</span>
                                        <span>{{ savingAiInstructions ? 'جارٍ الحفظ وتحديث العقل...' : 'حفظ وتحديث عقل المساعد فوراً' }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-show="activeAiTab === 'whatsapp'" class="space-y-4">
                        <div class="p-5 rounded-2xl bg-stone-50 dark:bg-slate-900/60 border border-stone-200 dark:border-slate-800 flex items-center justify-between flex-wrap gap-4">
                            <div>
                                <h4 class="font-bold text-stone-900 dark:text-white text-sm">
                                    حالة جلسة الواتساب لجهاز المنصة الرسمي
                                </h4>
                                <p class="text-xs text-stone-500 dark:text-slate-400 mt-1">
                                    الربط يتيح للمنصة الرد المباشر وإرسال التنبيهات والإشعارات دون الاعتماد على تطبيقات طرف ثالث.
                                </p>
                            </div>

                            <div class="flex items-center gap-2">
                                <button
                                    v-if="platformWaStatus !== 'connected'"
                                    @click="connectPlatformWa"
                                    :disabled="platformWaLoading"
                                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-bold text-sm bg-emerald-600 hover:bg-emerald-700 text-white transition-all shadow-sm focus:ring-2 focus:ring-offset-2 disabled:opacity-50 cursor-pointer"
                                >
                                    <span v-if="platformWaLoading">جارٍ التوليد...</span>
                                    <span v-else>📲 مسح رمز الاستجابة (QR Code) للربط</span>
                                </button>
                                <button
                                    v-else
                                    @click="disconnectPlatformWa"
                                    :disabled="platformWaLoading"
                                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl font-bold text-xs bg-rose-50 hover:bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400 border border-rose-200 dark:border-rose-800 transition cursor-pointer"
                                >
                                    <span>فصل الجلسة</span>
                                </button>
                            </div>
                        </div>

                        <div v-if="platformWaStatus === 'scan_qr'" class="text-center py-6 bg-white dark:bg-slate-900 rounded-2xl border border-stone-200 dark:border-slate-800">
                            <p class="text-sm font-bold text-stone-800 dark:text-white mb-4">
                                امسح رمز الاستجابة السريعة (QR) من تطبيق واتساب في هاتفك المخصص للمنصة
                            </p>
                            <div v-if="platformWaQr" class="inline-block p-4 bg-white rounded-2xl shadow-lg border border-stone-200">
                                <img :src="platformWaQr" alt="Platform WhatsApp QR Code" class="w-60 h-60 mx-auto object-contain" />
                            </div>
                            <div class="mt-5 max-w-sm mx-auto text-xs text-stone-600 dark:text-slate-400 space-y-1.5 text-right bg-stone-50 dark:bg-slate-800 p-4 rounded-xl border border-stone-200 dark:border-slate-700">
                                <div class="font-bold text-stone-900 dark:text-white mb-1">خطوات الربط لمرة واحدة:</div>
                                <div>1. افتح تطبيق واتساب على هاتفك المخصص للمنصة.</div>
                                <div>2. اضغط على خيارات (الثلاث نقاط) ⬅️ <strong>«الأجهزة المرتبطة»</strong>.</div>
                                <div>3. اضغط <strong>«ربط جهاز»</strong> ووجّه الكاميرا نحو الباركود أعلاه.</div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="p-4 rounded-2xl bg-stone-50 dark:bg-slate-900/40 border border-stone-200 dark:border-slate-800 flex flex-col justify-between">
                                <div>
                                    <div class="text-xs font-bold text-stone-800 dark:text-white mb-2">تجربة إرسال رسالة فحص من رقم المنصة:</div>
                                    <div class="flex items-center gap-2">
                                        <input
                                            v-model="platformTestPhone"
                                            type="text"
                                            placeholder="رقم الهاتف (مثال: 0933123456)"
                                            class="flex-1 rounded-xl text-xs border border-stone-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-stone-800 dark:text-white"
                                        />
                                        <button
                                            @click="sendPlatformTestMsg"
                                            :disabled="platformTestLoading || !platformTestPhone.trim()"
                                            type="button"
                                            class="px-3 py-2 rounded-xl text-xs font-bold bg-stone-900 hover:bg-stone-800 text-white dark:bg-slate-700 dark:hover:bg-slate-600 transition disabled:opacity-50 cursor-pointer"
                                        >
                                            {{ platformTestLoading ? 'جارٍ الإرسال...' : 'إرسال فحص' }}
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="p-4 rounded-2xl bg-amber-50/60 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-800/40 flex items-center justify-between">
                                <div>
                                    <div class="text-xs font-bold text-amber-900 dark:text-amber-200">سجل أصحاب المطاعم والعملاء المحتملين:</div>
                                    <div class="text-xs text-amber-700 dark:text-amber-400 mt-1">كل من يراسل واتساب المنصة يُسجل فوراً في CRM للمتابعة.</div>
                                </div>
                                <Link
                                    href="/super-admin/leads"
                                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold bg-amber-600 hover:bg-amber-500 text-white transition shadow-xs"
                                >
                                    <span>عرض العملاء</span>
                                    <span>↗</span>
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </transition>
        </div>
    </div>
</template>
