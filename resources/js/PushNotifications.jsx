import { BellRing } from 'lucide-react';
import { useState } from 'react';
import { enableBrowserPush } from '@/browserPush';

export default function PushNotifications() {
 const [state,setState]=useState('idle');
 const register = async () => { try { setState('loading'); setState(await enableBrowserPush()); } catch { setState('error'); } };
 return <div className="ss-card flex flex-wrap items-center justify-between gap-4 p-6 sm:p-7"><div><h2 className="font-display text-xl font-bold">Push notifications</h2><p className="mt-1 text-sm text-slate-500">Messages, expenses, comments, settlements, and membership updates.</p></div><button onClick={()=>register(true)} disabled={state==='loading'||state==='enabled'} className="ss-button h-11 gap-2 px-4"><BellRing size={18}/>{state==='enabled'?'Enabled':state==='loading'?'Enabling…':'Enable'}</button>{state==='denied'&&<p className="w-full text-sm text-rose-600">Notifications are blocked. On iPhone, install SplitShare to your Home Screen first, then allow notifications.</p>}{state==='error'&&<p className="w-full text-sm text-rose-600">Could not enable notifications. Check HTTPS and browser support, then try again.</p>}</div>;
}
