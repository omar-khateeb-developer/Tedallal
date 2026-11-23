<script setup lang="ts">
import AdminNav from '@/Components/Admin/AdminNav.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
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

async function load() {
    const { data } = await axios.get('/api/admin/restaurant');
    restaurant.value = data.data;
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
