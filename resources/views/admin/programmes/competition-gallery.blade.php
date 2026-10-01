@extends('layouts.admin', ['title' => $presentation['title'], 'heading' => $presentation['title'], 'subheading' => 'Competition photos · '.$eventCode])

@section('content')
@include('admin.media._backend-gallery-styles')
@if(session('status'))<p class="notice" role="status">{{ session('status') }}</p>@endif
@if($errors->any())<p class="error" role="alert">{{ $errors->first() }}</p>@endif
<div class="toolbar" style="margin-bottom:16px">
    <a class="button secondary" href="{{ route('admin.competition-gallery.index') }}"><span aria-hidden="true">←</span> Back to Competition photos</a>
    <a class="button secondary" href="{{ route('admin.photo-uploads.show', $event) }}">Photo uploads</a>
    <a class="button secondary" href="{{ route('admin.competition-gallery.settings', $event) }}">Gallery settings</a>
    <a class="button secondary" href="{{ route('admin.competition-videos.index', $event) }}">Video matching &amp; HOLD</a>
    <a class="button secondary" href="{{ route('admin.competition-gallery.customer-preview', $event) }}">Customer preview</a>
    <span class="badge">Private · admin preview</span>
</div>
@include('admin.media._photo-gallery-tabs')
<p class="muted">Uploaded photos from both photographers appear together. Customer access requires gallery readiness. Video upload status and HOLD are managed separately.</p>
@if($stale)<p class="card card-pad">These photos use an earlier programme. Reconciliation is needed before customer release.</p>@endif
@php
        $galleryBreadcrumbs = [['label' => $event->name, 'url' => route('admin.competition-gallery.show', $event).'#gallery-content']];
        foreach ($breadcrumbs as $crumb) $galleryBreadcrumbs[] = ['label' => $crumb['label'], 'url' => route('admin.competition-gallery.show', ['schedulingEvent' => $event, 'folder' => $crumb['uuid']]).'#gallery-content'];
    @endphp
    <x-gallery-breadcrumbs :items="$galleryBreadcrumbs" />
@if($selectedSection && isset($sectionReadiness[$selectedSection]))
    @include('admin.programmes._section-readiness', ['readiness' => $sectionReadiness[$selectedSection]])
@endif
@if($children)
    <div class="backend-gallery-grid">
        @foreach($children as $child)
            <div class="card backend-gallery-card gallery-navigation-card">
                <div class="media-grant-target">
                    <a class="backend-gallery-card-media" href="{{ route('admin.competition-gallery.show', ['schedulingEvent' => $event, 'folder' => $child['uuid']]) }}" aria-label="Open {{ $child['label'] }}">
                        @if($child['cover_uuid'] ?? null)<img src="{{ route('admin.competition-gallery.image', ['schedulingEvent' => $event, 'photo' => $child['cover_uuid'], 'size' => 'cover']) }}" alt="" loading="lazy">@else<span class="backend-gallery-card-placeholder" aria-hidden="true">▱</span>@endif
                    </a>
                    @if($child['kind'] === 'item')<x-admin.media-grant-button source-kind="competition_item" :event-uuid="$event->uuid" :source-uuid="$child['uuid']" :label="'Add all photos from '.$child['label'].' to a customer account'" :aria-label="'Add '.$child['label'].' to an account'" />@endif
                </div>
                <a class="backend-gallery-card-body" style="display:block;color:inherit;text-decoration:none" href="{{ route('admin.competition-gallery.show', ['schedulingEvent' => $event, 'folder' => $child['uuid']]) }}">
                    <strong class="backend-gallery-card-title">{{ $child['label'] }}</strong>
                    @if($child['kind'] === 'section')
                        <div class="backend-gallery-card-meta">{{ $sectionReadiness[$child['uuid']]['label'] ?? 'Awaiting uploader preparation' }}</div>
                    @else
                        <div class="backend-gallery-card-meta">{{ ucfirst($child['kind']) }}</div>
                    @endif
                </a>
                <div class="gallery-nav-footer"><a class="gallery-nav-action" href="{{ route('admin.competition-gallery.show', ['schedulingEvent' => $event, 'folder' => $child['uuid']]) }}">View {{ $child['kind'] === 'item' ? 'routine' : strtolower($child['kind']) }} <span aria-hidden="true">→</span></a></div>
            </div>
        @endforeach
    </div>
@elseif($selected && $selected['kind'] === 'item')
    <section class="card card-pad" style="margin-bottom:20px">
        <h2>Routine studio</h2><p class="muted">Assign a studio to make this routine appear under My Studios’ Routines for customers who have added that studio and this competition.</p>
        <form method="POST" action="{{ route('admin.competition-gallery.routine-studio', $event) }}" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap">@csrf
            <input type="hidden" name="item_uuid" value="{{ $selected['uuid'] }}">
            <input type="hidden" name="expected_studio_uuid" value="{{ $routineStudio?->studio?->uuid }}">
            <label>Studio<select name="studio_uuid"><option value="">No studio assigned</option>
                @foreach($routineStudios as $studio)<option value="{{ $studio->uuid }}" @selected($routineStudio?->studio_id === $studio->id)>{{ $studio->name }}</option>@endforeach
            </select></label>
            <button class="button">Save studio</button>
        </form>
    </section>
    @if($photos && $photos->total())
        <p>{{ $photos->total() }} uploaded {{ $photos->total() === 1 ? 'photo' : 'photos' }}</p>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px;align-items:start">
            @foreach($photos as $photo)
                <figure class="card" style="position:relative;margin:0;overflow:hidden">
                    <div class="media-grant-target">
                        <a href="{{ route('admin.competition-gallery.image', ['schedulingEvent' => $event, 'photo' => $photo->uuid]) }}" data-gallery-preview target="_blank" rel="noopener" aria-label="Open preview of {{ $photo->file_name }}">
                            <img src="{{ route('admin.competition-gallery.image', ['schedulingEvent' => $event, 'photo' => $photo->uuid, 'size' => 'thumbnail']) }}" alt="{{ $photo->file_name }}" loading="lazy" style="display:block;width:100%;height:auto">
                        </a>
                        <x-admin.media-grant-button source-kind="competition_photo" :event-uuid="$event->uuid" :source-uuid="$photo->uuid" :label="'Add '.$photo->file_name.' to a customer account'" :aria-label="'Add '.$photo->file_name.' to an account'" />
                    </div>
                    <figcaption style="padding:10px;overflow-wrap:anywhere">{{ $photo->file_name }} @if($photo->is_top_pic)<span aria-label="Top Pic">⭐</span>@endif</figcaption>
                    <form method="POST" action="{{ route('admin.competition-gallery.settings.update', $event) }}" style="padding:0 10px 10px">
                        @csrf
                        <input type="hidden" name="action" value="cover">
                        <input type="hidden" name="photo_uuid" value="{{ $photo->uuid }}">
                        <input type="hidden" name="version" value="{{ $presentation['version'] }}">
                        <button class="secondary" @disabled($presentation['cover_uuid'] === $photo->uuid)>{{ $presentation['cover_uuid'] === $photo->uuid ? 'Current cover' : 'Use as cover' }}</button>
                    </form>
                </figure>
            @endforeach
        </div>
        <nav aria-label="Photo pages" class="toolbar" style="margin-top:20px">
            @if($photos->previousPageUrl())<a class="button secondary" href="{{ $photos->previousPageUrl() }}">Previous photos</a>@endif
            <span>Page {{ $photos->currentPage() }} of {{ $photos->lastPage() }}</span>
            @if($photos->nextPageUrl())<a class="button secondary" href="{{ $photos->nextPageUrl() }}">Next photos</a>@endif
        </nav>
    @else<p class="card card-pad">No verified photos uploaded for this competitor yet.</p>@endif
@else
    <p class="card card-pad">{{ $selected ? 'No competitors in this folder yet.' : 'Confirm a competition programme to create the gallery folders.' }}</p>
@endif
<x-gallery-photo-viewer />
@include('admin.customers._grant-dialog')
@endsection
