import { getApp, getApps, initializeApp } from 'firebase/app';
import { getMessaging, getToken, isSupported, onMessage } from 'firebase/messaging';

const config = {
    apiKey: import.meta.env.VITE_FIREBASE_API_KEY,
    authDomain: import.meta.env.VITE_FIREBASE_AUTH_DOMAIN,
    projectId: import.meta.env.VITE_FIREBASE_PROJECT_ID,
    messagingSenderId: import.meta.env.VITE_FIREBASE_MESSAGING_SENDER_ID,
    appId: import.meta.env.VITE_FIREBASE_APP_ID,
};
let foregroundListenerStarted = false;
const storedTokenKey = 'splitshare:fcm-token';

function csrfHeaders() {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    return { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf || '', 'X-Requested-With': 'XMLHttpRequest' };
}

async function removeServerToken(token) {
    if (!token) return;
    await fetch(route('push-devices.destroy'), {
        method: 'DELETE', credentials: 'same-origin', headers: csrfHeaders(), body: JSON.stringify({ token }),
    });
}

export async function enablePushNotifications({ requestPermission = true } = {}) {
    if (!('Notification' in window) || !('serviceWorker' in navigator) || !(await isSupported())) return 'unsupported';

    const permission = requestPermission ? await Notification.requestPermission() : Notification.permission;
    if (permission !== 'granted') return permission === 'denied' ? 'denied' : 'idle';

    const app = getApps().length ? getApp() : initializeApp(config);
    const registration = await navigator.serviceWorker.register('/firebase-messaging-sw.js', { scope: '/firebase-messaging/' });
    const token = await getToken(getMessaging(app), {
        vapidKey: import.meta.env.VITE_FIREBASE_VAPID_KEY,
        serviceWorkerRegistration: registration,
    });
    if (!token) throw new Error('No push token returned.');

    const response = await fetch(route('push-devices.store'), {
        method: 'POST',
        credentials: 'same-origin',
        headers: csrfHeaders(),
        body: JSON.stringify({ token }),
    });

    if (!response.ok) throw new Error('Could not save this device.');
    const previousToken = localStorage.getItem(storedTokenKey);
    localStorage.setItem(storedTokenKey, token);
    if (previousToken && previousToken !== token) await removeServerToken(previousToken).catch(() => {});
    await startForegroundPushNotifications();
    return 'enabled';
}

export async function detachFirebaseToken() {
    const token = localStorage.getItem(storedTokenKey);
    if (!token) return;
    await removeServerToken(token).catch(() => {});
}

export async function startForegroundPushNotifications() {
    if (!('Notification' in window) || Notification.permission !== 'granted' || !(await isSupported())) return;

    const registration = await navigator.serviceWorker.getRegistration('/firebase-messaging/');
    if (!registration || foregroundListenerStarted) return;

    const app = getApps().length ? getApp() : initializeApp(config);
    foregroundListenerStarted = true;
    onMessage(getMessaging(app), (payload) => registration.showNotification(
        payload.notification?.title || payload.data?.title || 'SplitShare',
        {
            body: payload.notification?.body || payload.data?.body || '',
            data: { url: payload.data?.url || '/' },
            icon: '/icons/splitshare-192.png',
        },
    ));
}
