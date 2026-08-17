<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import axios from 'axios';

const restaurants = ref([]);

const fetchRestaurants = async () => {
    try {
        const response = await axios.get('/api/super-admin/restaurants');
        restaurants.value = response.data;
    } catch (e) {
        console.error(e);
    }
};

onMounted(() => {
    fetchRestaurants();
});
</script>

<template>
    <Head title="إدارة المطاعم - مدير النظام" />

    <AdminLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">إدارة المطاعم</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <h3 class="text-lg font-bold mb-4">قائمة المطاعم في النظام</h3>
                        <table class="w-full text-right border-collapse">
                            <thead>
                                <tr class="bg-gray-100">
                                    <th class="p-3 border">الاسم</th>
                                    <th class="p-3 border">الحالة</th>
                                    <th class="p-3 border">الهاتف</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="restaurant in restaurants" :key="restaurant.id">
                                    <td class="p-3 border">{{ restaurant.name }}</td>
                                    <td class="p-3 border">{{ restaurant.status === 'active' ? 'نشط' : 'غير نشط' }}</td>
                                    <td class="p-3 border">{{ restaurant.phone }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
