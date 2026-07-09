@extends('layouts.admin', [
    'title' => 'Download Links',
    'heading' => 'Download Links',
    'subheading' => 'Inspect generated tracking links, status, expiry, and access counts.',
])

@section('content')
    <div class="toolbar">
        <form class="filters" method="GET" action="{{ route('admin.download-links.index') }}">
            <label>
                Search
                <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="UUID, storage key, purpose">
            </label>
            <label>
                Status
                <select name="status">
                    <option value="">Any status</option>
                    @foreach (['active', 'expired', 'revoked'] as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </label>
            <button type="submit">Filter</button>
            <a class="button secondary" href="{{ route('admin.download-links.index') }}">Reset</a>
        </form>

        <a class="button" href="{{ route('admin.download-links.create') }}">Create links</a>
    </div>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Storage key</th>
                    <th>Status</th>
                    <th>Disk</th>
                    <th>Opens</th>
                    <th>Access rows</th>
                    <th>Expires</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($downloadLinks as $downloadLink)
                    <tr>
                        <td class="truncate">
                            <a href="{{ route('admin.download-links.show', $downloadLink) }}">{{ $downloadLink->storage_key }}</a>
                            @if ($downloadLink->purpose)
                                <div class="muted">{{ $downloadLink->purpose }}</div>
                            @endif
                        </td>
                        <td><span class="badge {{ $downloadLink->status }}">{{ $downloadLink->status }}</span></td>
                        <td>{{ $downloadLink->storage_disk }}</td>
                        <td>{{ number_format($downloadLink->download_count) }}</td>
                        <td>{{ number_format($downloadLink->accesses_count) }}</td>
                        <td>{{ $downloadLink->expires_at?->toDayDateTimeString() ?? 'Never' }}</td>
                        <td>{{ $downloadLink->created_at?->toDayDateTimeString() }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="muted">No download links match this view.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="pagination">
            {{ $downloadLinks->links() }}
        </div>
    </div>
@endsection
