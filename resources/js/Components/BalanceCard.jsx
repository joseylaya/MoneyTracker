import { Link } from '@inertiajs/react';
import { money } from '@/utils/money';

export default function BalanceCard({ balance, currency, settlementHref, compact = false }) {
    const state = balance > 0 ? 'CREDIT BALANCE' : balance < 0 ? 'AMOUNT DUE' : "NO BALANCE DUE";
    const color = balance > 0 ? 'text-[#10a84f]' : balance < 0 ? 'text-[#e11d48]' : 'text-slate-700';
    return <section className={`ss-summary-panel ${compact ? 'p-5' : 'p-7'} border border-[#bff2d3] bg-[#edfcf3]`}><p className={`eyebrow ${color}`}>{state}</p><div className="mt-2 flex flex-wrap items-end justify-between gap-4"><div><p className={`${compact ? 'text-3xl' : 'text-5xl'} text-money-xl ${color}`}>{money(balance, currency)}</p><p className="mt-1 text-sm text-slate-500">{balance > 0 ? 'Amount due to you.' : balance < 0 ? 'Your outstanding obligation.' : 'No outstanding obligation.'}</p></div>{settlementHref && balance !== 0 && <Link href={settlementHref} className="ss-button min-w-32">Settle balance</Link>}</div></section>;
}
