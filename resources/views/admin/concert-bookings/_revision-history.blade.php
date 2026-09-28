@if($revisions->isNotEmpty())
<section class="card card-pad booking-section">
    <h2>Booking change history</h2>
    <p class="muted">Saved changes to the current booking. The original studio submission remains unchanged.</p>
    @foreach($revisions as $revision)
        <details style="padding:12px 0;border-top:1px solid var(--line)">
            <summary>{{ $revision->created_at->format('j M Y, g:i a') }} · {{ $revision->user?->name ?? 'System' }}</summary>
            @if($revision->reason)<p>{{ $revision->reason }}</p>@endif
            <div style="overflow:auto"><table><thead><tr><th>Detail</th><th>Before</th><th>After</th></tr></thead><tbody>
            @foreach($revisionRows[$revision->id] as $row)
                <tr><th>{{ $row['label'] }}</th><td>{{ $row['before'] }}</td><td>{{ $row['after'] }}</td></tr>
            @endforeach
            </tbody></table></div>
        </details>
    @endforeach
    {{ $revisions->withQueryString()->links() }}
</section>
@endif
