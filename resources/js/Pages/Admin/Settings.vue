<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
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
    };
}

async function save() {
    saving.value = true;
    message.value = '';
    try {
        const { data } = await axios.patch('/api/admin/restaurant', form.value);
        restaurant.value = data.data;
        message.value = 'تم حفظ الإعدادات بنجاح';
    } catch {
        message.value = 'تعذر حفظ الإعدادات';
    } finally {
        saving.value = false;
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
            <Link :href="route('admin.dashboard')" class="mb-6 inline-block text-sm text-amber-700">
                ← العودة للوحة التحكم
            </Link>

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
                    <input v-model="form.currency" maxlength="3" required class="w-full rounded-lg border-stone-300 uppercase" dir="ltr" />
                </div>

                <p v-if="message" class="text-sm" :class="message.includes('نجاح') ? 'text-green-600' : 'text-red-600'">
                    {{ message }}
                </p>

                <button
                    type="submit"
                    class="rounded-xl bg-amber-600 px-6 py-3 font-medium text-white disabled:opacity-50"
                    :disabled="saving"
                >
                    {{ saving ? 'جاري الحفظ...' : 'حفظ الإعدادات' }}
                </button>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
