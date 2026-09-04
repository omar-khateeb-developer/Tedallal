<script setup lang="ts">
import { useToast } from '@/composables/useToast';

const { toasts, removeToast } = useToast();
</script>

<template>
    <div class="fixed bottom-4 right-4 z-50 flex flex-col gap-2">
        <TransitionGroup
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="translate-y-4 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-for="toast in toasts"
                :key="toast.id"
                class="flex w-72 flex-col rounded-lg bg-white p-4 shadow-lg ring-1 ring-black ring-opacity-5"
                dir="rtl"
            >
                <div class="flex items-start justify-between">
                    <div>
                        <p class="font-bold text-gray-900">{{ toast.title }}</p>
                        <p class="mt-1 text-sm text-gray-500">{{ toast.message }}</p>
                    </div>
                    <button
                        type="button"
                        class="ml-1 inline-flex rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none"
                        @click="removeToast(toast.id)"
                    >
                        <span class="sr-only">إغلاق</span>
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>
            </div>
        </TransitionGroup>
    </div>
</template>
