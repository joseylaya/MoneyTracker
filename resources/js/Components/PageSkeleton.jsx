function Line({ className = '' }) {
    return <div className={`ss-skeleton ${className}`} />;
}

export default function PageSkeleton({ page }) {
    if (page === 'alerts') {
        return <div className="page-wrap max-w-3xl space-y-3" aria-label="Loading notifications"><Line className="h-7 w-40" />{Array.from({ length: 5 }).map((_, index) => <div key={index} className="rounded-[1.5rem] border border-slate-100 bg-white p-5"><Line className="h-4 w-3/5" /><Line className="mt-3 h-3 w-full" /><Line className="mt-2 h-3 w-2/3" /><Line className="mt-4 h-3 w-28" /></div>)}</div>;
    }

    if (page === 'profile') {
        return <div className="page-wrap max-w-3xl"><div className="ss-card p-6"><div className="flex items-center gap-4"><Line className="size-16 rounded-full" /><div className="space-y-3"><Line className="h-5 w-40" /><Line className="h-3 w-52" /></div></div><div className="mt-8 space-y-5"><Line className="h-3 w-24" /><Line className="h-14 w-full rounded-2xl" /><Line className="h-3 w-28" /><Line className="h-14 w-full rounded-2xl" /></div></div></div>;
    }

    return <div className="page-wrap space-y-5" aria-label="Loading page"><div className="grid gap-4 sm:grid-cols-2"><Line className="h-40 rounded-[1.8rem]" /><Line className="h-40 rounded-[1.8rem]" /></div><div className="flex items-center justify-between"><Line className="h-7 w-36" /><Line className="h-4 w-20" /></div><div className="grid gap-5 lg:grid-cols-2">{Array.from({ length: 4 }).map((_, index) => <div key={index} className="ss-card flex gap-4 p-5"><Line className="size-16 shrink-0 rounded-2xl" /><div className="min-w-0 flex-1 space-y-3"><Line className="h-5 w-3/5" /><Line className="h-3 w-full" /><Line className="h-3 w-2/3" /></div></div>)}</div></div>;
}
