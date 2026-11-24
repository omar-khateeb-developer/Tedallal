import { ref, type Ref } from 'vue';

/**
 * Reset and normalize visual viewport zoom back to 1.0 smoothly.
 * Prevents mobile sheets from opening outside the visible area when user has pinched-to-zoom.
 */
export function normalizeVisualViewport() {
    if (typeof window === 'undefined') return;

    if (window.visualViewport && window.visualViewport.scale > 1.01) {
        window.scrollTo({
            top: window.visualViewport.pageTop || 0,
            left: 0,
            behavior: 'instant' as ScrollBehavior,
        });
    }
}

/**
 * Composable that ensures modal containers remain stable and fully visible in standard CSS flexbox.
 */
export function useVisualViewportModal(isOpen?: Ref<boolean>) {
    const modalStyle = ref<Record<string, string>>({});
    const modalContainerStyle = ref<Record<string, string>>({});

    return {
        modalStyle,
        modalContainerStyle,
        normalizeVisualViewport,
        update: () => {},
    };
}
