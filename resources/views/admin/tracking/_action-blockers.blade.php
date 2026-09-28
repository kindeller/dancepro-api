@if(count($blockers))
<div role="status" class="task-action-blockers" style="margin:16px 0;padding:16px;background:#eaf8fd;border:1px solid var(--line);border-radius:8px">
    <strong>{{ $blockerHeading ?? 'This action is not ready yet' }}</strong>
    <ul style="margin-bottom:0">@foreach($blockers as $blocker)<li>{{ $blocker }}</li>@endforeach</ul>
</div>
@endif
