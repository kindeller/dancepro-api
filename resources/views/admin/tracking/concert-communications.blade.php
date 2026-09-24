@extends('layouts.admin', [
    'title' => 'Concert communications',
    'heading' => 'Concert communications',
    'subheading' => $event->name.' · '.($event->studio?->name ?? 'Studio not set'),
])

@push('styles')
<style>
    .communication-list { display:grid; gap:14px; }
    .communication { display:grid; grid-template-columns:minmax(160px,.65fr) minmax(260px,1.35fr) auto; gap:18px; align-items:start; }
    .communication-body { white-space:pre-line; line-height:1.5; }
    .communication-actions { display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end; }
    .badge.status-scheduled, .badge.status-queued { background:#dff3ff; color:#087eae; }
    .badge.status-failed { background:#fee2e2; color:#b42331; }
    .badge.status-cancelled { background:#eef1f3; color:#65737d; }
    .communication-summary { display:grid; grid-template-columns:repeat(4,minmax(100px,1fr)); gap:10px; margin-bottom:18px; }
    .communication-summary div { padding:12px; border:1px solid var(--line); border-radius:8px; background:var(--soft); }
    .communication-summary strong { display:block; font-size:24px; }
    .inbound { border-left:4px solid #42a875; }
    @media(max-width:850px) { .communication { grid-template-columns:1fr; } .communication-actions { justify-content:flex-start; } }
</style>
@endpush

@section('content')
<div class="toolbar" style="margin-bottom:18px">
    <a class="button secondary" href="{{ route('admin.concert-tracker.index', ['year' => $event->event_date?->year]) }}">← Concert Tracker</a>
    <a class="button secondary" href="{{ route('admin.concert-tracker.templates.index') }}">Email templates</a>
    @if($event->studio)<a class="button" href="{{ route('admin.concert-tracker.studio-communications.show', $event->studio) }}">Studio conversation</a>@endif
</div>
@if(session('status'))<p class="card card-pad" role="status">{{ session('status') }}</p>@endif
@if($errors->any())<p class="card card-pad" role="alert" style="color:#a62232">{{ $errors->first() }}</p>@endif

<section class="card card-pad">
    <div class="communication-summary"><div><span class="muted">Sent</span><strong>{{ $summary['sent'] }}</strong></div><div><span class="muted">Opened</span><strong>{{ $summary['opened'] }}</strong></div><div><span class="muted">Received</span><strong>{{ $summary['received'] }}</strong></div><div><span class="muted">Failed</span><strong>{{ $summary['failed'] }}</strong></div></div>
    <div style="display:flex;justify-content:space-between;gap:12px;align-items:start"><div><h2 style="margin-top:0">Message history</h2><p class="muted">Scheduled and sent messages for this concert. Each record preserves the exact subject and message.</p></div><span class="badge">{{ $deliveries->count() }} message(s)</span></div>
    <div class="communication-list">
    @forelse($deliveries as $delivery)
        <article class="communication card card-pad">
            <div><strong>{{ $delivery->template?->name ?? 'Tracker email' }}</strong><p style="margin-bottom:5px">{{ $delivery->recipient_name ?: 'Recipient' }}<br>{{ $delivery->recipient_email }}</p><span class="badge {{ $delivery->status === 'sent' ? 'success' : 'status-'.$delivery->status }}">{{ ucfirst($delivery->status) }}</span>@if($delivery->first_opened_at)<span class="badge status-queued">Opened {{ $delivery->open_count }}×</span>@endif<p class="muted" style="font-size:12px">@if($delivery->status === 'scheduled')Scheduled {{ $delivery->scheduled_at?->format('j M Y g:i a') }}@elseif($delivery->sent_at)Sent {{ $delivery->sent_at->format('j M Y g:i a') }}@elseif($delivery->cancelled_at)Cancelled {{ $delivery->cancelled_at->format('j M Y g:i a') }}@elseQueued {{ $delivery->queued_at?->format('j M Y g:i a') }}@endif @if($delivery->last_opened_at)<br>Last opened {{ $delivery->last_opened_at->format('j M Y g:i a') }}@endif</p></div>
            <details><summary><strong>{{ $delivery->subject }}</strong></summary><div class="communication-body">{!! app(\App\Features\Tracking\Services\EmailContentFormatter::class)->html($delivery->body) !!}</div>@if($delivery->failure_message)<p style="color:#b42331">{{ $delivery->failure_message }}</p>@endif</details>
            <div class="communication-actions">@if($delivery->status === 'scheduled')<form method="post" action="{{ route('admin.concert-tracker.deliveries.cancel', $delivery) }}">@csrf<button class="secondary">Cancel</button></form>@endif @if(in_array($delivery->status, ['sent', 'failed', 'cancelled'], true))<form method="post" action="{{ route('admin.concert-tracker.deliveries.resend', $delivery) }}">@csrf<button>Resend</button></form>@endif</div>
        </article>
    @empty<p class="muted">No Tracker messages have been created for this concert.</p>@endforelse
    </div>
</section>

<section class="card card-pad" style="margin-top:18px"><h2>Received</h2><p class="muted">Replies will appear here after the production mailbox is connected.</p><div class="communication-list">@forelse($inbound as $message)<article class="communication card card-pad inbound"><div><strong>{{ $message->sender_name ?: $message->sender_email }}</strong><p>{{ $message->sender_email }}<br><span class="muted">{{ $message->received_at->format('j M Y g:i a') }}</span></p></div><details><summary><strong>{{ $message->subject }}</strong></summary><div class="communication-body">{!! app(\App\Features\Tracking\Services\EmailContentFormatter::class)->html($message->body) !!}</div></details><div></div></article>@empty<p class="muted">No received email has been linked to this concert.</p>@endforelse</div></section>
<p class="muted" style="font-size:12px">Open tracking is approximate. Some mail apps block tracking images, while privacy services may load them automatically.</p>
@endsection
