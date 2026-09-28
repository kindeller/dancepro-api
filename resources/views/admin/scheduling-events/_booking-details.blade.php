@php
    $bookingItem = $event->concertBookingItem;
    $booking = $bookingItem?->booking;
@endphp
@if($booking)
<section class="card card-pad" style="margin-bottom:20px">
    <div class="toolbar">
        <div><p class="eyebrow">Booking details</p><h2>{{ $bookingItem->title ?: ($bookingItem->item_type->value === 'dress_rehearsal' ? 'DR Portrait' : 'Concert') }}</h2></div>
        <a class="button" href="{{ route('admin.concert-bookings.show', $booking) }}#event-{{ $bookingItem->uuid }}">Edit booking</a>
    </div>
    <div class="event-summary-layout">
        <div class="event-summary-artwork"><x-event-artwork :url="$event->crewLogoUrl()" :name="$event->name" size="large" /></div>
        <div class="grid two-col event-summary-main">
        <div>
            <h3>Studio and contact</h3>
            <dl class="detail-list">
                <dt>Studio</dt><dd>{{ $booking->studio_name }}</dd>
                <dt>Contact</dt><dd>{{ $booking->contact_name ?: 'Not supplied' }}</dd>
                <dt>Email</dt><dd>@if($booking->contact_email)<a href="mailto:{{ $booking->contact_email }}">{{ $booking->contact_email }}</a>@else Not supplied @endif</dd>
                <dt>Phone</dt><dd>@if($booking->contact_phone)<a href="tel:{{ $booking->contact_phone }}">{{ $booking->contact_phone }}</a>@else Not supplied @endif</dd>
                <dt>Booking status</dt><dd>{{ ucfirst($booking->status->value) }}</dd>
                <dt>Event approval</dt><dd>{{ ucfirst($bookingItem->approval_status) }}</dd>
            </dl>
        </div>
        <div>
            <h3>Booked event</h3>
            <dl class="detail-list">
                <dt>Event code</dt><dd>{{ $event->eventCode() ?: 'Not set — no saved studio code' }}</dd>
                <dt>Type</dt><dd>{{ $event->eventTypeDefinition?->name ?: ($bookingItem->item_type->value === 'dress_rehearsal' ? 'DR Portrait' : 'Concert') }}</dd>
                <dt>Date</dt><dd>{{ $bookingItem->event_date?->format('D j M Y') ?: 'TBC' }}</dd>
                <dt>Booked start / finish</dt><dd>{{ substr($bookingItem->starts_at ?? '', 0, 5) ?: 'TBC' }}–{{ substr($bookingItem->finishes_at ?? '', 0, 5) ?: 'TBC' }}</dd>
                <dt>Venue</dt><dd>{{ $bookingItem->venue_name ?: $event->venue?->name ?: 'Not supplied' }}</dd>
                <dt>Venue address</dt><dd>{{ $bookingItem->venue_address ?: 'Not supplied' }}</dd>
                <dt>Booking programme</dt><dd>@if($bookingItem->programme_path)<a href="{{ route('admin.concert-booking-events.programme.show', $bookingItem) }}" target="_blank" rel="noopener">{{ $bookingItem->programme_name ?: 'Open booking programme' }} ↗</a>@else Not attached @endif</dd>
            </dl>
            <p class="muted">Crew arrival and finish including set-down are shown in Schedule below.</p>
        </div>
        </div>
    </div>
    <div class="grid two-col">
        <div>
            <h3>Services and inclusions</h3>
            <dl class="detail-list">
                @foreach(['wants_portrait_photography' => 'Portrait photography', 'wants_concert_photography' => 'Concert photography', 'wants_concert_videography' => 'Concert videography'] as $field => $label)
                    <dt>{{ $label }}</dt><dd>{{ $booking->$field ? 'Yes' : 'No' }}</dd>
                @endforeach
                <dt>Portrait interest</dt><dd>{{ ucfirst($booking->portrait_photography_interest ?: 'Not supplied') }}</dd>
                <dt>Approximate families</dt><dd>{{ $booking->approximate_family_count ?? 'Not supplied' }}</dd>
                <dt>Inclusions</dt><dd>@forelse($booking->concert_inclusions ?? [] as $inclusion)<div>{{ ['presentations_awards' => 'Presentations / awards', 'tap_dance' => 'Tap dance'][$inclusion] ?? str($inclusion)->replace('_', ' ')->title() }}</div>@empty Not supplied @endforelse</dd>
            </dl>
        </div>
        <div>
            <h3>Delivery and feedback</h3>
            <dl class="detail-list">
                <dt>Multiple concerts</dt><dd>{{ $booking->multiple_concert_relationship ?: 'Not supplied' }}</dd>
                <dt>Postal address</dt><dd style="white-space:pre-line">{{ $booking->postal_address ?: 'Not supplied' }}</dd>
                <dt>Feedback and requests</dt><dd style="white-space:pre-line">{{ $booking->previous_video_feedback ?: 'Not supplied' }}</dd>
            </dl>
        </div>
    </div>
    <details style="margin-top:16px">
        <summary>Booking confirmations and internal notes</summary>
        <h3>Accepted requirements</h3>
        <div class="filters">
            @forelse($booking->accepted_requirements ?? [] as $requirement)
                <span class="badge">{{ ['accurate'=>'Information is accurate', 'portrait_space'=>'Portrait space', 'no_personal_backdrop_photos'=>'No personal backdrop photos', 'minimum_spend'=>'Minimum spend', 'photographer_seat'=>'Photographer position', 'no_audience_recording'=>'No audience recording', 'promote_gallery'=>'Gallery promotion', 'credit_images'=>'Image credits', 'video_access'=>'Early video access', 'video_space_audio'=>'Video space and audio', 'programme_invoice'=>'Programme and invoice'][$requirement] ?? str($requirement)->replace('_', ' ')->title() }}</span>
            @empty<span class="muted">None recorded.</span>@endforelse
        </div>
        <h3>Internal booking note</h3><p style="white-space:pre-line">{{ $booking->internal_review_note ?: 'No internal booking note.' }}</p>
    </details>
    @if($booking->items->count() > 1)
        <details style="margin-top:16px"><summary>Other events in this booking ({{ $booking->items->count() - 1 }})</summary>
            @foreach($booking->items->where('id', '!=', $bookingItem->id) as $relatedItem)
                <p>@if($relatedItem->schedulingEvent)<a href="{{ route('admin.scheduling-events.show', $relatedItem->schedulingEvent) }}">{{ $relatedItem->title ?: ($relatedItem->item_type->value === 'dress_rehearsal' ? 'DR Portrait' : 'Concert') }}</a>@else{{ $relatedItem->title ?: 'Untitled event' }}@endif · {{ $relatedItem->event_date?->format('D j M Y') }} · {{ ucfirst($relatedItem->approval_status) }}</p>
            @endforeach
        </details>
    @endif
</section>
@else
<section class="card card-pad" style="margin-bottom:20px">
    <div class="event-summary-layout">
        <div class="event-summary-artwork"><x-event-artwork :url="$event->crewLogoUrl()" :name="$event->name" size="large" /></div>
        <div class="event-summary-main">
            <h2>{{ $event->name }}</h2>
            <dl class="detail-list">
                <dt>Event code</dt><dd>{{ $event->eventCode() ?: 'Not set — no saved organisation code' }}</dd>
                <dt>Event type</dt><dd>{{ $event->eventTypeDefinition?->name ?: ucfirst($event->event_type->value) }}</dd>
                <dt>Event date</dt><dd>{{ $event->event_date?->format('D j M Y') ?: 'TBC' }}</dd>
                <dt>Venue</dt><dd>{{ $event->venue?->name ?: 'Not supplied' }}</dd>
                <dt>Organiser</dt><dd>{{ $event->organiser_name ?: 'Not supplied' }}</dd>
                <dt>Email</dt><dd>@if($event->organiser_email)<a href="mailto:{{ $event->organiser_email }}">{{ $event->organiser_email }}</a>@else Not supplied @endif</dd>
                <dt>Phone</dt><dd>@if($event->organiser_phone)<a href="tel:{{ $event->organiser_phone }}">{{ $event->organiser_phone }}</a>@else Not supplied @endif</dd>
            </dl>
        </div>
    </div>
</section>
@endif
<section class="card card-pad" style="margin-bottom:20px">
    <h2>Venue and access</h2>
    @if($event->venue)
        <dl class="detail-list">
            <dt>Venue</dt><dd>{{ $event->venue->name }}</dd>
            <dt>Address</dt><dd>{{ collect([$event->venue->address_line_1, $event->venue->address_line_2, $event->venue->suburb, $event->venue->state, $event->venue->postcode])->filter()->join(', ') ?: 'Not supplied' }}</dd>
            <dt>Access</dt><dd style="white-space:pre-line">{{ $event->venue->access_notes ?: 'No access notes.' }}</dd>
            <dt>Parking</dt><dd style="white-space:pre-line">{{ $event->venue->parking_notes ?: 'No parking notes.' }}</dd>
            <dt>Operational notes</dt><dd style="white-space:pre-line">{{ $event->venue->operational_notes ?: 'No operational notes.' }}</dd>
        </dl>
    @else<p class="muted">No venue selected.</p>@endif
    @if($event->admin_notes)<h3>Internal event notes</h3><p style="white-space:pre-line">{{ $event->admin_notes }}</p>@endif
</section>

@push('styles')
<style nonce="{{ request()->attributes->get('csp_nonce') }}">
    .event-summary-layout { display:grid; grid-template-columns:200px minmax(0,1fr); gap:24px; align-items:start; }
    .event-summary-artwork { padding:12px; border:1px solid var(--line); border-radius:8px; background:#fff; }
    .event-summary-artwork .event-artwork { width:100%; height:180px; }
    .event-summary-main { min-width:0; }
    .event-summary-main h3 { margin-top:0; }
    .event-summary-main dd { overflow-wrap:anywhere; }
    @media (max-width:760px) { .event-summary-layout { grid-template-columns:1fr; gap:18px; } .event-summary-artwork { width:180px; } }
</style>
@endpush
