@extends('layouts.admin', ['title' => 'Photo uploads', 'heading' => 'Photo uploads', 'subheading' => $event->name])

@section('content')
<div class="toolbar" style="margin-bottom:16px">
    <a class="button secondary" href="{{ $event->event_type->value === 'competition' ? route('admin.competition-gallery.show', $event) : route('admin.scheduling-events.show', $event) }}">{{ $event->event_type->value === 'competition' ? 'Back to gallery' : 'Back to event' }}</a>
    <span class="badge">Private gallery</span>
</div>
@if(!$plan)
    <div class="card card-pad"><p>No working programme has been connected yet. Prepare the event and begin uploading from the Mac app first.</p></div>
@else
    <form method="GET" class="filters" style="margin-bottom:16px">
        <label>Working programme<select name="plan_uuid">
            @foreach($plans as $option)
                <option value="{{ $option->uuid }}" @selected($option->id === $plan->id)>Programme {{ $option->snapshot['version'] ?? '' }} · {{ $option->created_at->format('j M Y, g:i:s a') }}</option>
            @endforeach
        </select></label>
        <button class="secondary">View</button>
    </form>
    @if(!$progress['plan_current'])
        <p class="card card-pad">This is not the current working programme. Resolve the programme conflict before recording exceptions.</p>
    @endif
    <p class="muted">Waive missing work when a role cannot provide it, for example equipment failure or illness. Choose the exact scope and record why. Existing photos are kept. A waiver is not upload completion or permission to publish.</p>
    @foreach($roleDetails as $detail)
        @php($summary = collect($progress['photographers'])->firstWhere('role_uuid', $detail['role']['role_uuid']))
        <section class="card card-pad" style="margin-bottom:18px">
            <h2>{{ $detail['role']['role_name'] }}</h2>
            <p>{{ $summary['verified_file_count'] }} photos verified · {{ $summary['pending_file_count'] }} awaiting upload · {{ $summary['complete_item_count'] }} items complete · {{ $summary['waived_item_count'] }} waived</p>
            <form method="POST" action="{{ route('admin.photo-uploads.update', $event) }}" class="grid">
                @csrf
                <input type="hidden" name="plan_uuid" value="{{ $plan->uuid }}">
                <input type="hidden" name="role_uuid" value="{{ $detail['role']['role_uuid'] }}">
                <fieldset @disabled(!$progress['plan_current'] || !$detail['items']) style="border:0;padding:0;margin:0" class="grid">
                    <div class="filters">
                        <label>Action<select name="action"><option value="waive">Waive missing contribution</option><option value="reinstate">Reinstate requirement</option></select></label>
                        <label>Scope<select name="scope_uuid">
                            <option value="">Whole event for this role</option>
                            @foreach($scopeNodes as $node)
                                @if(in_array($node['kind'], ['day', 'section', 'group']))
                                    <option value="{{ $node['uuid'] }}">{{ ucfirst($node['kind']) }}: {{ $node['title'] }}</option>
                                @endif
                            @endforeach
                            @foreach($detail['items'] as $item)
                                <option value="{{ $item['uuid'] }}">Item: {{ $item['title'] }}</option>
                            @endforeach
                        </select></label>
                    </div>
                    <label>Reason<textarea name="reason" required minlength="5" maxlength="2000" rows="2" placeholder="Explain the exception or why work is required again"></textarea></label>
                    <p class="muted">Completed items stay complete. Reinstating a waiver requires Upload Complete again. Later uploads reopen the affected waived item automatically.</p>
                    <div><button>Apply to selected scope</button></div>
                </fieldset>
            </form>
            <details style="margin-top:16px"><summary>Item contributions ({{ count($detail['items']) }})</summary>
                <div style="overflow:auto"><table><thead><tr><th>Item</th><th>Status</th><th>Waiver reason</th></tr></thead><tbody>
                @foreach($detail['items'] as $item)
                    <tr><td>{{ $item['title'] }}</td><td>{{ str_replace('_', ' ', ucfirst($item['status'])) }}</td><td>{{ $item['reason'] ?? '—' }}</td></tr>
                @endforeach
                </tbody></table></div>
            </details>
        </section>
    @endforeach
    @if($progress['unfilled'])
        <section class="card card-pad" style="margin-bottom:18px"><h2>Unfilled staffing roles</h2>
            <p class="muted">Upload exceptions do not change Crew Management assignments.</p>
            @foreach($progress['unfilled'] as $role)<p>{{ $role['date'] }} · {{ ucfirst($role['period']) }} · {{ $role['role_name'] }} · {{ $role['missing_count'] }} unfilled</p>@endforeach
        </section>
    @endif
@endif
<section class="card card-pad"><h2>Exception history</h2><p class="muted">Latest 50 changes. Earlier entries remain in the audit record.</p>
    @forelse($history as $entry)
        @php($details = json_decode($entry->details, true))
        <article style="border-top:1px solid var(--line);padding:10px 0">
            <strong>{{ ['contribution_waive' => 'Contribution waived', 'contribution_reinstate' => 'Requirement reinstated', 'waiver_reopened' => 'New upload reopened waiver'][$entry->action] }}</strong>
            · {{ $entry->actor_name ?? 'Account no longer available' }} · {{ $entry->created_at }}
            <p>{{ $details['reason'] ?? '' }}</p>
            <span class="muted">{{ count($details['item_uuids'] ?? [$details['item_uuid'] ?? '']) }} item(s) · {{ $entry->role_name ?? 'Former event role' }}</span>
        </article>
    @empty<p class="muted">No exceptions recorded.</p>@endforelse
</section>
@endsection
