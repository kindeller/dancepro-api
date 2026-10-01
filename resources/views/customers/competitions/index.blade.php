@extends('layouts.public', ['title' => 'My Competitions', 'footerLabel' => 'Competition media'])

@section('content')
<section class="hero"><div class="container">@include('customers._heading', ['eyebrow' => 'DancePro Media', 'heading' => 'My Competitions', 'description' => 'Choose your event and enter its login code.'])</div></section>
<section class="section"><div class="container">
    <p><a href="{{ route('customer.media.index') }}">← My Media</a></p>
    <form method="POST" action="{{ route('customer.competitions.store') }}" class="card card-body" style="margin-bottom:28px;max-width:520px">
        @csrf
        <label for="competition-event">Event
            <select id="competition-event" name="event_uuid" required>
                <option value="">Select an event</option>
                @foreach($availableEvents as $availableEvent)
                    <option value="{{ $availableEvent->uuid }}" @selected(old('event_uuid') === $availableEvent->uuid)>{{ $availableEvent->name }} · {{ $availableEvent->event_date?->format('j M Y') }}</option>
                @endforeach
            </select>
        </label>
        @error('event_uuid')<p role="alert" style="color:#b42318">{{ $message }}</p>@enderror
        <label for="login-code">Event media access code<input id="login-code" type="password" name="login_code" required maxlength="64" autocomplete="off"></label>
        @error('login_code')<p role="alert" style="color:#b42318">{{ $message }}</p>@enderror
        @if($availableEvents->isEmpty())<p>No events are available to add yet.</p>@endif
        <button class="button">Add competition</button>
    </form>
    @if($registrations->isNotEmpty())
        <div class="gallery-navigation-grid">
            @foreach($registrations as $registration)
                @php($event = $registration->event)
                <x-gallery-navigation-card :title="$presentation[$event->uuid]['title']" :image="$event->crewLogoUrl()" :logo="true"
                    :subtitle="$event->event_date?->format('j M Y') ?? 'Date to be confirmed'"
                    :href="route('customer.competitions.show', $event)" action="View event" />
            @endforeach
        </div>
    @else<div class="empty"><h2>Your competitions will appear here</h2><p>Select your event above and use the login code supplied for that event.</p></div>@endif
    @if($registrations->hasPages())
        <nav aria-label="Competition pages" style="display:flex;gap:16px;margin-top:24px">
            @if($registrations->previousPageUrl())<a class="button secondary" href="{{ $registrations->previousPageUrl() }}">Previous</a>@endif
            @if($registrations->nextPageUrl())<a class="button secondary" href="{{ $registrations->nextPageUrl() }}">More competitions</a>@endif
        </nav>
    @endif
</div></section>
@endsection
