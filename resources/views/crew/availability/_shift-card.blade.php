@php
    $shift = $assignment->shift;
    $event = $shift->schedulingEvent;
    $bookingItem = $event->concertBookingItem;
    $venue = $event->venue;
    $shiftDate = $shift->shift_date ?? $event->event_date;
    $address = $venue ? collect([$venue->address_line_1, $venue->address_line_2, $venue->suburb, $venue->state, $venue->postcode])->filter()->join(', ') : null;
@endphp
<article class="card shift-card">
    <div class="shift-card-top"><div class="date-tile"><strong>{{ $shiftDate->format('d') }}</strong><span>{{ strtoupper($shiftDate->format('M')) }}</span></div><div class="event-visual">@if($eventLogoUrls->get($event->uuid))<img src="{{ $eventLogoUrls->get($event->uuid) }}" alt="{{ $event->name }} logo">@else<span class="event-image-label">Image</span>@endif</div><div class="shift-title"><div class="type-label">{{ $event->event_type->value === 'competition' ? 'COMP-'.($shift->period?->value === 'morning' ? 'M' : 'A') : 'CON' }}</div><h3>{{ $event->name }}</h3>@if($bookingItem?->title)<div class="muted">{{ $bookingItem->title }}</div>@endif</div><div style="display:grid;justify-items:end;gap:5px"><span class="status-pill {{ $assignment->acknowledgement_status === 'acknowledged' ? 'done' : 'attention' }}">{{ $assignment->acknowledgement_status === 'acknowledged' ? '✓ Acknowledged' : 'Please acknowledge' }}</span>@if($assignment->coverRequests->where('status','open')->isNotEmpty())<span class="status-pill attention">Cover Requested</span>@endif</div></div>
    <div class="detail-grid"><div><span class="detail-label">Your role</span><strong>{{ $assignment->role->name }} @if($assignment->is_team_leader) · 👑 Team Leader @endif</strong></div><div><span class="detail-label">Time</span><strong>Arrive {{ $shift->posted_arrival_at?->format('g:i a') ?? 'TBC' }}</strong><span>{{ $shift->starts_at?->format('g:i a') ?? 'TBC' }}–{{ $shift->estimated_finish_at?->format('g:i a') ?? 'TBC' }}</span></div><div><span class="detail-label">Venue</span><strong>{{ $venue?->name ?? 'Venue TBC' }}</strong>@if($address)<a href="https://www.google.com/maps/search/?api=1&amp;query={{ urlencode($address) }}" target="_blank" rel="noopener">Open map ↗</a>@endif</div></div>
    @if($venue?->parking_notes || $venue?->access_notes || $venue?->operational_notes)<details class="venue-details"><summary>Parking, access and venue information</summary>@if($venue->parking_notes)<p><strong>Parking:</strong> {{ $venue->parking_notes }}</p>@endif @if($venue->access_notes)<p><strong>Access:</strong> {{ $venue->access_notes }}</p>@endif @if($venue->operational_notes)<p><strong>Operational notes:</strong> {{ $venue->operational_notes }}</p>@endif</details>@endif
    @if($assignment->equipmentResponsibilities->isNotEmpty())
    <div class="responsibilities"><span class="detail-label">Equipment and media</span>
        @foreach($assignment->equipmentResponsibilities as $responsibility)
        @php($item = $equipmentItems[$responsibility->item_code] ?? ['•', ucfirst(str_replace('_', ' ', $responsibility->item_code))])
        <div><strong>{{ $responsibility->is_bringing ? '➡️' : '' }}{{ $item[0] }}{{ $responsibility->is_taking ? '➡️' : '' }} {{ $item[1] }}</strong>@if($responsibility->other_notes)<span>{{ $responsibility->other_notes }}</span>@endif</div>
        @endforeach
    </div>
    @endif
    <div class="resource-actions" style="margin-top:11px"><a class="button secondary" href="{{ route('crew.assignments.show',$assignment) }}">Details</a>@if(!$shiftDate->lt(today()))<a class="button secondary" href="{{ $assignment->coverRequests->where('status','open')->isNotEmpty()?route('crew.availability.index',['view'=>'cover']):route('crew.cover.create',$assignment) }}">Cover</a>@else<span class="button secondary" aria-disabled="true" style="opacity:.45;cursor:not-allowed">Cover</span>@endif<a class="button secondary" href="{{ route('crew.help.index') }}">Handbook</a></div>
    @if($assignment->acknowledgement_status !== 'acknowledged')<div class="shift-action">@if($assignment->acknowledgement_status === 'reset_by_material_change')<p>Important details changed. Please check them again.</p>@else<p>An assignment is not an offer to accept or decline. Please confirm that you have seen it.</p>@endif<form method="POST" action="{{ route('crew.assignments.acknowledge', $assignment) }}">@csrf<button>Acknowledge shift</button></form></div>@endif
</article>
