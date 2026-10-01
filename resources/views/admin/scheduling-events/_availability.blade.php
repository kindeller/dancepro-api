
    @unless($isolated ?? false) @include('admin.event-management._tabs') @endunless
    @php
        $equipmentItems = \App\Features\Scheduling\Support\AssignmentEquipmentItems::all();
    @endphp
    <details class="card card-pad availability-advanced" style="margin-bottom:16px">
    <summary style="font-weight:800;cursor:pointer">Advanced options</summary>
    @unless($isolated ?? false)
    <div class="toolbar">
        <form class="filters" method="GET">
            <label>Search<input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Event name"></label>
            <label>Type<select name="type"><option value="">All</option>@foreach($filterEventTypes as $eventType)<option value="{{ $eventType->uuid }}" @selected(($filters['type'] ?? '') === $eventType->uuid)>{{ $eventType->name }}</option>@endforeach</select></label>
            <label>Studio<select name="studio"><option value="">All studios</option>@foreach($filterStudios as $studio)<option value="{{ $studio->uuid }}" @selected(($filters['studio'] ?? '') === $studio->uuid)>{{ $studio->name }}</option>@endforeach</select></label>
            <label>Status<select name="status"><option value="">All</option><option value="pending" @selected(($filters['status'] ?? '') === 'pending')>Pending approval</option><option value="draft" @selected(($filters['status'] ?? '') === 'draft')>Draft</option><option value="open" @selected(($filters['status'] ?? '') === 'open')>Open</option><option value="closed" @selected(($filters['status'] ?? '') === 'closed')>Closed</option></select></label>
            <button>Filter</button>
        </form>
        <a class="button" href="{{ route('admin.scheduling-events.create') }}">Add event</a>
    </div>

    @endunless
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
        @foreach(collect($equipmentItems)->except(['photos', 'videos']) as $equipmentItem)<span title="{{ $equipmentItem[1] }}">{{ $equipmentItem[0] }} {{ $equipmentItem[1] }}</span>@endforeach
        <span title="Photo and video media">💾 Photos &amp; videos</span>
        <span class="muted">➡️ before = bringing · ➡️ after = taking · hover for details</span>
    </div>

    <form id="bulk-events" method="POST" action="{{ route('admin.scheduling-events.bulk') }}" class="card card-pad" style="margin-bottom:12px">
        @csrf @method('PATCH')
        <div class="filters">
            <label>Selected events<select name="action" id="event-bulk-action" required><option value="">Choose action</option><option value="open">Request availability</option><option value="publish_roster">Publish roster and notify crew</option></select></label>
            <label id="bulk-deadline">Deadline date <span class="muted">(5:00 pm)</span><input type="date" name="deadline_date"></label>
            <button>Apply</button>
        </div>
        <div class="muted">Choose an action, then tick the checkboxes on the left. Selecting an event includes all its shifts.</div>
    </form>

    </details>

    <div class="availability-table-shell {{ request()->boolean('fullscreen') ? 'forced-fullscreen' : '' }}">
        <div class="availability-table-controls">@if(request()->boolean('fullscreen'))<a class="button secondary" href="{{ request()->fullUrlWithoutQuery('fullscreen') }}">× Exit full screen</a>@else<button id="availability-fullscreen" class="secondary" type="button">⛶ Full screen</button>@endif</div>
    <div class="card availability-sheet">
        <table>
            <thead>
                <tr>
                    <th class="sticky-col bulk-select-col" hidden><span class="sr-only">Select events</span></th>
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
                @forelse($rows as $row)
                    @if($row->row_type === 'pending')
                        @include('admin.scheduling-events._pending-booking-row', ['item' => $row->item])
                        @continue
                    @endif
                    @php
                        $shift = $row->item;
                        $event = $shift->schedulingEvent;
                        $bookingItem = $event->concertBookingItem;
                        $booking = $bookingItem?->booking;
                        $roleOptions = app(\App\Features\Scheduling\Services\EventRoleOptions::class)->forEvent($event, $availableRoles)->map(fn ($option) => ['uuid' => $option->uuid, 'name' => $option->name]);
                        $eventDisplayName = $event->event_type->value === 'concert' && $booking ? $booking->studio_name : $event->name;
                        $eventSubtitle = $event->event_type->value === 'concert' ? ($bookingItem?->title ?: ($bookingItem?->item_type?->value === 'dress_rehearsal' ? 'Dress rehearsal' : null)) : null;
                        $responses = $shift->availabilityResponses->keyBy('crew_profile_id');
                        $requiredRoles = app(\App\Features\Scheduling\Services\EventRoleOptions::class)->slots($event);
                        $assignments = $shift->assignments->keyBy('crew_role_id');
                        $rosterState = $eventReadiness->get($event->uuid);
                        $isReady = $rosterState['ready'];
                        $displayStatus = $rosterState['label'];
                        $statusHover = $event->availability_deadline ? 'Availability deadline: '.$event->availability_deadline->format('j M Y, g:i a') : 'No availability deadline set.';
                    @endphp
                    <tr class="event-row {{ $event->cancelled_at ? 'cancelled-event-row' : '' }}" data-event="{{ $event->uuid }}">
                        <td class="sticky-col bulk-select-col" hidden>@unless($event->cancelled_at)<input form="bulk-events" class="event-selector" data-event="{{ $event->uuid }}" type="checkbox" name="event_ids[]" value="{{ $event->uuid }}" aria-label="Select {{ $eventDisplayName }} for bulk action">@endunless</td>
                        <td class="sticky-col col-date"><strong>{{ $shift->shift_date?->format('D j M') }}</strong><div class="muted">{{ $shift->shift_date?->format('Y') }}</div></td>
                        <td class="sticky-col col-time" title="Arrival {{ $shift->posted_arrival_at?->format('g:i a') ?: 'TBC' }} · Finish {{ $shift->estimated_finish_at?->format('g:i a') ?: 'TBC' }}">
                            <details class="shift-requirements-picker" data-popup-align="left" data-url="{{ route('admin.scheduling-shifts.requirements', $shift) }}">
                            <summary aria-label="Edit setup and set down for {{ $eventDisplayName }} {{ $shift->shift_date?->format('j M') }} {{ $shift->displayCode() }}">
                            <strong>{{ $shift->displayCode() }}</strong>
                            @if($shift->requires_setup)<span class="mini-badge {{ $equipmentShiftCoverage[$shift->id]['setup'] ? 'equipment-covered' : '' }}" title="{{ $equipmentShiftCoverage[$shift->id]['setup'] ? 'Setup assigned: a crew member is bringing a kit.' : 'Setup needs a crew member assigned to bring a kit.' }}">Setup</span>@endif
                            @if($shift->requires_set_down)<span class="mini-badge {{ $equipmentShiftCoverage[$shift->id]['set_down'] ? 'equipment-covered' : '' }}" title="{{ $equipmentShiftCoverage[$shift->id]['set_down'] ? 'Set down assigned: a crew member is taking a kit.' : 'Set down needs a crew member assigned to take a kit.' }}">Set down</span>@endif
                            <div class="muted">{{ $shift->starts_at?->format('g:i a') ?: 'TBC' }}–{{ $shift->estimated_finish_at?->format('g:i a') ?: 'TBC' }}</div>
                            </summary>
                            <div class="equipment-popover shift-requirements-popover" role="group" aria-label="Setup and set down requirements" title="">
                                <div class="equipment-popover-title"><strong>Setup &amp; set down</strong><button type="button" class="equipment-popover-close shift-requirements-close" aria-label="Close setup and set down">×</button></div>
                                <p class="muted">{{ $eventDisplayName }}{{ $eventSubtitle ? " · ".$eventSubtitle : "" }} · {{ $shift->shift_date?->format('j M Y') }} · {{ $shift->displayCode() }}</p>
                                <label><input type="checkbox" name="requires_setup" @checked($shift->requires_setup)> Setup required</label>
                                <label><input type="checkbox" name="requires_set_down" @checked($shift->requires_set_down)> Set down required</label>
                                <p class="muted">Turn these off between consecutive concerts when the equipment stays in place. Arrival and finish times stay as entered.</p>
                                <p class="shift-save-status" role="status" aria-live="polite">Changes save automatically.</p>
                            </div>
                            </details>
                        </td>
                        <td class="sticky-col col-event event-select-cell" title="Click to select this event. {{ $event->admin_notes }}">
                            <a href="{{ route('admin.scheduling-events.show', $event) }}"><strong>{{ $eventDisplayName }}</strong></a>
                            @if($event->eventCode())<div class="muted">{{ $event->eventCode() }}</div>@endif
                            @if($eventSubtitle)<div class="muted">{{ $eventSubtitle }}</div>@endif
                        </td>
                        <td class="sticky-col col-venue" title="Access: {{ $event->venue?->access_notes ?: 'No notes' }} · Parking: {{ $event->venue?->parking_notes ?: 'No notes' }}">
                            {{ $event->venue?->name ?: 'Not set' }}
                            @if($booking)<div class="muted">{{ $booking->contact_name }} · {{ $booking->contact_phone }}</div>@endif
                        </td>
                        <td class="sticky-col col-status" title="{{ $statusHover }}">
                            @if($event->cancelled_at)<span class="badge cancelled">{{ $displayStatus }}</span>
                            @else<button type="button" class="event-status-trigger badge {{ $isReady ? 'ready' : '' }}" data-url="{{ route('admin.scheduling-events.workflow', $event) }}" data-name="{{ $eventDisplayName }}{{ $eventSubtitle ? ' · '.$eventSubtitle : '' }}" data-roster="{{ $event->roster_status }}" data-deadline="{{ $event->availability_deadline?->toDateString() }}" aria-label="Change event status for {{ $eventDisplayName }}">{{ $displayStatus }}</button>@endif
                        </td>

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
                                        @php($crewOptions = app(\App\Features\Scheduling\Services\CrewAssignmentOptions::class)->forRole($crew, $role, $responses))
                                        @if($crewOptions['recommended']->isNotEmpty())
                                            <optgroup label="Available & trained">
                                                @foreach($crewOptions['recommended'] as $option)
                                                    <option value="{{ $option['profile']->uuid }}" @selected($assigned?->crew_profile_id === $option['profile']->id)>✓ {{ $option['name'] }}</option>
                                                @endforeach
                                            </optgroup>
                                        @endif
                                        @if($crewOptions['other']->isNotEmpty())
                                            <optgroup label="Other crew">
                                                @foreach($crewOptions['other'] as $option)
                                                    <option value="{{ $option['profile']->uuid }}" @selected($assigned?->crew_profile_id === $option['profile']->id)>{{ $option['name'] }}</option>
                                                @endforeach
                                            </optgroup>
                                        @endif
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
                                            @foreach($responsibilities->whereNotIn('item_code', \App\Features\Scheduling\Support\AssignmentEquipmentItems::MEDIA_CODES) as $responsibility)
                                                @php($equipmentEmoji = $equipmentItems[$responsibility->item_code][0])
                                                @php($equipmentName = $equipmentItems[$responsibility->item_code][1])
                                                @php($movement = $responsibility->is_bringing && $responsibility->is_taking ? 'bringing and taking' : ($responsibility->is_bringing ? 'bringing' : ($responsibility->is_taking ? 'taking' : 'using')))
                                                @php($journey = $equipmentJourneyDetails[$responsibility->id] ?? null)
                                                @php($journeyText = $journey ? collect([$journey['previous'] ? 'Previous: '.$journey['previous'] : null, $journey['next'] ? 'Next: '.$journey['next'] : null, $journey['continuity']->join(' '), $journey['warnings']->join(' ')])->filter()->join(' · ') : null)
                                                <span class="equipment-marker" title="{{ $assigned->crewProfile->preferred_name ?: $assigned->crewProfile->user?->name }} is {{ $movement }} {{ $equipmentName }}{{ $responsibility->other_notes ? ' · Other: '.$responsibility->other_notes : '' }}{{ $journeyText ? ' · '.$journeyText : '' }}">{{ $responsibility->is_bringing ? '➡️' : '' }}{{ $equipmentEmoji }}{{ $responsibility->is_taking ? '➡️' : '' }}{{ $responsibility->other_notes ? '📝' : '' }}{{ $journey && $journey['warnings']->isNotEmpty() ? '🚩' : '' }}</span>
                                            @endforeach
                                            @php($mediaSummary = \App\Features\Scheduling\Support\AssignmentEquipmentItems::mediaSummary($responsibilities))
                                            @if($mediaSummary)
                                                <span class="equipment-marker media-marker" title="{{ $mediaSummary['description'] }}">{{ $mediaSummary['bringing'] ? '➡️' : '' }}💾{{ $mediaSummary['taking'] ? '➡️' : '' }}{{ $mediaSummary['notes'] ? '📝' : '' }}</span>
                                            @endif
                                            <details class="equipment-picker">
                                                <summary title="Assign equipment or media">＋</summary>
                                                <div class="equipment-popover">
                                                    <div class="equipment-popover-title"><strong>Equipment and media</strong><button class="equipment-popover-close" type="button" aria-label="Close equipment and media">×</button></div>
                                                    <p class="equipment-save-status" role="status" aria-live="polite">Changes save automatically.</p>
                                                    <div class="equipment-row equipment-head"><strong>Item</strong><strong>Bring</strong><strong>Take</strong><strong>Other</strong></div>
                                                    @foreach($equipmentItems as $itemCode => $equipmentItem)
                                                        @php($equipmentEmoji = $equipmentItem[0])
                                                        @php($equipmentName = $equipmentItem[1])
                                                            @php($responsibility = $responsibilities->get($itemCode))
                                                            <div class="equipment-row" data-code="{{ $itemCode }}" data-url="{{ route('admin.scheduling-assignments.equipment', $assigned) }}">
                                                                <span>{{ $equipmentEmoji }} {{ $equipmentName }}</span>
                                                                <input class="equipment-control equipment-bring" type="checkbox" aria-label="Bring {{ $equipmentName }}" @checked($responsibility?->is_bringing)>
                                                                <input class="equipment-control equipment-take" type="checkbox" aria-label="Take {{ $equipmentName }}" @checked($responsibility?->is_taking)>
                                                                <input class="equipment-control equipment-other" value="{{ $responsibility?->other_notes }}" placeholder="e.g. Left at venue overnight" aria-label="Other instructions for {{ $equipmentName }}">
                                                            </div>
                                                    @endforeach
                                                </div>
                                            </details>
                                        </div>
                                    @endif
                            </td>
                            @else
                                <td class="assignment-cell empty-role-cell">
                                    @if(! $event->cancelled_at && $roleOptions->isNotEmpty())
                                        <button type="button" class="add-event-role" data-url="{{ route('admin.scheduling-events.roles.store', $event) }}" data-name="{{ $eventDisplayName }}{{ $eventSubtitle ? ' · '.$eventSubtitle : '' }}" data-roles="{{ $roleOptions->toJson() }}" aria-label="Add role for {{ $eventDisplayName }}" title="Add a role, such as P2">—</button>
                                    @else<span class="muted" title="{{ $event->cancelled_at ? 'Event cancelled' : 'No additional active roles available' }}">—</span>@endif
                                </td>
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
                    <tr><td colspan="{{ 8 + $crew->count() }}" class="muted">No events match these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="pagination"><x-admin-pagination :paginator="$rows" /></div>
    </div>
    </div>


@include('admin.scheduling-events._workflow-dialogs')

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
            bulkAction: document.getElementById('event-bulk-action').value,
            deadline: document.querySelector('#bulk-deadline input').value,
            selectedEvents: [...document.querySelectorAll('.event-selector:checked')].map((checkbox) => checkbox.value),
        }));
        if (document.fullscreenElement === spreadsheetShell) {
            const url = new URL(window.location.href);
            url.searchParams.set('fullscreen', '1');
            window.location.assign(url);
        } else window.location.reload();
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
            bulkAction.value = position.bulkAction || '';
            bulkDeadlineInput.value = position.deadline || '';
            showDeadline();
            refreshSelectionHighlights();
        });
    }

    const roleDialog = document.getElementById('event-role-dialog');
    const statusDialog = document.getElementById('event-status-dialog');
    const statusForm = document.getElementById('event-status-form');
    function openWorkflowDialog(dialog, trigger) {
        spreadsheetShell.append(dialog);
        dialog.querySelector('.dialog-event-name').textContent = trigger.dataset.name;
        dialog.querySelector('.dialog-error').hidden = true;
        dialog.dataset.url = trigger.dataset.url;
        dialog.showModal();
    }
    document.querySelectorAll('.dialog-close').forEach(button => button.addEventListener('click', () => button.closest('dialog').close()));
    async function saveEventWorkflow(dialog, method, body) {
        const controls = [...dialog.querySelectorAll('button,select,input')];
        const disabled = controls.map(control => control.disabled);
        controls.forEach(control => control.disabled = true);
        const errorBox = dialog.querySelector('.dialog-error');
        errorBox.hidden = true;
        try {
            const response = await fetch(dialog.dataset.url, {
                method, headers: {'Content-Type':'application/json', 'Accept':'application/json', 'X-CSRF-TOKEN':'{{ csrf_token() }}'}, body: JSON.stringify(body)
            });
            const data = await response.json();
            if (!response.ok) throw new Error(Object.values(data.errors || {}).flat().join(' ') || data.message || 'Could not save. Please try again.');
            reloadSpreadsheetInPlace();
        } catch (error) {
            errorBox.textContent = error.message;
            errorBox.hidden = false;
            controls.forEach((control, index) => control.disabled = disabled[index]);
        }
    }
    document.querySelectorAll('.add-event-role').forEach(trigger => trigger.addEventListener('click', () => {
        const options = roleDialog.querySelector('.role-options');
        options.replaceChildren();
        JSON.parse(trigger.dataset.roles).forEach(role => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'secondary';
            button.textContent = `Add ${role.name}`;
            button.addEventListener('click', () => saveEventWorkflow(roleDialog, 'POST', {role_uuid: role.uuid}));
            options.append(button);
        });
        openWorkflowDialog(roleDialog, trigger);
    }));
    function updateStatusAction() {
        const action = statusForm.elements.action.value;
        statusForm.querySelector('.status-deadline').hidden = action !== 'open';
        statusForm.elements.deadline.required = action === 'open';
        statusForm.querySelector('.status-action-help').textContent = ({
            draft: 'Crew cannot enter availability. Existing responses and draft allocations are kept.',
            open: 'Crew can enter availability. Opening availability creates an availability request notification.',
            closed: 'Crew can no longer enter availability. Existing responses and allocations are kept.',
            publish_roster: 'Every required role must be assigned. Publishing notifies assigned crew and asks them to acknowledge their allocation.'
        })[action] || '';
    }
    document.querySelectorAll('.event-status-trigger').forEach(trigger => trigger.addEventListener('click', () => {
        statusForm.reset();
        statusForm.elements.deadline.value = trigger.dataset.deadline || '';
        statusForm.querySelector('option[value="draft"]').disabled = trigger.dataset.roster !== 'draft';
        updateStatusAction();
        openWorkflowDialog(statusDialog, trigger);
    }));
    statusForm.elements.action.addEventListener('change', updateStatusAction);
    statusForm.addEventListener('submit', event => {
        event.preventDefault();
        saveEventWorkflow(statusDialog, 'PATCH', {action: statusForm.elements.action.value, deadline: statusForm.elements.deadline.value || null});
    });

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
    }
    document.querySelectorAll('.event-select-cell').forEach((cell) => cell.addEventListener('click', (event) => {
        if (!document.getElementById('event-bulk-action').value || event.target.closest('a,button,select,input,details,summary,label')) return;
        const eventId = cell.closest('.event-row').dataset.event;
        const checkboxes = [...document.querySelectorAll(`.event-selector[data-event="${eventId}"]`)];
        if (!checkboxes.length) return;
        const selected = !checkboxes[0].checked;
        checkboxes.forEach((checkbox) => checkbox.checked = selected);
        refreshSelectionHighlights();
    }));
    const bulkAction = document.getElementById('event-bulk-action');
    const bulkDeadline = document.getElementById('bulk-deadline');
    const bulkDeadlineInput = bulkDeadline.querySelector('input');
    const showDeadline = () => {
        const open = bulkAction.value === 'open';
        bulkDeadline.hidden = !open;
        bulkDeadlineInput.required = open;
        spreadsheet.classList.toggle('bulk-selecting', Boolean(bulkAction.value));
        document.querySelectorAll('.bulk-select-col').forEach(cell => cell.hidden = !bulkAction.value);
        if (!bulkAction.value) document.querySelectorAll('.event-selector').forEach(checkbox => checkbox.checked = false);
        refreshSelectionHighlights();
    };
    document.querySelectorAll('.event-selector').forEach(checkbox => checkbox.addEventListener('change', () => {
        document.querySelectorAll(`.event-selector[data-event="${checkbox.dataset.event}"]`).forEach(other => other.checked = checkbox.checked);
        refreshSelectionHighlights();
    }));
    document.getElementById('bulk-events').addEventListener('submit', event => {
        if (!document.querySelector('.event-selector:checked')) {
            event.preventDefault();
            alert('Select at least one event using the checkboxes on the left.');
        }
    });
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
    @include('admin.scheduling-events._equipment-autosave')
    @include('admin.scheduling-events._shift-requirements-autosave')
</script>
@endpush

@push('styles')
<style>
    .contact-tabs[aria-label="Event management sections"] { flex-wrap:wrap; }
    .cancelled-event-row { filter:grayscale(1); opacity:.62; }
    .cancelled-event-row .col-date, .cancelled-event-row .col-time, .cancelled-event-row .col-event, .cancelled-event-row .col-venue { text-decoration:line-through; }
    .cancelled-event-row select, .cancelled-event-row input, .cancelled-event-row summary { pointer-events:none; }
    .badge.cancelled { border-color:#b9b9b9; background:#ededed; color:#555; text-decoration:none; }
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
    .event-select-cell { cursor:pointer; }
    .event-row.is-event-selected .sticky-col { background:#dff4fc; }
    .event-row.is-event-selected .col-event { box-shadow:inset 4px 0 0 #0AA0DB; }
    .pending-booking-row td { background:#fff7f7; }
    .pending-booking-row .col-event a { color:#b42318; }
    .badge.pending-booking { background:#fee2e2; color:#b42318; }
    .pending-booking-locked { text-align:center; color:#687780; font-size:10px; }
    .availability-sheet thead th { position:sticky; top:0; z-index:10; background:#0d3550; color:#fff; }
    .sticky-col { position:sticky; z-index:20; background:#fff; text-align:left; }
    .availability-sheet thead .sticky-col { z-index:40; background:#0d3550; }
    .bulk-selecting { --selection-width:36px; }
    .bulk-select-col { left:0;min-width:36px;width:36px;text-align:center; }
    .col-date { left:var(--selection-width,0px); min-width:82px; width:82px; }
    .col-time { left:calc(82px + var(--selection-width,0px)); min-width:110px; width:110px; }
    .col-event { left:calc(192px + var(--selection-width,0px)); min-width:175px; width:175px; max-width:175px; overflow:hidden; text-overflow:ellipsis; }
    .col-venue { left:calc(367px + var(--selection-width,0px)); min-width:165px; width:165px; max-width:165px; overflow:hidden; text-overflow:ellipsis; }
    .col-status { left:calc(532px + var(--selection-width,0px)); min-width:88px; width:88px; border-right:2px solid #0d3550; box-shadow:8px 0 10px -10px rgba(16,24,32,.75); }
    @media(max-width:1000px) {
        .availability-sheet tbody .sticky-col { position:static; }
        .availability-sheet thead .sticky-col { left:auto; }
    }
    .empty-role-cell { padding:0!important; }
    .add-event-role { display:block;width:100%;min-height:54px;background:transparent;color:var(--muted);border:0;border-radius:0;font-size:20px; }
    .add-event-role:hover,.add-event-role:focus-visible { background:#e1f3fb;color:#087eaa; }
    .event-status-trigger { cursor:pointer;white-space:normal; }
    .event-workflow-dialog { width:min(520px,calc(100vw - 32px));box-sizing:border-box;max-height:85vh;overflow:auto;border:1px solid var(--line);border-radius:12px;padding:22px;color:var(--ink); }
    .event-workflow-dialog::backdrop { background:#07162099; }
    .event-workflow-dialog header,.event-workflow-dialog footer { display:flex;align-items:center;justify-content:space-between;gap:12px; }
    .event-workflow-dialog h2 { margin:0; }
    .event-workflow-dialog form,.event-workflow-dialog .role-options { display:grid;gap:12px; }
    .event-workflow-dialog label { display:grid;gap:6px; }
    .event-workflow-dialog [hidden] { display:none!important; }
    .event-workflow-dialog select,.event-workflow-dialog input { width:100%;box-sizing:border-box; }
    .event-workflow-dialog .role-options button { text-align:left; }
    .event-workflow-dialog .dialog-error { color:#b42335;background:#fff0f0;padding:12px;border-radius:6px; }
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
    .equipment-popover { position:fixed; z-index:1000; width:min(390px,calc(100vw - 24px)); max-height:calc(100vh - 24px); overflow:auto; padding:10px; border:1px solid #9eb5c0; background:#fff; box-shadow:0 12px 30px rgba(16,24,32,.18); font-size:12px; }
    .equipment-save-status { margin:4px 0 8px;white-space:normal;color:#52636c;font-size:11px; }
    .equipment-popover-title { display:flex; align-items:center; justify-content:space-between; margin-bottom:5px; font-size:13px; }
    .equipment-popover-close { width:25px; min-height:25px; padding:0; border:0; background:transparent; color:#52636c; font-size:22px; line-height:1; cursor:pointer; }
    .equipment-popover-close:hover { color:#10202a; background:#edf4f7; }
    .equipment-row { display:grid; grid-template-columns:minmax(0,1fr) 45px 45px 145px; align-items:center; gap:5px; padding:5px 2px; border-bottom:1px solid #e3ebef; text-align:left; }
    @media(max-width:700px) { .equipment-row { grid-template-columns:minmax(70px,1fr) 36px 36px minmax(85px,1.2fr); } }
    .equipment-row input { width:auto; min-height:0; margin:auto; }
    .equipment-row .equipment-other { width:100%; min-height:30px; padding:4px 6px; font-size:11px; }
    .mini-badge { display:inline-block; margin-left:2px; padding:1px 3px; background:#fff2cc; color:#765500; font-size:8px; font-weight:700; }
    .mini-badge.equipment-covered { background:#dcfce7; color:#166534; }
    .shift-requirements-picker > summary { cursor:pointer; list-style:none; padding:6px 0; }
    .shift-requirements-picker > summary::-webkit-details-marker { display:none; }
    .shift-requirements-picker[open] { position:relative; z-index:1100; }
    .col-time:has(.shift-requirements-picker[open]) { z-index:1100; }
    .shift-requirements-popover { white-space:normal; text-align:left; }
    .shift-requirements-popover label { display:flex; align-items:center; gap:8px; margin:12px 0; }
    .shift-requirements-popover input[type=checkbox] { width:18px; height:18px; margin:0; }
    @media(max-width:1000px) { .col-venue, .col-status { position:static; } }
</style>
@endpush
