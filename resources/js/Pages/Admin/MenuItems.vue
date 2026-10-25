<script setup lang="ts">
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { Head } from '@inertiajs/vue3';
import AdminNav from '@/Components/Admin/AdminNav.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

interface Category { id: number; name: string }
interface MenuItemRow { id: number; name: string; description: string; price: string; is_available: boolean; is_active: boolean; category_id: number; image: string | null }

const categories = ref<Category[]>([]);
const items = ref<MenuItemRow[]>([]);
const form = ref({ category_id: '', name: '', description: '', price: '', image: null as File | null });
const toastMessage = ref('');
const selectedImage = ref<string | null>(null);
const creatingItem = ref(false);

async function load() {
    const [itemsRes, catsRes] = await Promise.all([
        axios.get('/api/admin/menu/items'),
        axios.get('/api/admin/menu/categories'),
    ]);
    items.value = itemsRes.data.data ?? [];
    categories.value = catsRes.data.data ?? [];
    if (categories.value.length && !form.value.category_id) {
        form.value.category_id = String(categories.value[0].id);
    }
}

async function createItem() {
    if (!form.value.category_id || !form.value.name || !form.value.price) return;
    
    creatingItem.value = true;
    try {
        const formData = new FormData();
        formData.append('category_id', form.value.category_id);
        formData.append('name', form.value.name);
        formData.append('description', form.value.description);
        formData.append('price', form.value.price);
        if (form.value.image) {
            formData.append('image', form.value.image);
        }

        await axios.post('/api/admin/menu/items', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });
        
        form.value.name = '';
        form.value.price = '';
        form.value.description = '';
        form.value.image = null;
        const fileInput = document.getElementById('createImageInput') as HTMLInputElement;
        if (fileInput) fileInput.value = '';
        
        await load();
    } finally {
        creatingItem.value = false;
    }
}

async function updateItem(item: MenuItemRow, field: string, value: unknown) {
    try {
        if (value instanceof File) {
            const formData = new FormData();
            formData.append('_method', 'PATCH');
            formData.append(field, value);
            await axios.post(`/api/admin/menu/items/${item.id}`, formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });
        } else {
            await axios.patch(`/api/admin/menu/items/${item.id}`, { [field]: value });
        }
        await load();
        toastMessage.value = 'تم حفظ التعديل بنجاح';
        setTimeout(() => toastMessage.value = '', 3000);
    } catch (error: any) {
        alert(error.response?.data?.message || 'حدث خطأ أثناء حفظ التعديلات');
    }
}

async function deleteItem(item: MenuItemRow) {
    if (!confirm(`حذف "${item.name}"؟`)) return;
    await axios.delete(`/api/admin/menu/items/${item.id}`);
    await load();
}

onMounted(load);
</script>

<template>
    <Head title="أصناف القائمة" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800 dark:text-white">أصناف القائمة</h2>
        </template>
        <div class="mx-auto max-w-4xl p-6" dir="rtl">
            <AdminNav />
            <div class="mb-4 space-y-2">
                <div class="grid gap-2 sm:grid-cols-5">
                    <select v-model="form.category_id" class="rounded-xl border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-stone-900 dark:text-white text-sm focus:border-amber-500 focus:ring-amber-500">
                        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                    <input v-model="form.name" class="rounded-xl border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-stone-900 dark:text-white text-sm focus:border-amber-500 focus:ring-amber-500" placeholder="الاسم" />
                    <input v-model="form.price" class="rounded-xl border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-stone-900 dark:text-white text-sm focus:border-amber-500 focus:ring-amber-500" placeholder="السعر" dir="ltr" />
                    <input type="file" id="createImageInput" accept="image/*" class="rounded-xl border border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-stone-700 dark:text-stone-300 text-xs file:mr-2 file:py-1.5 file:px-3 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-amber-50 dark:file:bg-amber-950/60 file:text-amber-700 dark:file:text-amber-400 hover:file:bg-amber-100" @change="e => form.image = (e.target as HTMLInputElement).files?.[0] ?? null" />
                    <button class="rounded-xl bg-amber-600 dark:bg-amber-500 text-white dark:text-slate-950 font-bold flex items-center justify-center transition-all disabled:opacity-50 hover:bg-amber-700 dark:hover:bg-amber-400" :disabled="creatingItem" @click="createItem">
                        <span v-if="creatingItem" class="inline-block h-4 w-4 border-2 border-white dark:border-slate-950 border-t-transparent rounded-full animate-spin ml-2"></span>
                        إضافة
                    </button>
                </div>
                <textarea v-model="form.description" class="w-full rounded-xl border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-stone-900 dark:text-white text-sm focus:border-amber-500 focus:ring-amber-500" rows="2" placeholder="مواصفات الصنف ومكوناته (اختياري)"></textarea>
            </div>
            <ul class="space-y-2">
                <li
                    v-for="(item, index) in items"
                    :key="item.id"
                    class="rounded-xl bg-white dark:bg-[#0a0f1d] p-4 shadow-xs border border-stone-100 dark:border-slate-800 animate-stagger transition-colors"
                    :style="`animation-delay: ${index * 50}ms`"
                >
                    <div class="flex flex-col gap-2">
                        <div class="flex flex-wrap items-center gap-3">
                            <img 
                                v-if="item.image" 
                                :src="item.image" 
                                class="h-12 w-12 object-cover rounded-lg border border-stone-200 dark:border-slate-700 cursor-pointer hover:opacity-80 transition" 
                                alt="صورة الصنف" 
                                @click="selectedImage = item.image"
                            />
                            <div v-else class="h-12 w-12 rounded-lg border border-stone-200 dark:border-slate-700 bg-stone-50 dark:bg-slate-800 flex items-center justify-center text-xs text-stone-400 dark:text-slate-500">بدون</div>

                            <input
                                :value="item.name"
                                class="min-w-[120px] flex-1 rounded-lg border-stone-200 dark:border-slate-700 bg-stone-50 dark:bg-slate-900 text-stone-900 dark:text-white text-sm px-3 py-1.5 focus:border-amber-500 focus:ring-amber-500"
                                @change="updateItem(item, 'name', ($event.target as HTMLInputElement).value)"
                            />
                            <input
                                :value="item.price"
                                class="w-24 rounded-lg border-stone-200 dark:border-slate-700 bg-stone-50 dark:bg-slate-900 text-stone-900 dark:text-white text-sm px-3 py-1.5 focus:border-amber-500 focus:ring-amber-500"
                                dir="ltr"
                                @change="updateItem(item, 'price', ($event.target as HTMLInputElement).value)"
                            />
                            <input 
                                type="file" 
                                accept="image/*"
                                class="w-48 text-xs file:mr-2 file:py-1 file:px-2 file:rounded-full file:border-0 file:text-xs file:bg-stone-100 dark:file:bg-slate-800 file:text-stone-700 dark:file:text-stone-300 hover:file:bg-stone-200"
                                @change="e => { const f = (e.target as HTMLInputElement).files?.[0]; if(f) updateItem(item, 'image', f); }"
                            />
                            <button
                                class="rounded-lg px-2.5 py-1 text-xs font-semibold transition-colors"
                                :class="item.is_available ? 'bg-green-100 dark:bg-emerald-950/60 text-green-800 dark:text-emerald-300 border border-green-200/50 dark:border-emerald-800/40' : 'bg-red-100 dark:bg-red-950/60 text-red-700 dark:text-red-300 border border-red-200/50 dark:border-red-800/40'"
                                @click="updateItem(item, 'is_available', !item.is_available)"
                            >
                                {{ item.is_available ? 'متاح' : 'غير متاح' }}
                            </button>
                            <button
                                class="rounded-lg px-2.5 py-1 text-xs font-semibold transition-colors"
                                :class="item.is_active ? 'bg-blue-100 dark:bg-blue-950/60 text-blue-800 dark:text-blue-300 border border-blue-200/50 dark:border-blue-800/40' : 'bg-stone-100 dark:bg-slate-800 text-stone-500 dark:text-stone-400'"
                                @click="updateItem(item, 'is_active', !item.is_active)"
                            >
                                {{ item.is_active ? 'ظاهر' : 'مخفي' }}
                            </button>
                            <button class="rounded-lg bg-red-100 dark:bg-red-950/60 px-2.5 py-1 text-xs font-semibold text-red-700 dark:text-red-300 hover:bg-red-200 dark:hover:bg-red-900/50 transition-colors" @click="deleteItem(item)">
                                حذف
                            </button>
                        </div>
                        <div class="pl-[60px]">
                            <textarea
                                :value="item.description"
                                class="w-full rounded-lg border-stone-200 dark:border-slate-700 bg-stone-50 dark:bg-slate-900 text-stone-900 dark:text-white text-sm focus:border-amber-500 focus:ring-amber-500"
                                rows="2"
                                placeholder="مواصفات الصنف ومكوناته (اختياري)"
                                @change="updateItem(item, 'description', ($event.target as HTMLTextAreaElement).value)"
                            ></textarea>
                        </div>
                    </div>
                </li>
            </ul>
        </div>

        <!-- Toast Notification -->
        <transition enter-active-class="transition ease-out duration-300" enter-from-class="transform opacity-0 translate-y-2" enter-to-class="transform opacity-100 translate-y-0" leave-active-class="transition ease-in duration-200" leave-from-class="transform opacity-100 translate-y-0" leave-to-class="transform opacity-0 translate-y-2">
            <div v-if="toastMessage" class="fixed bottom-4 right-4 bg-green-600 text-white px-4 py-2 rounded-lg shadow-lg z-50">
                {{ toastMessage }}
            </div>
        </transition>

        <!-- Image Preview Modal -->
        <div v-if="selectedImage" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/80 p-4" @click="selectedImage = null">
            <img :src="selectedImage" class="max-h-full max-w-full rounded-xl object-contain shadow-2xl" @click.stop />
            <button class="absolute top-4 right-4 text-white hover:text-gray-300" @click="selectedImage = null">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.animate-stagger {
    animation: fadeSlideUp 0.5s cubic-bezier(0.4, 0, 0.2, 1) forwards;
    opacity: 0;
    transform: translateY(20px);
}
@keyframes fadeSlideUp {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
