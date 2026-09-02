@extends('layouts.admin', ['title' => 'Pending events', 'heading' => 'Pending events', 'subheading' => 'Review booked events before they enter scheduling and availability.'])

@section('content')
    @include('admin.event-management._tabs')
    <form method="POST" action="{{ route('admin.concert-booking-events.bulk-status') }}">
        @csrf @method('PATCH')
        <input type="hidden" name="action" value="approve">
        <div class="toolbar"><div class="muted">Approval creates draft scheduling events. Availability stays closed.</div><button type="submit">Approve selected</button></div>
        <div class="card"><table><thead><tr><th><input type="checkbox" id="select-all" aria-label="Select every pending event"></th><th>Date</th><th>Event</th><th>Studio</th><th>Venue</th><th>Status</th><th></th></tr></thead><tbody>
            @forelse($items as $item)
                <tr><td><input class="event-checkbox" type="checkbox" name="event_ids[]" value="{{ $item->uuid }}"></td><td><strong>{{ $item->event_date->format('D j M Y') }}</strong><div class="muted">{{ \Carbon\Carbon::parse($item->starts_at)->format('g:i a') }}–{{ \Carbon\Carbon::parse($item->finishes_at)->format('g:i a') }}</div></td><td><strong>{{ $item->title ?: 'Dress rehearsal' }}</strong><div class="muted">{{ $item->eventTypeDefinition?->name ?: str($item->item_type->value)->replace('_', ' ')->title() }} · {{ str($item->item_type->value)->replace('_', ' ')->title() }}</div></td><td>{{ $item->booking->studio_name }}<div class="muted">{{ $item->booking->contact_name }} · {{ $item->booking->contact_phone }}</div></td><td>{{ $item->venue?->name ?: $item->venue_name }}<div class="muted">{{ $item->venue_address }}</div></td><td><span class="badge pending">Pending approval</span></td><td><a class="button secondary" href="{{ route('admin.concert-bookings.show', $item->booking) }}">Review</a></td></tr>
            @empty<tr><td colspan="7" class="muted">No events are waiting for approval.</td></tr>@endforelse
        </tbody></table><div class="pagination"><x-admin-pagination :paginator="$items" /></div></div>
    </form>
@endsection

@push('scripts')
<script>const selectAll=document.getElementById('select-all');const eventCheckboxes=[...document.querySelectorAll('.event-checkbox')];selectAll?.addEventListener('change',()=>eventCheckboxes.forEach(checkbox=>checkbox.checked=selectAll.checked));</script>
@endpush
