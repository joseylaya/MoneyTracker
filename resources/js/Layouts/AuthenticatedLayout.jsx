import { Link, router, usePage } from '@inertiajs/react';
import { Bell, Home, ListChecks, UserRound } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import PageSkeleton from '@/Components/PageSkeleton';
import PwaInstallGuide from '@/Components/PwaInstallGuide';
import PushNotificationOnboarding from '@/Components/PushNotificationOnboarding';

const nav = [
    ['home', 'Home', Home],
    ['trackers.index', 'Trackers', ListChecks],
    ['notifications.index', 'Alerts', Bell],
    ['profile.edit', 'Profile', UserRound],
];

export default function AuthenticatedLayout({ header, children, fullBleed = false, floatingAction = null }) {
    const { auth, notificationUnreadCount = 0 } = usePage().props;
    const user = auth.user;
    const current = route().current();
    const [loading, setLoading] = useState(false);
    const [unreadCount, setUnreadCount] = useState(notificationUnreadCount);
    const loadingTimer = useRef(null);
    const loadingSafetyTimer = useRef(null);
    const currentNavIndex = nav.findIndex(([name]) => current === name || (name === 'trackers.index' && current.startsWith('trackers.')));
    const skeletonPage = nav[currentNavIndex]?.[0] === 'notifications.index' ? 'alerts' : nav[currentNavIndex]?.[0] === 'profile.edit' ? 'profile' : 'trackers';
    useEffect(() => {
        setUnreadCount(notificationUnreadCount);
    }, [notificationUnreadCount]);
    useEffect(() => {
        if (!window.Echo || !user?.id) return undefined;

        const channelName = `App.Models.User.${user.id}`;
        const channel = window.Echo.private(channelName);
        channel.listen('.tracker.notification.created', ({ badge_count: badgeCount }) => {
            const nextCount = Number(badgeCount);
            setUnreadCount((currentCount) => Number.isFinite(nextCount) ? nextCount : currentCount + 1);
        });

        return () => window.Echo.leave(`private-${channelName}`);
    }, [user?.id]);
    useEffect(() => {
        if (!('setAppBadge' in navigator)) return;
        if (unreadCount > 0) navigator.setAppBadge(unreadCount).catch(() => {});
        else navigator.clearAppBadge?.().catch(() => {});
    }, [unreadCount]);
    useEffect(() => {
        const cancelTimer = () => {
            if (loadingTimer.current) window.clearTimeout(loadingTimer.current);
            if (loadingSafetyTimer.current) window.clearTimeout(loadingSafetyTimer.current);
            loadingTimer.current = null;
            loadingSafetyTimer.current = null;
        };
        const removeStart = router.on('start', (event) => {
            if (event.detail.visit.method !== 'get') return;
            cancelTimer();
            loadingTimer.current = window.setTimeout(() => setLoading(true), 120);
            loadingSafetyTimer.current = window.setTimeout(() => setLoading(false), 12000);
        });
        const removeFinish = router.on('finish', () => {
            cancelTimer();
            setLoading(false);
        });
        return () => {
            cancelTimer();
            removeStart();
            removeFinish();
        };
    }, []);
    if (fullBleed) return <div className="h-[100dvh] overflow-hidden bg-white text-[#0f172a]"><main className="h-full ss-page-transition">{children}</main></div>;
    return <div className="min-h-[100dvh] overflow-x-hidden bg-[#f6f8f7] text-[#0f172a]">
        <div className="mx-auto min-h-[100dvh] max-w-6xl bg-[#f6f8f7] pb-24 shadow-sm sm:pb-8">
            <nav className="sticky top-0 z-30 border-b border-slate-200/80 bg-white/90 px-5 py-3 backdrop-blur">
                <div className="mx-auto flex max-w-5xl items-center justify-between">
                    <Link href={route('home')} className="flex items-center gap-2 font-display text-xl font-bold tracking-tight"><span className="flex size-9 items-center justify-center rounded-xl bg-[#2ecc70] text-white"><ListChecks size={19}/></span><span>SplitShare</span></Link>
                    <div className="flex items-center gap-2"><Link href={route('notifications.index')} className="relative hidden size-10 items-center justify-center rounded-full bg-slate-50 text-slate-500 sm:flex" aria-label="Notifications"><Bell size={19}/>{unreadCount > 0 && <span className="absolute -right-1 -top-1 flex min-w-5 justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold leading-5 text-white">{unreadCount > 99 ? '99+' : unreadCount}</span>}</Link><Link href={route('profile.edit')} className="flex size-10 items-center justify-center overflow-hidden rounded-full bg-[#e0f8ea] font-bold text-[#13a856]" title="Profile">{user.avatar_url ? <img src={user.avatar_url} alt="" className="size-full object-cover"/> : user.name.slice(0, 1).toUpperCase()}</Link></div>
                </div>
            </nav>
            {header && <header className="bg-white px-5 pb-5 pt-5"><div className="mx-auto max-w-5xl">{header}</div></header>}
            <main aria-busy={loading}>{loading ? <PageSkeleton page={skeletonPage} /> : children}</main>
            {floatingAction}
            <PwaInstallGuide />
            <PushNotificationOnboarding />
            <nav className="ss-mobile-bottom-nav fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white/95 px-3 pb-[max(.65rem,env(safe-area-inset-bottom))] pt-2 backdrop-blur sm:hidden"><div className="mx-auto flex max-w-md items-center justify-around">{nav.map(([name, label, Icon]) => <Link key={name} href={route(name)} className={`flex min-w-16 flex-col items-center gap-1 text-[10px] font-bold uppercase tracking-wider ${current === name || (name === 'trackers.index' && current.startsWith('trackers.')) ? 'text-[#18c968]' : 'text-[#94a3b8]'}`}><span className="relative"><Icon size={21} strokeWidth={2.4}/>{name === 'notifications.index' && unreadCount > 0 && <span className="absolute -right-3 -top-2 flex min-w-5 justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold leading-5 text-white">{unreadCount > 99 ? '99+' : unreadCount}</span>}</span>{label}</Link>)}</div></nav>
        </div>
    </div>;
}
