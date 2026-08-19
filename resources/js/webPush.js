function base64UrlToUint8Array(value) {
    const padding = '='.repeat((4 - (value.length % 4)) % 4);
    const base64 = (value + padding).replace(/-/g, '+').replace(/_/g, '/');
    return Uint8Array.from(atob(base64), (character) => character.charCodeAt(0));
}

export async function enablePushNotifications({ requestPermission = true } = {}) {
    if (!('Notification' in window) || !('serviceWorker' in navigator) || !('PushManager' in window)) return 'unsupported';

    const permission = requestPermission ? await Notification.requestPermission() : Notification.permission;
    if (permission !== 'granted') return permission === 'denied' ? 'denied' : 'idle';

    const vapidKey = import.meta.env.VITE_VAPID_PUBLIC_KEY;
    if (!vapidKey) throw new Error('Web Push is not configured.');

    const registration = await navigator.serviceWorker.ready;
    const subscription = await registration.pushManager.getSubscription()
        || await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: base64UrlToUint8Array(vapidKey),
        });
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const response = await fetch(route('push-devices.store'), {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf || '',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ subscription: subscription.toJSON() }),
    });

    if (!response.ok) throw new Error('Could not save this device.');
    return 'enabled';
}
