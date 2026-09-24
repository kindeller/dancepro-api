@extends('layouts.admin', [
    'title' => 'Concert Tracker',
    'heading' => 'Concert Tracker',
    'subheading' => 'Source-derived progress and the work that needs attention',
])

@push('styles')
<style>
    .tracker-controls { display:flex; align-items:center; justify-content:space-between; gap:14px; flex-wrap:wrap; margin-bottom:18px; }
    .tracker-tabs { display:flex; gap:0; border-bottom:1px solid var(--line); }
    .tracker-tabs a { padding:10px 16px; color:var(--muted); font-weight:800; text-decoration:none; border:1px solid transparent; border-bottom:0; border-radius:8px 8px 0 0; }
    .tracker-tabs a[aria-current="page"] { color:var(--brand-strong); background:#fff; border-color:var(--line); }
    .tracker-phase-tabs { display:flex; gap:8px; flex-wrap:wrap; margin:0 0 18px; }
    .tracker-phase-tabs a { padding:9px 13px; border:1px solid var(--line); border-radius:999px; background:#fff; color:var(--muted); font-weight:800; text-decoration:none; }
    .tracker-phase-tabs a[aria-current="page"] { color:#fff; }
    .tracker-phase-tabs .phase-all[aria-current="page"] { background:var(--brand-strong); border-color:var(--brand-strong); }
    .tracker-phase-tabs .phase-booking[aria-current="page"] { background:#168bb8; border-color:#168bb8; }
    .tracker-phase-tabs .phase-preparation[aria-current="page"] { background:#b97d00; border-color:#b97d00; }
    .tracker-phase-tabs .phase-editing[aria-current="page"] { background:#794da5; border-color:#794da5; }
    .tracker-phase-tabs .phase-completion[aria-current="page"] { background:#27805a; border-color:#27805a; }
    .tracker-summary { display:grid; grid-template-columns:repeat(4,minmax(130px,1fr)); gap:12px; margin-bottom:18px; }
    .tracker-summary .metric { padding:14px 16px; }
    .tracker-summary strong { display:block; margin-top:3px; font-size:25px; }
    .tracker-table-wrap { overflow:auto; }
    .tracker-table { min-width:940px; }
    .tracker-table th, .tracker-table td { padding:8px; }
    .tracker-event { min-width:205px; position:sticky; left:0; z-index:1; background:#fff; }
    .tracker-event small, .tracker-status small { display:block; color:var(--muted); margin-top:3px; line-height:1.35; }
    .tracker-status { display:block; min-width:118px; color:var(--ink); text-decoration:none; border-radius:7px; padding:5px; margin:-5px; font-size:12px; }
    .tracker-status:hover { background:var(--soft); text-decoration:none; }
    .tracker-status .badge { margin-bottom:2px; }
    .badge.status-waiting { background:#e8eef4; color:#536574; }
    .badge.status-ready { background:#dff3ff; color:#087eae; }
    .badge.status-in_progress { background:#dff3ff; color:#087eae; }
    .badge.status-not_required { background:#eef1f3; color:#65737d; }
    .badge.status-overdue { background:#fee2e2; color:#b42331; }
    .badge.status-failed { background:#fee2e2; color:#b42331; }
    .badge.status-near-due { background:#fff2cc; color:#946200; }
    .tracker-task-meta { display:flex; flex-wrap:wrap; gap:5px; margin-top:5px; color:var(--muted); font-size:11px; }
    .tracker-shared { background:#fbfdfe; }
    .compact-all th.tracker-task-heading { width:76px; min-width:76px; max-width:76px; height:128px; padding:5px; vertical-align:bottom; }
    .compact-all th.tracker-task-heading .vertical-label { display:inline-block; writing-mode:vertical-rl; transform:rotate(180deg); white-space:nowrap; line-height:1; }
    .tracker-task-heading { cursor:help; }
    .tracker-heading-tooltip { position:fixed; z-index:1000; width:min(340px,calc(100vw - 24px)); padding:12px 14px; border-radius:8px; background:#10202c; color:#fff; box-shadow:0 10px 28px rgba(16,32,44,.28); font-size:13px; font-weight:650; line-height:1.45; pointer-events:none; opacity:0; transform:translateY(4px); transition:opacity .12s ease,transform .12s ease; }
    .tracker-heading-tooltip[aria-hidden="false"] { opacity:1; transform:translateY(0); }
    .tracker-heading-tooltip strong { display:block; margin-bottom:4px; color:#86dcff; font-size:12px; letter-spacing:.03em; }
    .compact-all th.tracker-task-heading .muted { display:none; }
    .compact-all th.phase-booking { background:#e7f4fb; color:#096f98; border-bottom:4px solid #33a9d6; }
    .compact-all th.phase-preparation { background:#fff4d9; color:#855d00; border-bottom:4px solid #e8ad24; }
    .compact-all th.phase-editing { background:#f1eafb; color:#6a3c96; border-bottom:4px solid #9b6bcc; }
    .compact-all th.phase-completion { background:#e5f5ec; color:#176a42; border-bottom:4px solid #42a875; }
    .tracker-table:not(.compact-all) th.phase-booking { background:#e7f4fb; color:#096f98; border-bottom:4px solid #33a9d6; }
    .tracker-table:not(.compact-all) th.phase-preparation { background:#fff4d9; color:#855d00; border-bottom:4px solid #e8ad24; }
    .tracker-table:not(.compact-all) th.phase-editing { background:#f1eafb; color:#6a3c96; border-bottom:4px solid #9b6bcc; }
    .tracker-table:not(.compact-all) th.phase-completion { background:#e5f5ec; color:#176a42; border-bottom:4px solid #42a875; }
    .tracker-section-heading { border-top:4px solid var(--brand-strong); }
    .tracker-section-heading.phase-booking { border-top-color:#33a9d6; background:#f5fbfe; }
    .tracker-section-heading.phase-preparation { border-top-color:#e8ad24; background:#fffbf2; }
    .tracker-section-heading.phase-editing { border-top-color:#9b6bcc; background:#faf7fd; }
    .tracker-section-heading.phase-completion { border-top-color:#42a875; background:#f5fbf8; }
    .tracker-phase-key { display:none; gap:12px; flex-wrap:wrap; align-items:center; margin-top:10px; color:var(--muted); font-size:12px; font-weight:750; }
    .tracker-phase-key span { display:inline-flex; gap:6px; align-items:center; }
    .tracker-phase-key i { width:10px; height:10px; border-radius:3px; }
    .tracker-phase-key .booking { background:#33a9d6; }
    .tracker-phase-key .preparation { background:#e8ad24; }
    .tracker-phase-key .editing { background:#9b6bcc; }
    .tracker-phase-key .completion { background:#42a875; }
    .compact-all + * { scroll-margin-top:10px; }
    .compact-all td { width:88px; min-width:88px; max-width:88px; padding:6px; vertical-align:top; }
    .compact-all .tracker-event { width:180px; min-width:180px; max-width:180px; }
    .compact-all .tracker-status { min-width:0; font-size:11px; }
    .compact-all .tracker-status > small { display:none; }
    .compact-all .badge { min-height:20px; padding:2px 6px; font-size:10px; }
    .compact-all .tracker-task-meta { font-size:10px; line-height:1.2; }
    .tracker-studio-heading th { background:#eef7fb; color:var(--brand-strong); font-size:15px; text-align:left; }
    .release-workspace { margin-top:24px; }
    .release-grid { display:grid; grid-template-columns:minmax(280px,.8fr) minmax(420px,1.4fr); gap:18px; align-items:start; }
    .release-cover-grid { display:flex; flex-wrap:wrap; gap:9px; margin:12px 0; }
    .release-cover-option { position:relative; margin:0; }
    .release-cover-option button { display:block; padding:0; border:3px solid transparent; border-radius:9px; background:none; cursor:pointer; overflow:hidden; }
    .release-cover-option button[aria-pressed="true"] { border-color:#168bc1; }
    .release-cover-option img { display:block; width:112px; height:76px; object-fit:cover; }
    .release-parent-cover { display:block; width:min(100%,320px); aspect-ratio:16/9; object-fit:cover; border-radius:10px; margin:12px 0; background:#10202c; }
    @media (max-width:900px) { .release-grid { grid-template-columns:1fr; } .tracker-summary { grid-template-columns:1fr 1fr; } }
</style>
@endpush

@section('content')
@if(session('status'))<p class="card card-pad" role="status">{{ session('status') }}</p>@endif
@if($errors->any())<p class="card card-pad" role="alert" style="color:#a62232">{{ $errors->first() }}</p>@endif

<div class="tracker-controls">
    <nav class="tracker-tabs" aria-label="Tracker view">
        <a href="{{ route('admin.concert-tracker.index', ['view' => 'date', 'year' => $year, 'phase' => $phase]) }}" @if($view === 'date') aria-current="page" @endif>Date view</a>
        <a href="{{ route('admin.concert-tracker.index', ['view' => 'studio', 'year' => $year, 'phase' => $phase]) }}" @if($view === 'studio') aria-current="page" @endif>Studio view</a>
    </nav>
    <div class="toolbar">
        <a class="button secondary" href="{{ route('admin.concert-tracker.templates.index') }}">Email templates</a>
        <a class="button secondary" href="{{ route('admin.concert-tracker.automation.index', ['year' => $year]) }}">Communication schedule</a>
        <a class="button secondary" href="{{ route('admin.concert-tracker.automation.settings') }}">Automation settings</a>
        <a class="button secondary" href="{{ route('admin.concert-tracker.alerts.index') }}">Alerts @if($concertTrackerAlertCount ?? 0)<span class="badge status-overdue">{{ $concertTrackerAlertCount }}</span>@endif</a>
        <a class="button secondary" href="{{ route('admin.concert-tracker.index', ['view' => $view, 'year' => $year - 1, 'phase' => $phase]) }}" aria-label="Previous year">←</a>
        <strong>{{ $year }}</strong>
        <a class="button secondary" href="{{ route('admin.concert-tracker.index', ['view' => $view, 'year' => $year + 1, 'phase' => $phase]) }}" aria-label="Next year">→</a>
    </div>
</div>

<nav class="tracker-phase-tabs" aria-label="Tracker phase">
    <a class="phase-all" href="{{ route('admin.concert-tracker.index', ['view' => $view, 'year' => $year, 'phase' => 'all']) }}" @if($phase === 'all') aria-current="page" @endif>All tasks</a>
    @foreach($phases as $phaseKey => $phaseLabel)<a class="phase-{{ $phaseKey }}" href="{{ route('admin.concert-tracker.index', ['view' => $view, 'year' => $year, 'phase' => $phaseKey]) }}" @if($phase === $phaseKey) aria-current="page" @endif>{{ $phaseLabel }}</a>@endforeach
</nav>

<section class="tracker-summary" aria-label="Tracker summary">
    <a class="card metric" style="text-decoration:none;color:inherit" href="{{ route('admin.concert-tracker.index', ['view' => $view, 'year' => $year, 'phase' => $phase, 'attention' => $attention ? null : 1]) }}"><span class="muted">Needs attention</span><strong style="color:#b42331">{{ $summary['attention'] }}</strong><small>{{ $attention ? 'Show all events' : 'Show overdue, failed and follow-ups' }}</small></a>
    <div class="card metric"><span class="muted">Due in 7 days</span><strong style="color:#946200">{{ $summary['near_due'] }}</strong></div>
    <div class="card metric"><span class="muted">Open</span><strong>{{ $summary['open'] }}</strong></div>
    <div class="card metric"><span class="muted">Complete</span><strong style="color:var(--ok)">{{ $summary['complete'] }}</strong></div>
</section>

<section class="card">
    <div class="card-pad tracker-section-heading phase-{{ $phase }}"><h2 style="margin:0">{{ $attention ? 'Needs attention' : ($phase === 'all' ? 'All concert tasks' : ($phases[$phase] ?? 'Concert production')) }}</h2><p class="muted" style="margin-bottom:0">Automatic tasks read their source records. Manual tasks support status, assignee, due-date overrides, notes and follow-ups. Click any cell for details.</p>@if($phase === 'all')<div class="tracker-phase-key" style="display:flex" aria-label="Task category colours"><span><i class="booking"></i>Booking</span><span><i class="preparation"></i>Preparation</span><span><i class="editing"></i>Editing</span><span><i class="completion"></i>Completion</span></div>@endif</div>
    <div class="tracker-table-wrap"><table class="tracker-table {{ $phase === 'all' ? 'compact-all' : '' }}">
        <thead><tr><th>Event</th>@foreach($definitions as $definition)<th class="tracker-task-heading phase-{{ $definition['phase'] }}" tabindex="0" data-task-label="{{ $definition['label'] }}" data-task-description="{{ $definition['description'] }}" aria-describedby="tracker-heading-tooltip"><span class="vertical-label">{{ $definition['label'] }}</span>@if($definition['scope'] === 'booking')<div class="muted" style="font-size:10px">Shared booking task</div>@endif</th>@endforeach</tr></thead>
        <tbody>
        @php($lastStudio = null)
        @forelse($rows as $row)
            @php($event = $row['event'])
            @if($view === 'studio' && $lastStudio !== ($event->studio?->name ?? 'Studio not set'))
                @php($lastStudio = $event->studio?->name ?? 'Studio not set')
                <tr class="tracker-studio-heading"><th colspan="{{ $definitions->count() + 1 }}">{{ $lastStudio }}</th></tr>
            @endif
            <tr>
                <td class="tracker-event"><a href="{{ route('admin.scheduling-events.show', $event) }}"><strong>{{ $event->name }}</strong></a><small>@if($view === 'date'){{ $event->studio?->name ?? 'Studio not set' }} · @endif{{ $event->event_date?->format('D j M Y') ?? 'Date not set' }}@if($event->eventCode()) · {{ $event->eventCode() }}@endif</small><small><a href="{{ route('admin.concert-tracker.communications.show', $event) }}">Communications</a></small></td>
                @foreach($definitions as $definition)
                    @php($status = $row['tasks'][$definition['key']])
                    @unless($status['skip'])<td @if($status['rowspan'] > 1) rowspan="{{ $status['rowspan'] }}" class="tracker-shared" @endif><a class="tracker-status" href="{{ $status['detailUrl'] }}" title="{{ $status['detail'] }}"><span class="badge {{ $status['tone'] === 'complete' ? 'success' : 'status-'.$status['tone'] }}">{{ $status['label'] }}</span><small>{{ $status['detail'] }}</small><span class="tracker-task-meta">@if($status['activity'])<span>{{ $status['activity'] }}</span>@elseif($status['dueDate'])<span>Due {{ $status['dueDate']->format('j M') }}</span>@endif @if($phase !== 'all' && $status['assignee'])<span>· {{ $status['assignee']->name }}</span>@endif @if($status['note_count'])<span>· {{ $status['note_count'] }} note(s)</span>@endif</span></a></td>@endunless
                @endforeach
            </tr>
        @empty
            <tr><td colspan="{{ $definitions->count() + 1 }}" class="muted">No concert events are scheduled in {{ $year }}.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</section>

@if(in_array($phase, ['completion', 'all'], true))
<section class="release-workspace" id="prepare-release">
    <div class="release-grid">
        <details class="card card-pad" @if($albums->isNotEmpty()) open @endif><summary><strong>Prepare a photo release</strong></summary>
            <p class="muted">Choose customer albums from one studio. Related concerts can be released together as one parent album.</p>
            <form method="post" action="{{ route('admin.concert-photo-releases.store') }}" class="grid">@csrf
                <label>Parent album name<input name="name" maxlength="255" required placeholder="2026 Senior concerts"></label>
                <label>Watermark<select name="watermark_uuid"><option value="">Default concert watermark</option>@foreach($watermarks as $watermark)<option value="{{ $watermark->uuid }}">{{ $watermark->name }}</option>@endforeach</select></label>
                <fieldset><legend>Albums ready to group</legend>
                @forelse($albums as $album)
                    <label style="display:flex;gap:10px;align-items:start;margin:8px 0"><input type="checkbox" name="collection_uuids[]" value="{{ $album->uuid }}"><span><strong>{{ $album->concert->studio->name }} · {{ $album->concert->name }}</strong><br>{{ $album->name }}</span></label>
                @empty<p class="muted">No ungrouped concert photo albums are waiting.</p>@endforelse
                </fieldset><div><button @disabled($albums->isEmpty())>Create release group</button></div>
            </form>
        </details>

        <div id="release-groups"><h2 style="margin-top:0">Photo release</h2>
        @forelse($groups as $group)
            @php($topPics = $group->collections->flatMap->assets->filter(fn($asset) => ($asset->metadata['is_top_pic'] ?? false) && $asset->is_visible && $asset->status->value === 'available'))
            @php($cover = $group->displayCoverAsset())
            <article class="card card-pad" style="margin-bottom:14px"><div style="display:flex;justify-content:space-between;gap:12px;align-items:start"><div><h3 style="margin-top:0">{{ $group->name }}</h3><p>{{ $group->studio->name }} · {{ $group->collections->count() }} album(s) · {{ $group->watermark?->name ?? 'Default watermark' }}</p></div><span class="badge {{ $group->released_at ? 'success' : 'status-ready' }}">{{ $group->released_at ? 'Released' : 'Ready to release' }}</span></div>
            @if($cover && ($cover->metadata['programme_photo_file_uuid'] ?? null) && $cover->collection->concert->bookingItem?->schedulingEvent)<img class="release-parent-cover" src="{{ route('admin.concert-photo-gallery.image', ['schedulingEvent' => $cover->collection->concert->bookingItem->schedulingEvent, 'photo' => $cover->metadata['programme_photo_file_uuid'], 'size' => 'thumbnail']) }}" alt="{{ $group->name }} cover">@endif
            <ul>@foreach($group->collections as $album)<li>{{ $album->concert->name }} · {{ $album->name }}</li>@endforeach</ul>
            <details><summary><strong>Choose parent album cover</strong></summary>
            @if($topPics->isNotEmpty())<p class="muted">Parent covers come from photos marked Top Pics in the uploader.</p><div class="release-cover-grid">@foreach($topPics as $asset)@if(($asset->metadata['programme_photo_file_uuid'] ?? null) && $asset->collection->concert->bookingItem?->schedulingEvent)<form class="release-cover-option" method="post" action="{{ route('admin.concert-photo-releases.cover.update', $group) }}">@csrf<input type="hidden" name="asset_uuid" value="{{ $asset->uuid }}"><button aria-pressed="{{ $group->cover_media_asset_id === $asset->id ? 'true' : 'false' }}" title="Use as parent album cover"><img src="{{ route('admin.concert-photo-gallery.image', ['schedulingEvent' => $asset->collection->concert->bookingItem->schedulingEvent, 'photo' => $asset->metadata['programme_photo_file_uuid'], 'size' => 'thumbnail']) }}" alt="Top Pic cover option"></button></form>@endif @endforeach</div>
            <form method="post" action="{{ route('admin.concert-photo-releases.cover.update', $group) }}">@csrf<button class="secondary">Use first Top Pic automatically</button></form>
            @else<p class="muted">No uploaded photos in this group are marked Top Pics yet.</p>@endif
            </details>
            @if($group->released_at)<p><strong>Released {{ $group->released_at->format('j M Y g:i a') }}</strong></p>
            @else<form method="post" action="{{ route('admin.concert-photo-releases.release', $group) }}">@csrf<button>Release all albums</button></form>@endif
            </article>
        @empty<p class="card card-pad">No release groups yet.</p>@endforelse
        </div>
    </div>
</section>
@endif
<div id="tracker-heading-tooltip" class="tracker-heading-tooltip" role="tooltip" aria-hidden="true"></div>
@endsection

@push('scripts')
<script>
    (() => {
        const tooltip = document.getElementById('tracker-heading-tooltip');
        const headings = document.querySelectorAll('.tracker-task-heading[data-task-description]');
        if (!tooltip || !headings.length) return;

        const show = (heading, x, y) => {
            tooltip.replaceChildren();
            const title = document.createElement('strong');
            title.textContent = heading.dataset.taskLabel;
            tooltip.append(title, document.createTextNode(heading.dataset.taskDescription));
            tooltip.setAttribute('aria-hidden', 'false');

            const gap = 14;
            const width = tooltip.offsetWidth;
            const height = tooltip.offsetHeight;
            tooltip.style.left = `${Math.max(12, Math.min(x + gap, window.innerWidth - width - 12))}px`;
            tooltip.style.top = `${Math.max(12, Math.min(y + gap, window.innerHeight - height - 12))}px`;
        };
        const hide = () => tooltip.setAttribute('aria-hidden', 'true');

        headings.forEach((heading) => {
            heading.addEventListener('mouseenter', (event) => show(heading, event.clientX, event.clientY));
            heading.addEventListener('mousemove', (event) => show(heading, event.clientX, event.clientY));
            heading.addEventListener('mouseleave', hide);
            heading.addEventListener('focus', () => {
                const rect = heading.getBoundingClientRect();
                show(heading, rect.right, rect.top + (rect.height / 2));
            });
            heading.addEventListener('blur', hide);
        });
    })();
</script>
@endpush
