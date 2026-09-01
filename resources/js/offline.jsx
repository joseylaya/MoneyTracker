import '../css/app.css';
import { createRoot } from 'react-dom/client';
import { useEffect, useMemo, useState } from 'react';
import { CloudOff, WalletCards } from 'lucide-react';
import { loadLatestSnapshot, newOperation, queueOperation, syncPending } from '@/offline/store';

const money = (minor, code = 'PHP') => new Intl.NumberFormat('en-PH', { style: 'currency', currency: code }).format((minor || 0) / 100);

function OfflineApp() {
    const [cache, setCache] = useState(null); const [trackerId, setTrackerId] = useState(''); const [saving, setSaving] = useState(false);
    useEffect(() => { loadLatestSnapshot().then((value) => { setCache(value); setTrackerId(value?.snapshot?.trackers?.[0]?.id || ''); }); }, []);
    const tracker = useMemo(() => cache?.snapshot?.trackers?.find((item) => item.id === trackerId), [cache, trackerId]);
    async function submit(event) {
        event.preventDefault(); if (!cache || !tracker) return;
        const form = new FormData(event.currentTarget); const amount = String(form.get('amount') || ''); const description = String(form.get('description') || '').trim();
        if (!amount || !description) return;
        setSaving(true);
        await queueOperation(cache.userId, newOperation('expense.create', tracker.id, { description, amount, expense_date: String(form.get('expense_date')), paid_by_user_id: Number(form.get('paid_by_user_id')), note: String(form.get('note') || ''), participants: tracker.members.map((member) => member.id) }));
        event.currentTarget.reset(); setSaving(false); syncPending(cache.userId);
    }
    if (!cache) return <main className="grid min-h-[100dvh] place-items-center bg-[#f6f8f7] p-6 text-center"><div className="max-w-sm"><CloudOff className="mx-auto text-slate-400" size={42}/><h1 className="mt-5 font-display text-2xl font-bold">You’re offline</h1><p className="mt-3 text-slate-600">Open SplitShare once while connected to save your private offline copy. Your existing account and push-notification setup are unchanged.</p></div></main>;
    return <main className="min-h-[100dvh] bg-[#f6f8f7] pb-10 text-slate-900"><header className="border-b bg-white px-5 py-4"><div className="mx-auto flex max-w-2xl items-center gap-3"><span className="grid size-10 place-items-center rounded-xl bg-[#2ecc70] text-white"><WalletCards size={21}/></span><div><h1 className="font-display text-xl font-bold">SplitShare</h1><p className="text-xs font-semibold text-amber-700">Offline copy · changes will sync automatically</p></div></div></header><div className="mx-auto max-w-2xl p-5"><label className="eyebrow">Tracker</label><select className="ss-input mt-2" value={trackerId} onChange={(event) => setTrackerId(event.target.value)}>{cache.snapshot.trackers.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select>{tracker && <><section className="mt-5 ss-card p-5"><h2 className="text-section-title">Add expense</h2><p className="mt-1 text-sm text-slate-500">Saved on this device now, then sent when you reconnect.</p><form onSubmit={submit} className="mt-5 space-y-4"><input name="description" required placeholder="What was it for?" className="ss-input"/><div className="grid grid-cols-2 gap-3"><input name="amount" required inputMode="decimal" placeholder="Amount" className="ss-input"/><input name="expense_date" defaultValue={new Date().toISOString().slice(0, 10)} type="date" className="ss-input"/></div><select name="paid_by_user_id" className="ss-input">{tracker.members.map((member) => <option value={member.id} key={member.id}>{member.name} paid</option>)}</select><textarea name="note" rows="2" placeholder="Note (optional)" className="ss-input h-auto py-3"/><button disabled={saving} className="ss-button w-full">{saving ? 'Saving locally…' : 'Save offline expense'}</button></form></section><section className="mt-5"><h2 className="text-section-title">Cached expenses</h2><div className="mt-3 space-y-3">{tracker.expenses?.map((expense) => <article key={expense.id} className="ss-card flex justify-between p-4"><div><p className="font-semibold">{expense.description}</p><p className="mt-1 text-sm text-slate-500">{expense.expense_date} · {expense.payer?.name || 'Member'}</p></div><p className="font-bold">{money(expense.amount_minor, tracker.currency_code)}</p></article>)}</div></section></>}</div></main>;
}

createRoot(document.getElementById('app')).render(<OfflineApp/>);
