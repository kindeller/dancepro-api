@php
    $booking = $item->booking;
    $typeLabel = $item->item_type->value === 'dress_rehearsal' ? 'DR portraits' : 'Concert';
    $start = $item->starts_at ? \Carbon\Carbon::parse($item->starts_at) : null;
    $finish = $item->finishes_at ? \Carbon\Carbon::parse($item->finishes_at) : null;
@endphp
<tr class="pending-booking-row" data-booking-item="{{ $item->uuid }}">
    <td class="sticky-col bulk-select-col" hidden title="Approve this booking before requesting crew availability."></td>
    <td class="sticky-col col-date"><strong>{{ $item->event_date?->format('D j M') }}</strong><div class="muted">{{ $item->event_date?->format('Y') }}</div></td>
    <td class="sticky-col col-time" title="Requested event times; crew arrival and finish are confirmed after approval.">
        <strong>{{ $item->eventTypeDefinition?->code ?: $typeLabel }}</strong>
        <div class="muted">{{ $start?->format('g:i a') ?: 'TBC' }}–{{ $finish?->format('g:i a') ?: 'TBC' }}@if($start && $finish && $finish->lte($start)) (+1 day)@endif</div>
    </td>
    <td class="sticky-col col-event" title="{{ $booking->studio_name }} · {{ $item->title ?: $typeLabel }} · Review pending booking">
        <a href="{{ route('admin.concert-bookings.show', $booking) }}"><strong>{{ $booking->studio_name }}</strong></a>
        <div class="muted">{{ $item->title ?: $typeLabel }}</div>
    </td>
    <td class="sticky-col col-venue" title="{{ $item->venue?->name ?: $item->venue_name }} · {{ $item->venue_address }}">
        {{ $item->venue?->name ?: ($item->venue_name ?: 'Not set') }}
        <div class="muted">{{ $booking->contact_name }} · {{ $booking->contact_phone }}</div>
    </td>
    <td class="sticky-col col-status"><span class="badge pending-booking">Pending approval</span></td>
    <td colspan="3" class="pending-booking-locked">Awaiting booking approval</td>
    @if($crew->isNotEmpty())<td colspan="{{ $crew->count() }}" class="pending-booking-locked">Crew availability not open</td>@endif
</tr>
