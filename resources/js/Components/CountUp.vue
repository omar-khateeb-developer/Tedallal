<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';

const props = defineProps<{
    to: number | string;
    duration?: number;
}>();

const displayValue = ref<number | string>(0);

function easeOutQuart(x: number): number {
    return 1 - Math.pow(1 - x, 4);
}

function animateValue(endValue: number, duration: number) {
    let startTimestamp: number | null = null;
    const step = (timestamp: number) => {
        if (!startTimestamp) startTimestamp = timestamp;
        const progress = Math.min((timestamp - startTimestamp) / duration, 1);
        const easeProgress = easeOutQuart(progress);
        
        displayValue.value = Number((easeProgress * endValue).toFixed(2));
        
        if (progress < 1) {
            window.requestAnimationFrame(step);
        } else {
            displayValue.value = endValue;
        }
    };
    window.requestAnimationFrame(step);
}

onMounted(() => {
    const numericTo = Number(props.to);
    if (!isNaN(numericTo)) {
        animateValue(numericTo, props.duration || 1500);
    } else {
        displayValue.value = props.to;
    }
});

watch(() => props.to, (newVal) => {
    const numericTo = Number(newVal);
    if (!isNaN(numericTo)) {
        animateValue(numericTo, props.duration || 1500);
    } else {
        displayValue.value = newVal;
    }
});
</script>

<template>
    <span>{{ displayValue }}</span>
</template>
