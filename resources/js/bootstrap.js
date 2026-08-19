import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

// Android exposes this event only while the page is eligible for its native install prompt.
// Keep it until the authenticated UI is ready to offer the install button.
window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    window.splitShareInstallPrompt = event;
    window.dispatchEvent(new Event('splitshare:install-prompt-ready'));
});

if (import.meta.env.VITE_REVERB_APP_KEY) {
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
        wsPort: Number(import.meta.env.VITE_REVERB_PORT || 80),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT || 443),
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME || 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    });
}
