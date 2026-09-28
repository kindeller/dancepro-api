@if($event->studioBookingChangeRequests->isNotEmpty())
<section class="card card-pad" style="margin-bottom:20px">
    <h2>Studio booking changes</h2>
    @foreach($event->studioBookingChangeRequests as $change)
        <article style="padding:12px 0;border-top:1px solid var(--line)">
            <strong>{{ str($change->status)->replace('_', ' ')->title() }}</strong>
            <p style="white-space:pre-line">{{ $change->requested_value }}</p>
            <p class="muted">Requested by {{ $change->requestedBy?->name }} · {{ $change->created_at->format('j M Y, g:i a') }}</p>
            @if($change->details)<p>{{ $change->details }}</p>@endif
            @if($change->resolution_note)<p><strong>Resolution:</strong> {{ $change->resolution_note }}</p>@endif
            @if($change->status !== 'completed')
                <div class="toolbar">
                    @if($event->concertBookingItem && in_array($change->status, ['requested', 'in_review']))
                        <a class="button secondary" href="{{ route('admin.concert-bookings.show', [$event->concertBookingItem->booking, 'change_request' => $change->uuid]) }}">Review and apply changes</a>
                    @endif
                    <form method="post" action="{{ route('admin.concert-tracker.booking-changes.review', $change) }}" class="filters">
                        @csrf @method('PATCH')
                        <label>Request status<select name="status">@foreach(['requested' => 'Requested', 'in_review' => 'In review', 'declined' => 'Declined'] as $value => $label)<option value="{{ $value }}" @selected($change->status === $value)>{{ $label }}</option>@endforeach</select></label>
                        <button>Update status</button>
                    </form>
                </div>
            @endif
        </article>
    @endforeach
</section>
@endif
