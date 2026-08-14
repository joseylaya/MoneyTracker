import { getApp, getApps, initializeApp } from 'firebase/app';
import { getMessaging, getToken, isSupported } from 'firebase/messaging';
import { BellRing } from 'lucide-react';
import { useState } from 'react';
import { router } from '@inertiajs/react';

const config = { apiKey: import.meta.env.VITE_FIREBASE_API_KEY, authDomain: import.meta.env.VITE_FIREBASE_AUTH_DOMAIN, projectId: import.meta.env.VITE_FIREBASE_PROJECT_ID, messagingSenderId: import.meta.env.VITE_FIREBASE_MESSAGING_SENDER_ID, appId: import.meta.env.VITE_FIREBASE_APP_ID };
export default function PushNotifications() {
 const [state,setState]=useState(Notification.permission === 'granted' ? 'enabled' : 'idle');
 const enable = async () => { try { setState('loading'); if (!(await isSupported())) throw new Error(); const permission=await Notification.requestPermission(); if(permission!=='granted') {setState('denied');return;} const app=getApps().length?getApp():initializeApp(config); const registration=await navigator.serviceWorker.register(`${import.meta.env.BASE_URL}firebase-messaging-sw.js`); const token=await getToken(getMessaging(app),{vapidKey:import.meta.env.VITE_FIREBASE_VAPID_KEY,serviceWorkerRegistration:registration}); if(!token) throw new Error(); router.post(route('push-devices.store'),{token},{preserveScroll:true,onSuccess:()=>setState('enabled'),onError:()=>setState('error')}); } catch { setState('error'); } };
 return <div className="ss-card flex flex-wrap items-center justify-between gap-4 p-6 sm:p-7"><div><h2 className="font-display text-xl font-bold">Push notifications</h2><p className="mt-1 text-sm text-slate-500">New messages, invitations, and settlement updates.</p></div><button onClick={enable} disabled={state==='loading'||state==='enabled'} className="ss-button h-11 gap-2 px-4"><BellRing size={18}/>{state==='enabled'?'Enabled':state==='loading'?'Enabling…':'Enable'}</button>{state==='denied'&&<p className="w-full text-sm text-rose-600">Notifications are blocked in this browser’s settings.</p>}{state==='error'&&<p className="w-full text-sm text-rose-600">Could not enable notifications. Use HTTPS or an installed PWA.</p>}</div>;
}
