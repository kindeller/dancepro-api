@extends('layouts.admin', ['title' => 'Competition photos', 'heading' => 'Competition photos', 'subheading' => 'Choose a competition to browse its photo gallery.'])

@section('content')
@include('admin.media._backend-gallery-styles')
<div class="gallery-navigation-grid">
    @forelse($events as $event)
        <x-gallery-navigation-card :title="$presentation[$event->uuid]['title']" :image="$event->crewLogoUrl()" :logo="true" :subtitle="$event->eventCode().' · '.$event->event_date?->format('j M Y')" :href="route('admin.competition-gallery.show', $event)" />
    @empty
        <p class="card card-pad">No competitions yet.</p>
    @endforelse
</div>
@if($events->hasPages())
<nav aria-label="Competition pages" class="toolbar" style="margin-top:20px">
    @if($events->previousPageUrl())<a class="button secondary" href="{{ $events->previousPageUrl() }}">Previous</a>@endif
    <span>Page {{ $events->currentPage() }} of {{ $events->lastPage() }}</span>
    @if($events->nextPageUrl())<a class="button secondary" href="{{ $events->nextPageUrl() }}">Next</a>@endif
</nav>
@endif
@endsection
