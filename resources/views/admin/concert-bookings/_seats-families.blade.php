@php($concertItems = $seatsAndFamilies->concerts($booking))
@if($concertItems->isNotEmpty())
<section class="card card-pad booking-follow-on booking-materials" id="seats-families" style="scroll-margin-top:360px">
    <h2>1.3 Seats &amp; families numbers</h2>
    <p class="muted">Confirm the total unique families, then record the reserved seats for each concert below. The tracker completes this task when all these details are filled. DR portraits do not require this task.</p>
    @include('public.concert-bookings._family-numbers', ['familyNumbersUrl' => route('admin.concert-bookings.families', $booking), 'familyNumbersHeading' => 'Total final families', 'familyNumbersEmbedded' => true])
    @foreach($concertItems as $item)
        @php($event = $item->schedulingEvent)
        <details>
            <summary>{{ $item->title ?: 'Concert' }} · {{ $item->event_date->format('j M Y') }} · {{ $event && $seatsAndFamilies->seatsComplete($event, $booking) ? 'Seats recorded' : 'Seats to confirm' }}</summary>
            @if($event)
                    <form method="post" action="{{ route('admin.concert-booking-events.seats', $item) }}">
                        @csrf<input type="hidden" name="seat_event_uuid" value="{{ $item->uuid }}">
                        @php($seatedRoles = $seatsAndFamilies->roles($event, $booking))
                        @foreach($seatedRoles as $role)
                            @php($savedSeats = collect($event->roleSeats->firstWhere('crew_role_id', $role->id)?->seats ?? [])->groupBy('row')->map(fn ($seats, $row) => 'Row '.$row.': '.$seats->pluck('seat')->join(', '))->join("\n"))
                            <label>{{ $role->name }}<textarea name="roles[{{ $role->code }}][seating]" rows="2" maxlength="500" placeholder="{{ str_contains($role->code, 'videographer') ? 'J16-20' : 'J16-17' }}">{{ old('seat_event_uuid') === $item->uuid ? old('roles.'.$role->code.'.seating', $savedSeats) : $savedSeats }}</textarea><small class="muted">{{ str_contains($role->code, 'videographer') ? '5–6 seats' : '1–2 seats' }}. For example: J16-20 or J16, 18. Use a new line for another row.</small></label>
                            @if($allocation = $event->roleSeats->firstWhere('crew_role_id', $role->id))<small class="muted">Recorded by {{ $allocation->submittedBy?->name ?? 'Former user' }} · {{ $allocation->submitted_at?->format('j M Y, g:i a') }}</small>@endif
                        @endforeach
                        @if($seatedRoles->isNotEmpty())<div><button type="submit">Save seats</button></div>@else<p class="muted">No seated crew roles are required for this event.</p>@endif
                    </form>
            @else
                <p class="muted">Approve this concert to record its reserved seats.</p>
            @endif
        </details>
    @endforeach
</section>
@endif
