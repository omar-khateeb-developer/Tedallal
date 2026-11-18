import { ref, onMounted } from 'vue';

const isInstallable = ref(false);
const isInstalled = ref(false);
const isIOS = ref(false);
const isStandalone = ref(false);
const deferredPrompt = ref<any>(null);

// Push Notification States
const pushPermission = ref<NotificationPermission>('default');
const isPushSubscribed = ref(false);
const isPushLoading = ref(false);

function urlBase64ToUint8Array(base64String: string) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding)
        .replace(/-/g, '+')
        .replace(/_/g, '/');

    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);

    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

export function usePwa() {
    onMounted(() => {
        if (typeof window === 'undefined') return;

        // Check if running in standalone mode (already installed as app)
        const isStandaloneMode =
            window.matchMedia('(display-mode: standalone)').matches ||
            (window.navigator as any).standalone === true ||
            document.referrer.includes('android-app://');

        isStandalone.value = isStandaloneMode;
        if (isStandaloneMode) {
            isInstalled.value = true;
            isInstallable.value = false;
        }

        // Detect iOS
        const userAgent = window.navigator.userAgent.toLowerCase();
        isIOS.value = /iphone|ipad|ipod/.test(userAgent) && !(window as any).MSStream;

        // On iOS, if not standalone, it can always be installed via Share menu
        if (isIOS.value && !isStandaloneMode) {
            isInstallable.value = true;
        }

        // Listen for native Chromium beforeinstallprompt event
        window.addEventListener('beforeinstallprompt', (e: Event) => {
            e.preventDefault();
            deferredPrompt.value = e;
            isInstallable.value = true;
        });

        // Listen for appinstalled
        window.addEventListener('appinstalled', () => {
            isInstalled.value = true;
            isInstallable.value = false;
            deferredPrompt.value = null;
            console.log('[Tedallal PWA] App successfully installed on device!');
        });

        // Register Service Worker
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js').then((registration) => {
                console.log('[Tedallal SW] Registered with scope:', registration.scope);
                
                // Check initial push subscription status
                if ('pushManager' in registration) {
                    registration.pushManager.getSubscription().then((sub) => {
                        isPushSubscribed.value = !!sub;
                    });
                }
            }).catch((err) => {
                console.warn('[Tedallal SW] Registration failed:', err);
            });
        }

        // Check current notification permission
        if ('Notification' in window) {
            pushPermission.value = Notification.permission;
        }
    });

    // Trigger installation
    async function promptInstall(): Promise<'accepted' | 'dismissed' | 'ios_modal'> {
        if (isIOS.value && !isStandalone.value) {
            return 'ios_modal';
        }

        if (!deferredPrompt.value) {
            return 'dismissed';
        }

        try {
            deferredPrompt.value.prompt();
            const choiceResult = await deferredPrompt.value.userChoice;
            if (choiceResult.outcome === 'accepted') {
                isInstalled.value = true;
                isInstallable.value = false;
                deferredPrompt.value = null;
                return 'accepted';
            }
            return 'dismissed';
        } catch (err) {
            console.error('[Tedallal PWA] Prompt install error:', err);
            return 'dismissed';
        }
    }

    // Subscribe to Background WebPush Notifications
    async function subscribeToPush(vapidPublicKey: string): Promise<boolean> {
        if (typeof window === 'undefined' || !('serviceWorker' in navigator) || !('PushManager' in window)) {
            console.warn('[Tedallal Push] Push notifications not supported on this browser.');
            return false;
        }

        isPushLoading.value = true;
        try {
            // 1. Request user permission
            const permission = await Notification.requestPermission();
            pushPermission.value = permission;
            if (permission !== 'granted') {
                isPushLoading.value = false;
                return false;
            }

            // 2. Wait for service worker ready
            const registration = await navigator.serviceWorker.ready;

            // 3. Subscribe via PushManager with VAPID key
            const convertedKey = urlBase64ToUint8Array(vapidPublicKey);
            let subscription = await registration.pushManager.getSubscription();
            if (!subscription) {
                subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: convertedKey
                });
            }

            // 4. Send subscription credentials to backend
            const response = await fetch('/api/push/subscribe', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || ''
                },
                body: JSON.stringify(subscription.toJSON())
            });

            if (response.ok) {
                isPushSubscribed.value = true;
                isPushLoading.value = false;
                return true;
            } else {
                console.error('[Tedallal Push] Failed to store subscription on server:', await response.text());
                isPushLoading.value = false;
                return false;
            }
        } catch (err) {
            console.error('[Tedallal Push] Subscription error:', err);
            isPushLoading.value = false;
            return false;
        }
    }

    // Unsubscribe from Push Notifications
    async function unsubscribeFromPush(): Promise<boolean> {
        if (!('serviceWorker' in navigator)) return false;
        isPushLoading.value = true;
        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.getSubscription();
            if (subscription) {
                // Inform server
                await fetch('/api/push/unsubscribe', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || ''
                    },
                    body: JSON.stringify({ endpoint: subscription.endpoint })
                }).catch(() => {});

                // Unsubscribe from browser
                await subscription.unsubscribe();
            }
            isPushSubscribed.value = false;
            isPushLoading.value = false;
            return true;
        } catch (err) {
            console.error('[Tedallal Push] Unsubscribe error:', err);
            isPushLoading.value = false;
            return false;
        }
    }

    return {
        isInstallable,
        isInstalled,
        isIOS,
        isStandalone,
        pushPermission,
        isPushSubscribed,
        isPushLoading,
        promptInstall,
        subscribeToPush,
        unsubscribeFromPush
    };
}
