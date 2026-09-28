@extends('layouts.admin', ['title' => 'Competition photos', 'heading' => 'Competition photos', 'subheading' => 'Choose a competition to browse its photo gallery.'])

@section('content')
@include('admin.media._backend-gallery-styles')
<div class="backend-gallery-grid">
    @forelse($events as $event)
        <a class="card backend-gallery-card" href="{{ route('admin.competition-gallery.show', $event) }}">
            <div class="backend-gallery-card-media"><x-event-artwork :url="$event->crewLogoUrl()" :name="$presentation[$event->uuid]['title']" size="large" /></div>
            <div class="backend-gallery-card-body"><strong class="backend-gallery-card-title">{{ $presentation[$event->uuid]['title'] }}</strong>
            <div class="backend-gallery-card-meta">{{ $event->eventCode() }} · {{ $event->event_date?->format('j M Y') }}</div></div>
        </a>
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
