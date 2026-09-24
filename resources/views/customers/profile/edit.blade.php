@extends('layouts.public', ['title' => 'My Profile', 'footerLabel' => 'DancePro Media'])
@section('content')
<section class="hero"><div class="container"><h1>My Profile</h1><p class="lead">Your details, dancers, studios and preferences.</p></div></section>
<section class="section"><div class="container">
    <nav aria-label="Profile sections" style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:24px">
        <a href="{{ route('customer.media.index') }}">← My Media</a><a href="#details">My details</a><a href="#dancers">My dancers</a><a href="#studios">My studios</a><a href="#preferences">Email preferences</a>
    </nav>
    @if(session('status'))<p role="status" class="card card-body">{{ session('status') }}</p>@endif
    @if($errors->any())<div role="alert" class="card card-body" style="border-color:#b42318;margin-bottom:20px"><strong>Please check your changes.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,360px),1fr));gap:24px;align-items:start">
        <section id="details" class="card card-body">
            <h2>My details</h2>
            <form method="POST" action="{{ route('customer.profile.store') }}" style="display:grid;gap:14px">@csrf
                <input type="hidden" name="action" value="details">
                <label>Name<input name="name" required maxlength="255" autocomplete="name" value="{{ old('action') === 'details' ? old('name') : $account->name }}"></label>
                <label>Preferred name <span class="meta">(optional)</span><input name="preferred_name" maxlength="100" autocomplete="nickname" value="{{ old('action') === 'details' ? old('preferred_name') : $profile?->preferred_name }}"></label>
                <label>Phone <span class="meta">(optional)</span><input name="phone" type="tel" maxlength="40" autocomplete="tel" value="{{ old('action') === 'details' ? old('phone') : $profile?->phone }}"></label>
                <div><span class="meta">Account email</span><p style="overflow-wrap:anywhere;margin:6px 0">{{ $account->email }}</p><p class="meta">Email changes will be available once address verification is ready.</p></div>
                <div><button class="button">Save details</button></div>
            </form>
        </section>
        <section id="preferences" class="card card-body">
            <h2>Email preferences</h2>
            <form method="POST" action="{{ route('customer.profile.store') }}" style="display:grid;gap:16px">@csrf
                <input type="hidden" name="action" value="preferences">
                @foreach(['media_ready_email' => 'Let me know when my media is ready', 'expiry_reminders_email' => 'Remind me before my downloads expire', 'marketing_email' => 'Send me DancePro news and offers'] as $key => $label)
                    @php($enabled = old('action') === 'preferences' ? old($key, false) : ($key === 'marketing_email' ? $profile?->marketing_consent_at !== null : ($profile?->preferences[$key] ?? false)))
                    <input type="hidden" name="{{ $key }}" value="0">
                    <label style="display:flex;gap:10px;align-items:start"><input type="checkbox" name="{{ $key }}" value="1" @checked($enabled)>{{ $label }}</label>
                @endforeach
                <p class="meta">Your choices are saved here. Media-ready and expiry emails will start when those notifications become available.</p>
                <div><button class="button">Save preferences</button></div>
            </form>
        </section>
        <section id="dancers" class="card card-body">
            <h2>My dancers</h2><p>Add yourself or the dancers whose routines you want to find.</p>
            @forelse($dancers as $dancer)
                <details style="border-top:1px solid var(--line);padding:14px 0" @if(old('dancer_uuid') === $dancer->uuid) open @endif>
                    <summary style="cursor:pointer"><strong>{{ $dancer->name }}</strong> <span class="meta">· Edit</span></summary>
                    @php($editing = old('dancer_uuid') === $dancer->uuid)
                    <form method="POST" action="{{ route('customer.profile.store') }}" style="display:grid;gap:12px;margin-top:14px">@csrf
                        <input type="hidden" name="action" value="dancer_update"><input type="hidden" name="dancer_uuid" value="{{ $dancer->uuid }}">
                        <label>Dancer name<input name="name" value="{{ $editing ? old('name', $dancer->name) : $dancer->name }}" required maxlength="255"></label>
                        <label>This dancer is<select name="relationship">@foreach(['unspecified' => 'Prefer not to say', 'self' => 'Me', 'child' => 'My child', 'other' => 'Someone else'] as $value => $label)<option value="{{ $value }}" @selected(($editing ? old('relationship', $dancer->relationship) : $dancer->relationship) === $value)>{{ $label }}</option>@endforeach</select></label>
                        @php($aliases = $editing ? old('programme_names', $dancer->programme_names ?? []) : ($dancer->programme_names ?? []))
                        <label>Other names used in programmes <span class="meta">(optional)</span><textarea name="programme_names" rows="3" maxlength="2560">{{ is_array($aliases) ? implode("\n", array_filter($aliases, 'is_string')) : '' }}</textarea></label>
                        <p class="meta">One name per line, up to 10. These names will help identify routines when personal filters are available.</p>
                        <div><button class="button">Save dancer</button></div>
                    </form>
                    <form method="POST" action="{{ route('customer.profile.store') }}" style="margin-top:12px">@csrf<input type="hidden" name="action" value="dancer_remove"><input type="hidden" name="dancer_uuid" value="{{ $dancer->uuid }}"><button class="button secondary">Remove dancer from profile</button></form>
                </details>
            @empty<p>No dancers added yet.</p>@endforelse
            <form method="POST" action="{{ route('customer.profile.store') }}" style="border-top:1px solid var(--line);padding-top:16px;margin-top:12px">@csrf
                <input type="hidden" name="action" value="dancer">
                <label>Dancer name<input name="name" required maxlength="255" value="{{ old('action') === 'dancer' ? old('name') : '' }}"></label>
                <button class="button" style="margin-top:16px">Add dancer</button>
            </form>
            <p class="meta">Removing a dancer does not remove your media or purchases.</p>
        </section>
        <section id="studios" class="card card-body">
            <h2>My studios</h2>
            @forelse($registeredStudios as $studio)
                <div style="border-top:1px solid var(--line);padding:14px 0">
                    <strong>{{ $studio->name }}</strong>@unless(in_array($studio->id, $verifiedStudioIds, true))<span class="meta"> · Gallery code needed</span>@endunless
                    @if($studio->status->value !== 'active')<span class="meta"> · Currently unavailable</span>@endif
                    @if(in_array($studio->id, $communicationStudioIds, true))<p style="margin:8px 0"><a href="{{ route('customer.studio-communications.index', $studio) }}">View studio emails →</a></p>@endif
                    <form method="POST" action="{{ route('customer.profile.store') }}" style="margin-top:8px">@csrf<input type="hidden" name="action" value="studio_remove"><input type="hidden" name="studio_uuid" value="{{ $studio->uuid }}"><button class="button secondary">Remove studio from profile</button></form>
                </div>
            @empty<p>No studios added yet.</p>@endforelse
            <form method="POST" action="{{ route('customer.profile.store') }}">@csrf
                <input type="hidden" name="action" value="studio">
                <label>Studio<select name="studio_uuid" required><option value="">Select your studio</option>@foreach($availableStudios as $studio)<option value="{{ $studio->uuid }}" @selected(old('action') === 'studio' && old('studio_uuid') === $studio->uuid)>{{ $studio->name }}</option>@endforeach</select></label>
                <label>Studio gallery code<input type="password" name="studio_gallery_code" required maxlength="64" autocomplete="off"></label>
                <p class="meta">Enter the gallery code supplied by your studio. Concert video codes are entered separately in My Media.</p>
                @error('studio_gallery_code')<p role="alert">{{ $message }}</p>@enderror
                <button class="button" style="margin-top:16px">Add or verify studio</button>
            </form>
            <p class="meta">You can add more than one studio. Removing a studio leaves previously added media and purchases in your account.</p>
        </section>
    </div>
</div></section>
@endsection
