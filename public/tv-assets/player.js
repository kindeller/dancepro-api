import { SlideCache, cacheKey, isPlayable, validateManifest } from './cache.js';
const el = id => document.getElementById(id);
const preview = document.body.dataset.preview === 'true';
const cache = new SlideCache(!preview);
let manifest, token, pairing, playlist = [], index = -1, timer, pollTimer, hideTimer, paused = false, syncing = false, frameIndex = 0, generation = 0, rendering = false, currentSlide = null, activeVideo = null;
const frames = [el('frame-a'), el('frame-b')];
const objectUrls = new Map();
let durable = !preview, controlsRequested = false;
function status(text) { el('status').textContent = text; }
function controls(manual = true) { controlsRequested = manual; el('controls').inert = false; el('controls').classList.remove('hidden'); clearTimeout(hideTimer); if (playlist.length) hideTimer = setTimeout(hideControls, 12000); }
function hideControls() { controlsRequested = false; el('controls').inert = true; el('controls').classList.add('hidden'); document.activeElement?.blur(); }
function welcome(text) { el('welcome').hidden = false; el('message').textContent = text; }
function stopVideo() { if (activeVideo) { activeVideo.onended = null; activeVideo.onerror = null; activeVideo.pause(); activeVideo.removeAttribute("src"); activeVideo.load(); activeVideo = null; } }
function stop(text) { stopVideo(); clearTimeout(timer); generation++; playlist = []; frames.forEach(f => f.classList.remove('visible')); welcome(text); controls(false); }
function headers() { return { Accept: 'application/json', ...(token ? { Authorization: `Bearer ${token}` } : {}) }; }
async function request(url, options = {}) {
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 20000);
    try {
        const response = await fetch(url, { ...options, cache: 'no-store', headers: headers(), signal: controller.signal });
        if (!response.ok) { const error = new Error(`Screen update failed (${response.status}).`); error.status = response.status; throw error; }
        return response;
    } finally { clearTimeout(timeout); }
}
async function available() {
    const ready = [];
    if (isPlayable(manifest)) for (const slide of manifest.slides) if (slide.kind === 'text' || await cache.get(cacheKey(slide))) ready.push(slide);
    playlist = ready;
    if (currentSlide && !ready.some(s => s.id === currentSlide.id && s.version === currentSlide.version)) {
        stopVideo(); clearTimeout(timer); timer = null; generation++; currentSlide = null;
        frames.forEach(f => f.classList.remove('visible'));
    }
    if (!playlist.length) stop(manifest?.enabled ? 'Preparing the slideshow. Keep this screen connected for its first download.' : 'TV playback is disabled. Enable it in Publicity → TV Slideshows.');
    else if (!timer && !activeVideo && !rendering && !paused) await showNext();
}
async function showNext(direction = 1) {
    clearTimeout(timer); timer = null;
    if (rendering || !playlist.length || !isPlayable(manifest)) return;
    stopVideo();
    const currentGeneration = generation;
    rendering = true;
    index = (index + direction + playlist.length) % playlist.length;
    const slide = playlist[index];
    const frame = frames[1 - frameIndex];
    frame.replaceChildren(); frame.className = `frame ${slide.kind}`;
    try {
        if (['image','video'].includes(slide.kind)) {
            const key = cacheKey(slide), blob = await cache.get(key);
            if (!blob) return;
            if (!objectUrls.has(key)) objectUrls.set(key, URL.createObjectURL(blob));
            if (slide.kind === 'video') {
                const video = document.createElement('video'); video.playsInline = true; video.muted = !slide.play_sound; video.preload = 'auto';
                video.setAttribute('aria-label',slide.title || 'Video advert'); video.src = objectUrls.get(key); frame.append(video);
                await videoReady(video);
                if (currentGeneration !== generation) { video.removeAttribute('src'); video.load(); return; }
                activeVideo = video;
                video.onended = () => { if (activeVideo === video && !paused) showNext(); };
                video.onerror = () => { if (activeVideo === video) { status('Video could not play. Skipping it.'); stopVideo(); if (!paused) timer = setTimeout(showNext,1000); } };
            } else {
                const image = document.createElement('img'); image.alt = slide.title || 'Event Top Pic'; image.src = objectUrls.get(key);
                await image.decode(); frame.append(image);
            }
        }
        if (currentGeneration !== generation) return;
        if (slide.kind === 'text' ? (slide.title || slide.body) : (slide.kind === 'image' && slide.body)) {
            frame.classList.add('has-caption');
            const caption = document.createElement('div'); caption.className = 'caption';
            if (slide.title) { const h = document.createElement('h2'); h.textContent = slide.title; caption.append(h); }
            if (slide.body) { const p = document.createElement('p'); p.textContent = slide.body; caption.append(p); }
            frame.append(caption);
        }
        if (!controlsRequested) hideControls();
        el('welcome').hidden = true; frame.classList.add('visible'); frames[frameIndex].classList.remove('visible'); frameIndex = 1 - frameIndex; currentSlide = slide;
        if (activeVideo && !paused) await playVideo();
    } catch { stopVideo(); status('This slide could not be displayed. Skipping it.'); }
    finally { rendering = false; if (currentGeneration !== generation && playlist.length && !paused) { timer = setTimeout(showNext, 0); } else if (!activeVideo && !paused && playlist.length && currentGeneration === generation) timer = setTimeout(showNext, slide.seconds * 1000); }
}
function videoReady(video) {
    return new Promise((resolve,reject) => {
        const timeout = setTimeout(() => finish(new Error('Video could not be loaded.')),15000);
        const finish = error => { clearTimeout(timeout); video.onloadeddata = null; video.onerror = null; error ? reject(error) : resolve(); };
        video.onloadeddata = () => finish(); video.onerror = () => finish(new Error('Unsupported video.'));
        video.load();
    });
}
async function playVideo() {
    const video = activeVideo;
    if (!video) return;
    try { await video.play(); }
    catch {
        if (activeVideo !== video) return;
        video.muted = true;
        try { await video.play(); status('Browser blocked audio; this advert is playing silently.'); }
        catch { stopVideo(); status('Video could not start. Skipping it.'); if (!paused) timer = setTimeout(showNext,1000); }
    }
}
async function boundedBlob(response, limit) {
    const reader = response.body.getReader(), chunks = []; let bytes = 0;
    const timeout = setTimeout(() => reader.cancel(),300000);
    try {
        while (true) {
            const {done,value} = await reader.read(); if (done) break;
            bytes += value.length; if (bytes > limit) { await reader.cancel(); throw new Error('Slide too large.'); }
            chunks.push(value);
        }
        return new Blob(chunks,{type:response.headers.get('Content-Type') || ''});
    } finally { clearTimeout(timeout); reader.releaseLock(); }
}
async function sync() {
    if (syncing || (!preview && !token)) return;
    syncing = true; clearTimeout(pollTimer);
    try {
        const params = new URLSearchParams({ cached_count: String(playlist.length) });
        if (manifest?.revision && playlist.length === manifest.slides.length) params.set('cached_revision', manifest.revision);
        const response = await request(`${document.body.dataset.manifest}?${params}`);
        const data = (await response.json()).data;
        if (data.paired === false) {
            if (pairing && pairing.expires > Date.now()) { el('pair-code').textContent = pairing.code; welcome('Enter this code in the event’s TV Slideshows page. The code expires in 10 minutes.'); }
            else welcome('The pairing code has expired. Choose Pair screen for a new code.');
            status('Waiting for admin pairing'); return;
        }
        manifest = validateManifest(data);
        branding();
        el('pair-code').textContent = ''; pairing = null;
        await cache.put('manifest', manifest); await cache.remove('pairing');
        // Apply removals immediately; a failed download must never restore an unstarred photo.
        await cache.retain([...manifest.slides, ...(manifest.standard_slides || [])]);
        for (const [key, url] of objectUrls) if (!manifest.slides.some(s => cacheKey(s) === key)) { URL.revokeObjectURL(url); objectUrls.delete(key); }
        await available();
        let failed = 0;
        for (const slide of manifest.slides) {
            if (slide.kind === 'text' || await cache.get(cacheKey(slide))) continue;
            status(`Downloading · ${playlist.length} of ${manifest.slides.length} slides ready`);
            try {
                const isVideo = slide.kind === 'video';
                const response = await request((isVideo ? document.body.dataset.videos : document.body.dataset.images).replace('__KEY__', encodeURIComponent(slide.id)));
                const blob = await boundedBlob(response, isVideo ? 100*1024*1024 : 20*1024*1024);
                if (isVideo) {
                    if (!blob.type.startsWith('video/mp4') || blob.size !== slide.bytes) throw new Error('Incomplete video.');
                    const digest = await crypto.subtle.digest('SHA-256', await blob.arrayBuffer());
                    const hash = Array.from(new Uint8Array(digest), b => b.toString(16).padStart(2,'0')).join('');
                    if (hash !== slide.sha256) throw new Error('Incomplete video.');
                    const video = document.createElement('video'), url = URL.createObjectURL(blob);
                    try { video.preload = 'auto'; video.muted = true; video.src = url; await videoReady(video); }
                    finally { video.removeAttribute('src'); video.load(); URL.revokeObjectURL(url); }
                } else {
                    if (!blob.type.startsWith('image/')) throw new Error('Invalid image response.');
                    const url = URL.createObjectURL(blob), image = new Image();
                    try { image.src = url; await image.decode(); } finally { URL.revokeObjectURL(url); }
                }
                await cache.image(cacheKey(slide), blob); await available();
            } catch (error) {
                if ([401, 403].includes(error.status)) throw error;
                failed++; if (failed >= 3) break;
            }
        }
        const complete = playlist.length === manifest.slides.length && playlist.length > 0;
        status(manifest.enabled ? `${playlist.length}/${manifest.slides.length} slides · ${complete ? (durable ? 'Ready offline' : (preview ? 'Preview ready' : 'Ready for this session')) : 'Waiting for remaining downloads'} · Updated ${new Date().toLocaleTimeString()}` : 'Playback disabled');
        if (!manifest.slides.length && manifest.enabled) welcome('No slides yet. Add an information slide or star photos in the uploader.');
    } catch (error) {
        if ([401,403].includes(error.status)) {
            await cache.clear(); token = null; manifest = null; stop('Access has ended. Pair this screen again, or sign in again for an admin preview.'); status('Not connected');
        } else { status(playlist.length ? 'Connection unavailable · playing downloaded slides' : 'Cannot connect. Check your internet connection and try again.'); }
    } finally { syncing = false; pollTimer = setTimeout(sync, pairing ? 5000 : 60000); }
}
async function pair() {
    if (syncing) return;
    try {
        const response = await request('/api/publicity/screens/pair', { method: 'POST' });
        const data = (await response.json()).data;
        await cache.clear(); stop('Preparing pairing code…'); manifest = null;
        token = data.token; pairing = { code: data.code, expires: Date.now() + data.expires_in * 1000 };
        await cache.put('token', token); await cache.put('pairing', pairing);
        await sync();
    } catch { status('Could not create a pairing code. Please try again shortly.'); }
}
el('pair').onclick = pair;
el('refresh').onclick = () => { controls(); sync(); };
el('next').onclick = () => showNext();
el('pause').onclick = () => { paused = !paused; el('pause').textContent = paused ? 'Play' : 'Pause'; if (activeVideo) { if (paused) activeVideo.pause(); else if (activeVideo.ended) showNext(); else playVideo(); } else if (paused) { clearTimeout(timer); timer = null; } else showNext(); controls(); };
el('fullscreen').onclick = async () => { try { if (document.fullscreenElement) await document.exitFullscreen(); else await document.documentElement.requestFullscreen(); } catch { status('Use your browser’s full-screen control.'); } };
el('forget').onclick = async () => { if (syncing) { status('Please wait for the current update to finish before unpairing.'); controls(); return; } if (!confirm('Unpair and remove all downloaded slides from this screen?')) return; token = null; manifest = null; clearTimeout(pollTimer); await cache.clear(); stop('Screen cleared. Choose Pair screen to connect again.'); status('Not paired'); };
el('hide-controls').onclick = hideControls;
document.addEventListener('keydown', event => {
    if (['Escape','m','M'].includes(event.key)) controls();
    if (event.target instanceof HTMLButtonElement) return;
    if (event.code === 'Space') { event.preventDefault(); el('pause').click(); }
    if (event.key === 'ArrowRight') showNext();
    if (event.key === 'ArrowLeft') showNext(-1);
});
el('stage').addEventListener('click', () => controls());
setInterval(() => { if (manifest && !isPlayable(manifest)) stop('Offline access has expired. Reconnect this screen to refresh the slideshow.'); }, 10000);
window.addEventListener('online', sync);
try { await cache.open(); } catch { durable = false; status('Persistent storage unavailable. Playback will require this page to remain open.'); }
token = await cache.get('token'); pairing = await cache.get('pairing'); manifest = await cache.get('manifest');
if (manifest) { branding(); await available(); }
if (!preview) {
    if ('serviceWorker' in navigator) { try { await navigator.serviceWorker.register('/tv-sw.js', { scope: '/tv' }); await navigator.serviceWorker.ready; } catch { durable = false; } }
    else durable = false;
}
if (navigator.storage?.persist && !preview) navigator.storage.persist().catch(() => {});
async function keepAwake() { try { if (document.visibilityState === 'visible' && navigator.wakeLock) await navigator.wakeLock.request('screen'); } catch {} }
keepAwake(); document.addEventListener('visibilitychange', keepAwake);
if (playlist.length) hideControls(); else controls(false);
if (preview || token) sync(); else status('Not paired');

function branding() {
    for (const [name, id] of [['event', 'event-brand'], ['dancepro', 'dancepro-brand']]) {
        const image = el(id), src = manifest?.overlays?.[name];
        image.hidden = !src; if (src?.startsWith('data:image/png;base64,')) image.src = src;
    }
}
