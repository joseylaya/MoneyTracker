<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class FirebaseMessagingServiceWorkerController extends Controller
{
    public function __invoke(): Response
    {
        $config = json_encode([
            'apiKey' => env('VITE_FIREBASE_API_KEY'),
            'authDomain' => env('VITE_FIREBASE_AUTH_DOMAIN'),
            'projectId' => env('VITE_FIREBASE_PROJECT_ID'),
            'messagingSenderId' => env('VITE_FIREBASE_MESSAGING_SENDER_ID'),
            'appId' => env('VITE_FIREBASE_APP_ID'),
        ], JSON_UNESCAPED_SLASHES);

        $script = <<<JS
importScripts('https://www.gstatic.com/firebasejs/10.13.2/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.13.2/firebase-messaging-compat.js');
firebase.initializeApp({$config});
const messaging = firebase.messaging();
messaging.onBackgroundMessage((payload) => {
  const data = payload.data || {};
  const badgeCount = Number(data.badge_count || 0);
  if (Number.isFinite(badgeCount) && self.navigator.setAppBadge) self.navigator.setAppBadge(badgeCount);
  return self.registration.showNotification(payload.notification?.title || data.title || 'SplitShare', {
    body: payload.notification?.body || data.body || '', data: { url: data.url || '/' },
    icon: '/icons/splitshare-192.png', badge: '/icons/splitshare-192.png'
  });
});
self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const destination = new URL(event.notification.data?.url || '/', self.location.origin).href;
  event.waitUntil(clients.matchAll({ type: 'window', includeUncontrolled: true }).then(async (windows) => {
    const existing = windows.find((client) => new URL(client.url).origin === self.location.origin);
    if (!existing) return clients.openWindow(destination);
    try {
      await existing.navigate(destination);
      return existing.focus();
    } catch {
      return clients.openWindow(destination);
    }
  }));
});
JS;

        return response($script, 200, [
            'Content-Type' => 'application/javascript',
            'Service-Worker-Allowed' => '/',
            'Cache-Control' => 'no-cache',
        ]);
    }
}
