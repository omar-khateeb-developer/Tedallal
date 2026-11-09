import { ref, onMounted, onUnmounted } from 'vue';

export function useWakeLock() {
    const isSupported = ref(false);
    const isActive = ref(false);
    const userPreferredActive = ref(true); // Default to on for kitchen/POS tablets
    let wakeLockSentinel: any = null;

    async function requestLock(): Promise<boolean> {
        if (!isSupported.value) return false;

        try {
            if (wakeLockSentinel && !wakeLockSentinel.released) {
                isActive.value = true;
                return true;
            }

            wakeLockSentinel = await (navigator as any).wakeLock.request('screen');
            isActive.value = true;

            wakeLockSentinel.addEventListener('release', () => {
                isActive.value = false;
            });

            return true;
        } catch (err: any) {
            // Wake lock request can fail if device battery is extremely low or user denied
            console.warn('Wake Lock request failed:', err?.message || err);
            isActive.value = false;
            return false;
        }
    }

    async function releaseLock(): Promise<void> {
        if (wakeLockSentinel && !wakeLockSentinel.released) {
            try {
                await wakeLockSentinel.release();
            } catch (err) {
                // Ignore release errors
            }
        }
        wakeLockSentinel = null;
        isActive.value = false;
    }

    async function toggleLock(): Promise<boolean> {
        if (isActive.value) {
            userPreferredActive.value = false;
            await releaseLock();
            return false;
        } else {
            userPreferredActive.value = true;
            return await requestLock();
        }
    }

    function handleVisibilityChange() {
        if (document.visibilityState === 'visible' && userPreferredActive.value) {
            requestLock();
        }
    }

    onMounted(() => {
        isSupported.value = typeof window !== 'undefined' && 'wakeLock' in navigator;
        if (isSupported.value) {
            // Attempt auto-activation for staff screens
            requestLock();
            document.addEventListener('visibilitychange', handleVisibilityChange);
        }
    });

    onUnmounted(() => {
        if (isSupported.value) {
            document.removeEventListener('visibilitychange', handleVisibilityChange);
            releaseLock();
        }
    });

    return {
        isSupported,
        isActive,
        requestLock,
        releaseLock,
        toggleLock,
    };
}

