@extends('layouts.admin', ['title' => $event->name.' photos', 'heading' => 'Concert photos', 'subheading' => 'Manage studio galleries, event albums and photos.'])

@push('styles')
<style>
    .concert-album-card .backend-gallery-card-media { position:relative; }
    .concert-album-open { display:grid;width:100%;height:100%;place-items:center;color:inherit;text-decoration:none; }
    .concert-album-card .backend-gallery-card-body { display:grid;gap:7px; }
    .concert-album-card .backend-gallery-card-meta { margin:0; }
    .concert-album-footer { position:relative;border-top:1px solid var(--line);margin-top:auto; }
    .concert-album-view { position:absolute;left:16px;top:14px;color:var(--brand-strong);font-weight:750;text-decoration:none; }
    .concert-album-view:hover { text-decoration:none; }
    .concert-album-controls { margin:0;padding:0; }
    .concert-album-controls>summary { display:flex;align-items:center;justify-content:center;width:36px;height:36px;margin:8px 12px 8px auto;padding:0;border:1px solid var(--line);border-radius:4px;background:#f4f8fa;cursor:pointer;color:var(--brand-strong);list-style:none; }
    .concert-album-controls>summary::-webkit-details-marker { display:none; }
    .concert-album-controls>summary:hover,.concert-album-controls>summary:focus-visible { border-color:var(--brand);outline:2px solid var(--brand);outline-offset:2px; }
    .concert-album-controls>summary svg { width:20px;height:20px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round; }
    .concert-album-settings-panel { padding:8px 16px 16px;border-top:1px solid var(--line); }
    .concert-album-edit { display:grid;grid-template-columns:minmax(0,1fr) 92px;gap:12px;margin-top:14px; }
    .concert-album-edit .concert-album-save { grid-column:1/-1; }
    .album-cover-heading { margin:18px 0 4px; }
    .album-cover-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(94px,1fr));gap:8px;margin-top:10px; }
    .album-cover-option { position:relative;min-width:0; }
    .album-cover-option form { margin:0; }
    .album-cover-option form button { display:block;width:100%;padding:0;overflow:hidden;border:3px solid transparent;border-radius:8px;background:#101820;cursor:pointer; }
    .album-cover-option form button[aria-pressed="true"] { border-color:#168bc1; }
    .album-cover-option img { display:block;width:100%;aspect-ratio:4/3;object-fit:cover; }
    .concert-source-panel { margin-top:22px;padding:0;overflow:hidden; }
    .concert-source-panel>summary { display:flex;align-items:center;justify-content:space-between;gap:16px;padding:17px 20px;cursor:pointer;font-weight:850; }
    .concert-source-panel>summary span { color:var(--muted);font-size:13px;font-weight:650; }
    .concert-source-content { padding:0 20px 20px;border-top:1px solid var(--line); }
    .concert-source-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px;margin-top:16px; }
    .concert-source-card { padding:14px; }
    .concert-source-card p { margin:5px 0 10px; }
    .concert-source-thumbs { display:flex;gap:6px;overflow:auto; }
    .concert-source-thumbs img { width:72px;height:54px;flex:0 0 auto;border-radius:5px;object-fit:cover; }
    @media(max-width:700px){.concert-album-edit{grid-template-columns:1fr}}
</style>
@endpush

@section('content')
@include('admin.media._backend-gallery-styles')
<x-admin.gallery-heading title="Albums" :studio="$event->studio" :event="$event" :release="true"
    description="Open an album to view its photos. Use the settings cog to edit its title, order or cover."
    :back-url="route('admin.concert-photo-gallery.events', $event->studio ? ['studio' => $event->studio->uuid] : ['unlinked' => 1])" back-label="Back to events" />
@include('admin.media._concert-breadcrumbs')
@if(session('status'))<p class="card card-pad" role="status">{{ session('status') }}</p>@endif
@if($errors->any())<p class="card card-pad" role="alert" style="color:#a62232">{{ $errors->first() }}</p>@endif

@if(!$concert)
    <section class="card card-pad"><h2>Link the customer concert record</h2><p>This scheduled event is not linked to a customer concert yet. Open Concert media, create or edit the matching concert and choose this booking event. Photo uploads remain private and unchanged.</p><a class="button" href="{{ route('admin.concerts.index') }}">Open Concert media</a></section>
@else
    @if(!$gallery)<p class="card card-pad">No verified photo uploads are available for this event yet.</p>@endif
    <div class="backend-gallery-grid">
        @forelse($concert->mediaCollections->filter(fn($album) => $album->media_type->value === 'photo')->sortBy('sort_order') as $album)
            @php($coverAsset = $album->assets->firstWhere('id', $album->cover_media_asset_id) ?? $album->assets->where('media_type.value', 'photo')->where('is_visible', true)->first())
            <article id="album-{{ $album->uuid }}" class="card backend-gallery-card concert-album-card">
                <div class="backend-gallery-card-media media-grant-target">
                    <a class="concert-album-open" href="{{ route('admin.concert-photo-gallery.show', ['schedulingEvent' => $event, 'album' => $album->uuid]) }}" aria-label="View photos in {{ $album->name }}">
                    @if($coverAsset)<img src="{{ route('admin.concert-photo-gallery.asset', ['schedulingEvent' => $event, 'asset' => $coverAsset, 'size' => 'cover']) }}" alt="{{ $album->name }} cover" loading="lazy">
                    @else<span class="backend-gallery-card-placeholder" aria-hidden="true">▱</span>@endif
                    </a>
                    <x-admin.media-grant-button source-kind="concert_item" :event-uuid="$concert->uuid" :source-uuid="$album->uuid" :label="'Add every photo from '.$album->name.' to a customer account'" :aria-label="'Add '.$album->name.' to an account'" />
                </div>
                <div class="backend-gallery-card-body">
                    <a href="{{ route('admin.concert-photo-gallery.show', ['schedulingEvent' => $event, 'album' => $album->uuid]) }}" style="color:inherit;text-decoration:none"><strong class="backend-gallery-card-title">{{ $album->name }}</strong></a>
                    <div class="backend-gallery-card-meta">{{ $album->assets->where('media_type.value', 'photo')->count() }} photos · {{ $album->published_at ? 'Released '.$album->published_at->format('j M Y') : 'Private draft' }}</div>
                </div>
                <div class="concert-album-footer">
                    <a class="concert-album-view" href="{{ route('admin.concert-photo-gallery.show', ['schedulingEvent' => $event, 'album' => $album->uuid]) }}">View album <span aria-hidden="true">→</span></a>
                    <details class="concert-album-controls">
                        <summary aria-label="Album settings for {{ $album->name }}" title="Album settings"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 3 .6 2.1a7 7 0 0 1 4.8 0L15 3l3 1.7-1.5 1.6a7 7 0 0 1 2.4 4.2L21 11v3l-2.1.5a7 7 0 0 1-2.4 4.2L18 20.3 15 22l-.6-2.1a7 7 0 0 1-4.8 0L9 22l-3-1.7 1.5-1.6a7 7 0 0 1-2.4-4.2L3 14v-3l2.1-.5a7 7 0 0 1 2.4-4.2L6 4.7Z"/><circle cx="12" cy="12.5" r="3"/></svg></summary>
                        <div class="concert-album-settings-panel">
                        @if(!$album->published_at)
                            <form method="post" action="{{ route('admin.concert-photo-gallery.albums.update', [$event, $album]) }}" class="concert-album-edit">@csrf
                                <label>Album title<input name="name" value="{{ $album->name }}" maxlength="255" required></label>
                                <label>Order<input type="number" name="sort_order" value="{{ $album->sort_order }}" min="0" max="9999" required></label>
                                <div class="concert-album-save"><button>Save album</button></div>
                            </form>
                        @else
                            <p class="muted">Released album titles and ordering are locked.</p>
                        @endif
                        <h3 class="album-cover-heading">Album cover</h3><p class="muted">The first photo is used automatically until you choose another.</p>
                        <div class="album-cover-grid">
                            @foreach($album->assets->where('media_type.value', 'photo')->where('is_visible', true)->take(24) as $asset)
                                <div class="album-cover-option media-grant-target">
                                    <form method="post" action="{{ route('admin.concert-photo-gallery.albums.cover.update', [$event, $album]) }}">@csrf<input type="hidden" name="asset_uuid" value="{{ $asset->uuid }}"><button aria-pressed="{{ $album->cover_media_asset_id === $asset->id ? 'true' : 'false' }}" title="Use as album cover"><img src="{{ route('admin.concert-photo-gallery.asset', ['schedulingEvent' => $event, 'asset' => $asset, 'size' => 'thumbnail']) }}" alt="Album cover option" loading="lazy"></button></form>
                                    <x-admin.media-grant-button source-kind="concert_photo" :event-uuid="$concert->uuid" :source-uuid="$asset->uuid" :label="'Add '.($asset->display_name ?: $asset->original_filename).' to a customer account'" :aria-label="'Add '.($asset->display_name ?: $asset->original_filename).' to an account'" />
                                </div>
                            @endforeach
                        </div>
                        @if($album->cover_media_asset_id)<form method="post" action="{{ route('admin.concert-photo-gallery.albums.cover.update', [$event, $album]) }}" style="margin-top:12px">@csrf<button class="secondary">Use first photo automatically</button></form>@endif
                        </div>
                    </details>
                </div>
            </article>
        @empty
            <p class="card card-pad">Private albums appear automatically as concert photos are uploaded and verified.</p>
        @endforelse
    </div>

    <details class="card concert-source-panel">
        <summary>Capture folders <span>{{ $source->count() }} folder{{ $source->count() === 1 ? '' : 's' }} from the confirmed programme</span></summary>
        <div class="concert-source-content"><div class="concert-source-grid">
            @forelse($source as $entry)
                <article class="card concert-source-card"><strong>{{ $entry['item']['folder_number'] ?? $entry['item']['number'] ?? '' }} {{ $entry['item']['folder_title'] ?? $entry['item']['title'] }}</strong><p>{{ $entry['files']->count() }} verified photo(s) · {{ $entry['collection'] ? 'synced' : 'not synced' }}</p>
                    @if($entry['files']->isNotEmpty())<div class="concert-source-thumbs">@foreach($entry['files']->take(4) as $file)<img src="{{ route('admin.concert-photo-gallery.image', ['schedulingEvent' => $event, 'photo' => $file->uuid, 'size' => 'thumbnail']) }}" alt="" loading="lazy">@endforeach</div>@endif
                </article>
            @empty
                <p>No verified capture folders yet.</p>
            @endforelse
        </div></div>
    </details>
    <details class="card card-pad" style="margin-top:20px"><summary>Gallery repair</summary><p class="muted">Uploaded photos are added to albums automatically. Use repair only for older uploads or after linking an existing event.</p><form method="post" action="{{ route('admin.concert-photo-gallery.sync', $event) }}">@csrf<button class="secondary" @disabled(!$gallery || !$plan)>Repair gallery from verified uploads</button></form></details>
@endif
@include('admin.customers._grant-dialog')
@endsection
