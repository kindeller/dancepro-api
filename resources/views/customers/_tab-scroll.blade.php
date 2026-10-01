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

    const tabSelector = '.section-tabs, [data-preserve-scroll]';
    const tabs = [...document.querySelectorAll(tabSelector)];
    const targetTabs = tabs.find(tab => tab.getAttribute('aria-label') === saved?.group);
    const sameFormPage = saved?.scopeId && saved.targetPath === location.pathname
        && saved.tab === (new URL(location.href).searchParams.get('tab') || 'events');
    if ((saved?.target === pageKey || sameFormPage) && targetTabs && Date.now() - saved.at < 30000
        && Number.isFinite(saved.y) && Number.isFinite(saved.left)) {
        const formScope = saved.scopeId && document.getElementById(saved.scopeId);
        const form = (saved.formId && document.getElementById(saved.formId))
            || (formScope && [...formScope.querySelectorAll('form')][saved.formIndex]);
        if (form && Array.isArray(saved.openDetails)) {
            [...form.querySelectorAll('details')].forEach((details, index) => {
                if (saved.openDetails.includes(index) && !details.hidden) details.open = true;
            });
        }
        const detailsScope = saved.detailsScope && document.getElementById(saved.detailsScope);
        if (detailsScope && Array.isArray(saved.detailsState)) {
            const panels = [...detailsScope.querySelectorAll('details')];
            saved.detailsState.forEach((state, index) => {
                const details = state.id ? document.getElementById(state.id) : panels[index];
                if (details && detailsScope.contains(details) && !details.hidden) details.open = state.open;
            });
        }
        if (form && formScope) {
            const error = formScope.querySelector('[data-form-save-error]')?.textContent.trim();
            const message = formScope.querySelector('[data-form-save-status]')?.textContent.trim() || saved.successMessage;
            if (error || message) {
                const feedback = document.createElement('p');
                feedback.className = 'form-save-feedback' + (error ? ' error' : '');
                feedback.setAttribute('role', error ? 'alert' : 'status');
                feedback.textContent = error ? 'Not saved: ' + error : '✓ Saved. ' + message;
                const button = form.querySelector('button:not([type]), button[type="submit"], input[type="submit"]');
                if (button && !button.closest('[hidden]')) button.before(feedback);
                else form.append(feedback);
                if (!error) {
                    const clearFeedback = () => feedback.remove();
                    form.addEventListener('input', clearFeedback, { once: true });
                    form.addEventListener('change', clearFeedback, { once: true });
                }
            }
        }
        let interrupted = false;
        const restore = () => {
            if (interrupted) return;
            window.scrollTo({ top: saved.y, behavior: 'instant' });
            const strip = targetTabs.querySelector('.container') || targetTabs;
            if (strip) strip.scrollLeft = saved.left;
        };

        // Restore before painting, then account for images that finish loading.
        restore();
        window.addEventListener('load', restore, { once: true });
        for (const event of ['wheel', 'touchstart', 'pointerdown', 'keydown']) {
            window.addEventListener(event, () => { interrupted = true; }, { once: true, passive: true });
        }
    }

    const preserveFormScroll = event => {
        const form = event.target;
        if (event.defaultPrevented || !form.matches('form')) return;
        const scope = form.closest('[data-preserve-scroll-forms]');
        if (!form.hasAttribute('data-preserve-scroll-form') && !scope) return;
        const url = new URL(form.dataset.preserveScrollForm || location.href, location.href);
        const groupName = form.dataset.scrollGroup || scope?.dataset.scrollGroup;
        const group = tabs.find(tab => tab.getAttribute('aria-label') === groupName);
        if (url.origin !== location.origin || !group) return;

        try {
            sessionStorage.setItem(storageKey, JSON.stringify({
                target: url.pathname + url.search,
                group: groupName,
                scopeId: scope?.id,
                targetPath: url.pathname,
                tab: url.searchParams.get('tab') || 'events',
                y: window.scrollY,
                left: (group.querySelector('.container') || group).scrollLeft,
                at: Date.now(),
                formId: form.id,
                formIndex: scope ? [...scope.querySelectorAll('form')].indexOf(form) : null,
                successMessage: event.type === 'dancepro:before-reload' ? event.detail?.message : null,
                detailsScope: form.dataset.scrollDetailsScope || scope?.id,
                detailsState: [...(document.getElementById(form.dataset.scrollDetailsScope || scope?.id)?.querySelectorAll('details') || [])]
                    .map(details => ({ id: details.id, open: details.open })),
                openDetails: [...form.querySelectorAll('details')]
                    .flatMap((details, index) => details.open ? [index] : []),
            }));
        } catch (_) {
            // Saving still works when browser storage is unavailable.
        }
    };
    document.addEventListener('submit', preserveFormScroll);
    document.addEventListener('dancepro:before-reload', preserveFormScroll);

    document.addEventListener('click', event => {
        const link = event.target.closest?.('a[href]');
        if (!link || event.defaultPrevented || event.button !== 0
            || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey
            || link.hasAttribute('download') || (link.target && link.target !== '_self')) return;

        const group = link.closest(tabSelector);
        if (!group) return;

        const url = new URL(link.href, location.href);
        if (url.origin !== location.origin || url.hash) return;

        try {
            sessionStorage.setItem(storageKey, JSON.stringify({
                target: url.pathname + url.search,
                group: group.getAttribute('aria-label'),
                y: window.scrollY,
                left: (group.querySelector('.container') || group).scrollLeft,
                at: Date.now(),
            }));
        } catch (_) {
            // Keep ordinary links usable if storage is blocked.
        }
    });
})();
</script>
