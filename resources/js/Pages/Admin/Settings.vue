<script setup lang="ts">
import AdminNav from '@/Components/Admin/AdminNav.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, Link } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';
import axios from 'axios';

const restaurant = ref<{
    name: string;
    type?: string;
    windows_count?: number;
    phone: string | null;
    address: string | null;
    currency: string;
    auto_print_on_ready?: boolean;
} | null>(null);

const form = ref({
    name: '',
    phone: '',
    address: '',
    currency: 'SYP',
    windows_count: 1,
    auto_print_on_ready: false,
    navbar_image: null as File | null,
    logo: null as File | null,
    accounting_mapping: {
        cash_account_code: '10101',
        cash_account_name: 'الصندوق الرئيسي',
        dine_in_sales_code: '40101',
        dine_in_sales_name: 'إيراد مبيعات الصالة',
        takeaway_sales_code: '40102',
        takeaway_sales_name: 'إيراد مبيعات سفري',
        discount_account_code: '40201',
        discount_account_name: 'حسميات وعروض ترويجية',
        tax_account_code: '20301',
        tax_account_name: 'أمانات الضرائب والرسوم',
    },
});

const saving = ref(false);
const message = ref('');

// Clean Slate / Launchpad Reset States
const showResetModal = ref(false);
const resetForm = ref({
    password: '',
    confirmation: '',
});
const resetting = ref(false);
const resetError = ref<string | null>(null);
const resetSuccessMessage = ref<string | null>(null);

async function submitResetTestData() {
    if (resetForm.value.confirmation !== 'تصفير بيانات التجارب') {
        resetError.value = 'يرجى كتابة عبارة التأكيد بدقة: «تصفير بيانات التجارب»';
        return;
    }
    if (!resetForm.value.password) {
        resetError.value = 'يرجى إدخال كلمة مرور حسابك لتأكيد العملية';
        return;
    }

    resetting.value = true;
    resetError.value = null;

    try {
        const res = await axios.post('/api/admin/restaurant/reset-test-data', resetForm.value);
        resetSuccessMessage.value = res.data.data?.message || 'تم تصفير بيانات التجارب بنجاح ✓';
        showResetModal.value = false;
        resetForm.value = { password: '', confirmation: '' };
    } catch (e: any) {
        resetError.value = e.response?.data?.message || 'تعذر التصفير. يرجى التحقق من كلمة المرور.';
    } finally {
        resetting.value = false;
    }
}

// ==========================================
// بوابة واتساب المطعم والأتمتة الذكية
// ==========================================
const waStatus = ref<'disconnected' | 'connecting' | 'scan_qr' | 'connected'>('disconnected');
const waQrCode = ref<string | null>(null);
const waPhone = ref<string | null>(null);
const waConnectedAt = ref<string | null>(null);
const waLoading = ref(false);
const waFeatureLocked = ref(false);
const waFeatureLockedMsg = ref('');
const waAutoReplyEnabled = ref(false);
const waAutoReplyMessage = ref('');
const waExpiringRemindersEnabled = ref(true);
const hasAiReplyFeature = ref(false);
const waSavingSettings = ref(false);
const waSettingsNotice = ref<string | null>(null);

const waTestPhone = ref('');
const waSendingTest = ref(false);
const waTestNotice = ref<string | null>(null);
const waTestError = ref<string | null>(null);

let waPollingTimer: ReturnType<typeof setInterval> | null = null;

async function fetchWhatsappStatus() {
    try {
        const res = await axios.get('/api/admin/whatsapp/status');
        const data = res.data.data;
        waFeatureLocked.value = false;
        waStatus.value = data.status || 'disconnected';
        waQrCode.value = data.qr_code || null;
        waPhone.value = data.phone || null;
        waConnectedAt.value = data.connected_at || null;
        hasAiReplyFeature.value = Boolean(data.has_ai_reply_feature);
        waAutoReplyEnabled.value = Boolean(data.auto_reply_enabled);
        waAutoReplyMessage.value = data.auto_reply_message || '';
        waExpiringRemindersEnabled.value = data.expiring_reminders_enabled !== false;

        if (waStatus.value === 'scan_qr' || waStatus.value === 'connecting') {
            startWaPolling();
        } else {
            stopWaPolling();
        }
    } catch (e: any) {
        if (e.response?.status === 403 && e.response?.data?.feature_locked) {
            waFeatureLocked.value = true;
            waFeatureLockedMsg.value = e.response.data.message || 'ميزة بوابة الواتساب متاحة للباقة الشاملة (Max).';
        }
    }
}

function startWaPolling() {
    if (waPollingTimer) return;
    waPollingTimer = setInterval(async () => {
        try {
            const res = await axios.get('/api/admin/whatsapp/status');
            const data = res.data.data;
            waStatus.value = data.status;
            waQrCode.value = data.qr_code;
            waPhone.value = data.phone;
            waConnectedAt.value = data.connected_at;
            if (data.status === 'connected') {
                stopWaPolling();
            }
        } catch (_) {}
    }, 3000);
}

function stopWaPolling() {
    if (waPollingTimer) {
        clearInterval(waPollingTimer);
        waPollingTimer = null;
    }
}

async function connectWhatsapp() {
    waLoading.value = true;
    try {
        const res = await axios.post('/api/admin/whatsapp/connect');
        const data = res.data.data;
        waStatus.value = data.status;
        waQrCode.value = data.qr_code;
        waPhone.value = data.phone;
        waConnectedAt.value = data.connected_at;
        startWaPolling();
    } catch (e: any) {
        alert(e.response?.data?.message || 'تعذر بدء جلسة الواتساب');
    } finally {
        waLoading.value = false;
    }
}

async function disconnectWhatsapp() {
    if (!confirm('هل أنت متأكد من فصل رقم واتساب المطعم؟ لن يتم إرسال الرسائل والتذكيرات تلقائياً حتى تعيد ربطه.')) return;
    waLoading.value = true;
    try {
        await axios.post('/api/admin/whatsapp/disconnect');
        waStatus.value = 'disconnected';
        waQrCode.value = null;
        waPhone.value = null;
        waConnectedAt.value = null;
        stopWaPolling();
    } catch (e: any) {
        alert(e.response?.data?.message || 'تعذر فصل الجلسة');
    } finally {
        waLoading.value = false;
    }
}

async function saveWhatsappSettings() {
    waSavingSettings.value = true;
    waSettingsNotice.value = null;
    try {
        await axios.post('/api/admin/whatsapp/settings', {
            whatsapp_auto_reply_enabled: waAutoReplyEnabled.value,
            whatsapp_auto_reply_message: waAutoReplyMessage.value,
            whatsapp_expiring_reminders_enabled: waExpiringRemindersEnabled.value,
        });
        waSettingsNotice.value = 'تم حفظ إعدادات الأتمتة بنجاح ✓';
        setTimeout(() => { waSettingsNotice.value = null; }, 3500);
    } catch (e: any) {
        alert(e.response?.data?.message || 'تعذر حفظ إعدادات الواتساب');
    } finally {
        waSavingSettings.value = false;
    }
}

async function sendTestMessage() {
    if (!waTestPhone.value) return;
    waSendingTest.value = true;
    waTestNotice.value = null;
    waTestError.value = null;
    try {
        const res = await axios.post('/api/admin/whatsapp/send-test', {
            phone: waTestPhone.value,
        });
        waTestNotice.value = res.data.message || 'تم إرسال الرسالة التجريبية بنجاح إلى هاتفك ✓';
        setTimeout(() => { waTestNotice.value = null; }, 5000);
    } catch (e: any) {
        waTestError.value = e.response?.data?.message || 'تعذر إرسال الرسالة التجريبية. تحقق من الرقم وحالة الاتصال.';
        setTimeout(() => { waTestError.value = null; }, 6000);
    } finally {
        waSendingTest.value = false;
    }
}

async function load() {
    const { data } = await axios.get('/api/admin/restaurant');
    restaurant.value = data.data;
    await fetchWhatsappStatus();
    form.value = {
        name: data.data.name,
        phone: data.data.phone ?? '',
        address: data.data.address ?? '',
        currency: data.data.currency,
        windows_count: data.data.windows_count ?? 1,
        auto_print_on_ready: !!data.data.auto_print_on_ready,
        navbar_image: null,
        logo: null,
        accounting_mapping: {
            cash_account_code: data.data.accounting_mapping?.cash_account_code || '10101',
            cash_account_name: data.data.accounting_mapping?.cash_account_name || 'الصندوق الرئيسي',
            dine_in_sales_code: data.data.accounting_mapping?.dine_in_sales_code || '40101',
            dine_in_sales_name: data.data.accounting_mapping?.dine_in_sales_name || 'إيراد مبيعات الصالة',
            takeaway_sales_code: data.data.accounting_mapping?.takeaway_sales_code || '40102',
            takeaway_sales_name: data.data.accounting_mapping?.takeaway_sales_name || 'إيراد مبيعات سفري',
            discount_account_code: data.data.accounting_mapping?.discount_account_code || '40201',
            discount_account_name: data.data.accounting_mapping?.discount_account_name || 'حسميات وعروض ترويجية',
            tax_account_code: data.data.accounting_mapping?.tax_account_code || '20301',
            tax_account_name: data.data.accounting_mapping?.tax_account_name || 'أمانات الضرائب والرسوم',
        },
    };
}

async function save() {
    saving.value = true;
    message.value = '';
    try {
        const formData = new FormData();
        formData.append('_method', 'PATCH');
        formData.append('name', form.value.name);
        formData.append('phone', form.value.phone);
        formData.append('address', form.value.address);
        formData.append('currency', form.value.currency);
        formData.append('auto_print_on_ready', form.value.auto_print_on_ready ? '1' : '0');
        if (restaurant.value?.type === 'prepaid') {
            formData.append('windows_count', form.value.windows_count.toString());
        }
        if (form.value.navbar_image) {
            formData.append('navbar_image', form.value.navbar_image);
        }
        if (form.value.logo) {
            formData.append('logo', form.value.logo);
        }

        // إرسال خريطة الحسابات لبرامج المحاسبة (Accounting Bridge)
        const acc = form.value.accounting_mapping;
        formData.append('accounting_mapping[cash_account_code]', acc.cash_account_code);
        formData.append('accounting_mapping[cash_account_name]', acc.cash_account_name);
        formData.append('accounting_mapping[dine_in_sales_code]', acc.dine_in_sales_code);
        formData.append('accounting_mapping[dine_in_sales_name]', acc.dine_in_sales_name);
        formData.append('accounting_mapping[takeaway_sales_code]', acc.takeaway_sales_code);
        formData.append('accounting_mapping[takeaway_sales_name]', acc.takeaway_sales_name);
        formData.append('accounting_mapping[discount_account_code]', acc.discount_account_code);
        formData.append('accounting_mapping[discount_account_name]', acc.discount_account_name);
        formData.append('accounting_mapping[tax_account_code]', acc.tax_account_code);
        formData.append('accounting_mapping[tax_account_name]', acc.tax_account_name);

        const { data } = await axios.post('/api/admin/restaurant', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        restaurant.value = data.data;
        message.value = 'تم حفظ الإعدادات بنجاح';
        
        // Reload inertia props to update the navbar logo immediately
        router.reload({ only: ['auth'] });
    } catch (error: any) {
        console.error('Settings save error:', error);
        if (error.response?.status === 413) {
            message.value = 'حجم الملف كبير جداً. يرجى اختيار ملف أصغر.';
        } else if (error.response?.data?.errors) {
            const firstError = Object.values(error.response.data.errors)[0] as string[];
            message.value = firstError[0];
        } else if (error.response?.data?.message) {
            message.value = error.response.data.message;
        } else if (error.response?.statusText) {
            message.value = `تعذر حفظ الإعدادات: ${error.response.status} - ${error.response.statusText}`;
        } else if (error.message) {
            message.value = `تعذر حفظ الإعدادات: ${error.message}`;
        } else {
            message.value = 'تعذر حفظ الإعدادات';
        }
    } finally {
        saving.value = false;
    }
}

async function compressImage(file: File, maxWidth = 1200): Promise<File> {
    return new Promise((resolve) => {
        const reader = new FileReader();
        reader.readAsDataURL(file);
        reader.onload = (event) => {
            const img = new Image();
            img.src = event.target?.result as string;
            img.onload = () => {
                let width = img.width;
                let height = img.height;
                
                if (width > maxWidth) {
                    height = Math.round((height * maxWidth) / width);
                    width = maxWidth;
                }
                
                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;
                const ctx = canvas.getContext('2d');
                if (!ctx) return resolve(file);
                
                ctx.drawImage(img, 0, 0, width, height);
                
                canvas.toBlob((blob) => {
                    if (blob) {
                        resolve(new File([blob], file.name, { type: file.type, lastModified: Date.now() }));
                    } else {
                        resolve(file);
                    }
                }, file.type, 0.8);
            };
            img.onerror = () => resolve(file);
        };
        reader.onerror = () => resolve(file);
    });
}

async function handleFileUpload(event: Event, field: 'logo' | 'navbar_image') {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        const file = target.files[0];
        if (file.type.startsWith('image/')) {
            form.value[field] = await compressImage(file);
        } else {
            form.value[field] = file;
        }
    }
}

onMounted(load);
onUnmounted(stopWaPolling);
</script>

<template>
    <Head title="إعدادات المطعم" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800 dark:text-white">إعدادات المطعم</h2>
        </template>

        <div class="mx-auto max-w-2xl p-6" dir="rtl">
            <AdminNav />

            <form v-if="restaurant" class="space-y-4 rounded-2xl bg-white dark:bg-[#0a0f1d] p-6 shadow-xs border border-slate-100 dark:border-slate-800" @submit.prevent="save">
                <!-- بطاقة توضيح نوع نظام المطعم -->
                <div v-if="restaurant.type === 'prepaid'" class="rounded-xl bg-amber-50/80 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 p-4">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="text-xl">⚡</span>
                        <div>
                            <p class="font-extrabold text-sm text-amber-900 dark:text-amber-200">نظام المطعم: دفع مسبق (كاشير ونوافذ تسليم)</p>
                            <p class="text-xs text-amber-700 dark:text-amber-400 mt-0.5">يحاسب الزبون عند الكاشير ثم يستلم وجبته من إحدى نوافذ التحضير.</p>
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-bold text-stone-700 dark:text-stone-300">عدد نوافذ التحضير والاستلام المتاحة</label>
                        <input
                            v-model.number="form.windows_count"
                            type="number"
                            min="1"
                            max="50"
                            required
                            class="w-full rounded-lg border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-stone-900 dark:text-white font-bold focus:border-amber-500 focus:ring-amber-500"
                        />
                        <p class="text-[11px] text-stone-500 dark:text-slate-400 mt-1">تظهر هذه النوافذ في لوحة المطبخ (KDS) وفي شاشة تتبع الطلب لدى الزبائن.</p>
                    </div>
                </div>

                <div v-else class="rounded-xl bg-stone-50 dark:bg-slate-900 border border-stone-200 dark:border-slate-800 p-3.5 flex items-center gap-2.5">
                    <span class="text-xl">🍽️</span>
                    <div>
                        <p class="font-bold text-xs text-stone-800 dark:text-stone-200">نظام المطعم: دفع لاحق (طاولات وصالة)</p>
                        <p class="text-[11px] text-stone-500 dark:text-slate-400">يصل الطلب مباشرة للمطبخ ويتم المحاسبة بعد الانتهاء.</p>
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-stone-600 dark:text-stone-300">اسم المطعم</label>
                    <input v-model="form.name" required class="w-full rounded-lg border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-stone-900 dark:text-white focus:border-amber-500 focus:ring-amber-500" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-stone-600 dark:text-stone-300">الهاتف</label>
                    <input v-model="form.phone" class="w-full rounded-lg border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-stone-900 dark:text-white focus:border-amber-500 focus:ring-amber-500" dir="ltr" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-stone-600 dark:text-stone-300">العنوان</label>
                    <textarea v-model="form.address" rows="3" class="w-full rounded-lg border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-stone-900 dark:text-white focus:border-amber-500 focus:ring-amber-500" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-stone-600 dark:text-stone-300">العملة (ISO 4217)</label>
                    <select v-model="form.currency" required class="w-full rounded-lg border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-stone-900 dark:text-white focus:border-amber-500 focus:ring-amber-500" dir="ltr">
                        <option value="SYP">SYP - ليرة سورية</option>
                        <option value="SAR">SAR - ريال سعودي</option>
                        <option value="AED">AED - درهم إماراتي</option>
                        <option value="KWD">KWD - دينار كويتي</option>
                        <option value="QAR">QAR - ريال قطري</option>
                        <option value="BHD">BHD - دينار بحريني</option>
                        <option value="OMR">OMR - ريال عماني</option>
                        <option value="EGP">EGP - جنيه مصري</option>
                        <option value="JOD">JOD - دينار أردني</option>
                        <option value="USD">USD - دولار أمريكي</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-stone-600 dark:text-stone-300">لوغو المطعم (اختياري)</label>
                    <input type="file" @change="e => handleFileUpload(e, 'logo')" accept="image/*" class="w-full rounded-lg border border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-stone-700 dark:text-stone-300 text-sm file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-amber-50 dark:file:bg-amber-950/60 file:text-amber-700 dark:file:text-amber-400 hover:file:bg-amber-100 dark:hover:file:bg-amber-900/60" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-stone-600 dark:text-stone-300">صورة الـ NavBar المخصصة (اختياري)</label>
                    <input type="file" @change="e => handleFileUpload(e, 'navbar_image')" accept="image/*" class="w-full rounded-lg border border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-stone-700 dark:text-stone-300 text-sm file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-amber-50 dark:file:bg-amber-950/60 file:text-amber-700 dark:file:text-amber-400 hover:file:bg-amber-100 dark:hover:file:bg-amber-900/60" />
                </div>

                <!-- إعدادات الطباعة والتشغيل -->
                <div class="rounded-xl border border-stone-200 dark:border-slate-800 bg-stone-50/70 dark:bg-slate-900/60 p-4 space-y-3">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">🖨️</span>
                        <div>
                            <h4 class="font-bold text-sm text-stone-800 dark:text-stone-200">إعدادات الطباعة والتشغيل</h4>
                            <p class="text-xs text-stone-500 dark:text-slate-400">تخصيص سلوك الطابعة الحرارية وتجهيز الفواتير في الصالة والمطبخ</p>
                        </div>
                    </div>

                    <label class="flex items-start gap-3 cursor-pointer p-3 rounded-lg bg-white dark:bg-slate-800 border border-stone-200 dark:border-slate-700 hover:border-amber-400 transition-colors">
                        <input
                            type="checkbox"
                            v-model="form.auto_print_on_ready"
                            class="mt-1 h-4 w-4 rounded border-stone-300 dark:border-slate-600 text-amber-600 focus:ring-amber-500"
                        />
                        <div class="select-none">
                            <span class="block text-sm font-bold text-stone-800 dark:text-stone-100">الطباعة التلقائية عند تم التحضير</span>
                            <span class="block text-xs text-stone-500 dark:text-slate-400 mt-0.5">
                                إرسال الفاتورة الحرارية للطابعة تلقائياً فور تحويل الطلب إلى «تم التحضير» (جاهز)، لتوضع الفاتورة فوراً مع الصينية أو كيس الطلب قبل التسليم.
                            </span>
                        </div>
                    </label>
                </div>

                <!-- بطاقة بوابة واتساب المطعم والأتمتة الذكية (WhatsApp Integration) -->
                <div id="whatsapp" class="rounded-2xl border border-emerald-200 dark:border-emerald-800/60 bg-gradient-to-b from-emerald-50/50 via-white to-white dark:from-emerald-950/20 dark:via-[#0a0f1d] dark:to-[#0a0f1d] p-5 sm:p-6 space-y-5 transition-all shadow-2xs">
                    <!-- رأس البطاقة -->
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-emerald-100 dark:border-emerald-800/40 pb-4">
                        <div class="flex items-center gap-3">
                            <span class="text-2xl p-2 rounded-xl bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300">💬</span>
                            <div>
                                <h4 class="font-black text-sm text-stone-900 dark:text-white flex items-center gap-2">
                                    <span>بوابة واتساب المطعم والأتمتة الذكية</span>
                                    <span class="text-[10px] px-2 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-900 text-emerald-800 dark:text-emerald-300 font-bold border border-emerald-300 dark:border-emerald-700">WhatsApp Official Gateway</span>
                                </h4>
                                <p class="text-xs text-stone-600 dark:text-slate-400 mt-0.5 leading-relaxed">
                                    اربط رقم واتساب مطعمك لإرسال كوبونات عجلة الحظ، وتذكيرات الزبائن بالخصومات تلقائياً من رقمك الرسمي.
                                </p>
                            </div>
                        </div>

                        <!-- شارة الحالة الحالية -->
                        <div>
                            <span
                                v-if="waStatus === 'connected'"
                                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/80 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700"
                            >
                                <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                <span>متصل وجاهز</span>
                            </span>
                            <span
                                v-else-if="waStatus === 'scan_qr' || waStatus === 'connecting'"
                                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 dark:bg-amber-950/80 dark:text-amber-300 border border-amber-300 dark:border-amber-700"
                            >
                                <span class="animate-spin text-xs">⏳</span>
                                <span>بانتظار مسح الرمز</span>
                            </span>
                            <span
                                v-else
                                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-rose-100 text-rose-800 dark:bg-rose-950/80 dark:text-rose-300 border border-rose-300 dark:border-rose-700"
                            >
                                <span class="h-2 w-2 rounded-full bg-rose-500"></span>
                                <span>غير متصل</span>
                            </span>
                        </div>
                    </div>

                    <!-- في حال كانت الميزة مقفلة في باقة المطعم -->
                    <div v-if="waFeatureLocked" class="p-4 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 text-xs font-bold text-amber-800 dark:text-amber-300 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <span>🔒</span>
                            <span>{{ waFeatureLockedMsg }}</span>
                        </div>
                        <a href="https://wa.me/963988029051" target="_blank" class="px-3 py-1.5 rounded-lg bg-amber-600 text-white font-bold hover:bg-amber-700 transition">
                            ترقية الباقة الآن
                        </a>
                    </div>

                    <div v-else class="space-y-4">
                        <!-- الحالة 1: متصل -->
                        <div v-if="waStatus === 'connected'" class="space-y-4">
                            <div class="flex flex-wrap items-center justify-between gap-3 p-4 rounded-xl bg-emerald-50/80 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60">
                                <div>
                                    <p class="text-xs font-bold text-emerald-900 dark:text-emerald-200">
                                        رقم واتساب المطعم المرتبط حالياً:
                                    </p>
                                    <p class="text-base font-black text-emerald-700 dark:text-emerald-400 font-mono mt-0.5" dir="ltr">
                                        {{ waPhone ? (waPhone.startsWith('+') ? waPhone : '+' + waPhone) : 'متصل' }}
                                    </p>
                                    <p v-if="waConnectedAt" class="text-[11px] text-stone-500 dark:text-slate-400 mt-1">
                                        تاريخ الربط: {{ waConnectedAt }}
                                    </p>
                                </div>

                                <button
                                    @click="disconnectWhatsapp"
                                    :disabled="waLoading"
                                    type="button"
                                    class="px-3.5 py-2 rounded-xl text-xs font-bold bg-rose-50 hover:bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:hover:bg-rose-900/60 dark:text-rose-300 border border-rose-200 dark:border-rose-800/60 transition cursor-pointer"
                                >
                                    {{ waLoading ? 'جارٍ الفصل...' : '🔌 فصل الجلسة' }}
                                </button>
                            </div>

                            <!-- اختبار الإرسال السريع -->
                            <div class="p-3.5 rounded-xl bg-white dark:bg-slate-900/60 border border-stone-200 dark:border-slate-800 space-y-2">
                                <label class="block text-xs font-bold text-stone-700 dark:text-stone-300">
                                    تجربة الإرسال (أدخل رقم هاتفك لتأكيد وصول الرسائل):
                                </label>
                                <div class="flex gap-2">
                                    <input
                                        v-model="waTestPhone"
                                        type="tel"
                                        dir="ltr"
                                        placeholder="09XXXXXXXX"
                                        class="w-full rounded-xl text-xs font-mono border-stone-300 dark:border-slate-700 bg-stone-50 dark:bg-slate-800 text-stone-900 dark:text-white"
                                    />
                                    <button
                                        @click="sendTestMessage"
                                        :disabled="waSendingTest || !waTestPhone"
                                        type="button"
                                        class="px-4 py-2 rounded-xl text-xs font-bold bg-emerald-600 hover:bg-emerald-500 text-white shrink-0 transition disabled:opacity-50 cursor-pointer"
                                    >
                                        {{ waSendingTest ? 'جارٍ الإرسال...' : 'إرسال تجربة' }}
                                    </button>
                                </div>
                                <div v-if="waTestNotice" class="text-xs font-bold text-emerald-700 dark:text-emerald-400">
                                    {{ waTestNotice }}
                                </div>
                                <div v-if="waTestError" class="text-xs font-bold text-rose-600 dark:text-rose-400">
                                    {{ waTestError }}
                                </div>
                            </div>
                        </div>

                        <!-- الحالة 2: مسح رمز الـ QR -->
                        <div v-else-if="waStatus === 'scan_qr'" class="text-center space-y-4 p-4 rounded-xl bg-white dark:bg-slate-900 border border-stone-200 dark:border-slate-800">
                            <div class="text-xs font-bold text-amber-700 dark:text-amber-400 flex items-center justify-center gap-1.5">
                                <span class="animate-spin">⏳</span>
                                <span>امسح رمز الاستجابة السريعة (QR) من تطبيق واتساب في هاتفك</span>
                            </div>

                            <div v-if="waQrCode" class="inline-block p-3 rounded-2xl bg-white shadow-md border border-slate-200">
                                <img :src="waQrCode" alt="WhatsApp QR Code" class="w-52 h-52 mx-auto object-contain" />
                            </div>

                            <div class="max-w-xs mx-auto space-y-1.5">
                                <div class="flex items-center justify-between text-[11px] text-amber-600 dark:text-amber-400 font-bold px-1">
                                    <span>صلاحية الباركود</span>
                                    <span class="font-mono text-emerald-600 dark:text-emerald-400">نشط ومباشر 🟢</span>
                                </div>
                                <div class="h-1.5 w-full bg-slate-200 dark:bg-slate-800 rounded-full overflow-hidden">
                                    <div class="h-full bg-gradient-to-r from-amber-500 to-emerald-500 rounded-full animate-pulse w-full"></div>
                                </div>
                                <p class="text-[10px] text-slate-400">يتجدد الرمز تلقائياً لحماية أمان حسابك</p>
                            </div>

                            <div class="text-xs text-stone-600 dark:text-slate-400 max-w-sm mx-auto leading-relaxed text-right space-y-1 bg-stone-50 dark:bg-slate-800/80 p-3.5 rounded-xl border border-stone-200 dark:border-slate-700">
                                <div class="font-bold text-stone-900 dark:text-white mb-1.5">خطوات الربط في هاتفك:</div>
                                <div>1. افتح تطبيق واتساب على هاتفك.</div>
                                <div>2. الإعدادات ⚙️ أو القائمة ⋮ ➔ <strong>الأجهزة المرتبطة</strong>.</div>
                                <div>3. اضغط <strong>«ربط جهاز»</strong> ووجّه الكاميرا نحو الرمز أعلاه.</div>
                            </div>

                            <div>
                                <button
                                    @click="connectWhatsapp"
                                    :disabled="waLoading"
                                    type="button"
                                    class="px-4 py-2 rounded-xl text-xs font-bold bg-stone-100 dark:bg-slate-800 text-stone-700 dark:text-stone-300 hover:bg-stone-200 transition cursor-pointer"
                                >
                                    🔄 تحديث الرمز
                                </button>
                            </div>
                        </div>

                        <!-- الحالة 3: غير متصل -->
                        <div v-else class="text-center space-y-3 py-4 p-4 rounded-xl bg-white dark:bg-slate-900 border border-stone-200 dark:border-slate-800">
                            <div class="text-3xl">📱</div>
                            <h4 class="text-sm font-black text-stone-900 dark:text-white">واتساب المطعم غير مرتبط حالياً</h4>
                            <p class="text-xs text-stone-500 dark:text-slate-400 max-w-md mx-auto leading-relaxed">
                                اربط رقم هاتف المطعم عبر مسح الباركود لمرة واحدة فقط، لتبدأ كوبونات عجلة الحظ وتذكيرات الزبائن بالانطلاق تلقائياً في الخلفية من رقم مطعمك.
                            </p>
                            <button
                                @click="connectWhatsapp"
                                :disabled="waLoading"
                                type="button"
                                class="px-5 py-2.5 rounded-xl text-xs font-black bg-emerald-600 hover:bg-emerald-500 text-white shadow-md transition disabled:opacity-50 cursor-pointer"
                            >
                                {{ waLoading ? 'جارٍ توليد الرمز...' : '📲 ربط رقم واتساب المطعم (توليد رمز QR)' }}
                            </button>
                        </div>

                        <!-- إعدادات الأتمتة والرسائل التلقائية -->
                        <div class="p-4 rounded-xl bg-white dark:bg-slate-900/60 border border-stone-200 dark:border-slate-800 space-y-3.5">
                            <h5 class="text-xs font-black text-stone-900 dark:text-white uppercase tracking-wider flex items-center gap-1.5">
                                <span>⚙️</span>
                                <span>أتمتة الرسائل والتذكيرات</span>
                            </h5>

                            <!-- تنبيه انتهاء الكوبونات -->
                            <label class="flex items-start gap-3 cursor-pointer select-none">
                                <input
                                    type="checkbox"
                                    v-model="waExpiringRemindersEnabled"
                                    class="mt-1 h-4 w-4 rounded border-stone-300 dark:border-slate-600 text-emerald-600 focus:ring-emerald-500"
                                />
                                <div>
                                    <span class="block text-xs font-bold text-stone-800 dark:text-stone-200">
                                        إرسال تذكير تلقائي للزبائن قبل انتهاء صلاحية الكوبون بـ 24 ساعة
                                    </span>
                                    <span class="block text-[11px] text-stone-500 dark:text-slate-400 mt-0.5">
                                        يحفز الزبون على زيارة المطعم واستخدام كود الخصم قبل نفاد وقته تلقائياً دون أي تدخل يدوي منك.
                                    </span>
                                </div>
                            </label>

                            <!-- المجيب الآلي الذكي (يظهر فقط إذا تم تفعيله لمطعمك كترقية مخصصة من قِبل إدارة المنصة) -->
                            <div v-if="hasAiReplyFeature" class="pt-3 border-t border-stone-100 dark:border-slate-800">
                                <label class="flex items-start gap-3 cursor-pointer select-none">
                                    <input
                                        type="checkbox"
                                        v-model="waAutoReplyEnabled"
                                        class="mt-1 h-4 w-4 rounded border-stone-300 dark:border-slate-600 text-purple-600 focus:ring-purple-500"
                                    />
                                    <div>
                                        <span class="block text-xs font-bold text-stone-800 dark:text-stone-200 flex items-center gap-1.5">
                                            <span>⚡🤖</span>
                                            <span>تفعيل الرد الذكي على محادثات الزبائن (AI Auto-Reply)</span>
                                            <span class="text-[10px] px-2 py-0.5 rounded-full bg-purple-100 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 font-black">مفعل من المنصة</span>
                                        </span>
                                        <span class="block text-[11px] text-stone-500 dark:text-slate-400 mt-0.5">
                                            إرسال رسالة ترحيبية فورية تلقائية عندما يراسل الزبون رقم المطعم عبر واتساب.
                                        </span>
                                    </div>
                                </label>

                                <div v-if="waAutoReplyEnabled" class="pt-2">
                                    <label class="block text-xs font-bold text-stone-700 dark:text-stone-300 mb-1">نص رسالة الرد التلقائي:</label>
                                    <textarea
                                        v-model="waAutoReplyMessage"
                                        rows="2"
                                        placeholder="أهلاً بك في مطعمنا! يمكنك تصفح المنيو وطلب وجبتك مباشرة عبر الرابط..."
                                        class="w-full rounded-xl text-xs border-stone-300 dark:border-slate-700 bg-stone-50 dark:bg-slate-800 text-stone-900 dark:text-white"
                                    />
                                </div>
                            </div>

                            <div class="flex items-center justify-between pt-2">
                                <span v-if="waSettingsNotice" class="text-xs font-bold text-emerald-600 dark:text-emerald-400">
                                    {{ waSettingsNotice }}
                                </span>
                                <span v-else></span>
                                <button
                                    @click="saveWhatsappSettings"
                                    :disabled="waSavingSettings"
                                    type="button"
                                    class="px-4 py-2 rounded-xl text-xs font-bold bg-stone-800 hover:bg-stone-700 dark:bg-slate-800 dark:hover:bg-slate-700 text-white transition disabled:opacity-50 cursor-pointer"
                                >
                                    {{ waSavingSettings ? 'جارٍ الحفظ...' : '💾 حفظ إعدادات الأتمتة' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- بطاقة جسر الربط المحاسبي الذكي (Accounting ERP Bridge) -->
                <div class="rounded-2xl border border-amber-200/80 dark:border-amber-900/40 bg-gradient-to-b from-amber-50/50 via-white to-white dark:from-amber-950/20 dark:via-[#0a0f1d] dark:to-[#0a0f1d] p-5 space-y-4">
                    <div class="flex items-start gap-3">
                        <span class="text-2xl p-2 rounded-xl bg-amber-100 dark:bg-amber-950/70 border border-amber-200 dark:border-amber-800 text-amber-700 dark:text-amber-400">📑</span>
                        <div>
                            <h4 class="font-black text-sm text-stone-900 dark:text-white flex items-center gap-2">
                                <span>جسر الربط المحاسبي الذكي (Accounting ERP Bridge)</span>
                                <span class="text-[10px] px-2 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300 font-bold border border-emerald-300 dark:border-emerald-800">الأمين • البيان • أودو</span>
                            </h4>
                            <p class="text-xs text-stone-600 dark:text-slate-400 mt-1 leading-relaxed">
                                طابق رموز وأسماء الحسابات مع دليل الحسابات لديك في برنامج المحاسبة، ليتم إنشاء <strong>«سند القيد اليومي»</strong> متوازناً بنسبة خطأ 0% وجاهزاً للترحيل والنسخ بنقرة واحدة دون الحاجة لتغيير منظومة مطعمك الحالية.
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 pt-2">
                        <!-- حساب الصندوق (الطرف المدين) -->
                        <div class="p-3 rounded-xl bg-white dark:bg-slate-900/70 border border-stone-200 dark:border-slate-800 space-y-2">
                            <span class="text-xs font-black text-stone-800 dark:text-stone-200 flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                <span>حساب الصندوق الرئيسي (كاشير - مدين)</span>
                            </span>
                            <div class="grid grid-cols-3 gap-2">
                                <input v-model="form.accounting_mapping.cash_account_code" placeholder="رمز الحساب" class="col-span-1 rounded-lg border-stone-200 dark:border-slate-700 bg-stone-50 dark:bg-slate-800 text-xs font-mono font-bold text-center" dir="ltr" />
                                <input v-model="form.accounting_mapping.cash_account_name" placeholder="اسم الحساب" class="col-span-2 rounded-lg border-stone-200 dark:border-slate-700 bg-stone-50 dark:bg-slate-800 text-xs font-bold" />
                            </div>
                        </div>

                        <!-- حساب الخصومات والعروض (الطرف المدين) -->
                        <div class="p-3 rounded-xl bg-white dark:bg-slate-900/70 border border-stone-200 dark:border-slate-800 space-y-2">
                            <span class="text-xs font-black text-stone-800 dark:text-stone-200 flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                                <span>حساب الخصومات الترويجية (مدين)</span>
                            </span>
                            <div class="grid grid-cols-3 gap-2">
                                <input v-model="form.accounting_mapping.discount_account_code" placeholder="رمز الحساب" class="col-span-1 rounded-lg border-stone-200 dark:border-slate-700 bg-stone-50 dark:bg-slate-800 text-xs font-mono font-bold text-center" dir="ltr" />
                                <input v-model="form.accounting_mapping.discount_account_name" placeholder="اسم الحساب" class="col-span-2 rounded-lg border-stone-200 dark:border-slate-700 bg-stone-50 dark:bg-slate-800 text-xs font-bold" />
                            </div>
                        </div>

                        <!-- حساب مبيعات الصالة (الطرف الدائن) -->
                        <div class="p-3 rounded-xl bg-white dark:bg-slate-900/70 border border-stone-200 dark:border-slate-800 space-y-2">
                            <span class="text-xs font-black text-stone-800 dark:text-stone-200 flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                                <span>حساب إيراد مبيعات الصالة (دائن)</span>
                            </span>
                            <div class="grid grid-cols-3 gap-2">
                                <input v-model="form.accounting_mapping.dine_in_sales_code" placeholder="رمز الحساب" class="col-span-1 rounded-lg border-stone-200 dark:border-slate-700 bg-stone-50 dark:bg-slate-800 text-xs font-mono font-bold text-center" dir="ltr" />
                                <input v-model="form.accounting_mapping.dine_in_sales_name" placeholder="اسم الحساب" class="col-span-2 rounded-lg border-stone-200 dark:border-slate-700 bg-stone-50 dark:bg-slate-800 text-xs font-bold" />
                            </div>
                        </div>

                        <!-- حساب مبيعات السفري (الطرف الدائن) -->
                        <div class="p-3 rounded-xl bg-white dark:bg-slate-900/70 border border-stone-200 dark:border-slate-800 space-y-2">
                            <span class="text-xs font-black text-stone-800 dark:text-stone-200 flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                                <span>حساب إيراد مبيعات السفري (دائن)</span>
                            </span>
                            <div class="grid grid-cols-3 gap-2">
                                <input v-model="form.accounting_mapping.takeaway_sales_code" placeholder="رمز الحساب" class="col-span-1 rounded-lg border-stone-200 dark:border-slate-700 bg-stone-50 dark:bg-slate-800 text-xs font-mono font-bold text-center" dir="ltr" />
                                <input v-model="form.accounting_mapping.takeaway_sales_name" placeholder="اسم الحساب" class="col-span-2 rounded-lg border-stone-200 dark:border-slate-700 bg-stone-50 dark:bg-slate-800 text-xs font-bold" />
                            </div>
                        </div>
                    </div>
                </div>

                <p v-if="message" class="text-sm font-medium" :class="message.includes('نجاح') ? 'text-green-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
                    {{ message }}
                </p>

                <button
                    type="submit"
                    class="flex items-center justify-center gap-2 rounded-xl bg-amber-600 dark:bg-gradient-to-r dark:from-amber-500 dark:to-orange-500 px-6 py-3 font-medium text-white dark:text-slate-950 disabled:opacity-50 transition-all hover:bg-amber-700 dark:hover:from-amber-600 dark:hover:to-orange-600"
                    :disabled="saving"
                >
                    <span v-if="saving" class="inline-block h-5 w-5 border-2 border-white dark:border-slate-950 border-t-transparent rounded-full animate-spin"></span>
                    {{ saving ? 'جاري الحفظ...' : 'حفظ الإعدادات' }}
                </button>
            </form>

            <!-- Pilot Venue Launchpad / Clean Slate -->
            <div class="mt-8 rounded-2xl border border-rose-200 dark:border-rose-900/60 bg-rose-50/40 dark:bg-rose-950/20 p-5 space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span class="text-2xl">🚀</span>
                        <div>
                            <h4 class="font-black text-sm text-rose-900 dark:text-rose-200">منطقة الإطلاق الرسمي وتصفير بيانات التجارب (Pilot Venue Launchpad)</h4>
                            <p class="text-xs text-rose-800/80 dark:text-rose-300/80 mt-0.5">
                                هل انتهيت من تدريب طاقمك والتجارب الميدانية وجاهز للافتتاح الفعلي؟ يمكنك تصفير الطلبات التجريبية والجلسات لتبدأ سجلات الكاشير والمبيعات من الصفر.
                            </p>
                        </div>
                    </div>

                    <button
                        @click="showResetModal = true"
                        type="button"
                        class="px-4 py-2.5 rounded-xl text-xs font-black bg-rose-600 hover:bg-rose-700 text-white shadow-md transition cursor-pointer flex items-center gap-2"
                    >
                        <span>🧹</span>
                        <span>تصفير طلبات التجارب للبدء من الصفر</span>
                    </button>
                </div>

                <div v-if="resetSuccessMessage" class="p-3.5 rounded-xl bg-emerald-100 text-emerald-800 dark:bg-emerald-950/70 dark:text-emerald-300 text-xs font-bold border border-emerald-300 dark:border-emerald-700">
                    ✓ {{ resetSuccessMessage }}
                </div>
            </div>

            <!-- Safe Reset Confirmation Modal -->
            <div
                v-if="showResetModal"
                class="fixed inset-0 z-50 bg-black/75 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto"
                dir="rtl"
                @click.self="showResetModal = false"
            >
                <div class="bg-white dark:bg-[#0f172a] rounded-3xl max-w-lg w-full p-6 border border-rose-200 dark:border-rose-800/60 shadow-2xl space-y-5" @click.stop>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2.5 text-rose-600 dark:text-rose-400">
                            <span class="text-2xl">⚠️</span>
                            <h3 class="text-lg font-black text-slate-900 dark:text-white">تأكيد تصفير بيانات التجارب</h3>
                        </div>
                        <button
                            @click="showResetModal = false"
                            type="button"
                            class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white flex items-center justify-center font-bold text-sm cursor-pointer"
                        >
                            ✕
                        </button>
                    </div>

                    <!-- Safety Summary of What Stays and What Wipes -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        <div class="p-3 rounded-xl bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-800/40 text-rose-900 dark:text-rose-200 space-y-1">
                            <div class="font-bold flex items-center gap-1.5">
                                <span>🗑️</span>
                                <span>ما سيتم حذفه وتصفيره:</span>
                            </div>
                            <ul class="list-disc list-inside space-y-0.5 text-[11px] text-rose-800 dark:text-rose-300">
                                <li>جميع الطلبات السابقة</li>
                                <li>سجلات وتاريخ الحالات</li>
                                <li>جلسات الطاولات النشطة</li>
                                <li>رموز وتدويرات العجلة</li>
                                <li>قوائم الانتظار التجريبية</li>
                            </ul>
                        </div>

                        <div class="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800/40 text-emerald-900 dark:text-emerald-200 space-y-1">
                            <div class="font-bold flex items-center gap-1.5">
                                <span>🛡️</span>
                                <span>ما سيبقى محفوظاً بأمان 100%:</span>
                            </div>
                            <ul class="list-disc list-inside space-y-0.5 text-[11px] text-emerald-800 dark:text-emerald-300">
                                <li>قائمة الطعام والأصناف</li>
                                <li>التصنيفات والأسعار</li>
                                <li>الطاولات ورموز QR</li>
                                <li>حسابات الموظفين والصلاحيات</li>
                                <li>إعدادات المطعم والولاء</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Inputs -->
                    <div class="space-y-3 pt-1">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                اكتب العبارة التالية للتأكيد: <span class="font-mono text-rose-600 font-black">تصفير بيانات التجارب</span>
                            </label>
                            <input
                                v-model="resetForm.confirmation"
                                type="text"
                                placeholder="تصفير بيانات التجارب"
                                class="w-full rounded-xl text-xs border-slate-200 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                كلمة مرور حسابك لتأكيد الهوية:
                            </label>
                            <input
                                v-model="resetForm.password"
                                type="password"
                                placeholder="••••••••"
                                class="w-full rounded-xl text-xs border-slate-200 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                            />
                        </div>

                        <div v-if="resetError" class="p-3 rounded-xl bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 text-xs font-bold">
                            ⚠️ {{ resetError }}
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button
                            @click="showResetModal = false"
                            type="button"
                            class="px-4 py-2 rounded-xl text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 cursor-pointer"
                        >
                            إلغاء
                        </button>
                        <button
                            @click="submitResetTestData"
                            :disabled="resetting || resetForm.confirmation !== 'تصفير بيانات التجارب' || !resetForm.password"
                            type="button"
                            class="px-5 py-2 rounded-xl text-xs font-black bg-rose-600 text-white hover:bg-rose-700 disabled:opacity-50 cursor-pointer shadow-md"
                        >
                            {{ resetting ? 'جاري التصفير...' : 'تأكيد التصفير النهائي' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
