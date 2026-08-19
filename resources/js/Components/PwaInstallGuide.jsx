import { Download, Share, SquarePlus, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

const afterAuthKey = 'splitshare:pwa-install-guide-after-auth';
const dismissedKey = 'splitshare:pwa-install-guide-dismissed';
const seenThisSessionKey = 'splitshare:pwa-install-guide-seen';

const isAppleMobile = () => /iPhone|iPad|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
const isAndroid = () => /Android/i.test(navigator.userAgent);
const isStandalone = () => window.matchMedia?.('(display-mode: standalone)').matches || window.navigator.standalone === true;

export default function PwaInstallGuide() {
    const [open, setOpen] = useState(false);
    const [platform, setPlatform] = useState(null);
    const [installing, setInstalling] = useState(false);
    const eligibleAfterAuth = useRef(false);

    useEffect(() => {
        if (isStandalone() || localStorage.getItem(dismissedKey) || sessionStorage.getItem(seenThisSessionKey) || !sessionStorage.getItem(afterAuthKey)) return undefined;

        sessionStorage.removeItem(afterAuthKey);
        eligibleAfterAuth.current = true;
        if (isAppleMobile()) {
            setPlatform('ios');
            setOpen(true);
            return undefined;
        }
        if (!isAndroid()) return undefined;

        const offerAndroidInstall = () => {
            if (!eligibleAfterAuth.current || !window.splitShareInstallPrompt) return;
            setPlatform('android');
            setOpen(true);
        };
        offerAndroidInstall();
        window.addEventListener('splitshare:install-prompt-ready', offerAndroidInstall);
        return () => window.removeEventListener('splitshare:install-prompt-ready', offerAndroidInstall);
    }, []);

    useEffect(() => {
        const installed = () => close(false);
        window.addEventListener('appinstalled', installed);
        return () => window.removeEventListener('appinstalled', installed);
    }, []);

    const close = (permanently) => {
        setOpen(false);
        sessionStorage.setItem(seenThisSessionKey, '1');
        if (permanently) localStorage.setItem(dismissedKey, '1');
    };
    const installAndroid = async () => {
        const prompt = window.splitShareInstallPrompt;
        if (!prompt) return;
        setInstalling(true);
        try {
            await prompt.prompt();
            await prompt.userChoice;
        } finally {
            window.splitShareInstallPrompt = null;
            setInstalling(false);
            close(false);
        }
    };

    if (!open) return null;

    return <div className="fixed inset-0 z-[70] flex items-end bg-slate-950/35 p-4 backdrop-blur-[1px] sm:items-center sm:justify-center" role="dialog" aria-modal="true" aria-labelledby="pwa-install-title">
        <section className="w-full max-w-sm rounded-[2rem] bg-white p-6 shadow-2xl sm:p-7">
            <div className="flex items-start justify-between gap-4"><span className="flex size-12 items-center justify-center rounded-2xl bg-[#e0f8ea] text-[#13a856]"><Download size={23}/></span><button type="button" onClick={() => close(false)} className="rounded-full p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="Close install guide"><X size={20}/></button></div>
            <h2 id="pwa-install-title" className="mt-5 text-page-title text-2xl">Install SplitShare</h2>
            <p className="mt-2 text-body text-slate-600">Add SplitShare to your Home Screen for a full-screen app and push notifications.</p>
            {platform === 'ios' ? <ol className="mt-6 space-y-3 text-sm text-slate-700"><li className="flex items-center gap-3"><span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-[#e0f8ea] text-sm font-bold text-[#13a856]">1</span><span>Tap <strong>Share</strong> <Share className="mx-1 inline text-[#13a856]" size={16}/> in your browser.</span></li><li className="flex items-center gap-3"><span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-[#e0f8ea] text-sm font-bold text-[#13a856]">2</span><span>Choose <strong>Add to Home Screen</strong>.</span></li><li className="flex items-center gap-3"><span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-[#e0f8ea] text-sm font-bold text-[#13a856]">3</span><span>Keep <strong>Open as Web App</strong> on, then tap <strong>Add</strong> <SquarePlus className="mx-1 inline text-[#13a856]" size={16}/>.</span></li></ol> : <div className="mt-6 rounded-2xl bg-[#effcf4] p-4 text-sm leading-6 text-slate-700">Install once, then open SplitShare from your app drawer just like any other app.</div>}
            {platform === 'android' && <button type="button" className="ss-button mt-6 w-full justify-center" onClick={installAndroid} disabled={installing}>{installing ? 'Opening install…' : 'Install SplitShare'}</button>}
            <button type="button" className="mt-5 w-full text-sm font-medium text-slate-500 hover:text-slate-800" onClick={() => close(true)}>Don’t show this again</button>
        </section>
    </div>;
}
