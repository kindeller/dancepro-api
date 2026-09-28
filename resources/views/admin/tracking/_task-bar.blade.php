@once
@push('styles')
<style>
.tracker-bar{position:sticky;top:0;z-index:35;background:#fff;border:1px solid var(--line);border-top:4px solid var(--brand);border-radius:10px;padding:14px;margin-bottom:22px;box-shadow:0 8px 24px #10202c12}
.tracker-task-navigation{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:14px}.tracker-task-guide{margin-top:12px}.tracker-task-guide summary{cursor:pointer;font-weight:700;color:var(--brand-strong)}.tracker-task-guide p,.tracker-task-guide li{font-size:14px;line-height:1.5}.tracker-task-guide ol{padding-left:22px}.tracker-bar-heading{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px}.tracker-bar-heading strong{font-size:18px}.tracker-bar-heading small{display:block;color:var(--muted);margin-top:3px}
.tracker-bar-boxes{display:grid;grid-template-columns:1.4fr 1fr 1fr 1fr;gap:10px}.tracker-bar .tracker-bar-box{display:flex;flex-direction:column;justify-content:center;gap:6px;min-height:96px;width:100%;padding:12px;color:var(--ink);background:var(--soft);border:1px solid var(--line);border-radius:8px;text-align:center;white-space:normal}.tracker-bar-box span{font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:var(--muted)}.tracker-bar-box strong{font-size:19px}.tracker-bar-box small{font-size:12px;font-weight:500}.tracker-bar .tracker-bar-status strong{font-size:24px}.tracker-bar .tracker-bar-status{background:#eaf6fc;border-color:#9bcfe4;color:#076b94}.tracker-bar .tracker-bar-status.complete{background:#e7f7ec;border-color:#96d7ad;color:#126236}.tracker-bar .tracker-bar-status.roster{background:var(--soft);color:var(--brand-strong)}.tracker-bar-status.near-due,.tracker-bar .tracker-bar-status.waiting,.tracker-bar .tracker-bar-status.in_progress{background:#fff5df;border-color:#e8c572;color:#795400}.tracker-bar .tracker-bar-status.overdue{background:#fee2e2;border-color:#e4a0a9;color:#b42331}.tracker-bar .tracker-bar-status.not_required{background:#eef1f3;color:#576573}.tracker-bar .tracker-bar-overdue{color:#b42331;border-color:#e4a0a9;background:#fff4f5}.tracker-bar-box:hover{filter:brightness(.97)}.tracker-bar-box:focus-visible{outline:3px solid #009fd4;outline-offset:2px}
.tracker-bar-dialog{border:1px solid var(--line);border-radius:12px;width:min(560px,calc(100vw - 32px));max-height:85vh;padding:22px;color:var(--ink);box-shadow:0 20px 60px #0004}.tracker-bar-dialog::backdrop{background:#07162099}.tracker-bar-dialog header{display:flex;align-items:center;justify-content:space-between;gap:12px}.tracker-bar-dialog h2{margin:0}.tracker-bar-dialog form{display:grid;gap:16px;margin-top:18px}.tracker-bar-dialog footer{display:flex;justify-content:flex-end;gap:10px}.tracker-bar-dialog label{display:grid;gap:6px}.tracker-bar-dialog textarea{min-height:100px}.tracker-bar-note{border-top:1px solid var(--line);padding:12px 0}.tracker-bar-note p{white-space:pre-wrap;overflow-wrap:anywhere;margin:8px 0}.tracker-bar-notes{max-height:240px;overflow:auto;margin-top:18px}.tracker-bar-dialog select,.tracker-bar-dialog input{width:100%}
@media(max-width:760px){.tracker-bar{padding:10px}.tracker-bar-heading{align-items:flex-start}.tracker-bar-heading strong{font-size:15px}.tracker-bar-boxes{grid-template-columns:1fr 1fr}.tracker-bar .tracker-bar-box{min-height:70px;padding:8px}.tracker-bar-box strong,.tracker-bar .tracker-bar-status strong{font-size:17px}.tracker-bar-heading .button{padding:8px;font-size:12px}}
</style>
@endpush
@endonce
<section class="tracker-bar" aria-label="Tracker task controls">
    <nav class="tracker-task-navigation" aria-label="Task navigation">
        <a class="button secondary" href="{{ $bar['returnUrl'] }}">{{ $bar['returnLabel'] ?? '← Back to tracker' }}</a>
        @if($bar['eventUrl'] ?? null)<a class="button secondary" href="{{ $bar['eventUrl'] }}">← Back to event</a>@endif
    </nav>
    <div class="tracker-bar-heading"><div><strong>{{ $bar['definition']['numbered_label'] }}</strong><small>{{ $bar['context'] }}</small></div></div>
    @if($bar['confirmationUrl'] ?? null)<p><a href="{{ $bar['confirmationUrl'] }}">Confirmation: {{ $bar['confirmationState'] ?? 'Not sent' }} · View template &amp; delivery history →</a></p>@endif
    <div class="tracker-bar-boxes">
        <button type="button" class="tracker-bar-box tracker-bar-status {{ $bar['statusTone'] ?? $bar['status']['key'] }}" @disabled(in_array('status', $bar['lockedPanels'] ?? [])) data-tracker-dialog="tracker-status"><span>Status</span><strong>{{ $bar['status']['label'] }}</strong><small>{{ $bar['statusCaption'] ?? ($bar['task']->status_override !== null ? 'Manual override' : 'Automatic from booking') }}</small></button>
        <button type="button" @class(['tracker-bar-box', 'tracker-bar-overdue' => $bar['overdue']]) @disabled(in_array('due', $bar['lockedPanels'] ?? [])) data-tracker-dialog="tracker-due"><span>Due date</span><strong>{{ $bar['dueDate']?->format('j M Y') ?? 'No deadline' }}</strong><small>{{ in_array('due', $bar['lockedPanels'] ?? []) ? 'Set by admin' : 'Click to change' }}</small></button>
        <button type="button" class="tracker-bar-box" @disabled(in_array('assignment', $bar['lockedPanels'] ?? [])) data-tracker-dialog="tracker-assignment"><span>{{ $bar['assignmentLabel'] ?? 'Assigned to' }}</span><strong>{{ $bar['task']->assignee?->name ?? 'Unassigned' }}</strong><small>{{ $bar['assignmentCaption'] ?? 'Staff or crew' }}</small></button>
        <button type="button" class="tracker-bar-box" @disabled(in_array('follow_up', $bar['lockedPanels'] ?? [])) data-tracker-dialog="tracker-follow-up"><span>Follow up</span><strong>{{ $bar['followUpStatuses'][$bar['task']->follow_up_status ?? 'none'] }}</strong><small>{{ $bar['task']->follow_up_date?->format('j M Y') ?? 'No follow-up date' }} · {{ $bar['task']->notes->count() }} notes</small></button>
    </div>
    <details class="tracker-task-guide">
        <summary>Task instructions</summary>
        <p>{{ $bar['definition']['description'] }}</p>
        <ol>@foreach($bar['definition']['instructions'] ?? [] as $instruction)<li>{{ $instruction }}</li>@endforeach</ol>
        @if($bar['status']['detail'] ?? null)<p><strong>Current progress:</strong> {{ $bar['status']['detail'] }}</p>@endif
    </details>
    <p data-tracker-message role="status" style="margin:8px 0 0" hidden></p>
</section>
@foreach(['status' => 'Status', 'due' => 'Due date', 'assignment' => 'Assigned to', 'follow_up' => 'Follow up'] as $panel => $panelLabel)
@continue(in_array($panel, $bar['lockedPanels'] ?? []))
<dialog class="tracker-bar-dialog" id="tracker-{{ str_replace('_', '-', $panel) }}" aria-labelledby="tracker-{{ $panel }}-title" @if($errors->any() && old('panel') === $panel) data-tracker-reopen @endif>
    <header><h2 id="tracker-{{ $panel }}-title">{{ $panelLabel }}</h2><button type="button" class="secondary" data-tracker-close aria-label="Close {{ strtolower($panelLabel) }}">×</button></header>
    @if($errors->any() && old('panel') === $panel)<p role="alert" style="color:#b42331">{{ $errors->first() }}</p>@endif
    <form method="POST" action="{{ $bar['updateUrl'] }}">@csrf @method('PATCH')<input type="hidden" name="panel" value="{{ $panel }}">
        @if($panel === 'status')
            @if($bar['automaticOnly'] ?? false)
                <p class="muted">{{ $bar['automaticHelp'] ?? 'This status updates from the fields below. Confirm the final family count and fill the required seats for every concert to complete it. DR portraits remain Not required.' }}</p>
            @elseif($bar['manualTask'] ?? false)
                <p>{{ $bar['manualHelp'] ?? 'Record files received outside the portal, or mark this task not required when no studio media is needed. Uploaded files must be approved individually.' }}</p>
                <label>Tracker status<select name="status_override">@foreach($bar['statuses'] as $value => $label)<option value="{{ $value }}" @selected($bar['task']->status === $value)>{{ $label }}</option>@endforeach</select></label>
            @elseif($bar['emailTask'] ?? false)
                <p class="muted">Sending this email completes the task automatically. Choose Approved if the email does not need to be sent. Manual approval does not send an email or change the booking approval.</p>
                <label>Tracker status<select name="status_override"><option value="approved" @selected($bar['task']->status_override === 'complete')>Approved — email not needed</option>@foreach($bar['statuses'] as $value => $label)<option value="{{ $value }}" @selected($bar['task']->status === $value)>{{ $label }}</option>@endforeach</select></label>
            @else
                <p class="muted">Booking status: <strong>{{ $bar['automaticLabel'] }}</strong>. Choose Approved to approve the booking and open the confirmation email template. Other choices are tracker overrides only; Automatic follows the booking status.</p>
                @if(isset($contactReview) && $contactReview['studio'])<input type="hidden" name="studio_uuid" value="{{ $contactReview['studio']->uuid }}">@endif
                <label>Tracker status<select name="status_override"><option value="approved">Approved — open confirmation email</option><option value="" @selected(!old('status_override', $bar['task']->status_override))>Automatic — {{ $bar['automaticLabel'] }}</option>@foreach($bar['statuses'] as $value => $label)<option value="{{ $value }}" @selected(old('status_override', $bar['task']->status_override) === $value)>{{ $label }}</option>@endforeach</select></label>
            @endif
        @elseif($panel === 'due')
            <label>Due date<input type="date" name="due_date_override" value="{{ old('due_date_override', $bar['dueDate']?->format('Y-m-d')) }}"></label><p class="muted">Clear the field to restore the standard task deadline, or no deadline where none is defined.</p>
        @elseif($panel === 'assignment')
            <label>{{ $bar['assignmentLabel'] ?? 'Responsible staff or crew member' }}<select name="assignee_user_id"><option value="">Unassigned</option>@foreach($bar['assignees'] as $person)<option value="{{ $person->id }}" @selected((string) old('assignee_user_id', $bar['task']->assignee_user_id) === (string) $person->id)>{{ $person->name }}</option>@endforeach</select></label><p class="muted">This assigns responsibility for the task. Event crew rosters are managed separately.</p>
        @else
            <label>Follow-up status<select name="follow_up_status">@foreach($bar['followUpStatuses'] as $value => $label)<option value="{{ $value }}" @selected(old('follow_up_status', $bar['task']->follow_up_status ?? 'none') === $value)>{{ $label }}</option>@endforeach</select></label>
            <label>Follow-up date<input type="date" name="follow_up_date" value="{{ old('follow_up_date', $bar['task']->follow_up_date?->format('Y-m-d')) }}"></label>
            <label>Add a note<textarea name="note" rows="3" maxlength="3000">{{ old('panel') === 'follow_up' ? old('note') : '' }}</textarea></label>
        @endif
        <footer><button type="button" class="secondary" data-tracker-close>Cancel</button>@unless($panel === 'status' && ($bar['automaticOnly'] ?? false))<button type="submit">Save {{ strtolower($panelLabel) }}</button>@endunless</footer>
    </form>
    @if($panel === 'follow_up')
        <div class="tracker-bar-notes"><h3>Previous notes</h3>@forelse($bar['task']->notes as $note)<article class="tracker-bar-note"><strong>{{ $note->user?->name ?? 'Former staff member' }}</strong> <small class="muted">{{ $note->created_at->format('j M Y g:i a') }}</small><p>{{ $note->body }}</p>@if($note->follow_up_date)<small>Follow up {{ $note->follow_up_date->format('j M Y') }}</small>@endif</article>@empty<p class="muted">No notes yet.</p>@endforelse</div>
    @endif
</dialog>
@endforeach
@once
@push('scripts')
<script>
(() => {
    document.addEventListener('click', event => {
        const open = event.target.closest('[data-tracker-dialog]');
        const close = event.target.closest('[data-tracker-close]');
        if (open) document.getElementById(open.dataset.trackerDialog).showModal();
        if (close) close.closest('dialog').close();
    });
    document.addEventListener('submit', async event => {
        const form = event.target;
        const dialog = form.closest('.tracker-bar-dialog');
        if (!dialog || !window.fetch) return;
        event.preventDefault();
        const submit = form.querySelector('[type="submit"]');
        if (!submit || submit.disabled) return;
        submit.disabled = true;
        let error = dialog.querySelector('[role="alert"]');
        if (!error) {
            error = document.createElement('p');
            error.setAttribute('role', 'alert');
            error.style.color = '#b42331';
            dialog.querySelector('header').after(error);
        }
        error.textContent = '';
        if (form.elements.status_override?.value === 'approved' && window.bookingHasUnsavedChanges?.()) {
            error.textContent = 'Save the booking changes before approving it.';
            submit.disabled = false;
            return;
        }
        try {
            const values = new FormData(form);
            if (values.get('status_override') === 'approved') {
                const studio = document.querySelector('[data-approve-booking] [name="studio_uuid"]');
                if (studio) values.set('studio_uuid', studio.value);
            }
            const response = await fetch(form.action, {method: 'POST', body: values, headers: {'Accept': 'application/json'}, credentials: 'same-origin'});
            const result = await response.json();
            if (!response.ok) {
                error.textContent = Object.values(result.errors || {}).flat().join(' ') || result.message || 'Unable to save. Please try again.';
                return;
            }
            if (result.data.redirect) { window.location.assign(result.data.redirect); return; }
            const template = document.createElement('template');
            template.innerHTML = result.data.html;
            if (!template.content.querySelector('.tracker-bar')) throw new Error('Invalid response');
            const id = dialog.id;
            dialog.close();
            document.querySelectorAll('.tracker-bar-dialog').forEach(item => item.remove());
            document.querySelector('.tracker-bar').replaceWith(template.content);
            const message = document.querySelector('[data-tracker-message]');
            message.textContent = result.message;
            message.hidden = false;
            document.querySelector(`[data-tracker-dialog="${id}"]`)?.focus({preventScroll:true});
        } catch (failure) {
            error.textContent = 'Unable to confirm the save. Your entries are still here. Check your connection and try again.';
        } finally {
            submit.disabled = false;
        }
    });
    document.querySelectorAll('[data-tracker-reopen]').forEach(dialog => dialog.showModal());
})();
</script>
@endpush
@endonce
