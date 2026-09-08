import Avatar from '@/Components/Avatar';
import { money } from '@/utils/money';
import { Link } from '@inertiajs/react';
import { ChevronRight, ClipboardCheck } from 'lucide-react';
import { useMemo } from 'react';

export default function TrackerPlanningSummary({ tracker, members, tasks, plannedExpenses }) {
    const completedCount = tasks.filter((task) => task.completed_at).length;
    const estimateTotal = useMemo(() => plannedExpenses.reduce((sum, item) => sum + Number(item.estimated_amount_minor), 0), [plannedExpenses]);

    return <Link href={route('trackers.planning.index', tracker.id)} className="mt-4 flex items-center gap-4 rounded-[1.45rem] border border-[#c8f1d8] bg-white px-5 py-4 shadow-[0_3px_10px_rgba(15,23,42,.035)] transition hover:border-[#83dfa7] hover:bg-[#f8fefa] hover:shadow-[0_10px_25px_rgba(24,139,70,.08)] focus:outline-none focus:ring-4 focus:ring-[#25bd65]/15">
        <span className="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-[#e8faef] text-[#16b85b]"><ClipboardCheck size={21}/></span>
        <span className="min-w-0 flex-1">
            <span className="block font-display text-lg font-bold text-slate-900">Trip board</span>
            <span className="mt-0.5 block truncate text-sm text-slate-500">{completedCount} of {tasks.length} tasks done · {money(estimateTotal, tracker.currency_code)} estimated</span>
        </span>
        <span className="hidden -space-x-2 sm:flex">{members.slice(0, 3).map((member, index) => <Avatar key={member.id} name={member.name} index={index} size="sm"/>)}</span>
        <ChevronRight size={20} className="shrink-0 text-[#26bf67]"/>
    </Link>;
}
