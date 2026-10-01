<script nonce="{{ request()->attributes->get('csp_nonce') }}">
(() => {
    const feedback = document.querySelector('[data-notification-feedback]');
    const pending = new WeakSet();
    let queue = Promise.resolve();
    const markRead = row => {
        const form = row.querySelector('[data-notification-read-form]');
        const button = row.querySelector('[data-notification-read]');
        if (!form || !row.classList.contains('unread') || pending.has(row)) return;
        pending.add(row);
        button.disabled = true;
        // Keep responses ordered so simultaneous reads cannot restore stale badge counts.
        queue = queue.then(async () => {
            try {
                const response = await fetch(form.action, {
                    method: 'POST', body: new FormData(form), credentials: 'same-origin',
                    headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                });
                if (!response.ok) throw new Error('Read status was not saved.');
                const result = await response.json();
                if (!result.success || !result.data?.counts) throw new Error('Read status was not saved.');
                row.classList.remove('unread');
                const status = row.querySelector('.notification-status');
                status.textContent = 'Read';
                status.classList.remove('is-unread');
                if (document.activeElement === button) row.querySelector('summary').focus();
                button.hidden = true;
                const descriptions = {total: 'unread messages and notifications', notifications: 'unread notifications', conversations: 'unread conversations', studio: 'unread studio messages'};
                document.querySelectorAll('[data-unread-count]').forEach(badge => {
                    const key = badge.dataset.unreadCount, count = result.data.counts[key];
                    if (!Number.isInteger(count)) return;
                    if (count === 0 && !badge.hasAttribute('data-unread-keep-zero')) badge.remove();
                    else {
                        badge.textContent = count;
                        badge.setAttribute('aria-label', `${count} ${descriptions[key]}`);
                    }
                });
                if (feedback) feedback.hidden = true;
            } catch (_) {
                if (feedback) {
                    feedback.textContent = 'Could not mark the notification as read. Please try the Mark as read button again.';
                    feedback.hidden = false;
                }
            } finally {
                pending.delete(row);
                button.disabled = false;
            }
        });
    };
    document.querySelectorAll('.notification-row').forEach(row => {
        row.addEventListener('toggle', () => { if (row.open) markRead(row); });
        row.querySelector('[data-notification-read]')?.addEventListener('click', event => event.stopPropagation());
        row.querySelector('[data-notification-read-form]')?.addEventListener('submit', event => {
            event.preventDefault();
            markRead(row);
        });
    });
})();
</script>
