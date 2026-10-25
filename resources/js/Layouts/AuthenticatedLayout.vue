<script setup lang="ts">
import { onMounted, ref } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import NotificationToast from '@/Components/NotificationToast.vue';
import { useTheme } from '@/composables/useTheme';
import { Link } from '@inertiajs/vue3';

const showingNavigationDropdown = ref(false);
const { isDark, toggleTheme, initTheme } = useTheme();

onMounted(() => {
    initTheme('dark');
});
</script>

<template>
    <div>
        <div class="min-h-screen bg-slate-50 dark:bg-[#060913] text-slate-900 dark:text-slate-100 transition-colors duration-300">
            <nav
                class="relative border-b border-white/10 bg-white/80 dark:bg-[#0a0f1d]/90 bg-cover bg-center shadow-xs dark:border-slate-800 backdrop-blur-md transition-colors"
                :style="$page.props.auth.restaurant?.navbar_image ? `background-image: url('${$page.props.auth.restaurant.navbar_image}')` : ''"
            >
                <!-- Gradient Overlay -->
                <div v-if="$page.props.auth.restaurant?.navbar_image" class="absolute inset-0 bg-gradient-to-r from-slate-900/80 via-slate-800/40 to-slate-900/80 backdrop-blur-[2px]"></div>
                
                <!-- Primary Navigation Menu -->
                <div class="relative z-10 mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-24 justify-between items-center">
                        <div class="flex items-center gap-6">
                            <!-- Logo -->
                            <div class="flex shrink-0 items-center bg-white/90 dark:bg-slate-900/90 backdrop-blur-md px-3 py-1.5 rounded-2xl shadow-xs border border-white/50 dark:border-slate-700/80 transition-all hover:scale-105 hover:shadow-md">
                                <Link :href="route('dashboard')" class="flex items-center gap-3">
                                    <img v-if="$page.props.auth.restaurant?.logo" :src="$page.props.auth.restaurant.logo" class="h-16 w-auto max-w-[200px] object-contain rounded-xl shadow-xs" alt="Restaurant Logo" />
                                    <ApplicationLogo v-else class="block h-12 w-auto fill-current text-gray-800 dark:text-gray-200" />
                                    <span v-if="$page.props.auth.restaurant" class="text-lg font-extrabold bg-gradient-to-l from-amber-600 to-orange-400 bg-clip-text text-transparent ml-2 drop-shadow-xs font-sans tracking-wide">
                                        {{ $page.props.auth.restaurant.name }}
                                    </span>
                                </Link>
                            </div>

                            <!-- Navigation Links -->
                            <div class="hidden space-x-8 sm:-my-px sm:flex bg-white/90 dark:bg-slate-900/90 backdrop-blur-md px-6 py-2 rounded-2xl shadow-xs border border-white/50 dark:border-slate-700/80">
                                <NavLink
                                    :href="route('dashboard')"
                                    :active="route().current('dashboard')"
                                >
                                    Dashboard
                                </NavLink>
                            </div>
                        </div>

                        <div class="hidden sm:ms-6 sm:flex sm:items-center gap-3">
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

                            <div class="flex items-center bg-white/90 dark:bg-slate-900/90 backdrop-blur-md px-4 py-2 rounded-2xl shadow-xs border border-white/50 dark:border-slate-700/80 transition-all hover:shadow-md">
                                <!-- Settings Dropdown -->
                                <div class="relative ms-3">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                class="inline-flex items-center rounded-md border border-transparent bg-transparent px-3 py-2 text-sm font-semibold leading-4 text-slate-700 transition duration-150 ease-in-out hover:text-slate-900 focus:outline-none dark:text-gray-400 dark:hover:text-gray-300"
                                            >
                                                {{ $page.props.auth.user.name }}

                                                <svg
                                                    class="-me-0.5 ms-2 h-4 w-4"
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
                                        </span>
                                    </template>

                                    <template #content>
                                        <DropdownLink
                                            :href="route('profile.edit')"
                                        >
                                            Profile
                                        </DropdownLink>
                                        <DropdownLink
                                            :href="route('logout')"
                                            method="post"
                                            as="button"
                                        >
                                            Log Out
                                        </DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
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

                <!-- Responsive Navigation Menu -->
                <div
                    :class="{
                        block: showingNavigationDropdown,
                        hidden: !showingNavigationDropdown,
                    }"
                    class="sm:hidden relative z-20 bg-white/95 dark:bg-[#0a0f1d] shadow-lg border-t border-gray-100 dark:border-slate-800 transition-colors"
                >
                    <div class="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink
                            :href="route('dashboard')"
                            :active="route().current('dashboard')"
                        >
                            Dashboard
                        </ResponsiveNavLink>
                    </div>

                    <!-- Responsive Settings Options -->
                    <div
                        class="border-t border-gray-200 pb-3 pt-4 dark:border-gray-800"
                    >
                        <div class="px-4">
                            <div
                                class="text-base font-medium text-gray-800 dark:text-gray-200"
                            >
                                {{ $page.props.auth.user.name }}
                            </div>
                            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                {{ $page.props.auth.user.email }}
                            </div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink :href="route('profile.edit')">
                                Profile
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                :href="route('logout')"
                                method="post"
                                as="button"
                            >
                                Log Out
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
