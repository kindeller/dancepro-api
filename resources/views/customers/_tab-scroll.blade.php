<script nonce="{{ request()->attributes->get('csp_nonce') }}">
(() => {
    const storageKey = 'dancepro.customer-tab-scroll';
    const pageKey = location.pathname + location.search;
    let saved;

    try {
        saved = JSON.parse(sessionStorage.getItem(storageKey) || 'null');
        sessionStorage.removeItem(storageKey);
    } catch (_) {
        // Navigation still works when browser storage is unavailable.
    }

    const tabs = [...document.querySelectorAll('.section-tabs')];
    const targetTabs = tabs.find(tab => tab.getAttribute('aria-label') === saved?.group);
    if (saved?.target === pageKey && targetTabs && Date.now() - saved.at < 30000
        && Number.isFinite(saved.y) && Number.isFinite(saved.left)) {
        let interrupted = false;
        const restore = () => {
            if (interrupted) return;
            window.scrollTo({ top: saved.y, behavior: 'instant' });
            const strip = targetTabs.querySelector('.container');
            if (strip) strip.scrollLeft = saved.left;
        };

        // Restore before painting, then account for images that finish loading.
        restore();
        window.addEventListener('load', restore, { once: true });
        for (const event of ['wheel', 'touchstart', 'pointerdown', 'keydown']) {
            window.addEventListener(event, () => { interrupted = true; }, { once: true, passive: true });
        }
    }

    document.addEventListener('click', event => {
        const link = event.target.closest?.('.section-tabs a[href]');
        if (!link || event.defaultPrevented || event.button !== 0
            || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey
            || link.hasAttribute('download') || (link.target && link.target !== '_self')) return;

        const url = new URL(link.href, location.href);
        if (url.origin !== location.origin || url.hash) return;

        const group = link.closest('.section-tabs');
        try {
            sessionStorage.setItem(storageKey, JSON.stringify({
                target: url.pathname + url.search,
                group: group.getAttribute('aria-label'),
                y: window.scrollY,
                left: group.querySelector('.container')?.scrollLeft || 0,
                at: Date.now(),
            }));
        } catch (_) {
            // Keep ordinary links usable if storage is blocked.
        }
    });
})();
</script>
