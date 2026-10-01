<section class="website-group" id="upcoming-competition-events">
    <h2>Upcoming Competition Events</h2>
    <div class="website-grid">
        @forelse($competitions as $entry)
            @php($event = $entry['event'])
            <article class="card website-card" data-upcoming-competition>
                @if($event->crewLogoUrl())
                    <img class="website-event-logo" loading="lazy" src="{{ $event->crewLogoUrl() }}" alt="{{ $event->name }} logo">
                @else
                    <div class="website-event-logo website-logo-missing" style="display:grid;place-items:center;background:var(--paper);border-radius:8px">Event logo coming soon</div>
                @endif
                <h3>{{ $event->name }}</h3>
                <p class="website-event-date"><time datetime="{{ $entry['start']->toDateString() }}">{{ $entry['start']->format('j M Y') }}</time> – <time datetime="{{ $entry['end']->toDateString() }}">{{ $entry['end']->format('j M Y') }}</time></p>
                <p>{{ $event->venue?->name ?? 'Venue to be confirmed' }}@if($event->venue?->suburb) · {{ $event->venue->suburb }}@endif</p>
            </article>
        @empty
            <p>More competition events will be announced soon.</p>
        @endforelse
    </div>
</section>
@if($concertStudios->isNotEmpty())
    <section class="website-group" id="upcoming-concert-events">
        <h2>Upcoming Concert Events</h2>
        <div class="website-studio-grid">
            @foreach($concertStudios as $studio)
                <div class="card website-studio-logo" title="{{ $studio->name }}" data-upcoming-studio="{{ $studio->uuid }}">
                    @if($studio->logoUrl())
                        <img loading="lazy" src="{{ $studio->logoUrl() }}" alt="{{ $studio->name }}">
                    @else
                        <span class="website-logo-missing">{{ $studio->name }}<br><small>Logo coming soon</small></span>
                    @endif
                </div>
            @endforeach
        </div>
    </section>
@endif
