import Avatar from '@/Components/Avatar';
import { money, shortDate } from '@/utils/money';
import { router, useForm } from '@inertiajs/react';
import { Check, Circle, ClipboardCheck, Plus, Sparkles, Trash2, WalletCards, X } from 'lucide-react';
import { useMemo, useState } from 'react';

const inputClass = 'h-12 w-full rounded-2xl border border-slate-200 bg-white px-4 text-base text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-[#27c56b] focus:ring-4 focus:ring-[#27c56b]/10';

function AssigneeSelect({ members, value, onChange }) {
    return <select value={value} onChange={(event) => onChange(event.target.value)} className={inputClass}>
        <option value="">Anyone</option>
        {members.map((member) => <option key={member.id} value={member.id}>{member.name}</option>)}
    </select>;
}

export default function TrackerPlanningBoard({ tracker, members, tasks, plannedExpenses, canManage }) {
    const [panel, setPanel] = useState(null);
    const [updatingTask, setUpdatingTask] = useState(null);
    const taskForm = useForm({ title: '', assigned_to_user_id: '' });
    const expenseForm = useForm({ description: '', amount: '', assigned_to_user_id: '', expected_date: '' });
    const completedCount = tasks.filter((task) => task.completed_at).length;
    const progress = tasks.length ? Math.round((completedCount / tasks.length) * 100) : 0;
    const estimateTotal = useMemo(() => plannedExpenses.reduce((sum, item) => sum + Number(item.estimated_amount_minor), 0), [plannedExpenses]);

    const addTask = (event) => {
        event.preventDefault();
        taskForm.post(route('trackers.tasks.store', tracker.id), {
            preserveScroll: true,
            onSuccess: () => { taskForm.reset(); setPanel(null); },
        });
    };
    const addEstimate = (event) => {
        event.preventDefault();
        expenseForm.post(route('trackers.planned-expenses.store', tracker.id), {
            preserveScroll: true,
            onSuccess: () => { expenseForm.reset(); setPanel(null); },
        });
    };
    const toggleTask = (task) => {
        setUpdatingTask(task.id);
        router.patch(route('trackers.tasks.toggle', [tracker.id, task.id]), {}, {
            preserveScroll: true,
            only: ['tasks', 'flash'],
            onFinish: () => setUpdatingTask(null),
        });
    };

    return <section className="mt-5 overflow-hidden rounded-[1.75rem] border border-[#bceecf] bg-gradient-to-br from-[#f5fff9] via-white to-[#effbf4] shadow-[0_12px_35px_rgba(17,112,57,.08)]">
        <div className="border-b border-[#d9f4e4] px-5 pb-5 pt-5 sm:px-6">
            <div className="flex items-start justify-between gap-4">
                <div><div className="flex items-center gap-2 text-[#13984a]"><Sparkles size={16}/><span className="text-xs font-bold uppercase tracking-[.13em]">Trip board</span></div><h2 className="mt-1 font-display text-2xl font-bold text-slate-950">Get everyone ready</h2></div>
                <div className="flex -space-x-2">{members.slice(0, 4).map((member, index) => <Avatar key={member.id} name={member.name} index={index} size="sm"/>)}{members.length > 4 && <span className="flex size-8 items-center justify-center rounded-full border-2 border-white bg-slate-800 text-[10px] font-bold text-white">+{members.length - 4}</span>}</div>
            </div>
            <div className="mt-5 grid grid-cols-2 gap-3">
                <div className="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100"><div className="flex items-center gap-2 text-sm font-semibold text-slate-500"><ClipboardCheck size={17} className="text-[#1bb85b]"/>Tasks</div><div className="mt-2 flex items-end justify-between gap-2"><p className="font-display text-2xl font-bold text-slate-950">{completedCount}<span className="text-base text-slate-400">/{tasks.length}</span></p><span className="text-sm font-bold text-[#169c4d]">{progress}%</span></div><div className="mt-3 h-2 overflow-hidden rounded-full bg-slate-100"><div className="h-full rounded-full bg-[#25c468] transition-all duration-500" style={{ width: `${progress}%` }}/></div></div>
                <div className="rounded-2xl bg-[#123d2a] p-4 text-white shadow-sm"><div className="flex items-center gap-2 text-sm font-semibold text-white/65"><WalletCards size={17} className="text-[#5ee493]"/>Future estimate</div><p className="mt-2 truncate font-display text-2xl font-bold">{money(estimateTotal, tracker.currency_code)}</p><p className="mt-1 text-xs text-white/55">{plannedExpenses.length} planned item{plannedExpenses.length === 1 ? '' : 's'}</p></div>
            </div>
        </div>

        <div className="grid lg:grid-cols-2 lg:divide-x lg:divide-[#d9f4e4]">
            <div className="p-5 sm:p-6">
                <div className="flex items-center justify-between"><div><h3 className="font-display text-xl font-bold">To-do list</h3><p className="mt-0.5 text-sm text-slate-500">Tap a circle when it is done.</p></div>{canManage && <button type="button" onClick={() => setPanel(panel === 'task' ? null : 'task')} className="flex size-11 items-center justify-center rounded-2xl bg-[#1dbd61] text-white shadow-[0_7px_18px_rgba(29,189,97,.24)] transition hover:-translate-y-0.5" aria-label="Add task">{panel === 'task' ? <X size={20}/> : <Plus size={21}/>}</button>}</div>
                {panel === 'task' && <form onSubmit={addTask} className="mt-4 space-y-3 rounded-3xl border border-[#c9efd8] bg-white p-4 shadow-lg shadow-emerald-900/5">
                    <input value={taskForm.data.title} onChange={(event) => taskForm.setData('title', event.target.value)} className={inputClass} placeholder="What needs to be done?" autoFocus/>
                    <AssigneeSelect members={members} value={taskForm.data.assigned_to_user_id} onChange={(value) => taskForm.setData('assigned_to_user_id', value)}/>
                    {(taskForm.errors.title || taskForm.errors.assigned_to_user_id) && <p className="text-sm font-medium text-rose-600">{taskForm.errors.title || taskForm.errors.assigned_to_user_id}</p>}
                    <button disabled={taskForm.processing} className="ss-button h-12 w-full">{taskForm.processing ? 'Adding…' : 'Add task'}</button>
                </form>}
                <div className="mt-4 space-y-2">
                    {tasks.length === 0 && <button type="button" onClick={() => canManage && setPanel('task')} className="w-full rounded-3xl border border-dashed border-[#a8dfbd] bg-white/70 px-5 py-8 text-center"><Circle size={25} className="mx-auto text-[#31bf6b]"/><p className="mt-3 font-bold text-slate-800">Nothing on the list yet</p><p className="mt-1 text-sm text-slate-500">Add the first thing your group needs.</p></button>}
                    {tasks.map((task, index) => <div key={task.id} className={`group flex items-center gap-3 rounded-2xl border p-3 transition-all ${task.completed_at ? 'border-transparent bg-white/55 opacity-65' : 'border-slate-100 bg-white shadow-sm hover:border-[#b9eacb]'}`}>
                        <button type="button" disabled={updatingTask === task.id} onClick={() => toggleTask(task)} className={`flex size-9 shrink-0 items-center justify-center rounded-xl border-2 transition ${task.completed_at ? 'border-[#20b85e] bg-[#20b85e] text-white' : 'border-slate-300 bg-white text-transparent hover:border-[#20b85e]'}`} aria-label={task.completed_at ? `Mark ${task.title} incomplete` : `Mark ${task.title} complete`}><Check size={19}/></button>
                        <div className="min-w-0 flex-1"><p className={`font-semibold text-slate-900 ${task.completed_at ? 'line-through' : ''}`}>{task.title}</p><div className="mt-1 flex items-center text-xs text-slate-500">{task.assignee ? <span className="flex items-center gap-1.5"><Avatar name={task.assignee.name} index={index} size="sm"/><span className="max-w-28 truncate font-medium">{task.assignee.name}</span></span> : <span className="font-medium">Open to anyone</span>}</div></div>
                        {canManage && <button type="button" onClick={() => router.delete(route('trackers.tasks.destroy', [tracker.id, task.id]), { preserveScroll: true })} className="flex size-9 shrink-0 items-center justify-center rounded-xl text-slate-300 transition hover:bg-rose-50 hover:text-rose-500" aria-label={`Delete ${task.title}`}><Trash2 size={16}/></button>}
                    </div>)}
                </div>
            </div>

            <div className="border-t border-[#d9f4e4] p-5 sm:p-6 lg:border-t-0">
                <div className="flex items-center justify-between"><div><h3 className="font-display text-xl font-bold">Future expenses</h3><p className="mt-0.5 text-sm text-slate-500">Plan costs before money is spent.</p></div>{canManage && <button type="button" onClick={() => setPanel(panel === 'expense' ? null : 'expense')} className="flex size-11 items-center justify-center rounded-2xl border border-[#bde9cd] bg-white text-[#18a653] shadow-sm transition hover:-translate-y-0.5" aria-label="Add future expense">{panel === 'expense' ? <X size={20}/> : <Plus size={21}/>}</button>}</div>
                {panel === 'expense' && <form onSubmit={addEstimate} className="mt-4 space-y-3 rounded-3xl border border-[#c9efd8] bg-white p-4 shadow-lg shadow-emerald-900/5">
                    <input value={expenseForm.data.description} onChange={(event) => expenseForm.setData('description', event.target.value)} className={inputClass} placeholder="e.g. Island boat rental" autoFocus/>
                    <div className="relative"><span className="absolute left-4 top-1/2 -translate-y-1/2 font-bold text-slate-400">{tracker.currency_code}</span><input inputMode="decimal" value={expenseForm.data.amount} onChange={(event) => expenseForm.setData('amount', event.target.value)} className={`${inputClass} pl-14`} placeholder="0.00"/></div>
                    <div className="grid grid-cols-2 gap-2"><AssigneeSelect members={members} value={expenseForm.data.assigned_to_user_id} onChange={(value) => expenseForm.setData('assigned_to_user_id', value)}/><input type="date" value={expenseForm.data.expected_date} onChange={(event) => expenseForm.setData('expected_date', event.target.value)} className={inputClass}/></div>
                    {Object.values(expenseForm.errors)[0] && <p className="text-sm font-medium text-rose-600">{Object.values(expenseForm.errors)[0]}</p>}
                    <button disabled={expenseForm.processing} className="ss-button h-12 w-full">{expenseForm.processing ? 'Adding…' : 'Add estimate'}</button>
                </form>}
                <div className="mt-4 space-y-2">
                    {plannedExpenses.length === 0 && <button type="button" onClick={() => canManage && setPanel('expense')} className="w-full rounded-3xl border border-dashed border-[#a8dfbd] bg-white/70 px-5 py-8 text-center"><WalletCards size={27} className="mx-auto text-[#31bf6b]"/><p className="mt-3 font-bold text-slate-800">No estimated costs</p><p className="mt-1 text-sm text-slate-500">Add expected costs to see the trip estimate.</p></button>}
                    {plannedExpenses.map((item, index) => <div key={item.id} className="group flex items-center gap-3 rounded-2xl border border-slate-100 bg-white p-3 shadow-sm transition hover:border-[#b9eacb]">
                        <span className="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-[#e9faef] font-display text-lg font-bold text-[#159e4d]">{index + 1}</span>
                        <div className="min-w-0 flex-1"><p className="truncate font-semibold text-slate-900">{item.description}</p><div className="mt-1 flex flex-wrap items-center gap-x-2 text-xs text-slate-500">{item.assignee && <span>{item.assignee.name}</span>}{item.expected_date && <span>· {shortDate(item.expected_date)}</span>}</div></div>
                        <div className="text-right"><p className="font-display text-base font-bold text-slate-950">{money(item.estimated_amount_minor, tracker.currency_code)}</p>{canManage && <button type="button" onClick={() => router.delete(route('trackers.planned-expenses.destroy', [tracker.id, item.id]), { preserveScroll: true })} className="mt-1 text-xs font-semibold text-slate-400 transition hover:text-rose-500">Remove</button>}</div>
                    </div>)}
                </div>
            </div>
        </div>
    </section>;
}
