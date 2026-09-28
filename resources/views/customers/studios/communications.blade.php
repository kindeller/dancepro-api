@extends('layouts.public', ['title' => $studio->name.' Studio Hub', 'footerLabel' => 'Studio Hub'])

@push('styles')
<style>
    .hub-hero { padding:52px 0 42px; }
    .hub-hero h1 { font-size:clamp(38px,6vw,64px); }
    .hub-hero-grid { display:grid;grid-template-columns:minmax(0,1fr) auto;gap:30px;align-items:end; }
    .hub-identity { display:flex;align-items:center;gap:24px;min-width:0; }
    .hub-studio-logo { width:clamp(100px,11vw,150px);height:clamp(82px,9vw,120px);flex:0 0 auto;border-radius:10px;background:#fff;object-fit:contain;padding:8px;box-shadow:0 10px 30px rgba(0,0,0,.2); }
    .hub-identity-copy { min-width:0; }
    .hub-summary { display:grid;grid-template-columns:repeat(3,minmax(112px,1fr));gap:10px; }
    .hub-stat { min-width:112px;border:1px solid rgba(255,255,255,.2);border-radius:10px;background:rgba(255,255,255,.1);padding:14px 16px;backdrop-filter:blur(8px); }
    .hub-stat strong { display:block;font-size:25px;line-height:1.1; }
    .hub-stat span { color:#d9e8ee;font-size:12px;font-weight:750; }
    .hub-tabs { position:sticky;top:0;z-index:10;border-bottom:1px solid var(--line);background:rgba(244,248,250,.96);backdrop-filter:blur(12px); }
    .hub-tabs .container { display:flex;gap:4px;overflow-x:auto; }
    .hub-tabs a { border-bottom:3px solid transparent;padding:15px 17px;color:var(--muted);font-size:14px;font-weight:800;text-decoration:none;white-space:nowrap; }
    .hub-tabs a:hover { border-color:var(--accent);color:var(--ink); }
    .hub-tabs a.active { border-color:var(--accent);color:var(--ink);background:#fff; }
    .hub-intro { display:grid;grid-template-columns:minmax(0,1.5fr) minmax(260px,.5fr);gap:20px;margin-bottom:26px; }
    .hub-card { overflow:visible; }
    .hub-card-header { display:grid;grid-template-columns:minmax(0,1fr) auto 34px;gap:20px;align-items:center;padding:20px 22px;cursor:pointer;list-style:none; }
    .hub-card-header::-webkit-details-marker { display:none; }
    .hub-card-header h2 { margin:3px 0 5px;font-size:26px; }
    .hub-card-header::after { content:'⌄';display:grid;width:34px;height:34px;place-items:center;border-radius:50%;background:#eaf6fa;font-size:20px;transition:transform .2s; }
    details[open]>.hub-card-header::after { transform:rotate(180deg); }
    .hub-card-content { border-top:1px solid var(--line);padding:22px; }
    .hub-progress { display:flex;align-items:center;gap:10px; }
    .hub-progress-track { width:120px;height:8px;overflow:hidden;border-radius:99px;background:#dfe9ed; }
    .hub-progress-track span { display:block;height:100%;border-radius:inherit;background:#20a66a; }
    .hub-event-meta { display:flex;flex-wrap:wrap;gap:8px 18px;margin-top:10px;color:var(--muted);font-size:14px;font-weight:650; }
    .hub-badge { display:inline-flex;align-items:center;gap:6px;border-radius:99px;background:#eaf6fa;color:#075d7e;padding:5px 10px;font-size:12px;font-weight:850; }
    .hub-badge.success { background:#e8f8ef;color:#12643e; }
    .hub-badge.warning { background:#fff4db;color:#865b00; }
    .hub-badge.danger { background:#fff0f1;color:#a5202c; }
    .booking-panel { display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px 24px;border-radius:10px;background:#f5f9fb;padding:18px; }
    .booking-detail span { display:block;color:var(--muted);font-size:12px;font-weight:800;letter-spacing:.04em;text-transform:uppercase; }
    .booking-detail strong { display:block;margin-top:2px; }
    .booking-detail small { display:block;color:var(--muted); }
    .booking-panel label { margin-bottom:0; }
    .booking-detail input[readonly] { min-height:0;border:0;background:transparent;padding:2px 0;box-shadow:none;color:var(--ink);font-weight:800; }
    .booking-change-form.editing .booking-detail input { min-height:46px;border:1px solid var(--accent);background:#fff;padding:10px 12px;font-weight:650; }
    .booking-services { display:flex;flex-wrap:wrap;gap:8px 16px;margin-top:6px; }
    .booking-services .choice { margin:0; }
    .hub-actions { display:flex;flex-wrap:wrap;gap:10px;margin-top:16px; }
    .checklist { display:grid;gap:10px;margin:20px 0; }
    .check-item { display:grid;grid-template-columns:32px minmax(0,1fr) auto;gap:12px;align-items:start;border:1px solid var(--line);border-radius:10px;padding:14px;background:#fff; }
    .check-icon { display:grid;width:30px;height:30px;place-items:center;border-radius:50%;background:#edf2f4;color:#65757d;font-weight:900; }
    .check-item.complete .check-icon { background:#dff6e9;color:#167246; }
    .check-item.overdue { border-color:#f0bdc1;background:#fffafa; }
    .check-item p { margin:3px 0 0;color:var(--muted);font-size:13px; }
    .hub-progress-meta { display:flex;flex-wrap:wrap;justify-content:flex-end;align-items:center;gap:8px 14px;max-width:300px; }
    .hub-login-code { display:inline-flex;align-items:center;gap:6px;max-width:100%;padding:6px 10px;border:1px solid #c5e7f4;border-radius:8px;background:#eaf6fa;color:#075d7e;font-size:12px;font-weight:750; }
    .hub-login-code > span { white-space:nowrap; }
    .hub-login-code code { min-width:0;font-size:14px;font-weight:850;letter-spacing:.04em;overflow-wrap:anywhere;user-select:all; }
    .check-due { color:var(--muted);font-size:12px;font-weight:800;text-align:right; }
    .check-item.overdue .check-due { color:#a5202c; }
    .hub-workspace { display:grid;grid-template-columns:1fr;gap:10px; }
    .hub-tool { border:1px solid var(--line);border-radius:10px;padding:18px; }
    details.hub-tool { padding:0; }
    .hub-tool-summary { display:flex;align-items:center;justify-content:space-between;gap:12px;cursor:pointer;list-style:none;padding:16px 18px; }
    .hub-tool-summary::-webkit-details-marker { display:none; }
    .hub-tool-summary::after { content:'+';display:grid;width:28px;height:28px;place-items:center;border-radius:50%;background:#eaf6fa;font-weight:900; }
    details.hub-tool[open]>.hub-tool-summary::after { content:'−'; }
    .hub-tool-summary h3 { margin:0; }
    .hub-tool-summary.action-summary { display:grid;grid-template-columns:32px minmax(0,1fr) auto 28px;align-items:center; }
    .hub-tool-summary.action-summary::after { grid-column:4;grid-row:1 / span 2; }
    .hub-tool-summary.action-summary .check-icon { grid-column:1;grid-row:1 / span 2; }
    .hub-tool-summary.action-summary .action-copy { grid-column:2;min-width:0; }
    .hub-tool-summary.action-summary .action-copy p { margin:3px 0 0; }
    .hub-tool-summary.action-summary .check-due { grid-column:3;grid-row:1 / span 2;white-space:nowrap; }
    .hub-tool-content { border-top:1px solid var(--line);padding:18px; }
    .hub-tool h3 { display:flex;align-items:center;gap:8px;margin-bottom:5px; }
    .hub-tool p { margin-bottom:14px;color:var(--muted);font-size:14px; }
    .hub-tool.full { grid-column:1 / -1; }
    .file-list { display:grid;gap:8px;margin-top:14px; }
    .file-row { display:grid;grid-template-columns:minmax(0,1fr) auto;gap:12px;align-items:center;border-radius:8px;background:#f5f9fb;padding:11px 12px; }
    .file-row a { font-weight:800;text-decoration:none; }
    .message-list { display:grid;gap:12px; }
    .message-card { box-shadow:none; }
    .message-card summary { cursor:pointer;display:grid;grid-template-columns:minmax(0,1fr) auto;gap:12px;align-items:center; }
    .empty-compact { border:1px dashed #b6c8cf;border-radius:10px;padding:24px;text-align:center; }
    @media(max-width:850px) {
        .hub-hero-grid,.hub-intro,.hub-workspace,.booking-panel { grid-template-columns:1fr; }
        .hub-identity { align-items:flex-start;gap:15px; }
        .hub-studio-logo { width:82px;height:70px;padding:6px; }
        .hub-summary { grid-template-columns:repeat(3,1fr); }
        .hub-stat { min-width:0;padding:11px; }
        .hub-card-header { grid-template-columns:minmax(0,1fr) 34px; }
        .hub-progress { grid-column:1 / -1; }
        .check-item { grid-template-columns:32px minmax(0,1fr); }
        .check-due { grid-column:2;text-align:left; }
        .hub-production-item { grid-template-columns:30px minmax(0,1fr) auto;gap:8px; }
        .hub-progress-meta { max-width:120px; }
        .hub-login-code { flex-wrap:wrap;justify-content:center; }
        .hub-login-code code { flex:1 0 100%;text-align:center; }
        .hub-progress-meta .check-due { text-align:right; }
        .hub-tool-summary.action-summary { grid-template-columns:32px minmax(0,1fr) 28px; }
        .hub-tool-summary.action-summary::after { grid-column:3; }
        .hub-tool-summary.action-summary .check-due { grid-column:2;grid-row:2;text-align:left;white-space:normal; }
    }
</style>
@endpush

@section('content')
<section class="hero hub-hero"><div class="container hub-hero-grid">
    <div class="hub-identity">@if($studio->logoUrl())<img class="hub-studio-logo" src="{{ $studio->logoUrl() }}" alt="{{ $studio->name }} logo">@endif<div class="hub-identity-copy"><div class="eyebrow">Studio Hub</div><h1>{{ $studio->name }}</h1><p class="lead">Check every booking, complete what DancePro needs and keep your concert communication together.</p></div></div>
    <div class="hub-summary" aria-label="Studio hub summary">
        <div class="hub-stat"><strong>{{ $hub['events']->count() }}</strong><span>{{ Str::plural('event', $hub['events']->count()) }}</span></div>
        <div class="hub-stat"><strong>{{ $hub['actionCount'] }}</strong><span>actions remaining</span></div>
        <div class="hub-stat"><strong>{{ $hub['messageCount'] }}</strong><span>{{ Str::plural('message', $hub['messageCount']) }}</span></div>
    </div>
</div></section>

<nav class="hub-tabs" aria-label="Studio Hub sections"><div class="container">
    <a class="{{ $tab === 'events' ? 'active' : '' }}" href="{{ route('customer.studio-communications.index', [$studio, 'tab' => 'events']) }}">Events</a>
    <a class="{{ $tab === 'messages' ? 'active' : '' }}" href="{{ route('customer.studio-communications.index', [$studio, 'tab' => 'messages']) }}">Messages</a>
    <a href="{{ route('customer.studio-invoices.index', $studio) }}">Invoices</a>
</div></nav>

<section class="section" id="overview"><div class="container">
    <div style="display:flex;flex-wrap:wrap;gap:10px;justify-content:flex-end;margin-bottom:20px"><a class="button secondary" href="{{ route('customer.studio-bookings.index', $studio) }}">Event timeline</a><a class="button" href="{{ route('concert-bookings.create', ['studio' => $studio->uuid]) }}">Make booking</a></div>
    @if(session('status'))<div class="card card-body" role="status" style="margin-bottom:20px;border-left:4px solid #20a66a">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="error" role="alert">{{ $errors->first() }}</div>@endif

    @if($tab === 'events')
    <div id="events" class="section-head"><div><p class="eyebrow">Bookings</p><h2 style="margin-bottom:0">Events</h2></div><span class="meta">Open an event to review details, complete actions and follow progress</span></div>

    @forelse($hub['events'] as $index => $card)
        @php($event = $card['event'])
        @php($item = $card['bookingItem'])
        <details class="card hub-card" style="margin-bottom:16px">
            <summary class="hub-card-header">
                <div>
                    <div><span class="hub-badge {{ $item?->approval_status === 'approved' ? 'success' : 'warning' }}">{{ $item?->approval_status === 'approved' ? '✓ Booking confirmed' : 'Booking awaiting confirmation' }}</span> @if($card['changeRequests']->whereIn('status', ['requested','in_review'])->isNotEmpty())<span class="hub-badge danger">Change requested</span>@endif</div>
                    <h2>{{ $card['title'] }}</h2>
                    <div class="hub-event-meta"><span>📅 {{ $event->event_date?->format('D j M Y') ?: 'Date to be confirmed' }}</span><span>📍 {{ $item?->venue_name ?: $event->venue?->name ?: 'Venue to be confirmed' }}</span></div>
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
                    @if(!$card['isConcert'] && $card['booking']?->wants_portrait_photography)
                    @php($portraitTask = $card['checklist']->firstWhere('key','portrait_requirements'))
                    <details class="hub-tool full"><summary class="hub-tool-summary action-summary"><span class="check-icon">{{ $portraitTask['complete'] ? '✓' : '!' }}</span><div class="action-copy"><h3>Confirm portrait shoot requirements</h3><p>{{ $portraitTask['instruction'] }}</p></div><div class="check-due">{{ $portraitTask['complete'] ? 'Complete' : ($portraitTask['dueDate'] ? ($portraitTask['overdue'] ? 'Overdue · ' : 'Due ').$portraitTask['dueDate']->format('j M Y') : 'Required') }}</div></summary><div class="hub-tool-content">
                        <form method="post" action="{{ route('customer.studio-events.requirements.store', [$studio, $event]) }}">@csrf
                            <label class="choice"><input type="checkbox" name="requirements[]" value="portrait_space" @checked($card['portraitAcknowledgements']->contains('portrait_space'))><span><strong>Portrait area prepared</strong><br>Provide a minimum 8 × 10 metre space with accessible power, a desk and a chair for the dress rehearsal or photo day portrait shoot.</span></label>
                            <label class="choice" style="margin-top:14px"><input type="checkbox" name="requirements[]" value="portrait_dancers" @checked($card['portraitAcknowledgements']->contains('portrait_dancers'))><span><strong>Every dancer will attend</strong><br>Ensure all students are brought by their teacher or minder after their rehearsal to have their photo taken.</span></label>
                            <button class="button" style="margin-top:16px">Save requirements</button>
                        </form>
                    </div></details>
                    @endif

                    @if($card['seatedRoles']->isNotEmpty())
                    @php($seatTask = $card['checklist']->firstWhere('key','seating'))
                    <details class="hub-tool full"><summary class="hub-tool-summary action-summary"><span class="check-icon">{{ $seatTask['complete'] ? '✓' : '!' }}</span><div class="action-copy"><h3>Enter reserved seats</h3><p>{{ $seatTask['instruction'] }}</p></div><div class="check-due">{{ $seatTask['complete'] ? 'Complete' : ($seatTask['dueDate'] ? ($seatTask['overdue'] ? 'Overdue · ' : 'Due ').$seatTask['dueDate']->format('j M Y') : 'Not required') }}</div></summary><div class="hub-tool-content"><p>Enter seats in groups. Photography needs 1–2 seats per role; videography needs 5–6.</p>
                        <form method="post" action="{{ route('customer.studio-events.seats.store', [$studio, $event]) }}">@csrf
                            @foreach($card['seatedRoles'] as $requirement)
                                @php($role = $requirement->crewRole)
                                @php($minimum = str_contains($role->code, 'videographer') ? 5 : 1)
                                @php($limit = str_contains($role->code, 'videographer') ? 6 : 2)
                                @php($saved = collect($card['savedSeats']->get($role->id)?->seats ?? [])->groupBy('row')->map(fn($seats, $row) => 'Row '.$row.': '.$seats->pluck('seat')->join(', '))->join("\n"))
                                <fieldset style="border:0;padding:0;margin:0 0 18px"><legend><strong>{{ $role->name }}</strong> <span class="meta">({{ $minimum }}–{{ $limit }} seats)</span></legend>
                                    <label style="margin-top:10px">Rows and seat numbers<textarea name="roles[{{ $role->code }}][seating]" rows="2" maxlength="500" placeholder="Row A: 12, 13, 14">{{ old("roles.{$role->code}.seating", $saved) }}</textarea></label>
                                    <span class="meta">Use one line per row, for example: Row A: 12, 13, 14</span>
                                </fieldset>
                            @endforeach
                            <button class="button">Save seating details</button>
                        </form>
                    </div></details>
                    @endif

                    @if($card['isConcert'])
                    @php($programmeTask = $card['checklist']->firstWhere('key','programme'))
                    <details class="hub-tool"><summary class="hub-tool-summary action-summary"><span class="check-icon">{{ $programmeTask['complete'] ? '✓' : '!' }}</span><div class="action-copy"><h3>Upload programme</h3><p>{{ $programmeTask['instruction'] }}</p></div><div class="check-due">{{ $programmeTask['complete'] ? 'Complete' : ($programmeTask['dueDate'] ? ($programmeTask['overdue'] ? 'Overdue · ' : 'Due ').$programmeTask['dueDate']->format('j M Y') : 'Not required') }}</div></summary><div class="hub-tool-content"><p>Upload the final programme for this event. PDF is preferred; Word, image and other formats are accepted for DancePro to convert.</p>
                        <form class="studio-file-upload" method="post" action="{{ route('customer.studio-events.files.store', [$studio, $event]) }}" enctype="multipart/form-data">@csrf<input type="hidden" name="kind" value="programme"><label>Choose programme<input type="file" name="files[]" required></label><button class="button secondary">{{ $card['programme'] ? 'Replace programme' : 'Upload programme' }}</button><progress value="0" max="100" hidden style="width:100%;margin-top:8px"></progress><span class="meta upload-status"></span></form>
                    </div></details>
                    @php($videoTask = $card['checklist']->firstWhere('key','video'))
                    <details class="hub-tool"><summary class="hub-tool-summary action-summary"><span class="check-icon">{{ $videoTask['complete'] ? '✓' : '!' }}</span><div class="action-copy"><h3>Upload videos & slideshows</h3><p>{{ $videoTask['instruction'] }}</p></div><div class="check-due">{{ $videoTask['complete'] ? 'Complete' : ($videoTask['dueDate'] ? ($videoTask['overdue'] ? 'Overdue · ' : 'Due ').$videoTask['dueDate']->format('j M Y') : 'Not required') }}</div></summary><div class="hub-tool-content"><p>Choose up to 20 supplied videos, slideshows or presentation files at a time. You can return and add more later.</p>
                        <form class="studio-file-upload" method="post" action="{{ route('customer.studio-events.files.store', [$studio, $event]) }}" enctype="multipart/form-data">@csrf<input type="hidden" name="kind" value="studio_video"><label>Choose studio files<input type="file" name="files[]" accept="video/*,.ppt,.pptx,.key,.pdf" multiple required></label><button class="button secondary">Upload studio files</button><progress value="0" max="100" hidden style="width:100%;margin-top:8px"></progress><span class="meta upload-status"></span></form>
                    </div></details>
                    @endif

                    @if($card['files']->isNotEmpty())
                    <details class="hub-tool full"><summary class="hub-tool-summary"><h3>Submitted files</h3><span class="hub-badge success">{{ $card['files']->count() }} {{ Str::plural('file', $card['files']->count()) }}</span></summary><div class="hub-tool-content"><div class="file-list">@foreach($card['files'] as $file)<div class="file-row"><div><a href="{{ route('studio-event-files.download', $file) }}">{{ $file->kind === 'programme' ? '📄' : '🎬' }} {{ $file->original_name }}</a><div class="meta">{{ number_format($file->size_bytes / 1048576, 1) }} MB · uploaded {{ $file->uploaded_at->format('j M Y, g:i a') }}</div>@if($file->review_note)<div class="meta">DancePro note: {{ $file->review_note }}</div>@endif</div><span class="hub-badge {{ $file->status === 'approved' ? 'success' : (str_contains($file->status, 'attention') ? 'danger' : 'warning') }}">{{ str($file->status)->replace('_',' ')->title() }}</span></div>@endforeach</div></div></details>
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
        <div class="empty"><h2>No concert events yet</h2><p>Your confirmed bookings will appear here automatically.</p></div>
    @endforelse
    @endif

    @if($tab === 'messages')
    <div id="messages" class="section-head" style="margin-top:48px"><div><p class="eyebrow">Communication history</p><h2 style="margin-bottom:0">Messages</h2></div><span class="meta">Emails and account replies in one thread</span></div>
    <div class="message-list">
        @forelse($communications as $message)
            <details class="card message-card"><summary class="card-body"><span><strong>{{ $message['subject'] }}</strong><br><span class="meta">{{ $message['event'] ?: 'Studio communication' }} · {{ $message['direction'] === 'from_dancepro' ? 'From DancePro' : 'Sent to DancePro' }}</span></span><span class="meta">{{ $message['occurred_at']?->format('j M Y, g:i a') }}</span></summary><div class="card-body" style="border-top:1px solid var(--line)"><p class="meta">{{ ucfirst($message['status']) }} · {{ $message['contact'] }}</p><div>{!! app(\App\Features\Tracking\Services\EmailContentFormatter::class)->html($message['body']) !!}</div>@foreach($message['attachments'] as $attachment)<p><a href="{{ route('customer.studio-communications.attachments.show', [$studio, $attachment]) }}">📎 {{ $attachment->original_name }}</a> <span class="meta">({{ number_format($attachment->size_bytes / 1024) }} KB)</span></p>@endforeach @if($message['direction'] === 'from_dancepro')<button class="button secondary message-reply" type="button" data-event="{{ $message['event_uuid'] }}" data-subject="{{ $message['subject'] }}">Reply</button>@endif</div></details>
        @empty
            <div class="empty-compact"><h3>No messages yet</h3><p class="muted">DancePro emails and your account replies will appear here.</p></div>
        @endforelse
    </div>

    <section id="contact" class="card card-body" style="margin-top:40px"><p class="eyebrow">Contact DancePro</p><h2>Send a message</h2><p class="muted">Select the relevant event so your message is saved with the correct booking. You can also attach a supporting document.</p>
        <form method="post" action="{{ route('customer.studio-communications.store', $studio) }}" enctype="multipart/form-data">@csrf
            <div class="grid" style="grid-template-columns:1fr 1fr"><label>Event<select id="hub-message-event" name="event_uuid"><option value="">General studio message</option>@foreach($events as $event)<option value="{{ $event->uuid }}">{{ $event->concertBookingItem?->title ?: $event->name }} · {{ $event->event_date?->format('j M Y') }}</option>@endforeach</select></label><label>Subject<input id="hub-message-subject" name="subject" maxlength="255" value="{{ old('subject') }}" placeholder="What can we help with?"></label></div>
            <x-email-composer :value="old('body', '')" :rows="7" />
            <label>Attachment <span class="meta">optional, up to 10 MB</span><input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.csv,.txt"></label>
            <button class="button">Send to DancePro</button>
        </form>
    </section>
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
        if (request.status >= 200 && request.status < 300) { status.textContent = ' Upload complete'; window.location.reload(); return; }
        let message = 'Upload failed. Please try again.';
        try { const response = JSON.parse(request.responseText); message = response.message || Object.values(response.errors || {}).flat()[0] || message; } catch (_) {}
        status.textContent = ` ${message}`; button.disabled = false;
    });
    request.addEventListener('error', () => { status.textContent = ' Upload interrupted. Please try again.'; button.disabled = false; });
    request.send(new FormData(form));
}));
</script>
@endsection
