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
@if($type === 'concert')
@include('admin.media._concert-studio-browser', ['topPicsOnly' => true, 'indexRoute' => 'admin.top-pics.index', 'eventRoute' => 'admin.top-pics.show'])
@else
<div class="gallery-navigation-grid">
@forelse($events as $event)
<x-gallery-navigation-card :title="$event->name" :image="route('admin.competition-gallery.image', ['schedulingEvent' => $event, 'photo' => $event->cover_uuid, 'size' => 'cover'])" :subtitle="$event->event_date?->format('j M Y').' · '.$event->top_pic_count.' Top '.Str::plural('Pic', $event->top_pic_count)" :href="route('admin.top-pics.show', $event)" action="View Top Pics" />
@empty
<section class="card card-pad"><h2>No Top Pics yet</h2><p>Star photos in the uploader and upload them to see each {{ $type }} here.</p></section>
@endforelse
</div>
<nav class="top-pics-pagination" aria-label="Event pages">
@if($events->previousPageUrl())<a class="button secondary" href="{{ $events->previousPageUrl() }}">Previous</a>@endif
<span>Page {{ $events->currentPage() }} of {{ $events->lastPage() }}</span>
@if($events->nextPageUrl())<a class="button secondary" href="{{ $events->nextPageUrl() }}">Next</a>@endif
</nav>
@endif
@endsection
