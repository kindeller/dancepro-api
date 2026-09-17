(() => {
    const config = JSON.parse(document.querySelector('#concert-media-management-config').textContent);
    const root = document.querySelector('#concert-media-management');
    const message = document.querySelector('#concert-media-message');

    async function update(path, data) {
        const response = await fetch(`${config.base}/${path}`, {
            method: 'PATCH', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': config.csrf },
            body: JSON.stringify(data),
        });
        const result = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message || `Request failed (${response.status}).`);
        location.reload();
    }

    function showError(error) {
        message.hidden = false;
        message.textContent = error.message;
    }

    root.addEventListener('click', async event => {
        const button = event.target.closest('button');
        if (!button || !root.contains(button)) return;
        const collection = button.closest('[data-collection]');
        const asset = button.closest('[data-asset]');
        const path = asset ? `assets/${asset.dataset.asset}` : `collections/${collection.dataset.collection}`;

        if (button.matches('[data-edit-collection], [data-edit-asset]')) {
            const current = button.matches('[data-edit-collection]')
                ? collection.querySelector(':scope > .concert-media-line .concert-media-name strong').textContent
                : asset.querySelector('.concert-media-name span').textContent;
            const name = prompt(button.matches('[data-edit-collection]') ? 'Collection name' : 'Video title', current);
            if (name === null || name.trim() === current) return;
            if (!name.trim() || name.length > 255) { showError(new Error('Enter a name of 1–255 characters.')); return; }
            button.disabled = true;
            try { await update(path, button.matches('[data-edit-collection]') ? { name: name.trim() } : { display_name: name.trim() }); }
            catch (error) { showError(error); button.disabled = false; }
            return;
        }

        if (button.dataset.collectionStatus || button.dataset.assetVisible) {
            button.disabled = true;
            try { await update(path, button.dataset.collectionStatus ? { status: button.dataset.collectionStatus } : { is_visible: button.dataset.assetVisible === '1' }); }
            catch (error) { showError(error); button.disabled = false; }
        }
    });
})();
