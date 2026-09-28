@extends('layouts.admin', ['title' => 'Publicity · Top Pics', 'heading' => 'Publicity', 'subheading' => 'Top Pics and event screens'])
@section('content')
@include('admin.media._publicity-tabs')
@include('admin.media._backend-gallery-styles')
<style>
.top-pics-switch{display:inline-flex;padding:4px;gap:4px;border:1px solid var(--line);border-radius:999px;background:#e5eff4;margin-bottom:22px}.top-pics-switch a{padding:10px 22px;border-radius:999px;font-weight:800;color:var(--ink);text-decoration:none;transition:background .18s,color .18s}.top-pics-switch a:hover{background:#d3e9f4}.top-pics-switch a[aria-current=page]{background:var(--brand);color:#fff}.top-pics-pagination{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-top:24px}
</style>
<nav class="top-pics-switch" aria-label="Event type">
@foreach(['competition' => 'Competitions', 'concert' => 'Concerts'] as $value => $label)
<a href="{{ route('admin.top-pics.index', ['type' => $value]) }}" @if($type === $value) aria-current="page" @endif>{{ $label }}</a>
@endforeach
</nav>
<div class="backend-gallery-grid">
@forelse($events as $event)
<a class="card backend-gallery-card" href="{{ route('admin.top-pics.show', $event) }}">
    <div class="backend-gallery-card-media"><img src="{{ route($type === 'competition' ? 'admin.competition-gallery.image' : 'admin.concert-photo-gallery.image', ['schedulingEvent' => $event, 'photo' => $event->cover_uuid, 'size' => 'thumbnail']) }}" alt="" loading="lazy"></div>
    <div class="backend-gallery-card-body"><strong class="backend-gallery-card-title">{{ $event->name }}</strong>
        @if($type === 'concert')<div class="backend-gallery-card-meta">{{ $event->studio?->name }}</div>@endif
        <div class="backend-gallery-card-meta">{{ $event->event_date?->format('j M Y') }} · {{ $event->top_pic_count }} Top {{ $event->top_pic_count == 1 ? 'Pic' : 'Pics' }}</div>
    </div>
</a>
@empty
<section class="card card-pad"><h2>No Top Pics yet</h2><p>Star photos in the uploader and upload them to see each {{ $type }} here.</p></section>
@endforelse
</div>
<nav class="top-pics-pagination" aria-label="Event pages">
@if($events->previousPageUrl())<a class="button secondary" href="{{ $events->previousPageUrl() }}">Previous</a>@endif
<span>Page {{ $events->currentPage() }} of {{ $events->lastPage() }}</span>
@if($events->nextPageUrl())<a class="button secondary" href="{{ $events->nextPageUrl() }}">Next</a>@endif
</nav>
@endsection
