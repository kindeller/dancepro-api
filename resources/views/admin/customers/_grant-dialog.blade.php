<dialog id="complimentary-media-dialog" class="grant-dialog">
    <form method="POST" action="{{ route('admin.customers.complimentary-media.store') }}" class="grant-dialog-form">@csrf
        <div class="grant-dialog-heading"><div><h2>Add to account</h2><p class="muted" id="complimentary-media-label">Grant this media at no cost.</p></div><button type="button" class="secondary grant-dialog-close" data-close-grant aria-label="Close">×</button></div>
        <input type="hidden" name="source_kind" id="grant-source-kind">
        <input type="hidden" name="event_uuid" id="grant-event-uuid">
        <input type="hidden" name="source_uuid" id="grant-source-uuid">
        <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
        <label class="grant-customer-field">Customer name, email or account ID
            <input name="customer_lookup" value="{{ ($grantCustomer ?? null)?->email ?? '' }}" placeholder="Start typing a name, email or account ID" required autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="grant-customer-results">
            <div id="grant-customer-results" class="grant-customer-results" role="listbox" hidden></div>
        </label>
        <label id="grant-video-choice" class="grant-dialog-choice" style="display:none"><input type="checkbox" name="include_video" value="1"><span>Include video from this item</span></label>
        <label class="grant-dialog-choice"><input type="checkbox" name="notify_customer" value="1"><span><strong>Notify customer</strong><small>Email the customer to let them know this media was added.</small></span></label>
        <label>Internal reason or note <span class="muted">(optional)</span><textarea name="note" rows="3" maxlength="500" placeholder="Gift, customer service resolution, studio inclusion…"></textarea></label>
        <div class="grant-dialog-actions"><button type="submit">Add to account</button><button type="button" class="secondary" data-close-grant>Cancel</button></div>
    </form>
</dialog>
<style>
    .grant-dialog { width:min(600px,calc(100% - 32px));overflow:visible;border:0;border-radius:14px;padding:0;box-shadow:0 22px 70px rgba(16,24,32,.3); }
    .grant-dialog-form { display:grid;gap:18px;padding:26px; }
    .grant-dialog-heading { display:flex;justify-content:space-between;gap:20px;align-items:start; }
    .grant-dialog-heading h2,.grant-dialog-heading p { margin:0; }
    .grant-dialog-heading p { margin-top:7px; }
    .grant-dialog-close { width:42px;min-width:42px;height:42px;padding:0;font-size:22px; }
    .grant-customer-field { position:relative; }
    .grant-customer-results { position:absolute;z-index:20;top:calc(100% + 5px);right:0;left:0;max-height:280px;overflow:auto;border:1px solid var(--line);border-radius:8px;background:#fff;box-shadow:0 16px 36px rgba(16,24,32,.18); }
    .grant-customer-result { display:grid;width:100%;grid-template-columns:74px minmax(0,.7fr) minmax(0,1fr);gap:10px;min-height:0;border:0;border-bottom:1px solid var(--line);border-radius:0;background:#fff;color:var(--ink);padding:11px 12px;text-align:left; }
    .grant-customer-result:last-child { border-bottom:0; }
    .grant-customer-result:hover,.grant-customer-result:focus { background:var(--soft);color:var(--ink); }
    .grant-customer-result-id { color:var(--brand-strong);font-weight:850; }
    .grant-customer-result-name { overflow:hidden;font-weight:800;text-overflow:ellipsis;white-space:nowrap; }
    .grant-customer-result-email { overflow:hidden;color:var(--muted);text-overflow:ellipsis;white-space:nowrap; }
    .grant-customer-empty { margin:0;padding:12px;color:var(--muted);font-size:13px; }
    .grant-dialog-choice { display:flex;align-items:flex-start;gap:11px;padding:12px 14px;border:1px solid var(--line);border-radius:8px;background:var(--paper);cursor:pointer; }
    .grant-dialog-choice input { flex:0 0 18px;width:18px!important;height:18px!important;min-height:18px;margin:2px 0 0;padding:0;accent-color:var(--brand); }
    .grant-dialog-choice span { display:block;color:var(--ink);font-size:14px;font-weight:500; }
    .grant-dialog-choice strong,.grant-dialog-choice small { display:block; }
    .grant-dialog-choice small { margin-top:2px;color:var(--muted);font-size:12px; }
    .grant-dialog-form textarea { min-height:105px; }
    .grant-dialog-actions { display:flex;justify-content:flex-end;gap:10px; }
    dialog::backdrop { background:rgba(7,16,22,.62); }
    @media(max-width:600px){.grant-dialog-form{padding:20px}.grant-customer-result{grid-template-columns:65px 1fr}.grant-customer-result-email{grid-column:2}.grant-dialog-actions{flex-direction:column}.grant-dialog-actions button{width:100%}}
</style>
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
const grantCustomerInput = document.querySelector('#complimentary-media-dialog [name="customer_lookup"]');
const grantCustomerResults = document.getElementById('grant-customer-results');
let grantCustomerTimer;
let grantCustomerRequest;

const closeGrantCustomerResults = () => {
    if (! grantCustomerResults || ! grantCustomerInput) return;
    grantCustomerResults.hidden = true;
    grantCustomerInput.setAttribute('aria-expanded', 'false');
};

const showGrantCustomerResults = customers => {
    if (! grantCustomerResults || ! grantCustomerInput) return;
    grantCustomerResults.replaceChildren();
    if (! customers.length) {
        const empty = document.createElement('p');
        empty.className = 'grant-customer-empty';
        empty.textContent = 'No active customer accounts found.';
        grantCustomerResults.append(empty);
    }
    customers.forEach(customer => {
        const option = document.createElement('button');
        option.type = 'button';
        option.className = 'grant-customer-result';
        option.setAttribute('role', 'option');
        option.dataset.email = customer.email;
        [['grant-customer-result-id', `ID #${customer.id}`], ['grant-customer-result-name', customer.name], ['grant-customer-result-email', customer.email]].forEach(([className, value]) => {
            const part = document.createElement('span'); part.className = className; part.textContent = value; option.append(part);
        });
        grantCustomerResults.append(option);
    });
    grantCustomerResults.hidden = false;
    grantCustomerInput.setAttribute('aria-expanded', 'true');
};

grantCustomerInput?.addEventListener('input', () => {
    window.clearTimeout(grantCustomerTimer);
    grantCustomerRequest?.abort();
    const term = grantCustomerInput.value.trim();
    if (term.length < 2 && ! /^\d+$/.test(term)) { closeGrantCustomerResults(); return; }
    grantCustomerTimer = window.setTimeout(async () => {
        grantCustomerRequest = new AbortController();
        try {
            const response = await fetch(`{{ route('admin.customers.search') }}?q=${encodeURIComponent(term)}`, {headers:{Accept:'application/json'},signal:grantCustomerRequest.signal});
            if (! response.ok) throw new Error('Customer search failed');
            showGrantCustomerResults((await response.json()).data || []);
        } catch (error) {
            if (error.name !== 'AbortError') closeGrantCustomerResults();
        }
    }, 220);
});

grantCustomerResults?.addEventListener('click', event => {
    const option = event.target.closest('.grant-customer-result');
    if (! option) return;
    grantCustomerInput.value = option.dataset.email;
    closeGrantCustomerResults();
    grantCustomerInput.focus();
});

document.addEventListener('click', function (event) {
    const trigger = event.target.closest('[data-grant-media]');
    const dialog = document.getElementById('complimentary-media-dialog');
    if (trigger && dialog) {
        event.preventDefault(); event.stopPropagation();
        document.getElementById('grant-source-kind').value = trigger.dataset.sourceKind;
        document.getElementById('grant-event-uuid').value = trigger.dataset.eventUuid || '';
        document.getElementById('grant-source-uuid').value = trigger.dataset.sourceUuid;
        document.getElementById('complimentary-media-label').textContent = trigger.dataset.label || 'Grant this media at no cost.';
        const video = document.getElementById('grant-video-choice');
        video.style.display = trigger.dataset.sourceKind === 'competition_item' ? 'flex' : 'none';
        video.querySelector('input').checked = trigger.dataset.includeVideo === 'true';
        closeGrantCustomerResults(); dialog.showModal(); dialog.querySelector('[name="customer_lookup"]').focus();
    }
    if (event.target.closest('[data-close-grant]')) { closeGrantCustomerResults(); dialog?.close(); }
    if (! event.target.closest('.grant-customer-field')) closeGrantCustomerResults();
});
</script>
