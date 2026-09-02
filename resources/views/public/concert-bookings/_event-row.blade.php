<div class="booking-item" style="border-top:1px solid var(--line);padding-top:18px;margin-top:18px">
    <div class="grid" style="grid-template-columns:1fr 1fr 2fr">
        <label>Event type<select name="items[{{ $index }}][event_type_definition_id]" required>@foreach($eventTypes as $eventType)<option value="{{ $eventType->id }}" @selected((int) data_get($row, 'event_type_definition_id') === $eventType->id)>{{ $eventType->name }}</option>@endforeach</select></label>
        <label>Event details<select class="event-type-select" name="items[{{ $index }}][item_type]" required><option value="concert" @selected(data_get($row, 'item_type') === 'concert')>Concert</option><option value="dress_rehearsal" @selected(data_get($row, 'item_type') === 'dress_rehearsal')>DR Portrait</option></select></label>
        <label>Concert title (optional)<input name="items[{{ $index }}][title]" value="{{ data_get($row, 'title') }}" placeholder="e.g. Junior Concert"></label>
    </div>
    @php($selectedVenue = data_get($row, 'venue_uuid'))
    <label>Venue<select name="items[{{ $index }}][venue_uuid]" class="venue-select" required><option value="">Select a venue</option><option value="other" @selected($selectedVenue === 'other')>Other venue</option>@foreach($venues as $venue)<option value="{{ $venue->uuid }}" data-address="{{ collect([$venue->address_line_1, $venue->address_line_2, trim(collect([$venue->suburb, $venue->state, $venue->postcode])->filter()->join(' '))])->filter()->join(', ') }}" @selected($selectedVenue === $venue->uuid)>{{ $venue->name }}</option>@endforeach</select></label>
    <div class="selected-venue-address muted" style="margin-top:-8px"></div>
    <div class="other-venue-fields grid" style="grid-template-columns:repeat(2,1fr)" @if($selectedVenue !== 'other') hidden @endif>
        <label>Other venue name<input name="items[{{ $index }}][venue_name]" value="{{ data_get($row, 'venue_name') }}"></label>
        <label>Other venue address<input name="items[{{ $index }}][venue_address]" value="{{ data_get($row, 'venue_address') }}"></label>
    </div>
    <div class="grid"><label>Date<input type="date" name="items[{{ $index }}][event_date]" value="{{ data_get($row, 'event_date') }}" required></label><label>Start time<input type="time" name="items[{{ $index }}][starts_at]" value="{{ data_get($row, 'starts_at') }}" required></label><label>Finish time<input type="time" name="items[{{ $index }}][finishes_at]" value="{{ data_get($row, 'finishes_at') }}" required></label></div>
</div>
