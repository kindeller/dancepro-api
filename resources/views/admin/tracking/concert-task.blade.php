@extends('layouts.admin', [
    'title' => $definition['label'],
    'heading' => $definition['label'],
    'subheading' => $event->name.' · '.($event->studio?->name ?? 'Studio not set'),
])

@push('styles')
<style>
    .task-layout { display:grid; grid-template-columns:minmax(0,1.05fr) minmax(330px,.95fr); gap:20px; align-items:start; }
    .task-heading { display:flex; align-items:flex-start; justify-content:space-between; gap:14px; }
    .task-meta { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:10px; margin:16px 0; }
    .task-meta div { padding:12px; border:1px solid var(--line); border-radius:7px; background:var(--soft); }
    .task-meta strong { display:block; margin-top:3px; }
    .email-preview { padding:18px; border:1px solid var(--line); border-radius:8px; background:#fff; }
    .email-preview-body { line-height:1.55; }
    .note { padding:12px 0; border-top:1px solid var(--line); }
    .note:first-child { border-top:0; }
    .badge.status-waiting, .badge.status-not_started { background:#e8eef4; color:#536574; }
    .badge.status-in_progress { background:#dff3ff; color:#087eae; }
    .badge.status-not_required { background:#eef1f3; color:#65737d; }
    .badge.status-overdue { background:#fee2e2; color:#b42331; }
    .badge.status-near-due { background:#fff2cc; color:#946200; }
    @media(max-width:900px) { .task-layout { grid-template-columns:1fr; } .task-meta { grid-template-columns:1fr; } }
</style>
@endpush

@section('content')
<div class="toolbar" style="margin-bottom:18px"><a class="button secondary" href="{{ route('admin.concert-tracker.index', ['year' => $event->event_date?->year, 'phase' => $definition['phase']]) }}">← Concert Tracker</a><a class="button secondary" href="{{ route('admin.concert-tracker.communications.show', $event) }}">Communications</a><a class="button secondary" href="{{ route('admin.concert-tracker.templates.index') }}">Email templates</a>@if($status['sourceUrl'])<a class="button secondary" href="{{ $status['sourceUrl'] }}">Open source</a>@endif</div>
@if(session('status'))<p class="card card-pad" role="status">{{ session('status') }}</p>@endif
@if($errors->any())<p class="card card-pad" role="alert" style="color:#a62232">{{ $errors->first() }}</p>@endif

<div class="task-layout">
    <div>
        <section class="card card-pad">
            <div class="task-heading"><div><span class="badge {{ $status['tone'] === 'complete' ? 'success' : 'status-'.$status['tone'] }}">{{ $status['label'] }}</span><p>{{ $status['detail'] }}</p></div><span class="muted">{{ ucfirst($definition['scope']) }} task · {{ $definition['kind'] === 'source' ? 'Automatic' : 'Manual' }}</span></div>
            <div class="task-meta">
                <div><span class="muted">Due</span><strong>{{ $status['dueDate']?->format('D j M Y') ?? 'No deadline' }}</strong></div>
                <div><span class="muted">Assigned to</span><strong>{{ $task->assignee?->name ?? 'Unassigned' }}</strong></div>
                <div><span class="muted">Follow up</span><strong>{{ $task->follow_up_date?->format('D j M Y') ?? 'None' }}</strong></div>
            </div>

            <form method="post" action="{{ route('admin.concert-tracker.tasks.update', [$event, $definition['key']]) }}" class="grid">@csrf @method('PATCH')
                @if($definition['kind'] === 'manual')
                    <label>Status<select name="status"><option value="not_started" @selected($task->status === 'not_started')>Not started</option><option value="in_progress" @selected($task->status === 'in_progress')>In progress</option><option value="waiting" @selected($task->status === 'waiting')>Waiting</option><option value="complete" @selected($task->status === 'complete')>Complete</option><option value="not_required" @selected($task->status === 'not_required')>Not required</option></select></label>
                @else<input type="hidden" name="status" value="{{ $task->status }}"><p class="muted">Status is updated automatically from the source record. Assignment, deadlines and notes can still be managed here.</p>@endif
                <label>Assignee<select name="assignee_user_id"><option value="">Unassigned</option>@foreach($staff as $person)<option value="{{ $person->id }}" @selected($task->assignee_user_id === $person->id)>{{ $person->name }}</option>@endforeach</select></label>
                <div class="grid two-col"><label>Override due date<input type="date" name="due_date_override" value="{{ $task->due_date_override?->format('Y-m-d') }}"></label><label>Follow-up date<input type="date" name="follow_up_date" value="{{ $task->follow_up_date?->format('Y-m-d') }}"></label></div>
                <label>Add note<textarea name="note" rows="4" placeholder="Add context, an exception or the next follow-up"></textarea></label>
                <div><button>Save task</button></div>
            </form>
        </section>

        <section class="card card-pad" style="margin-top:18px"><h2>Notes & history</h2>
            @forelse($task->notes as $note)<div class="note"><strong>{{ $note->user->name }}</strong> <span class="muted">· {{ $note->created_at->format('j M Y g:i a') }}</span><p>{{ $note->body }}</p>@if($note->follow_up_date)<span class="badge status-waiting">Follow up {{ $note->follow_up_date->format('j M Y') }}</span>@endif</div>@empty<p class="muted">No notes have been added.</p>@endforelse
        </section>
    </div>

    <div>
        @if($template)
        <section class="card card-pad"><h2>Email action</h2><p class="muted">Sending this email records the exact rendered message and completes the manual task after successful delivery.</p>
            <div class="email-preview"><div style="margin-bottom:16px;padding:14px;background:#101820;color:#fff"><img src="{{ $emailBranding['logoUrl'] }}" alt="{{ $emailBranding['businessName'] }}" style="display:block;width:240px;max-width:100%;height:auto;object-fit:contain"></div><strong>{{ $renderedTemplate['subject'] }}</strong><div class="email-preview-body">{!! app(\App\Features\Tracking\Services\EmailContentFormatter::class)->html($renderedTemplate['body']) !!}</div><div style="margin-top:18px"><p>{{ $emailBranding['signature']['signOff'] }}</p><strong>{{ $emailBranding['signature']['name'] }}</strong>@if($emailBranding['signature']['position'])<br><span class="muted">{{ $emailBranding['signature']['position'] }}</span>@endif</div></div>
            <form method="post" action="{{ route('admin.concert-tracker.tasks.email.send', [$event, $definition['key']]) }}" class="grid" style="margin-top:14px">@csrf
                <label>Recipient name<input name="recipient_name" value="{{ old('recipient_name', $recipient['name']) }}"></label>
                <label>Recipient email<input type="email" name="recipient_email" value="{{ old('recipient_email', $recipient['email']) }}" required></label>
                <label>Send later <span class="muted">(optional)</span><input type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at') }}"></label>
                <div><button>Queue email</button></div>
            </form>
        </section>

        <details class="card card-pad" style="margin-top:18px"><summary><strong>Edit email template</strong></summary><p class="muted">Event fields and conditional blocks are managed in the central Email templates page. The preview above shows the version generated for this booking.</p>
            <form method="post" action="{{ route('admin.concert-tracker.tasks.template.update', [$event, $definition['key']]) }}" class="grid">@csrf @method('PATCH')
                <label>Template name<input name="name" value="{{ $template->name }}" required></label>
                <label>Subject<input name="subject" value="{{ $template->subject }}" required></label>
                <x-email-composer :value="$template->body" :rows="12" />
                <label style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="is_active" value="1" @checked($template->is_active)> Active</label>
                <div><button>Save template</button></div>
            </form>
        </details>

        <section class="card card-pad" style="margin-top:18px"><h2>Delivery history</h2>@forelse($task->deliveries as $delivery)<div class="note"><strong>{{ $delivery->recipient_email }}</strong> <span class="badge {{ $delivery->status === 'sent' ? 'success' : ($delivery->status === 'failed' ? 'status-overdue' : 'status-waiting') }}">{{ $delivery->status }}</span><div class="muted">{{ $delivery->scheduled_at?->format('j M Y g:i a') ?? $delivery->queued_at?->format('j M Y g:i a') }}</div><div class="toolbar" style="margin-top:8px">@if($delivery->status === 'scheduled')<form method="post" action="{{ route('admin.concert-tracker.deliveries.cancel', $delivery) }}">@csrf<button class="secondary">Cancel</button></form>@endif @if(in_array($delivery->status, ['sent', 'failed', 'cancelled'], true))<form method="post" action="{{ route('admin.concert-tracker.deliveries.resend', $delivery) }}">@csrf<button class="secondary">Resend</button></form>@endif</div></div>@empty<p class="muted">No messages have been queued.</p>@endforelse</section>
        @else
        <section class="card card-pad"><h2>Task guidance</h2><p>This task does not send a standard email. Update its status and notes when the work changes, or use the source link for automatic tasks.</p></section>
        @endif
    </div>
</div>
@endsection
