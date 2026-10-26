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
} | null>(null);

const form = ref({
    name: '',
    phone: '',
    address: '',
    currency: 'SYP',
    windows_count: 1,
    navbar_image: null as File | null,
    logo: null as File | null,
});

const saving = ref(false);
const message = ref('');

async function load() {
    const { data } = await axios.get('/api/admin/restaurant');
    restaurant.value = data.data;
    form.value = {
        name: data.data.name,
        phone: data.data.phone ?? '',
        address: data.data.address ?? '',
        currency: data.data.currency,
        windows_count: data.data.windows_count ?? 1,
        navbar_image: null,
        logo: null,
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
        if (restaurant.value?.type === 'prepaid') {
            formData.append('windows_count', form.value.windows_count.toString());
        }
        if (form.value.navbar_image) {
            formData.append('navbar_image', form.value.navbar_image);
        }
        if (form.value.logo) {
            formData.append('logo', form.value.logo);
        }

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
                            <p class="font-extrabold text-sm text-amber-900 dark:text-amber-200">نظام المطعم: دفع مسبق (كاشير وشبابيك تسليم)</p>
                            <p class="text-xs text-amber-700 dark:text-amber-400 mt-0.5">يحاسب الزبون عند الكاشير ثم يستلم وجبته من إحدى كواة التحضير.</p>
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-bold text-stone-700 dark:text-stone-300">عدد كواة وشبابيك التحضير المتاحة</label>
                        <input
                            v-model.number="form.windows_count"
                            type="number"
                            min="1"
                            max="50"
                            required
                            class="w-full rounded-lg border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-stone-900 dark:text-white font-bold focus:border-amber-500 focus:ring-amber-500"
                        />
                        <p class="text-[11px] text-stone-500 dark:text-slate-400 mt-1">تظهر هذه الكواة في لوحة المطبخ (KDS) وفي شاشة تتبع الطلب لدى الزبائن.</p>
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
        </div>
    </AuthenticatedLayout>
</template>
