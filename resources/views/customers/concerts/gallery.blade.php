@extends('layouts.public', ['title' => $concert->name.' photos', 'footerLabel' => 'Concert photos'])

@push('styles')
<style>
    .concert-folders, .concert-photos { display:grid; grid-template-columns:repeat(auto-fill,minmax(190px,230px)); gap:14px; align-items:start; }
    .concert-folder { display:block; padding:18px; text-decoration:none; }
    .concert-folder strong { display:block; line-height:1.3; }
    .concert-folder-cover { display:block; width:calc(100% + 36px); aspect-ratio:4/3; margin:-18px -18px 14px; object-fit:cover; background:#10202c; }
    .concert-parent-folder { padding:0; overflow:hidden; }
    .concert-parent-folder .concert-folder-copy { display:block; padding:14px 16px 16px; }
    .concert-parent-folder .concert-folder-cover { width:100%; aspect-ratio:16/9; margin:0; }
    .concert-folder-meta { display:block; margin-top:5px; color:var(--muted); font-size:13px; }
    .concert-photo { display:block; overflow:hidden; }
    .concert-photo img { display:block; width:100%; height:auto; }
    .concert-photo-tile { display:flex; flex-direction:column; gap:6px; }
    .concert-photo-actions { display:flex; gap:6px; }
    .concert-photo-actions form { flex:1; }
    .concert-photo-actions button { width:100%; min-height:34px; border:1px solid #ccd9df; border-radius:7px; background:#fff; cursor:pointer; font:inherit; font-size:12px; font-weight:750; }
    .concert-photo-actions button[aria-pressed="true"] { background:#e6f6fd; border-color:#168bc1; }
    .concert-viewer { width:min(1100px,calc(100% - 24px)); max-height:calc(100% - 24px); padding:0; border:0; border-radius:12px; background:#101820; color:#fff; }
    .concert-viewer::backdrop { background:#000c; }
    .concert-viewer-inner { display:flex; flex-direction:column; max-height:calc(100vh - 24px); }
    .concert-viewer-header { display:flex; justify-content:space-between; align-items:center; padding:10px 16px; }
    .concert-viewer-header button { background:#253842; border:1px solid #5b6971; color:#fff; border-radius:7px; padding:8px 13px; cursor:pointer; }
    .concert-viewer img { display:block; max-width:100%; max-height:calc(100vh - 110px); width:auto; height:auto; object-fit:contain; margin:auto; }
</style>
@endpush

@section('content')
@php
    $concertRoutes = $concertRoutes ?? ['show' => 'customer.concerts.photos.show', 'image' => 'customer.concerts.photos.image', 'selection' => 'customer.concerts.photos.selections.store', 'cart' => 'customer.concerts.photos.cart'];
    $concertHomeUrl = $concertHomeUrl ?? route('customer.media.index');
    $concertCartTarget = $concertCartTarget ?? $concert->studio;
@endphp
<div class="container" style="padding-top:18px"><a href="{{ $concertHomeUrl }}">← My Media</a></div>
<section class="hero" style="padding:38px 0"><div class="container"><div class="eyebrow">Concert photos</div><h1>{{ $concert->name }}</h1>
    @if($concert->event_date)<p class="lead">{{ $concert->event_date->format('j M Y') }}</p>@endif
</div></section>
<section class="section"><div class="container">
    <p><a class="button secondary" href="{{ route($concertRoutes['cart'], $concertCartTarget) }}">Favourites &amp; photo cart @if($shopping['cart']->isNotEmpty())({{ $shopping['cart']->count() }})@endif</a></p>
    @if($selected)<p><a href="{{ route($concertRoutes['show'], array_filter(['concert' => $concert, 'group' => $selectedGroup?->uuid])) }}">← {{ $selectedGroup?->name ?? 'All albums' }}</a></p><h2>{{ $selected->name }}</h2>
        @if($photos->isNotEmpty())<div class="concert-photos" id="concert-photos">
            @foreach($photos as $photo)
                @php($preview = route($concertRoutes['image'], ['concert' => $concert, 'asset' => $photo->uuid]))
                @php($saved = $shopping['selections']->first(fn ($selection) => $selection->media_asset_id === $photo->id && $selection->is_favourite))
                @php($inCart = $shopping['selections']->first(fn ($selection) => $selection->media_asset_id === $photo->id && $selection->in_cart))
                @php($owned = $shopping['ownedAssetIds']->contains($photo->id))
                <div class="concert-photo-tile">
                    <a class="card concert-photo" href="{{ $preview }}" target="_blank" rel="noopener" data-preview="{{ $preview }}" aria-label="View watermarked photo">
                        <img loading="lazy" src="{{ route($concertRoutes['image'], ['concert' => $concert, 'asset' => $photo->uuid, 'size' => 'thumbnail']) }}" alt="Concert photo preview">
                    </a>
                    <div class="concert-photo-actions">
                        @if($owned)<strong style="padding:7px 9px;color:#087eae">✓ Owned</strong>@endif
                        @foreach([['action' => $saved ? 'unfavourite' : 'favourite', 'label' => $saved ? '♥ Saved' : '♡ Save', 'pressed' => (bool) $saved], ['action' => $inCart ? 'remove' : 'add', 'label' => $inCart ? '✓ In cart' : 'Add · $'.number_format($shopping['photoPrice'] / 100, 2), 'pressed' => (bool) $inCart]] as $control)
                            <form method="post" action="{{ route($concertRoutes['selection'], $concert) }}">@csrf
                                <input type="hidden" name="asset_uuid" value="{{ $photo->uuid }}"><input type="hidden" name="action" value="{{ $control['action'] }}">
                                <button type="submit" aria-pressed="{{ $control['pressed'] ? 'true' : 'false' }}" @disabled($owned && $control['action'] === 'add')>{{ $owned && $control['action'] === 'add' ? 'Owned' : $control['label'] }}</button>
                            </form>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
        <nav class="actions" aria-label="Photo pages">
            @if($photos->previousPageUrl())<a class="button secondary" href="{{ $photos->previousPageUrl() }}">← Previous photos</a>@endif
            @if($photos->nextPageUrl())<a class="button secondary" href="{{ $photos->nextPageUrl() }}">Next photos →</a>@endif
        </nav>
        <dialog class="concert-viewer" id="concert-viewer" aria-label="Photo preview"><div class="concert-viewer-inner">
            <div class="concert-viewer-header"><strong>Watermarked preview</strong><button type="button" data-close>Close ✕</button></div>
            <img id="concert-viewer-image" alt="Watermarked concert photo preview">
        </div></dialog>
        @else<div class="empty"><p>No photos are available in this album yet.</p></div>@endif
    @elseif($selectedGroup)
        <p><a href="{{ route($concertRoutes['show'], $concert) }}">← All concert albums</a></p>
        <h2>{{ $selectedGroup->name }}</h2>
        @if($collections->isNotEmpty())<div class="concert-folders">
            @foreach($collections as $album)@php($albumCover = $album->displayCoverAsset())
                <a class="card concert-folder" href="{{ route($concertRoutes['show'], ['concert' => $concert, 'group' => $selectedGroup->uuid, 'folder' => $album->uuid]) }}">
                    @if($albumCover)<img class="concert-folder-cover" src="{{ route($concertRoutes['image'], ['concert' => $concert, 'asset' => $albumCover->uuid, 'size' => 'thumbnail']) }}" alt="{{ $album->name }} cover">@endif
                    <strong>{{ $album->name }}</strong>@if($selectedGroup->collections->pluck('concert_id')->unique()->count() > 1)<span class="concert-folder-meta">{{ $album->concert->name }}</span>@endif
                </a>
            @endforeach
        </div>@else<div class="empty"><p>Concert photos are being prepared.</p></div>@endif
    @else
        <h2>Concert albums</h2>
        @php($ungrouped = $collections->filter(fn($album) => $album->concertPhotoReleaseGroups->isEmpty()))
        @if($groups->isNotEmpty() || $ungrouped->isNotEmpty())<div class="concert-folders">
            @foreach($groups as $group)@php($groupCover = $group->displayCoverAsset())
                <a class="card concert-folder concert-parent-folder" href="{{ route($concertRoutes['show'], ['concert' => $concert, 'group' => $group->uuid]) }}">
                    @if($groupCover)<img class="concert-folder-cover" src="{{ route($concertRoutes['image'], ['concert' => $concert, 'asset' => $groupCover->uuid, 'size' => 'thumbnail']) }}" alt="{{ $group->name }} cover">@endif
                    <span class="concert-folder-copy"><strong>{{ $group->name }}</strong><span class="concert-folder-meta">{{ $group->collections->count() }} {{ Str::plural('album', $group->collections->count()) }}</span></span>
                </a>
            @endforeach
            @foreach($ungrouped as $album)@php($albumCover = $album->displayCoverAsset())
                <a class="card concert-folder" href="{{ route($concertRoutes['show'], ['concert' => $concert, 'folder' => $album->uuid]) }}">
                    @if($albumCover)<img class="concert-folder-cover" src="{{ route($concertRoutes['image'], ['concert' => $concert, 'asset' => $albumCover->uuid, 'size' => 'thumbnail']) }}" alt="{{ $album->name }} cover">@endif
                    <strong>{{ $album->name }}</strong>
                </a>
            @endforeach
        </div>@else<div class="empty"><p>Concert photos are being prepared.</p></div>@endif
    @endif
</div></section>
@endsection

@push('scripts')
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
(() => {
    const grid = document.getElementById('concert-photos');
    const viewer = document.getElementById('concert-viewer');
    if (!grid || !viewer || typeof viewer.showModal !== 'function') return;
    const image = document.getElementById('concert-viewer-image');
    grid.querySelectorAll('[data-preview]').forEach(link => link.addEventListener('click', event => {
        event.preventDefault(); image.src = link.dataset.preview; viewer.showModal();
    }));
    viewer.querySelector('[data-close]').addEventListener('click', () => viewer.close());
    viewer.addEventListener('close', () => image.removeAttribute('src'));
    viewer.addEventListener('click', event => { if (event.target === viewer) viewer.close(); });
})();
</script>
@endpush
