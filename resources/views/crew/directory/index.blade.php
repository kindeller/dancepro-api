@extends('layouts.crew', ['title' => 'My Crew'])

@section('content')
<div class="page-heading"><div><div class="crew-hub-brand"><img src="{{ asset('images/brand/dancepro-icon-blue.png') }}" alt=""><span>CREW HUB</span></div><h1>My Crew</h1><p class="muted">Crew, competition and studio contacts in one place.</p></div></div>

<nav class="filter-tabs main-shift-menu" aria-label="Contact directory">
    <a class="{{ $directory === 'crew' ? 'active' : '' }}" href="{{ route('crew.directory.index',['view'=>'crew']) }}">Crew</a>
    <a class="{{ $directory === 'competitions' ? 'active' : '' }}" href="{{ route('crew.directory.index',['view'=>'competitions']) }}">Competitions</a>
    <a class="{{ $directory === 'studios' ? 'active' : '' }}" href="{{ route('crew.directory.index',['view'=>'studios']) }}">Studios</a>
</nav>

@if($directory === 'crew')
<section><div class="section-heading"><div><p class="eyebrow">DancePro team</p><h2>Crew directory</h2></div><span class="count-pill">{{ $crew->count() }}</span></div><div class="directory-list">
@forelse($crew as $profile)
    @php
        $phoneLink = preg_replace('/[^0-9+]/', '', $profile->phone ?? '');
    @endphp
    <article class="card directory-row"><div class="directory-primary"><strong>{{ $profile->preferred_name ?: $profile->user->name }}</strong><span class="muted">Crew member</span></div><div class="directory-contact">@if($profile->phone)<a href="tel:{{ $phoneLink }}">{{ $profile->phone }}</a>@else<span class="muted">No phone number</span>@endif</div><form method="POST" action="{{ route('crew.chat.start') }}">@csrf<input type="hidden" name="recipient_profile_uuid" value="{{ $profile->uuid }}"><button class="directory-action" type="submit">Message in My Chat</button></form></article>
@empty<div class="card empty-state"><strong>No crew contacts found.</strong></div>@endforelse
</div></section>
@endif

@if($directory === 'competitions')
<section><div class="section-heading"><div><p class="eyebrow">Event contacts</p><h2>Competitions</h2></div><span class="count-pill">{{ $competitions->count() }}</span></div><div class="directory-list">
@forelse($competitions as $competition)
    @php
        $phoneLink = preg_replace('/[^0-9+]/', '', $competition->organiser_phone ?? '');
        $logoUrl = $competition->logoUrl();
    @endphp
    <article class="card directory-row"><div class="directory-primary directory-primary-with-logo">@if($logoUrl)<img class="directory-logo" src="{{ $logoUrl }}" alt="{{ $competition->name }} logo">@else<div class="directory-logo directory-logo-placeholder" aria-hidden="true">{{ str($competition->code ?: $competition->name)->substr(0, 2)->upper() }}</div>@endif<div><strong>{{ $competition->name }}</strong>@if($competition->code)<span class="muted">{{ $competition->code }}</span>@endif</div></div><div class="directory-contact"><span>{{ $competition->organiser_name ?: 'Organiser not added' }}</span>@if($competition->organiser_phone)<a href="tel:{{ $phoneLink }}">{{ $competition->organiser_phone }}</a>@else<span class="muted">No phone number</span>@endif</div></article>
@empty<div class="card empty-state"><strong>No competitions found.</strong></div>@endforelse
</div></section>
@endif

@if($directory === 'studios')
<section><div class="section-heading"><div><p class="eyebrow">Concert contacts</p><h2>Studios</h2></div><span class="count-pill">{{ $studios->count() }}</span></div><div class="directory-list">
@forelse($studios as $studio)
    <article class="card directory-row"><div class="directory-primary directory-primary-with-logo">@if($studio->logo_path)<img class="directory-logo" src="{{ $studio->logoUrl() }}" alt="{{ $studio->name }} logo">@else<div class="directory-logo directory-logo-placeholder" aria-hidden="true">{{ str($studio->code ?: $studio->name)->substr(0, 2)->upper() }}</div>@endif<div><strong>{{ $studio->name }}</strong>@if($studio->code)<span class="muted">{{ $studio->code }}</span>@endif</div></div><div class="directory-contact">@forelse($studio->contacts as $contact)@php($phoneLink = preg_replace('/[^0-9+]/', '', $contact->phone ?? ''))<div><span>{{ $contact->name }}@if($contact->role) · {{ $contact->role }}@endif</span>@if($contact->phone)<a href="tel:{{ $phoneLink }}">{{ $contact->phone }}</a>@else<span class="muted">No phone number</span>@endif</div>@empty @php($phoneLink = preg_replace('/[^0-9+]/', '', $studio->contact_phone ?? ''))<div><span>{{ $studio->contact_name ?: 'Contact not added' }}</span>@if($studio->contact_phone)<a href="tel:{{ $phoneLink }}">{{ $studio->contact_phone }}</a>@else<span class="muted">No phone number</span>@endif</div>@endforelse</div></article>
@empty<div class="card empty-state"><strong>No studios found.</strong></div>@endforelse
</div></section>
@endif
@endsection

@push('styles')
<style>
    .directory-primary-with-logo { flex-direction: row; align-items: center; gap: 12px; }
    .directory-primary-with-logo > div:last-child { display: flex; min-width: 0; flex-direction: column; }
    .directory-contact > div { display: flex; min-width: 0; flex-direction: column; }
    .directory-logo { width: 64px; height: 44px; flex: 0 0 64px; border: 1px solid #dbe3ea; border-radius: 8px; background: #fff; object-fit: contain; }
    .directory-logo-placeholder { display: grid; place-items: center; background: #eef3f6; color: #71808b; font-size: 11px; font-weight: 800; letter-spacing: .08em; }
</style>
@endpush
