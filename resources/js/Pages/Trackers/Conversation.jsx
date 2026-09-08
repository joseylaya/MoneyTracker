import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Avatar from '@/Components/Avatar';
import { money } from '@/utils/money';
import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, Paperclip, Send, Smile, WalletCards, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

const quickEmojis = ['😀', '😂', '👍', '❤️', '🎉', '🙏'];
const reactionEmojis = ['❤️', '😂', '😮', '😢', '👍'];

function messageTime(value) {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    return new Intl.DateTimeFormat(undefined, { hour: 'numeric', minute: '2-digit', month: 'short', day: 'numeric' }).format(date);
}

export default function Conversation({ tracker, messages: initialMessages, currentUserId, currentUserName, canChat, settlementOptions, hasMoreMessages }) {
    const [messages, setMessages] = useState(initialMessages);
    const [visibleTimeGroup, setVisibleTimeGroup] = useState(null);
    const [emojiPickerOpen, setEmojiPickerOpen] = useState(false);
    const [reactionPickerMessageId, setReactionPickerMessageId] = useState(null);
    const [hasMore, setHasMore] = useState(hasMoreMessages);
    const [loadingOlder, setLoadingOlder] = useState(false);
    const [settlementModalOpen, setSettlementModalOpen] = useState(false);
    const [selectedDebt, setSelectedDebt] = useState(settlementOptions[0]?.to_user_id ?? '');
    const [settlementAmount, setSettlementAmount] = useState('');
    const [settlementNote, setSettlementNote] = useState('');
    const [draft, setDraft] = useState('');
    const [attachment, setAttachment] = useState(null);
    const [typingUsers, setTypingUsers] = useState({});
    const endOfMessages = useRef(null);
    const input = useRef(null);
    const holdTimer = useRef(null);
    const emojiPicker = useRef(null);
    const fileInput = useRef(null);
    const messageScroller = useRef(null);
    const prependingHistory = useRef(false);
    const realtimeChannel = useRef(null);
    const typingStopTimer = useRef(null);
    const lastTypingSignalAt = useRef(0);
    const typingExpiryTimers = useRef(new Map());

    useEffect(() => setMessages(initialMessages), [initialMessages]);
    useEffect(() => setHasMore(hasMoreMessages), [hasMoreMessages]);
    useEffect(() => {
        const updatePresence = (active) => fetch(route('trackers.conversation.presence', tracker.id), {
            method: 'POST', credentials: 'same-origin', keepalive: !active,
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' },
            body: JSON.stringify({ active }),
        }).catch(() => {});
        const syncVisibility = () => updatePresence(document.visibilityState === 'visible');

        syncVisibility();
        document.addEventListener('visibilitychange', syncVisibility);
        const heartbeat = window.setInterval(() => {
            if (document.visibilityState === 'visible') updatePresence(true);
        }, 30000);

        return () => {
            window.clearInterval(heartbeat);
            document.removeEventListener('visibilitychange', syncVisibility);
            updatePresence(false);
        };
    }, [tracker.id]);
    useEffect(() => {
        if (!prependingHistory.current) endOfMessages.current?.scrollIntoView({ behavior: 'smooth', block: 'end' });
    }, [messages, typingUsers]);
    useEffect(() => {
        if (!window.Echo) return undefined;
        const channel = window.Echo.private(`tracker.${tracker.id}`);
        realtimeChannel.current = channel;
        channel.listen('.tracker.message.created', ({ message, client_message_id: clientMessageId }) => {
            setTypingUsers((current) => { const next = { ...current }; delete next[message.author.id]; return next; });
            setMessages((current) => {
                if (current.some((item) => item.id === message.id)) return current;
                const pendingIndex = clientMessageId ? current.findIndex((item) => item.clientMessageId === clientMessageId) : -1;
                if (pendingIndex < 0) return [...current, message];
                return current.map((item, index) => index === pendingIndex ? message : item);
            });
        });
        channel.listen('.tracker.message.reactions.updated', ({ message_id, reactions }) => {
            setMessages((current) => current.map((message) => message.id === message_id ? { ...message, reactions } : message));
        });
        channel.listen('.tracker.settlement-request.updated', ({ settlement_request_id, status }) => {
            setMessages((current) => current.map((message) => message.settlement_request?.id === settlement_request_id ? { ...message, settlement_request: { ...message.settlement_request, status } } : message));
        });
        channel.listenForWhisper('typing', ({ userId, name, typing }) => {
            if (!userId || Number(userId) === Number(currentUserId)) return;
            const key = String(userId);
            if (typingExpiryTimers.current.has(key)) window.clearTimeout(typingExpiryTimers.current.get(key));
            setTypingUsers((current) => {
                const next = { ...current };
                if (typing) next[key] = name || 'Someone'; else delete next[key];
                return next;
            });
            if (typing) typingExpiryTimers.current.set(key, window.setTimeout(() => {
                setTypingUsers((current) => { const next = { ...current }; delete next[key]; return next; });
                typingExpiryTimers.current.delete(key);
            }, 3500));
        });
        return () => {
            if (typingStopTimer.current) window.clearTimeout(typingStopTimer.current);
            typingExpiryTimers.current.forEach((timer) => window.clearTimeout(timer));
            typingExpiryTimers.current.clear();
            realtimeChannel.current = null;
            window.Echo.leave(`private-tracker.${tracker.id}`);
        };
    }, [tracker.id]);
    useEffect(() => {
        if (!emojiPickerOpen) return undefined;
        const closeOnOutsidePointer = (event) => {
            if (!emojiPicker.current?.contains(event.target)) setEmojiPickerOpen(false);
        };
        document.addEventListener('pointerdown', closeOnOutsidePointer);
        return () => document.removeEventListener('pointerdown', closeOnOutsidePointer);
    }, [emojiPickerOpen]);

    const sendMessage = async ({ temporaryId, clientMessageId, body, file }) => {
        const formData = new FormData();
        formData.append('body', body);
        formData.append('client_message_id', clientMessageId);
        if (file) formData.append('attachment', file);

        try {
            const response = await fetch(route('trackers.conversation.store', tracker.id), {
                method: 'POST', credentials: 'same-origin', body: formData,
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' },
            });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.message || 'This message was not sent.');
            setMessages((current) => current.some((message) => message.id === payload.message.id) ? current : current.map((message) => message.id === temporaryId ? payload.message : message));
        } catch (error) {
            setMessages((current) => current.map((message) => message.id === temporaryId ? { ...message, pending: false, failed: true, error: error.message || 'This message was not sent.' } : message));
        }
    };
    const submit = (event) => {
        event.preventDefault();
        const body = draft.trim();
        const file = attachment;
        if (!body && !file) return;
        signalTyping(false);
        const temporaryId = `pending-${Date.now()}`;
        const clientMessageId = typeof window.crypto?.randomUUID === 'function' ? window.crypto.randomUUID() : `${temporaryId}-${Math.random().toString(36).slice(2)}`;
        const optimisticMessage = {
            id: temporaryId,
            clientMessageId,
            body,
            created_at: new Date().toISOString(),
            type: 'message',
            author: { id: currentUserId, name: 'You' },
            attachments: file ? [{ id: `${temporaryId}-attachment`, name: file.name, mime_type: file.type, pending: true }] : [],
            reactions: [],
            pending: true,
        };
        setMessages((current) => [...current, optimisticMessage]);
        setDraft('');
        setAttachment(null);
        if (fileInput.current) fileInput.current.value = '';
        input.current?.focus();
        void sendMessage({ temporaryId, clientMessageId, body, file });
    };
    const signalTyping = (typing) => {
        if (!canChat || !realtimeChannel.current) return;
        const now = Date.now();
        if (typing && now - lastTypingSignalAt.current < 700) {
            if (typingStopTimer.current) window.clearTimeout(typingStopTimer.current);
            typingStopTimer.current = window.setTimeout(() => signalTyping(false), 1800);
            return;
        }
        realtimeChannel.current.whisper('typing', { userId: currentUserId, name: currentUserName, typing });
        lastTypingSignalAt.current = typing ? now : 0;
        if (typingStopTimer.current) window.clearTimeout(typingStopTimer.current);
        typingStopTimer.current = typing ? window.setTimeout(() => signalTyping(false), 1800) : null;
    };
    const updateDraft = (value) => {
        setDraft(value);
        signalTyping(Boolean(value.trim()));
    };
    const typingText = (() => {
        const names = Object.values(typingUsers);
        if (names.length === 0) return '';
        if (names.length === 1) return `${names[0]} is typing…`;
        if (names.length === 2) return `${names[0]} and ${names[1]} are typing…`;
        if (names.length === 3) return `${names[0]}, ${names[1]}, and ${names[2]} are typing…`;
        return `${names[0]}, ${names[1]}, and ${names.length - 2} others are typing…`;
    })();
    const addEmoji = (emoji) => { setDraft((value) => { const next = `${value}${emoji}`; signalTyping(true); return next; }); input.current?.focus(); };
    const clearHold = () => { if (holdTimer.current) window.clearTimeout(holdTimer.current); holdTimer.current = null; };
    const beginHold = (messageId) => { clearHold(); holdTimer.current = window.setTimeout(() => setReactionPickerMessageId(messageId), 450); };
    const react = (messageId, emoji) => {
        setReactionPickerMessageId(null);
        router.post(route('trackers.conversation.reactions.store', [tracker.id, messageId]), { emoji }, { preserveScroll: true, preserveState: true });
    };
    const submitSettlement = (event) => {
        event.preventDefault();
        router.post(route('trackers.conversation.settlements.store', tracker.id), { to_user_id: selectedDebt, amount: settlementAmount, settlement_date: new Date().toISOString().slice(0, 10), note: settlementNote }, { preserveScroll: true, onSuccess: () => { setSettlementModalOpen(false); setSettlementAmount(''); setSettlementNote(''); } });
    };
    const respondToSettlement = (requestId, decision) => router.post(route('trackers.conversation.settlements.response', [tracker.id, requestId]), { decision }, { preserveScroll: true });
    const loadOlder = async () => { if (loadingOlder || !hasMore || !messages.length) return; const scroller = messageScroller.current; const height = scroller?.scrollHeight ?? 0; setLoadingOlder(true); try { const params = new URLSearchParams({ before: messages[0].created_at }); const response = await fetch(`${route('trackers.conversation.messages.older', tracker.id)}?${params}`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }); const payload = await response.json(); prependingHistory.current = true; setMessages((current) => [...payload.messages, ...current]); setHasMore(payload.has_more); requestAnimationFrame(() => { if (scroller) scroller.scrollTop += scroller.scrollHeight - height; requestAnimationFrame(() => { prependingHistory.current = false; }); }); } finally { setLoadingOlder(false); } };
    const onChatScroll = (event) => { if (event.currentTarget.scrollTop < 80) loadOlder(); };

    return <AuthenticatedLayout fullBleed>
        <Head title={`${tracker.name} conversation`} />
        <div className="flex h-full flex-col bg-[radial-gradient(circle_at_top_left,#f8fffb_0%,#f0f4f2_48%,#eef2f5_100%)]">
            <header className="z-10 flex shrink-0 items-center gap-3 border-b border-slate-200/80 bg-white/90 px-4 py-3 shadow-[0_2px_12px_rgba(15,23,42,.06)] backdrop-blur-xl sm:px-6">
                <Link href={route('trackers.show', tracker.id)} className="flex size-10 shrink-0 items-center justify-center rounded-full text-slate-700 transition duration-200 hover:bg-slate-100 active:scale-95" aria-label="Back to tracker"><ChevronLeft size={28}/></Link>
                <span className="rounded-full ring-2 ring-[#d9f7e5]"><Avatar name={tracker.name} index={0} size="md"/></span>
                <div className="min-w-0 flex-1"><h1 className="truncate font-display text-lg font-bold tracking-tight">{tracker.name}</h1><p className="mt-0.5 text-xs font-medium text-[#16aa56]">Active tracker conversation</p></div>
            </header>
            <section ref={messageScroller} onScroll={onChatScroll} className="min-h-0 flex-1 overflow-y-auto px-4 py-5 sm:px-8 sm:py-7">
                <div className="mx-auto w-full max-w-5xl">
                    {messages.length === 0 ? <div className="flex min-h-[55dvh] flex-col items-center justify-center px-8 text-center"><span className="flex size-16 items-center justify-center rounded-full bg-[#dff8e9] text-3xl shadow-[0_10px_24px_rgba(46,204,112,.15)]">💬</span><h2 className="mt-5 font-display text-2xl font-bold">Start the conversation</h2><p className="mt-2 max-w-sm text-sm leading-6 text-slate-500">Coordinate with everyone in this tracker. Messages stay private to active members.</p></div> : <div className="space-y-2">{messages.map((message, index) => {
                        const mine = message.author.id === currentUserId;
                        const firstInRun = index === 0 || messages[index - 1].author.id !== message.author.id;
                        const timeIsVisible = visibleTimeGroup === message.id;
                        const pickerIsOpen = reactionPickerMessageId === message.id;
                        return <div key={message.id} className={`${firstInRun ? 'mt-5' : ''} flex max-w-[78%] gap-2.5 sm:max-w-[64%] ${mine ? 'ml-auto flex-row-reverse' : ''}`}>
                            {!mine && (firstInRun ? <Avatar name={message.author.name} index={index} size="sm"/> : <span className="size-8 shrink-0"/>)}
                            <div className={`relative min-w-0 ${mine ? 'ml-auto flex flex-col items-end' : ''}`}>{firstInRun && <p className={`mb-1 px-1 text-xs font-bold ${mine ? 'text-right text-[#18a956]' : 'text-slate-600'}`}>{mine ? 'You' : message.author.name}</p>}{message.type === 'settlement_request' ? <div className="w-72 rounded-3xl border border-[#bceecf] bg-white p-4 shadow-[0_8px_22px_rgba(15,23,42,.08)]"><div className="flex items-center gap-2 text-[#14a953]"><WalletCards size={19}/><p className="text-xs font-bold uppercase tracking-wider">Settlement request</p></div><p className="mt-3 text-sm text-slate-600">{message.settlement_request.from_name} paid {message.settlement_request.to_name}</p><p className="mt-1 font-display text-2xl font-bold text-[#13af59]">{money(message.settlement_request.amount_minor, tracker.currency_code)}</p>{message.settlement_request.note && <p className="mt-2 text-sm text-slate-500">{message.settlement_request.note}</p>}<p className={`mt-3 inline-flex rounded-full px-2.5 py-1 text-xs font-bold ${message.settlement_request.status === 'approved' ? 'bg-[#e5f9ed] text-[#139d4f]' : message.settlement_request.status === 'declined' ? 'bg-rose-50 text-rose-600' : 'bg-amber-50 text-amber-700'}`}>{message.settlement_request.status}</p>{message.settlement_request.status === 'pending' && message.settlement_request.to_user_id === currentUserId && <div className="mt-3 flex gap-2"><button onClick={() => respondToSettlement(message.settlement_request.id, 'approved')} className="flex-1 rounded-xl bg-[#24c86a] px-3 py-2 text-xs font-bold text-white">Approve</button><button onClick={() => respondToSettlement(message.settlement_request.id, 'declined')} className="rounded-xl bg-slate-100 px-3 py-2 text-xs font-bold text-slate-600">Decline</button></div>}</div> : <><button type="button" onPointerDown={() => beginHold(message.id)} onPointerUp={clearHold} onPointerLeave={clearHold} onPointerCancel={clearHold} onContextMenu={(event) => { event.preventDefault(); setReactionPickerMessageId(message.id); }} onClick={() => setVisibleTimeGroup(timeIsVisible ? null : message.id)} className={`block max-w-full transition duration-200 active:scale-[.985] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2ecc70] ${mine ? 'ml-auto text-right' : 'text-left'}`}><div className={`rounded-[1.45rem] px-4 py-1.5 ${mine ? 'rounded-tr-md bg-[linear-gradient(135deg,#31d776_0%,#1aba5d_100%)] text-white shadow-[0_8px_20px_rgba(29,190,93,.22)]' : 'rounded-tl-md bg-white text-slate-800 shadow-[0_5px_18px_rgba(15,23,42,.075)]'}`}>{message.body && <p className="whitespace-pre-wrap break-words text-[15px] leading-6">{message.body}</p>}{message.attachments?.map((attachment) => attachment.url ? <a key={attachment.id} href={attachment.url} target="_blank" className={`mt-1 flex items-center gap-2 rounded-xl px-2 py-2 text-sm font-semibold ${mine ? 'bg-white/15' : 'bg-slate-100'}`}><Paperclip size={16}/><span className="max-w-40 truncate">{attachment.name}</span></a> : <span key={attachment.id} className={`mt-1 flex items-center gap-2 rounded-xl px-2 py-2 text-sm font-semibold ${mine ? 'bg-white/15' : 'bg-slate-100'}`}><Paperclip size={16}/><span className="max-w-40 truncate">{attachment.name}</span></span>)}</div></button>{message.failed && <p className={`mt-1 px-1 text-xs font-semibold text-rose-600 ${mine ? 'text-right' : ''}`}>{message.error || "This message wasn't sent."}</p>}{pickerIsOpen && canChat && !message.pending && !message.failed && <div className={`absolute -top-12 z-20 flex gap-1 rounded-2xl border border-slate-200 bg-white p-1.5 shadow-[0_10px_28px_rgba(15,23,42,.18)] ${mine ? 'right-0' : 'left-0'}`}>{reactionEmojis.map((emoji) => <button key={emoji} type="button" onClick={() => react(message.id, emoji)} className="flex size-8 items-center justify-center rounded-xl text-lg transition hover:scale-110 hover:bg-slate-100 active:scale-95">{emoji}</button>)}</div>}{message.reactions?.length > 0 && <div className={`relative z-10 -mt-1 flex flex-wrap gap-1 px-1 ${mine ? 'justify-end' : ''}`}>{message.reactions.map((reaction) => <button key={reaction.emoji} type="button" onClick={() => canChat && react(message.id, reaction.emoji)} className={`rounded-full border px-1.5 py-0.5 text-xs shadow-sm ${reaction.reacted_by_me ? 'border-[#8de0ad] bg-[#e4f9ec]' : 'border-slate-200 bg-white'}`}>{reaction.emoji} <span className="font-semibold text-slate-500">{reaction.count}</span></button>)}</div>}{timeIsVisible && <p className={`mt-1.5 px-1 text-[11px] font-medium text-slate-400 ${mine ? 'text-right' : ''}`}>{messageTime(message.created_at)}</p>}</>}</div>
                        </div>;
                    })}</div>}
                    {typingText && <div className="mt-4 flex max-w-[78%] items-end gap-2.5 sm:max-w-[64%]" aria-live="polite" aria-label={typingText}>
                        <Avatar name={Object.values(typingUsers)[0]} index={0} size="sm"/>
                        <div className="min-w-0">
                            <p className="mb-1 px-1 text-xs font-bold text-slate-500">{typingText}</p>
                            <div className="inline-flex h-11 items-center gap-1.5 rounded-[1.45rem] rounded-bl-md bg-white px-4 shadow-[0_5px_18px_rgba(15,23,42,.075)]">
                                {[0, 1, 2].map((dot) => <span key={dot} className="size-2 rounded-full bg-slate-400 motion-safe:animate-bounce" style={{ animationDelay: `${dot * 140}ms`, animationDuration: '900ms' }}/>) }
                            </div>
                        </div>
                    </div>}
                    <div ref={endOfMessages}/>
                </div>
            </section>
            {canChat ? <form onSubmit={submit} className="z-10 shrink-0 border-t border-slate-200/80 bg-white/95 px-3 pb-[max(.5rem,env(safe-area-inset-bottom))] pt-1.5 shadow-[0_-5px_20px_rgba(15,23,42,.055)] backdrop-blur-xl sm:px-6">
                <div ref={emojiPicker} className="relative mx-auto max-w-5xl">{emojiPickerOpen && <div className="absolute bottom-[3.5rem] left-0 z-20 flex gap-1 rounded-2xl border border-slate-200 bg-white p-2 shadow-[0_10px_28px_rgba(15,23,42,.16)]">{quickEmojis.map((emoji) => <button key={emoji} type="button" onClick={() => { addEmoji(emoji); setEmojiPickerOpen(false); }} className="flex size-9 shrink-0 items-center justify-center rounded-xl text-lg transition hover:scale-110 hover:bg-[#e9faef]">{emoji}</button>)}</div>}<div className="flex items-center gap-2"><button type="button" onClick={() => setEmojiPickerOpen((open) => !open)} className={`flex size-10 shrink-0 items-center justify-center rounded-full transition ${emojiPickerOpen ? 'bg-[#2ecc70] text-white' : 'bg-[#e9faef] text-[#18ad58]'}`}><Smile size={20}/></button><button type="button" onClick={() => fileInput.current?.click()} className="flex size-10 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-600"><Paperclip size={19}/></button><input ref={fileInput} type="file" accept="image/jpeg,image/png,image/webp,application/pdf" className="hidden" onChange={(event) => setAttachment(event.target.files?.[0] ?? null)}/><input ref={input} value={draft} onChange={(event) => updateDraft(event.target.value)} onBlur={() => signalTyping(false)} maxLength="2000" className="h-11 min-w-0 flex-1 rounded-full border-0 bg-[#eef1f3] px-5 text-sm text-slate-800 placeholder:text-slate-400 transition focus:bg-white focus:ring-2 focus:ring-[#2ecc70]" placeholder={attachment ? `Attach: ${attachment.name}` : 'Message'}/><button type="button" onClick={() => setSettlementModalOpen(true)} className="flex size-10 shrink-0 items-center justify-center rounded-full bg-[#e9faef] text-[#18ad58]" aria-label="Request settlement"><WalletCards size={19}/></button><button type="submit" disabled={!draft.trim() && !attachment} className="flex size-11 shrink-0 items-center justify-center rounded-full bg-[linear-gradient(135deg,#31d776_0%,#1aba5d_100%)] text-white shadow-[0_8px_18px_rgba(29,190,93,.28)] disabled:opacity-45"><Send size={19}/></button></div></div>
            </form> : <div className="shrink-0 border-t border-slate-200 bg-white px-5 py-4 text-center text-sm text-slate-500">You have view-only access to this conversation.</div>}
            {settlementModalOpen && <div className="fixed inset-0 z-50 flex items-end bg-slate-950/40 sm:items-center sm:justify-center"><form onSubmit={submitSettlement} className="w-full rounded-t-3xl bg-white p-6 shadow-2xl sm:max-w-md sm:rounded-3xl"><div className="flex items-center justify-between"><h2 className="font-display text-2xl font-bold">Request settlement</h2><button type="button" onClick={() => setSettlementModalOpen(false)} className="rounded-full p-2 text-slate-500"><X size={20}/></button></div><p className="mt-2 text-sm text-slate-500">The recipient must approve before balances change.</p>{settlementOptions.length === 0 ? <p className="mt-6 rounded-2xl bg-slate-50 p-4 text-sm text-slate-500">You do not have an outstanding direct obligation to settle.</p> : <><label className="mt-5 block text-sm font-bold">Paying</label><select value={selectedDebt} onChange={(event) => setSelectedDebt(event.target.value)} className="mt-2 h-12 w-full rounded-2xl border-slate-200"><option value="">Choose member</option>{settlementOptions.map((option) => <option key={option.to_user_id} value={option.to_user_id}>{option.to_name} · due {money(option.amount_minor, tracker.currency_code)}</option>)}</select><label className="mt-4 block text-sm font-bold">Amount</label><input required value={settlementAmount} onChange={(event) => setSettlementAmount(event.target.value)} inputMode="decimal" placeholder="0.00" className="mt-2 h-12 w-full rounded-2xl border-slate-200 px-4"/><label className="mt-4 block text-sm font-bold">Note <span className="font-normal text-slate-400">optional</span></label><input value={settlementNote} onChange={(event) => setSettlementNote(event.target.value)} className="mt-2 h-12 w-full rounded-2xl border-slate-200 px-4" placeholder="e.g. sent via bank transfer"/><button className="mt-6 h-12 w-full rounded-2xl bg-[#25c96a] font-bold text-white">Send for approval</button></>}</form></div>}
        </div>
    </AuthenticatedLayout>;
}
