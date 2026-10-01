@foreach($hub['pendingEvents'] as $item)
    <article class="card card-body" style="margin-bottom:16px">
        <span class="hub-badge {{ $item->booking->status->value === 'received' ? 'warning' : ($item->booking->status->value === 'partial_approval' ? 'success' : 'danger') }}">{{ $item->booking->status->label() }}</span>
        <h2>{{ $item->title ?: ($item->item_type->value === 'dress_rehearsal' ? 'DR Portrait' : 'Concert') }}</h2>
        <div class="hub-event-meta"><span>📅 {{ $item->event_date->format('D j M Y') }}</span><span>🕒 {{ \Carbon\Carbon::parse($item->starts_at)->format('g:i a') }} – {{ \Carbon\Carbon::parse($item->finishes_at)->format('g:i a') }}</span><span>📍 {{ $item->venue_name }}</span></div>
        @if($item->booking->status->value !== 'received')<p class="muted">DancePro is reviewing this request. Your event is not yet confirmed; we will notify you when this event is approved.</p>@endif
        <div class="hub-actions"><a class="button secondary" href="{{ route('customer.studio-bookings.submission', [$studio, $item->booking]) }}">View booking</a><a class="button secondary" href="{{ route('customer.studio-bookings.show', [$studio, $item->booking]) }}">My timeline</a></div>
    </article>
@endforeach
