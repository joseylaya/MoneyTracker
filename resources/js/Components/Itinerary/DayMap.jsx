import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { loadGoogleMaps } from '@/lib/googleMaps';
import { Check, CheckCircle2, ChevronRight, ChevronUp, Compass, GripVertical, LocateFixed, Map as MapIcon, MapPin, Navigation, Pause, Play, Radio, Route, X } from 'lucide-react';
import { router, usePage } from '@inertiajs/react';
import { createPortal } from 'react-dom';

export default function DayMap({ trackerId, day, canManage = false, navigationHref = null, navigationOnly = false, backHref = null, initialLiveSharing = false }) {
    const currentUserId = usePage().props.auth?.user?.id;
    const container = useRef(null);
    const mapRef = useRef(null);
    const routeCoordinatesRef = useRef([]);
    const itineraryCoordinatesRef = useRef([]);
    const routeMetricsRef = useRef(null);
    const userMarkerRef = useRef(null);
    const memberMarkersRef = useRef(new Map());
    const memberAccuracyRef = useRef(new Map());
    const lastSharedAtRef = useRef(0);
    const liveSharingRef = useRef(initialLiveSharing);
    const navigationMarkerRef = useRef(null);
    const navigationPolylinesRef = useRef([]);
    const rerouteInFlightRef = useRef(false);
    const lastRerouteRef = useRef(0);
    const watchIdRef = useRef(null);
    const headingUpRef = useRef(true);
    const lastHeadingRef = useRef(0);
    const previousPositionRef = useRef(null);
    const animatedPositionRef = useRef(null);
    const cameraAnimationRef = useRef(null);
    const sheetDragStartRef = useRef(null);
    const suppressSheetClickRef = useRef(false);
    const completedIdsRef = useRef(new Set(day.items.filter((item) => item.completed_at).map((item) => item.id)));
    const draggedItemRef = useRef(null);
    const originalOrderRef = useRef(null);
    const orderedItemsRef = useRef(day.items);
    const dragPointerRef = useRef(null);
    const itineraryListRef = useRef(null);
    const cardPositionsRef = useRef(new Map());
    const [summary, setSummary] = useState(null);
    const [routeUnavailable, setRouteUnavailable] = useState(false);
    const [navigating, setNavigating] = useState(navigationOnly);
    const [paused, setPaused] = useState(false);
    const [headingUp, setHeadingUp] = useState(true);
    const [sheetExpanded, setSheetExpanded] = useState(false);
    const [navigation, setNavigation] = useState(null);
    const [navigationError, setNavigationError] = useState('');
    const [liveSharing, setLiveSharing] = useState(initialLiveSharing);
    const [liveLocations, setLiveLocations] = useState({});
    const [mapReady, setMapReady] = useState(false);
    const [completedIds, setCompletedIds] = useState(() => new Set(completedIdsRef.current));
    const [completingId, setCompletingId] = useState(null);
    const [orderedItems, setOrderedItems] = useState(day.items);
    const [committedOrderKey, setCommittedOrderKey] = useState(() => day.items.map((item) => item.id).join('|'));
    const [draggingId, setDraggingId] = useState(null);
    const [dragPreview, setDragPreview] = useState(null);
    const stops = orderedItems.filter((item) => item.latitude != null && item.longitude != null);
    const stopKey = `${committedOrderKey}:${day.items.map((item) => `${item.id}:${item.longitude},${item.latitude}`).join('|')}`;

    useLayoutEffect(() => {
        const cards = itineraryListRef.current?.querySelectorAll('[data-itinerary-stop]');
        if (!cards?.length) return;
        const nextPositions = new Map();
        cards.forEach((card) => {
            const top = card.getBoundingClientRect().top;
            nextPositions.set(card.dataset.itineraryStop, top);
            const previousTop = cardPositionsRef.current.get(card.dataset.itineraryStop);
            if (previousTop != null && previousTop !== top && card.dataset.itineraryStop !== draggingId) {
                card.animate([{ transform: `translateY(${previousTop - top}px)` }, { transform: 'translateY(0)' }], { duration: 180, easing: 'cubic-bezier(.2,.8,.2,1)' });
            }
        });
        cardPositionsRef.current = nextPositions;
    }, [orderedItems, draggingId]);

    useEffect(() => {
        if (!navigating) return undefined;
        const previousOverflow = document.body.style.overflow;
        const previousOverscroll = document.documentElement.style.overscrollBehavior;
        document.body.style.overflow = 'hidden';
        document.documentElement.style.overscrollBehavior = 'none';
        const frame = window.requestAnimationFrame(() => {
            if (mapRef.current && window.google?.maps) window.google.maps.event.trigger(mapRef.current, 'resize');
        });
        return () => {
            window.cancelAnimationFrame(frame);
            document.body.style.overflow = previousOverflow;
            document.documentElement.style.overscrollBehavior = previousOverscroll;
        };
    }, [navigating]);

    useEffect(() => {
        if (!container.current || !stops.length) return undefined;
        let cancelled = false;
        let map;
        let markers = [];
        let polylines = [];
        let mapClickListener;

        const initialize = async () => {
            setSummary(null); setRouteUnavailable(false);
            try {
                const maps = await loadGoogleMaps();
                if (cancelled) return;
                map = new maps.Map(container.current, {
                    center: { lat: Number(stops[0].latitude), lng: Number(stops[0].longitude) },
                    zoom: 12,
                    heading: 0,
                    tilt: 0,
                    renderingType: maps.RenderingType.VECTOR,
                    headingInteractionEnabled: false,
                    tiltInteractionEnabled: false,
                    mapTypeControl: false,
                    streetViewControl: false,
                    fullscreenControl: false,
                    zoomControl: false,
                    clickableIcons: false,
                    gestureHandling: 'greedy',
                    styles: [
                        { featureType: 'poi.business', stylers: [{ visibility: 'off' }] },
                        { featureType: 'poi.attraction', stylers: [{ visibility: 'off' }] },
                        { featureType: 'transit', stylers: [{ visibility: 'off' }] },
                        { featureType: 'road', elementType: 'geometry', stylers: [{ color: '#ffffff' }] },
                        { featureType: 'road', elementType: 'labels.text.fill', stylers: [{ color: '#52645a' }] },
                        { featureType: 'road.highway', elementType: 'geometry', stylers: [{ color: '#dff7e8' }] },
                        { featureType: 'landscape', elementType: 'geometry', stylers: [{ color: '#eef7f1' }] },
                        { featureType: 'poi.park', elementType: 'geometry', stylers: [{ color: '#d8f2e1' }] },
                        { featureType: 'water', elementType: 'geometry', stylers: [{ color: '#cceee8' }] },
                    ],
                });
                mapRef.current = map;
                setMapReady(true);
                mapClickListener = map.addListener('click', () => setSheetExpanded(false));
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
                        icon: { path: maps.SymbolPath.CIRCLE, fillColor: '#20b960', fillOpacity: 1, strokeColor: '#ffffff', strokeWeight: 3, scale: 16 },
                        zIndex: 20,
                    });
                    marker.addListener('click', () => { infoWindow.setContent(`<strong>${stop.title}</strong>`); infoWindow.open({ map, anchor: marker }); });
                    return marker;
                });
                if (stops.length > 1) map.fitBounds(bounds, 55);

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
                    strokeColor: '#20b960',
                    strokeOpacity: walking ? 0 : 1,
                    strokeWeight: 7,
                    zIndex: 6,
                    icons: walking ? [{ icon: { path: 'M 0,-1 0,1', strokeColor: '#20b960', strokeOpacity: 1, strokeWeight: 6, scale: 2 }, offset: '0', repeat: '16px' }] : undefined,
                });
                polylines = [outline, routeLine];
                if (navigating && watchIdRef.current == null && navigator.geolocation) {
                    watchIdRef.current = navigator.geolocation.watchPosition(navigationUpdate, () => setNavigationError('Unable to resume live location after reordering.'), { enableHighAccuracy: true, maximumAge: 2000, timeout: 15000 });
                }
            } catch (error) { console.error('Itinerary route polyline failed', error); setRouteUnavailable(true); }
        };
        initialize();
        return () => {
            cancelled = true;
            markers.forEach((marker) => marker.setMap(null));
            polylines.forEach((polyline) => polyline.setMap(null));
            userMarkerRef.current?.setMap(null);
            userMarkerRef.current = null;
            navigationMarkerRef.current?.setMap(null);
            navigationMarkerRef.current = null;
            navigationPolylinesRef.current.forEach((polyline) => polyline.setMap(null));
            navigationPolylinesRef.current = [];
            routeCoordinatesRef.current = [];
            itineraryCoordinatesRef.current = [];
            routeMetricsRef.current = null;
            if (watchIdRef.current != null) navigator.geolocation?.clearWatch(watchIdRef.current);
            watchIdRef.current = null;
            if (cameraAnimationRef.current) window.cancelAnimationFrame(cameraAnimationRef.current);
            cameraAnimationRef.current = null;
            previousPositionRef.current = null;
            animatedPositionRef.current = null;
            mapRef.current = null;
            setMapReady(false);
            mapClickListener?.remove();
        };
    }, [trackerId, day.id, day.route_mode, stopKey]);

    useEffect(() => {
        fetch(route('trackers.itinerary.live-locations.index', [trackerId, day.id]), { headers: { Accept: 'application/json' } })
            .then((response) => response.ok ? response.json() : Promise.reject())
            .then(({ locations }) => setLiveLocations(Object.fromEntries(locations.filter((item) => item.user.id !== currentUserId).map((item) => [item.user.id, item]))))
            .catch(() => {});
        if (!window.Echo) return undefined;
        const channel = window.Echo.private(`tracker.${trackerId}`);
        channel.listen('.tracker.live-location.updated', ({ day_id, user, location }) => {
            if (day_id !== day.id || user.id === currentUserId) return;
            setLiveLocations((current) => {
                const next = { ...current };
                if (location) next[user.id] = location; else delete next[user.id];
                return next;
            });
        });
        const expiry = window.setInterval(() => {
            const cutoff = Date.now() - 30000;
            setLiveLocations((current) => Object.fromEntries(Object.entries(current).filter(([, item]) => new Date(item.updated_at).getTime() >= cutoff)));
        }, 5000);
        return () => { window.clearInterval(expiry); window.Echo.leave(`private-tracker.${trackerId}`); };
    }, [trackerId, day.id, currentUserId]);

    useEffect(() => {
        if (!mapReady || !window.google?.maps) return;
        const maps = window.google.maps;
        const activeIds = new Set(Object.keys(liveLocations).map(Number));
        memberMarkersRef.current.forEach((marker, userId) => { if (!activeIds.has(userId)) { marker.setMap(null); memberMarkersRef.current.delete(userId); } });
        memberAccuracyRef.current.forEach((circle, userId) => { if (!activeIds.has(userId)) { circle.setMap(null); memberAccuracyRef.current.delete(userId); } });
        Object.values(liveLocations).forEach((item) => {
            const position = { lat: Number(item.latitude), lng: Number(item.longitude) };
            let marker = memberMarkersRef.current.get(item.user.id);
            if (!marker) {
                marker = new maps.Marker({ map: mapRef.current, position, title: `${item.user.name} · live location`, label: { text: item.user.name.slice(0, 1).toUpperCase(), color: '#ffffff', fontWeight: '700' }, icon: { path: maps.SymbolPath.CIRCLE, fillColor: '#3158cf', fillOpacity: 1, strokeColor: '#ffffff', strokeWeight: 3, scale: 12 }, zIndex: 45 });
                memberMarkersRef.current.set(item.user.id, marker);
            } else marker.setPosition(position);
            let circle = memberAccuracyRef.current.get(item.user.id);
            if (!circle) {
                circle = new maps.Circle({ map: mapRef.current, center: position, radius: item.accuracy, fillColor: '#3158cf', fillOpacity: .12, strokeColor: '#3158cf', strokeOpacity: .35, strokeWeight: 1, zIndex: 10 });
                memberAccuracyRef.current.set(item.user.id, circle);
            } else { circle.setCenter(position); circle.setRadius(item.accuracy); }
        });
    }, [liveLocations, mapReady]);

    useEffect(() => () => {
        memberMarkersRef.current.forEach((marker) => marker.setMap(null));
        memberAccuracyRef.current.forEach((circle) => circle.setMap(null));
    }, []);

    const stopLiveSharing = (updateState = true) => {
        liveSharingRef.current = false;
        if (updateState) setLiveSharing(false);
        const token = document.querySelector('meta[name="csrf-token"]')?.content;
        fetch(route('trackers.itinerary.live-location.destroy', [trackerId, day.id]), { method: 'DELETE', keepalive: true, headers: { Accept: 'application/json', ...(token ? { 'X-CSRF-TOKEN': token } : {}) } }).catch(() => {});
    };

    useEffect(() => () => {
        if (liveSharingRef.current) stopLiveSharing(false);
    }, [trackerId, day.id]);

    const sharePosition = (coords) => {
        if (!liveSharingRef.current || Date.now() - lastSharedAtRef.current < 3000) return;
        lastSharedAtRef.current = Date.now();
        const token = document.querySelector('meta[name="csrf-token"]')?.content;
        fetch(route('trackers.itinerary.live-location.update', [trackerId, day.id]), {
            method: 'PUT', keepalive: true,
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...(token ? { 'X-CSRF-TOKEN': token } : {}) },
            body: JSON.stringify({ latitude: coords.latitude, longitude: coords.longitude, accuracy: coords.accuracy, heading: Number.isFinite(coords.heading) ? coords.heading : null, speed: Number.isFinite(coords.speed) ? coords.speed : null }),
        }).catch(() => setNavigationError('Navigation continues, but live location could not be shared.'));
    };

    const distanceBetween = (from, to) => {
        const radians = (degrees) => degrees * Math.PI / 180;
        const deltaLatitude = radians(to.lat - from.lat);
        const deltaLongitude = radians(to.lng - from.lng);
        const a = Math.sin(deltaLatitude / 2) ** 2 + Math.cos(radians(from.lat)) * Math.cos(radians(to.lat)) * Math.sin(deltaLongitude / 2) ** 2;
        return 6371000 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    };

    const bearingBetween = (from, to) => {
        const radians = (degrees) => degrees * Math.PI / 180;
        const degrees = (value) => value * 180 / Math.PI;
        const fromLatitude = radians(from.lat);
        const toLatitude = radians(to.lat);
        const longitudeDelta = radians(to.lng - from.lng);
        const y = Math.sin(longitudeDelta) * Math.cos(toLatitude);
        const x = Math.cos(fromLatitude) * Math.sin(toLatitude) - Math.sin(fromLatitude) * Math.cos(toLatitude) * Math.cos(longitudeDelta);
        return (degrees(Math.atan2(y, x)) + 360) % 360;
    };

    const animateNavigationCamera = (to, heading) => {
        const map = mapRef.current;
        const marker = navigationMarkerRef.current;
        if (!map || !marker) return;
        if (cameraAnimationRef.current) window.cancelAnimationFrame(cameraAnimationRef.current);
        const from = animatedPositionRef.current || to;
        const startHeading = Number(map.getHeading()) || 0;
        const targetHeading = headingUpRef.current ? heading : 0;
        const headingDelta = ((targetHeading - startHeading + 540) % 360) - 180;
        const startedAt = performance.now();
        const duration = 900;
        const frame = (now) => {
            const progress = Math.min(1, (now - startedAt) / duration);
            const eased = 1 - ((1 - progress) ** 3);
            const position = { lat: from.lat + ((to.lat - from.lat) * eased), lng: from.lng + ((to.lng - from.lng) * eased) };
            animatedPositionRef.current = position;
            marker.setPosition(position);
            marker.setIcon({ path: window.google.maps.SymbolPath.FORWARD_CLOSED_ARROW, fillColor: '#13a856', fillOpacity: 1, rotation: headingUpRef.current ? 0 : heading, strokeColor: '#ffffff', strokeWeight: 3, scale: 7 });
            map.moveCamera({ center: position, zoom: Math.max(17, map.getZoom() || 17), heading: startHeading + (headingDelta * eased), tilt: 0 });
            if (progress < 1) cameraAnimationRef.current = window.requestAnimationFrame(frame);
            else cameraAnimationRef.current = null;
        };
        cameraAnimationRef.current = window.requestAnimationFrame(frame);
    };

    const loadLiveRoute = async (position, skipCompleted = true) => {
        if (rerouteInFlightRef.current || !mapRef.current || !window.google?.maps) return;
        rerouteInFlightRef.current = true;
        lastRerouteRef.current = Date.now();
        try {
            const url = new URL(route('trackers.itinerary.route', [trackerId, day.id]), window.location.origin);
            url.searchParams.set('origin_latitude', position.lat);
            url.searchParams.set('origin_longitude', position.lng);
            if (skipCompleted) url.searchParams.set('skip_completed', '1');
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
                new maps.Polyline({ map: mapRef.current, path, strokeColor: '#20b960', strokeOpacity: walking ? 0 : 1, strokeWeight: 8, zIndex: 31, icons: walking ? [{ icon: { path: 'M 0,-1 0,1', strokeColor: '#20b960', strokeOpacity: 1, strokeWeight: 7, scale: 2 }, offset: '0', repeat: '17px' }] : undefined }),
            ];
        } catch (error) {
            console.error('Live itinerary route failed', error);
            setNavigationError('Could not calculate a route from your current location.');
        } finally {
            rerouteInFlightRef.current = false;
        }
    };

    const navigationUpdate = ({ coords }) => {
        sharePosition(coords);
        const map = mapRef.current;
        const maps = window.google?.maps;
        if (!map || !maps) return;
        const position = { lat: coords.latitude, lng: coords.longitude };
        const previousPosition = previousPositionRef.current;
        const movementDistance = previousPosition ? distanceBetween(previousPosition, position) : 0;
        const reportedHeading = Number.isFinite(coords.heading) ? coords.heading : null;
        if (reportedHeading != null && (coords.speed == null || coords.speed > 0.5)) lastHeadingRef.current = reportedHeading;
        else if (previousPosition && movementDistance > 2) lastHeadingRef.current = bearingBetween(previousPosition, position);
        else if (lastHeadingRef.current === 0 && routeCoordinatesRef.current.length > 1) {
            let nearestIndex = 0;
            let nearestDistance = Infinity;
            routeCoordinatesRef.current.forEach((point, index) => {
                const distance = distanceBetween(position, point);
                if (distance < nearestDistance) { nearestDistance = distance; nearestIndex = index; }
            });
            const nextPoint = routeCoordinatesRef.current[Math.min(nearestIndex + 1, routeCoordinatesRef.current.length - 1)];
            if (nextPoint && distanceBetween(position, nextPoint) > 1) lastHeadingRef.current = bearingBetween(position, nextPoint);
        }
        previousPositionRef.current = position;
        if (!navigationMarkerRef.current) {
            navigationMarkerRef.current = new maps.Marker({
                map,
                position,
                title: 'Your live location',
                zIndex: 50,
                icon: { path: maps.SymbolPath.FORWARD_CLOSED_ARROW, fillColor: '#13a856', fillOpacity: 1, rotation: headingUpRef.current ? 0 : lastHeadingRef.current, strokeColor: '#ffffff', strokeWeight: 3, scale: 7 },
            });
            animatedPositionRef.current = position;
        }
        animateNavigationCamera(position, lastHeadingRef.current);

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
        let nextIndex = stops.findIndex((stop) => !completedIdsRef.current.has(stop.id));
        if (nextIndex < 0) nextIndex = stops.length - 1;
        for (let index = 0; index < stops.length; index += 1) {
            if (completedIdsRef.current.has(stops[index].id)) continue;
            const stopPosition = { lat: Number(stops[index].latitude), lng: Number(stops[index].longitude) };
            if (distanceBetween(position, stopPosition) > 75) { nextIndex = index; break; }
        }
        const arrived = remainingMeters < 75;
        if (nearestDistance > 100 && Date.now() - lastRerouteRef.current > 30000) loadLiveRoute(position);
        setNavigation({ remainingMeters, remainingSeconds, nextIndex, accuracy: coords.accuracy, speed: coords.speed, heading: coords.heading, arrived, offRoute: nearestDistance > 100 });
    };

    const startNavigation = () => {
        if (navigationHref && !navigationOnly) {
            const share = window.confirm('Share your live location with active members of this tracker while navigating?\n\nChoose Cancel to navigate privately.');
            const destination = new URL(navigationHref, window.location.origin);
            if (share) destination.searchParams.set('share_location', '1');
            router.visit(destination.toString());
            return;
        }
        setNavigationError('');
        if (!navigator.geolocation) { setNavigationError('Live location is not supported by this browser.'); return; }
        if (!routeCoordinatesRef.current.length) { setNavigationError('Wait for the route to finish loading.'); return; }
        setNavigating(true);
        setPaused(false);
        setSheetExpanded(false);
        watchIdRef.current = navigator.geolocation.watchPosition(navigationUpdate, (error) => {
            setNavigating(false);
            setNavigationError(error.code === 1 ? 'Location permission is required to start the trip.' : 'Unable to get your live location.');
        }, { enableHighAccuracy: true, maximumAge: 2000, timeout: 15000 });
    };

    const stopNavigation = () => {
        if (liveSharingRef.current) stopLiveSharing();
        if (watchIdRef.current != null) navigator.geolocation.clearWatch(watchIdRef.current);
        watchIdRef.current = null;
        navigationMarkerRef.current?.setMap(null);
        navigationMarkerRef.current = null;
        if (cameraAnimationRef.current) window.cancelAnimationFrame(cameraAnimationRef.current);
        cameraAnimationRef.current = null;
        previousPositionRef.current = null;
        animatedPositionRef.current = null;
        navigationPolylinesRef.current.forEach((polyline) => polyline.setMap(null));
        navigationPolylinesRef.current = [];
        routeCoordinatesRef.current = itineraryCoordinatesRef.current;
        routeMetricsRef.current = summary ? { distance: summary.distance, duration: summary.duration } : null;
        setNavigating(false);
        setPaused(false);
        setNavigation(null);
        if (navigationOnly && backHref) router.visit(backHref, { preserveScroll: true });
    };

    const togglePause = () => {
        if (paused) {
            watchIdRef.current = navigator.geolocation.watchPosition(navigationUpdate, () => {
                setNavigationError('Unable to resume your live location.');
            }, { enableHighAccuracy: true, maximumAge: 2000, timeout: 15000 });
            setPaused(false);
            return;
        }
        if (watchIdRef.current != null) navigator.geolocation.clearWatch(watchIdRef.current);
        watchIdRef.current = null;
        setPaused(true);
    };

    const toggleLiveSharing = () => {
        if (liveSharingRef.current) { stopLiveSharing(); return; }
        liveSharingRef.current = true;
        setLiveSharing(true);
        lastSharedAtRef.current = 0;
    };

    const recenter = () => {
        if (!mapRef.current || !window.google?.maps) return;
        const activeMarker = navigationMarkerRef.current || userMarkerRef.current;
        if (activeMarker) {
            mapRef.current.panTo(activeMarker.getPosition());
            mapRef.current.setZoom(navigating ? 17 : 15);
            return;
        }
        setNavigationError('');
        if (!navigator.geolocation) { setNavigationError('Live location is not supported by this browser.'); return; }
        navigator.geolocation.getCurrentPosition(({ coords }) => {
            const position = { lat: coords.latitude, lng: coords.longitude };
            userMarkerRef.current = new window.google.maps.Marker({
                map: mapRef.current,
                position,
                title: 'Your location',
                zIndex: 50,
                icon: { path: window.google.maps.SymbolPath.CIRCLE, fillColor: '#13a856', fillOpacity: 1, strokeColor: '#ffffff', strokeWeight: 4, scale: 10 },
            });
            mapRef.current.panTo(position);
            mapRef.current.setZoom(15);
        }, () => setNavigationError('Allow location access to recenter the map.'), { enableHighAccuracy: true, maximumAge: 5000, timeout: 15000 });
    };

    const showOverview = () => {
        if (!mapRef.current || !window.google?.maps || !routeCoordinatesRef.current.length) return;
        const bounds = new window.google.maps.LatLngBounds();
        routeCoordinatesRef.current.forEach((point) => bounds.extend(point));
        mapRef.current.fitBounds(bounds, navigating ? 100 : 55);
    };

    const toggleOrientation = () => {
        const nextHeadingUp = !headingUpRef.current;
        headingUpRef.current = nextHeadingUp;
        setHeadingUp(nextHeadingUp);
        const heading = nextHeadingUp ? lastHeadingRef.current : 0;
        mapRef.current?.moveCamera({ heading, tilt: 0 });
        if (navigationMarkerRef.current && window.google?.maps) {
            navigationMarkerRef.current.setIcon({ path: window.google.maps.SymbolPath.FORWARD_CLOSED_ARROW, fillColor: '#13a856', fillOpacity: 1, rotation: nextHeadingUp ? 0 : lastHeadingRef.current, strokeColor: '#ffffff', strokeWeight: 3, scale: 7 });
        }
    };

    const startSheetDrag = (event) => {
        sheetDragStartRef.current = event.clientY;
        event.currentTarget.setPointerCapture?.(event.pointerId);
    };

    const finishSheetDrag = (event) => {
        if (sheetDragStartRef.current == null) return;
        const distance = event.clientY - sheetDragStartRef.current;
        sheetDragStartRef.current = null;
        if (Math.abs(distance) < 28) return;
        suppressSheetClickRef.current = true;
        setSheetExpanded(distance < 0);
    };

    const toggleSheet = () => {
        if (suppressSheetClickRef.current) {
            suppressSheetClickRef.current = false;
            return;
        }
        setSheetExpanded((current) => !current);
    };

    const setStopCompleted = async (item, completed) => {
        if (!canManage || completingId) return;
        setCompletingId(item.id);
        setNavigationError('');
        try {
            const token = document.querySelector('meta[name="csrf-token"]')?.content;
            const response = await fetch(route('trackers.itinerary.items.completion', [trackerId, item.id]), {
                method: 'PATCH',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...(token ? { 'X-CSRF-TOKEN': token } : {}) },
                body: JSON.stringify({ completed }),
            });
            if (!response.ok) throw new Error('Unable to save stop progress');
            const next = new Set(completedIdsRef.current);
            if (completed) next.add(item.id); else next.delete(item.id);
            completedIdsRef.current = next;
            setCompletedIds(new Set(next));
            router.reload({ only: ['days'], preserveState: true, preserveScroll: true });
            const remainingMappedStops = stops.filter((stop) => !next.has(stop.id));
            if (completed && animatedPositionRef.current && remainingMappedStops.length) loadLiveRoute(animatedPositionRef.current, true);
        } catch (error) {
            setNavigationError('Could not update this stop. Please try again.');
        } finally {
            setCompletingId(null);
        }
    };

    const moveDraggedItem = (pointerY) => {
        const draggedId = draggedItemRef.current;
        if (!draggedId) return;
        const cards = [...(itineraryListRef.current?.querySelectorAll('[data-itinerary-stop]') || [])].filter((card) => card.dataset.itineraryStop !== draggedId);
        let insertionIndex = cards.findIndex((card) => pointerY < card.getBoundingClientRect().top + (card.getBoundingClientRect().height / 2));
        if (insertionIndex < 0) insertionIndex = cards.length;
        setOrderedItems((items) => {
            const from = items.findIndex((item) => item.id === draggedId);
            if (from < 0) return items;
            const next = [...items];
            const [moved] = next.splice(from, 1);
            next.splice(insertionIndex, 0, moved);
            if (next.every((item, index) => item.id === items[index].id)) return items;
            orderedItemsRef.current = next;
            return next;
        });
    };

    const startItemDrag = (item, event) => {
        if (!canManage) return;
        event.stopPropagation();
        const card = event.currentTarget.closest('[data-itinerary-stop]');
        const rect = card.getBoundingClientRect();
        dragPointerRef.current = { id: item.id, startY: event.clientY, offsetY: event.clientY - rect.top, left: rect.left, width: rect.width, active: false };
        originalOrderRef.current = orderedItems;
        orderedItemsRef.current = orderedItems;
        event.currentTarget.setPointerCapture?.(event.pointerId);
    };

    const updateItemDrag = (event) => {
        const drag = dragPointerRef.current;
        if (!drag) return;
        event.preventDefault();
        event.stopPropagation();
        if (!drag.active && Math.abs(event.clientY - drag.startY) < 6) return;
        if (!drag.active) {
            drag.active = true;
            draggedItemRef.current = drag.id;
            setDraggingId(drag.id);
        }
        const item = orderedItemsRef.current.find((entry) => entry.id === drag.id);
        setDragPreview({ item, top: event.clientY - drag.offsetY, left: drag.left, width: drag.width });
        moveDraggedItem(event.clientY);
    };

    const finishItemDrag = async (event) => {
        const drag = dragPointerRef.current;
        if (!drag) return;
        event.stopPropagation();
        dragPointerRef.current = null;
        setDragPreview(null);
        if (!drag.active) {
            setStopCompleted(orderedItemsRef.current.find((item) => item.id === drag.id), !completedIdsRef.current.has(drag.id));
            return;
        }
        draggedItemRef.current = null;
        setDraggingId(null);
        const itemIds = orderedItemsRef.current.map((item) => item.id);
        try {
            const token = document.querySelector('meta[name="csrf-token"]')?.content;
            const response = await fetch(route('trackers.itinerary.reorder', trackerId), {
                method: 'PATCH',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...(token ? { 'X-CSRF-TOKEN': token } : {}) },
                body: JSON.stringify({ day_id: day.id, item_ids: itemIds }),
            });
            if (!response.ok) throw new Error('Unable to reorder itinerary');
            setCommittedOrderKey(itemIds.join('|'));
        } catch (error) {
            const original = originalOrderRef.current || day.items;
            orderedItemsRef.current = original;
            setOrderedItems(original);
            setNavigationError('Could not save the new itinerary order.');
        } finally {
            originalOrderRef.current = null;
        }
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
    const firstIncompleteIndex = stops.findIndex((stop) => !completedIds.has(stop.id));
    const nextStop = stops[navigation?.nextIndex ?? (firstIncompleteIndex < 0 ? stops.length - 1 : firstIncompleteIndex)] || stops.at(-1);
    const arrivalTime = navigation ? new Intl.DateTimeFormat(undefined, { hour: 'numeric', minute: '2-digit' }).format(new Date(Date.now() + navigation.remainingSeconds * 1000)) : '—';
    const speed = navigation?.speed >= 0 ? `${Math.round(navigation.speed * 3.6)}` : '—';
    const itineraryIcons = { activity: '🎯', food: '🍜', accommodation: '🏨', transport: '🚆', shopping: '🛍️', place: '📍', other: '📝' };

    return <div className={navigating ? 'fixed inset-x-0 top-0 z-[100] h-[100dvh] overflow-hidden overscroll-none bg-slate-100 text-slate-950' : 'mt-6 overflow-hidden rounded-[1.65rem] border border-slate-200 bg-white shadow-[0_14px_38px_rgba(15,23,42,.08)]'}>
        {navigating ? <>
            <button type="button" onClick={stopNavigation} className="absolute left-3 top-[calc(env(safe-area-inset-top)+.75rem)] z-30 flex size-11 items-center justify-center rounded-2xl border border-white/70 bg-white text-slate-800 shadow-[0_8px_28px_rgba(15,23,42,.25)] transition active:scale-95 sm:left-6 sm:size-12" aria-label="Close navigation"><X size={22}/></button>
            <div className="absolute inset-x-0 top-0 z-20 pl-[4.25rem] pr-3 pt-[calc(env(safe-area-inset-top)+.75rem)] sm:px-24">
                <div className="mx-auto flex max-w-3xl items-center gap-3 rounded-[1.4rem] border border-white/10 bg-[#173f2a]/95 p-3 text-white shadow-[0_14px_34px_rgba(15,23,42,.3)] backdrop-blur sm:rounded-[1.75rem] sm:p-4">
                    <div className="flex size-12 shrink-0 items-center justify-center rounded-xl bg-[#20b960] text-white sm:size-16 sm:rounded-2xl"><ChevronRight className="size-8 sm:size-10" strokeWidth={3}/></div>
                    <div className="min-w-0 flex-1"><p className="font-display text-xl font-bold leading-none sm:text-3xl">{navigation?.arrived ? 'Arrived' : navigationDistance || 'Locating…'}</p><p className="mt-1.5 truncate text-sm font-semibold text-slate-100 sm:mt-2 sm:text-base">{navigation?.offRoute ? 'Returning to the route' : navigation?.arrived ? nextStop?.title : `Continue to ${nextStop?.title || 'next stop'}`}</p></div>
                    <div className="hidden shrink-0 text-right sm:block"><p className="text-xs font-semibold uppercase tracking-wider text-slate-300">Speed</p><p className="mt-1 text-xl font-bold">{speed} <span className="text-xs">km/h</span></p></div>
                </div>
            </div>
        </> : <>
            <div className="flex items-center justify-between gap-3 px-5 py-4"><div><p className="text-[11px] font-bold uppercase tracking-[.14em] text-[#16a653]">Mapped itinerary</p><h3 className="mt-1 font-display text-xl font-bold">Day route</h3><p className="mt-1 text-sm text-slate-500">{summary ? `${distance} · ${duration} · ${stops.length} stops` : routeUnavailable ? 'Stops shown · route temporarily unavailable' : stops.length > 1 ? 'Calculating the best route…' : 'Add another mapped stop to calculate a route'}</p></div><span className="rounded-full bg-[#eafaf1] px-3 py-1.5 text-xs font-bold capitalize text-[#10994a]">{day.route_mode === 'walking' ? 'Walking' : 'Driving'}</span></div>
            {summary && <div className="border-y border-slate-200 bg-[#f7fbf8] px-4 py-3"><button type="button" onClick={startNavigation} className="flex min-h-12 w-full items-center justify-center gap-2 rounded-2xl bg-[#179f50] px-4 py-3 text-base font-bold text-white shadow-[0_8px_20px_rgba(23,159,80,.22)] transition active:scale-[.99] hover:bg-[#138a45]"><Navigation size={19} fill="currentColor"/>Start navigation</button>{navigationError && <p className="mt-2 text-center text-xs font-semibold text-red-600">{navigationError}</p>}</div>}
        </>}

        <div className={navigating ? 'contents' : 'relative'}>
            <div ref={container} className={navigating ? 'absolute inset-0 h-full w-full bg-slate-100' : 'h-[360px] w-full bg-slate-100'} aria-label={`Google map of ${stops.length} itinerary stops`}/>
            {!navigating && <div className="absolute right-3 top-3 z-20 flex flex-col items-end gap-2">
                <button type="button" onClick={recenter} className="flex min-h-11 items-center justify-center gap-2 rounded-xl border border-white/80 bg-white/95 px-3 font-semibold text-[#137f43] shadow-[0_5px_18px_rgba(15,23,42,.22)] backdrop-blur transition active:scale-95" aria-label="Recenter map on my location" title="My location"><LocateFixed size={20}/><span className="text-xs">My location</span></button>
                {routeCoordinatesRef.current.length > 0 && <button type="button" onClick={showOverview} className="flex min-h-11 items-center justify-center gap-2 rounded-xl border border-white/80 bg-white/95 px-3 font-semibold text-slate-700 shadow-[0_5px_18px_rgba(15,23,42,.22)] backdrop-blur transition active:scale-95" aria-label="Fit the full route on the map" title="Route overview"><Route size={19}/><span className="text-xs">Full route</span></button>}
            </div>}
        </div>

        {navigating ? <>
            <div className="absolute right-3 top-[calc(env(safe-area-inset-top)+7.25rem)] z-20 flex flex-col gap-2 sm:right-6 sm:top-[10rem]">
                <button type="button" onClick={recenter} className="flex size-12 items-center justify-center rounded-full bg-white text-[#13a856] shadow-lg" aria-label="Recenter map"><LocateFixed size={22}/></button>
                <button type="button" onClick={showOverview} className="flex size-12 items-center justify-center rounded-full bg-white text-slate-700 shadow-lg" aria-label="Show route overview"><Route size={21}/></button>
                <button type="button" onClick={toggleOrientation} className={`flex size-12 items-center justify-center rounded-full shadow-lg transition active:scale-95 ${headingUp ? 'bg-[#173f2a] text-white' : 'bg-white text-slate-700'}`} aria-label={headingUp ? 'Map follows your direction. Switch to north up.' : 'Map points north. Switch to heading up.'} title={headingUp ? 'Switch to north up' : 'Switch to heading up'}><Compass size={22} style={{ transform: headingUp ? `rotate(${-lastHeadingRef.current}deg)` : 'rotate(0deg)' }} className="transition-transform"/></button>
            </div>
            <div className="absolute inset-x-0 bottom-0 z-20 sm:px-6">
                <div className="mx-auto max-h-[calc(100dvh-env(safe-area-inset-top)-8.25rem)] max-w-3xl overflow-y-auto overscroll-contain rounded-t-[2rem] border-t border-slate-200 bg-white px-4 pb-[calc(1rem+env(safe-area-inset-bottom))] pt-4 shadow-[0_-10px_40px_rgba(15,23,42,.22)] sm:px-6 sm:pb-[calc(1.5rem+env(safe-area-inset-bottom))] sm:pt-6">
                    <button type="button" onClick={toggleSheet} onPointerDown={startSheetDrag} onPointerUp={finishSheetDrag} className="-mx-2 -mt-2 flex w-[calc(100%+1rem)] touch-none items-center justify-between gap-3 rounded-2xl px-2 py-2 text-left" aria-expanded={sheetExpanded} aria-label={sheetExpanded ? 'Collapse route details' : 'Expand route details'}>
                        <div><p className="font-display text-3xl font-bold leading-none sm:text-4xl">{arrivalTime}</p><p className="mt-1.5 text-sm font-semibold text-slate-500">Estimated arrival</p></div>
                        <div className="flex items-center gap-2"><span className="rounded-full bg-indigo-100 px-3 py-1.5 text-sm font-bold text-indigo-700">{navigationDuration || duration}</span><span className="text-lg font-semibold text-slate-700">{navigationDistance || distance}</span><ChevronUp size={22} className={`ml-1 text-slate-400 transition-transform duration-300 ${sheetExpanded ? 'rotate-180' : ''}`}/></div>
                    </button>
                    <div className="sticky top-0 z-10 mt-3 grid grid-cols-4 gap-2 rounded-2xl bg-white pb-1 sm:gap-3">
                        <button type="button" onClick={showOverview} className="flex min-h-12 items-center justify-center gap-1.5 rounded-xl bg-[#eaf1ff] px-1 text-xs font-bold text-slate-800 sm:min-h-14 sm:gap-2 sm:rounded-2xl sm:px-2 sm:text-sm"><MapIcon size={18}/>Overview</button>
                        <button type="button" onClick={togglePause} className="flex min-h-12 items-center justify-center gap-1.5 rounded-xl bg-[#eaf1ff] px-1 text-xs font-bold text-slate-800 sm:min-h-14 sm:gap-2 sm:rounded-2xl sm:px-2 sm:text-sm">{paused ? <Play size={18}/> : <Pause size={18}/>} {paused ? 'Resume' : 'Pause'}</button>
                        <button type="button" onClick={toggleLiveSharing} className={`flex min-h-12 items-center justify-center gap-1 rounded-xl px-1 text-[11px] font-bold sm:min-h-14 sm:rounded-2xl sm:text-sm ${liveSharing ? 'bg-[#dcfce7] text-[#138a48]' : 'bg-[#eaf1ff] text-slate-800'}`}><Radio size={17}/>{liveSharing ? 'Sharing' : 'Share'}</button>
                        <button type="button" onClick={stopNavigation} className="flex min-h-12 items-center justify-center gap-1.5 rounded-xl bg-red-100 px-1 text-xs font-bold text-red-700 sm:min-h-14 sm:gap-2 sm:rounded-2xl sm:px-2 sm:text-sm"><X size={18}/>End</button>
                    </div>
                    <div className={`grid transition-[grid-template-rows] duration-300 ease-out ${sheetExpanded ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'}`}><div className="overflow-hidden">
                        <div className="mt-4 h-2 overflow-hidden rounded-full bg-slate-100"><div className="h-full rounded-full bg-[#20b960] transition-all" style={{ width: `${summary?.distance && navigation ? Math.max(5, Math.min(100, 100 - (navigation.remainingMeters / summary.distance * 100))) : 5}%` }}/></div>
                        <div className="mt-3 flex items-center justify-between gap-3 text-sm"><p className="min-w-0 truncate font-semibold text-slate-600"><span className="mr-2 inline-block size-2.5 rounded-full bg-[#20b960]"/>{paused ? 'Navigation paused' : navigation?.offRoute ? 'Rerouting…' : 'Live route active'}</p><p className="shrink-0 font-semibold text-[#138a48]">Next: {nextStop?.title}</p></div>
                        {navigationError && <p className="mt-3 rounded-xl bg-red-50 px-3 py-2 text-center text-xs font-semibold text-red-600">{navigationError}</p>}
                        <div className="mt-4 border-t border-slate-100 pt-4">
                            <div className="mb-3 flex items-center justify-between"><p className="text-sm font-bold text-slate-800">Today’s itinerary</p><p className="text-xs font-semibold text-slate-500">{completedIds.size}/{orderedItems.length} completed</p></div>
                            <div ref={itineraryListRef} className="space-y-2">{orderedItems.map((item, index) => {
                                const completed = completedIds.has(item.id);
                                const current = item.id === nextStop?.id && !completed;
                                return <div key={item.id} data-itinerary-stop={item.id} role="button" tabIndex={canManage ? 0 : -1} onPointerDown={(event) => startItemDrag(item, event)} onPointerMove={updateItemDrag} onPointerUp={finishItemDrag} onPointerCancel={finishItemDrag} onKeyDown={(event) => { if (event.key === 'Enter' || event.key === ' ') setStopCompleted(item, !completed); }} className={`flex min-h-[4.75rem] w-full touch-none items-center gap-2 rounded-2xl border p-3 text-left ${draggingId === item.id ? 'border-2 border-dashed border-[#20b960] bg-[#edfbf3]' : completed ? 'border-slate-200 bg-slate-100 text-slate-400' : current ? 'border-[#8ce2ae] bg-[#edfbf3] text-slate-900 shadow-sm' : 'border-slate-200 bg-white text-slate-700'} ${canManage ? 'cursor-grab active:cursor-grabbing' : ''}`}>
                                    {draggingId === item.id ? <span className="flex w-full items-center justify-center gap-2 text-sm font-bold text-[#138a48]"><GripVertical size={18}/>Drop stop here</span> : <>
                                    {canManage && <span className="flex size-8 shrink-0 items-center justify-center rounded-xl text-slate-400" aria-hidden="true"><GripVertical size={19}/></span>}
                                    <span className={`flex size-10 shrink-0 items-center justify-center rounded-full text-sm font-bold ${completed ? 'bg-[#20b960] text-white' : current ? 'bg-[#20b960] text-white' : 'bg-slate-100 text-slate-600'}`}>{completed ? <Check size={19} strokeWidth={3}/> : index + 1}</span>
                                    <span className="min-w-0 flex-1"><span className={`block truncate text-sm font-bold ${completed ? 'line-through' : ''}`}>{itineraryIcons[item.type]} {item.title}</span><span className="mt-1 flex items-center gap-1 truncate text-xs"><MapPin size={12}/>{completed ? 'Completed · tap to reopen' : current ? 'Next stop · tap to mark complete' : item.location_name || item.location_address || 'Planned stop'}</span></span>
                                    {completed ? <CheckCircle2 size={21} className="shrink-0 text-[#20b960]"/> : current && canManage ? <span className="shrink-0 rounded-full bg-[#20b960] px-3 py-1.5 text-xs font-bold text-white">Arrived</span> : null}</>}
                                </div>;
                            })}</div>
                        </div>
                    </div></div>
                </div>
            </div>
        </> : <>
            <div className="border-t border-slate-200 bg-[#f8fafc] px-5 py-3 text-center text-xs text-slate-500">Keep SplitShare open during live navigation.</div>
            {summary?.legs?.length > 0 && <div className="divide-y divide-slate-100 border-t border-slate-200">{summary.legs.map((leg, index) => { const from = stops[index], to = stops[index + 1], departure = from?.end_time || from?.start_time, eta = clockTime(departure, leg.duration_seconds); return <div key={`${from?.id}-${to?.id}`} className="flex items-center gap-3 px-5 py-4"><span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-[#3158cf] text-sm font-bold text-white">{index + 1}</span><div className="min-w-0 flex-1"><p className="truncate text-sm font-bold">{from?.title} → {to?.title}</p><p className="mt-1 text-xs text-slate-500">{legLabel(leg)} · {legDuration(leg)}</p></div><div className="shrink-0 text-right"><p className="text-[10px] font-bold uppercase tracking-wider text-slate-400">ETA</p><p className="mt-1 font-display font-bold text-[#244bc3]">{eta || 'Set time'}</p></div></div>; })}</div>}
        </>}
        {dragPreview?.item && createPortal(<div className="pointer-events-none fixed z-[999] flex items-center gap-2 rounded-2xl border-2 border-[#20b960] bg-white p-3 text-left shadow-[0_18px_45px_rgba(15,23,42,.28)]" style={{ top: dragPreview.top, left: dragPreview.left, width: dragPreview.width }}>
            <span className="flex size-8 shrink-0 items-center justify-center text-[#13a856]"><GripVertical size={19}/></span>
            <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-[#20b960] text-sm font-bold text-white">{orderedItemsRef.current.findIndex((item) => item.id === dragPreview.item.id) + 1}</span>
            <span className="min-w-0 flex-1"><span className="block truncate text-sm font-bold text-slate-900">{itineraryIcons[dragPreview.item.type]} {dragPreview.item.title}</span><span className="mt-1 block truncate text-xs text-slate-500">Move to reorder this stop</span></span>
        </div>, document.body)}
    </div>;
}
