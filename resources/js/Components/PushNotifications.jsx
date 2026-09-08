import { BellRing } from 'lucide-react';
import { useEffect, useState } from 'react';
import { browserPushSupport, enableBrowserPush } from '@/browserPush';

export default function PushNotifications() {
 const [state,setState]=useState('idle');
 useEffect(() => {
  const support = browserPushSupport();
  if (support !== 'granted') { setState(support); return; }
  setState('loading');
  enableBrowserPush({ requestPermission: false }).then(setState).catch(() => setState('error'));
 }, []);
 const register = async () => { try { setState('loading'); setState(await enableBrowserPush()); } catch { setState('error'); } };
 return <div className="ss-card flex flex-wrap items-center justify-between gap-4 p-6 sm:p-7"><div><h2 className="font-display text-xl font-bold">Browser notifications</h2><p className="mt-1 text-sm text-slate-500">Receive messages, expenses, comments, settlements, and membership updates in this browser.</p></div><button onClick={register} disabled={state==='loading'||state==='enabled'||state==='denied'||state==='unsupported'} className="ss-button h-11 gap-2 px-4"><BellRing size={18}/>{state==='enabled'?'Enabled':state==='loading'?'Enabling…':state==='unsupported'?'Not supported':state==='denied'?'Blocked':'Enable Notifications'}</button>{state==='denied'&&<p className="w-full text-sm text-rose-600">Notifications are blocked for this site. Allow them in your browser settings, then reload this page.</p>}{state==='unsupported'&&<p className="w-full text-sm text-slate-500">This browser does not support Web Push. On iPhone or iPad, add SplitShare to the Home Screen first.</p>}{state==='error'&&<p className="w-full text-sm text-rose-600">Could not enable notifications. Check HTTPS and browser support, then try again.</p>}</div>;
}
