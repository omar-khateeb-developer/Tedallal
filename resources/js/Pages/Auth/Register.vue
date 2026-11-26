<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    restaurant_name: '',
    phone: '',
    currency: 'SYP',
    type: 'postpaid',
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => {
            form.reset('password', 'password_confirmation');
        },
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="تسجيل مطعم جديد" />

        <div dir="rtl" class="space-y-4">
            <div class="text-center pb-2 border-b border-stone-200/70 dark:border-slate-800">
                <h2 class="text-lg font-black text-stone-900 dark:text-white">إنشاء مساحة مطعم سريعة</h2>
                <p class="text-xs text-stone-500 dark:text-slate-400 mt-1">أدخل بيانات مطعمك لبدء العمل فوراً بفترة تجريبية مجانية</p>
            </div>

            <form @submit.prevent="submit" class="space-y-3.5">
                <div>
                    <InputLabel for="restaurant_name" value="اسم المطعم *" class="text-right font-bold" />
                    <TextInput
                        id="restaurant_name"
                        type="text"
                        class="mt-1 block w-full focus:border-amber-500 focus:ring-amber-500"
                        v-model="form.restaurant_name"
                        required
                        autofocus
                        placeholder="مثال: مطعم دار الياسمين"
                    />
                    <InputError class="mt-1.5" :message="form.errors.restaurant_name" />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <InputLabel for="currency" value="العملة" class="text-right font-bold" />
                        <select
                            id="currency"
                            v-model="form.currency"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-xs focus:border-amber-500 focus:ring-amber-500 text-sm"
                        >
                            <option value="SYP">ليرة سورية (SYP)</option>
                            <option value="SAR">ريال سعودي (SAR)</option>
                            <option value="AED">درهم إماراتي (AED)</option>
                            <option value="USD">دولار أمريكي (USD)</option>
                            <option value="JOD">دينار أردني (JOD)</option>
                            <option value="IQD">دينار عراقي (IQD)</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel for="type" value="نظام العمل" class="text-right font-bold" />
                        <select
                            id="type"
                            v-model="form.type"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-xs focus:border-amber-500 focus:ring-amber-500 text-sm"
                        >
                            <option value="postpaid">دفع لاحق (طاولات)</option>
                            <option value="prepaid">دفع مسبق (سريع)</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <InputLabel for="name" value="اسم المدير *" class="text-right font-bold" />
                        <TextInput
                            id="name"
                            type="text"
                            class="mt-1 block w-full focus:border-amber-500 focus:ring-amber-500"
                            v-model="form.name"
                            required
                            autocomplete="name"
                            placeholder="الاسم الكامل"
                        />
                        <InputError class="mt-1.5" :message="form.errors.name" />
                    </div>
                    <div>
                        <InputLabel for="phone" value="رقم الواتساب" class="text-right font-bold" />
                        <TextInput
                            id="phone"
                            type="text"
                            dir="ltr"
                            class="mt-1 block w-full text-left focus:border-amber-500 focus:ring-amber-500"
                            v-model="form.phone"
                            placeholder="09XXXXXXXX"
                        />
                        <InputError class="mt-1.5" :message="form.errors.phone" />
                    </div>
                </div>

                <div>
                    <InputLabel for="email" value="البريد الإلكتروني *" class="text-right font-bold" />
                    <TextInput
                        id="email"
                        type="email"
                        dir="ltr"
                        class="mt-1 block w-full text-left focus:border-amber-500 focus:ring-amber-500"
                        v-model="form.email"
                        required
                        autocomplete="username"
                        placeholder="owner@restaurant.com"
                    />
                    <InputError class="mt-1.5" :message="form.errors.email" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <InputLabel for="password" value="كلمة المرور *" class="text-right font-bold" />
                        <TextInput
                            id="password"
                            type="password"
                            dir="ltr"
                            class="mt-1 block w-full text-left focus:border-amber-500 focus:ring-amber-500"
                            v-model="form.password"
                            required
                            autocomplete="new-password"
                        />
                        <InputError class="mt-1.5" :message="form.errors.password" />
                    </div>
                    <div>
                        <InputLabel for="password_confirmation" value="تأكيد كلمة المرور *" class="text-right font-bold" />
                        <TextInput
                            id="password_confirmation"
                            type="password"
                            dir="ltr"
                            class="mt-1 block w-full text-left focus:border-amber-500 focus:ring-amber-500"
                            v-model="form.password_confirmation"
                            required
                            autocomplete="new-password"
                        />
                        <InputError class="mt-1.5" :message="form.errors.password_confirmation" />
                    </div>
                </div>

                <div class="pt-2 space-y-3">
                    <button
                        type="submit"
                        class="w-full py-3 px-6 rounded-xl bg-gradient-to-r from-[#BF953F] via-[#E8C265] to-[#B38728] text-stone-950 font-black text-sm shadow-lg shadow-amber-500/20 hover:brightness-105 active:scale-[0.99] transition-all cursor-pointer disabled:opacity-50"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'جاري إنشاء المطعم...' : 'إنشاء المطعم وبدء التشغيل ⚡' }}
                    </button>

                    <div class="text-center">
                        <Link
                            :href="route('login')"
                            class="text-xs sm:text-sm text-amber-600 dark:text-amber-400 underline hover:text-amber-500"
                        >
                            لديك حساب بالفعل؟ تسجيل الدخول
                        </Link>
                    </div>
                </div>
            </form>
        </div>
    </GuestLayout>
</template>
