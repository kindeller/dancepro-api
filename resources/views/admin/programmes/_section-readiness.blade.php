<section class="card card-pad" style="margin-bottom:20px" aria-label="Section photo readiness">
    <h2 style="margin-top:0">{{ $readiness['label'] }}</h2>
    <p class="muted">Section photo contributions · Photos remain private. This status does not publish them.</p>
    @if($readiness['roles'])
        <div style="overflow:auto"><table>
            <thead><tr><th>Photo role</th><th>Status</th><th>Competitors complete</th><th>Waived</th><th>Outstanding</th><th>Photos verified</th><th>Photos pending</th></tr></thead>
            <tbody>@foreach($readiness['roles'] as $role)
                <tr><td>{{ $role['name'] }}</td>
                    <td>{{ ['complete' => 'Complete', 'waived' => 'Waived', 'complete_with_exceptions' => 'Complete with exceptions', 'receiving' => 'In progress', 'missing' => 'Not started'][$role['status']] }}</td>
                    <td>{{ $role['complete'] }} / {{ $role['expected'] }}</td><td>{{ $role['waived'] }}</td><td>{{ $role['outstanding'] }}</td><td>{{ $role['verified'] }}</td><td>{{ $role['pending'] }}</td>
                </tr>
            @endforeach</tbody>
        </table></div>
    @endif
    <p style="margin-bottom:0"><a href="{{ route('admin.photo-uploads.show', $event) }}">Manage photo uploads and exceptions</a></p>
</section>
