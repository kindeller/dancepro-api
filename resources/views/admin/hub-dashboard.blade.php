@extends('layouts.admin', [
    'title' => 'Hub Management Dashboard',
    'heading' => 'Dashboard',
    'subheading' => 'A quick read on upcoming events, crew coverage and work needing attention.',
])

@section('content')
    <div class="toolbar dashboard-actions">
        <div></div>
        <a class="button" href="{{ route('admin.scheduling-events.index', ['fullscreen' => 1]) }}">Event Availability</a>
    </div>
    <section class="grid stats" aria-label="Hub management statistics">
        <a class="card metric dashboard-metric" href="{{ route('admin.scheduling-events.index') }}">
            <span class="muted">Events in next 14 days</span>
            <strong>{{ number_format($totals['events']) }}</strong>
        </a>
        <a class="card metric dashboard-metric" href="{{ route('admin.scheduling-events.index') }}">
            <span class="muted">Published crew assignments</span>
            <strong>{{ number_format($totals['assignedCrew']) }}</strong>
        </a>
        <a class="card metric dashboard-metric" href="{{ route('admin.exceptions.index', ['tab' => 'shifts-events']) }}">
            <span class="muted">Open cover requests</span>
            <strong>{{ number_format($totals['coverRequests']) }}</strong>
        </a>
        <a class="card metric dashboard-metric" href="{{ route('admin.timesheets.invoices.index') }}">
            <span class="muted">Pending invoices</span>
            <strong>{{ number_format($totals['pendingInvoices']) }}</strong>
        </a>
    </section>

    <section class="grid two-col" style="margin-top: 18px;">
        <div class="card">
            <div class="card-pad dashboard-heading">
                <div>
                    <h2>Upcoming Events</h2>
                    <p class="muted">Today through the next 14 days.</p>
                </div>
                <a href="{{ route('admin.scheduling-events.index') }}">View all</a>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Crew</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($upcomingEvents as $event)
                        @php
                            $assignments = $event->shifts->flatMap->assignments;
                            $acknowledged = $assignments->where('acknowledgement_status', 'acknowledged')->count();
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('admin.scheduling-events.show', $event) }}"><strong>{{ $event->name }}</strong></a>
                                <div class="muted">{{ $event->venue?->name ?? 'Venue not set' }}</div>
                            </td>
                            <td>{{ $acknowledged }}/{{ $assignments->count() }} acknowledged</td>
                            <td><span class="badge {{ $event->roster_status === 'published' ? 'success' : 'attention' }}">{{ str($event->roster_status)->replace('_', ' ')->title() }}</span></td>
                            <td>{{ $event->event_date->format('D j M') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="muted">No events are scheduled in the next 14 days.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="card">
            <div class="card-pad dashboard-heading">
                <div>
                    <h2>Needs Attention</h2>
                    <p class="muted">{{ $exceptionCount }} {{ str('exception')->plural($exceptionCount) }} across Hub Management.</p>
                </div>
                <a href="{{ route('admin.exceptions.index') }}">View all</a>
            </div>
            <div class="dashboard-exceptions">
                @forelse($exceptions as $exception)
                    <a href="{{ $exception['url'] }}">
                        <i class="dashboard-dot {{ $exception['severity'] }}"></i>
                        <span><strong>{{ $exception['title'] }}</strong><small>{{ $exception['event'] }} · {{ $exception['detail'] }}</small></span>
                    </a>
                @empty
                    <div class="card-pad muted">Nothing needs attention.</div>
                @endforelse
            </div>
        </div>
    </section>
@endsection

@push('styles')
<style>
    .dashboard-metric { color:var(--ink); text-decoration:none; }
    .dashboard-actions { margin-bottom:14px; }
    .dashboard-metric:hover { border-color:var(--brand); text-decoration:none; }
    .dashboard-heading { display:flex; align-items:flex-start; justify-content:space-between; gap:14px; }
    .dashboard-heading p { margin:4px 0 0; font-size:12px; }
    .dashboard-exceptions { display:grid; }
    .dashboard-exceptions > a { display:grid; grid-template-columns:9px minmax(0,1fr); align-items:start; gap:10px; padding:13px 18px; border-top:1px solid var(--line); color:var(--ink); }
    .dashboard-exceptions > a:hover { background:var(--soft); text-decoration:none; }
    .dashboard-exceptions small { display:block; margin-top:2px; color:var(--muted); line-height:1.35; }
    .dashboard-dot { width:8px; height:8px; margin-top:6px; border-radius:50%; background:#e9a825; }
    .dashboard-dot.action { background:#dc3545; }
</style>
@endpush
