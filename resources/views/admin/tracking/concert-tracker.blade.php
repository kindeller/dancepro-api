@extends('layouts.admin', [
    'title' => 'Concert tracker',
    'heading' => 'Concert tracker',
    'subheading' => 'Concert bookings, preparation and delivery',
])

@push('styles')
<style>
    .tracker-controls { display:flex; align-items:center; justify-content:flex-end; gap:14px; flex-wrap:wrap; margin-bottom:18px; }
    .tracker-tabs { display:flex; gap:0; border-bottom:1px solid var(--line); }
    .tracker-tabs a { padding:10px 16px; color:var(--muted); font-weight:800; text-decoration:none; border:1px solid transparent; border-bottom:0; border-radius:8px 8px 0 0; }
    .tracker-tabs a[aria-current="page"] { color:var(--brand-strong); background:#fff; border-color:var(--line); }
    .tracker-summary { display:grid; grid-template-columns:repeat(4,minmax(130px,1fr)); gap:12px; margin-bottom:18px; }
    .tracker-summary .metric { padding:14px 16px; }
    .tracker-summary strong { display:block; margin-top:3px; font-size:25px; }
    .tracker-table-wrap { overflow:auto; max-height:70vh; min-height:0; overscroll-behavior:contain; }
    .tracker-table { min-width:940px; border-collapse:separate; border-spacing:0; }
    .tracker-table thead th { position:sticky; top:0; z-index:3; background:#fff; }
    .tracker-table thead th:first-child { left:0; z-index:4; }
    .tracker-table th, .tracker-table td { padding:8px; }
    .tracker-event { min-width:205px; position:sticky; left:0; z-index:1; background:#fff; }
    .tracker-event-heading,.tracker-event-title { display:block;max-width:280px;margin-inline:auto;overflow-wrap:anywhere; }
    .tracker-event-title { margin-top:4px;color:var(--ink);font-size:13px; }
    .tracker-event-tools { display:flex;flex-wrap:wrap;justify-content:center;align-items:center;gap:8px;margin-top:7px;max-width:280px;margin-inline:auto; }
    .tracker-communications { display:inline-grid;place-items:center;width:30px;height:30px;border:1px solid var(--line);border-radius:6px;color:var(--brand-strong);background:#fff;flex-shrink:0; }
    .tracker-communications svg { width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linejoin:round; }
    .tracker-communications:hover,.tracker-communications:focus-visible { background:var(--soft);outline:2px solid var(--brand-strong);outline-offset:2px; }
    .tracker-gallery-login { display:inline-flex;flex-wrap:wrap;justify-content:center;gap:5px;align-items:center;min-width:0;max-width:100%;font-size:11px;color:var(--muted); }
    .tracker-gallery-login code { font-size:12px;font-weight:800;color:var(--ink);overflow-wrap:anywhere;user-select:all; }
    .tracker-access-code { display:block; margin-top:5px; color:var(--brand-strong); font-size:10px; overflow-wrap:anywhere; }
    .tracker-access-code code { font:inherit; font-weight:800; user-select:all; }
    .tracker-sheet[aria-busy="true"] { cursor:progress; }
    .tracker-event small, .tracker-status small { display:block; color:var(--muted); margin-top:3px; line-height:1.35; }
    .tracker-status { display:block; min-width:118px; color:var(--ink); text-decoration:none; border-radius:7px; padding:5px; margin:-5px; font-size:12px; }
    .tracker-status:hover { background:var(--soft); text-decoration:none; }
    .tracker-status .badge { margin-bottom:2px; }
    .badge.status-roster { background:var(--soft); color:var(--brand-strong); }
    .badge.status-waiting { background:#e8eef4; color:#536574; }
    .badge.status-ready { background:#dff3ff; color:#087eae; }
    .badge.status-in_progress { background:#dff3ff; color:#087eae; }
    .badge.status-not_required { background:#eef1f3; color:#65737d; }
    .badge.status-overdue { background:#fee2e2; color:#b42331; }
    .badge.status-failed { background:#fee2e2; color:#b42331; }
    .badge.status-near-due { background:#fff2cc; color:#946200; }
    .tracker-task-meta { display:flex; flex-wrap:wrap; gap:5px; margin-top:5px; justify-content:center; color:var(--muted); font-size:11px; }
    .tracker-shared { background:#fbfdfe; }
    .compact-all th.tracker-task-heading { width:96px; min-width:96px; max-width:96px; height:142px; padding:7px; vertical-align:bottom; }
    .compact-all th.tracker-task-heading .vertical-label { display:inline-block; writing-mode:vertical-rl; transform:rotate(180deg); white-space:normal; height:128px; max-height:128px; text-wrap:balance; line-height:1.5; font-size:11px; letter-spacing:.02em; }
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
    .tracker-phase-key { display:flex; gap:6px; flex-wrap:wrap; align-items:center; margin-top:12px; color:var(--muted); font-size:12px; font-weight:750; }
    .tracker-phase-key a { display:inline-flex; gap:6px; align-items:center; padding:5px 9px; border:1px solid var(--line); border-radius:6px; text-decoration:none; background:#fff; }
    .tracker-phase-key a[aria-current="page"] { border-color:var(--brand-strong); color:var(--ink); box-shadow:inset 0 -2px var(--brand-strong); }
    .tracker-phase-key a:hover,.tracker-phase-key a:focus-visible { outline:2px solid var(--brand-strong); outline-offset:1px; }
    .tracker-phase-key i { width:10px; height:10px; border-radius:3px; }
    .tracker-phase-key .booking { background:#33a9d6; }
    .tracker-phase-key .preparation { background:#e8ad24; }
    .tracker-phase-key .editing { background:#9b6bcc; }
    .tracker-phase-key .completion { background:#42a875; }
    .compact-all + * { scroll-margin-top:10px; }
    .compact-all td { width:96px; min-width:96px; max-width:96px; padding:6px; vertical-align:top; }
    .compact-all .tracker-event { width:180px; min-width:180px; max-width:180px; }
    .compact-all .tracker-status { min-width:0; font-size:11px; }
    .compact-all .tracker-status > small { display:none; }
    .compact-all .badge { min-height:20px; padding:2px 6px; font-size:10px; }
    .compact-all .tracker-task-meta { font-size:10px; line-height:1.2; }
    .tracker-event .tracker-pending-heading { color:#b42331; }
    .tracker-not-approved { background:#ffe5e8;color:#b42331; }
    .tracker-pending-note { width:360px; max-width:65vw; margin:0; padding:12px; }
    .tracker-studio-heading th { background:#eef7fb; color:var(--brand-strong); font-size:15px; text-align:center; }
    .content { text-align:center; }
    .topbar { text-align:left; }
    .content th, .content td, .content input, .content select, .content textarea { text-align:center; }
    .tracker-tabs, .tracker-phase-key { justify-content:flex-start; }
    .tracker-due-overdue { color:#b42331; font-weight:800; }
    .tracker-sheet-toolbar { display:flex; align-items:center; justify-content:space-between; gap:14px; flex-wrap:wrap; }
    .tracker-sheet-toolbar h2 { margin:0; font-size:18px; }
    #tracker-fullscreen { margin-left:auto; }
    .tracker-sheet:fullscreen, .tracker-sheet.is-expanded { display:flex; flex-direction:column; box-sizing:border-box; width:100%; height:100%; padding:12px; background:#f5fafc; overflow:hidden; }
    .tracker-sheet.is-expanded { position:fixed; inset:0; z-index:1100; width:100vw; height:100dvh; }
    .tracker-sheet:fullscreen .tracker-table-wrap, .tracker-sheet.is-expanded .tracker-table-wrap { flex:1; max-height:none; }
    .tracker-sheet:fullscreen .tracker-section-heading, .tracker-sheet.is-expanded .tracker-section-heading { flex-shrink:0; }
    @media (max-width:900px) { .tracker-summary { grid-template-columns:1fr 1fr; } }
</style>
@endpush

@section('content')
@if(session('status'))<p class="card card-pad" role="status">{{ session('status') }}</p>@endif
@if($errors->any())<p class="card card-pad" role="alert" style="color:#a62232">{{ $errors->first() }}</p>@endif

<div class="tracker-controls">
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

<section class="tracker-summary" aria-label="Tracker summary">
    <a class="card metric" style="text-decoration:none;color:inherit" href="{{ route('admin.concert-tracker.index', ['view' => $view, 'year' => $year, 'phase' => $phase, 'attention' => $attention ? null : 1]) }}"><span class="muted">Needs attention</span><strong style="color:#b42331">{{ $summary['attention'] }}</strong><small>{{ $attention ? 'Show all events' : 'Show pending bookings and tasks needing action' }}</small></a>
    <div class="card metric"><span class="muted">Due within 3 days</span><strong style="color:#946200">{{ $summary['near_due'] }}</strong></div>
    <div class="card metric"><span class="muted">Open</span><strong>{{ $summary['open'] }}</strong></div>
    <div class="card metric"><span class="muted">Complete</span><strong style="color:var(--ok)">{{ $summary['complete'] }}</strong></div>
</section>

<section class="card tracker-sheet" id="tracker-sheet" aria-label="Concert tracker tasks">
    <div class="card-pad tracker-section-heading phase-{{ $phase }}">
        <div class="tracker-sheet-toolbar">
            <nav class="tracker-tabs" aria-label="Tracker view">
                <a href="{{ route('admin.concert-tracker.index', ['view' => 'date', 'year' => $year, 'phase' => $phase, 'attention' => $attention ? 1 : null]) }}" @if($view === 'date') aria-current="page" @endif>Date view</a>
                <a href="{{ route('admin.concert-tracker.index', ['view' => 'studio', 'year' => $year, 'phase' => $phase, 'attention' => $attention ? 1 : null]) }}" @if($view === 'studio') aria-current="page" @endif>Studio view</a>
            </nav>
            <h2>{{ $attention ? 'Needs attention' : ($phase === 'all' ? 'All concert tasks' : ($phases[$phase] ?? 'Concert production')) }} · {{ $year }}</h2>
            <button type="button" class="secondary" id="tracker-fullscreen" aria-controls="tracker-sheet" aria-pressed="false">⛶ Full screen</button>
        </div>
        <nav class="tracker-phase-key" aria-label="Tracker phase">
            <a class="phase-all" href="{{ route('admin.concert-tracker.index', ['view' => $view, 'year' => $year, 'phase' => 'all', 'attention' => $attention ? 1 : null]) }}" @if($phase === 'all') aria-current="page" @endif>All tasks</a>
            @foreach($phases as $phaseKey => $phaseLabel)
                <a href="{{ route('admin.concert-tracker.index', ['view' => $view, 'year' => $year, 'phase' => $phaseKey, 'attention' => $attention ? 1 : null]) }}" @if($phase === $phaseKey) aria-current="page" @endif><i class="{{ $phaseKey }}" aria-hidden="true"></i>{{ $phaseLabel }}</a>
            @endforeach
        </nav>
    </div>
    <div class="tracker-table-wrap" tabindex="0" role="region" aria-label="Scrollable concert tasks"><table class="tracker-table {{ $phase === 'all' ? 'compact-all' : '' }}">
        <thead><tr><th>Event</th>@foreach($definitions as $definition)<th class="tracker-task-heading phase-{{ $definition['phase'] }}" tabindex="0" data-task-label="{{ $definition['numbered_label'] }}" data-task-description="{{ $definition['description'] }}" aria-describedby="tracker-heading-tooltip"><span class="vertical-label">{{ $definition['numbered_label'] }}</span>@if($definition['scope'] === 'booking')<div class="muted" style="font-size:10px">Shared booking task</div>@endif</th>@endforeach</tr></thead>
        <tbody>
        @php($lastStudio = null)
        @forelse($rows as $row)
            @if($row['pending'])
                @php($item = $row['bookingItem'])
                <tr class="tracker-pending-row">
                    <td class="tracker-event">
                        @include('admin.tracking._event-column', ['row' => $row])
                    </td>
                    @if($definitions->contains('key', 'booking_received'))
                        <td><a class="tracker-status" href="{{ route('admin.concert-bookings.show', $item->booking) }}"><span class="badge tracker-not-approved">Not approved</span><small>{{ $row['bookingTask']?->status_override !== null ? 'Manual tracker: '.$row['bookingStatus']['label'] : 'Review booking & services' }}</small>@if($row['bookingTask']?->due_date_override)<span @class(['tracker-task-meta', 'tracker-due-overdue' => $row['bookingTask']->due_date_override->isBefore(today()) && !in_array($row['bookingStatus']['key'], ['complete', 'not_required'], true)])>Due {{ $row['bookingTask']->due_date_override->format('j M') }}</span>@endif</a></td>
                    @endif
                    <td colspan="{{ $definitions->count() - ($definitions->contains('key', 'booking_received') ? 1 : 0) }}">
                        <p class="muted tracker-pending-note">Awaiting confirmation. Event tasks become available when this event is approved.</p>
                    </td>
                </tr>
                @continue
            @endif
            @php($event = $row['event'])
            @if($view === 'studio' && $lastStudio !== ($event->studio?->name ?? 'Studio not set'))
                @php($lastStudio = $event->studio?->name ?? 'Studio not set')
                <tr class="tracker-studio-heading"><th colspan="{{ $definitions->count() + 1 }}">{{ $lastStudio }}</th></tr>
            @endif
            <tr>
                <td class="tracker-event">@include('admin.tracking._event-column', ['row' => $row])</td>
                @foreach($definitions as $definition)
                    @php($status = $row['tasks'][$definition['key']])
                    @unless($status['skip'])
                        <td data-task-key="{{ $definition['key'] }}" @if($status['rowspan'] > 1) rowspan="{{ $status['rowspan'] }}" class="tracker-shared" @endif>
                            <a class="tracker-status" href="{{ $status['detailUrl'] }}" title="{{ $status['detail'] }}">
                                @if($status['photoRoles'] ?? [])
                                    @foreach($status['photoRoles'] as $photoRole)<span class="badge {{ $photoRole['tone'] === 'complete' ? 'success' : 'status-'.$photoRole['tone'] }}">{{ $photoRole['label'] }}</span>@unless($loop->last)<br>@endunless
                                    @endforeach
                                @else
                                <span class="badge {{ ($status['familiesConfirmed'] ?? false) || $status['tone'] === 'complete' ? 'success' : 'status-'.$status['tone'] }}">{{ $status['label'] }}</span>
                                @endif
                                @foreach($status['crewSummary'] ?? [] as $crewLine)<br><span class="badge success">{{ $crewLine }}</span>@endforeach
                                @foreach($status['seatSummary'] ?? [] as $seatLine)
                                    <br><span class="badge success">{{ $seatLine }}</span>
                                @endforeach
                                @if($status['seatsLabel'] ?? null)
                                    <br><span class="badge status-{{ $status['tone'] }}">{{ $status['seatsLabel'] }}</span>
                                @endif
                                @if($status['key'] !== 'not_required' && filled($releaseLogins[$event->id][$definition['key']]['value'] ?? null))
                                    <span class="tracker-access-code">Access Code: <code>{{ $releaseLogins[$event->id][$definition['key']]['value'] }}</code></span>
                                @endif
                                <small>{{ $status['detail'] }}</small>
                                <span class="tracker-task-meta">
                                    @if($status['activity'])<span>{{ $status['activity'] }}</span>@endif
                                    @if($status['dueDate'] && (!$status['activity'] || $definition['key'] === 'final_numbers_received'))
                                        <span @class(['tracker-due-overdue' => $status['overdue']])>Due {{ $status['dueDate']->format('j M') }}</span>
                                    @endif
                                    @if($phase !== 'all' && $status['assignee'])<span>· {{ $status['assignee']->name }}</span>@endif
                                    @if($status['note_count'])<span>· {{ $status['note_count'] }} note(s)</span>@endif
                                </span>
                            </a>
                        </td>
                    @endunless
                @endforeach
            </tr>
        @empty
            <tr><td colspan="{{ $definitions->count() + 1 }}" class="muted">No concert events are scheduled in {{ $year }}.</td></tr>
        @endforelse
        </tbody>
    </table></div>
<div id="tracker-heading-tooltip" class="tracker-heading-tooltip" role="tooltip" aria-hidden="true"></div>
</section>

@endsection

@push('scripts')
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
    (() => {
        const shell = document.getElementById('tracker-sheet');
        let button = document.getElementById('tracker-fullscreen');
        let previousOverflow = '';
        const expanded = () => document.fullscreenElement === shell || shell.classList.contains('is-expanded');
        const updateButton = () => {
            button.textContent = expanded() ? '× Exit full screen' : '⛶ Full screen';
            button.setAttribute('aria-pressed', String(expanded()));
        };
        const exitFallback = () => {
            shell.classList.remove('is-expanded');
            document.body.style.overflow = previousOverflow;
            updateButton();
            button.focus();
        };
        shell.addEventListener('click', async (event) => {
            if (!event.target.closest('#tracker-fullscreen')) return;
            if (document.fullscreenElement === shell) await document.exitFullscreen();
            else if (shell.classList.contains('is-expanded')) exitFallback();
            else {
                try {
                    if (!shell.requestFullscreen) throw new Error('Fullscreen unavailable');
                    await shell.requestFullscreen();
                } catch {
                    previousOverflow = document.body.style.overflow;
                    document.body.style.overflow = 'hidden';
                    shell.classList.add('is-expanded');
                }
            }
            updateButton();
        });
        document.addEventListener('fullscreenchange', updateButton);
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && shell.classList.contains('is-expanded')) exitFallback();
        });
        let tooltip = document.getElementById('tracker-heading-tooltip');
        let navigation;
        let renderedUrl = window.location.href;
        const navigate = async (url, push = true) => {
            navigation?.abort();
            const controller = new AbortController();
            navigation = controller;
            shell.setAttribute('aria-busy', 'true');
            shell.querySelector('[data-navigation-error]')?.remove();
            try {
                const response = await fetch(url, {signal: controller.signal, credentials: 'same-origin', headers: {'Accept': 'text/html'}});
                if (!response.ok || new URL(response.url).pathname !== window.location.pathname) throw new Error('Tracker unavailable');
                const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
                const next = doc.getElementById('tracker-sheet');
                if (!next || controller.signal.aborted) throw new Error('Tracker unavailable');
                // Keep the fullscreen element itself mounted so browser fullscreen survives navigation.
                shell.replaceChildren(...next.childNodes);
                const summary = doc.querySelector('.tracker-summary');
                if (summary) document.querySelector('.tracker-summary').replaceWith(summary);
                button = shell.querySelector('#tracker-fullscreen');
                tooltip = shell.querySelector('#tracker-heading-tooltip');
                updateButton();
                if (push) {
                    history.replaceState({...history.state, concertTracker: true}, '', renderedUrl);
                    history.pushState({concertTracker: true}, '', url);
                }
                renderedUrl = window.location.href;
                const selected = [...shell.querySelectorAll('nav a')].find(link => link.href === new URL(url, location.href).href);
                selected?.focus({preventScroll: true});
            } catch (error) {
                if (controller.signal.aborted) return;
                if (!push) history.replaceState({concertTracker: true}, '', renderedUrl);
                const notice = document.createElement('p');
                notice.dataset.navigationError = '';
                notice.setAttribute('role', 'alert');
                notice.className = 'card-pad';
                notice.textContent = 'Could not change the tracker view. Your current view is still open. Please try again.';
                shell.prepend(notice);
            } finally {
                if (navigation === controller) shell.removeAttribute('aria-busy');
            }
        };
        shell.addEventListener('click', event => {
            const link = event.target.closest('.tracker-phase-key a, .tracker-tabs a');
            if (!link || !expanded() || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
            event.preventDefault();
            navigate(link.href);
        });
        window.addEventListener('popstate', event => {
            if (event.state?.concertTracker) navigate(window.location.href, false);
        });

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

        shell.addEventListener('mousemove', event => {
            const heading = event.target.closest('.tracker-task-heading[data-task-description]');
            if (heading) show(heading, event.clientX, event.clientY);
            else hide();
        });
        shell.addEventListener('mouseleave', hide);
        shell.addEventListener('focusin', event => {
            const heading = event.target.closest('.tracker-task-heading[data-task-description]');
            if (heading) {
                const rect = heading.getBoundingClientRect();
                show(heading, rect.right, rect.top + (rect.height / 2));
            }
        });
        shell.addEventListener('focusout', hide);
    })();
</script>
@endpush
