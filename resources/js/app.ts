import '../css/app.css';
import './bootstrap';
import './echo';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, DefineComponent, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

function syncCustomerDesktopModeGuard() {
    if (typeof window === 'undefined') return;
    try {
        const path = window.location.pathname || '';
        const isCustomerRoute = /^\/(t|m|wheel|wheel-demo|w|r|book)(\/|$)/.test(path);
        const minScreen = Math.min(window.screen.width || 9999, window.screen.height || 9999);
        const isCoarse = window.matchMedia && window.matchMedia('(pointer: coarse)').matches;
        const hasTouch = (navigator.maxTouchPoints || 0) > 0 || isCoarse;
        const iw = window.innerWidth || 0;
        const ih = window.innerHeight || 1;
        const isPortraitTall = (ih / iw) >= 1.65;
        const isPhoneDesktopMode = isCustomerRoute && hasTouch && iw >= 750 && (minScreen <= 560 || (isCoarse && isPortraitTall && iw >= 850));
        document.documentElement.classList.toggle('tld-desktop-mode-phone', Boolean(isPhoneDesktopMode));
    } catch {}
}

if (typeof window !== 'undefined') {
    syncCustomerDesktopModeGuard();
    window.addEventListener('resize', syncCustomerDesktopModeGuard);
    router.on('navigate', syncCustomerDesktopModeGuard);
}

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);

        if (typeof window !== 'undefined' && (window as any).__dismissTedallalSplash) {
            (window as any).__dismissTedallalSplash();
        }
    },
    progress: {
        color: '#4B5563',
    },
});
