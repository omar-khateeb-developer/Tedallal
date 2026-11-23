import { ref, watch, onUnmounted, type Ref } from 'vue';

/**
 * Reset and normalize visual viewport zoom back to 1.0 smoothly.
 * Prevents mobile sheets from opening outside the visible area when user has pinched-to-zoom.
 */
export function normalizeVisualViewport() {
    if (typeof window === 'undefined') return;

    // 1. Smoothly reset zoom by temporarily restricting maximum-scale
    const meta = document.querySelector('meta[name="viewport"]');
    if (meta) {
        const originalContent = meta.getAttribute('content') || 'width=device-width, initial-scale=1.0';
        meta.setAttribute('content', 'width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no');
        setTimeout(() => {
            meta.setAttribute('content', originalContent.includes('user-scalable') ? originalContent : 'width=device-width, initial-scale=1.0, maximum-scale=5.0');
        }, 350);
    }

    // 2. Align scroll position
    if (window.visualViewport) {
        const targetTop = window.visualViewport.pageTop || window.scrollY || 0;
        window.scrollTo({
            top: targetTop,
            behavior: 'instant' as ScrollBehavior,
        });
    }
}

/**
 * Composable that binds modal containers directly to the mobile Visual Viewport coordinates.
 * Ensures bottom sheets and dialogs are 100% visible in the user's current view regardless of zoom level.
 */
export function useVisualViewportModal(isOpen: Ref<boolean>) {
    const modalStyle = ref<Record<string, string>>({});

    function update() {
        if (typeof window === 'undefined') return;
        const vv = window.visualViewport;
        if (!isOpen.value || !vv) {
            modalStyle.value = {};
            return;
        }

        if (vv.scale > 1.02) {
            modalStyle.value = {
                position: 'fixed',
                top: `${vv.offsetTop}px`,
                left: `${vv.offsetLeft}px`,
                width: `${vv.width}px`,
                height: `${vv.height}px`,
                maxWidth: `${vv.width}px`,
                maxHeight: `${vv.height}px`,
            };
        } else {
            modalStyle.value = {};
        }
    }

    watch(isOpen, (open) => {
        if (open) {
            normalizeVisualViewport();
            update();
            if (typeof window !== 'undefined' && window.visualViewport) {
                window.visualViewport.addEventListener('resize', update);
                window.visualViewport.addEventListener('scroll', update);
            }
        } else {
            modalStyle.value = {};
            if (typeof window !== 'undefined' && window.visualViewport) {
                window.visualViewport.removeEventListener('resize', update);
                window.visualViewport.removeEventListener('scroll', update);
            }
        }
    });

    onUnmounted(() => {
        if (typeof window !== 'undefined' && window.visualViewport) {
            window.visualViewport.removeEventListener('resize', update);
            window.visualViewport.removeEventListener('scroll', update);
        }
    });

    return {
        modalStyle,
        normalizeVisualViewport,
        update,
    };
}
