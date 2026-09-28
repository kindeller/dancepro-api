<section class="card card-pad upload-overview" style="margin-bottom:18px">
    <div class="toolbar"><h2>Upload progress</h2><a class="button secondary" href="{{ route('admin.photo-uploads.show', ['schedulingEvent' => $event, 'plan_uuid' => $plan?->uuid]) }}">Refresh progress</a></div>
    <div class="upload-metrics">
        <div><span>Published photographers</span><strong>{{ $uploadSummary['photographers'] }}</strong><small>{{ $uploadSummary['roles'] }} photo {{ Str::plural('role', $uploadSummary['roles']) }}</small></div>
        <div><span>Photos registered</span><strong>{{ number_format($uploadSummary['registered']) }}</strong></div>
        <div><span>Uploaded & verified</span><strong>{{ number_format($uploadSummary['verified']) }}</strong></div>
        <div><span>Awaiting upload / verification</span><strong>{{ number_format($uploadSummary['pending']) }}</strong></div>
    </div>
    @if($uploadSummary['percent'] !== null)
        <div class="upload-percentage"><strong>{{ $uploadSummary['percent'] }}% of registered photos verified</strong><progress max="100" value="{{ $uploadSummary['percent'] }}" aria-label="Registered photos verified"></progress></div>
    @endif
    <p class="muted">The uploader registers its queued photos when Upload starts. Counts update when that list reaches DancePro; photos still only on the laptop are not included. 100% verified is separate from the photographer confirming Upload Complete. Duplicate copies are excluded.</p>
    @if($progress)<small class="muted">Checked {{ \Illuminate\Support\Carbon::parse($progress['checked_at'])->format('j M Y, g:i:s a') }} · {{ $progress['plan_current'] ? 'Current working programme' : 'Older working programme' }}</small>@endif
    <div class="upload-role-table"><table><thead><tr><th>Photo role / crew</th><th>Registered</th><th>Verified</th><th>Remaining</th><th>Progress</th><th>Completion</th></tr></thead><tbody>
    @forelse($progress['photographers'] ?? [] as $person)
        @php
            $total = $person['verified_file_count'] + $person['pending_file_count'];
            $percentage = $total ? (int) floor(100 * $person['verified_file_count'] / $total) : null;
            $crewNames = collect($roster['photographers'])->where('role_uuid', $person['role_uuid'])->pluck('name')->unique()->join(', ');
            $completion = match($person['status']) {
                'complete' => 'Upload complete', 'complete_with_exceptions' => 'Complete with waivers',
                'waived' => 'Waived', 'no_items' => 'No assigned items', 'needs_attention' => 'Programme needs attention',
                'receiving' => $person['pending_file_count'] ? 'Awaiting files' : 'Awaiting Upload Complete', default => 'Not started',
            };
        @endphp
        <tr><td><strong>{{ $person['role_name'] }}</strong><small>{{ $crewNames ?: 'No published photographer assigned' }}</small>
            @if($person['uploaders'])<small>Uploaded by {{ collect($person['uploaders'])->pluck('name')->unique()->join(', ') }}</small>@endif
        </td><td>{{ number_format($total) }}</td><td>{{ number_format($person['verified_file_count']) }}</td><td>{{ number_format($person['pending_file_count']) }}</td>
        <td>@if($percentage !== null)<strong>{{ $percentage }}%</strong><progress max="100" value="{{ $percentage }}" aria-label="{{ $person['role_name'] }} registered photos verified"></progress>@else<span class="muted">No photos registered</span>@endif</td>
        <td><strong @if($person['status'] === 'complete') style="color:#126236" @endif>{{ $completion }}</strong><small>{{ $person['complete_item_count'] }} / {{ $person['expected_item_count'] }} items complete @if($person['waived_item_count']) · {{ $person['waived_item_count'] }} waived @endif</small></td></tr>
    @empty
        <tr><td colspan="6">{{ $plan ? 'No photo roles are configured for this event.' : 'Photo counts will appear after the uploader connects a working programme.' }}
            @foreach($roster['photographers'] as $person)<small>{{ $person['role_name'] }} · {{ $person['name'] }}</small>@endforeach
        </td></tr>
    @endforelse
    </tbody></table></div>
</section>
