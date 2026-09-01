import Dexie from 'dexie';

const db = new Dexie('splitshare-offline');
db.version(1).stores({
    keys: '&id',
    profiles: '&userId, lastUsedAt',
    snapshots: '&userId, savedAt',
    operations: '&id, userId, status, createdAt',
});

const encoder = new TextEncoder();
const decoder = new TextDecoder();
const KEY_ID = 'splitshare-offline-device-key-v1';
const SNAPSHOT_ID = 'splitshare-offline-latest-snapshot-v1';
const eventName = 'splitshare-offline-status';
let activeKey;

function publish(detail) { window.dispatchEvent(new CustomEvent(eventName, { detail })); }
export { eventName };

async function deviceKey() {
    if (activeKey) return activeKey;
    // Safari does not reliably structured-clone CryptoKey into IndexedDB.
    // Keep only the device key material in local storage and encrypt every
    // user snapshot/queued operation before it enters IndexedDB.
    let encoded = localStorage.getItem(KEY_ID);
    if (!encoded) {
        const raw = crypto.getRandomValues(new Uint8Array(32));
        encoded = btoa(String.fromCharCode(...raw));
        localStorage.setItem(KEY_ID, encoded);
    }
    const raw = Uint8Array.from(atob(encoded), (character) => character.charCodeAt(0));
    activeKey = await crypto.subtle.importKey('raw', raw, { name: 'AES-GCM' }, false, ['encrypt', 'decrypt']);
    return activeKey;
}
async function seal(value) {
    const iv = crypto.getRandomValues(new Uint8Array(12));
    const data = await crypto.subtle.encrypt({ name: 'AES-GCM', iv }, await deviceKey(), encoder.encode(JSON.stringify(value)));
    return { iv: Array.from(iv), data };
}
function encodeBytes(data) { return btoa(String.fromCharCode(...new Uint8Array(data))); }
function decodeBytes(data) { return Uint8Array.from(atob(data), (character) => character.charCodeAt(0)).buffer; }
function browserSnapshot(userId, encrypted) { return JSON.stringify({ userId: String(userId), savedAt: Date.now(), iv: encrypted.iv, data: encodeBytes(encrypted.data) }); }
async function open(record) {
    const plain = await crypto.subtle.decrypt({ name: 'AES-GCM', iv: new Uint8Array(record.iv) }, await deviceKey(), record.data);
    return JSON.parse(decoder.decode(plain));
}
function csrf() { return document.querySelector('meta[name="csrf-token"]')?.content || ''; }
function report(stage, error) {
    const payload = JSON.stringify({ stage, name: error?.name || 'Error', message: String(error?.message || error || 'Unknown offline cache error'), online: navigator.onLine, user_agent: navigator.userAgent });
    fetch('/offline/diagnostics', { method: 'POST', credentials: 'same-origin', keepalive: true, headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() }, body: payload }).catch(() => {});
}

export async function saveSnapshot(userId, snapshot) {
    let encrypted;
    try { encrypted = await seal(snapshot); } catch (error) { report('encrypt_snapshot', error); throw error; }
    // This immediate localStorage copy is the Safari-safe primary fallback.
    // IndexedDB remains useful for the larger mutation queue, but some iOS PWA
    // contexts discard/deny its first write even after a successful fetch.
    try { localStorage.setItem(SNAPSHOT_ID, browserSnapshot(userId, encrypted)); } catch (error) { report('write_local_storage', error); throw error; }
    try { await db.transaction('rw', db.profiles, db.snapshots, async () => {
        await db.profiles.put({ userId: String(userId), lastUsedAt: Date.now() });
        await db.snapshots.put({ userId: String(userId), savedAt: Date.now(), ...encrypted });
    }); } catch (_) {}
    publish({ state: 'saved' });
}
export async function loadLatestSnapshot() {
    try {
        const profile = await db.profiles.orderBy('lastUsedAt').last();
        const snapshot = profile && await db.snapshots.get(profile.userId);
        if (snapshot) return { userId: profile.userId, snapshot: await open(snapshot) };
    } catch (error) { report('write_indexeddb', error); }
    try {
        const saved = JSON.parse(localStorage.getItem(SNAPSHOT_ID) || 'null');
        if (!saved?.userId || !saved?.data) return null;
        return { userId: saved.userId, snapshot: await open({ iv: saved.iv, data: decodeBytes(saved.data) }) };
    } catch (_) { return null; }
}
export async function queueOperation(userId, operation) {
    await db.operations.put({ id: operation.id, userId: String(userId), status: 'pending', createdAt: Date.now(), ...(await seal(operation)) });
    publish({ state: 'pending', pending: await pendingCount(userId) });
}
export async function pendingCount(userId) { return db.operations.where({ userId: String(userId), status: 'pending' }).count(); }

export async function refreshSnapshot() {
    try {
        if (!navigator.onLine) return null;
        const response = await fetch('/offline/bootstrap', { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (!response.ok) { report('fetch_bootstrap', new Error(`HTTP ${response.status}`)); return null; }
        const snapshot = await response.json();
        await saveSnapshot(snapshot.user.id, snapshot);
        return snapshot;
    } catch (error) { report('refresh_snapshot', error); return null; }
}
export async function syncPending(userId) {
    if (!navigator.onLine) { publish({ state: 'offline', pending: await pendingCount(userId) }); return; }
    const rows = await db.operations.where({ userId: String(userId), status: 'pending' }).sortBy('createdAt');
    if (!rows.length) { publish({ state: 'synced', pending: 0 }); return; }
    publish({ state: 'syncing', pending: rows.length });
    const operations = await Promise.all(rows.map(open));
    try {
        const response = await fetch('/offline/sync/batch', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() }, body: JSON.stringify({ operations }) });
        if (!response.ok) throw new Error('sync unavailable');
        const { results } = await response.json();
        await db.transaction('rw', db.operations, async () => {
            for (const result of results) {
                if (result.status === 'applied') await db.operations.delete(result.id);
                else await db.operations.update(result.id, { status: 'rejected', rejection: result.message || 'Rejected by server' });
            }
        });
        await refreshSnapshot();
        publish({ state: 'synced', pending: await pendingCount(userId) });
    } catch (_) { publish({ state: 'offline', pending: await pendingCount(userId) }); }
}
export function startOfflineSync(userId) {
    const sync = () => syncPending(userId);
    const refresh = () => refreshSnapshot().then(sync);
    window.addEventListener('online', refresh); window.addEventListener('focus', refresh);
    refresh();
    return () => { window.removeEventListener('online', refresh); window.removeEventListener('focus', refresh); };
}
export function newOperation(type, trackerId, payload) { return { id: crypto.randomUUID(), type, tracker_id: trackerId, payload, created_at: new Date().toISOString() }; }
