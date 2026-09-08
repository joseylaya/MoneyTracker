import { enablePushNotifications as enableFirebasePush, detachFirebaseToken } from '@/firebaseMessaging';
import { enablePushNotifications as enableWebPush, detachWebPushSubscription } from '@/webPush';

const isStandalone = () => window.matchMedia?.('(display-mode: standalone)').matches || window.navigator.standalone === true;
const isIos = () => /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

export function browserPushSupport() {
    if (!('Notification' in window) || !('serviceWorker' in navigator) || !('PushManager' in window)) return 'unsupported';
    if (isIos() && !isStandalone()) return 'unsupported';
    if (Notification.permission === 'denied') return 'denied';
    return Notification.permission === 'granted' ? 'granted' : 'idle';
}

export async function enableBrowserPush({ requestPermission = true } = {}) {
    const support = browserPushSupport();
    if (support === 'unsupported' || support === 'denied') return support;
    if (requestPermission && Notification.permission === 'default') {
        const permission = await Notification.requestPermission();
        if (permission !== 'granted') return permission === 'denied' ? 'denied' : 'idle';
    }
    if (Notification.permission !== 'granted') return 'idle';

    // Installed PWAs retain the existing root Web Push worker. Normal browser
    // tabs use the already-scoped Firebase worker and FCM registration.
    if (isStandalone()) return enableWebPush({ requestPermission: false });

    try {
        const result = await enableFirebasePush({ requestPermission: false });
        if (result === 'enabled') await detachWebPushSubscription({ unsubscribe: true });
        return result === 'enabled' ? result : enableWebPush({ requestPermission: false });
    } catch {
        // Safari and browsers unsupported by Firebase continue through the
        // standards-based Web Push path used by the existing PWA.
        return enableWebPush({ requestPermission: false });
    }
}

export async function detachBrowserPushFromCurrentUser() {
    await Promise.allSettled([detachFirebaseToken(), detachWebPushSubscription()]);
}
