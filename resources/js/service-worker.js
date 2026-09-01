import { cleanupOutdatedCaches, precacheAndRoute } from 'workbox-precaching';
import { clientsClaim } from 'workbox-core';

// Take control as soon as a new release is installed so an old shell cannot
// keep requesting JavaScript chunks that no longer exist after deployment.
self.skipWaiting();
clientsClaim();
cleanupOutdatedCaches();
const precacheManifest = self.__WB_MANIFEST;
precacheAndRoute(precacheManifest);

self.addEventListener('push', (event) => {
    const payload = event.data?.json?.() || {};
    const badgeCount = Number(payload.badge_count || 0);

    if (Number.isFinite(badgeCount) && self.navigator.setAppBadge) {
        event.waitUntil(self.navigator.setAppBadge(badgeCount));
    }

    event.waitUntil(self.registration.showNotification(payload.title || 'SplitShare', {
        body: payload.body || '',
        data: { url: payload.url || '/' },
        icon: '/icons/splitshare-192.png',
        badge: '/icons/splitshare-192.png',
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const destination = new URL(event.notification.data?.url || '/', self.location.origin).href;

    event.waitUntil(clients.matchAll({ type: 'window', includeUncontrolled: true }).then(async (windows) => {
        const existing = windows.find((client) => new URL(client.url).origin === self.location.origin);
        if (!existing) return clients.openWindow(destination);

        try {
            await existing.navigate(destination);
        } catch {
            return clients.openWindow(destination);
        }

        return existing.focus();
    }));
});
