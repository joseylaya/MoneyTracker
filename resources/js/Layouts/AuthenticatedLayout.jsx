import { Link, usePage } from '@inertiajs/react';
import { Bell, Home, ListChecks, UserRound } from 'lucide-react';

const nav = [
    ['home', 'Home', Home],
    ['trackers.index', 'Trackers', ListChecks],
    ['activity.index', 'Activity', Bell],
    ['profile.edit', 'Profile', UserRound],
];

export default function AuthenticatedLayout({ header, children, fullBleed = false }) {
    const user = usePage().props.auth.user;
    const current = route().current();
    if (fullBleed) return <div className="h-[100dvh] overflow-hidden bg-white text-[#0f172a]"><main className="h-full ss-page-transition">{children}</main></div>;
    return <div className="min-h-[100dvh] bg-[#f6f8f7] text-[#0f172a]">
        <div className="mx-auto min-h-[100dvh] max-w-6xl bg-[#f6f8f7] pb-24 shadow-sm sm:pb-8">
            <nav className="sticky top-0 z-30 border-b border-slate-200/80 bg-white/90 px-5 py-3 backdrop-blur">
                <div className="mx-auto flex max-w-5xl items-center justify-between">
                    <Link href={route('home')} className="flex items-center gap-2 font-display text-xl font-bold tracking-tight"><span className="flex size-9 items-center justify-center rounded-xl bg-[#2ecc70] text-white"><ListChecks size={19}/></span><span>SplitShare</span></Link>
                    <div className="flex items-center gap-2"><Link href={route('activity.index')} className="hidden size-10 items-center justify-center rounded-full bg-slate-50 text-slate-500 sm:flex" aria-label="Activity"><Bell size={19}/></Link><Link href={route('profile.edit')} className="flex size-10 items-center justify-center rounded-full bg-[#e0f8ea] font-bold text-[#13a856]" title="Profile">{user.name.slice(0, 1).toUpperCase()}</Link></div>
                </div>
            </nav>
            {header && <header className="bg-white px-5 pb-5 pt-5"><div className="mx-auto max-w-5xl">{header}</div></header>}
            <main className="ss-page-transition">{children}</main>
            <nav className="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white/95 px-3 pb-[max(.65rem,env(safe-area-inset-bottom))] pt-2 backdrop-blur sm:hidden"><div className="mx-auto flex max-w-md items-center justify-around">{nav.map(([name, label, Icon]) => <Link key={name} href={route(name)} className={`flex min-w-16 flex-col items-center gap-1 text-[10px] font-bold uppercase tracking-wider ${current === name || (name === 'trackers.index' && current.startsWith('trackers.')) ? 'text-[#18c968]' : 'text-[#94a3b8]'}`}><Icon size={21} strokeWidth={2.4}/>{label}</Link>)}</div></nav>
        </div>
    </div>;
}
