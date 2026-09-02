@extends('layouts.admin', ['title' => 'Venue Management', 'heading' => 'Venue Management', 'subheading' => 'Manage reusable venue details, access maps and operational information.'])

@section('content')
    <div class="card card-pad" style="margin-bottom:20px">
        <h2>Add venue</h2>
        <p class="muted">New venues are available when creating or editing an event.</p>
        <form method="POST" action="{{ route('admin.venues.store') }}" class="grid two-col">
            @csrf
            <div class="grid">
                <label>Name<input name="name" value="{{ old('name') }}" required></label>
                <label>Address<input name="address_line_1" value="{{ old('address_line_1') }}"></label>
                <label>Address line 2<input name="address_line_2" value="{{ old('address_line_2') }}"></label>
                <div class="grid" style="grid-template-columns:2fr 1fr 1fr">
                    <label>Suburb<input name="suburb" value="{{ old('suburb') }}"></label>
                    <label>State<input name="state" value="{{ old('state', 'WA') }}"></label>
                    <label>Postcode<input name="postcode" value="{{ old('postcode') }}"></label>
                </div>
            </div>
            <div class="grid">
                <label>Access notes<textarea name="access_notes" style="min-height:80px;font-family:inherit">{{ old('access_notes') }}</textarea></label>
                <label>Parking notes<textarea name="parking_notes" style="min-height:80px;font-family:inherit">{{ old('parking_notes') }}</textarea></label>
                <label>Operational notes<textarea name="operational_notes" style="min-height:80px;font-family:inherit">{{ old('operational_notes') }}</textarea></label>
                <button type="submit">Add venue</button>
            </div>
        </form>
    </div>

    <form method="GET" class="card card-pad" style="margin-bottom:20px">
        <div class="toolbar">
            <label style="flex:1;min-width:240px">Search venues
                <input name="search" value="{{ $search }}" placeholder="Name, address or suburb">
            </label>
            <button type="submit">Search</button>
            @if($search !== '')<a class="button secondary" href="{{ route('admin.venues.index') }}">Clear</a>@endif
        </div>
    </form>

    <div class="card" style="margin-bottom:20px;overflow:auto">
        <table>
            <thead><tr><th>Venue</th><th>Address</th><th>Venue information</th><th>Map</th><th>Events</th></tr></thead>
            <tbody>
                @forelse($venues as $venue)
                    <tr>
                        <td>
                            <strong>{{ $venue->name }}</strong>
                            @if($venue->mapUrl())
                                <button
                                    class="venue-map-thumbnail"
                                    type="button"
                                    data-map-url="{{ $venue->mapUrl() }}"
                                    data-map-title="{{ $venue->name }}"
                                    aria-label="Expand map for {{ $venue->name }}"
                                >
                                    <img src="{{ $venue->mapUrl() }}" alt="Map for {{ $venue->name }}">
                                    <span>Click to expand</span>
                                </button>
                            @endif
                        </td>
                        <td>
                            {{ collect([$venue->address_line_1, $venue->address_line_2])->filter()->join(', ') }}
                            @if($venue->suburb || $venue->state || $venue->postcode)<div class="muted">{{ collect([$venue->suburb, $venue->state, $venue->postcode])->filter()->join(' ') }}</div>@endif
                        </td>
                        <td>
                            @if($venue->parking_notes)<div><strong>Parking:</strong> {{ $venue->parking_notes }}</div>@endif
                            @if($venue->access_notes)<div><strong>Access:</strong> {{ $venue->access_notes }}</div>@endif
                            @if($venue->operational_notes)<div><strong>Operations:</strong> {{ $venue->operational_notes }}</div>@endif
                            @if(!$venue->parking_notes && !$venue->access_notes && !$venue->operational_notes)<span class="muted">No notes recorded</span>@endif
                        </td>
                        <td style="min-width:220px">
                            @if($venue->mapUrl())<a href="{{ $venue->mapUrl() }}" target="_blank" rel="noopener">View map ↗</a>@else<span class="muted">No map</span>@endif
                            @if($venue->referenceImageUrl())<div><a href="{{ $venue->referenceImageUrl() }}" target="_blank" rel="noopener">View reference image ↗</a></div>@endif
                            <form method="POST" action="{{ route('admin.venues.map.update', $venue) }}" enctype="multipart/form-data" class="grid" style="margin-top:8px">
                                @csrf @method('PUT')
                                <input type="file" name="map" accept="image/jpeg,image/png,image/webp" required aria-label="Upload map for {{ $venue->name }}">
                                <button class="secondary" type="submit">{{ $venue->map_path ? 'Replace map' : 'Upload map' }}</button>
                            </form>
                        </td>
                        <td>{{ $venue->scheduling_events_count }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No venues match this search.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="pagination"><x-admin-pagination :paginator="$venues" /></div>
    </div>

    <dialog id="venue-map-dialog" class="venue-map-dialog" aria-labelledby="venue-map-dialog-title">
        <div class="venue-map-dialog-header">
            <strong id="venue-map-dialog-title"></strong>
            <button class="secondary" type="button" data-close-map>Close</button>
        </div>
        <img src="" alt="">
    </dialog>
@endsection

@push('scripts')
<script>
    const venueMapDialog = document.getElementById('venue-map-dialog');
    const venueMapDialogImage = venueMapDialog.querySelector('img');
    const venueMapDialogTitle = document.getElementById('venue-map-dialog-title');

    document.querySelectorAll('[data-map-url]').forEach((button) => {
        button.addEventListener('click', () => {
            venueMapDialogImage.src = button.dataset.mapUrl;
            venueMapDialogImage.alt = `Map for ${button.dataset.mapTitle}`;
            venueMapDialogTitle.textContent = button.dataset.mapTitle;
            venueMapDialog.showModal();
        });
    });

    venueMapDialog.querySelector('[data-close-map]').addEventListener('click', () => venueMapDialog.close());
    venueMapDialog.addEventListener('click', (event) => {
        if (event.target === venueMapDialog) venueMapDialog.close();
    });
</script>
@endpush

@push('styles')
<style>
    .venue-map-thumbnail {
        display: grid;
        width: 130px;
        margin: 8px auto 0;
        padding: 0;
        overflow: hidden;
        border: 1px solid #cbd8df;
        border-radius: 7px;
        background: #071c28;
        color: #fff;
        cursor: zoom-in;
    }
    .venue-map-thumbnail img { width: 130px; height: 82px; object-fit: cover; }
    .venue-map-thumbnail span { padding: 4px 6px; font-size: 10px; text-align: center; }
    .venue-map-dialog { width: min(94vw, 1200px); max-width: none; padding: 12px; border: 0; border-radius: 10px; }
    .venue-map-dialog::backdrop { background: rgba(0, 0, 0, .78); }
    .venue-map-dialog-header { display: flex; gap: 16px; align-items: center; justify-content: space-between; margin-bottom: 10px; }
    .venue-map-dialog img { display: block; width: 100%; max-height: 82vh; object-fit: contain; background: #000; }
</style>
@endpush
