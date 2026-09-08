import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import TrackerPlanningBoard from '@/Components/TrackerPlanningBoard';
import { Head, Link, usePage } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';

export default function Planning({ tracker, members, tasks, plannedExpenses, canManage }) {
    const { flash } = usePage().props;
    return <AuthenticatedLayout header={<div className="flex items-center gap-3"><Link href={route('trackers.show', tracker.id)} className="flex size-10 items-center justify-center rounded-full text-slate-700" aria-label={`Back to ${tracker.name}`}><ChevronLeft size={28}/></Link><div className="min-w-0"><h1 className="truncate font-display text-xl font-bold">Trip board</h1><p className="truncate text-xs font-semibold text-slate-500">{tracker.name}</p></div></div>}>
        <Head title={`Trip board · ${tracker.name}`}/>
        <div className="page-wrap max-w-5xl">
            {flash.success && <div className="mb-4 rounded-2xl bg-[#e9fbf0] px-4 py-3 text-sm font-medium text-[#0d9b4b]">{flash.success}</div>}
            <TrackerPlanningBoard tracker={tracker} members={members} tasks={tasks} plannedExpenses={plannedExpenses} canManage={canManage}/>
        </div>
    </AuthenticatedLayout>;
}
