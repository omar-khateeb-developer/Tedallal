import { ref } from 'vue';

export interface Toast {
    id: number;
    title: string;
    message: string;
    type: 'success' | 'info' | 'warning' | 'error';
}

const toasts = ref<Toast[]>([]);
let nextId = 1;

export function useToast() {
    function showToast(
        title: string,
        message: string,
        type: 'success' | 'info' | 'warning' | 'error' = 'info',
        duration = 4000
    ) {
        const id = nextId++;
        toasts.value.push({ id, title, message, type });
        
        setTimeout(() => {
            removeToast(id);
        }, duration);
    }

    function removeToast(id: number) {
        const index = toasts.value.findIndex((t) => t.id === id);
        if (index > -1) {
            toasts.value.splice(index, 1);
        }
    }

    return {
        toasts,
        showToast,
        removeToast,
    };
}
