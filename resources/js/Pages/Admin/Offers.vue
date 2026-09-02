<script setup lang="ts">
import AdminNav from '@/Components/Admin/AdminNav.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import axios from 'axios';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const isLocked = ref(false);
const loading = ref(true);
const offers = ref<any[]>([]);

const form = useForm({
    code: '',
    discount_type: 'percentage',
    discount_value: '',
    min_order_value: 0,
});

const loadOffers = async () => {
    try {
        const response = await axios.get('/api/admin/promo-codes');
        offers.value = response.data.data;
    } catch (e: any) {
        if (e.response?.status === 403) {
            isLocked.value = true;
        }
    } finally {
        loading.value = false;
    }
};

onMounted(loadOffers);

const createOffer = async () => {
    try {
        await axios.post('/api/admin/promo-codes', form.data());
        form.reset();
        await loadOffers();
    } catch (e: any) {
        alert(e.response?.data?.message || 'خطأ أثناء إضافة العرض');
    }
};

const toggleActive = async (offer: any) => {
    try {
        await axios.patch(`/api/admin/promo-codes/${offer.id}`, {
            ...offer,
            is_active: !offer.is_active,
        });
        await loadOffers();
    } catch (e) {
        alert('خطأ أثناء تحديث الحالة');
    }
};

const deleteOffer = async (id: number) => {
    if (!confirm('هل أنت متأكد من حذف هذا الكوبون؟')) return;
    try {
        await axios.delete(`/api/admin/promo-codes/${id}`);
        await loadOffers();
    } catch (e) {
        alert('خطأ أثناء الحذف');
    }
};
</script>

<template>
    <Head title="العروض والخصومات" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">العروض والكوبونات (نظام الولاء)</h2>
        </template>
        <div class="mx-auto max-w-5xl p-6" dir="rtl">
            <AdminNav />
            
            <div v-if="loading" class="text-center py-10">
                <p class="text-stone-500">جاري تحميل البيانات...</p>
            </div>

            <!-- Locked State -->
            <div v-else-if="isLocked" class="bg-white rounded-2xl p-10 text-center shadow-sm border border-stone-100 max-w-2xl mx-auto mt-10">
                <div class="bg-blue-100 text-blue-600 w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-10"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                </div>
                <h3 class="text-2xl font-bold text-gray-800 mb-2">ميزة الكوبونات غير مفعلة لمطعمك</h3>
                <p class="text-stone-500 mb-6 leading-relaxed">
                    نظام العروض والكوبونات يتيح لك إنشاء أكواد خصم ذكية لزيادة ولاء الزبائن ورفع المبيعات (خصم نسبة مئوية، خصم ثابت، تحديد حد أدنى للطلب).
                </p>
                <button class="bg-blue-600 text-white px-8 py-3 rounded-xl font-semibold hover:bg-blue-700 transition shadow-lg shadow-blue-600/20">
                    تواصل مع الإدارة لتفعيل الميزة
                </button>
            </div>

            <!-- Active State -->
            <div v-else class="space-y-8">
                <!-- Add New Offer -->
                <div class="bg-white rounded-2xl shadow-sm border border-stone-100 p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">إنشاء كوبون جديد</h3>
                    <form @submit.prevent="createOffer" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">كود الخصم (مثال: SAVE20)</label>
                            <TextInput v-model="form.code" type="text" class="w-full" required />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">نوع الخصم</label>
                            <select v-model="form.discount_type" class="w-full border-gray-300 rounded-md shadow-sm focus:border-amber-500 focus:ring-amber-500">
                                <option value="percentage">نسبة مئوية (%)</option>
                                <option value="fixed">مبلغ ثابت (SAR)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">قيمة الخصم</label>
                            <TextInput v-model="form.discount_value" type="number" step="0.01" class="w-full" required />
                        </div>
                        <div>
                            <PrimaryButton type="submit" class="w-full justify-center h-10">إضافة الكوبون</PrimaryButton>
                        </div>
                    </form>
                </div>

                <!-- Offers List -->
                <div class="bg-white rounded-2xl shadow-sm border border-stone-100 overflow-x-auto">
                    <table class="w-full text-right text-sm">
                        <thead class="bg-stone-50 text-stone-600 border-b border-stone-100">
                            <tr>
                                <th class="py-3 px-4 font-semibold">الكود</th>
                                <th class="py-3 px-4 font-semibold">القيمة</th>
                                <th class="py-3 px-4 font-semibold">تاريخ الانتهاء</th>
                                <th class="py-3 px-4 font-semibold">الحالة</th>
                                <th class="py-3 px-4 font-semibold">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100">
                            <tr v-if="offers.length === 0">
                                <td colspan="5" class="py-6 text-center text-stone-400">لا توجد كوبونات مسجلة.</td>
                            </tr>
                            <tr v-for="offer in offers" :key="offer.id" class="hover:bg-stone-50/50">
                                <td class="py-3 px-4 font-bold text-gray-800">{{ offer.code }}</td>
                                <td class="py-3 px-4 text-gray-600">
                                    {{ offer.discount_value }} {{ offer.discount_type === 'percentage' ? '%' : 'SAR' }}
                                </td>
                                <td class="py-3 px-4 text-gray-600">{{ offer.expires_at ? new Date(offer.expires_at).toLocaleDateString() : 'مفتوح' }}</td>
                                <td class="py-3 px-4">
                                    <button @click="toggleActive(offer)" :class="offer.is_active ? 'bg-green-100 text-green-700' : 'bg-stone-100 text-stone-600'" class="px-2 py-1 rounded text-xs font-medium hover:opacity-80">
                                        {{ offer.is_active ? 'نشط' : 'معطل' }}
                                    </button>
                                </td>
                                <td class="py-3 px-4">
                                    <button @click="deleteOffer(offer.id)" class="text-red-500 hover:text-red-700 text-sm">حذف</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
