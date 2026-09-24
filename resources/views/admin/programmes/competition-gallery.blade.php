@extends('layouts.admin', ['title' => $presentation['title'], 'heading' => $presentation['title'], 'subheading' => 'Competition Photos · '.$eventCode])

@section('content')
<div class="toolbar" style="margin-bottom:16px">
    <a class="button secondary" href="{{ route('admin.competition-gallery.index') }}"><span aria-hidden="true">←</span> Back to Competition Photos</a>
    <a class="button secondary" href="{{ route('admin.photo-uploads.show', $event) }}">Photo uploads</a>
    <a class="button secondary" href="{{ route('admin.competition-gallery.settings', $event) }}">Gallery settings</a>
    <a class="button secondary" href="{{ route('admin.competition-videos.index', $event) }}">Video matching &amp; HOLD</a>
    <a class="button secondary" href="{{ route('admin.competition-gallery.customer-preview', $event) }}">Customer Preview</a>
    <span class="badge">Private · admin preview</span>
</div>
<p class="muted">Uploaded photos from both photographers appear together. Customer access requires gallery readiness. Video upload status and HOLD are managed separately.</p>
@if($stale)<p class="card card-pad">These photos use an earlier programme. Reconciliation is needed before customer release.</p>@endif
<nav aria-label="Gallery folders" class="toolbar" style="margin-bottom:20px">
    <a href="{{ route('admin.competition-gallery.show', $event) }}">{{ $eventCode }}</a>
    @foreach($breadcrumbs as $crumb)
        <span aria-hidden="true">/</span>
        @if($loop->last)<strong aria-current="page">{{ $crumb['label'] }}</strong>
        @else<a href="{{ route('admin.competition-gallery.show', ['schedulingEvent' => $event, 'folder' => $crumb['uuid']]) }}">{{ $crumb['label'] }}</a>@endif
    @endforeach
</nav>
@if($selectedSection && isset($sectionReadiness[$selectedSection]))
    @include('admin.programmes._section-readiness', ['readiness' => $sectionReadiness[$selectedSection]])
@endif
@if($children)
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px">
        @foreach($children as $child)
            <a class="card card-pad" style="display:block" href="{{ route('admin.competition-gallery.show', ['schedulingEvent' => $event, 'folder' => $child['uuid']]) }}"><strong>{{ $child['label'] }}</strong>
                @if($child['kind'] === 'section')
                    <div style="margin-top:8px" class="muted">{{ $sectionReadiness[$child['uuid']]['label'] ?? 'Awaiting uploader preparation' }}</div>
                @endif
            </a>
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
                <figure class="card" style="margin:0;overflow:hidden">
                    <a href="{{ route('admin.competition-gallery.image', ['schedulingEvent' => $event, 'photo' => $photo->uuid]) }}" target="_blank" rel="noopener" aria-label="Open preview of {{ $photo->file_name }}">
                        <img src="{{ route('admin.competition-gallery.image', ['schedulingEvent' => $event, 'photo' => $photo->uuid, 'size' => 'thumbnail']) }}" alt="{{ $photo->file_name }}" loading="lazy" style="display:block;width:100%;height:auto">
                    </a>
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
@endsection
