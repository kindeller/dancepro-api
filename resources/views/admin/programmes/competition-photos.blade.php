@extends('layouts.admin', ['title' => 'Competition Photos', 'heading' => 'Competition Photos', 'subheading' => 'Choose a competition to browse its photo gallery.'])

@section('content')
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px">
    @forelse($events as $event)
        <a class="card card-pad" style="display:block" href="{{ route('admin.competition-gallery.show', $event) }}">
            <div style="margin-bottom:14px"><x-event-artwork :url="$presentation[$event->uuid]['cover_url']" :name="$presentation[$event->uuid]['title']" size="large" /></div>
            <strong>{{ $presentation[$event->uuid]['title'] }}</strong>
            <div class="muted">{{ $event->eventCode() }} · {{ $event->event_date?->format('j M Y') }}</div>
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
