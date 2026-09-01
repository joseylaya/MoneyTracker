import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import AppErrorBoundary from '@/Components/AppErrorBoundary';
import { registerSW } from 'virtual:pwa-register';

const appName = import.meta.env.VITE_APP_NAME || 'SplitShare';
const isLocalDevelopment = ['127.0.0.1', 'localhost'].includes(window.location.hostname);
document.documentElement.dataset.splitshareBuild = '20260819-overflow';

if (isLocalDevelopment) {
    // A production service worker can retain hashed chunks between local builds.
    // Keep localhost reliable for feature work without removing the Firebase push worker.
    navigator.serviceWorker?.getRegistrations().then((registrations) => registrations
        .filter((registration) => [registration.active, registration.waiting, registration.installing]
            .some((worker) => worker?.scriptURL.includes('/build/sw.js')))
        .forEach((registration) => registration.unregister()));
    window.caches?.keys().then((keys) => keys.filter((key) => key.startsWith('workbox-') || key.startsWith('vite-pwa-')).forEach((key) => window.caches.delete(key)));
} else {
    const hadController = Boolean(navigator.serviceWorker?.controller);
    navigator.serviceWorker?.addEventListener('controllerchange', () => {
        if (hadController && !sessionStorage.getItem('splitshare-pwa-reloaded')) {
            sessionStorage.setItem('splitshare-pwa-reloaded', '1');
            window.location.reload();
        }
    });
    registerSW({ immediate: true, onRegisteredSW: (_workerUrl, registration) => registration?.update().catch(() => {}) });
}

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob('./Pages/**/*.jsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(<AppErrorBoundary><App {...props} /></AppErrorBoundary>);
    },
    progress: false,
});
