<script setup lang="ts">
import AdminNav from '@/Components/Admin/AdminNav.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import axios from 'axios';

const restaurant = ref<{
    name: string;
    phone: string | null;
    address: string | null;
    currency: string;
} | null>(null);

const form = ref({
    name: '',
    phone: '',
    address: '',
    currency: 'SAR',
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
            <h2 class="text-xl font-semibold text-gray-800">إعدادات المطعم</h2>
        </template>

        <div class="mx-auto max-w-2xl p-6" dir="rtl">
            <AdminNav />

            <form v-if="restaurant" class="space-y-4 rounded-2xl bg-white p-6 shadow" @submit.prevent="save">
                <div>
                    <label class="mb-1 block text-sm text-stone-600">اسم المطعم</label>
                    <input v-model="form.name" required class="w-full rounded-lg border-stone-300" />
                </div>
                <div>
                    <label class="mb-1 block text-sm text-stone-600">الهاتف</label>
                    <input v-model="form.phone" class="w-full rounded-lg border-stone-300" dir="ltr" />
                </div>
                <div>
                    <label class="mb-1 block text-sm text-stone-600">العنوان</label>
                    <textarea v-model="form.address" rows="3" class="w-full rounded-lg border-stone-300" />
                </div>
                <div>
                    <label class="mb-1 block text-sm text-stone-600">العملة (ISO 4217)</label>
                    <select v-model="form.currency" required class="w-full rounded-lg border-stone-300" dir="ltr">
                        <option value="SAR">SAR - ريال سعودي</option>
                        <option value="SYP">SYP - ليرة سورية</option>
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
                    <label class="mb-1 block text-sm text-stone-600">لوغو المطعم (اختياري)</label>
                    <input type="file" @change="e => handleFileUpload(e, 'logo')" accept="image/*" class="w-full rounded-lg border-stone-300 text-sm file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100" />
                </div>
                <div>
                    <label class="mb-1 block text-sm text-stone-600">صورة الـ NavBar المخصصة (اختياري)</label>
                    <input type="file" @change="e => handleFileUpload(e, 'navbar_image')" accept="image/*" class="w-full rounded-lg border-stone-300 text-sm file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100" />
                </div>

                <p v-if="message" class="text-sm" :class="message.includes('نجاح') ? 'text-green-600' : 'text-red-600'">
                    {{ message }}
                </p>

                <button
                    type="submit"
                    class="flex items-center justify-center gap-2 rounded-xl bg-amber-600 px-6 py-3 font-medium text-white disabled:opacity-50 transition-all hover:bg-amber-700"
                    :disabled="saving"
                >
                    <span v-if="saving" class="inline-block h-5 w-5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                    {{ saving ? 'جاري الحفظ...' : 'حفظ الإعدادات' }}
                </button>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
