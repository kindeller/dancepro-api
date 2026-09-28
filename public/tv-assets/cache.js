const DB_NAME = 'dancepro-tv-v1';
const LIMIT = 500 * 1024 * 1024;
export class SlideCache {
    constructor(persistent = true) { this.persistent = persistent; this.memory = new Map(); }
    async open() {
        if (!this.persistent) return;
        this.db = await new Promise((resolve, reject) => {
            const request = indexedDB.open(DB_NAME, 1);
            request.onupgradeneeded = () => { request.result.createObjectStore('data'); };
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }
    async operation(mode, action) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction('data', mode);
            const request = action(transaction.objectStore('data'));
            transaction.oncomplete = () => resolve(request.result);
            transaction.onerror = () => reject(transaction.error);
            transaction.onabort = () => reject(transaction.error || new Error('Cache write was interrupted.'));
        });
    }
    async get(key) { return this.db ? this.operation('readonly', s => s.get(key)) : this.memory.get(key); }
    async put(key, value) {
        if (this.db) await this.operation('readwrite', s => s.put(value, key));
        else this.memory.set(key, value);
    }
    async keys() { return this.db ? this.operation('readonly', s => s.getAllKeys()) : [...this.memory.keys()]; }
    async remove(key) { if (this.db) await this.operation('readwrite', s => s.delete(key)); else this.memory.delete(key); }
    async clear() { if (this.db) await this.operation('readwrite', s => s.clear()); else this.memory.clear(); }
    async image(key, blob) {
        let bytes = 0;
        for (const k of await this.keys()) { const value = await this.get(k); if (value instanceof Blob) bytes += value.size; }
        if (bytes + blob.size > LIMIT) throw new Error('The slideshow exceeds the 500 MB screen cache. Reduce the selected photos or adverts.');
        await this.put(key, blob);
    }
    async retain(slides) {
        const wanted = new Set(slides.filter(s => ['image','video'].includes(s.kind)).map(cacheKey));
        for (const key of await this.keys()) if (key.startsWith('image:') && !wanted.has(key)) await this.remove(key);
    }
}
export function cacheKey(slide) { return `image:${slide.id}:${slide.asset_version || slide.version}`; }
export function isPlayable(manifest, now = Date.now()) {
    return manifest?.image_variant === 'unwatermarked-v1' && !!manifest?.enabled && Date.parse(manifest.offline_until) > now;
}
export function validateManifest(data) {
    if (!data || typeof data.revision !== 'string' || !Array.isArray(data.slides) || !Number.isFinite(Date.parse(data.offline_until))) throw new Error('Invalid slideshow update.');
    if (data.standard_slides !== undefined && !Array.isArray(data.standard_slides)) throw new Error('Invalid standard adverts.');
    for (const s of [...data.slides, ...(data.standard_slides || [])]) {
        if (!/^(?:(?:photo|slide|auto)-[a-f0-9-]{36}|template-[a-f0-9-]{36}-[1-6])$/.test(s.id) || !['image','video','text'].includes(s.kind) || typeof s.version !== 'string' || !Number.isFinite(s.seconds) || s.seconds < (s.kind === 'video' ? 1 : 2) || s.seconds > 120) throw new Error('Invalid slide.');
        if (s.kind === 'video' && (!s.id.startsWith('slide-') || !/^[a-f0-9]{64}$/.test(s.sha256) || !Number.isInteger(s.bytes) || s.bytes <= 0 || s.bytes > 100*1024*1024)) throw new Error('Invalid video.');
    }
    return data;
}
