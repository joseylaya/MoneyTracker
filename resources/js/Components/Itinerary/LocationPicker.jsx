import { loadGoogleMaps } from '@/lib/googleMaps';
import { LoaderCircle, MapPin, Search, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

const DEFAULT_CENTER = { lat: 10.3157, lng: 123.8854 };

export default function LocationPicker({ value, onChange, errors = {} }) {
    const mapContainer = useRef(null);
    const mapRef = useRef(null);
    const markerRef = useRef(null);
    const placesRef = useRef(null);
    const sessionTokenRef = useRef(null);
    const requestRef = useRef(0);
    const selectedQueryRef = useRef(value.location_name || value.location_address || '');
    const valueRef = useRef(value);
    const onChangeRef = useRef(onChange);
    const [query, setQuery] = useState(value.location_name || value.location_address || '');
    const [suggestions, setSuggestions] = useState([]);
    const [searching, setSearching] = useState(false);
    const [searchError, setSearchError] = useState('');
    const [placesReady, setPlacesReady] = useState(false);
    const [focused, setFocused] = useState(false);
    const [activeIndex, setActiveIndex] = useState(-1);
    const [message, setMessage] = useState('Search for a place, then drag or click the map to set the exact pin.');
    valueRef.current = value;
    onChangeRef.current = onChange;

    useEffect(() => {
        let cancelled = false;
        let map;
        let marker;
        let mapClickListener;
        let dragListener;

        const setPosition = (position, place = {}) => {
            marker.setPosition(position);
            marker.setVisible(true);
            map.panTo(position);
            map.setZoom(17);
            onChangeRef.current({
                latitude: position.lat(),
                longitude: position.lng(),
                location_name: place.location_name ?? valueRef.current.location_name,
                location_address: place.location_address ?? valueRef.current.location_address,
            });
            setMessage('Pin selected. Drag it or click elsewhere for a more exact location.');
        };

        const initialize = async () => {
            try {
                const maps = await loadGoogleMaps();
                placesRef.current = await maps.importLibrary('places');
                sessionTokenRef.current = new placesRef.current.AutocompleteSessionToken();
                if (cancelled) return;
                const hasPosition = valueRef.current.latitude !== '' && valueRef.current.longitude !== '' && valueRef.current.latitude != null && valueRef.current.longitude != null;
                const center = hasPosition ? { lat: Number(valueRef.current.latitude), lng: Number(valueRef.current.longitude) } : DEFAULT_CENTER;
                map = new maps.Map(mapContainer.current, { center, zoom: hasPosition ? 17 : 11, mapTypeControl: false, streetViewControl: false, fullscreenControl: false });
                marker = new maps.Marker({ map, position: center, visible: hasPosition, draggable: true, title: 'Drag to set exact location' });
                mapRef.current = map;
                markerRef.current = marker;
                mapClickListener = map.addListener('click', ({ latLng }) => setPosition(latLng));
                dragListener = marker.addListener('dragend', ({ latLng }) => setPosition(latLng));
                setPlacesReady(true);
            } catch (error) {
                console.error('Location picker failed', error);
                setMessage('Map search is unavailable. Confirm that Places API (New) is enabled.');
            }
        };
        initialize();
        return () => {
            cancelled = true;
            mapClickListener?.remove();
            dragListener?.remove();
            marker?.setMap(null);
            mapRef.current = null;
            markerRef.current = null;
            placesRef.current = null;
        };
    }, []);

    useEffect(() => {
        if (!placesReady || query.trim().length < 2 || query === selectedQueryRef.current) { setSuggestions([]); setSearching(false); return undefined; }
        const requestId = ++requestRef.current;
        setSearching(true);
        setSearchError('');
        const timer = window.setTimeout(async () => {
            try {
                const { suggestions: results } = await placesRef.current.AutocompleteSuggestion.fetchAutocompleteSuggestions({
                    input: query.trim(),
                    sessionToken: sessionTokenRef.current,
                });
                if (requestId === requestRef.current) setSuggestions(results.map((result) => result.placePrediction).filter(Boolean));
            } catch (error) {
                console.error('Place suggestions failed', error);
                if (requestId === requestRef.current) {
                    setSuggestions([]);
                    setSearchError(error?.message || 'Google Places search is unavailable.');
                }
            } finally {
                if (requestId === requestRef.current) setSearching(false);
            }
        }, 250);
        return () => window.clearTimeout(timer);
    }, [query, placesReady]);

    const chooseSuggestion = async (prediction) => {
        setSearching(true);
        try {
            const place = prediction.toPlace();
            await place.fetchFields({ fields: ['displayName', 'formattedAddress', 'location'] });
            if (!place.location || !markerRef.current || !mapRef.current) return;
            markerRef.current.setPosition(place.location);
            markerRef.current.setVisible(true);
            mapRef.current.panTo(place.location);
            mapRef.current.setZoom(17);
            const name = place.displayName || prediction.mainText?.toString() || prediction.text.toString();
            selectedQueryRef.current = name;
            setQuery(name);
            setSuggestions([]);
            setFocused(false);
            onChangeRef.current({ latitude: place.location.lat(), longitude: place.location.lng(), location_name: name, location_address: place.formattedAddress || '' });
            sessionTokenRef.current = new placesRef.current.AutocompleteSessionToken();
            setMessage('Place selected. Drag the pin or click the map to fine-tune it.');
        } finally {
            setSearching(false);
        }
    };

    const handleKeyDown = (event) => {
        if (!suggestions.length) return;
        if (event.key === 'ArrowDown') { event.preventDefault(); setActiveIndex((index) => Math.min(index + 1, suggestions.length - 1)); }
        if (event.key === 'ArrowUp') { event.preventDefault(); setActiveIndex((index) => Math.max(index - 1, 0)); }
        if (event.key === 'Enter' && activeIndex >= 0) { event.preventDefault(); chooseSuggestion(suggestions[activeIndex]); }
        if (event.key === 'Escape') { setSuggestions([]); setFocused(false); }
    };

    return <div className="space-y-3">
        <div className="relative z-20">
            <div className={`flex items-center gap-2 rounded-2xl border bg-white px-4 shadow-sm transition ${focused ? 'border-[#2ecc70] ring-4 ring-[#2ecc70]/10' : 'border-slate-200'}`}>
                {searching ? <LoaderCircle className="animate-spin text-[#20b960]" size={19}/> : <Search className="text-slate-400" size={19}/>}
                <input value={query} onChange={(event) => { selectedQueryRef.current = ''; setQuery(event.target.value); setActiveIndex(-1); }} onFocus={() => setFocused(true)} onBlur={() => window.setTimeout(() => setFocused(false), 150)} onKeyDown={handleKeyDown} placeholder="Search a place or address" autoComplete="off" className="h-12 min-w-0 flex-1 border-0 bg-transparent px-0 text-sm font-medium focus:ring-0"/>
                {query && <button type="button" onClick={() => { selectedQueryRef.current = ''; setQuery(''); setSuggestions([]); }} className="flex size-8 items-center justify-center rounded-full text-slate-400 hover:bg-slate-100" aria-label="Clear location search"><X size={16}/></button>}
            </div>
            {focused && query.trim().length >= 2 && <div className="absolute left-0 right-0 top-[calc(100%+.45rem)] max-h-64 overflow-y-auto rounded-2xl border border-slate-200 bg-white p-1.5 shadow-2xl">
                {suggestions.map((prediction, index) => <button key={prediction.placeId} type="button" onMouseDown={(event) => event.preventDefault()} onClick={() => chooseSuggestion(prediction)} className={`flex w-full items-start gap-3 rounded-xl px-3 py-3 text-left transition ${activeIndex === index ? 'bg-[#edfbf3]' : 'hover:bg-slate-50'}`}><span className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full bg-[#eafaf1] text-[#16a653]"><MapPin size={16}/></span><span className="min-w-0"><span className="block truncate text-sm font-bold text-slate-800">{prediction.mainText?.toString() || prediction.text.toString()}</span>{prediction.secondaryText && <span className="mt-0.5 block truncate text-xs text-slate-500">{prediction.secondaryText.toString()}</span>}</span></button>)}
                {!searching && searchError && <div className="rounded-xl bg-rose-50 px-4 py-4 text-center text-sm font-semibold text-rose-700">Place recommendations are unavailable. Enable Places API (New) for this API key, then try again.</div>}
                {!searching && !searchError && suggestions.length === 0 && <div className="px-4 py-5 text-center text-sm text-slate-500">No matching places found. Try including the city or country.</div>}
                {searching && suggestions.length === 0 && <div className="flex items-center justify-center gap-2 px-4 py-5 text-sm text-slate-500"><LoaderCircle className="animate-spin" size={16}/>Searching Google Maps…</div>}
            </div>}
        </div>
        <div ref={mapContainer} className="h-72 overflow-hidden rounded-2xl border border-slate-200 bg-slate-100" aria-label="Choose an exact itinerary location on Google Maps"/>
        <p className="text-xs text-slate-500">{message}</p>
        {(errors.latitude || errors.longitude) && <p className="text-xs font-semibold text-rose-600">{errors.latitude || errors.longitude}</p>}
        {value.latitude !== '' && value.longitude !== '' && <div className="rounded-xl bg-[#edfbf3] px-3 py-2 text-xs font-semibold text-[#109a49]">📍 {value.location_name || 'Pinned location'}{value.location_address ? ` · ${value.location_address}` : ''}</div>}
    </div>;
}
