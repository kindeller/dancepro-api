<section class="card card-pad booking-materials" id="event-materials">
    <h2>Programmes &amp; slideshows</h2>
    <p class="muted">Record details and upload files received by email or from the studio. These are shared with the Studio Hub and tracker. Add them when available; they are not required to approve a booking.</p>
    @foreach($booking->items->whereNull('cancelled_at') as $item)
        @php($event = $item->schedulingEvent)
        <details>
            <summary>{{ $item->title ?: ($item->item_type->value === 'concert' ? 'Concert' : 'DR Portrait') }} · {{ $item->event_date->format('j M Y') }}</summary>
            @if($event)
                <h3>Programme</h3>
                @if($item->programme_path)<p><a href="{{ route('admin.concert-booking-events.programme.show', $item) }}" target="_blank" rel="noopener">{{ $item->programme_name ?: 'Open current programme' }}</a> · <a href="{{ route('admin.programmes.show', $event) }}">Prepare / confirm programme</a></p>@else<p class="muted">Not received yet.</p>@endif
                <form method="post" enctype="multipart/form-data" action="{{ route('admin.concert-booking-events.files', $item) }}">
                    @csrf<input type="hidden" name="kind" value="programme">
                    <label>{{ $item->programme_path ? 'Replace programme' : 'Upload programme' }}<input type="file" name="files[]" required></label>
                    <small class="muted">PDF is ready for preparation. Other formats are kept for conversion; upload the converted PDF here to replace them.</small>
                    <div><button type="submit">{{ $item->programme_path ? 'Replace programme' : 'Upload programme' }}</button></div>
                </form>
                @include('admin.concert-bookings._studio-media-files', ['event' => $event, 'item' => $item])
            @else
                <p class="muted">Seats and studio files can be recorded here after this event is approved. You can attach its programme now.</p>
                @if($item->programme_path)<p><a href="{{ route('admin.concert-booking-events.programme.show', $item) }}">{{ $item->programme_name ?: 'Open programme' }}</a></p>@endif
                <form method="post" enctype="multipart/form-data" action="{{ route('admin.concert-booking-events.programme.store', $item) }}">@csrf<label>Programme PDF<input type="file" name="programme" accept="application/pdf,.pdf" required></label><div><button>{{ $item->programme_path ? 'Replace programme' : 'Upload programme' }}</button></div></form>
            @endif
        </details>
    @endforeach
</section>
