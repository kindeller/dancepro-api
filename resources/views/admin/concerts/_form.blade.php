@php($concert = $concert ?? null)
<div class="grid two-col">
    <div class="grid">
        <label>Studio<select name="studio_id" required><option value="">Choose a studio</option>@foreach($studios as $studio)<option value="{{ $studio->id }}" @selected((string)old('studio_id', $concert?->studio_id ?? $selectedStudioId ?? null) === (string)$studio->id)>{{ $studio->name }}@if($studio->status->value !== 'active') ({{ $studio->status->value }})@endif</option>@endforeach</select></label>
        <label>Concert name<input name="name" value="{{ old('name', $concert?->name) }}" maxlength="255" required></label>
        <label>Slug<input name="slug" value="{{ old('slug', $concert?->slug) }}" maxlength="255" placeholder="Generated automatically when blank"></label>
        <label>Status<select name="status" required>@foreach($statuses as $status)<option value="{{ $status->value }}" @selected(old('status', $concert?->status?->value ?? 'draft') === $status->value)>{{ ucfirst($status->value) }}@if($status->value === 'published') (released)@endif</option>@endforeach</select></label>
        <label>Event date<input type="date" name="event_date" value="{{ old('event_date', $concert?->event_date?->format('Y-m-d')) }}"></label>
        <label>Event end date<input type="date" name="event_end_date" value="{{ old('event_end_date', $concert?->event_end_date?->format('Y-m-d')) }}"></label>
        <label>Venue<input name="venue_name" value="{{ old('venue_name', $concert?->venue_name) }}" maxlength="255"></label>
        <label>Description<textarea name="description" style="min-height:130px;font-family:inherit">{{ old('description', $concert?->description) }}</textarea></label>
    </div>
    <div class="grid">
        <div class="card card-pad" style="box-shadow:none">
            <h3 style="margin-bottom:12px">Release controls</h3>
            <input type="hidden" name="is_enabled" value="0"><label style="display:flex;grid-template-columns:auto 1fr;align-items:center"><input type="checkbox" name="is_enabled" value="1" style="width:auto;min-height:auto" @checked((bool)old('is_enabled', $concert?->is_enabled ?? true))> Enabled for customer access</label>
            <input type="hidden" name="requires_approval" value="0"><label style="display:flex;grid-template-columns:auto 1fr;align-items:center"><input type="checkbox" name="requires_approval" value="1" style="width:auto;min-height:auto" @checked((bool)old('requires_approval', $concert?->requires_approval ?? false))> Require staff approval before release</label>
            <input type="hidden" name="is_approved" value="0"><label style="display:flex;grid-template-columns:auto 1fr;align-items:center"><input type="checkbox" name="is_approved" value="1" style="width:auto;min-height:auto" @checked((bool)old('is_approved', $concert?->approved_at !== null))> Approved for release</label>
            <p class="muted" style="margin:8px 0 0">A concert must also be published, enabled, approved when required, and inside its availability window to appear publicly.</p>
        </div>
        <label>Available from<input type="datetime-local" name="available_from" value="{{ old('available_from', $concert?->available_from?->format('Y-m-d\TH:i')) }}"></label>
        <label>Available until<input type="datetime-local" name="available_until" value="{{ old('available_until', $concert?->available_until?->format('Y-m-d\TH:i')) }}"></label>
        <label>Concert password<input type="password" name="access_password" minlength="6" autocomplete="new-password" placeholder="{{ $concert?->requiresPassword() ? 'Leave blank to keep current password' : 'Leave blank for open access' }}"></label>
        @if($concert?->requiresPassword())<input type="hidden" name="clear_access_password" value="0"><label style="display:flex;grid-template-columns:auto 1fr;align-items:center"><input type="checkbox" name="clear_access_password" value="1" style="width:auto;min-height:auto"> Remove current concert password</label>@endif
        <label>Brand colour<input name="brand_color" value="{{ old('brand_color', $concert?->brand_color) }}" placeholder="#0AA0DB" pattern="#[0-9A-Fa-f]{6}"></label>
        <label>Cover image URL<input type="url" name="cover_image_url" value="{{ old('cover_image_url', $concert?->cover_image_url) }}" placeholder="https://…"></label>
        <label>Program URL<input type="url" name="program_url" value="{{ old('program_url', $concert?->program_url) }}" placeholder="https://…"></label>
        <label>External gallery URL<input type="url" name="external_gallery_url" value="{{ old('external_gallery_url', $concert?->external_gallery_url) }}" placeholder="https://…"></label>
        <label>Internal notes<textarea name="notes" style="min-height:100px">{{ old('notes', $concert?->notes) }}</textarea></label>
    </div>
</div>
@if($concert)
<div class="notice" style="margin-top:16px"><strong>Media storage is managed separately.</strong><br><span class="muted">Disk: {{ $concert->storage_disk }} · Prefix: {{ $concert->storage_prefix }} · Collections: {{ $concert->mediaCollections()->count() }}</span></div>
@endif
<div style="margin-top:16px"><button type="submit">{{ $submitLabel }}</button> <a class="button secondary" href="{{ route('admin.concerts.index') }}">Cancel</a>@if($concert?->isPubliclyAvailable()) <a class="button secondary" href="{{ route('concerts.show', $concert) }}" target="_blank" rel="noopener">View public page</a>@endif</div>
