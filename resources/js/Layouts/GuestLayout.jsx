import { Link } from '@inertiajs/react';
import { ListChecks } from 'lucide-react';

export default function GuestLayout({ children }) {
    return (
        <div className="flex min-h-[100dvh] flex-col items-center bg-[radial-gradient(circle_at_top_left,#dff9e9_0%,#f7fbf8_46%,#eef4f1_100%)] px-5 py-10 sm:justify-center">
            <Link href="/" className="flex items-center gap-3 text-slate-900"><span className="flex size-12 items-center justify-center rounded-2xl bg-[#2ecc70] text-white shadow-[0_12px_28px_rgba(46,204,112,.28)]"><ListChecks size={25}/></span><span><span className="block font-display text-2xl font-bold tracking-tight">SplitShare</span><span className="block text-xs font-medium text-[#18a956]">Share clearly. Settle simply.</span></span></Link>
            <div className="ss-page-transition mt-8 w-full overflow-hidden rounded-[2rem] border border-white/80 bg-white/90 px-6 py-7 shadow-[0_22px_55px_rgba(15,23,42,.11)] backdrop-blur sm:max-w-md sm:px-8">
                {children}
            </div>
        </div>
    );
}
