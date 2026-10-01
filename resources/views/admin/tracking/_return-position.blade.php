<script nonce="{{ request()->attributes->get('csp_nonce') }}">
(() => {
    const trackerPath = new URL(@json(route('admin.concert-tracker.index')), location.href).pathname;
    const positionKey = 'dancepro.concert-tracker.position';
    const returnKey = 'dancepro.concert-tracker.return';
    const shell = document.getElementById('tracker-sheet');
    const readPosition = () => {
        try {
            const saved = JSON.parse(sessionStorage.getItem(positionKey) || 'null');
            const url = new URL(saved?.url, location.href);
            if (url.origin === location.origin && url.pathname === trackerPath
                && Date.now() - saved.at < 8 * 60 * 60 * 1000
                && [saved.y, saved.top, saved.left].every(Number.isFinite)) return saved;
        } catch (_) { /* Keep normal navigation available without browser storage. */ }
        return null;
    };
    const updateReturnLink = link => {
        const url = new URL(link.href, location.href);
        if (url.origin !== location.origin || url.pathname !== trackerPath) return null;
        const saved = readPosition();
        if (saved) link.href = saved.url;
        return saved;
    };
    document.querySelectorAll('[data-tracker-return]').forEach(updateReturnLink);
    document.addEventListener('click', event => {
        const link = event.target.closest('a[href]');
        if (!link || event.defaultPrevented || event.button !== 0) return;
        if (link.matches('[data-tracker-return]')) {
            const saved = updateReturnLink(link);
            if (saved) {
                try { sessionStorage.setItem(returnKey, saved.url); } catch (_) {}
            }
            return;
        }
        if (!shell?.contains(link) || location.pathname !== trackerPath) return;
        const url = new URL(link.href, location.href);
        if (url.origin !== location.origin || url.pathname === trackerPath || link.hasAttribute('download')) return;
        const table = shell.querySelector('.tracker-table-wrap');
        if (!table) return;
        try {
            sessionStorage.setItem(positionKey, JSON.stringify({
                url: location.href, y: window.scrollY, top: table.scrollTop, left: table.scrollLeft,
                expanded: document.fullscreenElement === shell || shell.classList.contains('is-expanded'),
                at: Date.now(),
            }));
            sessionStorage.removeItem(returnKey);
        } catch (_) { /* Opening the task still works when storage is unavailable. */ }
    }, true);

    if (!shell || location.pathname !== trackerPath) return;
    const saved = readPosition();
    let returning;
    try {
        returning = sessionStorage.getItem(returnKey);
        sessionStorage.removeItem(returnKey);
    } catch (_) {}
    if (!saved || returning !== saved.url || saved.url !== location.href) return;
    const table = shell.querySelector('.tracker-table-wrap');
    if (!table) return;
    if (saved.expanded) {
        // Browser fullscreen needs a fresh gesture; use the existing expanded-page mode on return.
        shell.classList.add('is-expanded');
        document.body.style.overflow = 'hidden';
        document.dispatchEvent(new Event('fullscreenchange'));
    }
    let interrupted = false;
    const restore = () => {
        if (interrupted) return;
        window.scrollTo({top: saved.y, behavior: 'instant'});
        table.scrollTop = saved.top;
        table.scrollLeft = saved.left;
    };
    restore();
    window.addEventListener('load', restore, {once: true});
    for (const event of ['wheel', 'touchstart', 'pointerdown', 'keydown']) {
        window.addEventListener(event, () => { interrupted = true; }, {once: true, passive: true});
    }
})();
</script>
