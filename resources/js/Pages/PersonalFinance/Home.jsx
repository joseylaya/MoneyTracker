import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { Banknote, Beer, CreditCard, HeartPulse, House, Landmark, Lightbulb, PiggyBank, Plus, Tv, WalletCards, X } from 'lucide-react';
import { useState } from 'react';

const money = (minor, currency = 'PHP') => new Intl.NumberFormat('en-PH', { style: 'currency', currency }).format((minor || 0) / 100);
const amountInput = (minor) => ((minor || 0) / 100).toFixed(2);
const today = new Date().toISOString().slice(0, 10);
const BillIcon = ({ category }) => {
    const value = (category || '').toLowerCase();
    const Icon = value.includes('allowance') ? Banknote : value.includes('rent') ? House : value.includes('subscription') ? Tv : value.includes('loan') ? CreditCard : Lightbulb;
    return <span className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-[#e6f9ed] text-[#159a4a]"><Icon size={18} /></span>;
};

function IncomeForm({ accounts, onClose }) {
    const form = useForm({ income_type: 'salary', salary_period: 'first', description: '', date: today, entries: [{ account_id: String(accounts[0]?.id || ''), amount: '' }] });
    const updateEntry = (index, field, value) => form.setData('entries', form.data.entries.map((entry, entryIndex) => entryIndex === index ? { ...entry, [field]: value } : entry));
    const addEntry = () => form.setData('entries', [...form.data.entries, { account_id: String(accounts[0]?.id || ''), amount: '' }]);
    const removeEntry = (index) => form.setData('entries', form.data.entries.filter((_, entryIndex) => entryIndex !== index));
    const submit = (event) => {
        event.preventDefault();
        form.post(route('personal.income.store'), { preserveScroll: true, onSuccess: () => { form.reset(); onClose(); } });
    };

    return <form onSubmit={submit} className="ss-card relative mt-4 p-5">
        <button type="button" onClick={onClose} className="absolute right-5 top-5 text-slate-400 hover:text-slate-700" aria-label="Close record income form"><X size={18} /></button>
        <h2 className="font-display text-xl font-bold">Record income</h2>
        <p className="mt-1 text-sm text-slate-500">Split one income across accounts when needed.</p>
        <div className="mt-4 space-y-3">
            <select className="ss-input h-11 w-full text-slate-900" value={form.data.income_type} onChange={(event) => form.setData('income_type', event.target.value)}><option value="salary">Salary</option><option value="sale">Sale</option><option value="other">Other income</option></select>
            {form.data.income_type === 'salary' && <select className="ss-input h-11 w-full text-slate-900" value={form.data.salary_period} onChange={(event) => form.setData('salary_period', event.target.value)}><option value="first">15th salary</option><option value="second">30th / 31st salary</option></select>}
            <input className="ss-input h-11 w-full text-slate-900" value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} placeholder={form.data.income_type === 'salary' ? 'Optional note, e.g. with absence deduction' : 'What was this income? e.g. Bike sale'} />
            <input className="ss-input h-11 w-full text-slate-900" type="date" value={form.data.date} onChange={(event) => form.setData('date', event.target.value)} required />
            {form.data.entries.map((entry, index) => <div className="flex gap-2" key={index}>
                <select className="ss-input h-11 min-w-0 flex-1 text-slate-900" value={entry.account_id} onChange={(event) => updateEntry(index, 'account_id', event.target.value)}>{accounts.map((account) => <option key={account.id} value={account.id}>{account.name}</option>)}</select>
                <input className="ss-input h-11 w-28 text-slate-900" value={entry.amount} onChange={(event) => updateEntry(index, 'amount', event.target.value)} type="number" min="0.01" step="0.01" placeholder="Amount" required />
                {form.data.entries.length > 1 && <button type="button" onClick={() => removeEntry(index)} className="rounded-lg px-2 text-[#168f48] hover:bg-[#effcf4]" aria-label="Remove income split"><X size={18} /></button>}
            </div>)}
            <button type="button" onClick={addEntry} className="text-sm font-bold text-[#168f48] hover:text-[#103f30]"><Plus className="mr-1 inline" size={16} /> Split to another account</button>
            <button className="ss-button h-11 w-full" disabled={form.processing}>Save income</button>
        </div>
    </form>;
}

function PayBillForm({ accounts, bill, onClose }) {
    const form = useForm({ date: today, entries: [{ account_id: String(accounts[0]?.id || ''), amount: amountInput(bill.remaining_minor) }] });
    const updateEntry = (index, field, value) => form.setData('entries', form.data.entries.map((entry, entryIndex) => entryIndex === index ? { ...entry, [field]: value } : entry));
    const addEntry = () => form.setData('entries', [...form.data.entries, { account_id: String(accounts[0]?.id || ''), amount: '' }]);
    const removeEntry = (index) => form.setData('entries', form.data.entries.filter((_, entryIndex) => entryIndex !== index));
    const submit = (event) => {
        event.preventDefault();
        form.post(route('personal.commitments.pay', bill.id), { preserveScroll: true, onSuccess: onClose });
    };

    return <form onSubmit={submit} className="relative mt-3 rounded-2xl border border-[#9be3ba] bg-[#effcf4] p-4">
        <button type="button" onClick={onClose} className="absolute right-3 top-3 text-slate-400 hover:text-slate-700" aria-label="Close bill payment form"><X size={17} /></button>
        <p className="pr-8 text-sm font-bold">Pay {bill.name}</p>
        <p className="mt-1 text-xs text-slate-600">Remaining: {money(bill.remaining_minor)}</p>
        <div className="mt-3 space-y-2">
            {form.data.entries.map((entry, index) => <div className="flex gap-2" key={index}><select className="ss-input h-10 min-w-0 flex-1" value={entry.account_id} onChange={(event) => updateEntry(index, 'account_id', event.target.value)}>{accounts.map((account) => <option key={account.id} value={account.id}>{account.name}</option>)}</select><input className="ss-input h-10 w-28" value={entry.amount} onChange={(event) => updateEntry(index, 'amount', event.target.value)} type="number" min="0.01" step="0.01" required />{form.data.entries.length > 1 && <button type="button" onClick={() => removeEntry(index)} className="rounded-lg px-1 text-slate-500 hover:bg-slate-100" aria-label="Remove payment split"><X size={17} /></button>}</div>)}
            <button type="button" onClick={addEntry} className="text-left text-sm font-bold text-[#168f48] hover:text-[#103f30]"><Plus className="mr-1 inline" size={16} /> Split payment to another account</button>
            <input className="ss-input h-10 w-full" value={form.data.date} onChange={(event) => form.setData('date', event.target.value)} type="date" required />
            {Object.values(form.errors).map((error) => <p className="text-xs font-medium text-rose-600" key={error}>{error}</p>)}
            <button className="ss-button h-10 w-full" disabled={form.processing}>Pay from selected account</button>
        </div>
    </form>;
}

function ExpenseForm({ accounts, onClose }) {
    const [moreDetails, setMoreDetails] = useState(false);
    const form = useForm({ account_id: String(accounts[0]?.id || ''), amount: '', category: 'Lifestyle', description: '', date: today });
    const submit = (event) => {
        event.preventDefault();
        form.post(route('personal.expenses.store'), { preserveScroll: true, onSuccess: () => { form.reset(); onClose(); } });
    };

    return <form onSubmit={submit} className="ss-card relative mt-4 p-5">
        <button type="button" onClick={onClose} className="absolute right-5 top-5 text-slate-400 hover:text-slate-700" aria-label="Close quick expense form"><X size={18} /></button>
        <h2 className="font-display text-xl font-bold">Quick expense</h2>
        <p className="mt-1 text-sm text-slate-500">Record everyday spending in a few seconds.</p>
        <div className="mt-4 space-y-3">
            <input className="ss-input h-11 w-full" value={form.data.amount} onChange={(event) => form.setData('amount', event.target.value)} type="number" min="0.01" step="0.01" placeholder="Amount" autoFocus required />
            <select className="ss-input h-11 w-full" value={form.data.category} onChange={(event) => form.setData('category', event.target.value)}><option value="Lifestyle">Lifestyle</option><option value="Food & dining">Food & dining</option><option value="Groceries">Groceries</option><option value="Transport">Transport</option><option value="Shopping">Shopping</option><option value="Health">Health</option><option value="Other">Other</option></select>
            <select className="ss-input h-11 w-full" value={form.data.account_id} onChange={(event) => form.setData('account_id', event.target.value)}>{accounts.map((account) => <option key={account.id} value={account.id}>{account.name}</option>)}</select>
            <button type="button" onClick={() => setMoreDetails(!moreDetails)} className="text-sm font-bold text-[#168f48] hover:text-[#103f30]">{moreDetails ? 'Hide details' : 'More details'}</button>
            {moreDetails && <><input className="ss-input h-11 w-full" value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} placeholder="Description or merchant (optional)" /><input className="ss-input h-11 w-full" value={form.data.date} onChange={(event) => form.setData('date', event.target.value)} type="date" required /></>}
            {Object.values(form.errors).map((error) => <p className="text-sm font-medium text-rose-600" key={error}>{error}</p>)}
            <button className="ss-button h-11 w-full" disabled={form.processing}>Save expense</button>
        </div>
    </form>;
}

export default function Home({ settings, accounts, liquid, reservedBills, savings, emergency, safe, lifestyle, lifestyleBudget, upcomingCommitments, nextPayday, days, recentTransactions }) {
    const [recordingIncome, setRecordingIncome] = useState(false);
    const [recordingExpense, setRecordingExpense] = useState(false);
    const [payingBill, setPayingBill] = useState(null);
    const currency = settings.currency_code;
    const lifestyleRemaining = Math.max(0, lifestyleBudget - lifestyle);
    const recentActivity = recentTransactions.slice(0, 8);

    return <AuthenticatedLayout header={<div><p className="eyebrow">Personal Finance</p><h1 className="font-display text-2xl font-bold">Your money, clearly.</h1></div>} floatingAction={<button onClick={() => { setRecordingExpense(true); setRecordingIncome(false); }} disabled={!accounts.length || recordingExpense} className="fixed bottom-[calc(4.75rem+env(safe-area-inset-bottom))] right-5 z-50 flex size-14 items-center justify-center rounded-full bg-[#18b957] text-white shadow-lg shadow-emerald-900/25 transition hover:bg-[#149d49] disabled:opacity-50 sm:right-8" aria-label="Add expense" title="Add expense"><Plus size={24} /></button>}>
        <Head title="Home" />
        <div className="page-wrap space-y-6 pb-10">
            <section className="ss-summary-panel border-2 border-[#2ecc70] bg-[#effcf4]">
                <div className="flex items-start justify-between gap-4"><div><p className="text-sm font-semibold uppercase tracking-wider text-[#0d9b4c]">Safe to spend</p><p className="mt-2 text-money-xl text-[#0d9b4c]">{money(safe, currency)}</p><p className="mt-2 text-sm text-slate-600">{money(Math.floor(safe / days), currency)} per day until payday · {new Date(nextPayday).toLocaleDateString('en-PH', { month: 'short', day: 'numeric' })}</p></div><button onClick={() => { setRecordingIncome(true); setRecordingExpense(false); }} disabled={!accounts.length || recordingIncome} className="ss-button h-10 shrink-0 rounded-xl px-3"><Plus className="mr-1 inline" size={16} /> Income</button></div>
                <div className="mt-5 grid grid-cols-2 gap-3 text-sm"><div className="rounded-2xl border border-[#c5f0d5] bg-white p-3"><span className="block text-slate-500">Liquid money</span><b className="text-money-sm">{money(liquid, currency)}</b></div><div className="rounded-2xl border border-[#c5f0d5] bg-white p-3"><span className="block text-slate-500">Bills reserved</span><b className="text-money-sm">{money(reservedBills, currency)}</b></div></div>
                {!accounts.length && <p className="mt-4 text-sm text-slate-600">Add an account first before recording income.</p>}
                {recordingIncome && <IncomeForm accounts={accounts} onClose={() => setRecordingIncome(false)} />}
                {recordingExpense && <ExpenseForm accounts={accounts} onClose={() => setRecordingExpense(false)} />}
            </section>
            <section className="grid gap-3 sm:grid-cols-3"><div className="ss-card p-4"><div className="flex items-start justify-between gap-3"><p className="eyebrow">Lifestyle left</p><span className="flex size-9 items-center justify-center rounded-xl bg-amber-50 text-amber-600"><Beer size={18} /></span></div><p className="mt-2 text-money-lg">{money(lifestyleRemaining, currency)}</p><p className="mt-1 text-xs text-slate-500">{lifestyleBudget ? Math.round(lifestyle / lifestyleBudget * 100) : 0}% used</p></div><div className="ss-card p-4"><div className="flex items-start justify-between gap-3"><p className="eyebrow">Savings protected</p><span className="flex size-9 items-center justify-center rounded-xl bg-sky-50 text-sky-600"><PiggyBank size={18} /></span></div><p className="mt-2 text-money-lg">{money(savings, currency)}</p></div><div className="ss-card p-4"><div className="flex items-start justify-between gap-3"><p className="eyebrow">Emergency fund</p><span className="flex size-9 items-center justify-center rounded-xl bg-rose-50 text-rose-600"><HeartPulse size={18} /></span></div><p className="mt-2 text-money-lg">{money(emergency, currency)}</p></div></section>
            <section className="ss-card p-5"><div className="flex items-center justify-between gap-4"><div><h2 className="font-display text-xl font-bold">Your accounts</h2><p className="mt-1 text-sm text-slate-500">Banks, e-wallets, and cash are managed from Profile.</p></div><Link href={route('personal.accounts')} className="ss-button h-11 shrink-0 gap-2"><Plus size={17} />Manage</Link></div>{accounts.length ? <div className="mt-4 grid gap-3 sm:grid-cols-2">{accounts.map((account) => <div key={account.id} className="rounded-2xl bg-slate-50 p-4"><div className="flex items-center gap-2"><Landmark size={17} className="text-[#18b957]" /><b>{account.name}</b></div><p className="mt-2 text-lg font-bold">{money(account.balance_minor, currency)}</p><p className="text-xs text-slate-500">{account.last_reconciled_at ? `Verified ${new Date(account.last_reconciled_at).toLocaleDateString()}` : 'Needs checking'}</p></div>)}</div> : <div className="mt-4 rounded-2xl border border-dashed border-[#9be3ba] bg-[#effcf4] p-5"><WalletCards className="text-[#18b957]" /><p className="mt-2 font-bold">Add your first account from Profile</p><p className="mt-1 text-sm text-slate-600">Start with your bank, e-wallet, or cash balance. It will immediately update Safe to Spend.</p></div>}</section>
            <section className="grid gap-5 lg:grid-cols-2"><div className="ss-card p-5"><div className="flex items-center justify-between"><h2 className="font-display text-xl font-bold">Upcoming bills</h2><Link href={route('personal.accounts')} className="text-sm font-bold text-[#18b957]">Manage</Link></div><div className="mt-3 space-y-3">{upcomingCommitments.length ? upcomingCommitments.map((bill) => <div key={bill.id} className="rounded-2xl bg-slate-50 p-3"><div className="flex items-center justify-between gap-3"><div className="flex min-w-0 items-center gap-3"><BillIcon category={bill.category} /><div className="min-w-0"><b>{bill.name}</b><p className="text-xs text-slate-500">Due every {bill.due_day} · <span className={bill.status === 'Overdue' ? 'font-bold text-rose-600' : ''}>{bill.status}</span></p>{bill.status !== 'Paid' && bill.paid_minor > 0 && <p className="mt-1 text-xs text-slate-500">Paid {money(bill.paid_minor, currency)} · {money(bill.remaining_minor, currency)} left</p>}</div></div><div className="shrink-0 text-right"><b className={bill.status === 'Overdue' ? 'text-rose-600' : ''}>{money(bill.remaining_minor, currency)}</b>{bill.status !== 'Paid' && <button onClick={() => setPayingBill(bill)} className="mt-2 block rounded-lg bg-[#18b957] px-2.5 py-1.5 text-xs font-bold text-white">Pay</button>}</div></div>{payingBill?.id === bill.id && <PayBillForm accounts={accounts} bill={bill} onClose={() => setPayingBill(null)} />}</div>) : <p className="text-sm text-slate-500">No bills due in this half of the month.</p>}</div></div><div className="ss-card p-5"><h2 className="font-display text-xl font-bold">Recent activity</h2><div className="mt-3 space-y-3">{recentActivity.length ? recentActivity.map((transaction) => <div key={transaction.id} className="flex justify-between text-sm"><span><b>{transaction.description || transaction.category || transaction.type}</b><small className="block text-slate-500">{transaction.account?.name} · {transaction.occurred_on}</small></span><b className={transaction.type === 'expense' || transaction.type === 'transfer_out' ? 'text-rose-600' : 'text-[#14a956]'}>{transaction.type === 'expense' || transaction.type === 'transfer_out' ? '-' : '+'}{money(transaction.amount_minor, currency)}</b></div>) : <p className="text-sm text-slate-500">Your income, expenses, and transfers will appear here.</p>}</div></div></section>
        </div>
    </AuthenticatedLayout>;
}
