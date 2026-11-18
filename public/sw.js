// ==============================================================================
// TEDALLAL ROYAL SERVICE WORKER (PWA & BACKGROUND WEB PUSH)
// عامل الخدمة الملكي لإدارة التخزين المؤقت والإشعارات الفورية والتثبيت المستقل
// ==============================================================================

const CACHE_NAME = 'tedallal-cache-v1';
const PRECACHE_ASSETS = [
    '/',
    '/site.webmanifest',
    '/favicon.svg',
    '/favicon-192x192.png',
    '/favicon-512x512.png',
    '/apple-touch-icon.png'
];

// 1. Install Event: Cache essential shell and activate immediately
self.addEventListener('install', (event) => {
    self.skipWaiting();
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(PRECACHE_ASSETS).catch((err) => {
                console.warn('[Tedallal SW] Precache partial error:', err);
            });
        })
    );
});

// 2. Activate Event: Claim control immediately and delete older caches
self.addEventListener('activate', (event) => {
    event.waitUntil(
        Promise.all([
            self.clients.claim(),
            caches.keys().then((keys) => {
                return Promise.all(
                    keys.map((key) => {
                        if (key !== CACHE_NAME) {
                            return caches.delete(key);
                        }
                    })
                );
            })
        ])
    );
});

// 3. Fetch Event: Network-first for dynamic navigation, Cache-first for static icons
self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    // Bypass non-GET, API calls, Reverb, or WebSocket connections
    if (
        request.method !== 'GET' ||
        url.pathname.startsWith('/api/') ||
        url.pathname.startsWith('/broadcasting/') ||
        url.pathname.startsWith('/sanctum/') ||
        url.pathname.startsWith('/_debugbar')
    ) {
        return;
    }

    // Static assets (images, icons, fonts) -> Stale-while-revalidate / Cache First
    if (
        url.pathname.match(/\.(png|jpg|jpeg|svg|webp|ico|woff2?|ttf|css|js)$/i) ||
        url.pathname.startsWith('/build/') ||
        url.pathname.startsWith('/images/')
    ) {
        event.respondWith(
            caches.match(request).then((cachedResponse) => {
                const fetchPromise = fetch(request).then((networkResponse) => {
                    if (networkResponse && networkResponse.status === 200) {
                        const responseToCache = networkResponse.clone();
                        caches.open(CACHE_NAME).then((cache) => {
                            cache.put(request, responseToCache);
                        });
                    }
                    return networkResponse;
                }).catch(() => cachedResponse);

                return cachedResponse || fetchPromise;
            })
        );
        return;
    }

    // HTML / Navigation -> Network First with Cache Fallback
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => {
                return caches.match(request).then((cached) => {
                    return cached || caches.match('/');
                });
            })
        );
    }
});

// ==============================================================================
// 4. Background Push Notification Handler (When App is Closed or Phone Locked)
// استقبال الإشعارات في الخلفية حتى عندما يكون التطبيق مغلقاً أو الجهاز مقفلاً
// ==============================================================================
self.addEventListener('push', (event) => {
    let data = {};
    if (event.data) {
        try {
            data = event.data.json();
        } catch (e) {
            data = {
                title: 'إشعار جديد من تدلّل',
                body: event.data.text()
            };
        }
    }

    const title = data.title || 'تدلّل | إشعار جديد 🔔';
    const options = {
        body: data.body || 'لديك تحديث جديد في صالة المطعم.',
        icon: data.icon || '/favicon-192x192.png',
        badge: data.badge || '/favicon-96x96.png',
        data: {
            url: data.url || (data.order_id ? `/admin/orders` : '/admin/dashboard'),
            order_id: data.order_id || null,
            table_id: data.table_id || null,
            timestamp: Date.now()
        },
        vibrate: data.vibrate || [200, 100, 200, 100, 300, 150, 400],
        tag: data.tag || (data.order_id ? `tedallal-order-${data.order_id}` : `tedallal-notification-${Date.now()}`),
        renotify: true,
        requireInteraction: true, // Keeps notification visible until user acts
        actions: data.actions || [
            {
                action: 'open_order',
                title: '👁️ معاينة فورية'
            },
            {
                action: 'dismiss',
                title: 'تم'
            }
        ],
        dir: 'rtl',
        lang: 'ar'
    };

    event.waitUntil(
        self.registration.showNotification(title, options)
    );
});

// 5. Notification Click Handler: Open or Focus targeted window
self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const action = event.action;
    if (action === 'dismiss') {
        return;
    }

    const targetUrl = (event.notification.data && event.notification.data.url) ? event.notification.data.url : '/admin/dashboard';
    const absoluteTargetUrl = new URL(targetUrl, self.location.origin).href;

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
            // Check if there is already a window open with this app
            for (let i = 0; i < windowClients.length; i++) {
                const client = windowClients[i];
                if (client.url.startsWith(self.location.origin) && 'focus' in client) {
                    client.navigate(absoluteTargetUrl);
                    return client.focus();
                }
            }
            // If no open window, open a new one
            if (clients.openWindow) {
                return clients.openWindow(absoluteTargetUrl);
            }
        })
    );
});
