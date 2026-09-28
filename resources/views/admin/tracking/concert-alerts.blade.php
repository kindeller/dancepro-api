@extends('layouts.admin', [
    'title' => 'Tracker alerts',
    'heading' => 'Tracker alerts',
    'subheading' => 'Overdue tasks, follow-ups and communication problems',
])

@push('styles')
<style>
    .alert-list { display:grid; gap:10px; }
    .tracker-alert { display:grid; grid-template-columns:12px minmax(0,1fr) auto; gap:14px; align-items:start; }
    .tracker-alert .dot { width:10px; height:10px; margin-top:7px; border-radius:50%; background:#b42331; }
    .tracker-alert.read .dot { background:#b6c2ca; }
    @media(max-width:720px) { .tracker-alert { grid-template-columns:12px 1fr; } .tracker-alert form { grid-column:2; } }
</style>
@endpush

@section('content')
<div class="toolbar" style="margin-bottom:18px"><a class="button secondary" href="{{ route('admin.concert-tracker.index') }}">← Concert tracker</a>@if($alerts->whereNull('read_at')->isNotEmpty())<form method="post" action="{{ route('admin.concert-tracker.alerts.read-all') }}">@csrf<button class="secondary">Mark all read</button></form>@endif</div>
@if(session('status'))<p class="card card-pad" role="status">{{ session('status') }}</p>@endif
<div class="alert-list">@forelse($alerts as $alert)<article class="card card-pad tracker-alert {{ $alert->read_at ? 'read' : '' }}"><span class="dot" aria-hidden="true"></span><div><span class="badge">{{ str($alert->type)->replace('_', ' ')->title() }}</span><h2 style="margin:6px 0">{{ $alert->title }}</h2><p>{{ $alert->body }}</p><span class="muted">{{ $alert->event?->name }} · {{ $alert->created_at->format('j M Y g:i a') }}</span></div><form method="post" action="{{ route('admin.concert-tracker.alerts.read', $alert) }}">@csrf<button>{{ $alert->read_at ? 'Open' : 'Read & open' }}</button></form></article>@empty<p class="card card-pad">Nothing needs your attention.</p>@endforelse</div>
@endsection
