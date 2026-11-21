<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import axios from 'axios';

const props = defineProps<{
    restaurant: {
        id: number;
        name: string;
        slug: string;
        logo_url?: string;
        active_waiting_count: number;
    };
}>();

const form = ref({
    guest_name: '',
    guest_phone: '',
    party_size: 2,
    seating_preference: 'any',
    notes: '',
});

const isSubmitting = ref(false);
const errorMessage = ref('');

async function submitJoin() {
    if (!form.value.guest_name.trim() || !form.value.guest_phone.trim()) {
        errorMessage.value = 'يرجى إدخال الاسم ورقم الهاتف للمتابعة.';
        return;
    }

    errorMessage.value = '';
    isSubmitting.value = true;
    try {
        const { data } = await axios.post(`/api/public/restaurants/${props.restaurant.slug}/waitlist/join`, form.value);
        if (data.redirect_url) {
            window.location.href = data.redirect_url;
        }
    } catch (e: any) {
        errorMessage.value = e.response?.data?.message || 'تعذر الانضمام لقائمة الانتظار حالياً، يرجى مراجعة موظف الاستقبال.';
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <Head :title="`انضم لقائمة الانتظار - ${restaurant.name}`" />

    <div class="min-h-screen bg-slate-950 text-slate-100 flex flex-col justify-between selection:bg-amber-500 selection:text-slate-950 font-sans p-4" dir="rtl">
        <div class="max-w-md w-full mx-auto my-auto space-y-6">
            <!-- Restaurant Logo & Title -->
            <div class="text-center space-y-2">
                <div v-if="restaurant.logo_url" class="w-16 h-16 mx-auto rounded-2xl overflow-hidden border border-amber-500/30 bg-slate-900 p-1 shadow-xl">
                    <img :src="restaurant.logo_url" :alt="restaurant.name" class="w-full h-full object-cover rounded-xl" />
                </div>
                <div v-else class="w-16 h-16 mx-auto rounded-2xl bg-amber-500/20 border border-amber-500/40 flex items-center justify-center font-black text-amber-400 text-2xl shadow-xl">
                    {{ restaurant.name.charAt(0) }}
                </div>
                <h1 class="text-xl font-black text-white">{{ restaurant.name }}</h1>
                <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-slate-900 border border-slate-800 text-xs text-amber-400">
                    <span>📋 طابور الانتظار الرقمي</span>
                    <span v-if="restaurant.active_waiting_count > 0" class="text-slate-400">• {{ restaurant.active_waiting_count }} مجموعات بانتظار طاولة</span>
                </div>
            </div>

            <!-- Card Form -->
            <form @submit.prevent="submitJoin" class="bg-slate-900/90 border border-slate-800 rounded-3xl p-6 shadow-2xl backdrop-blur-md space-y-4">
                <div v-if="errorMessage" class="p-3 rounded-xl bg-rose-950/80 border border-rose-500 text-xs text-rose-300">
                    {{ errorMessage }}
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5">الاسم الكريم *</label>
                    <input
                        v-model="form.guest_name"
                        type="text"
                        required
                        placeholder="مثال: أحمد الحلبي"
                        class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 focus:border-amber-500 text-white text-sm outline-hidden transition-colors"
                    />
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5">رقم الواتساب (لاستلام إشعار الطاولة) *</label>
                    <input
                        v-model="form.guest_phone"
                        type="tel"
                        required
                        placeholder="09xxxxxxxx أو +963..."
                        dir="ltr"
                        class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 focus:border-amber-500 text-white text-sm outline-hidden text-right transition-colors"
                    />
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5">عدد الأفراد (الضيوف)</label>
                    <div class="grid grid-cols-5 gap-1.5">
                        <button
                            v-for="n in [1, 2, 3, 4, 6]"
                            :key="n"
                            type="button"
                            @click="form.party_size = n"
                            class="py-2 rounded-xl text-xs font-bold border transition-all cursor-pointer"
                            :class="form.party_size === n ? 'bg-amber-500 text-slate-950 border-amber-400 font-black shadow-md' : 'bg-slate-950 text-slate-300 border-slate-800 hover:border-slate-700'"
                        >
                            {{ n }}
                        </button>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5">تفضيل الجلسة</label>
                    <select
                        v-model="form.seating_preference"
                        class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 focus:border-amber-500 text-white text-xs outline-hidden transition-colors"
                    >
                        <option value="any">أي طاولة متاحة أولاً</option>
                        <option value="indoor">صالة داخلية مكيفة</option>
                        <option value="terrace">تراس خارجي في الهواء الطلق</option>
                        <option value="family">قسم عائلي خاص</option>
                        <option value="vip">جناح VIP</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5">ملاحظات إضافية (اختياري)</label>
                    <input
                        v-model="form.notes"
                        type="text"
                        placeholder="كرسي أطفال، مناسبة خاصة..."
                        class="w-full px-3.5 py-2 rounded-xl bg-slate-950 border border-slate-800 focus:border-amber-500 text-white text-xs outline-hidden transition-colors"
                    />
                </div>

                <button
                    type="submit"
                    :disabled="isSubmitting"
                    class="w-full py-3.5 px-4 rounded-2xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-black text-sm flex items-center justify-center gap-2 shadow-lg shadow-amber-500/20 hover:scale-[1.01] active:scale-[0.99] transition-all cursor-pointer disabled:opacity-50"
                >
                    <span>{{ isSubmitting ? 'جاري التسجيل...' : 'تأكيد التسجيل في قائمة الانتظار' }}</span>
                    <span>←</span>
                </button>
            </form>
        </div>

        <footer class="p-3 text-center text-[11px] text-slate-600 font-mono">
            نظام تدلل لإدارة الطوابير والمطاعم
        </footer>
    </div>
</template>
