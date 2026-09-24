@extends('layouts.admin', ['title' => 'Concert Photos', 'heading' => 'Concert Photos', 'subheading' => 'Set up and review customer concert photo galleries'])

@section('content')
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px">
@forelse($events as $event)
    @php($concert = $event->concertBookingItem?->concert)
    <a class="card card-pad" href="{{ route('admin.concert-photo-gallery.show', $event) }}" style="display:block;text-decoration:none">
        <div style="margin-bottom:14px"><x-event-artwork :url="$event->crewLogoUrl()" :name="$event->name" size="large" /></div>
        <strong>{{ $event->name }}</strong><div class="muted">{{ $event->studio?->name }} · {{ $event->event_date?->format('j M Y') }}</div>
        <p style="margin-bottom:0">@if(!$concert)<strong style="color:#a62232">Customer concert record needs linking</strong>@elseif($concert->mediaCollections->where('media_type.value', 'photo')->isEmpty())Gallery not set up@else{{ $concert->mediaCollections->where('media_type.value', 'photo')->count() }} photo album(s)@endif</p>
    </a>
@empty<p class="card card-pad">No scheduled concert events yet.</p>@endforelse
</div>
@if($events->hasPages())<nav class="toolbar" aria-label="Concert photo pages" style="margin-top:20px">@if($events->previousPageUrl())<a class="button secondary" href="{{ $events->previousPageUrl() }}">Previous</a>@endif<span>Page {{ $events->currentPage() }} of {{ $events->lastPage() }}</span>@if($events->nextPageUrl())<a class="button secondary" href="{{ $events->nextPageUrl() }}">Next</a>@endif</nav>@endif
@endsection
