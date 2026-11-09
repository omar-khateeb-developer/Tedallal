<script setup lang="ts">
defineProps<{
    isOnline: boolean;
    isSocketConnected: boolean;
    showReconnected: boolean;
}>();

const emit = defineEmits<{
    (e: 'reconnect'): void;
}>();
</script>

<template>
    <div>
        <!-- تحذير انقطاع الاتصال -->
        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="transform -translate-y-4 opacity-0"
            enter-to-class="transform translate-y-0 opacity-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="transform translate-y-0 opacity-100"
            leave-to-class="transform -translate-y-4 opacity-0"
        >
            <div
                v-if="!isOnline || !isSocketConnected"
                class="fixed top-3 left-1/2 -translate-x-1/2 z-50 w-11/12 max-w-xl bg-amber-500 text-stone-950 font-bold px-4 py-2.5 rounded-2xl shadow-2xl flex items-center justify-between gap-3 border-2 border-amber-300 animate-pulse select-none"
                dir="rtl"
            >
                <div class="flex items-center gap-2 text-xs sm:text-sm">
                    <span class="text-lg">⚠️</span>
                    <span>
                        {{ !isOnline ? 'انقطع اتصال الإنترنت بجهازك!' : 'انقطع الاتصال المباشر بالخادم - جاري المحاولة...' }}
                    </span>
                </div>
                <button
                    @click="emit('reconnect')"
                    type="button"
                    class="bg-stone-900 hover:bg-stone-800 text-amber-300 text-xs px-3 py-1.5 rounded-xl font-black shadow-sm shrink-0 transition-all cursor-pointer flex items-center gap-1 active:scale-95"
                >
                    <span>🔄</span>
                    <span>تحديث فوري</span>
                </button>
            </div>
        </Transition>

        <!-- إشعار استعادة الاتصال -->
        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="transform -translate-y-4 opacity-0"
            enter-to-class="transform translate-y-0 opacity-100"
            leave-active-class="transition duration-300 ease-in"
            leave-from-class="transform translate-y-0 opacity-100"
            leave-to-class="transform -translate-y-4 opacity-0"
        >
            <div
                v-if="showReconnected && isOnline && isSocketConnected"
                class="fixed top-3 left-1/2 -translate-x-1/2 z-50 w-11/12 max-w-md bg-emerald-600 text-white font-bold px-4 py-2 rounded-2xl shadow-xl flex items-center justify-center gap-2 border border-emerald-400 select-none text-xs sm:text-sm"
                dir="rtl"
            >
                <span>✅</span>
                <span>تم استعادة الاتصال بالخادم بنجاح ومزامنة الطلبات.</span>
            </div>
        </Transition>
    </div>
</template>

