@extends('layouts.crew', ['title' => 'My Profile'])

@section('content')
@php
    $vehicleRows = old('vehicles', $crewProfile->vehicles->toArray());
    $signaturesByContract = $crewProfile->contractSignatures->keyBy('crew_contract_id');
    $wwccMissing = blank($crewProfile->working_with_children_number) || $crewProfile->working_with_children_expiry === null;
    $wwccExpiring = $crewProfile->working_with_children_expiry?->between(today(), today()->addDays(60)) ?? false;
    $wwccExpired = $crewProfile->working_with_children_expiry?->isPast() ?? false;
@endphp

<div class="page-heading">
    <div><div class="crew-hub-brand"><img src="{{ asset('images/brand/dancepro-icon-blue.png') }}" alt=""><span>CREW HUB</span></div><h1>My Profile</h1><p class="muted">Keep your contact, payment and safety information current.</p></div>
    <div class="profile-account-actions">
        <button type="button" class="button secondary" id="open-password-dialog">Change password</button>
        <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="button secondary">Log out</button></form>
    </div>
</div>

<dialog class="account-dialog" id="password-dialog" aria-labelledby="password-dialog-title" data-open-on-load="{{ $errors->hasAny(['current_password', 'password']) ? '1' : '0' }}">
    <div class="account-dialog-heading"><div><span class="type-label">ACCOUNT SECURITY</span><h2 id="password-dialog-title">Change password</h2><p class="muted">Enter your current password before choosing a new one.</p></div><button type="button" class="button secondary" id="close-password-dialog" aria-label="Close password change">Close</button></div>
    <form method="POST" action="{{ route('crew.profile.password') }}" class="grid">@csrf @method('PUT')
        <label>Current password<input type="password" name="current_password" autocomplete="current-password" required></label>
        <label>New password<input type="password" name="password" autocomplete="new-password" required></label>
        <label>Confirm new password<input type="password" name="password_confirmation" autocomplete="new-password" required></label>
        <button type="submit">Change password</button>
    </form>
</dialog>

@if($badgeGroups->isNotEmpty())
<section class="card profile-badges" aria-label="Your achievements">
    @foreach($badgeGroups as $group => $badges)
        @foreach($badges as $badge)
            @if($badge['url'])<a class="profile-badge {{ $group }} {{ isset($badge['design']) ? 'design-'.$badge['design'] : '' }}" href="{{ $badge['url'] }}" aria-label="{{ str($group)->title() }}: {{ $badge['name'] }}. {{ $badge['detail'] }}">@else<button class="profile-badge {{ $group }} {{ isset($badge['design']) ? 'design-'.$badge['design'] : '' }}" type="button" aria-label="{{ str($group)->title() }}: {{ $badge['name'] }}. {{ $badge['detail'] }}">@endif
                <span class="profile-badge-roundel">
                    <img src="{{ asset('images/brand/dancepro-icon-blue.png') }}" width="88" height="88" alt="">
                    <span class="profile-badge-centre">{{ $badge['icon'] }}</span>
                </span>
                <span class="profile-badge-tooltip" role="tooltip"><small>{{ str($group)->title() }}</small><strong>{{ $badge['name'] }}</strong><span>{{ $badge['detail'] }}</span></span>
            @if($badge['url'])</a>@else</button>@endif
        @endforeach
    @endforeach
</section>
@endif

@if($wwccMissing || $wwccExpiring || $wwccExpired)
<div class="profile-warning"><strong>Working With Children Check:</strong> @if($wwccMissing)Please add your card number and expiry date.@elseif($wwccExpired)Your recorded check has expired. Please update it.@elseYour check expires on {{ $crewProfile->working_with_children_expiry->format('j M Y') }}.@endif</div>
@endif

<form method="POST" action="{{ route('crew.profile.update') }}">@csrf @method('PUT')
    <section class="card profile-section"><h2>Contact and clothing</h2><div class="profile-grid">
        <label>Preferred name<input name="preferred_name" value="{{ old('preferred_name', $crewProfile->preferred_name) }}" required></label>
        <label>Legal name<input name="legal_name" value="{{ old('legal_name', $crewProfile->legal_name) }}"></label>
        <label>Email<input type="email" name="email" value="{{ old('email', $crewProfile->user->email) }}" required></label>
        <label>Mobile<input name="phone" value="{{ old('phone', $crewProfile->phone) }}" required></label>
        <label>Shirt size<input name="shirt_size" value="{{ old('shirt_size', $crewProfile->shirt_size) }}"></label>
        <label>Jacket size<input name="jacket_size" value="{{ old('jacket_size', $crewProfile->jacket_size) }}"></label>
    </div></section>

    <section class="card profile-section"><h2>Personal and emergency details</h2><div class="profile-grid">
        <label>Date of birth<input type="date" name="date_of_birth" value="{{ old('date_of_birth', $crewProfile->date_of_birth) }}"></label>
        @if(config('services.google_maps.browser_key'))
        <label class="address-search">Find your address<span id="crew-address-search" class="address-search-box"></span><small class="muted">Start typing, then choose your address from the list.</small></label>
        @endif
        <label>Address line 1<input name="address_line_1" value="{{ old('address_line_1', $crewProfile->address_line_1) }}" required></label>
        <label>Address line 2<input name="address_line_2" value="{{ old('address_line_2', $crewProfile->address_line_2) }}"></label>
        <label>Suburb<input name="suburb" value="{{ old('suburb', $crewProfile->suburb) }}" required></label>
        <label>State<input name="state" value="{{ old('state', $crewProfile->state) }}" required></label>
        <label>Postcode<input name="postcode" value="{{ old('postcode', $crewProfile->postcode) }}" required></label>
        <label>Emergency contact<input name="emergency_contact_name" value="{{ old('emergency_contact_name', $crewProfile->emergency_contact_name) }}"></label>
        <label>Relationship<input name="emergency_contact_relationship" value="{{ old('emergency_contact_relationship', $crewProfile->emergency_contact_relationship) }}"></label>
        <label>Emergency contact phone<input name="emergency_contact_phone" value="{{ old('emergency_contact_phone', $crewProfile->emergency_contact_phone) }}"></label>
        <label class="wide">Dietary requirements<textarea name="dietary_requirements">{{ old('dietary_requirements', $crewProfile->dietary_requirements) }}</textarea></label>
        <label class="wide">Medical information and allergies<textarea name="medical_information">{{ old('medical_information', $crewProfile->medical_information) }}</textarea></label>
    </div></section>

    <section class="card profile-section"><h2>Payment details</h2><p class="muted">These details are encrypted before they are stored.</p><div class="profile-grid">
        <label>ABN<input name="abn" value="{{ old('abn', $crewProfile->abn) }}"></label>
        <label>Bank account name<input name="bank_account_name" value="{{ old('bank_account_name', $crewProfile->bank_account_name) }}"></label>
        <label>Bank<input name="bank_name" value="{{ old('bank_name', $crewProfile->bank_name) }}" placeholder="e.g. Commonwealth Bank"></label>
        <label>BSB<input name="bank_bsb" value="{{ old('bank_bsb', $crewProfile->bank_bsb) }}"></label>
        <label>Account number<input name="bank_account_number" value="{{ old('bank_account_number', $crewProfile->bank_account_number) }}"></label>
    </div></section>

    <section class="card profile-section"><h2>Working With Children Check</h2><p class="muted">This information is important for confirming your eligibility for crew work.</p><div class="profile-grid">
        <label>WWCC number<input name="working_with_children_number" value="{{ old('working_with_children_number', $crewProfile->working_with_children_number) }}" required></label>
        <label>Expiry date<input type="date" name="working_with_children_expiry" value="{{ old('working_with_children_expiry', $crewProfile->working_with_children_expiry?->toDateString()) }}" required></label>
    </div></section>

    <section class="card profile-section"><h2>Vehicles</h2><p class="muted">Add the vehicles you may use for DancePro work. Leave an unused row blank.</p>
        @for($index = 0; $index < max(3, count($vehicleRows) + 1); $index++)
        @php($vehicle = $vehicleRows[$index] ?? [])
        <div class="vehicle-row"><input type="hidden" name="vehicles[{{ $index }}][uuid]" value="{{ data_get($vehicle, 'uuid') }}"><label>Make<input name="vehicles[{{ $index }}][make]" value="{{ data_get($vehicle, 'make') }}"></label><label>Model<input name="vehicles[{{ $index }}][model]" value="{{ data_get($vehicle, 'model') }}"></label><label>Registration<input name="vehicles[{{ $index }}][registration]" value="{{ data_get($vehicle, 'registration') }}"></label><label>Colour<input name="vehicles[{{ $index }}][colour]" value="{{ data_get($vehicle, 'colour') }}"></label><label class="wide">Notes<input name="vehicles[{{ $index }}][notes]" value="{{ data_get($vehicle, 'notes') }}"></label></div>
        @endfor
    </section>

    <section class="card profile-section"><h2>Contracts</h2>
        @forelse($contracts as $contract)@php($signature = $signaturesByContract->get($contract->id))<div class="contract-row"><div><strong>{{ $contract->name }}</strong><span class="muted">Version {{ $contract->version }}@if($contract->effective_from) · effective {{ $contract->effective_from->format('j M Y') }}@endif</span></div><div style="display:flex;align-items:center;gap:8px">@if($signature?->status?->value === 'signed')<span class="status-pill done" title="{{ $signature->recording_method?->value === 'digital' ? 'Signed electronically by you' : 'Existing signature recorded by DancePro' }}">✓ Signed {{ $signature->signed_at?->format('j M Y') }}</span>@else<span class="status-pill attention">Signature required</span>@endif<a class="button secondary" href="{{ route('crew.contracts.show', $contract) }}">{{ $signature?->status?->value === 'signed' ? 'View' : 'Review and sign' }}</a></div></div>@empty<p class="muted">There are no active contracts to display.</p>@endforelse
    </section>

    <div class="profile-save"><button type="submit">Save my profile</button></div>
</form>

@endsection

@push('styles')
<style>
    .profile-account-actions { display:flex; align-items:center; gap:8px; }
    .profile-account-actions form { margin:0; }
    .account-dialog { width:min(92vw,480px); max-width:none; padding:22px; border:0; border-radius:10px; box-shadow:0 20px 60px rgba(0,0,0,.28); }
    .account-dialog::backdrop { background:rgba(3,24,35,.72); }
    .account-dialog-heading { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:18px; }
    .account-dialog-heading p { margin-bottom:0; }
    .profile-badges { display:flex; flex-wrap:wrap; align-items:center; gap:16px; margin-bottom:16px; padding:20px; overflow:visible; }
    .profile-badge { position:relative; display:block; width:88px; height:88px; padding:0; border:0; background:transparent; color:inherit; cursor:help; text-decoration:none; }
    .profile-badge:hover,.profile-badge:focus-visible { z-index:3; text-decoration:none; outline:none; }
    .profile-badge-roundel { position:relative; display:block; width:88px; height:88px; border-radius:50%; background:#fff; box-shadow:0 5px 14px rgba(7,54,72,.22); transition:transform .15s ease,box-shadow .15s ease; }
    .profile-badge-roundel img { display:block; width:100%; height:100%; }
    .profile-badge-centre { position:absolute; inset:21px; display:grid; place-items:center; border:2px solid #fff; border-radius:50%; background:#082b38; box-shadow:0 1px 5px rgba(0,0,0,.24); color:#fff; font-size:24px; font-weight:900; line-height:1; }
    .profile-badge.milestones .profile-badge-centre { background:#f3b51b; color:#102d38; font-size:21px; }
    .profile-badge.recognition .profile-badge-centre { background:#804bc7; }
    .profile-badge.design-dp-blue .profile-badge-centre { background:#087ca6; }
    .profile-badge.design-gold-star .profile-badge-centre { background:#f3b51b; color:#102d38; }
    .profile-badge.design-purple-heart .profile-badge-centre { background:#804bc7; }
    .profile-badge.design-teal-compass .profile-badge-centre { background:#159c9c; }
    .profile-badge.design-red-bolt .profile-badge-centre { background:#d94343; }
    .profile-badge.design-green-shield .profile-badge-centre { background:#249b68; }
    .profile-badge.design-midnight-crown .profile-badge-centre { background:#082b38; }
    .profile-badge.design-rainbow .profile-badge-centre { background:conic-gradient(#ef5350,#ffca28,#45b86b,#23a9d5,#7857c8,#ef5350); }
    .profile-badge.rewards .profile-badge-centre { background:#249b68; }
    .profile-badge:hover .profile-badge-roundel,.profile-badge:focus-visible .profile-badge-roundel { transform:translateY(-3px) scale(1.06); box-shadow:0 8px 17px rgba(7,54,72,.28); }
    .profile-badge:focus-visible .profile-badge-roundel { outline:3px solid #f3b51b; outline-offset:3px; }
    .profile-badge-tooltip { position:absolute; bottom:calc(100% + 10px); left:50%; display:none; width:220px; padding:11px 13px; border-radius:7px; background:#101820; box-shadow:0 10px 25px rgba(16,24,32,.24); color:#fff; text-align:left; transform:translateX(-50%); }
    .profile-badge-tooltip::after { position:absolute; top:100%; left:50%; border:7px solid transparent; border-top-color:#101820; content:""; transform:translateX(-50%); }
    .profile-badge-tooltip small { display:block; margin-bottom:3px; color:#82d7f7; font-size:9px; font-weight:900; letter-spacing:.1em; text-transform:uppercase; }
    .profile-badge-tooltip strong,.profile-badge-tooltip span { display:block; }
    .profile-badge-tooltip span { margin-top:3px; color:#d1dde2; font-size:12px; }
    .profile-badge:hover .profile-badge-tooltip,.profile-badge:focus-visible .profile-badge-tooltip { display:block; }
    @media(max-width:620px) { .profile-account-actions { width:100%; margin-top:12px; } .profile-account-actions > * { flex:1; } .profile-account-actions button { width:100%; } .profile-badges { gap:12px; padding:15px; } .profile-badge,.profile-badge-roundel { width:76px; height:76px; } .profile-badge-centre { inset:18px; font-size:21px; } .profile-badge.milestones .profile-badge-centre { font-size:18px; } .profile-badge-tooltip { left:0; transform:none; } .profile-badge-tooltip::after { left:38px; } }
</style>
@endpush

@push('scripts')
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
    const passwordDialog = document.getElementById('password-dialog');
    document.getElementById('open-password-dialog')?.addEventListener('click', () => passwordDialog.showModal());
    document.getElementById('close-password-dialog')?.addEventListener('click', () => passwordDialog.close());
    passwordDialog?.addEventListener('click', event => { if (event.target === passwordDialog) passwordDialog.close(); });
    if (passwordDialog?.dataset.openOnLoad === '1') passwordDialog.showModal();
</script>
@endpush

@if(config('services.google_maps.browser_key'))
@push('scripts')
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
    window.initialiseCrewAddressSearch = async function () {
        const mount = document.getElementById('crew-address-search');
        if (!mount) return;

        const { PlaceAutocompleteElement } = await google.maps.importLibrary('places');
        const autocomplete = new PlaceAutocompleteElement({ includedPrimaryTypes: ['street_address'] });
        autocomplete.includedRegionCodes = ['au'];
        autocomplete.setAttribute('placeholder', 'Start typing your street address');
        mount.replaceChildren(autocomplete);

        autocomplete.addEventListener('gmp-select', async (event) => {
            const place = event.placePrediction.toPlace();
            await place.fetchFields({ fields: ['addressComponents'] });
            const components = place.addressComponents ?? [];
            const part = (type, short = false) => {
                const component = components.find(item => item.types.includes(type));
                return component ? (short ? component.shortText : component.longText) : '';
            };
            const street = [part('street_number'), part('route')].filter(Boolean).join(' ');
            const suburb = part('locality') || part('postal_town') || part('sublocality_level_1');

            document.querySelector('[name="address_line_1"]').value = street;
            document.querySelector('[name="suburb"]').value = suburb;
            document.querySelector('[name="state"]').value = part('administrative_area_level_1', true);
            document.querySelector('[name="postcode"]').value = part('postal_code');
        });
    };
</script>
<script nonce="{{ request()->attributes->get('csp_nonce') }}" async src="https://maps.googleapis.com/maps/api/js?key={{ urlencode(config('services.google_maps.browser_key')) }}&amp;loading=async&amp;callback=initialiseCrewAddressSearch"></script>
@endpush
@endif
