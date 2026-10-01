document.querySelectorAll('[data-bank-copy]').forEach((button) => {
    button.addEventListener('click', async () => {
        const row = button.closest('[data-bank-copy-field]');
        const input = row.querySelector('input');
        const status = row.querySelector('[role="status"]');
        const label = input.getAttribute('aria-label');
        status.textContent = '';
        status.removeAttribute('data-error');
        button.disabled = true;
        let copied = false;

        try {
            await navigator.clipboard.writeText(input.value);
            copied = true;
        } catch {
            // Also supports local HTTP testing and browsers without clipboard access.
            const x = window.scrollX;
            const y = window.scrollY;
            input.focus({ preventScroll: true });
            input.select();
            input.setSelectionRange(0, input.value.length);
            try {
                copied = document.execCommand('copy');
            } catch {
                copied = false;
            }
            window.scrollTo(x, y);
        }

        if (copied) {
            button.textContent = 'Copied ✓';
            status.textContent = `${label} copied.`;
            window.setTimeout(() => { button.textContent = 'Copy'; }, 2500);
        } else {
            status.setAttribute('data-error', '');
            status.textContent = `Select and copy the ${label.toLowerCase()} above.`;
        }
        button.disabled = false;
    });
});
