@php
    $columnEvent = $row['event'];
    $columnItem = $row['pending'] ? $row['bookingItem'] : $columnEvent->concertBookingItem;
    $columnStudio = $row['pending'] ? $columnItem->booking->studio : $columnEvent->studio;
    $studioName = $columnStudio?->name ?? $columnItem?->booking?->studio_name ?? 'Studio not set';
    $eventTitle = $columnItem?->title ?: ($columnEvent?->name ?: ($columnItem?->item_type->value === 'dress_rehearsal' ? 'Dress rehearsal portraits' : 'Concert'));
    $eventUrl = $row['pending'] ? route('admin.concert-bookings.show', $columnItem->booking) : route('admin.scheduling-events.show', $columnEvent);
    $communicationsUrl = $columnEvent ? route('admin.concert-tracker.communications.show', $columnEvent) : ($columnStudio ? route('admin.concert-tracker.studio-communications.show', $columnStudio) : null);
    $galleryLogin = $columnStudio ? $galleryLogins->get($columnStudio->id) : null;
@endphp
<a @class(['tracker-event-heading', 'tracker-pending-heading' => $row['pending']]) href="{{ $eventUrl }}"><strong>{{ $studioName }}</strong></a>
<span class="tracker-event-title">{{ $eventTitle }}</span>
<small>{{ $row['event_date']?->format('D j M Y') ?? 'Date not set' }}</small>
<div class="tracker-event-tools">
    @if($communicationsUrl)
        <a class="tracker-communications" href="{{ $communicationsUrl }}" aria-label="Communications for {{ $eventTitle }}" title="Communications">
            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 6 9 7 9-7"/></svg>
        </a>
    @endif
    <span class="tracker-gallery-login" title="Studio photo gallery login code">
        @if($galleryLogin['value'] ?? null)<span>Gallery:</span><code>{{ $galleryLogin['value'] }}</code>
        @else<span>{{ $galleryLogin ? 'Gallery code unavailable' : 'Gallery code not set' }}</span>@endif
    </span>
</div>
