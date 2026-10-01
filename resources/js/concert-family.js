const csrf = document.querySelector('[data-family-viewer]')?.dataset.csrf;
const playerId = crypto.randomUUID();
const cards = [...document.querySelectorAll('[data-family-video]')];
const players = cards.map(card => card.querySelector('video')).filter(Boolean);
const jsonHeaders = { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf };

cards.forEach(card => {
    const video = card.querySelector('video');
    const status = card.querySelector('[data-family-status]');
    const playButton = card.querySelector('[data-family-play]');
    let hls;
    let lastReport = 0;
    async function report(ended = false) {
        if (!video || !Number.isFinite(video.currentTime) || !video.currentTime) return;
        lastReport = Date.now();
        try {
            const response = await fetch(card.dataset.activity, { method: 'POST', credentials: 'same-origin', headers: jsonHeaders,
                body: JSON.stringify({ player: playerId, position: Math.floor(video.currentTime), ended, paused: video.paused }), keepalive: true });
            if ([403, 410].includes(response.status)) {
                video.pause();
                video.removeAttribute('src');
                hls?.destroy();
                video.load();
                status.textContent = 'Your viewing access ended or another browser was verified. Reload this page to check access.';
            }
        } catch { /* Activity failures must not interrupt playback. */ }
    }
    playButton?.addEventListener('click', async () => {
        playButton.disabled = true;
        status.textContent = 'Preparing video…';
        try {
            const response = await fetch(`${card.dataset.playback}?player=${playerId}`,  { headers: { Accept: 'application/json' } });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.message ?? 'Video unavailable. Reload to check access.');
            const { data } = payload;
            hls?.destroy();
            video.hidden = false;
            if (data.format === 'hls') {
                video.setAttribute('crossorigin', 'use-credentials');
                if (video.canPlayType('application/vnd.apple.mpegurl')) video.src = data.url;
                else {
                    const { default: Hls } = await import('hls.js/light');
                    if (Hls.isSupported()) {
                        hls = new Hls({ xhrSetup(xhr) { xhr.withCredentials = true; } });
                        hls.loadSource(data.url);
                        hls.attachMedia(video);
                        hls.on(Hls.Events.ERROR, (_event, error) => {
                            if (error.fatal) { hls.destroy(); video.removeAttribute('crossorigin'); video.src = data.fallback_url; video.play().catch(() => {}); }
                        });
                    } else { video.removeAttribute('crossorigin'); video.src = data.fallback_url; }
                }
            } else { video.removeAttribute('crossorigin'); video.src = data.url; }
            status.textContent = '';
            playButton.textContent = 'Reload video';
            await video.play().catch(() => { status.textContent = 'Press play on the video to begin.'; });
        } catch (error) { status.textContent = error.message; }
        finally { playButton.disabled = false; }
    });
    video?.addEventListener('play', () => players.filter(other => other !== video).forEach(other => other.pause()));
    video?.addEventListener('timeupdate', () => { if (Date.now() - lastReport > 60000) report(); });
    video?.addEventListener('pause', () => report());
    video?.addEventListener('ended', () => report(true));
    video?.addEventListener('error', () => { status.textContent = 'Playback was interrupted. Use Reload video to request fresh access.'; });
    const form = card.querySelector('[data-family-download]');
    form?.addEventListener('submit', async event => {
        event.preventDefault();
        const button = form.querySelector('button');
        if (button.disabled) return;
        button.disabled = true;
        try {
            const response = await fetch(form.action, { method: 'POST', headers: jsonHeaders,
                body: JSON.stringify({ request_uuid: form.elements.request_uuid.value }) });
            const payload = await response.json();
            if (!response.ok) throw new Error(Object.values(payload.errors ?? {}).flat()[0] ?? payload.message ?? 'Unable to request download.');
            const link = document.createElement('a'); link.href = payload.url; document.body.append(link); link.click(); link.remove();
            card.querySelector('[data-download-count]').textContent = `${3 - payload.remaining} of 3 download requests used.`;
            status.textContent = 'Download requested. Check your browser’s downloads; this does not confirm the file has finished.';
            form.elements.request_uuid.value = crypto.randomUUID();
            button.disabled = payload.remaining === 0;
        } catch (error) { status.textContent = error.message; button.disabled = false; }
    });
});
