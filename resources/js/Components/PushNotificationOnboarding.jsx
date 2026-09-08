import { BellRing, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { browserPushSupport, enableBrowserPush } from '@/browserPush';

const afterLoginKey = 'splitshare:push-onboarding-after-login';

export default function PushNotificationOnboarding() {
    const [open, setOpen] = useState(false);
    const [state, setState] = useState('idle');

    useEffect(() => {
        if (!('Notification' in window)) return;

        // Re-register an existing permission/token on every authenticated app
        // session. This repairs rotated Firebase tokens and new deployments
        // without deleting a working device from the server.
        if (Notification.permission === 'granted') {
            enableBrowserPush({ requestPermission: false }).catch(() => {});
        }

        if (!sessionStorage.getItem(afterLoginKey)) return;

        sessionStorage.removeItem(afterLoginKey);

        if (Notification.permission === 'granted') {
            return;
        }

        if (Notification.permission === 'default' && browserPushSupport() === 'idle') {
            setOpen(true);
        }
    }, []);

    const enable = async () => {
        try {
            setState('loading');
            const result = await enableBrowserPush();
            setState(result);
            if (result === 'enabled' || result === 'denied') setOpen(false);
        } catch {
            setState('error');
        }
    };

    if (!open) return null;

    return <div className="fixed inset-0 z-[75] flex items-end bg-slate-950/35 p-4 backdrop-blur-[1px] sm:items-center sm:justify-center" role="dialog" aria-modal="true" aria-labelledby="push-onboarding-title">
        <section className="w-full max-w-sm rounded-[2rem] bg-white p-6 shadow-2xl sm:p-7">
            <div className="flex items-start justify-between gap-4"><span className="flex size-12 items-center justify-center rounded-2xl bg-[#e0f8ea] text-[#13a856]"><BellRing size={23}/></span><button type="button" onClick={() => setOpen(false)} className="rounded-full p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="Not now"><X size={20}/></button></div>
            <h2 id="push-onboarding-title" className="mt-5 text-page-title text-2xl">Never miss an update</h2>
            <p className="mt-2 text-body text-slate-600">Get instant alerts for new chats, expenses, settlements, and reminders.</p>
            <button type="button" className="ss-button mt-6 w-full justify-center" onClick={enable} disabled={state === 'loading'}>{state === 'loading' ? 'Opening notification settings…' : 'Enable notifications'}</button>
            <button type="button" className="mt-5 w-full text-sm font-medium text-slate-500 hover:text-slate-800" onClick={() => setOpen(false)}>Not now</button>
            {state === 'error' && <p className="mt-4 text-center text-sm text-rose-600">Could not enable notifications. Check your connection and try again from Profile.</p>}
        </section>
    </div>;
}
