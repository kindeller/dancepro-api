@extends('layouts.public', ['title' => ($tab === 'messages' ? 'Studio messages' : 'Concert events').' · '.$studio->name, 'footerLabel' => 'Studio Hub'])

@include('customers.studios._styles')
@push('styles')@include('customers.messages._styles')@endpush

@section('content')
@include('customers.studios._header')
@include('customers.studios._tabs')

<section class="section" id="overview" @if($tab === 'events') data-preserve-scroll-forms data-scroll-group="Studio Hub sections" @endif><div class="container">
    @include('customers.studios._booking-actions')
    @if(session('status'))<div class="card card-body" role="status" data-form-save-status style="margin-bottom:20px;border-left:4px solid #20a66a">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="error" role="alert" data-form-save-error>{{ $errors->first() }}</div>@endif

    @if($tab === 'events')
    <div id="events" class="section-head"><div><p class="eyebrow">Bookings</p><h2 style="margin-bottom:0">Concert events</h2></div><span class="meta">Open an event to review details, complete actions and follow progress</span></div>

    @include('customers.studios._pending-bookings')
    @forelse($hub['events'] as $index => $card)
        @php($event = $card['event'])
        @php($item = $card['bookingItem'])
        <details id="studio-event-{{ $event->uuid }}" class="card hub-card {{ $card['overdueCount'] ? 'has-overdue-actions' : '' }}" style="margin-bottom:16px" @if(request()->boolean('actions') && $card['actionCount']) open @endif>
            <summary class="hub-card-header">
                <div>
                    <div><span class="hub-badge {{ $item?->approval_status === 'approved' ? 'success' : 'warning' }}">{{ $item?->booking?->status->value === 'partial_approval' ? 'Partial Approval · Event approved' : ($item?->approval_status === 'approved' ? '✓ Booking confirmed' : 'Booking awaiting confirmation') }}</span> @if($card['changeRequests']->whereIn('status', ['requested','in_review'])->isNotEmpty())<span class="hub-badge danger">Change requested</span>@endif</div>
                    <h2>{{ $card['title'] }}</h2>
                    <div class="hub-action-badges" aria-label="Your actions for {{ $card['title'] }}">
                        @if($card['overdueCount'])<span class="hub-badge danger">{{ $card['overdueCount'] }} overdue</span>@endif
                        @if($card['actionCount'] > $card['overdueCount'])<span class="hub-badge">{{ $card['actionCount'] - $card['overdueCount'] }} {{ $card['overdueCount'] ? 'other '.Str::plural('action', $card['actionCount'] - $card['overdueCount']) : Str::plural('action', $card['actionCount']).' needed' }}</span>@endif
                        @if(!$card['actionCount'])<span class="hub-badge success">Your actions complete</span>@endif
                    </div>
                    @if($card['overdueCount'])<p class="hub-overdue-summary">Overdue: {{ $card['overdueTasks']->join(' · ') }}</p>@endif
                    <div class="hub-event-meta">
                        <span>📅 {{ $event->event_date?->format('D j M Y') ?: 'Date to be confirmed' }}</span>
                        <span>🕒 @if($item?->starts_at){{ \Carbon\Carbon::parse($item->starts_at)->format('g:i a') }}@if($item->finishes_at) – {{ \Carbon\Carbon::parse($item->finishes_at)->format('g:i a') }}@endif @elseif($item?->finishes_at)Finishes {{ \Carbon\Carbon::parse($item->finishes_at)->format('g:i a') }} @else Time to be confirmed @endif</span>
                        <span>📍 {{ $item?->venue_name ?: $event->venue?->name ?: 'Venue to be confirmed' }}</span>
                    </div>
                </div>
                <div class="hub-progress"><div class="hub-progress-track"><span style="width:{{ $card['totalCount'] ? round($card['completeCount'] / $card['totalCount'] * 100) : 0 }}%"></span></div><strong>{{ $card['completeCount'] }}/{{ $card['totalCount'] }}</strong></div>
            </summary>
            <div class="hub-card-content">
                <form class="booking-change-form" method="post" action="{{ route('customer.studio-events.booking-changes.store', [$studio, $event]) }}">@csrf
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:10px"><h3 style="margin:0">Booking details</h3><button class="button secondary edit-booking" type="button">Request changes</button></div>
                    <div class="booking-panel">
                        <label class="booking-detail"><span>Date</span><input type="date" name="booking[event_date]" value="{{ $event->event_date?->format('Y-m-d') }}" readonly></label>
                        <div class="booking-detail"><span>Event type</span><strong>{{ $card['isConcert'] ? 'Concert' : 'Dress rehearsal / portraits' }}</strong></div>
                        <label class="booking-detail"><span>Venue</span><input name="booking[venue_name]" value="{{ $item?->venue_name ?: $event->venue?->name }}" maxlength="255" readonly>@if($item?->venue_address)<small>{{ $item->venue_address }}</small>@endif</label>
                        <div class="booking-detail"><span>Services</span><input type="hidden" name="booking[services_present]" value="1" disabled><div class="booking-services">@if($card['isConcert'])<label class="choice"><input type="checkbox" name="booking[services][]" value="photography" @checked($card['booking']?->wants_concert_photography) disabled> Photography</label><label class="choice"><input type="checkbox" name="booking[services][]" value="videography" @checked($card['booking']?->wants_concert_videography) disabled> Videography</label>@else<label class="choice"><input type="checkbox" name="booking[services][]" value="portraits" @checked($card['booking']?->wants_portrait_photography) disabled> Dress rehearsal portraits</label>@endif</div></div>
                        <label class="booking-detail"><span>{{ $card['isConcert'] ? 'Curtain up' : 'Shoot time' }}</span><input type="time" name="booking[starts_at]" value="{{ $item?->starts_at ? \Carbon\Carbon::parse($item->starts_at)->format('H:i') : '' }}" readonly></label>
                        <label class="booking-detail"><span>Expected finish</span><input type="time" name="booking[finishes_at]" value="{{ $item?->finishes_at ? \Carbon\Carbon::parse($item->finishes_at)->format('H:i') : '' }}" readonly></label>
                        <label class="booking-detail"><span>Venue access / setup</span><input type="time" name="booking[access_time]" value="{{ $card['arrival']?->format('H:i') }}" readonly></label>
                        <div class="booking-detail"><span>Booking status</span><strong>{{ str($item?->approval_status ?: 'pending')->replace('_',' ')->title() }}</strong></div>
                    </div>
                    <div class="booking-change-actions" hidden><label style="margin-top:16px">Note for DancePro <span class="meta">optional</span><textarea name="details" rows="3" maxlength="5000" placeholder="Explain anything that will help us review these changes"></textarea></label><div class="hub-actions"><button class="button">Submit requested changes</button><button class="button secondary cancel-booking-edit" type="button">Cancel</button></div></div>
                </form>
                @foreach($card['changeRequests'] as $change)<div class="card card-body" style="margin:14px 0;border-color:{{ in_array($change->status,['requested','in_review']) ? '#e69aa1' : 'var(--line)' }};background:{{ in_array($change->status,['requested','in_review']) ? '#fff7f7' : '#fff' }}"><span class="hub-badge {{ in_array($change->status,['requested','in_review']) ? 'danger' : 'success' }}">{{ str($change->status)->replace('_',' ')->title() }}</span><strong style="display:block;margin-top:8px">Booking detail change</strong><p style="margin:4px 0;white-space:pre-line">{{ $change->requested_value }}</p>@if($change->details)<p class="meta"><strong>Note:</strong> {{ $change->details }}</p>@endif</div>@endforeach

                <h3 style="margin-top:22px">Your actions</h3>
                <div class="hub-workspace">
                    @php($detailsTask = $card['checklist']->firstWhere('key', 'event_details'))
                    <details id="studio-details-{{ $event->uuid }}" class="hub-tool full"><summary class="hub-tool-summary action-summary"><span class="check-icon {{ $detailsTask['complete'] ? 'complete' : ($detailsTask['overdue'] ? 'overdue' : 'outstanding') }}">{{ $detailsTask['complete'] ? '✓' : '!' }}</span><div class="action-copy"><h3>Confirm event details</h3><p>{{ $detailsTask['instruction'] }}</p></div><div class="check-due {{ $detailsTask['overdue'] ? 'overdue' : '' }}">{{ $detailsTask['complete'] ? 'Complete' : ($detailsTask['dueDate'] ? ($detailsTask['overdue'] ? 'Overdue · ' : 'Due ').$detailsTask['dueDate']->format('j M Y') : 'To confirm') }}</div></summary><div class="hub-tool-content">
                        <form id="event-details-{{ $event->uuid }}" data-preserve-scroll-form="{{ request()->fullUrl() }}" data-scroll-group="Studio Hub sections" data-scroll-details-scope="overview" method="post" action="{{ route('customer.studio-events.requirements.store', [$studio, $event]) }}">@csrf
                            <input type="hidden" name="group" value="event_details">
                            <label class="choice"><input type="checkbox" name="requirements[]" value="event_details" @checked($detailsTask['complete'])><span>I confirm the arrival/setup times, start and finish times, date and venue are correct.</span></label>
                            <button class="button" style="margin-top:16px">Save confirmation</button>
                        </form>
                    </div></details>
                    @if(!$card['isConcert'] && $card['booking']?->wants_portrait_photography)
                    @php($portraitTask = $card['checklist']->firstWhere('key','portrait_requirements'))
                    <details id="studio-portraits-{{ $event->uuid }}" class="hub-tool full"><summary class="hub-tool-summary action-summary"><span class="check-icon {{ $portraitTask['complete'] ? 'complete' : ($portraitTask['overdue'] ? 'overdue' : 'outstanding') }}">{{ $portraitTask['complete'] ? '✓' : '!' }}</span><div class="action-copy"><h3>Confirm portrait shoot requirements</h3><p>{{ $portraitTask['instruction'] }}</p></div><div class="check-due {{ $portraitTask['overdue'] ? 'overdue' : '' }}">{{ $portraitTask['complete'] ? 'Complete' : ($portraitTask['dueDate'] ? ($portraitTask['overdue'] ? 'Overdue · ' : 'Due ').$portraitTask['dueDate']->format('j M Y') : 'Required') }}</div></summary><div class="hub-tool-content">
                        <form id="portrait-requirements-{{ $event->uuid }}" data-preserve-scroll-form="{{ request()->fullUrl() }}" data-scroll-group="Studio Hub sections" data-scroll-details-scope="overview" method="post" action="{{ route('customer.studio-events.requirements.store', [$studio, $event]) }}">@csrf
                            <label class="choice"><input type="checkbox" name="requirements[]" value="portrait_space" @checked($card['portraitAcknowledgements']->contains('portrait_space'))><span><strong>Portrait area prepared</strong><br>Provide a minimum 8 × 10 metre space with accessible power, a desk and a chair for the dress rehearsal or photo day portrait shoot.</span></label>
                            <label class="choice" style="margin-top:14px"><input type="checkbox" name="requirements[]" value="portrait_dancers" @checked($card['portraitAcknowledgements']->contains('portrait_dancers'))><span><strong>Every dancer will attend</strong><br>Ensure all students are brought by their teacher or minder after their rehearsal to have their photo taken.</span></label>
                            <button class="button" style="margin-top:16px">Save requirements</button>
                        </form>
                    </div></details>
                    @endif

                    @if($card['isConcert'] && $card['booking'])
                    @php($familyTask = $card['checklist']->firstWhere('key', 'families'))
                    <details id="studio-families-{{ $event->uuid }}" class="hub-tool full"><summary class="hub-tool-summary action-summary"><span class="check-icon {{ $familyTask['complete'] ? 'complete' : ($familyTask['overdue'] ? 'overdue' : 'outstanding') }}">{{ $familyTask['complete'] ? '✓' : '!' }}</span><div class="action-copy"><h3>Confirm final family numbers</h3><p>{{ $familyTask['instruction'] }}</p></div><div class="check-due {{ $familyTask['overdue'] ? 'overdue' : '' }}">{{ $familyTask['complete'] ? 'Complete' : ($familyTask['dueDate'] ? ($familyTask['overdue'] ? 'Overdue · ' : 'Due ').$familyTask['dueDate']->format('j M Y') : 'To confirm') }}</div></summary><div class="hub-tool-content">
                        @include('public.concert-bookings._family-numbers', ['booking' => $card['booking'], 'familyNumbersUrl' => route('customer.studio-bookings.families', [$studio, $card['booking']]), 'familyNumbersEmbedded' => true, 'familyNumbersId' => 'family-numbers-'.$event->uuid])
                    </div></details>
                    @endif

                    @if($card['checklist']->contains('key', 'seating'))
                    @php($seatTask = $card['checklist']->firstWhere('key','seating'))
                    <details id="studio-seats-{{ $event->uuid }}" class="hub-tool full"><summary class="hub-tool-summary action-summary"><span class="check-icon {{ $seatTask['complete'] ? 'complete' : ($seatTask['overdue'] ? 'overdue' : 'outstanding') }}">{{ $seatTask['complete'] ? '✓' : '!' }}</span><div class="action-copy"><h3>Enter reserved seats</h3><p>{{ $seatTask['instruction'] }}</p></div><div class="check-due {{ $seatTask['overdue'] ? 'overdue' : '' }}">{{ $seatTask['complete'] ? 'Complete' : ($seatTask['dueDate'] ? ($seatTask['overdue'] ? 'Overdue · ' : 'Due ').$seatTask['dueDate']->format('j M Y') : 'Not required') }}</div></summary><div class="hub-tool-content"><p>Enter seats in groups. Photography needs 1–2 seats per role; videography needs 5–6.</p>
                        <form method="post" action="{{ route('customer.studio-events.seats.store', [$studio, $event]) }}">@csrf
                            @foreach($card['seatedRoles'] as $requirement)
                                @php($role = $requirement->crewRole)
                                @php($minimum = str_contains($role->code, 'videographer') ? 5 : 1)
                                @php($limit = str_contains($role->code, 'videographer') ? 6 : 2)
                                @php($saved = collect($card['savedSeats']->get($role->id)?->seats ?? [])->groupBy('row')->map(fn($seats, $row) => 'Row '.$row.': '.$seats->pluck('seat')->join(', '))->join("\n"))
                                <fieldset style="border:0;padding:0;margin:0 0 18px"><legend><strong>{{ $role->name }}</strong> <span class="meta">({{ $minimum }}–{{ $limit }} seats)</span></legend>
                                    <label style="margin-top:10px">Reserved seats<textarea name="roles[{{ $role->code }}][seating]" rows="2" maxlength="500" placeholder="{{ $minimum === 5 ? 'J16-20' : 'J16-17' }}">{{ old("roles.{$role->code}.seating", $saved) }}</textarea></label>
                                    <span class="meta">For example: J16-20 or J16, 18. Use a new line for another row.</span>
                                </fieldset>
                            @endforeach
                            <button class="button">Save seating details</button>
                        </form>
                    </div></details>
                    @endif

                    @if($card['isConcert'])
                    @php($programmeTask = $card['checklist']->firstWhere('key','programme'))
                    <details id="studio-programme-{{ $event->uuid }}" class="hub-tool"><summary class="hub-tool-summary action-summary"><span class="check-icon {{ $programmeTask['complete'] ? 'complete' : ($programmeTask['overdue'] ? 'overdue' : 'outstanding') }}">{{ $programmeTask['complete'] ? '✓' : '!' }}</span><div class="action-copy"><h3>Upload programme</h3><p>{{ $programmeTask['instruction'] }}</p></div><div class="check-due {{ $programmeTask['overdue'] ? 'overdue' : '' }}">{{ $programmeTask['complete'] ? 'Complete' : ($programmeTask['dueDate'] ? ($programmeTask['overdue'] ? 'Overdue · ' : 'Due ').$programmeTask['dueDate']->format('j M Y') : 'Not required') }}</div></summary><div class="hub-tool-content"><p>Upload the final programme for this event. PDF is preferred; Word, image and other formats are accepted for DancePro to convert.</p>
                        <form class="studio-file-upload" method="post" action="{{ route('customer.studio-events.files.store', [$studio, $event]) }}" enctype="multipart/form-data">@csrf<input type="hidden" name="kind" value="programme"><label>Choose programme<input type="file" name="files[]" required></label><button class="button secondary">{{ $card['programme'] ? 'Replace programme' : 'Upload programme' }}</button><progress value="0" max="100" hidden style="width:100%;margin-top:8px"></progress><span class="meta upload-status"></span></form>
                    </div></details>
                    @endif

                    @if($card['checklist']->contains('key', 'video'))
                    @php($videoTask = $card['checklist']->firstWhere('key','video'))
                    <details id="studio-videos-{{ $event->uuid }}" class="hub-tool"><summary class="hub-tool-summary action-summary"><span class="check-icon {{ $videoTask['complete'] ? 'complete' : ($videoTask['overdue'] ? 'overdue' : 'outstanding') }}">{{ $videoTask['complete'] ? '✓' : '!' }}</span><div class="action-copy"><h3>Upload videos & slideshows</h3><p>{{ $videoTask['instruction'] }}</p></div><div class="check-due {{ $videoTask['overdue'] ? 'overdue' : '' }}">{{ $videoTask['complete'] ? 'Complete' : ($videoTask['dueDate'] ? ($videoTask['overdue'] ? 'Overdue · ' : 'Due ').$videoTask['dueDate']->format('j M Y') : 'Not required') }}</div></summary><div class="hub-tool-content"><p>Choose up to 20 supplied videos, slideshows or presentation files at a time. You can return and add more later.</p>
                        <form class="studio-file-upload" method="post" action="{{ route('customer.studio-events.files.store', [$studio, $event]) }}" enctype="multipart/form-data">@csrf<input type="hidden" name="kind" value="studio_video"><label>Choose studio files<input type="file" name="files[]" accept="video/*,.ppt,.pptx,.key,.pdf" multiple required></label><button class="button secondary">Upload studio files</button><progress value="0" max="100" hidden style="width:100%;margin-top:8px"></progress><span class="meta upload-status"></span></form>
                    </div></details>
                    @endif

                    @if($card['videoReview'])
                    @php($reviewAction = $card['videoReview'])
                    <div class="check-item hub-review-action {{ $reviewAction['complete'] ? 'complete' : ($reviewAction['url'] ? 'ready' : 'unavailable') }}" @if(!$reviewAction['complete'] && !$reviewAction['url']) aria-disabled="true" @endif>
                        <span class="check-icon" aria-hidden="true">{{ $reviewAction['complete'] ? '✓' : ($reviewAction['url'] ? '!' : '–') }}</span>
                        <div><h3>@if($reviewAction['url'])<a href="{{ $reviewAction['url'] }}">{{ $reviewAction['label'] }}</a>@else{{ $reviewAction['label'] }}@endif</h3><p>{{ $reviewAction['instruction'] }}</p></div>
                        <span class="check-due">{{ $reviewAction['status'] }}</span>
                    </div>
                    @endif

                    @if($card['files']->isNotEmpty())
                    <details id="studio-files-{{ $event->uuid }}" class="hub-tool full"><summary class="hub-tool-summary"><h3>Submitted files</h3><span class="hub-badge success">{{ $card['files']->count() }} {{ Str::plural('file', $card['files']->count()) }}</span></summary><div class="hub-tool-content"><div class="file-list">@foreach($card['files'] as $file)<div class="file-row"><div><a href="{{ route('studio-event-files.download', $file) }}">{{ $file->kind === 'programme' ? '📄' : '🎬' }} {{ $file->original_name }}</a><div class="meta">{{ number_format($file->size_bytes / 1048576, 1) }} MB · uploaded {{ $file->uploaded_at->format('j M Y, g:i a') }}</div>@if($file->review_note)<div class="meta">DancePro note: {{ $file->review_note }}</div>@endif</div><span class="hub-badge {{ $file->status === 'approved' ? 'success' : (str_contains($file->status, 'attention') ? 'danger' : 'warning') }}">{{ str($file->status)->replace('_',' ')->title() }}</span></div>@endforeach</div></div></details>
                    @endif
                </div>

                <h3 style="margin-top:22px">DancePro progress</h3>
                <div class="checklist" aria-label="{{ $card['title'] }} DancePro progress">
                    @foreach($card['production'] as $milestone)
                        <div class="check-item hub-production-item {{ $milestone['status'] === 'complete' ? 'complete' : '' }}">
                            <span class="check-icon">{{ $milestone['status'] === 'complete' ? '✓' : ($milestone['status'] === 'in_progress' ? '•' : '–') }}</span>
                            <div>@if($milestone['url'])<a href="{{ $milestone['url'] }}"><strong>{{ $milestone['label'] }}</strong></a>@else<strong>{{ $milestone['label'] }}</strong>@endif<p>{{ $milestone['detail'] }}</p></div>
                            <div class="hub-progress-meta">
                                @if($milestone['loginCode']['value'] ?? null)
                                    <span class="hub-login-code" title="{{ $milestone['loginCode']['label'] }}" aria-label="{{ $milestone['loginCode']['label'] }}: {{ $milestone['loginCode']['value'] }}"><span aria-hidden="true">Access Code:</span><code>{{ $milestone['loginCode']['value'] }}</code></span>
                                @endif
                                <span class="check-due">{{ str($milestone['status'])->replace('_',' ')->title() }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </details>
    @empty
        @if($hub['pendingEvents']->isEmpty())<div class="empty"><h2>No concert events yet</h2><p>Your submitted bookings will appear here automatically.</p></div>@endif
    @endforelse
    @endif

    @if($tab === 'messages')
    @include('customers.studios._message-form')
    <div id="messages" class="section-head"><div><p class="eyebrow">Communication history</p><h2 style="margin-bottom:0">Studio messages</h2></div><span class="meta">Your updates and conversations with DancePro</span></div>
    @include('customers.studios._message-list')
    @endif
</div></section>

<script>
document.querySelectorAll('.booking-change-form').forEach(form => {
    const edit = form.querySelector('.edit-booking');
    const actions = form.querySelector('.booking-change-actions');
    const fields = [...form.querySelectorAll('input[name^="booking"]')];
    const originals = fields.map(field => ({ field, value: field.value, checked: field.checked }));
    const setEditing = editing => {
        form.classList.toggle('editing', editing);
        actions.hidden = !editing;
        edit.hidden = editing;
        fields.forEach(field => { field.readOnly = !editing; field.disabled = !editing && field.type === 'checkbox'; });
    };
    edit.addEventListener('click', () => setEditing(true));
    form.querySelector('.cancel-booking-edit').addEventListener('click', () => {
        originals.forEach(({ field, value, checked }) => { field.value = value; field.checked = checked; });
        form.querySelector('textarea[name="details"]').value = '';
        setEditing(false);
    });
});
document.querySelectorAll('.message-reply').forEach(button => button.addEventListener('click', () => {
    document.getElementById('contact').open = true;
    const form = document.querySelector('#contact form');
    const subject = button.dataset.subject || '';
    form.querySelector('[name="event_uuid"]').value = button.dataset.event || '';
    form.querySelector('[name="subject"]').value = /^re:/i.test(subject) ? subject : `Re: ${subject}`;
    document.getElementById('contact').scrollIntoView({ behavior: 'smooth', block: 'start' });
    setTimeout(() => form.querySelector('[name="body"]').focus(), 350);
}));
document.querySelectorAll('.studio-file-upload').forEach(form => form.addEventListener('submit', event => {
    event.preventDefault();
    const button = form.querySelector('button');
    const progress = form.querySelector('progress');
    const status = form.querySelector('.upload-status');
    button.disabled = true; progress.hidden = false; status.textContent = ' Uploading…';
    const request = new XMLHttpRequest();
    request.open('POST', form.action);
    request.setRequestHeader('Accept', 'application/json');
    request.upload.addEventListener('progress', upload => {
        if (upload.lengthComputable) { progress.value = Math.round(upload.loaded / upload.total * 100); status.textContent = ` ${progress.value}% uploaded`; }
    });
    request.addEventListener('load', () => {
        if (request.status >= 200 && request.status < 300) { status.textContent = ' Upload complete'; form.dispatchEvent(new CustomEvent('dancepro:before-reload', { bubbles: true, detail: { message: 'Files uploaded successfully.' } })); window.location.reload(); return; }
        let message = 'Upload failed. Please try again.';
        try { const response = JSON.parse(request.responseText); message = response.message || Object.values(response.errors || {}).flat()[0] || message; } catch (_) {}
        status.textContent = ` ${message}`; button.disabled = false;
    });
    request.addEventListener('error', () => { status.textContent = ' Upload interrupted. Please try again.'; button.disabled = false; });
    request.send(new FormData(form));
}));
</script>
@include('customers.messages._notification-read')
@endsection
