@extends('layouts.admin', ['title' => 'Availability', 'heading' => 'Event availability', 'subheading' => 'All competition and concert shifts, assignments and crew responses in one place.'])

@section('content')
    @include('admin.event-management._tabs')
    @php
        $equipmentItems = [
            'video_1' => ['🔵', 'Video 1'], 'video_2' => ['🟢', 'Video 2'], 'video_3' => ['🔴', 'Video 3'],
            'backdrop_1' => ['🟠', 'Backdrop 1'], 'backdrop_2' => ['🟡', 'Backdrop 2'], 'media' => ['💾', 'Media'],
        ];
    @endphp
    <div class="toolbar">
        <form class="filters" method="GET">
            <label>Search<input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Event name"></label>
            <label>Type<select name="type"><option value="">All</option>@foreach($filterEventTypes as $eventType)<option value="{{ $eventType->uuid }}" @selected(($filters['type'] ?? '') === $eventType->uuid)>{{ $eventType->name }}</option>@endforeach</select></label>
            <label>Status<select name="status"><option value="">All</option><option value="draft" @selected(($filters['status'] ?? '') === 'draft')>Draft</option><option value="open" @selected(($filters['status'] ?? '') === 'open')>Open</option><option value="closed" @selected(($filters['status'] ?? '') === 'closed')>Closed</option></select></label>
            <button>Filter</button>
        </form>
        <a class="button" href="{{ route('admin.scheduling-events.create') }}">Add event</a>
    </div>

    <div class="availability-key">
        <span><i class="key-dot available"></i> Available</span>
        <span><i class="key-dot unavailable"></i> Unavailable</span>
        <span><i class="key-dot unanswered"></i> No response</span>
        <span class="muted">Changes save immediately. Hover over event details or responses for more information.</span>
    </div>
    <div class="roster-key" aria-label="Roster status key">
        <strong>Roster key</strong>
        <span title="Team Leader responsibility">👑 Team Leader</span>
        <span title="Privately pencilled into the draft roster. Crew cannot see this yet.">✏️ Pencil</span>
        <span title="Published and sent to the crew member. Acknowledgement is still needed.">💌 Sent</span>
        <span title="The crew member has acknowledged the current shift details.">✅ Acknowledged</span>
        <span title="Check recommended: unavailable response or scheduling clash.">🚩 Issue</span>
    </div>
    <div class="equipment-key" aria-label="Equipment and media key">
        <strong>Equipment</strong>
        @foreach($equipmentItems as $equipmentItem)<span title="{{ $equipmentItem[1] }}">{{ $equipmentItem[0] }} {{ $equipmentItem[1] }}</span>@endforeach
        <span class="muted">➡️ before = bringing · ➡️ after = taking · hover for details</span>
    </div>

    <form id="bulk-events" method="POST" action="{{ route('admin.scheduling-events.bulk') }}" class="card card-pad" style="margin-bottom:12px">
        @csrf @method('PATCH')
        <div class="filters">
            <label>Selected events<select name="action" id="event-bulk-action" required><option value="">Choose action</option><option value="open">Request availability</option><option value="publish_roster">Publish roster and notify crew</option></select></label>
            <label id="bulk-deadline">Deadline date <span class="muted">(5:00 pm)</span><input type="date" name="deadline_date"></label>
            <button>Apply</button>
        </div>
        <div class="muted">Tick any row for an event; every row for that event is selected together.</div>
    </form>

    <div class="availability-table-shell {{ request()->boolean('fullscreen') ? 'forced-fullscreen' : '' }}">
        <div class="availability-table-controls">@if(request()->boolean('fullscreen'))<a class="button secondary" href="{{ route('admin.scheduling-events.index') }}">× Exit full screen</a>@else<button id="availability-fullscreen" class="secondary" type="button">⛶ Full screen</button>@endif</div>
    <div class="card availability-sheet">
        <table>
            <thead>
                <tr>
                    <th class="sticky-col col-date">Date</th>
                    <th class="sticky-col col-time">Shift</th>
                    <th class="sticky-col col-event">Event</th>
                    <th class="sticky-col col-venue">Venue</th>
                    <th class="sticky-col col-status">Status</th>
                    @foreach(range(1, 3) as $slot)<th class="role-heading">Role {{ $slot }}</th>@endforeach
                    @foreach($crew as $profile)<th class="crew-heading" title="{{ $profile->legal_name }} · {{ $profile->phone }}">{{ $profile->preferred_name ?: $profile->user->name }}</th>@endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($shifts as $shift)
                    @php
                        $event = $shift->schedulingEvent;
                        $bookingItem = $event->concertBookingItem;
                        $booking = $bookingItem?->booking;
                        $eventDisplayName = $event->event_type->value === 'concert' && $booking ? $booking->studio_name : $event->name;
                        $eventSubtitle = $event->event_type->value === 'concert' ? ($bookingItem?->title ?: ($bookingItem?->item_type?->value === 'dress_rehearsal' ? 'Dress rehearsal' : null)) : null;
                        $responses = $shift->availabilityResponses->keyBy('crew_profile_id');
                        $requiredRoles = $event->roleRequirements->sortBy('id')->values();
                        $assignments = $shift->assignments->keyBy('crew_role_id');
                        $isReady = $eventReadiness->get($event->uuid, false);
                        $displayStatus = $isReady ? 'READY' : ($event->roster_status === 'published' || $event->roster_status === 'changed' ? 'Assigned' : ($event->availability_status->value === 'open' ? 'Availability open' : ($event->availability_status->value === 'closed' ? 'Rostering' : 'Draft')));
                        $statusHover = $event->availability_deadline ? 'Availability deadline: '.$event->availability_deadline->format('j M Y, g:i a') : 'No availability deadline set.';
                    @endphp
                    <tr class="event-row" data-event="{{ $event->uuid }}">
                        <td class="sticky-col col-date"><strong>{{ $shift->shift_date?->format('D j M') }}</strong><div class="muted">{{ $shift->shift_date?->format('Y') }}</div></td>
                        <td class="sticky-col col-time" title="Arrival {{ $shift->posted_arrival_at?->format('g:i a') ?: 'TBC' }} · Finish {{ $shift->estimated_finish_at?->format('g:i a') ?: 'TBC' }}">
                            <strong>{{ $event->event_type->value === 'competition' ? 'COMP-'.($shift->period->value === 'morning' ? 'M' : 'A') : 'CON' }}</strong>
                            @if($shift->requires_setup)<span class="mini-badge">Setup</span>@endif
                            @if($shift->requires_set_down)<span class="mini-badge">Set down</span>@endif
                            <div class="muted">{{ $shift->starts_at?->format('g:i a') ?: 'TBC' }}–{{ $shift->estimated_finish_at?->format('g:i a') ?: 'TBC' }}</div>
                        </td>
                        <td class="sticky-col col-event event-select-cell" title="Click to select this event. {{ $event->admin_notes }}">
                            <input form="bulk-events" class="event-selector" data-event="{{ $event->uuid }}" type="checkbox" name="event_ids[]" value="{{ $event->uuid }}" hidden><a href="{{ route('admin.scheduling-events.show', $event) }}"><strong>{{ $eventDisplayName }}</strong></a>
                            @if($eventSubtitle)<div class="muted">{{ $eventSubtitle }}</div>@endif
                        </td>
                        <td class="sticky-col col-venue" title="Access: {{ $event->venue?->access_notes ?: 'No notes' }} · Parking: {{ $event->venue?->parking_notes ?: 'No notes' }}">
                            {{ $event->venue?->name ?: 'Not set' }}
                            @if($booking)<div class="muted">{{ $booking->contact_name }} · {{ $booking->contact_phone }}</div>@endif
                        </td>
                        <td class="sticky-col col-status" title="{{ $statusHover }}"><span class="badge {{ $isReady ? 'ready' : '' }}">{{ $displayStatus }}</span></td>

                        @foreach(range(0, 2) as $slotIndex)
                            @php($role = $requiredRoles->get($slotIndex)?->crewRole)
                            @if($role)
                            @php($assigned = $assignments->get($role->id))
                            @php($assignedResponse = $assigned ? $responses->get($assigned->crew_profile_id) : null)
                            @php($hasEquipmentWarning = $assigned?->equipmentResponsibilities->contains(fn($responsibility) => ($equipmentJourneyDetails[$responsibility->id]['warnings'] ?? collect())->isNotEmpty()) ?? false)
                            <td class="assignment-cell {{ ($assigned && (isset($assignmentConflicts[$assigned->id]) || $assignedResponse?->status->value === 'unavailable' || $hasEquipmentWarning)) ? 'assignment-conflict' : '' }}" @if($assigned && isset($assignmentConflicts[$assigned->id])) title="Scheduling clash: this crew member has an overlapping assignment." @elseif($assignedResponse?->status->value === 'unavailable') title="Check recommended: this crew member responded unavailable." @elseif($hasEquipmentWarning) title="Check the flagged equipment journey." @endif>
                                    <div class="role-title" title="Role {{ $slotIndex + 1 }} for this event">{{ $role->name }}</div>
                                    <select class="instant-save assignment-select" data-shift="{{ $shift->uuid }}" data-role="{{ $role->id }}" data-url="{{ route('admin.scheduling-shifts.roles.assignment', [$shift, $role]) }}" aria-label="Pencil {{ $role->name }} for {{ $event->name }} {{ $shift->period?->value ?? 'concert' }}">
                                        <option value="">Unassigned</option>
                                        @foreach($crew as $profile)
                                            @if($profile->roles->contains(fn($qualifiedRole) => $qualifiedRole->id === $role->id && $qualifiedRole->pivot->status->value === 'approved'))
                                                <option value="{{ $profile->uuid }}" @selected($assigned?->crew_profile_id === $profile->id)>{{ $profile->preferred_name ?: $profile->user->name }}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                    @if($assigned)
                                        @php($responsibilities = $assigned->equipmentResponsibilities->keyBy('item_code'))
                                        <div class="assignment-state {{ $assigned->acknowledgement_status }}" aria-label="Assignment status">
                                            @if($role->code === 'team-leader')<span title="Team Leader responsibility">👑</span>@endif
                                            @if(isset($assignmentConflicts[$assigned->id]) || $assignedResponse?->status->value === 'unavailable')
                                                <span title="@if(isset($assignmentConflicts[$assigned->id])) Scheduling clash: this crew member has an overlapping assignment. @else Check recommended: this crew member responded unavailable. @endif">🚩</span>
                                            @elseif($assigned->status === 'draft')
                                                <span title="Privately pencilled into the draft roster. Crew cannot see this yet.">✏️</span>
                                            @elseif($assigned->acknowledgement_status === 'acknowledged')
                                                <span title="The crew member acknowledged the current shift details{{ $assigned->acknowledged_at ? ' on '.$assigned->acknowledged_at->format('j M, g:i a') : '' }}.">✅</span>
                                            @else
                                                <span title="@if($assigned->acknowledgement_status === 'reset_by_material_change') Important shift details changed and were sent again. Acknowledgement is needed. @else Published and sent to the crew member. Acknowledgement is still needed. @endif">💌</span>
                                            @endif
                                            @if($event->event_type->value === 'competition')
                                                <label class="team-leader-control" title="@if($assigned->is_team_leader && $assigned->status === 'published') Published Team Leader responsibility. @else Privately pencil this assigned crew member as Team Leader. @endif">
                                                    <input class="team-leader-checkbox" type="checkbox" data-url="{{ route('admin.scheduling-shifts.crew.team-leader', [$shift, $assigned->crewProfile]) }}" @checked($assigned->is_team_leader)><span>👑</span>
                                                </label>
                                            @endif
                                            @foreach($responsibilities as $responsibility)
                                                @php($equipmentEmoji = $equipmentItems[$responsibility->item_code][0])
                                                @php($equipmentName = $equipmentItems[$responsibility->item_code][1])
                                                @php($movement = $responsibility->is_bringing && $responsibility->is_taking ? 'bringing and taking' : ($responsibility->is_bringing ? 'bringing' : ($responsibility->is_taking ? 'taking' : 'using')))
                                                @php($journey = $equipmentJourneyDetails[$responsibility->id] ?? null)
                                                @php($journeyText = $journey ? collect([$journey['previous'] ? 'Previous: '.$journey['previous'] : null, $journey['next'] ? 'Next: '.$journey['next'] : null, $journey['continuity']->join(' '), $journey['warnings']->join(' ')])->filter()->join(' · ') : null)
                                                <span class="equipment-marker" title="{{ $assigned->crewProfile->preferred_name ?: $assigned->crewProfile->user?->name }} is {{ $movement }} {{ $equipmentName }}{{ $responsibility->other_notes ? ' · Other: '.$responsibility->other_notes : '' }}{{ $journeyText ? ' · '.$journeyText : '' }}">{{ $responsibility->is_bringing ? '➡️' : '' }}{{ $equipmentEmoji }}{{ $responsibility->is_taking ? '➡️' : '' }}{{ $responsibility->other_notes ? '📝' : '' }}{{ $journey && $journey['warnings']->isNotEmpty() ? '🚩' : '' }}</span>
                                            @endforeach
                                            <details class="equipment-picker">
                                                <summary title="Assign equipment or media">＋</summary>
                                                <div class="equipment-popover">
                                                    <div class="equipment-popover-title"><strong>Equipment and media</strong><button class="equipment-popover-close" type="button" aria-label="Close equipment and media">×</button></div>
                                                    <div class="equipment-row equipment-head"><strong>Item</strong><strong>Bring</strong><strong>Take</strong><strong>Other</strong></div>
                                                    @foreach($equipmentItems as $itemCode => $equipmentItem)
                                                        @php($equipmentEmoji = $equipmentItem[0])
                                                        @php($equipmentName = $equipmentItem[1])
                                                        @if($event->event_type->value === 'concert' || $itemCode === 'media')
                                                            @php($responsibility = $responsibilities->get($itemCode))
                                                            <div class="equipment-row" data-code="{{ $itemCode }}" data-url="{{ route('admin.scheduling-assignments.equipment', $assigned) }}">
                                                                <span>{{ $equipmentEmoji }} {{ $equipmentName }}</span>
                                                                <input class="equipment-control equipment-bring" type="checkbox" aria-label="Bring {{ $equipmentName }}" @checked($responsibility?->is_bringing)>
                                                                <input class="equipment-control equipment-take" type="checkbox" aria-label="Take {{ $equipmentName }}" @checked($responsibility?->is_taking)>
                                                                <input class="equipment-control equipment-other" value="{{ $responsibility?->other_notes }}" placeholder="e.g. Left at venue overnight" aria-label="Other instructions for {{ $equipmentName }}">
                                                            </div>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            </details>
                                        </div>
                                    @endif
                            </td>
                            @else
                                <td class="assignment-cell"><span class="muted">—</span></td>
                            @endif
                        @endforeach

                        @foreach($crew as $profile)
                            @php($response = $responses->get($profile->id))
                            <td class="availability-cell {{ $response?->status->value ?? 'unanswered' }}" title="{{ $profile->preferred_name ?: $profile->user->name }}: {{ $response ? ucfirst($response->status->value).($response->note ? ' · '.$response->note : '').' · replied '.$response->responded_at->format('j M, g:i a') : 'No response yet' }}">
                                <select class="instant-save availability-select" data-url="{{ route('admin.scheduling-shifts.crew.availability', [$shift, $profile]) }}" aria-label="Availability for {{ $profile->preferred_name }}">
                                    <option value="unanswered" @selected(!$response)>–</option>
                                    <option value="available" @selected($response?->status->value === 'available')>Y</option>
                                    <option value="unavailable" @selected($response?->status->value === 'unavailable')>N</option>
                                </select>
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ 8 + $crew->count() }}" class="muted">No shifts match these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="pagination"><x-admin-pagination :paginator="$shifts" /></div>
    </div>
    </div>

@endsection

@push('scripts')
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
    const spreadsheetStateKey = `dancepro-schedule-position:${window.location.pathname}`;
    const spreadsheet = document.querySelector('.availability-sheet');
    const spreadsheetShell = document.querySelector('.availability-table-shell');
    const fullscreenButton = document.getElementById('availability-fullscreen');

    fullscreenButton?.addEventListener('click', async () => {
        if (document.fullscreenElement === spreadsheetShell) await document.exitFullscreen();
        else await spreadsheetShell.requestFullscreen();
    });
    document.addEventListener('fullscreenchange', () => {
        const isFullscreen = document.fullscreenElement === spreadsheetShell;
        if (fullscreenButton) {
            fullscreenButton.textContent = isFullscreen ? '× Exit full screen' : '⛶ Full screen';
            fullscreenButton.setAttribute('aria-pressed', String(isFullscreen));
        }
    });

    function reloadSpreadsheetInPlace() {
        sessionStorage.setItem(spreadsheetStateKey, JSON.stringify({
            windowX: window.scrollX,
            windowY: window.scrollY,
            sheetX: spreadsheet?.scrollLeft || 0,
            sheetY: spreadsheet?.scrollTop || 0,
            selectedEvents: [...document.querySelectorAll('.event-selector:checked')].map((checkbox) => checkbox.value),
            selectedBookings: [...document.querySelectorAll('.booking-item-selector:checked')].map((checkbox) => checkbox.value),
        }));
        window.location.reload();
    }

    const savedSpreadsheetState = sessionStorage.getItem(spreadsheetStateKey);
    if (savedSpreadsheetState) {
        sessionStorage.removeItem(spreadsheetStateKey);
        const position = JSON.parse(savedSpreadsheetState);
        requestAnimationFrame(() => {
            window.scrollTo(position.windowX, position.windowY);
            if (spreadsheet) {
                spreadsheet.scrollLeft = position.sheetX;
                spreadsheet.scrollTop = position.sheetY;
            }
            document.querySelectorAll('.event-selector').forEach((checkbox) => {
                checkbox.checked = position.selectedEvents.includes(checkbox.value);
            });
            document.querySelectorAll('.booking-item-selector').forEach((checkbox) => {
                checkbox.checked = (position.selectedBookings || []).includes(checkbox.value);
            });
            refreshSelectionHighlights();
        });
    }

    document.querySelectorAll('.instant-save').forEach((select) => {
        select.dataset.previous = select.value;
        select.addEventListener('change', async () => {
            const previous = select.dataset.previous;
            select.disabled = true;
            const isAvailability = select.classList.contains('availability-select');
            const body = isAvailability ? { status: select.value } : { crew_profile_uuid: select.value || null };

            try {
                const response = await fetch(select.dataset.url, {
                    method: 'PUT',
                    headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
                    body: JSON.stringify(body),
                });
                if (!response.ok) throw new Error((await response.json()).message || 'Could not save');
                select.dataset.previous = select.value;
                if (isAvailability) {
                    select.closest('td').className = `availability-cell ${select.value}`;
                } else {
                    reloadSpreadsheetInPlace();
                }
            } catch (error) {
                select.value = previous;
                alert(error.message);
            } finally {
                select.disabled = false;
            }
        });
    });
    function refreshSelectionHighlights() {
        document.querySelectorAll('.event-row').forEach((row) => row.classList.toggle('is-event-selected', document.querySelector(`.event-selector[data-event="${row.dataset.event}"]`)?.checked));
        document.querySelectorAll('.pending-event-row').forEach((row) => row.classList.toggle('is-event-selected', row.querySelector('.booking-item-selector')?.checked));
    }
    document.querySelectorAll('.event-select-cell').forEach((cell) => cell.addEventListener('click', (event) => {
        if (event.target.closest('a,button,select,input,details,summary,label')) return;
        const eventId = cell.closest('.event-row').dataset.event;
        const checkboxes = [...document.querySelectorAll(`.event-selector[data-event="${eventId}"]`)];
        const selected = !checkboxes[0].checked;
        checkboxes.forEach((checkbox) => checkbox.checked = selected);
        refreshSelectionHighlights();
    }));
    document.querySelectorAll('.pending-event-row').forEach((row) => row.addEventListener('click', (event) => {
        if (event.target.closest('a,button,select,input,details,summary,label')) return;
        const checkbox = row.querySelector('.booking-item-selector');
        checkbox.checked = !checkbox.checked;
        refreshSelectionHighlights();
    }));
    const bulkAction = document.getElementById('event-bulk-action');
    const bulkDeadline = document.getElementById('bulk-deadline');
    const bulkDeadlineInput = bulkDeadline.querySelector('input');
    const showDeadline = () => { const open = bulkAction.value === 'open'; bulkDeadline.hidden = !open; bulkDeadlineInput.required = open; };
    bulkAction.addEventListener('change', showDeadline); showDeadline();
    document.querySelectorAll('.team-leader-checkbox').forEach((checkbox) => checkbox.addEventListener('change', async () => {
        const previous = !checkbox.checked;
        checkbox.disabled = true;
        try {
            const response = await fetch(checkbox.dataset.url, {method:'PUT', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'}, body:JSON.stringify({is_team_leader:checkbox.checked})});
            if (!response.ok) throw new Error((await response.json()).message || 'Could not save Team Leader');
            reloadSpreadsheetInPlace();
        } catch (error) {
            checkbox.checked = previous;
            checkbox.disabled = false;
            alert(error.message);
        }
    }));
    document.querySelectorAll('.equipment-control').forEach((control) => {
        control.dataset.previous = control.type === 'checkbox' ? String(control.checked) : control.value;
        control.addEventListener('change', async () => {
        const row = control.closest('.equipment-row');
        control.disabled = true;
        try {
            const response = await fetch(row.dataset.url, {method:'PUT', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'}, body:JSON.stringify({item_code:row.dataset.code,is_bringing:row.querySelector('.equipment-bring').checked,is_taking:row.querySelector('.equipment-take').checked,other_notes:row.querySelector('.equipment-other').value || null})});
            if (!response.ok) throw new Error((await response.json()).message || 'Could not save equipment');
            reloadSpreadsheetInPlace();
        } catch (error) {
            if (control.type === 'checkbox') control.checked = control.dataset.previous === 'true';
            else control.value = control.dataset.previous;
            control.disabled = false;
            alert(error.message);
        }
        });
    });
    document.querySelectorAll('.equipment-picker').forEach((picker) => {
        picker.addEventListener('toggle', () => {
            if (!picker.open) return;
            document.querySelectorAll('.equipment-picker[open]').forEach((otherPicker) => {
                if (otherPicker !== picker) otherPicker.open = false;
            });
        });
        picker.querySelector('.equipment-popover-close').addEventListener('click', () => {
            picker.open = false;
        });
    });
    document.addEventListener('click', (event) => {
        document.querySelectorAll('.equipment-picker[open]').forEach((picker) => {
            if (!picker.contains(event.target)) picker.open = false;
        });
    });
</script>
@endpush

@push('styles')
<style>
    .availability-key { display:flex; gap:12px; align-items:center; flex-wrap:wrap; margin:0 0 8px; font-size:11px; }
    .roster-key { display:flex; gap:11px; align-items:center; flex-wrap:wrap; margin:0 0 8px; padding:6px 9px; border:1px solid #d7e4ea; background:#fff; font-size:11px; }
    .roster-key span { cursor:help; }
    .equipment-key { display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin:0 0 8px; padding:6px 9px; border:1px solid #d7e4ea; background:#fff; font-size:11px; }
    .equipment-key span { cursor:help; }
    .key-dot { display:inline-block; width:12px; height:12px; margin-right:4px; border:1px solid #b8c3c8; vertical-align:-1px; }
    .key-dot.available, .availability-cell.available { background:#72b657; }
    .key-dot.unavailable, .availability-cell.unavailable { background:#c6c9ca; }
    .key-dot.unanswered, .availability-cell.unanswered { background:#fff; }
    .availability-table-shell { position:relative; }
    .availability-table-controls { display:flex; justify-content:flex-end; margin-bottom:6px; }
    .availability-table-controls button,.availability-table-controls .button { min-height:34px; padding:6px 11px; }
    .availability-table-shell.forced-fullscreen { position:fixed; inset:0; z-index:1000; box-sizing:border-box; width:100vw; height:100vh; padding:10px; background:#edf3f6; }
    .availability-table-shell.forced-fullscreen .availability-sheet { max-height:calc(100vh - 56px); height:calc(100vh - 56px); }
    .availability-table-shell:fullscreen { box-sizing:border-box; width:100vw; height:100vh; padding:10px; background:#edf3f6; }
    .availability-table-shell:fullscreen .availability-sheet { max-height:calc(100vh - 56px); height:calc(100vh - 56px); }
    .availability-sheet { isolation:isolate; max-width:100%; overflow:auto; max-height:72vh; }
    .availability-sheet table { width:max-content; min-width:100%; font-size:11px; line-height:1.15; }
    .availability-sheet th, .availability-sheet td { padding:4px 5px; vertical-align:middle; white-space:nowrap; }
    .availability-sheet .muted { font-size:9px; line-height:1.15; }
    .event-select-cell, .pending-event-row { cursor:pointer; }
    .event-row.is-event-selected .sticky-col { background:#dff4fc; }
    .event-row.is-event-selected .col-event { box-shadow:inset 4px 0 0 #0AA0DB; }
    .pending-event-row.is-event-selected td { background:#dff4fc; }
    .availability-sheet thead th { position:sticky; top:0; z-index:10; background:#0d3550; color:#fff; }
    .sticky-col { position:sticky; z-index:20; background:#fff; text-align:left; }
    .availability-sheet thead .sticky-col { z-index:40; background:#0d3550; }
    .col-date { left:0; min-width:82px; width:82px; }
    .col-time { left:82px; min-width:110px; width:110px; }
    .col-event { left:192px; min-width:175px; width:175px; max-width:175px; overflow:hidden; text-overflow:ellipsis; }
    .col-venue { left:367px; min-width:165px; width:165px; max-width:165px; overflow:hidden; text-overflow:ellipsis; }
    .col-status { left:532px; min-width:88px; width:88px; border-right:2px solid #0d3550; box-shadow:8px 0 10px -10px rgba(16,24,32,.75); }
    .role-heading { min-width:120px; }
    .crew-heading { min-width:58px; max-width:72px; overflow:hidden; text-overflow:ellipsis; }
    .assignment-cell { position:relative; border-right:1px solid #b7c8d0; }
    .assignment-cell:has(.equipment-picker[open]) { z-index:100; }
    .assignment-cell.assignment-conflict { background:#fee2e2; box-shadow:inset 0 0 0 2px #b42318; }
    .badge.ready { background:#dcfce7; color:#147a52; }
    .assignment-cell select { min-width:110px; min-height:25px; padding:2px 3px; font-size:10px; }
    .role-title { margin-bottom:2px; color:#52636c; font-size:8px; font-weight:800; letter-spacing:.03em; text-transform:uppercase; }
    .assignment-state { display:flex; align-items:center; justify-content:center; gap:3px; min-height:19px; margin-top:1px; font-size:14px; line-height:1; white-space:normal; }
    .assignment-state span { cursor:help; }
    .availability-cell { padding:3px !important; transition:background .15s; }
    .availability-cell select { width:100%; min-width:48px; min-height:25px; padding:1px; border:0; background:transparent; font-size:10px; font-weight:800; text-align:center; cursor:pointer; }
    .team-leader-control { display:inline-flex; align-items:center; gap:1px; margin:0; padding:0; font-size:13px; cursor:help; }
    .team-leader-control input { width:auto; min-height:0; margin:0; }
    .equipment-marker { font-size:11px; cursor:help; }
    .equipment-picker { position:relative; z-index:1; }
    .equipment-picker[open] { z-index:100; }
    .equipment-picker summary { display:grid; width:18px; height:18px; place-items:center; border:1px solid #b9cbd4; border-radius:50%; background:#fff; color:#087fb0; font-size:12px; font-weight:800; cursor:pointer; list-style:none; }
    .equipment-picker summary::-webkit-details-marker { display:none; }
    .equipment-popover { position:absolute; z-index:1000; top:28px; right:0; width:390px; padding:10px; border:1px solid #9eb5c0; background:#fff; box-shadow:0 12px 30px rgba(16,24,32,.18); font-size:12px; }
    .equipment-popover-title { display:flex; align-items:center; justify-content:space-between; margin-bottom:5px; font-size:13px; }
    .equipment-popover-close { width:25px; min-height:25px; padding:0; border:0; background:transparent; color:#52636c; font-size:22px; line-height:1; cursor:pointer; }
    .equipment-popover-close:hover { color:#10202a; background:#edf4f7; }
    .equipment-row { display:grid; grid-template-columns:minmax(0,1fr) 45px 45px 145px; align-items:center; gap:5px; padding:5px 2px; border-bottom:1px solid #e3ebef; text-align:left; }
    .equipment-row input { width:auto; min-height:0; margin:auto; }
    .equipment-row .equipment-other { width:100%; min-height:30px; padding:4px 6px; font-size:11px; }
    .mini-badge { display:inline-block; margin-left:2px; padding:1px 3px; background:#fff2cc; color:#765500; font-size:8px; font-weight:700; }
    @media(max-width:1000px) { .col-venue, .col-status { position:static; } }
</style>
@endpush
