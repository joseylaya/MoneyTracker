import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { registerSW } from 'virtual:pwa-register';
import AppErrorBoundary from '@/Components/AppErrorBoundary';

const appName = import.meta.env.VITE_APP_NAME || 'SplitShare';
const isLocalDevelopment = ['127.0.0.1', 'localhost'].includes(window.location.hostname);

if (isLocalDevelopment) {
    // A production service worker can retain hashed chunks between local builds.
    // Keep localhost reliable for feature work; PWA caching remains active on HTTPS deployments.
    navigator.serviceWorker?.getRegistrations().then((registrations) => registrations.forEach((registration) => registration.unregister()));
    window.caches?.keys().then((keys) => keys.filter((key) => key.startsWith('workbox-') || key.startsWith('vite-pwa-')).forEach((key) => window.caches.delete(key)));
} else {
    registerSW({ immediate: true });
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
    progress: {
        color: '#4B5563',
    },
});
