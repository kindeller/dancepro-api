@php
    $studio = $studio ?? null;
    $defaults = $defaults ?? [];
    $contactRows = old('contacts', $studio?->contacts?->map(fn ($contact) => [
        'name' => $contact->name,
        'role' => $contact->role,
        'emails' => $contact->emailString(),
        'phone' => $contact->phone,
        'account_uuid' => $contact->user?->uuid,
        'account_label' => $contact->user ? $contact->user->name.' · '.$contact->user->email : '',
    ])->all() ?? ($defaults['contacts'] ?? []));
    $contactRows = count($contactRows) ? $contactRows : [['name' => '', 'role' => '', 'emails' => '', 'phone' => '']];
@endphp
<div class="grid two-col">
    <div class="grid">
        <label>Name<input name="name" value="{{ old('name', $studio?->name ?? ($defaults['name'] ?? '')) }}" maxlength="255" required></label>
        <label>Organisation type<select name="is_school"><option value="0" @selected(!old('is_school', $studio?->is_school ?? false))>Dance studio</option><option value="1" @selected(old('is_school', $studio?->is_school ?? false))>School</option></select><span class="muted">Identify schools for school-specific pricing and features. This setting does not change existing quotes or invoices.</span></label>
        <label>Studio code<input name="code" value="{{ old('code', $studio?->code ?? ($defaults['code'] ?? '')) }}" maxlength="50" pattern="[A-Za-z0-9][A-Za-z0-9_-]*" placeholder="e.g. FCD"><span class="muted">One code for studio membership and released concert photo galleries. Letters are saved in uppercase. Competition event codes and concert video codes remain separate.</span></label>
        <label>Slug<input name="slug" value="{{ old('slug', $studio?->slug ?? ($defaults['slug'] ?? '')) }}" maxlength="255" placeholder="Generated automatically when blank"></label>
        @if($studio)
            <input type="hidden" name="status" value="{{ old('status', $studio->status->value) }}">
        @else
            <label>Status<select name="status" required>@foreach($statuses as $status)<option value="{{ $status->value }}" @selected(old('status', $defaults['status'] ?? 'active') === $status->value)>{{ ucfirst($status->value) }}</option>@endforeach</select></label>
        @endif
        <label>Brand colour<input name="brand_color" value="{{ old('brand_color', $studio?->brand_color ?? ($defaults['brand_color'] ?? '')) }}" placeholder="#0AA0DB" pattern="#[0-9A-Fa-f]{6}"></label>
        <label>Cover image URL<input type="url" name="cover_image_url" value="{{ old('cover_image_url', $studio?->cover_image_url ?? ($defaults['cover_image_url'] ?? '')) }}" placeholder="https://…"></label>
        @if($studio?->logo_path)<img class="entity-logo" src="{{ $studio->logoUrl() }}" alt="{{ $studio->name }} logo">@endif
        <label>Studio logo<input type="file" name="logo" accept="image/jpeg,image/png,image/webp"><span class="muted">JPG, PNG or WebP. The full logo will be shown without cropping.</span></label>
    </div>
    <div class="grid">
        <label>Description<textarea name="description" style="min-height:120px;font-family:inherit">{{ old('description', $studio?->description ?? ($defaults['description'] ?? '')) }}</textarea></label>
        <label>Internal notes<textarea name="notes" style="min-height:100px">{{ old('notes', $studio?->notes ?? ($defaults['notes'] ?? '')) }}</textarea></label>
    </div>
</div>
<section style="margin-top:24px">
    <div class="toolbar">
        <div><h2>Studio staff</h2><div class="muted">Add as many studio contacts as needed. Separate multiple email addresses with commas.</div></div>
        <button class="secondary" id="add-studio-contact" type="button">Add staff member</button>
    </div>
    <div class="grid" id="studio-contact-rows">
        @foreach($contactRows as $index => $contact)
            <div class="card card-pad studio-contact-row" style="background:var(--surface-subtle, #f8fafc)">
                <div class="toolbar"><strong class="studio-contact-number">Staff member {{ $loop->iteration }}</strong><button class="button secondary remove-studio-contact" type="button">Remove</button></div>
                @include('admin.studios._account-picker', ['index' => $index, 'contact' => $contact])
                <div class="grid two-col">
                    <label>Name<input name="contacts[{{ $index }}][name]" value="{{ $contact['name'] ?? '' }}" maxlength="255"></label>
                    <label>Role / position<input name="contacts[{{ $index }}][role]" value="{{ $contact['role'] ?? '' }}" maxlength="255" placeholder="e.g. Studio owner"></label>
                    <label>Email addresses<input name="contacts[{{ $index }}][emails]" value="{{ $contact['emails'] ?? '' }}" maxlength="2000" placeholder="name@example.com, accounts@example.com"><span class="muted">Use a comma between addresses. Future messages to this staff member will include every valid address listed here.</span></label>
                    <label>Phone<input name="contacts[{{ $index }}][phone]" value="{{ $contact['phone'] ?? '' }}" maxlength="50"></label>
                </div>
            </div>
        @endforeach
    </div>
</section>
<div style="margin-top:16px"><button type="submit">{{ $submitLabel }}</button> <a class="button secondary" href="{{ $cancelUrl ?? route('admin.studios.index') }}">Cancel</a></div>

<template id="studio-contact-template">
    <div class="card card-pad studio-contact-row" style="background:var(--surface-subtle, #f8fafc)">
        <div class="toolbar"><strong class="studio-contact-number">Staff member</strong><button class="button secondary remove-studio-contact" type="button">Remove</button></div>
        @include('admin.studios._account-picker', ['index' => '__INDEX__', 'contact' => []])
        <div class="grid two-col">
            <label>Name<input name="contacts[__INDEX__][name]" maxlength="255"></label>
            <label>Role / position<input name="contacts[__INDEX__][role]" maxlength="255" placeholder="e.g. Studio owner"></label>
            <label>Email addresses<input name="contacts[__INDEX__][emails]" maxlength="2000" placeholder="name@example.com, accounts@example.com"><span class="muted">Use a comma between addresses. Future messages to this staff member will include every valid address listed here.</span></label>
            <label>Phone<input name="contacts[__INDEX__][phone]" maxlength="50"></label>
        </div>
    </div>
</template>

@push('scripts')
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
(() => {
    const rows = document.getElementById('studio-contact-rows');
    const template = document.getElementById('studio-contact-template');
    let nextIndex = {{ count($contactRows) }};
    const searches = new WeakMap();
    const closeResults = picker => {
        picker.querySelector('.studio-account-results').hidden = true;
        picker.querySelector('.studio-account-search').setAttribute('aria-expanded', 'false');
    };
    rows.addEventListener('input', event => {
        if (!event.target.matches('.studio-account-search')) return;
        const input = event.target, picker = input.closest('.studio-account-picker');
        const previous = searches.get(input);
        clearTimeout(previous?.timer);
        previous?.request?.abort();
        picker.querySelector('.studio-account-uuid').value = '';
        picker.querySelector('.studio-account-access').value = '';
        closeResults(picker);
        const status = picker.querySelector('.studio-account-search-status');
        const term = input.value.trim();
        if (term.length < 2) { status.textContent = 'Type at least 2 characters to search.'; return; }
        status.textContent = 'Searching…';
        const state = {request: new AbortController()};
        searches.set(input, state);
        state.timer = setTimeout(async () => {
            try {
                const response = await fetch(`{{ route('admin.customers.search') }}?q=${encodeURIComponent(term)}`, {headers:{Accept:'application/json'}, signal:state.request.signal});
                if (!response.ok) throw new Error('Search failed');
                const customers = (await response.json()).data || [];
                if (state.request.signal.aborted || input.value.trim() !== term || !input.isConnected) return;
                const results = picker.querySelector('.studio-account-results');
                results.replaceChildren();
                customers.forEach(customer => {
                    const option = document.createElement('button');
                    option.type = 'button'; option.className = 'studio-account-result secondary';
                    const title = document.createElement('strong'); title.textContent = `${customer.name} · ${customer.email}`;
                    const id = document.createElement('span'); id.textContent = `Account ID: ${customer.uuid}`;
                    option.append(title, id);
                    option.addEventListener('click', () => {
                        state.request.abort();
                        const row = picker.closest('.studio-contact-row');
                        input.value = `${customer.name} · ${customer.email}`;
                        picker.querySelector('.studio-account-uuid').value = customer.uuid;
                        for (const [field, value] of Object.entries({name:customer.name, emails:customer.email, phone:customer.phone || ''})) {
                            row.querySelector(`input[name$="[${field}]"]`).value = value;
                        }
                        status.textContent = 'Account selected. Choose Studio Hub access below if needed.';
                        closeResults(picker);
                        picker.querySelector('.studio-account-access').focus();
                    });
                    results.append(option);
                });
                status.textContent = customers.length ? `${customers.length} matching account(s).` : 'No active customer accounts found. You can still enter contact details manually.';
                results.hidden = !customers.length;
                input.setAttribute('aria-expanded', customers.length ? 'true' : 'false');
            } catch(error) {
                if (error.name !== 'AbortError') status.textContent = 'Customer search is unavailable. Please try again.';
            }
        }, 220);
    });
    rows.addEventListener('keydown', event => {
        const picker = event.target.closest('.studio-account-picker');
        if (!picker) return;
        const results = picker.querySelector('.studio-account-results');
        if (event.key === 'Escape') { closeResults(picker); picker.querySelector('input[type="search"]').focus(); }
        if (results.hidden) return;
        const options = [...results.querySelectorAll('button')];
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            const direction = event.key === 'ArrowDown' ? 1 : -1;
            const current = options.indexOf(document.activeElement);
            options[(current + direction + options.length) % options.length]?.focus();
        }
        if (event.key === 'Enter' && event.target.matches('.studio-account-search')) { event.preventDefault(); options[0]?.click(); }
    });
    document.addEventListener('click', event => rows.querySelectorAll('.studio-account-picker').forEach(picker => {
        if (!picker.contains(event.target)) closeResults(picker);
    }));
    const renumber = () => rows.querySelectorAll('.studio-contact-number').forEach((node, index) => node.textContent = `Staff member ${index + 1}`);

    document.getElementById('add-studio-contact').addEventListener('click', () => {
        rows.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', nextIndex++));
        renumber();
    });
    rows.addEventListener('click', event => {
        if (!event.target.classList.contains('remove-studio-contact')) return;
        event.target.closest('.studio-contact-row').remove();
        if (!rows.querySelector('.studio-contact-row')) document.getElementById('add-studio-contact').click();
        renumber();
    });
})();
</script>
@endpush

@push('styles')
<style>
.studio-account-results{margin:8px 0;border:1px solid var(--line);border-radius:8px;max-height:300px;overflow-y:auto;background:white}
.studio-account-result{display:block;width:100%;text-align:left;white-space:normal;overflow-wrap:anywhere;border:0;border-bottom:1px solid var(--line);border-radius:0;padding:12px}
.studio-account-result span{display:block;font-size:12px;font-weight:400;margin-top:4px}.studio-account-result:focus{outline:2px solid var(--brand);outline-offset:-2px}
</style>
@endpush
