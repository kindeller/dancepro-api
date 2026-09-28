(async () => {
    const form = document.getElementById('invoice-payment');
    if (!form || form.dataset.mode !== 'braintree') return;
    const button = document.getElementById('pay-invoice');
    const error = document.getElementById('payment-error');
    let submitting = false;
    try {
        const response = await fetch(form.dataset.tokenUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' });
        if (!response.ok) throw new Error('setup');
        const { token } = await response.json();
        const client = await braintree.client.create({ authorization: token });
        const fields = await braintree.hostedFields.create({ client, fields: { number: { selector: '#card-number' }, expirationDate: { selector: '#card-expiry' }, cvv: { selector: '#card-cvv' } }, styles: { input: { 'font-size': '18px', color: '#12212a' } } });
        const verification = await braintree.threeDSecure.create({ client, version: 2 });
        button.disabled = false;
        form.addEventListener('submit', async event => {
            event.preventDefault();
            if (submitting) return;
            submitting = true; button.disabled = true; error.textContent = '';
            try {
                const card = await fields.tokenize();
                const verified = await verification.verifyCard({ amount: (Number(form.elements.balance_cents.value) / 100).toFixed(2), nonce: card.nonce, bin: card.details.bin, collectDeviceData: true, onLookupComplete: (_data, next) => next() });
                document.getElementById('payment-nonce').value = verified.nonce;
                HTMLFormElement.prototype.submit.call(form);
            } catch (_) {
                error.textContent = 'Card verification was not completed. Check your details and try again.';
                submitting = false; button.disabled = false;
            }
        });
    } catch (_) {
        error.textContent = 'Card payments could not be loaded. Refresh or contact DancePro.';
    }
})();
