@extends('layouts.admin', ['title' => 'Edit '.$studio->name, 'heading' => 'Edit Studio', 'subheading' => $studio->name])
@section('content')
<form class="card card-pad" method="POST" action="{{ route('admin.studios.update', $studio) }}">@csrf @method('PUT')
    @include('admin.studios._form', ['submitLabel' => 'Save studio'])
</form>

<section style="margin-top:24px">
    <div class="toolbar">
        <div>
            <h2>Associated Concerts</h2>
            <div class="muted">{{ $studio->concerts->count() }} {{ Str::plural('concert', $studio->concerts->count()) }} assigned to {{ $studio->name }}.</div>
        </div>
        <a class="button" href="{{ route('admin.concerts.create', ['studio_id' => $studio->id]) }}">Add concert</a>
    </div>

    <div class="card">
        <table>
            <thead><tr><th>Concert</th><th>Release</th><th>Access</th><th>Media</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($studio->concerts as $concert)
                <tr>
                    <td><strong>{{ $concert->name }}</strong><div class="muted">{{ $concert->event_date?->format('j M Y') ?? 'Date not set' }}@if($concert->venue_name) · {{ $concert->venue_name }}@endif</div></td>
                    <td><span class="badge">{{ $concert->status->value }}</span><div class="muted">@if(!$concert->is_enabled) Disabled @elseif($concert->requires_approval && !$concert->approved_at) Awaiting approval @elseif($concert->isPubliclyAvailable()) Public @else Not public @endif</div></td>
                    <td>{{ $concert->requiresPassword() ? 'Password protected' : 'Open' }}</td>
                    <td>{{ $concert->media_collections_count }} {{ Str::plural('collection', $concert->media_collections_count) }}</td>
                    <td>
                        <div class="actions">
                            <a class="button secondary" href="{{ route('admin.concerts.edit', $concert) }}">Edit</a>
                            @if($concert->isPubliclyAvailable())
                                <a class="button secondary" href="{{ route('concerts.show', $concert) }}" target="_blank" rel="noopener">Visit</a>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No concerts are associated with this studio yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
