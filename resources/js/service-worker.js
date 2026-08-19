import { cleanupOutdatedCaches, precacheAndRoute } from 'workbox-precaching';

cleanupOutdatedCaches();
precacheAndRoute(self.__WB_MANIFEST);

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

    event.waitUntil(clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
        const existing = windows.find((client) => client.url === destination);
        return existing ? existing.focus() : clients.openWindow(destination);
    }));
});
