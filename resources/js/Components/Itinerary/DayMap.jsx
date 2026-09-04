import { useEffect, useRef, useState } from 'react';
import { loadGoogleMaps } from '@/lib/googleMaps';

export default function DayMap({ trackerId, day }) {
    const container = useRef(null);
    const mapRef = useRef(null);
    const routeCoordinatesRef = useRef([]);
    const itineraryCoordinatesRef = useRef([]);
    const routeMetricsRef = useRef(null);
    const navigationMarkerRef = useRef(null);
    const navigationPolylinesRef = useRef([]);
    const rerouteInFlightRef = useRef(false);
    const lastRerouteRef = useRef(0);
    const watchIdRef = useRef(null);
    const [summary, setSummary] = useState(null);
    const [routeUnavailable, setRouteUnavailable] = useState(false);
    const [navigating, setNavigating] = useState(false);
    const [navigation, setNavigation] = useState(null);
    const [navigationError, setNavigationError] = useState('');
    const stops = day.items.filter((item) => item.latitude != null && item.longitude != null);
    const stopKey = stops.map((item) => `${item.id}:${item.longitude},${item.latitude}`).join('|');

    useEffect(() => {
        if (!container.current || !stops.length) return undefined;
        let cancelled = false;
        let map;
        let markers = [];
        let polylines = [];
        let userMarker;
        let locationButton;

        const initialize = async () => {
            setSummary(null); setRouteUnavailable(false);
            try {
                const maps = await loadGoogleMaps();
                if (cancelled) return;
                map = new maps.Map(container.current, {
                    center: { lat: Number(stops[0].latitude), lng: Number(stops[0].longitude) },
                    zoom: 12,
                    mapTypeControl: false,
                    streetViewControl: false,
                    fullscreenControl: false,
                });
                mapRef.current = map;
                const bounds = new maps.LatLngBounds();
                const infoWindow = new maps.InfoWindow();
                markers = stops.map((stop, index) => {
                    const position = { lat: Number(stop.latitude), lng: Number(stop.longitude) };
                    bounds.extend(position);
                    const marker = new maps.Marker({
                        map,
                        position,
                        title: stop.title,
                        label: { text: String(index + 1), color: '#ffffff', fontWeight: '700' },
                        icon: { path: maps.SymbolPath.CIRCLE, fillColor: '#3158cf', fillOpacity: 1, strokeColor: '#ffffff', strokeWeight: 3, scale: 16 },
                        zIndex: 20,
                    });
                    marker.addListener('click', () => { infoWindow.setContent(`<strong>${stop.title}</strong>`); infoWindow.open({ map, anchor: marker }); });
                    return marker;
                });
                if (stops.length > 1) map.fitBounds(bounds, 55);

                locationButton = document.createElement('button');
                locationButton.type = 'button';
                locationButton.title = 'Show my location';
                locationButton.className = 'm-2 flex size-10 items-center justify-center rounded-sm bg-white text-xl shadow-md';
                locationButton.textContent = '◎';
                locationButton.addEventListener('click', () => navigator.geolocation?.getCurrentPosition(({ coords }) => {
                    const position = { lat: coords.latitude, lng: coords.longitude };
                    if (!userMarker) userMarker = new maps.Marker({ map, position, title: 'Your location', icon: { path: maps.SymbolPath.CIRCLE, fillColor: '#1687ff', fillOpacity: 1, strokeColor: '#ffffff', strokeWeight: 3, scale: 9 } });
                    else userMarker.setPosition(position);
                    map.panTo(position);
                }));
                map.controls[maps.ControlPosition.RIGHT_BOTTOM].push(locationButton);

                if (stops.length < 2) return;
                const response = await fetch(route('trackers.itinerary.route', [trackerId, day.id]), { headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error('Route unavailable');
                const result = await response.json();
                if (cancelled) return;
                setSummary({ distance: result.distance_meters, duration: result.duration_seconds, legs: result.legs || [] });
                const path = (result.geometry?.coordinates || []).map(([lng, lat]) => ({ lat, lng }));
                routeCoordinatesRef.current = path;
                itineraryCoordinatesRef.current = path;
                routeMetricsRef.current = { distance: result.distance_meters, duration: result.duration_seconds };
                const outline = new maps.Polyline({ map, path, strokeColor: '#ffffff', strokeOpacity: 1, strokeWeight: 11, zIndex: 5 });
                const walking = day.route_mode === 'walking';
                const routeLine = new maps.Polyline({
                    map,
                    path,
                    strokeColor: '#0066ff',
                    strokeOpacity: walking ? 0 : 1,
                    strokeWeight: 7,
                    zIndex: 6,
                    icons: walking ? [{ icon: { path: 'M 0,-1 0,1', strokeColor: '#0066ff', strokeOpacity: 1, strokeWeight: 6, scale: 2 }, offset: '0', repeat: '16px' }] : undefined,
                });
                polylines = [outline, routeLine];
            } catch (error) { console.error('Itinerary route polyline failed', error); setRouteUnavailable(true); }
        };
        initialize();
        return () => {
            cancelled = true;
            markers.forEach((marker) => marker.setMap(null));
            polylines.forEach((polyline) => polyline.setMap(null));
            userMarker?.setMap(null);
            navigationMarkerRef.current?.setMap(null);
            navigationMarkerRef.current = null;
            navigationPolylinesRef.current.forEach((polyline) => polyline.setMap(null));
            navigationPolylinesRef.current = [];
            routeCoordinatesRef.current = [];
            itineraryCoordinatesRef.current = [];
            routeMetricsRef.current = null;
            if (watchIdRef.current != null) navigator.geolocation?.clearWatch(watchIdRef.current);
            watchIdRef.current = null;
            locationButton?.remove();
            mapRef.current = null;
        };
    }, [trackerId, day.id, day.route_mode, stopKey]);

    const distanceBetween = (from, to) => {
        const radians = (degrees) => degrees * Math.PI / 180;
        const deltaLatitude = radians(to.lat - from.lat);
        const deltaLongitude = radians(to.lng - from.lng);
        const a = Math.sin(deltaLatitude / 2) ** 2 + Math.cos(radians(from.lat)) * Math.cos(radians(to.lat)) * Math.sin(deltaLongitude / 2) ** 2;
        return 6371000 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    };

    const loadLiveRoute = async (position) => {
        if (rerouteInFlightRef.current || !mapRef.current || !window.google?.maps) return;
        rerouteInFlightRef.current = true;
        lastRerouteRef.current = Date.now();
        try {
            const url = new URL(route('trackers.itinerary.route', [trackerId, day.id]), window.location.origin);
            url.searchParams.set('origin_latitude', position.lat);
            url.searchParams.set('origin_longitude', position.lng);
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('Unable to calculate route from your location');
            const result = await response.json();
            const path = (result.geometry?.coordinates || []).map(([lng, lat]) => ({ lat, lng }));
            routeCoordinatesRef.current = path;
            routeMetricsRef.current = { distance: result.distance_meters, duration: result.duration_seconds };
            navigationPolylinesRef.current.forEach((polyline) => polyline.setMap(null));
            const maps = window.google.maps;
            const walking = day.route_mode === 'walking';
            navigationPolylinesRef.current = [
                new maps.Polyline({ map: mapRef.current, path, strokeColor: '#ffffff', strokeOpacity: 1, strokeWeight: 13, zIndex: 30 }),
                new maps.Polyline({ map: mapRef.current, path, strokeColor: '#0066ff', strokeOpacity: walking ? 0 : 1, strokeWeight: 8, zIndex: 31, icons: walking ? [{ icon: { path: 'M 0,-1 0,1', strokeColor: '#0066ff', strokeOpacity: 1, strokeWeight: 7, scale: 2 }, offset: '0', repeat: '17px' }] : undefined }),
            ];
        } catch (error) {
            console.error('Live itinerary route failed', error);
            setNavigationError('Could not calculate a route from your current location.');
        } finally {
            rerouteInFlightRef.current = false;
        }
    };

    const navigationUpdate = ({ coords }) => {
        const map = mapRef.current;
        const maps = window.google?.maps;
        if (!map || !maps) return;
        const position = { lat: coords.latitude, lng: coords.longitude };
        if (!navigationMarkerRef.current) {
            navigationMarkerRef.current = new maps.Marker({
                map,
                position,
                title: 'Your live location',
                zIndex: 50,
                icon: { path: maps.SymbolPath.FORWARD_CLOSED_ARROW, fillColor: '#1687ff', fillOpacity: 1, rotation: Number.isFinite(coords.heading) ? coords.heading : 0, strokeColor: '#ffffff', strokeWeight: 3, scale: 7 },
            });
        } else {
            navigationMarkerRef.current.setPosition(position);
            navigationMarkerRef.current.setIcon({ path: maps.SymbolPath.FORWARD_CLOSED_ARROW, fillColor: '#1687ff', fillOpacity: 1, rotation: Number.isFinite(coords.heading) ? coords.heading : 0, strokeColor: '#ffffff', strokeWeight: 3, scale: 7 });
        }
        map.panTo(position);
        if (map.getZoom() < 16) map.setZoom(16);

        if (!navigationPolylinesRef.current.length) loadLiveRoute(position);

        const path = routeCoordinatesRef.current;
        const metrics = routeMetricsRef.current;
        if (!path.length || !metrics) return;
        let nearestIndex = 0;
        let nearestDistance = Infinity;
        path.forEach((point, index) => {
            const distance = distanceBetween(position, point);
            if (distance < nearestDistance) { nearestDistance = distance; nearestIndex = index; }
        });
        let remainingMeters = distanceBetween(position, path[nearestIndex]);
        for (let index = nearestIndex; index < path.length - 1; index += 1) remainingMeters += distanceBetween(path[index], path[index + 1]);
        const remainingSeconds = metrics.distance > 0 ? metrics.duration * Math.min(1, remainingMeters / metrics.distance) : 0;
        let nextIndex = 1;
        for (let index = 1; index < stops.length; index += 1) {
            const stopPosition = { lat: Number(stops[index].latitude), lng: Number(stops[index].longitude) };
            if (distanceBetween(position, stopPosition) > 75) { nextIndex = index; break; }
            nextIndex = Math.min(index + 1, stops.length - 1);
        }
        const arrived = remainingMeters < 75;
        if (nearestDistance > 100 && Date.now() - lastRerouteRef.current > 30000) loadLiveRoute(position);
        setNavigation({ remainingMeters, remainingSeconds, nextIndex, accuracy: coords.accuracy, arrived, offRoute: nearestDistance > 100 });
    };

    const startNavigation = () => {
        setNavigationError('');
        if (!navigator.geolocation) { setNavigationError('Live location is not supported by this browser.'); return; }
        if (!routeCoordinatesRef.current.length) { setNavigationError('Wait for the route to finish loading.'); return; }
        setNavigating(true);
        watchIdRef.current = navigator.geolocation.watchPosition(navigationUpdate, (error) => {
            setNavigating(false);
            setNavigationError(error.code === 1 ? 'Location permission is required to start the trip.' : 'Unable to get your live location.');
        }, { enableHighAccuracy: true, maximumAge: 2000, timeout: 15000 });
    };

    const stopNavigation = () => {
        if (watchIdRef.current != null) navigator.geolocation.clearWatch(watchIdRef.current);
        watchIdRef.current = null;
        navigationMarkerRef.current?.setMap(null);
        navigationMarkerRef.current = null;
        navigationPolylinesRef.current.forEach((polyline) => polyline.setMap(null));
        navigationPolylinesRef.current = [];
        routeCoordinatesRef.current = itineraryCoordinatesRef.current;
        routeMetricsRef.current = summary ? { distance: summary.distance, duration: summary.duration } : null;
        setNavigating(false);
        setNavigation(null);
    };

    const distance = summary ? summary.distance >= 1000 ? `${(summary.distance / 1000).toFixed(1)} km` : `${Math.round(summary.distance)} m` : null;
    const duration = summary ? summary.duration >= 3600 ? `${Math.floor(summary.duration / 3600)}h ${Math.round((summary.duration % 3600) / 60)}m` : `${Math.max(1, Math.round(summary.duration / 60))} min` : null;
    const clockTime = (value, extraSeconds) => {
        if (!value) return null;
        const [hours, minutes] = value.slice(0, 5).split(':').map(Number);
        const date = new Date(2000, 0, 1, hours, minutes, 0);
        date.setSeconds(date.getSeconds() + extraSeconds);
        return new Intl.DateTimeFormat(undefined, { hour: 'numeric', minute: '2-digit' }).format(date);
    };
    const legLabel = (leg) => leg.distance_meters >= 1000 ? `${(leg.distance_meters / 1000).toFixed(1)} km` : `${Math.round(leg.distance_meters)} m`;
    const legDuration = (leg) => leg.duration_seconds >= 3600 ? `${Math.floor(leg.duration_seconds / 3600)}h ${Math.round((leg.duration_seconds % 3600) / 60)}m` : `${Math.max(1, Math.round(leg.duration_seconds / 60))} min`;
    const navigationDistance = navigation ? navigation.remainingMeters >= 1000 ? `${(navigation.remainingMeters / 1000).toFixed(1)} km` : `${Math.max(0, Math.round(navigation.remainingMeters))} m` : null;
    const navigationDuration = navigation ? `${Math.max(1, Math.round(navigation.remainingSeconds / 60))} min` : null;
    return <div className="mt-6 overflow-hidden rounded-[1.65rem] border border-slate-200 bg-white"><div className="flex items-center justify-between gap-3 px-5 py-4"><div><h3 className="font-display text-lg font-bold">Itinerary Route Polyline</h3><p className="text-xs text-slate-500">{summary ? `${distance} · ${duration}` : routeUnavailable ? 'Stops shown · route temporarily unavailable' : stops.length > 1 ? 'Calculating route…' : 'Add another mapped stop to calculate a route'}</p></div><span className="rounded-full bg-[#eafaf1] px-3 py-1.5 text-xs font-bold capitalize text-[#10994a]">{day.route_mode === 'walking' ? 'Walking trek' : 'Driving route'}</span></div>{summary && <div className="border-y border-slate-200 bg-slate-50 px-5 py-3">{navigating ? <div className="flex items-center gap-3"><div className="min-w-0 flex-1"><p className="text-xs font-bold uppercase tracking-wide text-[#3158cf]">{navigation?.arrived ? 'Destination reached' : navigation?.offRoute ? 'Return to the highlighted route' : 'Navigating to'}</p><p className="truncate font-bold">{navigation?.arrived ? stops.at(-1)?.title : stops[navigation?.nextIndex ?? 1]?.title}</p><p className="mt-0.5 text-xs text-slate-500">{navigation ? `${navigationDistance} · ${navigationDuration} remaining` : 'Finding your live location…'}</p></div><button type="button" onClick={stopNavigation} className="rounded-xl border border-red-200 bg-white px-4 py-2 text-sm font-bold text-red-600">Stop</button></div> : <button type="button" onClick={startNavigation} className="w-full rounded-xl bg-[#3158cf] px-4 py-3 text-sm font-bold text-white shadow-sm hover:bg-[#244bc3]">Start Trip</button>}{navigationError && <p className="mt-2 text-center text-xs font-semibold text-red-600">{navigationError}</p>}</div>}<div ref={container} className="h-[360px] w-full bg-slate-100" aria-label={`Google map of ${stops.length} itinerary stops`}/><div className="border-t border-slate-200 bg-[#f8fafc] px-5 py-3 text-center text-[11px] text-slate-500">Keep SplitShare open while navigating. Live location pauses when the browser restricts background access.</div>{summary?.legs?.length > 0 && <div className="divide-y divide-slate-100 border-t border-slate-200">{summary.legs.map((leg, index) => { const from = stops[index], to = stops[index + 1], departure = from?.end_time || from?.start_time, eta = clockTime(departure, leg.duration_seconds); return <div key={`${from?.id}-${to?.id}`} className="flex items-center gap-3 px-5 py-4"><span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-[#3158cf] text-sm font-bold text-white">{index + 1}</span><div className="min-w-0 flex-1"><p className="truncate text-sm font-bold">{from?.title} → {to?.title}</p><p className="mt-1 text-xs text-slate-500">{legLabel(leg)} · {legDuration(leg)}</p></div><div className="shrink-0 text-right"><p className="text-[10px] font-bold uppercase tracking-wider text-slate-400">ETA</p><p className="mt-1 font-display font-bold text-[#244bc3]">{eta || 'Set time'}</p></div></div>; })}</div>}</div>;
}
