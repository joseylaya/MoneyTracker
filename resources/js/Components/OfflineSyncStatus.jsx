import { Cloud, CloudOff, RefreshCw } from 'lucide-react';
import { useEffect, useState } from 'react';
import { eventName, pendingCount } from '@/offline/store';

export default function OfflineSyncStatus({ userId }) {
    const [state, setState] = useState(navigator.onLine ? 'synced' : 'offline');
    const [pending, setPending] = useState(0);
    useEffect(() => {
        pendingCount(userId).then(setPending);
        const update = (event) => { setState(event.detail?.state || 'synced'); if (typeof event.detail?.pending === 'number') setPending(event.detail.pending); };
        const offline = () => setState('offline'); const online = () => setState('syncing');
        window.addEventListener(eventName, update); window.addEventListener('offline', offline); window.addEventListener('online', online);
        return () => { window.removeEventListener(eventName, update); window.removeEventListener('offline', offline); window.removeEventListener('online', online); };
    }, [userId]);
    const label = state === 'offline' ? (pending ? `${pending} change${pending === 1 ? '' : 's'} waiting` : 'Offline copy') : state === 'syncing' ? 'Syncing…' : pending ? `${pending} change${pending === 1 ? '' : 's'} waiting` : 'Synced';
    const Icon = state === 'offline' ? CloudOff : state === 'syncing' ? RefreshCw : Cloud;
    return <span title={label} className={`hidden items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-bold sm:flex ${state === 'offline' || pending ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700'}`}><Icon size={13} className={state === 'syncing' ? 'animate-spin' : ''}/>{label}</span>;
}
