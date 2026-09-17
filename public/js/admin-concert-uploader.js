(() => {
    const config = JSON.parse(document.querySelector('#media-uploader-config').textContent);
    const status = document.querySelector('#media-upload-status');
    const overall = document.querySelector('#media-overall-progress');
    const overallPercent = document.querySelector('#media-overall-percent');
    const mask = (1n << 64n) - 1n;
    const polynomial = 0x9a6c9329ac4bc9b5n;
    const table = Array.from({ length: 256 }, (_, index) => {
        let value = BigInt(index);
        for (let bit = 0; bit < 8; bit++) value = value & 1n ? (value >> 1n) ^ polynomial : value >> 1n;
        return value;
    });
    let progress = null;
    let busy = false;

    function updateCrc(crc, bytes) {
        for (const byte of bytes) crc = table[Number((crc ^ BigInt(byte)) & 255n)] ^ (crc >> 8n);
        return crc;
    }

    function encodeCrc(crc) {
        const bytes = new Uint8Array(8);
        let value = crc ^ mask;
        for (let i = 7; i >= 0; i--) {
            bytes[i] = Number(value & 255n);
            value >>= 8n;
        }
        return btoa(String.fromCharCode(...bytes));
    }

    async function api(path, method = 'POST', body = {}) {
        const response = await fetch(`${config.base}/${path}`, {
            method,
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': config.csrf,
                'Idempotency-Key': body.idempotencyKey || crypto.randomUUID(),
            },
            body: JSON.stringify(body),
        });
        const result = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message || `Request failed (${response.status}).`);
        return result.data;
    }

    function savedUpload(asset, path, file) {
        const key = `concert-upload:${asset}:${path}`;
        const fingerprint = `${file.name}:${file.size}:${file.lastModified}`;
        let state;
        try { state = JSON.parse(localStorage.getItem(key)); } catch { state = null; }
        if (state?.fingerprint !== fingerprint) state = { fingerprint, idempotencyKey: crypto.randomUUID(), complete: false };
        localStorage.setItem(key, JSON.stringify(state));
        return { key, state };
    }

    function beginProgress(form, original, fallback, groupTotal = original.size + fallback.size, groupCompleted = 0) {
        progress = { form, total: groupTotal, completed: groupCompleted, original: 0, fallback: 0 };
        overall.value = Math.round(100 * groupCompleted / groupTotal);
        overallPercent.textContent = `${overall.value}%`;
        for (const slot of form.querySelectorAll('[data-file-slot]')) {
            slot.querySelector('progress').value = 0;
            slot.querySelector('[data-file-progress]').textContent = 'Waiting to upload · 0%';
        }
    }

    function showProgress(form, kind, loaded, total, phase) {
        const slot = form.querySelector(`[data-file-slot="${kind}"]`);
        const percent = Math.round(100 * Math.min(loaded, total) / total);
        slot.querySelector('progress').value = percent;
        slot.querySelector('[data-file-progress]').textContent = `${phase} · ${percent}%`;
        if (progress?.form === form) {
            progress[kind] = Math.min(loaded, total);
            const combined = Math.round(100 * (progress.completed + progress.original + progress.fallback) / progress.total);
            overall.value = combined;
            overallPercent.textContent = `${combined}%`;
        }
    }

    async function checksumFile(file, form, kind) {
        let crc = mask;
        const chunkSize = 8 * 1024 * 1024;
        for (let offset = 0; offset < file.size; offset += chunkSize) {
            const bytes = new Uint8Array(await file.slice(offset, offset + chunkSize).arrayBuffer());
            crc = updateCrc(crc, bytes);
            form.querySelector(`[data-file-slot="${kind}"] [data-file-progress]`).textContent =
                `Checking file · ${Math.round(100 * Math.min(offset + chunkSize, file.size) / file.size)}%`;
        }
        return encodeCrc(crc);
    }

    function putPart(signed, blob, onProgress) {
        return new Promise((resolve, reject) => {
            const request = new XMLHttpRequest();
            request.open('PUT', signed.url);
            for (const [name, value] of Object.entries(signed.headers)) request.setRequestHeader(name, value);
            request.upload.onprogress = event => { if (event.lengthComputable) onProgress(event.loaded); };
            request.onerror = () => reject(new Error('S3 upload failed. Check network access and bucket CORS.'));
            request.onload = () => {
                if (request.status < 200 || request.status >= 300) {
                    reject(new Error(`S3 rejected an upload part (${request.status}). Check bucket permissions and CORS.`));
                    return;
                }
                const etag = request.getResponseHeader('ETag');
                if (!etag) {
                    reject(new Error('S3 did not expose ETag. Add ETag to the bucket CORS ExposeHeaders.'));
                    return;
                }
                resolve(etag);
            };
            request.send(blob);
        });
    }

    async function uploadFile(asset, path, file, form, kind) {
        if (!file || !file.name.toLowerCase().endsWith('.mp4') || !file.size) throw new Error('Select a non-empty MP4 file for each slot.');
        const { key, state } = savedUpload(asset, path, file);
        if (state.complete) { showProgress(form, kind, file.size, file.size, 'Already uploaded'); return; }

        // CRC-64/NVME check vector: ASCII 123456789 -> AE8B14860A799888.
        const fullChecksum = await checksumFile(file, form, kind);
        const started = await api(`assets/${asset}/multipart`, 'POST', {
            idempotencyKey: state.idempotencyKey,
            relative_path: path,
            content_type: 'video/mp4',
            size_bytes: file.size,
            checksum_algorithm: 'CRC64NVME',
            checksum: fullChecksum,
            source_filename: file.name,
        });
        const partSize = started.part_size_bytes;
        const count = Math.ceil(file.size / partSize);
        if (count > 10000) throw new Error('This file exceeds the supported multipart part count.');
        const completed = [];

        for (let number = 1; number <= count; number++) {
            const offset = (number - 1) * partSize;
            const blob = file.slice(offset, number * partSize);
            const bytes = new Uint8Array(await blob.arrayBuffer());
            const partChecksum = encodeCrc(updateCrc(mask, bytes));
            const request = await api(`assets/${asset}/multipart/${started.upload_uuid}/parts`, 'POST', {
                parts: [{ part_number: number, checksum_crc64nvme: partChecksum }],
            });
            const etag = await putPart(request.parts[0], blob, loaded => showProgress(form, kind, offset + loaded, file.size, 'Uploading'));
            completed.push({ part_number: number, etag, checksum_crc64nvme: partChecksum });
            showProgress(form, kind, offset + blob.size, file.size, 'Uploading');
        }

        form.querySelector(`[data-file-slot="${kind}"] [data-file-progress]`).textContent = 'Verifying with S3 · 100%';
        await api(`assets/${asset}/multipart/${started.upload_uuid}/complete`, 'POST', {
            parts: completed,
            checksum_crc64nvme: fullChecksum,
        });
        state.complete = true;
        localStorage.setItem(key, JSON.stringify(state));
        showProgress(form, kind, file.size, file.size, 'Verified');
    }

    async function uploadBoth(asset, form, original = form.elements.original.files[0], fallback = form.elements.fallback.files[0], groupTotal, groupCompleted) {
        beginProgress(form, original, fallback, groupTotal, groupCompleted);
        await uploadFile(asset, 'original/video.mp4', original, form, 'original');
        await uploadFile(asset, 'stream/fallback.mp4', fallback, form, 'fallback');
        status.textContent = 'Finalising video…';
        try {
            await api(`assets/${asset}/finalize`, 'POST', { expected_outputs: ['original', 'fallback_mp4'] });
        } catch (error) {
            localStorage.removeItem(`concert-upload:${asset}:original/video.mp4`);
            localStorage.removeItem(`concert-upload:${asset}:stream/fallback.mp4`);
            throw error;
        }
    }

    async function run(form, work) {
        if (busy) { status.textContent = 'Finish the current upload before starting another.'; return; }
        busy = true;
        const button = form.querySelector('button[type="submit"], [data-bulk-upload]');
        button.disabled = true;
        try { await work(); } catch (error) { status.textContent = error.message; button.disabled = false; busy = false; return; }
        location.reload();
    }

    for (const slot of document.querySelectorAll('[data-file-slot]')) {
        const input = slot.querySelector('input[type=file]');
        const form = slot.closest('form');
        const title = form.elements.display_name;
        input.addEventListener('change', () => {
            const file = input.files[0];
            slot.querySelector('[data-file-name]').textContent = file ? file.name : 'No file selected';
            slot.querySelector('[data-file-progress]').textContent = 'Ready · 0%';
            if (file && input.name === 'original' && title && !title.dataset.edited) {
                title.value = file.name.replace(/\.mp4$/i, '').replace(/[_-]+/g, ' ').trim();
            }
        });
        slot.addEventListener('dragover', event => { event.preventDefault(); slot.classList.add('is-dragging'); });
        slot.addEventListener('dragleave', () => slot.classList.remove('is-dragging'));
        slot.addEventListener('drop', event => {
            event.preventDefault();
            slot.classList.remove('is-dragging');
            const file = event.dataTransfer.files[0];
            if (!file) return;
            if (!file.name.toLowerCase().endsWith('.mp4')) { status.textContent = 'Select an MP4 file.'; return; }
            const transfer = new DataTransfer();
            transfer.items.add(file);
            input.files = transfer.files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }
    for (const title of document.querySelectorAll('.new-media-asset [name=display_name]')) {
        title.addEventListener('input', () => { title.dataset.edited = '1'; });
    }

    document.querySelector('#new-media-collection')?.addEventListener('submit', event => {
        event.preventDefault();
        const form = event.currentTarget;
        form.dataset.idempotencyKey ||= crypto.randomUUID();
        run(form, () => api(`concerts/${config.concert}/collections`, 'POST', {
            idempotencyKey: form.dataset.idempotencyKey,
            name: form.elements.namedItem('name').value,
            media_type: 'video',
        }));
    });

    document.querySelectorAll('.new-media-asset').forEach(form => form.addEventListener('submit', event => {
        event.preventDefault();
        run(form, async () => {
            let collection = form.closest('[data-collection]')?.dataset.collection;
            if (!collection) {
                form.dataset.collectionKey ||= crypto.randomUUID();
                const created = await api(`concerts/${config.concert}/collections`, 'POST', {
                    idempotencyKey: form.dataset.collectionKey,
                    name: form.dataset.defaultCollectionName,
                    media_type: 'video',
                });
                collection = created.uuid;
            }
            form.dataset.assetKey ||= crypto.randomUUID();
            const original = form.elements.original.files[0];
            const fallback = form.elements.fallback.files[0];
            const asset = await api(`collections/${collection}/assets`, 'POST', {
                idempotencyKey: form.dataset.assetKey,
                media_type: 'video',
                display_name: form.elements.display_name.value,
                original_filename: original.name,
                expected_outputs: ['original', 'fallback_mp4'],
                source: { fallback_filename: fallback.name },
            });
            await uploadBoth(asset.uuid, form);
        });
    }));

    function matchBatch(files) {
        const groups = new Map();
        const errors = [];
        for (const file of files) {
            const match = /^(.*)\.mp4$/i.exec(file.name);
            if (!match || !file.size) { errors.push(`${file.name}: only non-empty MP4 files are supported.`); continue; }
            const stream = /-stream$/i.test(match[1]);
            const name = stream ? match[1].slice(0, -7) : match[1];
            if (!name.trim()) { errors.push(`${file.name}: missing video name.`); continue; }
            const key = name.toLocaleLowerCase();
            if (!groups.has(key)) groups.set(key, { name });
            const pair = groups.get(key);
            const slot = stream ? 'fallback' : 'original';
            if (pair[slot]) errors.push(`${file.name}: duplicate ${stream ? 'playback' : 'original'} file for ${name}.`);
            else pair[slot] = file;
        }
        for (const pair of groups.values()) {
            if (!pair.original || !pair.fallback) errors.push(`${pair.name}: needs both an original and a -stream MP4.`);
        }
        return { pairs: [...groups.values()].filter(pair => pair.original && pair.fallback), errors };
    }

    document.querySelectorAll('.media-bulk').forEach(bulk => {
        const input = bulk.querySelector('[data-bulk-files]');
        const drop = bulk.querySelector('[data-bulk-drop]');
        const preview = bulk.querySelector('[data-bulk-preview]');
        const button = bulk.querySelector('[data-bulk-upload]');
        let pairs = [];

        function previewFiles(files) {
            if (busy) return;
            const matched = matchBatch(files);
            pairs = matched.pairs;
            preview.replaceChildren();
            for (const pair of pairs) {
                const row = document.querySelector('#media-bulk-pair-template').content.firstElementChild.cloneNode(true);
                row.querySelector('[data-bulk-title]').value = pair.name.replace(/[_-]+/g, ' ').trim();
                row.querySelector('[data-file-slot="original"] [data-file-name]').textContent = pair.original.name;
                row.querySelector('[data-file-slot="fallback"] [data-file-name]').textContent = pair.fallback.name;
                pair.row = row;
                pair.assetKey = crypto.randomUUID();
                preview.append(row);
            }
            for (const message of matched.errors) {
                const error = document.createElement('p');
                error.className = 'media-bulk-error';
                error.textContent = message;
                preview.append(error);
            }
            button.disabled = !pairs.length || matched.errors.length > 0;
            status.textContent = matched.errors.length ? 'Fix the unmatched files before uploading.' : `${pairs.length} matched video${pairs.length === 1 ? '' : 's'} ready to upload.`;
        }

        input.addEventListener('change', () => previewFiles(input.files));
        drop.addEventListener('dragover', event => { event.preventDefault(); drop.classList.add('is-dragging'); });
        drop.addEventListener('dragleave', () => drop.classList.remove('is-dragging'));
        drop.addEventListener('drop', event => {
            event.preventDefault();
            drop.classList.remove('is-dragging');
            previewFiles(event.dataTransfer.files);
        });
        button.addEventListener('click', () => run(bulk, async () => {
            if (!pairs.length || pairs.some(pair => !pair.row.querySelector('[data-bulk-title]').value.trim())) {
                throw new Error('Enter a title for every matched video.');
            }
            let collection = bulk.closest('[data-collection]')?.dataset.collection;
            if (!collection) {
                bulk.dataset.collectionKey ||= crypto.randomUUID();
                const created = await api(`concerts/${config.concert}/collections`, 'POST', {
                    idempotencyKey: bulk.dataset.collectionKey,
                    name: bulk.dataset.defaultCollectionName,
                    media_type: 'video',
                });
                collection = created.uuid;
            }
            const total = pairs.reduce((sum, pair) => sum + pair.original.size + pair.fallback.size, 0);
            let completed = 0;
            for (const [index, pair] of pairs.entries()) {
                status.textContent = `Uploading video ${index + 1} of ${pairs.length}: ${pair.name}`;
                const asset = await api(`collections/${collection}/assets`, 'POST', {
                    idempotencyKey: pair.assetKey,
                    media_type: 'video',
                    display_name: pair.row.querySelector('[data-bulk-title]').value.trim(),
                    original_filename: pair.original.name,
                    expected_outputs: ['original', 'fallback_mp4'],
                    source: { fallback_filename: pair.fallback.name },
                });
                await uploadBoth(asset.uuid, pair.row, pair.original, pair.fallback, total, completed);
                completed += pair.original.size + pair.fallback.size;
            }
        }));
    });

    document.querySelectorAll('.media-file-upload').forEach(form => form.addEventListener('submit', event => {
        event.preventDefault();
        run(form, () => uploadBoth(form.closest('[data-asset]').dataset.asset, form));
    }));

    document.querySelectorAll('[data-asset-visible]').forEach(button => button.addEventListener('click', async () => {
        button.disabled = true;
        try { await api(`assets/${button.closest('[data-asset]').dataset.asset}`, 'PATCH', { is_visible: button.dataset.assetVisible === '1' }); location.reload(); }
        catch (error) { status.textContent = error.message; button.disabled = false; }
    }));

    document.querySelectorAll('[data-collection-status]').forEach(button => button.addEventListener('click', async () => {
        button.disabled = true;
        try { await api(`collections/${button.closest('[data-collection]').dataset.collection}`, 'PATCH', { status: button.dataset.collectionStatus }); location.reload(); }
        catch (error) { status.textContent = error.message; button.disabled = false; }
    }));
})();
