(() => {
    const forms = [...document.querySelectorAll('[data-top-pic-cover]')];
    const status = document.querySelector('[data-cover-status]');
    let saving = false;
    forms.forEach(form => form.addEventListener('submit', async event => {
        event.preventDefault();
        if (saving) return;
        saving = true;
        status.textContent = 'Saving event cover…';
        const previous = forms.map(item => item.querySelector('button').disabled);
        forms.forEach(item => { item.querySelector('button').disabled = true; });
        try {
            const response = await fetch(form.action, {method: 'POST', body: new FormData(form), headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}});
            if (!response.ok) throw new Error('Could not save the cover. Please try again.');
            const result = await response.json();
            forms.forEach(item => {
                const uuid = item.querySelector('[name="photo_uuid"]').value;
                const button = item.querySelector('button');
                button.disabled = Boolean(uuid && uuid === result.photo_uuid);
                button.textContent = !uuid ? 'Use automatic event cover' : (button.disabled ? 'Event cover ✓' : 'Use as event cover');
            });
            status.textContent = result.message;
        } catch (error) {
            forms.forEach((item, index) => { item.querySelector('button').disabled = previous[index]; });
            status.textContent = error.message;
        } finally {
            saving = false;
        }
    }));
})();
