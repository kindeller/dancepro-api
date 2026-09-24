@extends('layouts.admin', ['title' => $event->name.' photos', 'heading' => 'Concert Photos', 'subheading' => $event->name])

@push('styles')
<style>
    .album-cover-grid { display:flex; flex-wrap:wrap; gap:7px; margin-top:8px; }
    .album-cover-grid form { margin:0; }
    .album-cover-grid button { display:block; padding:0; overflow:hidden; border:3px solid transparent; border-radius:8px; background:none; cursor:pointer; }
    .album-cover-grid button[aria-pressed="true"] { border-color:#168bc1; }
    .album-cover-grid img { display:block; width:88px; height:62px; object-fit:cover; }
</style>
@endpush

@section('content')
<div class="toolbar" style="margin-bottom:20px"><a class="button secondary" href="{{ route('admin.concert-photo-gallery.events') }}">← Concert Photos</a><a class="button secondary" href="{{ route('admin.concert-tracker.index', ['year' => $event->event_date?->year]) }}#prepare-release">Prepare release in Tracker</a></div>
@if(session('status'))<p class="card card-pad" role="status">{{ session('status') }}</p>@endif
@if($errors->any())<p class="card card-pad" role="alert" style="color:#a62232">{{ $errors->first() }}</p>@endif
@if(!$concert)
<section class="card card-pad"><h2>Link the customer concert record</h2><p>This scheduled event is not linked to a customer concert yet. Open Concert Media, create or edit the matching concert and choose this booking event. Photo uploads remain private and unchanged.</p><a class="button" href="{{ route('admin.concerts.index') }}">Open Concert Media</a></section>
@else
<section class="card card-pad"><div style="display:flex;gap:18px;align-items:start;flex-wrap:wrap"><x-event-artwork :url="$event->crewLogoUrl()" :name="$event->name" size="large" /><div style="flex:1"><h2 style="margin-top:0">{{ $concert->name }}</h2><p>{{ $event->studio?->name }} · {{ $event->event_date?->format('j M Y') }}</p>
<p class="muted">Sync creates or updates private customer albums from verified Mac uploader photos. It never publishes albums and never changes renamed album titles.</p>
<form method="post" action="{{ route('admin.concert-photo-gallery.sync', $event) }}">@csrf<button @disabled(!$gallery || !$plan)>Sync verified photos</button></form>
@if(!$gallery)<p class="muted">No verified uploader photo gallery exists for this event yet.</p>@endif</div></div></section>

<div class="grid two-col" style="margin-top:20px">
<section><h2>Capture folders</h2>
@forelse($source as $entry)<article class="card card-pad" style="margin-bottom:12px"><strong>{{ $entry['item']['folder_number'] ?? $entry['item']['number'] ?? '' }} {{ $entry['item']['folder_title'] ?? $entry['item']['title'] }}</strong><p>{{ $entry['files']->count() }} verified photo(s) · {{ $entry['collection'] ? 'synced' : 'not synced' }}</p>
@if($entry['files']->isNotEmpty())<div style="display:flex;gap:6px;overflow:auto">@foreach($entry['files']->take(4) as $file)<img src="{{ route('admin.concert-photo-gallery.image', ['schedulingEvent' => $event, 'photo' => $file->uuid, 'size' => 'thumbnail']) }}" alt="" style="width:72px;height:54px;object-fit:cover;border-radius:5px">@endforeach</div>@endif
</article>@empty<p class="card card-pad">No verified capture folders yet.</p>@endforelse
</section>
<section><h2>Customer albums</h2>
@forelse($concert->mediaCollections->filter(fn($album) => $album->media_type->value === 'photo')->sortBy('sort_order') as $album)<article class="card card-pad" style="margin-bottom:12px"><form method="post" action="{{ route('admin.concert-photo-gallery.albums.update', [$event, $album]) }}" class="grid">@csrf
<label>Album title<input name="name" value="{{ $album->name }}" maxlength="255" required @disabled($album->published_at)></label><label>Order<input type="number" name="sort_order" value="{{ $album->sort_order }}" min="0" max="9999" required @disabled($album->published_at)></label>
<p>{{ $album->assets->where('media_type.value', 'photo')->count() }} photos · {{ $album->published_at ? 'Released '.$album->published_at->format('j M Y') : 'Private draft' }}</p>
@if(!$album->published_at)<div><button>Save album</button></div>@endif</form>
<details><summary><strong>Choose album cover</strong></summary><p class="muted">The first photo is used automatically until you choose another.</p><div class="album-cover-grid">@foreach($album->assets->where('media_type.value', 'photo')->where('is_visible', true)->take(24) as $asset)@if($asset->metadata['programme_photo_file_uuid'] ?? null)<form method="post" action="{{ route('admin.concert-photo-gallery.albums.cover.update', [$event, $album]) }}">@csrf<input type="hidden" name="asset_uuid" value="{{ $asset->uuid }}"><button aria-pressed="{{ $album->cover_media_asset_id === $asset->id ? 'true' : 'false' }}" title="Use as album cover"><img src="{{ route('admin.concert-photo-gallery.image', ['schedulingEvent' => $event, 'photo' => $asset->metadata['programme_photo_file_uuid'], 'size' => 'thumbnail']) }}" alt="Album cover option"></button></form>@endif @endforeach</div>@if($album->cover_media_asset_id)<form method="post" action="{{ route('admin.concert-photo-gallery.albums.cover.update', [$event, $album]) }}" style="margin-top:10px">@csrf<button class="secondary">Use first photo automatically</button></form>@endif</details>
</article>
@empty<p class="card card-pad">Sync verified photos to create the first private albums.</p>@endforelse
</section></div>
@endif
@endsection
