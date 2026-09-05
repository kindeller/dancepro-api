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
                            <td><span class="badge {{ $item->approval_status }}">{{ $item->approval_status === 'pending' ? 'Pending approval' : ucfirst($item->approval_status) }}</span></td>
                            <td>
                                @if($item->schedulingEvent)
                                    <span class="badge">{{ $item->schedulingEvent->availability_status->value }}</span>
                                    @if($item->schedulingEvent->availability_deadline)<div class="muted">Due {{ $item->schedulingEvent->availability_deadline->format('j M, g:i a') }}</div>@endif
                                @else
                                    <span class="muted">Not created</span>
                                @endif
                            </td>
                            <td><div class="booking-row-actions">
                                @if($item->programme_path)
                                    <details class="programme-menu">
                                        <summary class="programme-icon attached" title="Programme attached" aria-label="Programme attached">▤</summary>
                                        <div><a href="{{ route('admin.concert-booking-events.programme.show', $item) }}" target="_blank">Open Programme</a><button type="button" data-programme-picker="programme-{{ $item->uuid }}">Replace Programme</button></div>
                                    </details>
                                @else
                                    <button class="programme-icon" type="button" title="Attach Programme" aria-label="Attach Programme" data-programme-picker="programme-{{ $item->uuid }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8.5 12.5 14.8 6.2a3 3 0 0 1 4.2 4.2l-8.1 8.1a5 5 0 0 1-7.1-7.1l8.5-8.5M7.1 14l8.2-8.2"/></svg></button>
                                @endif
                                <a class="button secondary" href="{{ route('admin.concert-bookings.show', $item->booking) }}">{{ $item->approval_status === 'pending' ? 'Review' : 'Edit' }}</a>
                            </div></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="muted">No concert events have been submitted yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="pagination"><x-admin-pagination :paginator="$items" /></div>
        </div>
    </form>

    @foreach($items as $item)
        <form class="programme-upload-form" id="programme-{{ $item->uuid }}" method="POST" enctype="multipart/form-data" action="{{ route('admin.concert-booking-events.programme.store', $item) }}">
            @csrf
            <input type="file" name="programme" accept="application/pdf,.pdf" required data-programme-input>
        </form>
    @endforeach
@endsection

@push('scripts')
    <script nonce="{{ request()->attributes->get('csp_nonce') }}">
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

        document.querySelectorAll('[data-programme-picker]').forEach((button) => {
            button.addEventListener('click', () => document.querySelector(`#${CSS.escape(button.dataset.programmePicker)} [data-programme-input]`)?.click());
        });
        document.querySelectorAll('[data-programme-input]').forEach((input) => {
            input.addEventListener('change', () => { if (input.files.length) input.form.submit(); });
        });
    </script>
@endpush

@push('styles')
<style nonce="{{ request()->attributes->get('csp_nonce') }}">
    .badge.pending{border-color:#e8aaa4;background:#fff0ef;color:#a8241b}.booking-row-actions{display:flex;align-items:center;justify-content:flex-end;gap:8px}.programme-icon{display:inline-grid;place-items:center;width:34px;height:34px;padding:0;border:1px solid #b9c8d0;border-radius:7px;background:#fff;color:#52636c;font-size:18px;line-height:1;cursor:pointer}.programme-icon svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}.programme-icon:hover,.programme-icon.attached{border-color:#2478a8;color:#17658f;background:#edf7fc}.programme-menu{position:relative}.programme-menu summary{list-style:none}.programme-menu summary::-webkit-details-marker{display:none}.programme-menu>div{position:absolute;z-index:20;right:0;top:40px;min-width:165px;padding:6px;border:1px solid #c8d3d9;border-radius:8px;background:#fff;box-shadow:0 8px 24px rgba(16,32,42,.16)}.programme-menu a,.programme-menu button{display:block;width:100%;padding:8px 10px;border:0;border-radius:5px;background:transparent;color:#173847;text-align:left;text-decoration:none;font:inherit;cursor:pointer}.programme-menu a:hover,.programme-menu button:hover{background:#edf4f7}.programme-upload-form{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}
</style>
@endpush
