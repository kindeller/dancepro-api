@extends('layouts.admin', ['title' => 'Exceptions', 'heading' => 'Exceptions', 'subheading' => 'Unusual or incomplete records that may need attention. Normal work continues without approval.'])

@section('content')
<nav class="filter-tabs exception-tabs" aria-label="Exception categories">
    @foreach($tabs as $tab => $label)
        <a class="{{ $activeTab === $tab ? 'active' : '' }}" href="{{ route('admin.exceptions.index', ['tab' => $tab]) }}">
            @if($counts[$tab])<span>{{ $counts[$tab] }}</span>@endif {{ $label }}
        </a>
    @endforeach
</nav>

<div class="exception-key"><span><i class="exception-dot action"></i> Action needed</span><span><i class="exception-dot check"></i> Check recommended</span></div>

<section class="exception-list">
    @forelse($exceptions as $exception)
        <article class="card exception-card {{ $exception['severity'] }}">
            <div class="exception-marker"><i class="exception-dot {{ $exception['severity'] }}"></i><span>{{ $exception['severity'] === 'action' ? 'Action needed' : 'Check recommended' }}</span></div>
            <div class="exception-copy">
                <h2>{{ $exception['title'] }}</h2>
                <p>{{ $exception['detail'] }}</p>
                <div class="exception-meta">
                    <strong>{{ $exception['event'] }}</strong>
                    @if($exception['person'])<span>{{ $exception['person'] }}</span>@endif
                    @if($exception['date'])<span>{{ $exception['date']->format('D j M Y') }}</span>@endif
                </div>
            </div>
            <a class="button secondary" href="{{ $exception['url'] }}">{{ $exception['action'] }}</a>
        </article>
    @empty
        <div class="card empty-state"><h2>Nothing needs attention</h2><p class="muted">There are no exceptions in this category.</p></div>
    @endforelse
</section>
@endsection

@push('styles')
<style>
    .exception-tabs { display:flex; gap:5px; margin-bottom:14px; padding:4px; overflow:auto; border:1px solid var(--line); border-radius:4px; background:#fff; }
    .exception-tabs a { display:flex; flex:1; align-items:center; justify-content:center; gap:5px; min-width:max-content; padding:9px 12px; border-radius:3px; color:var(--muted); font-weight:800; text-decoration:none; }
    .exception-tabs a.active { background:var(--ink); color:#fff; }
    .exception-tabs span { display:grid; min-width:19px; height:19px; padding:0 5px; place-items:center; border-radius:99px; background:#e8eef1; color:#40545e; font-size:10px; }
    .exception-tabs a.active span { background:#fff; color:var(--ink); }
    .exception-key { display:flex; gap:18px; margin:8px 0 18px; color:var(--muted); font-size:12px; }
    .exception-key span,.exception-marker { display:flex; align-items:center; gap:7px; }
    .exception-dot { display:inline-block; width:8px; height:8px; flex:0 0 8px; border-radius:50%; }
    .exception-dot.action { background:#dc3545; }
    .exception-dot.check { background:#e9a825; }
    .exception-list { display:grid; gap:10px; }
    .exception-card { display:grid; grid-template-columns:145px minmax(0,1fr) auto; align-items:center; gap:18px; margin:0; border-left:4px solid #e9a825; }
    .exception-card.action { border-left-color:#dc3545; }
    .exception-marker { color:var(--muted); font-size:11px; font-weight:800; text-transform:uppercase; }
    .exception-copy h2 { margin-bottom:3px; font-size:17px; }
    .exception-copy p { margin:0 0 7px; }
    .exception-meta { display:flex; flex-wrap:wrap; gap:6px 14px; color:var(--muted); font-size:12px; }
    @media(max-width:800px) { .exception-card { grid-template-columns:1fr; gap:10px; } .exception-card .button { width:max-content; } }
</style>
@endpush
