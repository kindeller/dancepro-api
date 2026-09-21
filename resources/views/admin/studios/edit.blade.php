@extends('layouts.admin', ['title' => $studio->name, 'heading' => $studio->name, 'subheading' => $studio->code])
@section('content')
<div class="studio-page-controls">
    <a class="button secondary" href="{{ route('admin.studios.index') }}">← Back to studio contacts</a>
    <form class="studio-status-form" method="POST" action="{{ route('admin.studios.status.update', $studio) }}">
        @csrf @method('PATCH')
        <select id="studio-page-status" class="status-select status-{{ $studio->status->value }}" name="status" aria-label="Change studio status" onchange="this.form.submit()">
            @foreach($statuses as $status)<option value="{{ $status->value }}" @selected($studio->status === $status)>{{ ucfirst($status->value) }}</option>@endforeach
        </select>
    </form>
</div>
<form class="card card-pad" method="POST" action="{{ route('admin.studios.update', $studio) }}" enctype="multipart/form-data">@csrf @method('PUT')
    @include('admin.studios._form', ['submitLabel' => 'Save studio'])
</form>
<section class="card card-pad" style="margin-top:24px">
    <h2>Studio logo / cover image</h2>
    @if($studio->coverImageForDisplay())<p><img src="{{ $studio->coverImageForDisplay() }}" alt="Current studio cover" style="max-width:320px;width:100%;border-radius:8px"></p>@endif
    <form method="POST" action="{{ route('admin.branding.studios.cover.store', $studio) }}" enctype="multipart/form-data">@csrf
        <label>Image (JPG, PNG or WebP; up to 10 MB)<input type="file" name="file" accept="image/jpeg,image/png,image/webp" required></label>
        @if($studio->cover_image_storage_key)<label style="display:flex;align-items:flex-start;gap:8px"><input type="checkbox" name="replace_confirm" value="1" required style="width:auto;min-height:auto"><span><strong>Confirm replacement</strong><br>Replace the current object in {{ config('filesystems.disks.'.config('media.upload_disk').'.bucket') }} at <code>{{ $studio->cover_image_storage_key }}</code>. Earlier versions may remain if S3 Versioning is enabled.</span></label>@endif
        @error('replace_confirm')<p role="alert">{{ $message }}</p>@enderror
        @error('file')<p role="alert">{{ $message }}</p>@enderror
        <div class="actions"><button type="submit">{{ $studio->cover_image_url ? 'Replace cover' : 'Upload cover' }}</button>@if($studio->cover_image_url)<a class="button secondary" href="{{ route('admin.branding.studios.cover.confirm-delete', $studio) }}">Clear cover</a>@endif</div>
    </form>
</section>

<section style="margin-top:24px">
    <div class="toolbar">
        <div>
            <h2>Associated Concerts</h2>
            <div class="muted">{{ $studio->concerts->count() }} {{ Str::plural('concert', $studio->concerts->count()) }} assigned to {{ $studio->name }}.</div>
        </div>
        <a class="button" href="{{ route('admin.concerts.create', ['studio_id' => $studio->id]) }}">Add concert</a>
    </div>

    <div class="card">
        <table>
            <thead><tr><th>Concert</th><th>Release</th><th>Access</th><th>Media</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($studio->concerts as $concert)
                <tr>
                    <td><strong>{{ $concert->name }}</strong><div class="muted">{{ $concert->event_date?->format('j M Y') ?? 'Date not set' }}@if($concert->venue_name) · {{ $concert->venue_name }}@endif</div></td>
                    <td><span class="badge">{{ $concert->status->value }}</span><div class="muted">@if(!$concert->is_enabled) Disabled @elseif($concert->requires_approval && !$concert->approved_at) Awaiting approval @elseif($concert->isPubliclyAvailable()) Public @else Not public @endif</div></td>
                    <td>{{ $concert->requiresPassword() ? 'Password protected' : 'Open' }}</td>
                    <td>{{ $concert->media_collections_count }} {{ Str::plural('collection', $concert->media_collections_count) }}</td>
                    <td>
                        <div class="actions">
                            <a class="button secondary" href="{{ route('admin.concerts.edit', $concert) }}">Edit</a>
                            @if($concert->isPubliclyAvailable())
                                <a class="button secondary" href="{{ route('concerts.show', $concert) }}" target="_blank" rel="noopener">Visit</a>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No concerts are associated with this studio yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection

@push('styles')
<style>
    .studio-page-controls { display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:16px; }
    .studio-status-form { margin:0; }
    .status-select { width:auto; min-width:0; min-height:24px; border:0; border-radius:4px; padding:3px 8px; appearance:none; -webkit-appearance:none; background:var(--soft); color:var(--brand-strong); font:inherit; font-size:12px; font-weight:800; line-height:18px; text-align:left; text-transform:capitalize; cursor:pointer; }
    .status-inactive, .status-archived { background:#fef2f2; color:var(--danger); }
</style>
@endpush
