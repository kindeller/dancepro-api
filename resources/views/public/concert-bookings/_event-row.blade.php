<div class="booking-item">
    <div class="grid booking-event-heading">
        <input type="hidden" name="items[{{ $index }}][event_type_definition_id]" value="{{ data_get($row, 'event_type_definition_id') }}">
        <label>Event details<select class="event-type-select" name="items[{{ $index }}][item_type]" required><option value="concert" @selected(data_get($row, 'item_type') === 'concert')>Concert</option><option value="dress_rehearsal" @selected(data_get($row, 'item_type') === 'dress_rehearsal')>DR Portrait</option></select></label>
        <label>Event title<input name="items[{{ $index }}][title]" value="{{ data_get($row, 'title') }}" placeholder="e.g. Junior Concert"></label>
    </div>
    @php($selectedVenue = data_get($row, 'venue_uuid'))
    @php($venueRecord = $venues->firstWhere('uuid', $selectedVenue))
    <div class="venue-picker">
        <div class="venue-search-field">
            <label for="venue-search-{{ $index }}">Venue</label>
            <input type="hidden" name="items[{{ $index }}][venue_uuid]" class="venue-value" value="{{ $selectedVenue }}">
            <input id="venue-search-{{ $index }}" class="venue-search" name="items[{{ $index }}][venue_search]" type="text" value="{{ $venueRecord?->name ?? data_get($row, 'venue_search') }}" placeholder="Start typing a venue name" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="venue-results-{{ $index }}" aria-describedby="venue-address-{{ $index }}" @required($selectedVenue !== 'other')>
            <div id="venue-results-{{ $index }}" class="venue-results" role="listbox" aria-label="Matching venues" hidden></div>
        </div>
        <button class="button secondary other-venue-button" type="button" aria-pressed="{{ $selectedVenue === 'other' ? 'true' : 'false' }}">Other venue</button>
    </div>
    <div id="venue-address-{{ $index }}" class="selected-venue-address muted" aria-live="polite"></div>
    <div class="other-venue-fields grid booking-two-columns" @if($selectedVenue !== 'other') hidden @endif>
        <label>Other venue name<input name="items[{{ $index }}][venue_name]" value="{{ data_get($row, 'venue_name') }}"></label>
        <label>Other venue address<input name="items[{{ $index }}][venue_address]" value="{{ data_get($row, 'venue_address') }}"></label>
    </div>
    <div class="grid"><label>Date<input type="date" name="items[{{ $index }}][event_date]" value="{{ data_get($row, 'event_date') }}" required></label><label>Start time<input type="time" name="items[{{ $index }}][starts_at]" value="{{ data_get($row, 'starts_at') }}" required></label><label>Finish time<input type="time" name="items[{{ $index }}][finishes_at]" value="{{ data_get($row, 'finishes_at') }}" required></label></div>
</div>
