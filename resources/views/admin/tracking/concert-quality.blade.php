@extends('layouts.admin', ['title' => $definition['numbered_label'], 'heading' => $event->studio?->name ?? $event->name, 'subheading' => $event->name])
@section('content')
@include('admin.tracking._task-bar', ['bar' => $trackerBar])
@if(session('status'))<p role="status">{{ session('status') }}</p>@endif
@if($errors->any())<p role="alert" style="color:var(--danger)">{{ $errors->first() }}</p>@endif
<section class="card card-pad" style="width:100%;min-width:0">
<h2>Consistency comes first</h2>
<p>Each studio is different, so concerts may be filmed, edited and organised differently. Every video within the same concert should follow the same template, guide and rules.</p>
@if($status['key'] === 'not_required')
<p>{{ $status['detail'] }}</p>
@else
<p><strong id="quality-count">{{ $quality['checked'] }} of {{ $quality['total'] }} checks confirmed.</strong> Each tick or untick saves automatically.</p>
<p id="quality-completion" style="color:var(--ok)">@if($quality['complete'])Completed by {{ $task->completedBy?->name ?? 'Former staff member' }} · {{ $task->completed_at?->format('j M Y, g:i a') }}@endif</p>
<form id="quality-form" method="POST" action="{{ route('admin.concert-tracker.quality.update', $event) }}">
@csrf @method('PATCH')
<input type="hidden" name="fingerprint" value="{{ $quality['fingerprint'] }}">
@if(auth()->user()->canAccessAdmin())
<label class="choice" style="padding:16px;background:#eaf8fd;border:1px solid var(--line);border-radius:8px">
<input type="checkbox" id="quality-complete-all" @checked($quality['complete']) @disabled($quality['complete'])>
<span><strong>Complete all checks</strong><small style="display:block">Admin only · Saves automatically. Untick individual checks to reopen them.</small></span>
</label>
@else
<p class="muted">Staff confirm each check individually. Complete all is available to admin accounts.</p>
@endif
@foreach($quality['groups'] as $group => $checks)
<fieldset style="border:1px solid var(--line);border-radius:8px;padding:18px;margin:18px 0">
<legend style="font-size:20px;font-weight:700">{{ $group }}</legend>
@foreach($checks as $key => $label)
<label class="choice" style="margin:14px 0;align-items:flex-start;font-size:15px;color:var(--ink);font-weight:400">
<input type="checkbox" name="checks[]" value="{{ $key }}" @checked(isset($quality['checks'][$key]))>
<span>{{ $label }}
@if(isset($quality['checks'][$key]))<small data-quality-audit style="display:block;color:var(--muted)">{{ $quality['reviewers'][$quality['checks'][$key]['user_id']] ?? 'Former staff member' }} · {{ \Carbon\Carbon::parse($quality['checks'][$key]['checked_at'])->format('j M Y, g:i a') }}</small>@endif
</span></label>
@endforeach
</fieldset>
@endforeach
<div style="position:sticky;bottom:12px;background:white;border:1px solid var(--line);border-radius:8px;padding:14px;display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap">
<span id="quality-save-status" role="status" aria-live="polite">Saved</span>
<button type="button" id="quality-retry" hidden>Retry saving</button>
<noscript><button type="submit">Save quality checklist</button></noscript>
</div>
</form>
@endif
</section>
@endsection
@push('scripts')
<script>
(() => {
    const form = document.getElementById('quality-form');
    if (!form) return;
    const indicator = document.getElementById('quality-save-status');
    const retry = document.getElementById('quality-retry');
    const all = document.getElementById('quality-complete-all');
    const boxes = [...form.querySelectorAll('input[name="checks[]"]')];
    const queue = [];
    let saving = false, failed = false;
    function enqueue(completeAll = false) {
        if (all) all.checked = boxes.every(box => box.checked);
        queue.push({checks: boxes.filter(box => box.checked).map(box => box.value), complete_all: completeAll});
        if (all) all.disabled = true;
        if (!failed) saveNext();
    }
    async function saveNext() {
        if (saving || !queue.length) return;
        saving = true; failed = false; retry.hidden = true;
        indicator.textContent = 'Saving…';
        try {
            const response = await fetch(form.action, {method: 'PATCH', credentials: 'same-origin',
                headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': form.elements._token.value},
                body: JSON.stringify({...queue[0], fingerprint: form.elements.fingerprint.value})});
            const result = await response.json();
            if (!response.ok) throw new Error(Object.values(result.errors || {}).flat()[0] || result.message || 'Could not save.');
            const data = result.data;
            form.elements.fingerprint.value = data.fingerprint;
            document.getElementById('quality-completion').textContent = data.completion || '';
            document.getElementById('quality-count').textContent = `${data.checked} of 20 checks confirmed.`;
            const template = document.createElement('template'); template.innerHTML = data.html;
            if (template.content.querySelector('.tracker-bar')) {
                document.querySelectorAll('.tracker-bar-dialog').forEach(dialog => dialog.remove());
                document.querySelector('.tracker-bar').replaceWith(template.content);
            }
            for (const box of boxes) {
                const label = box.closest('label');
                label.querySelector('[data-quality-audit]')?.remove();
                if (data.checks[box.value]) {
                    const audit = document.createElement('small'); audit.dataset.qualityAudit = '';
                    audit.style.cssText = 'display:block;color:var(--muted)';
                    const check = data.checks[box.value];
                    audit.textContent = `${data.reviewers[check.user_id] || 'Former staff member'} · ${new Date(check.checked_at).toLocaleString()}`;
                    label.querySelector('span').append(audit);
                }
            }
            queue.shift();
            indicator.textContent = queue.length ? 'Saving…' : (data.complete ? 'Saved · Quality check complete' : 'Saved');
        } catch (error) {
            failed = true;
            indicator.textContent = `Couldn’t save: ${error.message} Your changes are not saved.`;
            retry.hidden = false;
        } finally {
            saving = false;
            if (all) all.disabled = queue.length > 0 || boxes.every(box => box.checked);
            if (!failed && queue.length) saveNext();
        }
    }
    boxes.forEach(box => box.addEventListener('change', () => enqueue()));
    all?.addEventListener('change', () => { boxes.forEach(box => box.checked = true); enqueue(true); });
    retry.addEventListener('click', saveNext);
    form.addEventListener('submit', event => event.preventDefault());
    window.addEventListener('beforeunload', event => { if (queue.length) { event.preventDefault(); event.returnValue = ''; } });
})();
</script>
@endpush
