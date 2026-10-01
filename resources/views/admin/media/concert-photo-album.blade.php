@extends('layouts.admin', ['title' => $album->name.' · Concert photos', 'heading' => 'Concert photos', 'subheading' => 'Manage studio galleries, event albums and photos.'])

@section('content')
<x-admin.gallery-heading :title="$album->name" :studio="$event->studio" :event="$event" :release="true"
    :description="$photos->total().' '.Str::plural('photo', $photos->total()).' · '.($album->published_at ? 'Released '.$album->published_at->format('j M Y') : 'Private draft')"
    :back-url="route('admin.concert-photo-gallery.show', $event).'#album-'.$album->uuid" back-label="Back to event albums" />
@include('admin.media._concert-breadcrumbs', ['breadcrumbAlbum' => $album])
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,220px),1fr));gap:16px;align-items:start">
    @forelse($photos as $photo)
    <figure class="card gallery-hover-card" style="margin:0">
        <div class="media-grant-target">
            <a href="{{ route('admin.concert-photo-gallery.asset', ['schedulingEvent' => $event, 'asset' => $photo]) }}" data-gallery-preview target="_blank" rel="noopener" aria-label="Open preview of {{ $photo->display_name ?: $photo->original_filename }}">
                <img src="{{ route('admin.concert-photo-gallery.asset', ['schedulingEvent' => $event, 'asset' => $photo, 'size' => 'thumbnail']) }}" alt="{{ $photo->display_name ?: $photo->original_filename }}" loading="lazy" style="display:block;width:100%;height:auto">
            </a>
            <x-admin.media-grant-button source-kind="concert_photo" :event-uuid="$concert->uuid" :source-uuid="$photo->uuid" :label="'Add '.($photo->display_name ?: $photo->original_filename).' to a customer account'" />
        </div>
        <figcaption style="padding:10px;overflow-wrap:anywhere">{{ $photo->display_name ?: $photo->original_filename }} @if($photo->metadata['is_top_pic'] ?? false)<span aria-label="Top Pic">⭐</span>@endif</figcaption>
    </figure>
    @empty
    <p class="card card-pad">No photos are available in this album yet.</p>
    @endforelse
</div>
@if($photos->hasPages())
<nav aria-label="Album photo pages" class="toolbar" style="margin-top:20px">
    @if($photos->previousPageUrl())<a class="button secondary" href="{{ $photos->previousPageUrl() }}">Previous photos</a>@endif
    <span>Page {{ $photos->currentPage() }} of {{ $photos->lastPage() }}</span>
    @if($photos->nextPageUrl())<a class="button secondary" href="{{ $photos->nextPageUrl() }}">Next photos</a>@endif
</nav>
@endif
<x-gallery-photo-viewer />
@include('admin.customers._grant-dialog')
@endsection
