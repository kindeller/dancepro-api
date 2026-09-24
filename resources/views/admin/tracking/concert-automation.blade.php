@extends('layouts.admin', [
    'title' => 'Communication schedule',
    'heading' => 'Communication schedule',
    'subheading' => 'Review automatic concert emails before they are scheduled',
])

@push('styles')
<style>
    .automation-table td, .automation-table th { padding:10px; vertical-align:top; }
    .automation-table .event { min-width:220px; }
    .automation-table .recipient { min-width:210px; }
</style>
@endpush

@section('content')
<div class="tracker-controls" style="display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:18px"><div class="toolbar"><a class="button secondary" href="{{ route('admin.concert-tracker.index', ['year' => $year]) }}">← Concert Tracker</a><a class="button secondary" href="{{ route('admin.concert-tracker.templates.index') }}">Email templates</a></div><div class="toolbar"><a class="button secondary" href="{{ route('admin.concert-tracker.automation.index', ['year' => $year - 1]) }}">←</a><strong>{{ $year }}</strong><a class="button secondary" href="{{ route('admin.concert-tracker.automation.index', ['year' => $year + 1]) }}">→</a></div></div>
@if(session('status'))<p class="card card-pad" role="status">{{ session('status') }}</p>@endif
@if($errors->any())<p class="card card-pad" role="alert" style="color:#a62232">{{ $errors->first() }}</p>@endif

<section class="card"><div class="card-pad"><h2 style="margin-top:0">Review queue</h2><p class="muted" style="margin-bottom:0">Nothing is sent from this page automatically. Approving a row creates a scheduled delivery. A past due message is held for five minutes so it can still be cancelled.</p></div><div style="overflow:auto"><table class="automation-table"><thead><tr><th>Planned</th><th>Email</th><th>Concert</th><th>Recipient</th><th>Status</th><th></th></tr></thead><tbody>
@forelse($candidates as $candidate)
    <tr><td><strong>{{ $candidate['planned_at']->format('D j M Y') }}</strong><br><span class="muted">{{ $candidate['planned_at']->format('g:i a') }}</span></td><td>{{ $candidate['label'] }}</td><td class="event"><strong>{{ $candidate['event']->name }}</strong><br><span class="muted">{{ $candidate['event']->studio?->name }}</span></td><td class="recipient">{{ $candidate['recipient_name'] ?: 'Contact not set' }}<br><span class="muted">{{ $candidate['recipient_email'] ?: 'Email missing' }}</span></td><td>@if($candidate['existing'])<span class="badge {{ $candidate['existing']->status === 'sent' ? 'success' : 'status-waiting' }}">{{ ucfirst($candidate['existing']->status) }}</span>@elseif(!$candidate['recipient_email'])<span class="badge status-overdue">Missing email</span>@elseif($candidate['planned_at']->isPast())<span class="badge status-near-due">Ready now</span>@else<span class="badge status-ready">Ready to schedule</span>@endif</td><td>@if(!$candidate['existing'] && $candidate['recipient_email'])<form method="post" action="{{ route('admin.concert-tracker.automation.schedule', [$candidate['event'], $candidate['task_key']]) }}">@csrf<button>{{ $candidate['planned_at']->isPast() ? 'Queue in 5 min' : 'Schedule' }}</button></form>@endif</td></tr>
@empty<tr><td colspan="6" class="muted">No concert communications are available for {{ $year }}.</td></tr>@endforelse
</tbody></table></div></section>
@endsection
