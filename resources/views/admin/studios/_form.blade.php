@php
    $studio = $studio ?? null;
    $defaults = $defaults ?? [];
    $contactRows = old('contacts', $studio?->contacts?->map(fn ($contact) => [
        'name' => $contact->name,
        'role' => $contact->role,
        'emails' => $contact->emailString(),
        'phone' => $contact->phone,
    ])->all() ?? ($defaults['contacts'] ?? []));
    $contactRows = count($contactRows) ? $contactRows : [['name' => '', 'role' => '', 'emails' => '', 'phone' => '']];
@endphp
<div class="grid two-col">
    <div class="grid">
        <label>Name<input name="name" value="{{ old('name', $studio?->name ?? ($defaults['name'] ?? '')) }}" maxlength="255" required></label>
        <label>Studio code<input name="code" value="{{ old('code', $studio?->code ?? ($defaults['code'] ?? '')) }}" maxlength="50" pattern="[A-Za-z0-9][A-Za-z0-9_-]*" placeholder="e.g. FCD"><span class="muted">Optional unique internal code. Letters are saved in uppercase.</span></label>
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
