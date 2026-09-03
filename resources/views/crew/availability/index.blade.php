@extends('layouts.crew', ['title' => 'My Shifts'])

@section('content')
@php
    $equipmentItems = [
        'video_1' => ['🔵', 'Video 1'], 'video_2' => ['🟢', 'Video 2'], 'video_3' => ['🔴', 'Video 3'],
        'backdrop_1' => ['🟠', 'Backdrop 1'], 'backdrop_2' => ['🟡', 'Backdrop 2'], 'media' => ['💾', 'Media'],
    ];
@endphp

<div class="page-heading">
    <div><div class="crew-hub-brand"><img src="{{ asset('images/brand/dancepro-icon-blue.png') }}" alt=""><span>CREW HUB</span></div><h1>My Shifts</h1><p class="muted">Everything you need for your upcoming work.</p></div>
    <div style="display:grid;justify-items:end;gap:8px"><a class="button secondary" href="{{ route('crew.availability.index',['view'=>$filter==='calendar'?'upcoming':'calendar']) }}">{{ $filter==='calendar'?'List view':'Calendar view' }}</a>@if($pendingAvailability || $needsAcknowledgement)<div class="action-summary">@if($pendingAvailability)<span><strong>{{ $pendingAvailability }}</strong> availability</span>@endif @if($needsAcknowledgement)<span><strong>{{ $needsAcknowledgement }}</strong> acknowledge</span>@endif</div>@endif</div>
</div>

@if($nextAssignment)
@php
    $nextShift = $nextAssignment->shift;
    $nextEvent = $nextShift->schedulingEvent;
    $nextShiftDate = $nextShift->shift_date ?? $nextEvent->event_date;
    $nextTimeEntry = $nextAssignment->timeEntry;
    $clockOutAvailable = $nextTimeEntry?->actual_clock_in_at || ($nextShift->posted_arrival_at && now()->gte($nextShift->posted_arrival_at->copy()->addHours(2)));
    $unfinishedCrew = $nextShift->assignments->where('status', 'published')->filter(fn ($teamAssignment) => !$teamAssignment->timeEntry?->actual_finish_at);
    $nextChecklist = $checklistProgress[$nextAssignment->id] ?? ['done' => 0, 'total' => 0, 'completed_at' => null];
@endphp
<section class="card next-shift-card">
    <div class="section-label">Next shift</div>
    <div class="next-grid">
        <div class="date-tile"><strong>{{ $nextShiftDate->format('d') }}</strong><span>{{ strtoupper($nextShiftDate->format('M')) }}</span></div>
        <div class="event-visual">@if($eventLogoUrls->get($nextEvent->uuid))<img src="{{ $eventLogoUrls->get($nextEvent->uuid) }}" alt="{{ $nextEvent->name }} logo">@else<span class="event-image-label">Image</span>@endif</div>
        <div><h2>{{ $nextEvent->name }}</h2>@if($nextEvent->concertBookingItem?->title)<div class="muted">{{ $nextEvent->concertBookingItem->title }}</div>@endif<div class="shift-meta"><strong>{{ $nextAssignment->role->name }}</strong>@if($nextAssignment->is_team_leader)<span>👑 Team Leader</span>@endif<span>Arrive {{ $nextShift->posted_arrival_at?->format('g:i a') ?? 'TBC' }}</span></div></div>
        <span class="status-pill {{ $nextAssignment->acknowledgement_status === 'acknowledged' ? 'done' : 'attention' }}">{{ $nextAssignment->acknowledgement_status === 'acknowledged' ? '✓ Acknowledged' : 'Acknowledgement needed' }}</span>
    </div>
    <div class="next-shift-workflow">
        <section class="workflow-step {{ $nextTimeEntry?->actual_clock_in_at ? 'complete' : '' }}">
            <span class="workflow-number">1</span><h3>Clock in</h3>
            @if($nextTimeEntry?->actual_clock_in_at)
                <strong>✓ Clocked in {{ $nextTimeEntry->actual_clock_in_at->format('g:i a') }}</strong>
            @elseif($clockOutAvailable)
                <span class="status-pill attention">Clock-in not recorded</span>
            @else
                <form method="POST" action="{{ route('crew.assignments.time.clock-in',$nextAssignment) }}">@csrf<button type="submit">Clock in</button></form>
                @if($nextShift->starts_at)<span class="shift-countdown" data-shift-start="{{ $nextShift->starts_at->toIso8601String() }}">Shift starts soon</span>@else<span class="muted">Shift start time TBC</span>@endif
            @endif
        </section>

        <section class="workflow-step {{ $nextChecklist['completed_at'] ? 'complete' : '' }}">
            <span class="workflow-number">2</span><h3>Pre-Start Checks</h3>
            @if($nextChecklist['total'] > 0)
                <button type="button" data-open-prestart>Open Pre-Start Checks</button>
                <strong data-next-checklist-complete @if(!$nextChecklist['completed_at']) hidden @endif>✓ Checks completed {{ $nextChecklist['completed_at']?->format('g:i a') }}</strong>
                <span class="muted" data-next-checklist-summary @if($nextChecklist['completed_at']) hidden @endif>{{ $nextChecklist['done'] }} of {{ $nextChecklist['total'] }} complete</span>
            @else
                <span class="muted">No checks assigned</span>
            @endif
        </section>

        <section class="workflow-step {{ $nextTimeEntry?->actual_finish_at ? 'complete' : '' }}">
            <span class="workflow-number">3</span><h3>Clock out</h3>
            @if($nextTimeEntry?->actual_finish_at)
                <strong>✓ Clocked out {{ $nextTimeEntry->actual_finish_at->format('g:i a') }}</strong>
            @elseif($clockOutAvailable)
                <form method="POST" action="{{ route('crew.assignments.time.finish',$nextAssignment) }}">@csrf<button type="submit">Clock out</button></form>
            @else
                <span class="muted">Available after clock-in</span>
            @endif

            @if($nextAssignment->is_team_leader && $nextEvent->event_type->value === 'competition' && $unfinishedCrew->isEmpty())
                <span class="status-pill done">✓ Crew clocked out</span>
            @elseif($nextAssignment->is_team_leader && $nextEvent->event_type->value === 'competition' && $clockOutAvailable)
            <details class="venue-details">
                <summary>Clock crew out</summary>
                <form method="POST" action="{{ route('crew.assignments.time.finish-team',$nextAssignment) }}" class="grid" style="margin-top:12px">
                    @csrf
                    <label>Finish time<input type="time" name="actual_finish_at" value="{{ now()->format('H:i') }}" required></label>
                    <div class="team-finish-list">
                        @foreach($nextShift->assignments as $teamAssignment)
                        <div><strong>{{ $teamAssignment->crewProfile->preferred_name ?: $teamAssignment->crewProfile->user->name }}@if($teamAssignment->id === $nextAssignment->id) (You · Team Leader)@endif</strong><span>{{ $teamAssignment->timeEntry?->actual_finish_at ? 'Clocked out '.$teamAssignment->timeEntry->actual_finish_at->format('g:i a') : 'Will be clocked out' }}</span></div>
                        @endforeach
                    </div>
                    <label>Optional note<input name="optional_note" placeholder="Optional"></label>
                    <button type="submit">Clock all crew out</button>
                </form>
            </details>
            @endif
        </section>
    </div>
</section>

@if($nextChecklist['total'] > 0)
<dialog class="prestart-dialog" data-prestart-dialog aria-labelledby="prestart-dialog-title">
    <div class="prestart-dialog-heading">
        <div><p class="eyebrow">{{ $nextEvent->name }}</p><h2 id="prestart-dialog-title">Pre-Start Checks</h2><p class="muted">Complete these checks before the event begins.</p></div>
        <button type="button" class="secondary" data-close-prestart aria-label="Close pre-start checks">Close</button>
    </div>
    <div class="prestart-progress"><strong data-prestart-progress>{{ $nextChecklist['done'] }} of {{ $nextChecklist['total'] }} complete</strong></div>
    @foreach($nextChecklistTemplates as $template)
        <div class="checklist-group"><h3>{{ $template->name }}</h3>
        @foreach($template->items as $item)
            @php
                $checked = $nextAssignment->checklistCompletions
                    ->whereNotNull('completed_at')
                    ->contains('checklist_template_item_id', $item->id);
            @endphp
            <label class="checklist-item {{ $checked?'complete':'' }}"><input type="checkbox" data-prestart-checklist-url="{{ route('crew.assignments.checklist.toggle',[$nextAssignment,$item]) }}" @checked($checked)><span>{{ $item->instruction }}</span></label>
        @endforeach
        </div>
    @endforeach
    <div class="prestart-dialog-actions"><a href="{{ route('crew.assignments.show',$nextAssignment) }}#checklist-progress">Open in full shift details</a><button type="button" data-close-prestart>Done</button></div>
</dialog>
@endif
@endif

@push('scripts')
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
document.querySelectorAll('[data-shift-start]').forEach((countdown) => {
    const startsAt = new Date(countdown.dataset.shiftStart).getTime();
    const render = () => {
        const difference = startsAt - Date.now();
        const absoluteMinutes = Math.max(0, Math.ceil(Math.abs(difference) / 60000));
        const hours = Math.floor(absoluteMinutes / 60);
        const minutes = absoluteMinutes % 60;
        const duration = hours > 0 ? `${hours}h ${minutes}m` : `${minutes}m`;
        countdown.textContent = difference >= 0 ? `Shift starts in ${duration}` : `Shift started ${duration} ago`;
    };
    render();
    window.setInterval(render, 60000);
});

const prestartDialog = document.querySelector('[data-prestart-dialog]');
document.querySelector('[data-open-prestart]')?.addEventListener('click', () => prestartDialog?.showModal());
document.querySelectorAll('[data-close-prestart]').forEach((button) => button.addEventListener('click', () => prestartDialog?.close()));
prestartDialog?.addEventListener('click', (event) => {
    if (event.target === prestartDialog) prestartDialog.close();
});
document.querySelectorAll('[data-prestart-checklist-url]').forEach((box) => box.addEventListener('change', async () => {
    const previous = !box.checked;
    box.disabled = true;
    try {
        const response = await fetch(box.dataset.prestartChecklistUrl, {
            method: 'PUT',
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
            body: JSON.stringify({completed: box.checked}),
        });
        if (!response.ok) throw new Error();
        box.closest('.checklist-item').classList.toggle('complete', box.checked);
        const boxes = [...document.querySelectorAll('[data-prestart-checklist-url]')];
        const completed = boxes.filter((item) => item.checked).length;
        document.querySelector('[data-prestart-progress]').textContent = `${completed} of ${boxes.length} complete`;
        const workflow = document.querySelector('[data-open-prestart]')?.closest('.workflow-step');
        const completedLabel = workflow?.querySelector('[data-next-checklist-complete]');
        const summary = workflow?.querySelector('[data-next-checklist-summary]');
        if (completed === boxes.length) {
            workflow?.classList.add('complete');
            completedLabel.textContent = `✓ Checks completed ${new Intl.DateTimeFormat('en-AU', {hour: 'numeric', minute: '2-digit'}).format(new Date())}`;
            completedLabel.hidden = false;
            summary.hidden = true;
        } else {
            workflow?.classList.remove('complete');
            completedLabel.hidden = true;
            summary.textContent = `${completed} of ${boxes.length} complete`;
            summary.hidden = false;
        }
    } catch (error) {
        box.checked = previous;
        alert('That checklist update could not be saved. Please try again.');
    } finally {
        box.disabled = false;
    }
}));
</script>
@endpush

<nav class="filter-tabs main-shift-menu" aria-label="My shifts menu">
    <a class="{{ $filter === 'availability' ? 'active' : '' }}" href="{{ route('crew.availability.index', ['view' => 'availability']) }}">@if($pendingAvailability)<span class="alert-count">{{ $pendingAvailability }}</span>@endif Availability</a>
    <a class="{{ $filter === 'acknowledge' ? 'active' : '' }}" href="{{ route('crew.availability.index', ['view' => 'acknowledge']) }}">@if($needsAcknowledgement)<span class="alert-count">{{ $needsAcknowledgement }}</span>@endif Acknowledge</a>
    <a class="{{ $filter === 'upcoming' ? 'active' : '' }}" href="{{ route('crew.availability.index', ['view' => 'upcoming']) }}">Upcoming</a>
    <a class="{{ $filter === 'cover' ? 'active' : '' }}" href="{{ route('crew.availability.index', ['view' => 'cover']) }}">@if($pendingCoverCount)<span class="alert-count">{{ $pendingCoverCount }}</span>@endif Cover</a>
    <a class="{{ $filter === 'completed' ? 'active' : '' }}" href="{{ route('crew.availability.index', ['view' => 'completed']) }}">Completed</a>
</nav>

@if($filter === 'availability')
<section>
    <div class="section-heading"><div><p class="eyebrow">Action needed</p><h2>Availability requests</h2></div><span class="count-pill">{{ $events->sum(fn($event) => $event->shifts->count()) }} shifts</span></div>
    @foreach($events as $event)
    @php
        $eventAvailabilityComplete = $event->shifts->every(
            fn ($shift) => $shift->availabilityResponses->isNotEmpty()
        );
    @endphp
    <article class="card availability-card">
        <div class="event-heading" style="justify-content:space-between"><div style="display:flex;align-items:center;gap:12px"><div class="event-visual">@if($eventLogoUrls->get($event->uuid))<img src="{{ $eventLogoUrls->get($event->uuid) }}" alt="{{ $event->name }} logo">@else<span class="event-image-label">Image</span>@endif</div><div><h3>{{ $event->name }}</h3><div class="muted">{{ $event->venue?->name ?? 'Venue to be confirmed' }} · replies due {{ $event->availability_deadline->format('D j M, g:i a') }}</div></div></div><span class="status-pill {{ $eventAvailabilityComplete?'done':'attention' }}">{{ $eventAvailabilityComplete?'✓ Availability saved':'Please select availability' }}</span></div>
        @foreach($event->shifts as $shift)
        @php
            $response = $shift->availabilityResponses->first();
            $availabilityDate = $shift->shift_date ?? $event->event_date;
        @endphp
        <div class="availability-row">
            <div><strong>{{ $availabilityDate->format('D j M') }}@if($shift->period) · {{ ucfirst($shift->period->value) }}@endif</strong><div class="muted">{{ $shift->posted_arrival_at ? 'Arrive '.$shift->posted_arrival_at->format('g:i a') : 'Times TBC' }}</div></div>
            <form method="POST" action="{{ route('crew.availability.store', $shift) }}" class="availability-form">@csrf @method('PUT')<input name="note" value="{{ $response?->note }}" placeholder="Optional note"><div>@if($response)<div class="response-saved {{ $response->status->value }}">✓ Saved as {{ ucfirst($response->status->value) }}</div>@endif<div class="choice-buttons"><button class="available-button {{ $response?->status->value === 'available' ? 'selected' : '' }}" name="status" value="available">{{ $response?->status->value === 'available' ? '✓ ' : '' }}Available</button><button class="unavailable {{ $response?->status->value === 'unavailable' ? 'selected' : '' }}" name="status" value="unavailable">{{ $response?->status->value === 'unavailable' ? '✓ ' : '' }}Unavailable</button></div></div></form>
        </div>
        @endforeach
    </article>
    @endforeach
    @if($events->isEmpty())<div class="card empty-state"><strong>Availability is up to date.</strong><p class="muted">There are no open requests right now.</p></div>@endif
</section>
@endif

@if(in_array($filter,['acknowledge','upcoming','completed'],true))
<section>
    <div class="section-heading"><div><p class="eyebrow">Schedule</p><h2>Assigned shifts</h2></div></div>

    @forelse($visibleAssignments as $assignment)
    @include('crew.availability._shift-card',['assignment'=>$assignment])
    @empty
    <div class="card empty-state"><strong>No shifts here.</strong><p class="muted">Your shifts will appear here when a roster is published.</p></div>
    @endforelse
</section>
@endif

@if($filter === 'calendar')
<section>
    <div class="section-heading"><div><p class="eyebrow">Schedule</p><h2>Calendar</h2></div><span class="muted">Select a date with a blue dot to see its shifts.</span></div>
    <div class="calendar-scroll" style="margin-top:12px">
    @foreach($calendarMonths as $month)
        @php
            $monthStart = $month->copy()->startOfMonth();
            $leadingDays = $monthStart->dayOfWeekIso - 1;
        @endphp
        <article class="card calendar-month" id="month-{{ $month->format('Y-m') }}">
            <h2>{{ $month->format('F Y') }}</h2>
            <div class="calendar-grid">
                @foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $weekday)<div class="calendar-weekday">{{ $weekday }}</div>@endforeach
                @for($blank=0;$blank<$leadingDays;$blank++)<div aria-hidden="true"></div>@endfor
                @for($day=1;$day<=$month->daysInMonth;$day++)
                    @php
                        $calendarDate = $month->copy()->day($day);
                        $dateKey = $calendarDate->toDateString();
                        $dateAssignments = $assignmentsByDate->get($dateKey, collect());
                    @endphp
                    @if($dateAssignments->isNotEmpty())
                        <button type="button" class="calendar-day {{ $calendarDate->isToday()?'today':'' }}" data-calendar-date="{{ $dateKey }}" aria-expanded="false"><span>{{ $day }}</span><span class="calendar-dot" aria-label="Assigned shift"></span></button>
                    @else
                        <div class="calendar-day {{ $calendarDate->isToday()?'today':'' }}"><span>{{ $day }}</span></div>
                    @endif
                @endfor
            </div>
            @foreach($assignmentsByDate->filter(fn ($items,$date) => str_starts_with($date,$month->format('Y-m'))) as $date => $dateAssignments)
                <div class="calendar-selection" data-calendar-panel="{{ $date }}" hidden><div class="section-heading"><h2>{{ \Carbon\Carbon::parse($date)->format('l j F') }}</h2><span class="count-pill">{{ $dateAssignments->count() }} shift{{ $dateAssignments->count()===1?'':'s' }}</span></div>@foreach($dateAssignments as $assignment)@include('crew.availability._shift-card',['assignment'=>$assignment])@endforeach</div>
            @endforeach
        </article>
    @endforeach
    </div>
</section>
@endif

@if($filter === 'cover')
<section><div class="section-heading"><div><p class="eyebrow">Action needed</p><h2>Requests sent to you</h2></div><span class="count-pill">{{ $pendingCoverCount }}</span></div>
@forelse($receivedCoverRequests as $recipient)
@php
    $coverRequest = $recipient->coverRequest;
    $coverAssignment = $coverRequest->assignment;
    $coverEvent = $coverAssignment->shift->schedulingEvent;
@endphp
<article class="card"><div class="section-heading"><div><h3>{{ $coverEvent->name }}</h3><p class="muted">{{ $coverAssignment->shift->shift_date->format('D j M Y') }} · {{ $coverAssignment->role->name }} · requested by {{ $coverRequest->requester->preferred_name }}</p></div><span class="status-pill {{ $recipient->status==='accepted'?'done':($recipient->status==='pending'&&$coverRequest->status==='open'?'attention':'') }}">{{ $recipient->status==='pending'&&$coverRequest->status!=='open'?'Filled':ucfirst($recipient->status) }}</span></div>@if($coverRequest->message)<div class="venue-details"><strong>Personal message</strong><p>{!! nl2br(e($coverRequest->message)) !!}</p></div>@else<p>Can you cover this shift?</p>@endif @if($recipient->status==='pending'&&$coverRequest->status==='open')<form method="POST" action="{{ route('crew.cover.accept',$coverRequest) }}" style="margin-top:12px">@csrf<button>Accept cover</button></form>@endif</article>
@empty<div class="card empty-state"><strong>No cover requests.</strong><p class="muted">Requests sent directly to you will appear here.</p></div>@endforelse
</section>

<section><div class="section-heading"><div><p class="eyebrow">Your requests</p><h2>Cover you have organised</h2></div></div>
@forelse($sentCoverRequests as $coverRequest)
@php
    $coverAssignment = $coverRequest->assignment;
    $coverEvent = $coverAssignment->shift->schedulingEvent;
@endphp
<article class="card"><div class="section-heading"><div><h3>{{ $coverEvent->name }}</h3><p class="muted">{{ $coverAssignment->shift->shift_date->format('D j M Y') }} · {{ $coverAssignment->role->name }}</p></div><span class="status-pill {{ $coverRequest->status==='accepted'?'done':'attention' }}">{{ ucfirst($coverRequest->status) }}</span></div>@if($coverRequest->message)<p>“{{ $coverRequest->message }}”</p>@endif<p class="muted">Sent to {{ $coverRequest->recipients->pluck('crewProfile.preferred_name')->filter()->join(', ') }}.</p>@if($coverRequest->acceptedBy)<p><strong>{{ $coverRequest->acceptedBy->preferred_name }}</strong> accepted the shift.</p>@endif</article>
@empty<div class="card empty-state"><strong>You haven’t requested cover.</strong></div>@endforelse
</section>
@endif

@if($notifications->isNotEmpty())<section><div class="section-heading"><div><p class="eyebrow">Recent</p><h2>Updates</h2></div></div><div class="card updates-list">@foreach($notifications as $notification)<div @if(!$notification->read_at)data-notification-read-url="{{ route('crew.notifications.read',$notification) }}"@endif><strong class="notification-title">@if(!$notification->read_at)<span class="unread-dot" aria-label="Unread"></span>@endif{{ $notification->title }}</strong><p>{{ $notification->message }}</p><span class="muted">{{ $notification->created_at->diffForHumans() }}</span></div>@endforeach</div></section>@endif
@endsection

@push('scripts')
<script nonce="{{ request()->attributes->get('csp_nonce') }}">document.querySelectorAll('[data-calendar-date]').forEach(button=>button.addEventListener('click',()=>{const date=button.dataset.calendarDate;const panel=document.querySelector(`[data-calendar-panel="${date}"]`);const wasOpen=button.getAttribute('aria-expanded')==='true';document.querySelectorAll('[data-calendar-date]').forEach(item=>item.setAttribute('aria-expanded','false'));document.querySelectorAll('[data-calendar-panel]').forEach(item=>item.hidden=true);if(!wasOpen&&panel){button.setAttribute('aria-expanded','true');panel.hidden=false;requestAnimationFrame(()=>panel.scrollIntoView({behavior:'smooth',block:'nearest'}));}}));const unreadTimers=new WeakMap();const markNotificationRead=async item=>{unreadTimers.delete(item);unreadObserver.unobserve(item);try{const response=await fetch(item.dataset.notificationReadUrl,{method:'PATCH',headers:{'Accept':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'}});if(response.ok)item.querySelector('.unread-dot')?.remove();else unreadObserver.observe(item);}catch(error){unreadObserver.observe(item);}};const unreadObserver=new IntersectionObserver(entries=>entries.forEach(entry=>{const existingTimer=unreadTimers.get(entry.target);if(!entry.isIntersecting){if(existingTimer)clearTimeout(existingTimer);unreadTimers.delete(entry.target);return;}if(!existingTimer)unreadTimers.set(entry.target,setTimeout(()=>markNotificationRead(entry.target),3000));}),{threshold:.6});document.querySelectorAll('[data-notification-read-url]').forEach(item=>unreadObserver.observe(item));</script>
@endpush
