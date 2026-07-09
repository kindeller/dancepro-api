@extends('layouts.admin', [
    'title' => 'DancePro Admin',
    'heading' => 'Dashboard',
    'subheading' => 'A quick read on generated download links and access activity.',
])

@section('content')
    <section class="grid stats" aria-label="Download link statistics">
        <div class="card metric">
            <span class="muted">Total links</span>
            <strong>{{ number_format($totals['links']) }}</strong>
        </div>
        <div class="card metric">
            <span class="muted">Active links</span>
            <strong>{{ number_format($totals['active']) }}</strong>
        </div>
        <div class="card metric">
            <span class="muted">Access attempts</span>
            <strong>{{ number_format($totals['accesses']) }}</strong>
        </div>
        <div class="card metric">
            <span class="muted">Successful opens</span>
            <strong>{{ number_format($totals['successful_accesses']) }}</strong>
        </div>
    </section>

    <section class="grid two-col" style="margin-top: 18px;">
        <div class="card">
            <div class="card-pad">
                <h2>Recent Download Links</h2>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Key</th>
                        <th>Status</th>
                        <th>Opens</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentLinks as $downloadLink)
                        <tr>
                            <td class="truncate">
                                <a href="{{ route('admin.download-links.show', $downloadLink) }}">{{ $downloadLink->storage_key }}</a>
                            </td>
                            <td><span class="badge {{ $downloadLink->status }}">{{ $downloadLink->status }}</span></td>
                            <td>{{ number_format($downloadLink->download_count) }}</td>
                            <td>{{ $downloadLink->created_at?->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="muted">No download links yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="card">
            <div class="card-pad">
                <h2>Recent Access</h2>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Result</th>
                        <th>When</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentAccesses as $access)
                        <tr>
                            <td>
                                @if ($access->was_successful)
                                    <span class="badge">success</span>
                                @else
                                    <span class="badge revoked">{{ $access->failure_reason ?? 'failed' }}</span>
                                @endif
                            </td>
                            <td>{{ $access->accessed_at?->diffForHumans() }}</td>
                            <td>{{ $access->ip_address ?? 'Unknown' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="muted">No access attempts yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
