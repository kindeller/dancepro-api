<form method="POST" action="{{ route('customer.profile.store', ['tab' => 'studios']) }}" data-studio-search="{{ route('customer.profile.studios.search') }}">@csrf
    <input type="hidden" name="action" value="studio">
    <input type="hidden" name="studio_uuid" value="{{ old('studio_uuid') }}" data-studio-id>
    <div class="profile-fields"><div>
    <label>Find your studio<input type="search" required maxlength="255" autocomplete="off" placeholder="Start typing your studio name" value="{{ $selectedStudio?->name }}" data-studio-query aria-describedby="studio-search-status" aria-controls="studio-search-results"></label>
    <p class="meta" id="studio-search-status" role="status">Type at least two letters, then choose your studio.</p>
    <div id="studio-search-results" class="studio-search-results" aria-label="Matching studios" hidden></div>
    </div><div>
    <label>Studio code<input name="studio_code" required maxlength="50" autocomplete="off" autocapitalize="characters"></label>
    <p class="meta">Your studio code adds your studio and unlocks its concert photos when released. You can add it before any galleries exist. Competitions require their event code; concert videos use the video code supplied after studio release.</p>
    </div></div>
    <div><button class="button">Add studio</button></div>
</form>
<details class="profile-studio-manager" style="margin-top:20px" @if(old('action') === 'studio_request') open @endif>
    <summary>Studio not listed, or no studio code?</summary>
    <p>Tell us the studio name and we’ll confirm its details. Your request stays on this account, and we’ll attach the approved studio for you.</p>
    <form method="POST" action="{{ route('customer.profile.store', ['tab' => 'studios']) }}">@csrf
        <input type="hidden" name="action" value="studio_request">
        <div class="profile-fields">
        <label>Studio name<input name="studio_name" required maxlength="255" value="{{ old('studio_name') }}" data-request-studio-name></label>
        <label>Suburb / city (optional)<input name="studio_location" maxlength="255" value="{{ old('studio_location') }}"></label>
        </div>
        <div><button class="button secondary">Submit studio for review</button></div>
    </form>
</details>
@push('styles')
<style>.studio-search-results { display:grid;gap:6px; }.studio-search-results[hidden] { display:none; }.studio-search-results button { text-align:left;display:flex;align-items:center;gap:12px;width:100%;white-space:normal; }.studio-search-results img { width:40px;height:40px;object-fit:contain; }.profile-section-status.pending { background:#e4f4fc;color:#087fab; }</style>
@endpush
@push('scripts')
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
(() => {
    const form = document.querySelector('[data-studio-search]');
    if (!form) return;
    const input = form.querySelector('[data-studio-query]'), id = form.querySelector('[data-studio-id]');
    const results = document.getElementById('studio-search-results'), status = document.getElementById('studio-search-status');
    let timer, controller, revision = 0;
    input.addEventListener('input', () => {
        const current = ++revision;
        id.value = ''; input.setCustomValidity('Choose a studio from the search results.');
        clearTimeout(timer); controller?.abort(); results.replaceChildren(); results.hidden = true;
        const q = input.value.trim();
        const requestName = document.querySelector('[data-request-studio-name]');
        if (requestName && !requestName.dataset.edited) requestName.value = q;
        if (q.length < 2) { status.textContent = 'Type at least two letters, then choose your studio.'; return; }
        status.textContent = 'Searching…';
        timer = setTimeout(async () => {
            controller = new AbortController();
            try {
                const response = await fetch(form.dataset.studioSearch + '?q=' + encodeURIComponent(q), {signal:controller.signal,headers:{Accept:'application/json'}});
                if (!response.ok) throw new Error('Search failed');
                const payload = await response.json();
                if (current !== revision) return;
                const studios = payload.data;
                status.textContent = studios.length ? 'Choose your studio below. Keep typing to narrow the results.' : 'No matching studios. Use “Studio not listed, or no studio code?” below.';
                studios.forEach(studio => {
                    const button = document.createElement('button'); button.type = 'button'; button.className = 'button secondary';
                    if (studio.logo) { const logo = document.createElement('img'); logo.src = studio.logo; logo.alt = ''; button.append(logo); }
                    const name = document.createElement('span'); name.textContent = studio.name; button.append(name);
                    button.addEventListener('click', () => { input.value = studio.name; id.value = studio.uuid; input.setCustomValidity(''); results.hidden = true; status.textContent = 'Selected: ' + studio.name; form.querySelector('[name="studio_code"]').focus(); });
                    results.append(button);
                });
                results.hidden = studios.length === 0;
            } catch (error) { if (error.name !== 'AbortError' && current === revision) status.textContent = 'Search is unavailable. Please try again, or submit your studio for review below.'; }
        }, 250);
    });
    input.addEventListener('keydown', event => { if (event.key === 'ArrowDown' && !results.hidden) { event.preventDefault(); results.querySelector('button')?.focus(); } });
    results.addEventListener('keydown', event => {
        const buttons = [...results.querySelectorAll('button')], index = buttons.indexOf(document.activeElement);
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') { event.preventDefault(); buttons[(index + (event.key === 'ArrowDown' ? 1 : -1) + buttons.length) % buttons.length]?.focus(); }
        if (event.key === 'Escape') { results.hidden = true; input.focus(); }
    });
    document.querySelector('[data-request-studio-name]')?.addEventListener('input', event => { event.target.dataset.edited = 'true'; });
    form.addEventListener('submit', event => { if (!id.value) { event.preventDefault(); input.setCustomValidity('Choose a studio from the search results.'); input.reportValidity(); } });
})();
</script>
@endpush
