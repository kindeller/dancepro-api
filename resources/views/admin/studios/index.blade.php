@extends('layouts.admin', [
    'title' => 'Studios',
    'heading' => 'Studios',
    'subheading' => 'Manage studio presentation, contact details, and availability.',
])

@section('content')
    <div class="toolbar">
        <form class="filters" method="GET" action="{{ route('admin.studios.index') }}">
            <label>Search<input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Studio name or email"></label>
            <label>Status<select name="status"><option value="">Any status</option>@foreach($statuses as $status)<option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ ucfirst($status->value) }}</option>@endforeach</select></label>
            <button type="submit">Filter</button>
            <a class="button secondary" href="{{ route('admin.studios.index') }}">Reset</a>
        </form>
        <a class="button" href="{{ route('admin.studios.create') }}">Add studio</a>
    </div>

    <div class="card"><table><thead><tr><th>Studio</th><th>Status</th><th>Contact</th><th>Concerts</th><th>Updated</th><th></th></tr></thead><tbody>
        @forelse($studios as $studio)
            <tr><td><strong>{{ $studio->name }}</strong><div class="muted">{{ $studio->slug }}</div></td><td><span class="badge">{{ $studio->status->value }}</span></td><td>{{ $studio->contact_name ?: '—' }}<div class="muted">{{ $studio->contact_email }}</div></td><td>{{ $studio->concerts_count }}</td><td>{{ $studio->updated_at?->toDayDateTimeString() }}</td><td><a class="button secondary" href="{{ route('admin.studios.edit', $studio) }}">Edit</a></td></tr>
        @empty<tr><td colspan="6" class="muted">No studios match this view.</td></tr>@endforelse
    </tbody></table><div class="pagination">{{ $studios->links() }}</div></div>
@endsection
