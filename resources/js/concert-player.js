import Hls from 'hls.js';

const player = document.querySelector('#concert-player');
const items = [...document.querySelectorAll('.playlist-item')];

if (player && items.length > 0) {
    const playerTitle = document.querySelector('#player-title');
    const playerDownload = document.querySelector('#player-download');
    const playerStatus = document.querySelector('#player-status');
    const qualitySelect = document.querySelector('#player-quality');
    let hls = null;
    let playbackRequest = 0;
    let activeFallbackUrl = null;
    let fallbackInProgress = false;

    function destroyHls() {
        if (hls) {
            hls.destroy();
            hls = null;
        }

        qualitySelect.replaceChildren(new Option('Auto', '-1'));
        qualitySelect.disabled = true;
    }

    function useProgressiveSource(url, autoplay) {
        destroyHls();
        activeFallbackUrl = null;
        fallbackInProgress = true;
        player.src = url;
        playerStatus.textContent = 'MP4 playback';
        player.load();

        if (autoplay) {
            player.play().catch(() => {});
        }
    }

    function useHlsSource(source, autoplay) {
        destroyHls();
        activeFallbackUrl = source.fallback_url;
        fallbackInProgress = false;
        playerStatus.textContent = 'Adaptive streaming';

        if (player.canPlayType('application/vnd.apple.mpegurl')) {
            player.src = source.url;
            player.load();

            if (autoplay) {
                player.play().catch(() => {});
            }

            return;
        }

        if (!Hls.isSupported()) {
            useProgressiveSource(source.fallback_url, autoplay);
            return;
        }

        hls = new Hls({
            capLevelToPlayerSize: true,
            xhrSetup(xhr) {
                xhr.withCredentials = true;
            },
        });
        hls.loadSource(source.url);
        hls.attachMedia(player);
        hls.on(Hls.Events.MANIFEST_PARSED, (_event, data) => {
            qualitySelect.replaceChildren(new Option('Auto', '-1'));

            data.levels.forEach((level, index) => {
                const label = level.name || (level.height ? `${level.height}p` : `Quality ${index + 1}`);
                qualitySelect.add(new Option(label, String(index)));
            });

            qualitySelect.disabled = data.levels.length < 2;

            if (autoplay) {
                player.play().catch(() => {});
            }
        });
        hls.on(Hls.Events.ERROR, (_event, data) => {
            if (data.fatal && !fallbackInProgress && activeFallbackUrl) {
                fallbackInProgress = true;
                useProgressiveSource(source.fallback_url, autoplay);
            }
        });
    }

    async function selectItem(item, autoplay = true) {
        const request = ++playbackRequest;
        items.forEach(entry => entry.classList.toggle('active', entry === item));
        playerTitle.textContent = item.dataset.title;
        playerDownload.href = item.dataset.download;
        playerStatus.textContent = 'Preparing playback…';
        activeFallbackUrl = null;
        fallbackInProgress = false;
        destroyHls();
        player.removeAttribute('src');
        player.load();

        try {
            const response = await fetch(item.dataset.playback, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                throw new Error('Playback is unavailable.');
            }

            const payload = await response.json();

            if (request !== playbackRequest) {
                return;
            }

            if (payload.data.format === 'hls') {
                useHlsSource(payload.data, autoplay);
            } else {
                useProgressiveSource(payload.data.url, autoplay);
            }
        } catch (_error) {
            if (request === playbackRequest) {
                playerStatus.textContent = 'Playback is currently unavailable.';
            }
        }
    }

    items.forEach(item => item.addEventListener('click', () => selectItem(item)));
    qualitySelect.addEventListener('change', () => {
        if (hls) {
            hls.currentLevel = Number(qualitySelect.value);
        }
    });
    player.addEventListener('ended', () => {
        const activeIndex = items.findIndex(item => item.classList.contains('active'));
        const next = items[activeIndex + 1];

        if (next) {
            selectItem(next);
        }
    });
    player.addEventListener('error', () => {
        if (activeFallbackUrl && !fallbackInProgress) {
            const fallbackUrl = activeFallbackUrl;
            fallbackInProgress = true;
            useProgressiveSource(fallbackUrl, true);
        }
    });

    selectItem(items[0], false);

    let downloadIndex = 0;
    let downloadTimer;
    let downloadsPaused = false;
    const downloadStatus = document.querySelector('#download-status');
    const startButton = document.querySelector('#download-all');
    const pauseButton = document.querySelector('#download-pause');

    function updateDownloadStatus(message) {
        downloadStatus.textContent = message ?? `${downloadIndex} of ${items.length} downloads started.`;
    }

    function runDownloads() {
        if (downloadsPaused || downloadIndex >= items.length) {
            if (downloadIndex >= items.length) {
                updateDownloadStatus('All downloads have been sent to your browser.');
                pauseButton.disabled = true;
            }

            return;
        }

        const link = document.createElement('a');
        link.href = items[downloadIndex].dataset.download;
        link.click();
        downloadIndex++;
        updateDownloadStatus();
        downloadTimer = setTimeout(runDownloads, 900);
    }

    startButton.addEventListener('click', () => {
        if (downloadIndex === 0 && !confirm('Your browser may ask permission to download multiple files. Continue?')) {
            return;
        }

        downloadsPaused = false;
        pauseButton.disabled = false;
        pauseButton.textContent = 'Pause';
        runDownloads();
    });
    pauseButton.addEventListener('click', () => {
        downloadsPaused = !downloadsPaused;
        clearTimeout(downloadTimer);
        pauseButton.textContent = downloadsPaused ? 'Resume' : 'Pause';
        updateDownloadStatus(downloadsPaused ? `Paused after ${downloadIndex} of ${items.length} downloads.` : null);

        if (!downloadsPaused) {
            runDownloads();
        }
    });
    document.querySelector('#download-reset').addEventListener('click', () => {
        clearTimeout(downloadTimer);
        downloadIndex = 0;
        downloadsPaused = false;
        pauseButton.disabled = true;
        pauseButton.textContent = 'Pause';
        updateDownloadStatus(`Ready to download ${items.length} originals.`);
    });
}
