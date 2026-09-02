@extends('layouts.admin', ['title'=>'Crew Payments · Timesheets','heading'=>'Crew Payments','subheading'=>'Trust-based time monitoring. Only discrepancies require attention.'])
@section('content')
@include('admin.timesheets._tabs')
<div class="card"><table><thead><tr><th>Crew / event</th><th>Recorded time</th><th>Payment preview</th><th>Internal flags</th><th>Status</th></tr></thead><tbody>
@forelse($entries as $entry)
@php
    $preview = $previews[$entry->id];
@endphp
<tr><td><strong>{{ $entry->assignment->crewProfile->preferred_name }}</strong><div class="muted">{{ $entry->assignment->shift->schedulingEvent->name }} · {{ $entry->assignment->shift->shift_date->format('j M Y') }}</div></td><td>{{ $entry->actual_clock_in_at?->format('g:i a') }}–{{ $entry->actual_finish_at?->format('g:i a') }}<div class="muted">{{ $preview['hours'] !== null ? $preview['hours'].' hours' : $entry->assignment->role->name }}</div></td><td>{{ $preview['total'] !== null ? '$'.number_format($preview['total'],2) : 'Not ready' }}</td><td>@forelse($preview['flags'] as $flag)<div style="color:var(--warn)">{{ $flag }}</div>@empty<span class="muted">None</span>@endforelse</td><td><span class="badge {{ $entry->invoiceLine || $entry->approval_status === 'externally_invoiced' ? 'success' : 'attention' }}">
@if($entry->invoiceLine)
Invoiced
@elseif($entry->approval_status === 'externally_invoiced')
Externally invoiced
@else
Pending
@endif
</span></td></tr>
@empty
<tr><td colspan="5" class="muted">No completed time records yet.</td></tr>
@endforelse
</tbody></table></div>
@endsection
