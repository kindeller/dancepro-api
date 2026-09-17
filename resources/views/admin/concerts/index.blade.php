@extends('layouts.admin', ['title' => 'Concerts', 'heading' => 'Concerts', 'subheading' => 'Manage release, approval, availability, access, and presentation.'])
@section('content')
<div class="toolbar">
    <form class="filters" method="GET" action="{{ route('admin.concerts.index') }}">
        <label>Search<input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Concert or venue"></label>
        <label>Studio<select name="studio_id"><option value="">All studios</option>@foreach($studios as $studio)<option value="{{ $studio->id }}" @selected((string)($filters['studio_id'] ?? '') === (string)$studio->id)>{{ $studio->name }}</option>@endforeach</select></label>
        <label>Status<select name="status"><option value="">Any status</option>@foreach($statuses as $status)<option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ ucfirst($status->value) }}</option>@endforeach</select></label>
        <button type="submit">Filter</button><a class="button secondary" href="{{ route('admin.concerts.index') }}">Reset</a>
    </form>
    <a class="button" href="{{ route('admin.concerts.create') }}">Add concert</a>
</div>
<div class="card"><table><thead><tr><th>Concert</th><th>Studio</th><th>Release</th><th>Availability</th><th>Media</th><th></th></tr></thead><tbody>
@forelse($concerts as $concert)
    <tr><td><strong>{{ $concert->name }}</strong><div class="muted">{{ $concert->event_date?->format('j M Y') }} @if($concert->venue_name) · {{ $concert->venue_name }} @endif</div></td><td>{{ $concert->studio->name }}</td><td><span class="badge">{{ $concert->status->value }}</span><div class="muted">@if(!$concert->is_enabled) Disabled @elseif($concert->requires_approval && !$concert->approved_at) Awaiting approval @elseif($concert->isPubliclyAvailable()) Public @else Not public @endif</div></td><td>{{ $concert->available_from?->format('j M Y H:i') ?? 'Immediately' }}<div class="muted">to {{ $concert->available_until?->format('j M Y H:i') ?? 'no end date' }}</div></td><td>{{ $concert->media_collections_count }} collections</td><td><a class="button secondary" href="{{ route('admin.concerts.edit', $concert) }}">Edit</a></td></tr>
@empty<tr><td colspan="6" class="muted">No concerts match this view.</td></tr>@endforelse
</tbody></table><div class="pagination">{{ $concerts->links() }}</div></div>
@endsection
