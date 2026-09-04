import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import DayMap from '@/Components/Itinerary/DayMap';
import LocationPicker from '@/Components/Itinerary/LocationPicker';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowDown, ArrowUp, CalendarDays, Car, ChevronLeft, Footprints, MapPin, Pencil, Plus, ReceiptText, Trash2, X } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

const types = {
    activity: ['Activity', '🎯'], food: ['Food', '🍜'], accommodation: ['Accommodation', '🏨'],
    transport: ['Transport', '🚆'], shopping: ['Shopping', '🛍️'], place: ['Place', '📍'], other: ['Other', '📝'],
};
const blankItem = { title: '', description: '', type: 'place', start_time: '', end_time: '', location_name: '', location_address: '', latitude: '', longitude: '', notes: '' };

function readableDate(value) {
    return new Intl.DateTimeFormat(undefined, { weekday: 'long', month: 'long', day: 'numeric', timeZone: 'UTC' }).format(new Date(`${String(value).slice(0, 10)}T00:00:00Z`));
}
function shortDay(value) {
    return new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric', timeZone: 'UTC' }).format(new Date(`${String(value).slice(0, 10)}T00:00:00Z`));
}
function readableTime(value) {
    if (!value) return null;
    const [hour, minute] = value.slice(0, 5).split(':');
    return new Intl.DateTimeFormat(undefined, { hour: 'numeric', minute: '2-digit' }).format(new Date(2000, 0, 1, Number(hour), Number(minute)));
}

function Modal({ title, onClose, children }) {
    useEffect(() => { const close = (event) => event.key === 'Escape' && onClose(); window.addEventListener('keydown', close); return () => window.removeEventListener('keydown', close); }, [onClose]);
    return <div className="fixed inset-0 z-50 flex items-end justify-center bg-slate-950/45 p-0 sm:items-center sm:p-5" onMouseDown={(event) => event.target === event.currentTarget && onClose()}>
        <div role="dialog" aria-modal="true" className="max-h-[92dvh] w-full overflow-y-auto rounded-t-[2rem] bg-white p-5 shadow-2xl sm:max-w-xl sm:rounded-[2rem] sm:p-7">
            <div className="flex items-center justify-between"><h2 className="font-display text-2xl font-bold">{title}</h2><button type="button" onClick={onClose} className="flex size-10 items-center justify-center rounded-full bg-slate-100" aria-label="Close"><X size={20}/></button></div>
            {children}
        </div>
    </div>;
}
function Field({ label, error, children }) { return <label className="block text-sm font-semibold text-slate-700"><span>{label}</span><span className="mt-1.5 block">{children}</span>{error && <span className="mt-1 block text-xs text-rose-600">{error}</span>}</label>; }
const inputClass = 'w-full rounded-2xl border-slate-200 bg-slate-50 px-4 py-3 text-sm focus:border-[#2ecc70] focus:ring-[#2ecc70]';

function DayForm({ tracker, day, onClose }) {
    const form = useForm({ date: day?.date?.slice(0, 10) || '', title: day?.title || '', notes: day?.notes || '', route_mode: day?.route_mode || 'driving' });
    const submit = (event) => { event.preventDefault(); const options = { preserveScroll: true, onSuccess: onClose }; day ? form.patch(route('trackers.itinerary.days.update', [tracker.id, day.id]), options) : form.post(route('trackers.itinerary.days.store', tracker.id), options); };
    return <form onSubmit={submit} className="mt-6 space-y-4">
        <Field label="Date" error={form.errors.date}><input type="date" value={form.data.date} onChange={(e) => form.setData('date', e.target.value)} className={inputClass} required /></Field>
        <Field label="Day title (optional)" error={form.errors.title}><input value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} placeholder="Arrival, Old Town, Beach day…" className={inputClass}/></Field>
        <Field label="Default route mode" error={form.errors.route_mode}><select value={form.data.route_mode} onChange={(e) => form.setData('route_mode', e.target.value)} className={inputClass}><option value="driving">Driving</option><option value="walking">Walking</option></select></Field>
        <Field label="Notes (optional)" error={form.errors.notes}><textarea rows="3" value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} className={inputClass}/></Field>
        <button disabled={form.processing} className="ss-button w-full">{day ? 'Save day' : 'Add day'}</button>
    </form>;
}

function ItemForm({ tracker, day, item, onClose }) {
    const form = useForm(item ? Object.fromEntries(Object.keys(blankItem).map((key) => [key, item[key] ?? ''])) : blankItem);
    const submit = (event) => { event.preventDefault(); const options = { preserveScroll: true, onSuccess: onClose }; item ? form.patch(route('trackers.itinerary.items.update', [tracker.id, item.id]), options) : form.post(route('trackers.itinerary.items.store', [tracker.id, day.id]), options); };
    return <form onSubmit={submit} className="mt-6 space-y-4">
        <Field label="What are you adding?" error={form.errors.type}><div className="grid grid-cols-2 gap-2 sm:grid-cols-4">{Object.entries(types).map(([value, [label, emoji]]) => <button key={value} type="button" onClick={() => form.setData('type', value)} className={`rounded-2xl border px-3 py-3 text-left text-xs font-bold ${form.data.type === value ? 'border-[#2ecc70] bg-[#edfbf3] text-[#109a49]' : 'border-slate-200 bg-white'}`}><span className="mr-1">{emoji}</span>{label}</button>)}</div></Field>
        <Field label="Name" error={form.errors.title}><input value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} placeholder="Shibuya Crossing" className={inputClass} required/></Field>
        <div className="grid grid-cols-2 gap-3"><Field label="Start time" error={form.errors.start_time}><input type="time" value={form.data.start_time?.slice(0, 5)} onChange={(e) => form.setData('start_time', e.target.value)} className={inputClass}/></Field><Field label="End time" error={form.errors.end_time}><input type="time" value={form.data.end_time?.slice(0, 5)} onChange={(e) => form.setData('end_time', e.target.value)} className={inputClass}/></Field></div>
        <Field label="Location"><LocationPicker value={form.data} errors={form.errors} onChange={(location) => form.setData({ ...form.data, ...location })}/></Field>
        <Field label="Description (optional)" error={form.errors.description}><textarea rows="2" value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} className={inputClass}/></Field>
        <Field label="Notes (optional)" error={form.errors.notes}><textarea rows="3" value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} className={inputClass}/></Field>
        <button disabled={form.processing} className="ss-button w-full">{item ? 'Save item' : 'Add to itinerary'}</button>
    </form>;
}

export default function Index({ tracker, days, canManage }) {
    const { flash } = usePage().props;
    const [selectedId, setSelectedId] = useState(days[0]?.id || null);
    const [modal, setModal] = useState(null);
    useEffect(() => { if (!days.some((day) => day.id === selectedId)) setSelectedId(days[0]?.id || null); }, [days, selectedId]);
    const selected = useMemo(() => days.find((day) => day.id === selectedId) || days[0], [days, selectedId]);
    const removeDay = (day) => window.confirm(`Delete ${readableDate(day.date)} and all of its itinerary items?`) && router.delete(route('trackers.itinerary.days.destroy', [tracker.id, day.id]), { preserveScroll: true });
    const removeItem = (item) => window.confirm(`Delete ${item.title}? Linked expenses will be kept.`) && router.delete(route('trackers.itinerary.items.destroy', [tracker.id, item.id]), { preserveScroll: true });
    const move = (index, direction) => { const items = [...selected.items]; const target = index + direction; if (target < 0 || target >= items.length) return; [items[index], items[target]] = [items[target], items[index]]; router.patch(route('trackers.itinerary.reorder', tracker.id), { day_id: selected.id, item_ids: items.map((item) => item.id) }, { preserveScroll: true }); };
    return <AuthenticatedLayout header={<div className="flex items-center justify-between"><Link href={route('trackers.show', tracker.id)} className="flex size-10 items-center justify-center rounded-full text-slate-700"><ChevronLeft size={28}/></Link><div className="min-w-0 text-center"><h1 className="truncate font-display text-xl font-bold">{tracker.name}</h1><p className="mt-0.5 text-[10px] font-bold uppercase tracking-[.14em] text-[#18b95b]">Itinerary</p></div><span className="size-10"/></div>}>
        <Head title={`Itinerary · ${tracker.name}`}/><div className="page-wrap max-w-4xl">
            {flash.success && <div className="mb-4 rounded-2xl bg-[#e9fbf0] px-4 py-3 text-sm font-medium text-[#0d9b4b]">{flash.success}</div>}
            <div className="flex items-start justify-between gap-4"><div><h2 className="font-display text-3xl font-bold">Trip plan</h2><p className="mt-1 text-sm text-slate-500">Plan each day in order. Locations with coordinates are ready for the map.</p></div>{canManage && <button onClick={() => setModal({ type: 'day' })} className="ss-button h-11 shrink-0 gap-2 px-4"><Plus size={18}/>Day</button>}</div>
            {days.length === 0 ? <div className="mt-6 ss-card p-10 text-center"><CalendarDays className="mx-auto text-[#22bd63]" size={38}/><h3 className="mt-4 font-display text-xl font-bold">Start your itinerary</h3><p className="mx-auto mt-2 max-w-sm text-sm text-slate-500">Add a travel day, then organize places, meals, accommodation, and activities.</p>{canManage && <button onClick={() => setModal({ type: 'day' })} className="ss-button mt-5">Add first day</button>}</div> : <>
                <div className="-mx-5 mt-6 flex gap-2 overflow-x-auto px-5 pb-2">{days.map((day) => <button key={day.id} onClick={() => setSelectedId(day.id)} className={`shrink-0 rounded-2xl border px-4 py-3 text-left ${selected?.id === day.id ? 'border-[#2ecc70] bg-[#eafaf1] text-[#119d4b]' : 'border-slate-200 bg-white text-slate-600'}`}><span className="block text-xs font-bold uppercase tracking-wide">{shortDay(day.date)}</span><span className="mt-0.5 block max-w-28 truncate text-xs">{day.title || readableDate(day.date).split(',')[0]}</span></button>)}</div>
                {selected && <section className="mt-5"><div className="flex items-center justify-between gap-3"><div><h3 className="font-display text-2xl font-bold">{readableDate(selected.date)}</h3>{selected.title && <p className="mt-1 font-semibold text-slate-500">{selected.title}</p>}</div>{canManage && <div className="flex gap-1"><button onClick={() => setModal({ type: 'day', day: selected })} className="flex size-10 items-center justify-center rounded-xl border border-slate-200 bg-white" aria-label="Edit day"><Pencil size={17}/></button><button onClick={() => removeDay(selected)} className="flex size-10 items-center justify-center rounded-xl border border-rose-200 bg-white text-rose-600" aria-label="Delete day"><Trash2 size={17}/></button></div>}</div>
                    <div className="mt-3 flex items-center gap-2 text-xs font-semibold text-slate-500">{selected.route_mode === 'walking' ? <Footprints size={16}/> : <Car size={16}/>} {selected.route_mode === 'walking' ? 'Walking' : 'Driving'} route · {selected.items.filter((item) => item.latitude != null && item.longitude != null).length} mapped stops</div>
                    {selected.notes && <p className="mt-3 rounded-2xl bg-amber-50 px-4 py-3 text-sm text-amber-900">{selected.notes}</p>}
                    <div className="relative mt-5 space-y-3 before:absolute before:bottom-7 before:left-[1.35rem] before:top-7 before:w-px before:bg-[#b9eccc]">{selected.items.map((item, index) => <article key={item.id} className="relative flex gap-3"><div className="z-10 mt-4 flex size-11 shrink-0 items-center justify-center rounded-full border-4 border-[#f6f8f7] bg-[#24be65] font-bold text-white">{index + 1}</div><div className="min-w-0 flex-1 rounded-[1.5rem] border border-slate-100 bg-white p-4 shadow-[0_3px_12px_rgba(15,23,42,.04)]"><div className="flex items-start justify-between gap-3"><div className="min-w-0"><p className="text-xs font-bold uppercase tracking-wide text-[#17ad55]">{types[item.type]?.[1]} {types[item.type]?.[0]}{readableTime(item.start_time) && ` · ${readableTime(item.start_time)}`}</p><h4 className="mt-1 truncate font-display text-lg font-bold">{item.title}</h4></div>{canManage && <div className="flex shrink-0"><button disabled={index === 0} onClick={() => move(index, -1)} className="flex size-8 items-center justify-center disabled:opacity-20" aria-label={`Move ${item.title} up`}><ArrowUp size={16}/></button><button disabled={index === selected.items.length - 1} onClick={() => move(index, 1)} className="flex size-8 items-center justify-center disabled:opacity-20" aria-label={`Move ${item.title} down`}><ArrowDown size={16}/></button><button onClick={() => setModal({ type: 'item', day: selected, item })} className="flex size-8 items-center justify-center" aria-label={`Edit ${item.title}`}><Pencil size={15}/></button><button onClick={() => removeItem(item)} className="flex size-8 items-center justify-center text-rose-500" aria-label={`Delete ${item.title}`}><Trash2 size={15}/></button></div>}</div>
                                {(item.location_name || item.location_address) && <p className="mt-2 flex items-start gap-1.5 text-sm text-slate-500"><MapPin className="mt-0.5 shrink-0" size={15}/><span>{item.location_name}{item.location_name && item.location_address ? ' · ' : ''}{item.location_address}</span></p>}
                                {item.description && <p className="mt-2 text-sm text-slate-600">{item.description}</p>}{item.notes && <p className="mt-2 text-sm italic text-slate-500">{item.notes}</p>}
                                {item.expenses?.length > 0 && <div className="mt-3 border-t border-slate-100 pt-3 text-xs font-semibold text-slate-500"><ReceiptText className="mr-1 inline" size={14}/>{item.expenses.length} linked expense{item.expenses.length === 1 ? '' : 's'}</div>}
                                {canManage && <Link href={route('trackers.expenses.create', { tracker: tracker.id, itinerary_item: item.id })} className="mt-3 inline-flex items-center gap-1 text-xs font-bold text-[#139e4d]"><Plus size={14}/>Add linked expense</Link>}
                            </div></article>)}
                        {selected.items.length === 0 && <div className="relative rounded-[1.5rem] border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">Nothing planned for this day yet.</div>}</div>
                    {canManage && <button onClick={() => setModal({ type: 'item', day: selected })} className="ss-button mt-5 w-full gap-2"><Plus size={18}/>Add itinerary item</button>}
                    {selected.items.some((item) => item.latitude != null) && <DayMap trackerId={tracker.id} day={selected}/>}
                </section>}
            </>}
        </div>
        {modal?.type === 'day' && <Modal title={modal.day ? 'Edit day' : 'Add itinerary day'} onClose={() => setModal(null)}><DayForm tracker={tracker} day={modal.day} onClose={() => setModal(null)}/></Modal>}
        {modal?.type === 'item' && <Modal title={modal.item ? 'Edit itinerary item' : 'Add to itinerary'} onClose={() => setModal(null)}><ItemForm tracker={tracker} day={modal.day} item={modal.item} onClose={() => setModal(null)}/></Modal>}
    </AuthenticatedLayout>;
}
