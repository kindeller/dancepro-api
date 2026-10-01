@php
    $venueSearchOptions = $venues->map(fn ($venue) => ['uuid' => $venue->uuid, 'name' => $venue->name, 'address' => collect([$venue->address_line_1, $venue->address_line_2, trim(collect([$venue->suburb, $venue->state, $venue->postcode])->filter()->join(' '))])->filter()->join(', ')])->values();
@endphp
const bookingVenues = {{ \Illuminate\Support\Js::from($venueSearchOptions) }};
function configureVenueRow(row) {
    const search = row.querySelector('.venue-search'), value = row.querySelector('.venue-value');
    const results = row.querySelector('.venue-results'), other = row.querySelector('.other-venue-button');
    const fields = row.querySelector('.other-venue-fields'), address = row.querySelector('.selected-venue-address');
    let matches = [], active = -1;
    const close = () => { results.hidden = true; search.setAttribute('aria-expanded', 'false'); search.removeAttribute('aria-activedescendant'); };
    const update = () => {
        const isOther = value.value === 'other';
        fields.hidden = !isOther;
        fields.querySelectorAll('input').forEach(input => { input.required = isOther; input.disabled = !isOther; });
        other.setAttribute('aria-pressed', String(isOther));
        search.required = !isOther;
        search.placeholder = isOther ? 'Other venue selected' : 'Start typing a venue name';
        search.setCustomValidity(!value.value && search.value ? 'Choose a matching venue or select Other venue.' : '');
        address.textContent = bookingVenues.find(venue => venue.uuid === value.value)?.address || '';
    };
    const choose = venue => { value.value = venue.uuid; search.value = venue.name; update(); close(); };
    const highlight = () => {
        [...results.querySelectorAll('[role="option"]')].forEach((option, index) => option.setAttribute('aria-selected', String(index === active)));
        const option = results.children[active];
        if (option) { search.setAttribute('aria-activedescendant', option.id); option.scrollIntoView({block:'nearest'}); }
    };
    const show = () => {
        const query = search.value.trim().toLocaleLowerCase();
        matches = bookingVenues.filter(venue => `${venue.name} ${venue.address}`.toLocaleLowerCase().includes(query)).slice(0, 15);
        active = -1; search.removeAttribute('aria-activedescendant'); results.replaceChildren();
        matches.forEach((venue, index) => {
            const option = document.createElement('button');
            option.type = 'button'; option.tabIndex = -1; option.id = `${results.id}-option-${index}`;
            option.setAttribute('role', 'option'); option.setAttribute('aria-selected', 'false');
            const name = document.createElement('strong'); name.textContent = venue.name;
            const location = document.createElement('small'); location.textContent = venue.address;
            option.append(name, location);
            option.addEventListener('pointerdown', event => event.preventDefault());
            option.addEventListener('click', () => choose(venue)); results.append(option);
        });
        if (!matches.length) { const message = document.createElement('p'); message.textContent = 'No matching venues. Select Other venue to add the details.'; results.append(message); }
        results.hidden = false; search.setAttribute('aria-expanded', 'true');
    };
    search.addEventListener('input', () => { value.value = ''; update(); show(); });
    search.addEventListener('focus', show);
    search.addEventListener('keydown', event => {
        if (event.key === 'Escape') { close(); return; }
        if (['ArrowDown', 'ArrowUp'].includes(event.key)) {
            event.preventDefault(); if (results.hidden) show();
            if (matches.length) { active = active < 0 ? (event.key === 'ArrowDown' ? 0 : matches.length - 1) : (active + (event.key === 'ArrowDown' ? 1 : -1) + matches.length) % matches.length; highlight(); }
        } else if (event.key === 'Enter' && !results.hidden && (active >= 0 || matches.length === 1)) {
            event.preventDefault(); choose(matches[active >= 0 ? active : 0]);
        }
    });
    row.querySelector('.venue-picker').addEventListener('focusout', event => { if (!row.querySelector('.venue-picker').contains(event.relatedTarget)) close(); });
    other.addEventListener('click', () => { value.value = 'other'; search.value = ''; update(); close(); fields.querySelector('input').focus(); });
    update();
}
