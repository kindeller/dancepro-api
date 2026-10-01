@extends('layouts.public', ['title' => 'My Profile', 'footerLabel' => 'DancePro Media'])
@push('styles')
<style>
    .profile-page { width:100%;max-width:none; }
    .profile-form { display:grid;gap:24px; }
    .profile-fieldset { min-width:0;margin:0;padding:20px 0 0;border:0;border-top:1px solid var(--line); }
    .profile-fieldset legend { padding:0 12px 0 0;font-size:16px;font-weight:800; }
    .profile-fields { display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px 24px; }
    .profile-fields.names { grid-template-columns:repeat(3,minmax(0,1fr)); }
    .profile-fields>label { min-width:0; }
    .profile-full-row { grid-column:1/-1; }
    .profile-form-actions { border-top:1px solid var(--line);padding-top:20px; }
    .profile-preference { display:flex;align-items:center;justify-content:flex-start;gap:12px;padding:20px 0;border-bottom:1px solid var(--line); }
    .profile-preference:first-of-type { border-top:1px solid var(--line); }
    .profile-preference span { font-size:16px;font-weight:650;color:var(--ink); }
    @media(max-width:700px) { .profile-fields,.profile-fields.names { grid-template-columns:1fr; } .profile-preference { gap:16px; } }
    .profile-page>.card { margin:0; }
    .profile-tabs .inbox-badge { margin-left:0;margin-right:7px; }
    .profile-section-heading { display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap; }
    .profile-section-status { flex-shrink:0;padding:5px 10px;border-radius:20px;font-size:13px;font-weight:800; }
    .profile-section-status.complete { background:#e4f7ec;color:#147a52; }
    .profile-section-status.missing { background:#ffeded;color:#b42318; }
    .profile-studio-row { display:flex;align-items:center;gap:18px;border-top:1px solid var(--line);padding:20px 0; }
    .profile-studio-logo { width:90px;height:90px;flex:0 0 90px;object-fit:contain;border-radius:8px;background:#f4f8fa; }
    .profile-studio-info { min-width:0;flex:1; }
    .profile-studio-info strong { display:block;font-size:18px; }
    .profile-studio-manager { border-top:1px solid var(--line);padding-top:16px; }
    .profile-studio-manager summary { cursor:pointer;font-weight:750;color:#087fab; }
    .profile-studio-manager form { display:grid;gap:14px;margin-top:18px; }
    .profile-studio-manager .studio-remove { margin:0 0 14px; }
    @media(max-width:480px) { .profile-studio-logo { width:64px;height:64px;flex-basis:64px; } .profile-studio-row { gap:12px; } }
</style>
@endpush
@section('content')
<section class="hero"><div class="container">@include('customers._heading', ['heading' => 'My Profile', 'description' => 'Your details, family, studio and preferences.'])</div></section>
<nav class="section-tabs profile-tabs" aria-label="Profile sections"><div class="container">
        @foreach(['details' => 'My details', 'family' => 'My Family', 'studios' => 'My studio', 'preferences' => 'Email preferences'] as $key => $label)
            @php($missing = match($key) { 'family' => $customerProfileCounts['family'] ? 'Dancer not added' : null, 'studios' => $customerProfileCounts['studios'] ? 'Studio not added' : null, default => null })
            <a href="{{ route('customer.profile.edit', ['tab' => $key]) }}" @if($profileTab === $key) aria-current="page" @endif>@if($missing)<span class="inbox-badge" aria-label="1 action required: {{ $missing }}" title="{{ $missing }}">1</span>@endif{{ $label }}</a>
        @endforeach
</div></nav>
<section class="section"><div class="container">
    <form method="POST" action="{{ route('logout') }}" style="display:flex;justify-content:flex-end;margin-bottom:20px">@csrf<button class="button secondary" type="submit">Sign out</button></form>
    @if(session('status'))<p role="status" class="card card-body">{{ session('status') }}</p>@endif
    @if($errors->any())<div role="alert" class="card card-body" style="border-color:#b42318;margin-bottom:20px"><strong>Please check your changes.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <p class="meta"><a href="{{ route('customer.media.help') }}#article-guide-find-dancer-routines">Help finding your dancers and studios</a> · <a href="{{ route('account.security') }}">Account security</a></p>
    <div class="profile-page {{ $profileTab === 'family' ? 'profile-family' : '' }}">
        @if($profileTab === 'details')
        <section id="details" class="card card-body">
            <h2>My details</h2>
            <form method="POST" action="{{ route('customer.profile.store', ['tab' => 'details']) }}" class="profile-form">@csrf
                <input type="hidden" name="action" value="details">
                <fieldset class="profile-fieldset"><legend>Your name</legend><div class="profile-fields names">
                <label>First name<input name="first_name" required maxlength="255" autocomplete="given-name" value="{{ old('action') === 'details' ? old('first_name') : $account->first_name }}"></label>
                <label>Last name<input name="last_name" required maxlength="255" autocomplete="family-name" value="{{ old('action') === 'details' ? old('last_name') : $account->last_name }}"></label>
                <label>Preferred name<input name="preferred_name" required maxlength="100" autocomplete="nickname" value="{{ old('action') === 'details' ? old('preferred_name') : ($profile?->preferred_name ?: $account->first_name) }}"></label>
                </div></fieldset>
                <fieldset class="profile-fieldset"><legend>Contact details</legend><div class="profile-fields">
                <label>Phone<input name="phone" required type="tel" maxlength="40" autocomplete="tel" value="{{ old('action') === 'details' ? old('phone') : $profilePhone }}"></label>
                <label>Account email<input name="email" type="email" required maxlength="255" autocomplete="email" value="{{ old('action') === 'details' ? old('email') : $account->email }}" data-account-email data-current-email="{{ $account->email }}"></label>
                <div class="profile-full-row" data-email-confirmation hidden>
                    <label>Current password<input type="password" name="current_password" autocomplete="current-password" data-email-password></label>
                    <p class="meta">Confirm your password to change your email. We'll email the new address a confirmation link. Keep signing in with your current address until you confirm the change.</p>
                </div>
                @if($account->pending_email)<p role="status" class="meta profile-full-row">Awaiting confirmation of <strong>{{ $account->pending_email }}</strong>. Check that inbox and junk/spam folders. Your current email stays active until confirmed. To request a fresh link, enter the new address again and save.</p>@endif
                </div></fieldset>
                <div class="profile-form-actions"><button class="button">Save details</button></div>
            </form>
        </section>
        @elseif($profileTab === 'preferences')
        <section id="preferences" class="card card-body">
            <h2>Email preferences</h2>
            <form method="POST" action="{{ route('customer.profile.store', ['tab' => 'preferences']) }}" class="profile-preferences-form">@csrf
                <input type="hidden" name="action" value="preferences">
                @foreach(['media_ready_email' => 'Let me know when my media is ready', 'expiry_reminders_email' => 'Remind me before my downloads expire', 'marketing_email' => 'Send me DancePro news and offers'] as $key => $label)
                    @php($enabled = old('action') === 'preferences' ? old($key, false) : $emailPreferences[$key])
                    <input type="hidden" name="{{ $key }}" value="0">
                    <label class="profile-preference"><input type="checkbox" name="{{ $key }}" value="1" @checked($enabled)><span>{{ $label }}</span></label>
                @endforeach
                <p class="meta">Untick any emails you don’t want, then save your preferences. You can change these choices at any time.</p>
                <div><button class="button">Save preferences</button></div>
            </form>
        </section>
        @elseif($profileTab === 'family')
        @include('customers.family.panel')
        @elseif($profileTab === 'studios')
        <section id="studios" class="card card-body">
            @php($studioPending = $registeredStudios->isEmpty() && $studioRequests->contains('status', 'pending'))
            <div class="profile-section-heading"><h2>My studio</h2><span class="profile-section-status {{ $studioPending ? 'pending' : ($registeredStudios->isEmpty() ? 'missing' : 'complete') }}" role="status">{{ $studioPending ? 'Awaiting review' : ($registeredStudios->isEmpty() ? 'Studio not added' : 'Studio added') }}</span></div>
            <p>Add your dance studio to receive updates about your media and access your studio's competition and concert photos and videos to view and order. Event access codes and purchases may still be required.</p>
            @forelse($registeredStudios as $studio)
                <div class="profile-studio-row">
                    @if($studio->logoUrl())<img class="profile-studio-logo" src="{{ $studio->logoUrl() }}" alt="{{ $studio->name }} logo">@else<div class="profile-studio-logo" aria-hidden="true" style="display:grid;place-items:center;font-weight:800;color:#087fab">{{ mb_substr($studio->name, 0, 1) }}</div>@endif
                    <div class="profile-studio-info"><strong>{{ $studio->name }}</strong>
                    @if(in_array($studio->id, $familyStudioIds ?? [], true))<p class="meta">Added through your family account manager.</p>@endif
                    @if(in_array($studio->id, $verifiedStudioIds, true))
                        <p class="meta">Released concert photo galleries are included with your studio access.</p>
                    @else<p class="meta">Confirm your studio code under Add/change studio to access its concert photos.</p>@endif
                    @if($studio->status->value !== 'active')<p class="meta">Currently unavailable</p>@endif
                    @if(in_array($studio->id, $communicationStudioIds, true))<p style="margin:8px 0"><x-studio-hub-link :href="route('customer.studio-communications.index', $studio)" /></p>@endif
                    </div>
                </div>
            @empty<p>No studio added yet.</p>@endforelse
            @foreach($studioRequests as $submission)
                <div class="profile-studio-row"><div class="profile-studio-info"><strong>{{ $submission->name }}</strong><p class="meta">{{ $submission->location }}</p><span class="profile-section-status {{ $submission->status === 'pending' ? 'pending' : 'missing' }}">{{ $submission->status === 'pending' ? 'Awaiting review' : 'Not approved' }}</span>
                <p class="meta">{{ $submission->status === 'pending' ? 'Your request is saved. DancePro will confirm the studio and attach it to your account.' : $submission->review_note }}</p></div></div>
            @endforeach
            <details class="profile-studio-manager" @if($registeredStudios->isEmpty() || in_array(old('action'), ['studio', 'studio_remove', 'studio_request', 'studio_gallery'], true)) open @endif>
                <summary>Add/change studio</summary>
                @include('customers.profile._studio-search')
                @if($registeredStudios->isNotEmpty())
                    <p class="meta">To change studios, add your new studio, then remove the old one below. Existing media and purchases stay in your account.</p>
                    @foreach($registeredStudios as $studio)
                    @continue(!in_array($studio->id, $ownStudioIds ?? [], true))
                    <form class="studio-remove" method="POST" action="{{ route('customer.profile.store', ['tab' => 'studios']) }}">@csrf<input type="hidden" name="action" value="studio_remove"><input type="hidden" name="studio_uuid" value="{{ $studio->uuid }}"><div><button class="button secondary">Remove {{ $studio->name }}</button></div></form>
                    @endforeach
                @endif
            </details>
        </section>
        @endif
    </div>
</div></section>
@endsection

@push('scripts')
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
(() => {
    const email = document.querySelector('[data-account-email]');
    const confirmation = document.querySelector('[data-email-confirmation]');
    const password = document.querySelector('[data-email-password]');
    if (!email || !confirmation || !password) return;
    const update = () => {
        const changed = email.value.trim().toLowerCase() !== email.dataset.currentEmail.toLowerCase();
        confirmation.hidden = !changed;
        password.required = changed;
        password.disabled = !changed;
        if (!changed) password.value = '';
    };
    email.addEventListener('input', update);
    update();
})();
</script>
@endpush
