<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        align?: 'left' | 'right';
        width?: '48' | '56' | '64' | '72';
        contentClasses?: string;
    }>(),
    {
        align: 'right',
        width: '48',
        contentClasses: 'py-1 bg-white dark:bg-[#0a0f1d] dark:border dark:border-slate-800 shadow-xl',
    },
);

const open = ref(false);
const dropdownRef = ref<HTMLElement | null>(null);

const closeOnEscape = (e: KeyboardEvent) => {
    if (open.value && e.key === 'Escape') {
        open.value = false;
    }
};

const closeOnClickOutside = (e: PointerEvent) => {
    if (!open.value) return;
    const target = e.target;
    if (target instanceof Node && dropdownRef.value && !dropdownRef.value.contains(target)) {
        open.value = false;
    }
};

const handleContentClick = (e: MouseEvent) => {
    const target = e.target as HTMLElement | null;
    if (target?.closest('a, button')) {
        open.value = false;
    }
};

onMounted(() => {
    document.addEventListener('keydown', closeOnEscape);
    document.addEventListener('pointerdown', closeOnClickOutside, true);
});

onUnmounted(() => {
    document.removeEventListener('keydown', closeOnEscape);
    document.removeEventListener('pointerdown', closeOnClickOutside, true);
});

const widthClass = computed(() => {
    return {
        '48': 'w-48',
        '56': 'w-56',
        '64': 'w-64',
        '72': 'w-72',
    }[props.width.toString()] || 'w-48';
});

const alignmentClasses = computed(() => {
    if (props.align === 'left') {
        return 'origin-top-left left-0';
    } else if (props.align === 'right') {
        return 'origin-top-right right-0 rtl:right-auto rtl:left-0';
    } else {
        return 'origin-top';
    }
});
</script>

<template>
    <div ref="dropdownRef" class="relative">
        <div @click="open = !open">
            <slot name="trigger" />
        </div>

        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition ease-in duration-75"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div
                v-if="open"
                class="absolute top-full z-50 mt-2 rounded-2xl shadow-2xl"
                :class="[widthClass, alignmentClasses]"
                @click="handleContentClick"
            >
                <div
                    class="rounded-2xl ring-1 ring-black/5 dark:ring-white/10 overflow-hidden shadow-2xl"
                    :class="contentClasses"
                >
                    <slot name="content" />
                </div>
            </div>
        </Transition>
    </div>
</template>
