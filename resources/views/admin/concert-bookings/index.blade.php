@extends('layouts.admin', ['title' => 'Event bookings', 'heading' => 'Event bookings', 'subheading' => 'View event requests submitted through the booking form.'])

@section('content')
    @include('admin.event-management._tabs')
    <div class="toolbar">
        <div class="muted">Select events from any studios, then update them together.</div>
        <div><a class="button secondary" href="{{ route('concert-bookings.create') }}">Open booking form</a> <a class="button" href="{{ route('admin.scheduling-events.create') }}">Add event</a></div>
    </div>

    <form method="POST" action="{{ route('admin.concert-booking-events.bulk-status') }}" id="bulk-event-form">
        @csrf
        @method('PATCH')
        <div class="card card-pad" style="margin-bottom:16px">
            <div class="filters">
                <label>Change selected events to
                    <select name="action" id="bulk-action" required>
                        <option value="">Choose status</option>
                        <option value="approve" @selected(old('action') === 'approve')>Approved</option>
                        <option value="open" @selected(old('action') === 'open')>Availability open</option>
                        <option value="close" @selected(old('action') === 'close')>Availability closed</option>
                    </select>
                </label>
                <label id="deadline-field">Deadline date <span class="muted">(always 5:00 pm)</span>
                    <input type="date" name="deadline_date" value="{{ old('deadline_date') }}">
                </label>
                <button type="submit">Apply to selected</button>
            </div>
            <div class="muted" style="margin-top:8px">Approving creates the event but keeps availability closed until you open it.</div>
        </div>

        <div class="card">
            <table>
                <thead><tr><th><input type="checkbox" id="select-all" aria-label="Select every event on this page"></th><th>Date</th><th>Event</th><th>Studio</th><th>Venue</th><th>Booking</th><th>Availability</th><th></th></tr></thead>
                <tbody>
                    @forelse($items as $item)
                        <tr>
                            <td><input class="event-checkbox" type="checkbox" name="event_ids[]" value="{{ $item->uuid }}" @checked(in_array($item->uuid, old('event_ids', [])))></td>
                            <td><strong>{{ $item->event_date->format('D j M') }}</strong><div class="muted">{{ \Carbon\Carbon::parse($item->starts_at)->format('g:i a') }}–{{ \Carbon\Carbon::parse($item->finishes_at)->format('g:i a') }}</div></td>
                            <td><strong>{{ $item->title ?: 'Dress rehearsal' }}</strong><div class="muted">{{ $item->eventTypeDefinition?->name ?: str($item->item_type->value)->replace('_', ' ')->title() }} · {{ str($item->item_type->value)->replace('_', ' ')->title() }}</div></td>
                            <td>{{ $item->booking->studio_name }}<div class="muted">{{ $item->booking->contact_name }}</div></td>
                            <td>{{ $item->venue_name }}<div class="muted">{{ $item->venue_address }}</div></td>
                            <td><span class="badge">{{ $item->approval_status }}</span></td>
                            <td>
                                @if($item->schedulingEvent)
                                    <span class="badge">{{ $item->schedulingEvent->availability_status->value }}</span>
                                    @if($item->schedulingEvent->availability_deadline)<div class="muted">Due {{ $item->schedulingEvent->availability_deadline->format('j M, g:i a') }}</div>@endif
                                @else
                                    <span class="muted">Not created</span>
                                @endif
                            </td>
                            <td><a class="button secondary" href="{{ route('admin.concert-bookings.show', $item->booking) }}">Review</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="muted">No concert events have been submitted yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="pagination"><x-admin-pagination :paginator="$items" /></div>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        const selectAll = document.getElementById('select-all');
        const eventCheckboxes = [...document.querySelectorAll('.event-checkbox')];
        const action = document.getElementById('bulk-action');
        const deadlineField = document.getElementById('deadline-field');
        const deadlineInput = deadlineField.querySelector('input');

        selectAll?.addEventListener('change', () => eventCheckboxes.forEach((checkbox) => checkbox.checked = selectAll.checked));

        function updateDeadline() {
            const opening = action.value === 'open';
            deadlineField.hidden = !opening;
            deadlineInput.required = opening;
        }

        action.addEventListener('change', updateDeadline);
        updateDeadline();
    </script>
@endpush
