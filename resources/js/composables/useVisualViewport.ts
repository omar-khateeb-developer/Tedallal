import { ref, watch, onUnmounted, type Ref } from 'vue';

/**
 * Normalize scroll offset gently when visual viewport is zoomed on mobile.
 */
export function normalizeVisualViewport() {
    if (typeof window === 'undefined') return;

    if (window.visualViewport && window.visualViewport.scale > 1.02) {
        window.scrollTo({
            top: window.visualViewport.pageTop || window.scrollY || 0,
            left: window.visualViewport.pageLeft || window.scrollX || 0,
            behavior: 'instant' as ScrollBehavior,
        });
    }
}

/**
 * Composable that pins outer modal backdrop overlays to the active Visual Viewport
 * whenever the customer has pinched-to-zoom or when a mobile keyboard shrinks the screen.
 * At normal 1x scale without zoom, it returns empty styles so pure CSS `fixed inset-0` handles layout.
 */
export function useVisualViewportModal(isOpen?: Ref<boolean>) {
    const viewportBackdropStyle = ref<Record<string, string>>({});

    function update() {
        if (typeof window === 'undefined') return;
        const vv = window.visualViewport;
        if (!vv || (isOpen && !isOpen.value)) {
            viewportBackdropStyle.value = {};
            return;
        }

        const isZoomed = vv.scale > 1.02;
        const isKeyboardShrunk = window.innerHeight - vv.height > 80;

        if (isZoomed || isKeyboardShrunk) {
            viewportBackdropStyle.value = {
                position: 'fixed',
                top: `${vv.offsetTop}px`,
                left: `${vv.offsetLeft}px`,
                width: `${vv.width}px`,
                height: `${vv.height}px`,
                right: 'auto',
                bottom: 'auto',
            };
        } else {
            viewportBackdropStyle.value = {};
        }
    }

    function attach() {
        if (typeof window === 'undefined' || !window.visualViewport) return;
        window.visualViewport.addEventListener('resize', update);
        window.visualViewport.addEventListener('scroll', update);
    }

    function detach() {
        if (typeof window === 'undefined' || !window.visualViewport) return;
        window.visualViewport.removeEventListener('resize', update);
        window.visualViewport.removeEventListener('scroll', update);
    }

    if (isOpen) {
        watch(
            isOpen,
            (open) => {
                if (open) {
                    update();
                    attach();
                } else {
                    viewportBackdropStyle.value = {};
                    detach();
                }
            },
            { immediate: true }
        );
    }

    onUnmounted(() => {
        detach();
    });

    return {
        viewportBackdropStyle,
        modalStyle: viewportBackdropStyle,
        modalContainerStyle: ref<Record<string, string>>({}),
        normalizeVisualViewport,
        update,
    };
}
