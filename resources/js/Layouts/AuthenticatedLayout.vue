<script setup lang="ts">
import { onMounted, ref, computed } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import NotificationToast from '@/Components/NotificationToast.vue';
import { useTheme } from '@/composables/useTheme';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();
const showingNavigationDropdown = ref(false);
const { isDark, toggleTheme, initTheme } = useTheme();

const user = computed(() => (page.props.auth as any)?.user);
const restaurant = computed(() => (page.props.auth as any)?.restaurant);
const userRole = computed(() => user.value?.role);
const roleLabel = computed(() => user.value?.role_label);

const userRoleBadgeClass = computed(() => {
    switch (userRole.value) {
        case 'super_admin':
        case 'admin':
            return 'bg-purple-100 dark:bg-purple-950/80 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800/80';
        case 'cashier':
            return 'bg-emerald-100 dark:bg-emerald-950/80 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/80';
        case 'chef':
            return 'bg-amber-100 dark:bg-amber-950/80 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800/80';
        case 'supervisor':
            return 'bg-sky-100 dark:bg-sky-950/80 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-800/80';
        case 'waiter':
            return 'bg-teal-100 dark:bg-teal-950/80 text-teal-700 dark:text-teal-300 border border-teal-200 dark:border-teal-800/80';
        default:
            return 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700';
    }
});

const userRoleDotClass = computed(() => {
    switch (userRole.value) {
        case 'super_admin':
        case 'admin':
            return 'bg-purple-500';
        case 'cashier':
            return 'bg-emerald-500';
        case 'chef':
            return 'bg-amber-500';
        case 'supervisor':
            return 'bg-sky-500';
        case 'waiter':
            return 'bg-teal-500';
        default:
            return 'bg-slate-400';
    }
});

onMounted(() => {
    initTheme('dark');
});
</script>

<template>
    <div>
        <div class="min-h-screen bg-slate-50 dark:bg-[#060913] text-slate-900 dark:text-slate-100 transition-colors duration-300" dir="rtl">
            <nav
                class="relative z-40 border-b border-white/10 bg-white/80 dark:bg-[#0a0f1d]/90 bg-cover bg-center shadow-xs dark:border-slate-800 backdrop-blur-md transition-colors"
                :style="restaurant?.navbar_image ? `background-image: url('${restaurant.navbar_image}')` : ''"
            >
                <!-- Gradient Overlay -->
                <div v-if="restaurant?.navbar_image" class="absolute inset-0 bg-gradient-to-r from-slate-900/80 via-slate-800/40 to-slate-900/80 backdrop-blur-[2px]"></div>
                
                <!-- Primary Navigation Menu -->
                <div class="relative z-10 mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-20 sm:h-24 justify-between items-center gap-4">
                        <div class="flex items-center gap-4 sm:gap-6 min-w-0">
                            <!-- Logo & Restaurant Info -->
                            <div class="flex shrink-0 items-center bg-white/90 dark:bg-slate-900/90 backdrop-blur-md px-3 py-1.5 sm:px-4 sm:py-2 rounded-2xl shadow-xs border border-white/50 dark:border-slate-700/80 transition-all hover:shadow-md min-w-0">
                                <Link :href="route('dashboard')" class="flex items-center gap-2.5 sm:gap-3.5 min-w-0">
                                    <img v-if="restaurant?.logo" :src="restaurant.logo" class="h-10 sm:h-14 w-auto max-w-[120px] sm:max-w-[180px] object-contain rounded-xl shadow-xs" alt="Restaurant Logo" />
                                    <ApplicationLogo v-else class="block h-9 sm:h-11 w-auto fill-current text-amber-500" />
                                    
                                    <!-- Restaurant Name & Operating Type Badge -->
                                    <div v-if="restaurant" class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-2.5 min-w-0">
                                        <span class="text-sm sm:text-lg font-extrabold bg-gradient-to-l from-amber-600 to-orange-500 dark:from-amber-400 dark:to-orange-400 bg-clip-text text-transparent drop-shadow-xs font-sans tracking-wide truncate max-w-[140px] sm:max-w-none">
                                            {{ restaurant.name }}
                                        </span>
                                        <span
                                            v-if="restaurant.type === 'prepaid'"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-xl text-xs font-bold bg-amber-500/15 border border-amber-500/30 text-amber-700 dark:text-amber-300 shadow-2xs whitespace-nowrap self-start sm:self-auto"
                                            title="نظام دفع مسبق ونوافذ استلام ذاتية"
                                        >
                                            <span class="h-1.5 w-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                            <span>دفع مسبق</span>
                                            <bdi class="text-[10px] font-mono opacity-85">(Prepaid)</bdi>
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-xl text-xs font-bold bg-emerald-500/15 border border-emerald-500/30 text-emerald-700 dark:text-emerald-300 shadow-2xs whitespace-nowrap self-start sm:self-auto"
                                            title="نظام خدمة طاولات ودفع لاحق"
                                        >
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                            <span>دفع لاحق</span>
                                            <bdi class="text-[10px] font-mono opacity-85">(Postpaid)</bdi>
                                        </span>
                                    </div>

                                    <!-- Super Admin Branding if no restaurant -->
                                    <div v-else-if="userRole === 'super_admin'" class="flex items-center gap-2">
                                        <span class="text-base sm:text-lg font-extrabold bg-gradient-to-l from-purple-600 to-indigo-500 dark:from-purple-400 dark:to-indigo-300 bg-clip-text text-transparent drop-shadow-xs font-sans tracking-wide">
                                            منصة تدلّل
                                        </span>
                                        <span class="hidden sm:inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-xl text-xs font-bold bg-purple-500/15 border border-purple-500/30 text-purple-700 dark:text-purple-300 shadow-2xs whitespace-nowrap">
                                            <span class="h-1.5 w-1.5 rounded-full bg-purple-500 animate-pulse"></span>
                                            <span>إدارة النظام</span>
                                        </span>
                                    </div>
                                </Link>
                            </div>

                            <!-- Desktop Quick Nav Links -->
                            <div class="hidden md:flex items-center gap-2 bg-white/90 dark:bg-slate-900/90 backdrop-blur-md px-3 py-1.5 rounded-2xl shadow-xs border border-white/50 dark:border-slate-700/80">
                                <Link
                                    :href="route('dashboard')"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs sm:text-sm font-semibold transition-all duration-200"
                                    :class="route().current('dashboard') || route().current('admin.dashboard') || route().current('super_admin.dashboard')
                                        ? 'bg-amber-500/15 text-amber-700 dark:text-amber-300 font-bold'
                                        : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800/60'"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                    </svg>
                                    <span>الرئيسية</span>
                                </Link>
                                
                                <Link
                                    v-if="user?.permissions?.includes('manage_orders')"
                                    :href="route('admin.orders')"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs sm:text-sm font-semibold transition-all duration-200"
                                    :class="route().current('admin.orders')
                                        ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 font-bold'
                                        : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800/60'"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                    </svg>
                                    <span>الكاشير والطلبات</span>
                                </Link>

                                <Link
                                    v-if="user?.role === 'chef' || user?.role === 'waiter' || user?.role === 'admin' || user?.role === 'supervisor'"
                                    :href="route('staff.orders')"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs sm:text-sm font-semibold transition-all duration-200"
                                    :class="route().current('staff.orders')
                                        ? 'bg-amber-500/15 text-amber-700 dark:text-amber-300 font-bold'
                                        : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800/60'"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                    </svg>
                                    <span>المطبخ <bdi class="text-[10px] font-mono opacity-85">(KDS)</bdi></span>
                                </Link>
                            </div>
                        </div>

                        <!-- Left Section in RTL (Theme Toggle + User Dropdown) -->
                        <div class="hidden sm:flex sm:items-center gap-3">
                            <!-- Theme Toggle Button -->
                            <button
                                type="button"
                                class="flex h-10 items-center gap-1.5 rounded-2xl px-3 text-xs font-bold transition-all duration-200 border active:scale-95 shadow-2xs select-none bg-white/90 border-slate-200 text-slate-700 hover:bg-slate-100 dark:bg-slate-800/90 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-700 dark:hover:border-amber-500/40"
                                :title="isDark ? 'التبديل إلى الألوان الفاتحة' : 'التبديل إلى الألوان الداكنة الفاخرة'"
                                @click="toggleTheme"
                            >
                                <span v-if="isDark" class="flex items-center gap-1.5">
                                    <svg class="h-4 w-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                                    </svg>
                                    <span class="text-[11px] text-amber-300 font-bold hidden md:inline">فاتح</span>
                                </span>
                                <span v-else class="flex items-center gap-1.5">
                                    <svg class="h-4 w-4 text-slate-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                                    </svg>
                                    <span class="text-[11px] text-slate-700 font-bold hidden md:inline">داكن</span>
                                </span>
                            </button>

                            <!-- User Profile & Role Dropdown -->
                            <div class="flex items-center bg-white/90 dark:bg-slate-900/90 backdrop-blur-md px-3 py-1.5 rounded-2xl shadow-xs border border-white/50 dark:border-slate-700/80 transition-all hover:shadow-md">
                                <Dropdown align="right" width="64">
                                    <template #trigger>
                                        <button
                                            id="user-menu-button"
                                            type="button"
                                            class="inline-flex items-center gap-2 rounded-xl border border-transparent bg-transparent px-2 py-1 text-sm font-semibold leading-4 text-slate-700 transition duration-150 ease-in-out hover:text-slate-900 focus:outline-none dark:text-gray-300 dark:hover:text-white"
                                        >
                                            <div class="flex items-center gap-2">
                                                <span class="font-bold text-slate-800 dark:text-slate-100 max-w-[150px] lg:max-w-[220px] xl:max-w-xs truncate">{{ user?.name }}</span>
                                                <!-- Role Badge -->
                                                <span
                                                    v-if="roleLabel"
                                                    class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-lg text-xs font-bold shadow-2xs whitespace-nowrap"
                                                    :class="userRoleBadgeClass"
                                                >
                                                    <span class="h-1.5 w-1.5 rounded-full" :class="userRoleDotClass"></span>
                                                    <span>{{ roleLabel }}</span>
                                                </span>
                                            </div>

                                            <svg
                                                class="-me-0.5 ms-1 h-4 w-4 opacity-70"
                                                xmlns="http://www.w3.org/2000/svg"
                                                viewBox="0 0 20 20"
                                                fill="currentColor"
                                            >
                                                <path
                                                    fill-rule="evenodd"
                                                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                    clip-rule="evenodd"
                                                />
                                            </svg>
                                        </button>
                                    </template>

                                    <template #content>
                                        <!-- Header summary inside dropdown -->
                                        <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-800/80 bg-slate-50/70 dark:bg-slate-900/60 text-start">
                                            <div class="flex items-center justify-between gap-2 mb-1">
                                                <span class="text-xs font-bold text-slate-900 dark:text-white truncate">{{ user?.name }}</span>
                                                <span
                                                    v-if="roleLabel"
                                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-bold shrink-0"
                                                    :class="userRoleBadgeClass"
                                                >
                                                    <span class="h-1 w-1 rounded-full" :class="userRoleDotClass"></span>
                                                    <span>{{ roleLabel }}</span>
                                                </span>
                                            </div>
                                            <div class="text-[11px] text-slate-500 dark:text-slate-400 truncate">
                                                {{ user?.email }}
                                            </div>
                                            <div v-if="restaurant" class="mt-2 pt-2 border-t border-slate-200/50 dark:border-slate-800/50 flex items-center justify-between text-[11px]">
                                                <span class="text-slate-600 dark:text-slate-300 font-semibold truncate">{{ restaurant.name }}</span>
                                                <span
                                                    v-if="restaurant.type === 'prepaid'"
                                                    class="text-[10px] font-bold text-amber-600 dark:text-amber-400 shrink-0"
                                                >
                                                    دفع مسبق
                                                </span>
                                                <span
                                                    v-else
                                                    class="text-[10px] font-bold text-emerald-600 dark:text-emerald-400 shrink-0"
                                                >
                                                    دفع لاحق
                                                </span>
                                            </div>
                                        </div>

                                        <div class="py-1">
                                            <DropdownLink :href="route('profile.edit')" class="flex items-center gap-2">
                                                <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                                </svg>
                                                <span>الملف الشخصي</span>
                                            </DropdownLink>
                                            <DropdownLink :href="route('logout')" method="post" as="button" class="flex items-center gap-2 text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/30">
                                                <svg class="w-4 h-4 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                                </svg>
                                                <span>تسجيل الخروج</span>
                                            </DropdownLink>
                                        </div>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <!-- Mobile Theme Toggle & Hamburger -->
                        <div class="-me-2 flex items-center gap-2 sm:hidden">
                            <button
                                type="button"
                                class="flex h-9 w-9 items-center justify-center rounded-xl border active:scale-95 transition-all bg-white/90 border-slate-200 text-slate-700 hover:bg-slate-100 dark:bg-slate-800/90 dark:border-slate-700 dark:text-slate-200"
                                :title="isDark ? 'التبديل إلى الألوان الفاتحة' : 'التبديل إلى الألوان الداكنة'"
                                @click="toggleTheme"
                            >
                                <svg v-if="isDark" class="h-4 w-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                                <svg v-else class="h-4 w-4 text-slate-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                                </svg>
                            </button>

                            <button
                                @click="
                                    showingNavigationDropdown =
                                        !showingNavigationDropdown
                                "
                                class="inline-flex items-center justify-center rounded-xl p-2 text-gray-500 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-700 focus:outline-none dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                            >
                                <svg
                                    class="h-6 w-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        :class="{
                                            hidden: showingNavigationDropdown,
                                            'inline-flex':
                                                !showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{
                                            hidden: !showingNavigationDropdown,
                                            'inline-flex':
                                                showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Responsive Mobile Navigation Menu -->
                <div
                    :class="{
                        block: showingNavigationDropdown,
                        hidden: !showingNavigationDropdown,
                    }"
                    class="sm:hidden relative z-20 bg-white/95 dark:bg-[#0a0f1d] shadow-2xl border-t border-gray-100 dark:border-slate-800 transition-colors"
                    dir="rtl"
                >
                    <!-- Restaurant Card in Mobile Menu -->
                    <div v-if="restaurant" class="p-4 border-b border-gray-100 dark:border-slate-800/80 bg-slate-50/70 dark:bg-slate-900/50">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <img v-if="restaurant.logo" :src="restaurant.logo" class="h-9 w-9 rounded-xl object-contain bg-white dark:bg-slate-800 p-0.5 border border-slate-200 dark:border-slate-700" alt="Restaurant Logo" />
                                <span class="font-extrabold text-sm text-slate-800 dark:text-slate-100 truncate">
                                    {{ restaurant.name }}
                                </span>
                            </div>
                            <!-- Mobile Restaurant Type Badge -->
                            <span
                                v-if="restaurant.type === 'prepaid'"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl text-xs font-bold bg-amber-500/15 border border-amber-500/30 text-amber-700 dark:text-amber-300 shadow-2xs whitespace-nowrap shrink-0"
                            >
                                <span class="h-1.5 w-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                <span>دفع مسبق</span>
                                <bdi class="text-[10px] font-mono opacity-80">(Prepaid)</bdi>
                            </span>
                            <span
                                v-else
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl text-xs font-bold bg-emerald-500/15 border border-emerald-500/30 text-emerald-700 dark:text-emerald-300 shadow-2xs whitespace-nowrap shrink-0"
                            >
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                <span>دفع لاحق</span>
                                <bdi class="text-[10px] font-mono opacity-80">(Postpaid)</bdi>
                            </span>
                        </div>
                    </div>

                    <!-- Navigation Links in Mobile -->
                    <div class="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink
                            :href="route('dashboard')"
                            :active="route().current('dashboard') || route().current('admin.dashboard') || route().current('super_admin.dashboard')"
                        >
                            الرئيسية
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            v-if="user?.permissions?.includes('manage_orders')"
                            :href="route('admin.orders')"
                            :active="route().current('admin.orders')"
                        >
                            شاشة الكاشير والطلبات
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            v-if="user?.role === 'chef' || user?.role === 'waiter' || user?.role === 'admin' || user?.role === 'supervisor'"
                            :href="route('staff.orders')"
                            :active="route().current('staff.orders')"
                        >
                            شاشة المطبخ <bdi class="text-[10px] font-mono opacity-85">(KDS)</bdi>
                        </ResponsiveNavLink>
                    </div>

                    <!-- Responsive Settings Options -->
                    <div class="border-t border-gray-200 pb-4 pt-4 dark:border-gray-800">
                        <div class="px-4">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-base font-bold text-gray-800 dark:text-gray-200 truncate">{{ user?.name }}</span>
                                <span
                                    v-if="roleLabel"
                                    class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-lg text-xs font-bold shadow-2xs whitespace-nowrap"
                                    :class="userRoleBadgeClass"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full" :class="userRoleDotClass"></span>
                                    <span>{{ roleLabel }}</span>
                                </span>
                            </div>
                            <div class="text-sm font-medium text-gray-500 dark:text-gray-400 mt-1">
                                {{ user?.email }}
                            </div>
                        </div>

                        <div class="mt-4 space-y-1">
                            <ResponsiveNavLink :href="route('profile.edit')">
                                الملف الشخصي
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                :href="route('logout')"
                                method="post"
                                as="button"
                                class="text-rose-600 dark:text-rose-400 hover:text-rose-700 dark:hover:text-rose-300"
                            >
                                تسجيل الخروج
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Page Heading -->
            <header
                class="bg-white/95 dark:bg-[#0a0f1d] shadow-xs border-b border-slate-100 dark:border-slate-800/80 transition-colors"
                v-if="$slots.header"
            >
                <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main>
                <Transition name="page" mode="out-in" appear>
                    <div :key="$page.url">
                        <slot />
                    </div>
                </Transition>
            </main>
        </div>
        <NotificationToast />
    </div>
</template>

<style>
.page-enter-active,
.page-leave-active {
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.page-enter-from {
  opacity: 0;
  transform: translateY(10px);
}
.page-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}
</style>
