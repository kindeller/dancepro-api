@extends('layouts.admin', ['title' => ($event->concertBookingItem?->title ?: $event->name).' · Top Pics', 'heading' => 'Publicity', 'subheading' => 'Top Pics and event screens'])
@section('content')
@include('admin.media._publicity-tabs')
@include('admin.media._backend-gallery-styles')
<style>
.top-pic-image{position:relative}.top-pic-preview-trigger{display:block;width:100%;height:100%;padding:0;border:0;border-radius:0;background:transparent;cursor:zoom-in}.top-pic-copy{display:grid;gap:8px;overflow-wrap:anywhere}.top-pic-copy p{margin:0}.top-pic-actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:6px}.top-pic-dialog{width:min(1100px,calc(100% - 24px));max-height:calc(100% - 24px);padding:0;border:0;border-radius:12px;background:#101820;color:white}.top-pic-dialog::backdrop{background:#000c}.top-pic-dialog header,.top-pic-dialog footer{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:14px;flex-wrap:wrap}.top-pic-dialog img{display:block;max-width:100%;max-height:70vh;margin:auto;object-fit:contain}.top-pic-dialog a{color:#8ddbf7}.top-pic-dialog button{min-width:44px}.top-pic-pagination{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-top:24px}
</style>
@if($type === 'concert')
<x-admin.gallery-heading title="Top Pics" :studio="$event->studio" :event="$event" :release="true"
    :description="$photos->total().' Top '.Str::plural('Pic', $photos->total()).' · Choose an event cover or open a photo to preview it.'"
    :back-url="route('admin.top-pics.index', ['type' => 'concert'] + ($event->studio ? ['studio' => $event->studio->uuid] : ['unlinked' => 1]))" back-label="Back to events" />
@else
<a class="button secondary" href="{{ route('admin.top-pics.index', ['type' => $type]) }}">← Back to events</a>
@include('admin.media._photo-gallery-tabs')
@endif
@if($type === 'concert')
@include('admin.media._concert-breadcrumbs', ['breadcrumbTopPics' => true])
@else
<x-gallery-breadcrumbs :items="[['label' => $event->name, 'url' => route('admin.competition-gallery.show', $event)], ['label' => 'Top Pics']]" />
@endif
@if(session('status'))<p role="status" class="notice">{{ session('status') }}</p>@endif
@if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
@if($type !== 'concert')<p class="muted">{{ $photos->total() }} Top {{ $photos->total() === 1 ? 'Pic' : 'Pics' }} · {{ $event->event_date?->format('j M Y') }}</p>@endif
@if($type === 'concert')
<div class="toolbar" style="margin:16px 0"><form method="post" action="{{ route('admin.top-pics.cover', $event) }}" data-top-pic-cover>@csrf<input type="hidden" name="photo_uuid" value=""><button class="button secondary" type="submit">Use automatic event cover</button></form><span role="status" data-cover-status></span></div>
@endif
<div class="backend-gallery-grid">
@forelse($photos as $entry)
@php
    $photo = $entry['file'];
    $imageRoute = $type === 'competition' ? 'admin.competition-gallery.image' : 'admin.concert-photo-gallery.image';
    $downloadUrl = route('admin.top-pics.download', ['schedulingEvent' => $event, 'photo' => $photo->uuid]);
@endphp
<article class="card backend-gallery-card">
    <div class="backend-gallery-card-media top-pic-image media-grant-target">
        <button type="button" class="top-pic-preview-trigger" data-top-pic-preview data-src="{{ route($imageRoute, ['schedulingEvent' => $event, 'photo' => $photo->uuid]) }}" data-title="{{ $entry['section'].' · '.$entry['item'].' · '.$photo->file_name }}" data-download="{{ $downloadUrl }}" data-gallery="{{ $entry['gallery_url'] }}" aria-label="Preview {{ $photo->file_name }}">
            <img src="{{ route($imageRoute, ['schedulingEvent' => $event, 'photo' => $photo->uuid, 'size' => 'thumbnail']) }}" alt="{{ $entry['item'] }}" loading="lazy">
        </button>
        @if($entry['grant_uuid'])<x-admin.media-grant-button :source-kind="$entry['grant_kind']" :source-uuid="$entry['grant_uuid']" :event-uuid="$entry['grant_event_uuid']" :label="'Add '.$photo->file_name.' to a customer account'" />@endif
    </div>
    <div class="backend-gallery-card-body top-pic-copy">
        <p class="muted">{{ $entry['section'] }}</p><strong>{{ $entry['item'] }}</strong><p class="muted">{{ $photo->file_name }}</p>
        <div class="top-pic-actions"><a href="{{ $entry['gallery_url'] }}">Go to gallery</a><a href="{{ $downloadUrl }}" download>Download original</a></div>
        @if($type === 'concert')
        <form method="post" action="{{ route('admin.top-pics.cover', $event) }}" data-top-pic-cover>@csrf<input type="hidden" name="photo_uuid" value="{{ $photo->uuid }}"><button class="button secondary" type="submit" @disabled($coverPhotoId === $photo->id)>{{ $coverPhotoId === $photo->id ? 'Event cover ✓' : 'Use as event cover' }}</button></form>
        @endif
        @if(!$entry['grant_uuid'])<small class="muted">New concert uploads appear in the gallery automatically. For older uploads, use Gallery repair in Concert photos to enable Add to account.</small>@endif
    </div>
</article>
@empty
<section class="card card-pad"><h2>No Top Pics for this event yet</h2><p>Photos marked with a star in the uploader appear here once uploaded and verified.</p></section>
@endforelse
</div>
<nav class="top-pic-pagination" aria-label="Top Pic pages">
@if($photos->previousPageUrl())<a class="button secondary" href="{{ $photos->previousPageUrl() }}">Previous page</a>@endif
<span>Page {{ $photos->currentPage() }} of {{ $photos->lastPage() }}</span>
@if($photos->nextPageUrl())<a class="button secondary" href="{{ $photos->nextPageUrl() }}">Next page</a>@endif
</nav>
<dialog id="top-pic-preview" class="top-pic-dialog" aria-labelledby="top-pic-title">
    <header><strong id="top-pic-title"></strong><button type="button" data-close-preview aria-label="Close preview">×</button></header>
    <img alt="">
    <footer><button type="button" data-previous>← Previous</button><a data-download download>Download original</a><a data-gallery>Go to gallery</a><button type="button" data-next>Next →</button></footer>
</dialog>
@include('admin.customers._grant-dialog')
<script>
(() => {
    const dialog = document.getElementById('top-pic-preview');
    const buttons = [...document.querySelectorAll('[data-top-pic-preview]')];
    let index = 0;
    function show(position) {
        index = position;
        const data = buttons[index].dataset;
        dialog.querySelector('img').src = data.src;
        dialog.querySelector('img').alt = data.title;
        dialog.querySelector('#top-pic-title').textContent = data.title;
        dialog.querySelector('[data-download]').href = data.download;
        dialog.querySelector('[data-gallery]').href = data.gallery;
        dialog.querySelector('[data-previous]').disabled = index === 0;
        dialog.querySelector('[data-next]').disabled = index === buttons.length - 1;
    }
    buttons.forEach((button, position) => button.addEventListener('click', () => {show(position); dialog.showModal();}));
    dialog.querySelector('[data-close-preview]').addEventListener('click', () => dialog.close());
    dialog.querySelector('[data-previous]').addEventListener('click', () => show(index - 1));
    dialog.querySelector('[data-next]').addEventListener('click', () => show(index + 1));
    dialog.addEventListener('keydown', event => {
        if(event.key === 'ArrowLeft' && index > 0) {event.preventDefault(); show(index - 1);}
        if(event.key === 'ArrowRight' && index < buttons.length - 1) {event.preventDefault(); show(index + 1);}
    });
    dialog.addEventListener('close', () => dialog.querySelector('img').removeAttribute('src'));
})();
</script>
@endsection

@if($type === 'concert')
@push('scripts')
<script nonce="{{ request()->attributes->get('csp_nonce') }}" src="{{ asset('js/admin-top-pic-cover.js') }}" defer></script>
@endpush
@endif
