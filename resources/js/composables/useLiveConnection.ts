import { ref, onMounted, onUnmounted } from 'vue';

export function useLiveConnection(onReconnectCallback?: () => void) {
    const isOnline = ref(typeof navigator !== 'undefined' ? navigator.onLine : true);
    const isSocketConnected = ref(true);
    const wasEverDisconnected = ref(false);
    const showReconnectedBanner = ref(false);
    let bannerTimeout: any = null;

    function handleOnline() {
        isOnline.value = true;
        checkAndNotifyReconnected();
    }

    function handleOffline() {
        isOnline.value = false;
        wasEverDisconnected.value = true;
    }

    function checkAndNotifyReconnected() {
        if (isOnline.value && isSocketConnected.value && wasEverDisconnected.value) {
            showReconnectedBanner.value = true;
            wasEverDisconnected.value = false;

            if (onReconnectCallback) {
                try {
                    onReconnectCallback();
                } catch (e) {
                    console.error('Error during live connection reload callback:', e);
                }
            }

            if (bannerTimeout) clearTimeout(bannerTimeout);
            bannerTimeout = setTimeout(() => {
                showReconnectedBanner.value = false;
            }, 4500);
        }
    }

    function setupEchoListener() {
        try {
            const pusher = (window as any).Echo?.connector?.pusher;
            if (pusher && pusher.connection) {
                pusher.connection.bind('state_change', (states: { previous: string; current: string }) => {
                    const current = states.current;
                    if (current === 'connected') {
                        isSocketConnected.value = true;
                        checkAndNotifyReconnected();
                    } else if (['unavailable', 'failed', 'disconnected'].includes(current)) {
                        isSocketConnected.value = false;
                        wasEverDisconnected.value = true;
                    }
                });
            }
        } catch (e) {
            // Echo or pusher might not be immediately ready
        }
    }

    function reconnectNow() {
        try {
            const pusher = (window as any).Echo?.connector?.pusher;
            if (pusher && pusher.connect) {
                pusher.connect();
            }
        } catch (e) {}

        if (onReconnectCallback) {
            onReconnectCallback();
        }
    }

    onMounted(() => {
        window.addEventListener('online', handleOnline);
        window.addEventListener('offline', handleOffline);

        // Allow Echo a moment to initialize if needed
        setTimeout(setupEchoListener, 1000);
    });

    onUnmounted(() => {
        window.removeEventListener('online', handleOnline);
        window.removeEventListener('offline', handleOffline);
        if (bannerTimeout) clearTimeout(bannerTimeout);
    });

    return {
        isOnline,
        isSocketConnected,
        showReconnectedBanner,
        reconnectNow,
    };
}

