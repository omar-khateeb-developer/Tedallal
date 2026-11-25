<script setup lang="ts">
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps<{
    canResetPassword?: boolean;
    status?: string;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => {
            form.reset('password');
        },
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="تسجيل الدخول" />

        <div dir="rtl" class="space-y-4">
            <div v-if="status" class="mb-4 text-sm font-medium text-emerald-500">
                {{ status }}
            </div>

            <form @submit.prevent="submit">
                <div>
                    <InputLabel for="email" value="البريد الإلكتروني" class="text-right font-bold" />

                    <TextInput
                        id="email"
                        type="email"
                        dir="ltr"
                        class="mt-1.5 block w-full text-left focus:border-amber-500 focus:ring-amber-500"
                        v-model="form.email"
                        required
                        autofocus
                        autocomplete="username"
                        placeholder="admin@restaurant.com"
                    />

                    <InputError class="mt-2" :message="form.errors.email" />
                </div>

                <div class="mt-4">
                    <InputLabel for="password" value="كلمة المرور" class="text-right font-bold" />

                    <TextInput
                        id="password"
                        type="password"
                        dir="ltr"
                        class="mt-1.5 block w-full text-left focus:border-amber-500 focus:ring-amber-500"
                        v-model="form.password"
                        required
                        autocomplete="current-password"
                    />

                    <InputError class="mt-2" :message="form.errors.password" />
                </div>

                <div class="mt-4 flex items-center justify-between">
                    <label class="flex items-center cursor-pointer select-none">
                        <Checkbox name="remember" v-model:checked="form.remember" class="text-amber-500 focus:ring-amber-500" />
                        <span class="ms-2 text-xs sm:text-sm text-stone-600 dark:text-stone-300 font-medium">
                            تذكرني على هذا الجهاز
                        </span>
                    </label>

                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="rounded-md text-xs sm:text-sm text-amber-600 dark:text-amber-400 underline hover:text-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 dark:focus:ring-offset-stone-900"
                    >
                        نسيت كلمة المرور؟
                    </Link>
                </div>

                <div class="mt-6">
                    <button
                        type="submit"
                        class="w-full py-3 px-6 rounded-xl bg-gradient-to-r from-[#BF953F] via-[#E8C265] to-[#B38728] text-stone-950 font-black text-sm shadow-lg shadow-amber-500/20 hover:brightness-105 active:scale-[0.99] transition-all cursor-pointer disabled:opacity-50"
                        :class="{ 'opacity-50': form.processing }"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'جاري تسجيل الدخول...' : 'تسجيل الدخول' }}
                    </button>
                </div>
            </form>
        </div>
    </GuestLayout>
</template>
