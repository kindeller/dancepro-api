<div class="hub-actions" style="justify-content:flex-end;margin:0 0 20px">
    @if($latestBooking)<a class="button secondary" href="{{ route('customer.studio-bookings.submission', [$studio, $latestBooking]) }}">My booking</a>@endif
    <a class="button secondary" href="{{ $latestBooking ? route('customer.studio-bookings.show', [$studio, $latestBooking]) : route('customer.studio-bookings.index', $studio) }}">My timeline</a>
    <a class="button" href="{{ route('concert-bookings.create', ['studio' => $studio->uuid]) }}">{{ !empty($bookingDraft) ? 'Continue booking' : 'Make booking' }}</a>
</div>
