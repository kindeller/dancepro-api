@extends('layouts.admin', [
    'title' => $definition['numbered_label'],
    'heading' => $definition['numbered_label'],
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
    .release-workspace { margin-top:24px; }
    .release-grid { display:grid; grid-template-columns:minmax(280px,.8fr) minmax(420px,1.4fr); gap:18px; align-items:start; }
    .release-cover-grid { display:flex; flex-wrap:wrap; gap:9px; margin:12px 0; }
    .release-cover-option { position:relative; margin:0; }
    .release-cover-option button { display:block; padding:0; border:3px solid transparent; border-radius:9px; background:none; cursor:pointer; overflow:hidden; }
    .release-cover-option button[aria-pressed="true"] { border-color:#168bc1; }
    .release-cover-option img { display:block; width:112px; height:76px; object-fit:cover; }
    .release-parent-cover { display:block; width:min(100%,320px); aspect-ratio:16/9; object-fit:cover; border-radius:10px; margin:12px 0; background:#10202c; }
    @media(max-width:1100px) { .release-grid { grid-template-columns:1fr; } }
    @media(max-width:900px) { .task-layout { grid-template-columns:1fr; } .task-meta { grid-template-columns:1fr; } }
</style>
@endpush

@section('content')
<div class="toolbar" style="margin-bottom:18px"><a class="button secondary" href="{{ route('admin.concert-tracker.index', ['year' => $event->event_date?->year, 'phase' => $definition['phase']]) }}">← Concert tracker</a><a class="button secondary" href="{{ route('admin.concert-tracker.communications.show', $event) }}">Communications</a><a class="button secondary" href="{{ route('admin.concert-tracker.templates.index') }}">Email templates</a>@if($status['sourceUrl'])<a class="button secondary" href="{{ $status['sourceUrl'] }}">{{ $status['key'] === 'not_required' ? 'Review event & services' : ($status['sourceLabel'] ?? $definition['sourceLabel']) }}</a>@endif</div>
@if($definition['key'] === 'confirmation_information_email' && $event->concertBookingItem?->booking)<p><a class="button secondary" href="{{ route('admin.concert-bookings.show', $event->concertBookingItem->booking) }}">← Back to booking</a></p>@endif
@if(session('status'))<p class="card card-pad" role="status">{{ session('status') }}</p>@endif
@if($errors->any())<p class="card card-pad" role="alert" style="color:#a62232">{{ $errors->first() }}</p>@endif

<p class="card card-pad">{{ $definition['description'] }}</p>
@if($releaseWorkspace)
    @include('admin.tracking._photo-release-workspace', $releaseWorkspace)
@endif
<div class="task-layout">
    <div>
        <section class="card card-pad">
            <div class="task-heading"><div><span class="badge {{ $status['tone'] === 'complete' ? 'success' : 'status-'.$status['tone'] }}">{{ $status['label'] }}</span><p>{{ $status['detail'] }}</p></div><span class="muted">{{ ucfirst($definition['scope']) }} task · {{ $status['sourceDriven'] ? 'Automatic' : 'Manual' }}</span></div>
            <div class="task-meta">
                <div><span class="muted">Due</span><strong @if($status['overdue']) style="color:#b42331" @endif>{{ $status['dueDate']?->format('D j M Y') ?? 'No deadline' }}</strong></div>
                <div><span class="muted">Assigned to</span><strong>{{ $task->assignee?->name ?? 'Unassigned' }}</strong></div>
                <div><span class="muted">Follow up</span><strong>{{ $task->follow_up_date?->format('D j M Y') ?? 'None' }}</strong></div>
            </div>

            <form method="post" action="{{ route('admin.concert-tracker.tasks.update', [$event, $definition['key']]) }}" class="grid">@csrf @method('PATCH')
                @if(! $status['sourceDriven'])
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
        @include('admin.tracking._email-action')
    </div>
</div>
@endsection
