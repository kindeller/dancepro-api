@php($studio = $studio ?? null)
<div class="grid two-col">
    <div class="grid">
        <label>Name<input name="name" value="{{ old('name', $studio?->name) }}" maxlength="255" required></label>
        <label>Slug<input name="slug" value="{{ old('slug', $studio?->slug) }}" maxlength="255" placeholder="Generated automatically when blank"></label>
        <label>Status<select name="status" required>@foreach($statuses as $status)<option value="{{ $status->value }}" @selected(old('status', $studio?->status?->value ?? 'active') === $status->value)>{{ ucfirst($status->value) }}</option>@endforeach</select></label>
        <label>Brand colour<input name="brand_color" value="{{ old('brand_color', $studio?->brand_color) }}" placeholder="#0AA0DB" pattern="#[0-9A-Fa-f]{6}"></label>
        <label>Cover image URL<input type="url" name="cover_image_url" value="{{ old('cover_image_url', $studio?->cover_image_url) }}" placeholder="https://…"></label>
    </div>
    <div class="grid">
        <label>Contact name<input name="contact_name" value="{{ old('contact_name', $studio?->contact_name) }}" maxlength="255"></label>
        <label>Contact email<input type="email" name="contact_email" value="{{ old('contact_email', $studio?->contact_email) }}" maxlength="255"></label>
        <label>Contact phone<input name="contact_phone" value="{{ old('contact_phone', $studio?->contact_phone) }}" maxlength="50"></label>
        <label>Description<textarea name="description" style="min-height:120px;font-family:inherit">{{ old('description', $studio?->description) }}</textarea></label>
        <label>Internal notes<textarea name="notes" style="min-height:100px">{{ old('notes', $studio?->notes) }}</textarea></label>
    </div>
</div>
<div style="margin-top:16px"><button type="submit">{{ $submitLabel }}</button> <a class="button secondary" href="{{ route('admin.studios.index') }}">Cancel</a></div>
