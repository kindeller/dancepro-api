@extends('layouts.crew', ['title' => $crewContract->name])

@section('content')
<div class="page-heading"><div><p class="eyebrow">Crew contract</p><h1>{{ $crewContract->name }}</h1><p class="muted">Version {{ $crewContract->version }}@if($crewContract->effective_from) · effective {{ $crewContract->effective_from->format('j M Y') }}@endif</p></div><a class="button secondary" href="{{ route('crew.profile.edit') }}">Back to profile</a></div>

<section class="card profile-section contract-document">{!! $crewContract->content !!}</section>

@if($signature?->status?->value === 'signed')
<section class="card profile-section"><h2>Signature recorded</h2><p><strong>{{ $signature->signed_name ?: (auth()->user()->crewProfile?->legal_name ?: auth()->user()->name) }}</strong></p><p class="muted">Signed {{ $signature->signed_at?->format('j M Y, g:i a') }} · {{ $signature->recording_method?->value === 'digital' ? 'Electronically signed in the Crew Hub' : 'Existing signature recorded by DancePro' }}</p></section>
@else
<section class="card profile-section"><h2>Sign this contract</h2><p class="muted">Typing your name and confirming your password identifies you as the person signing.</p><form method="POST" action="{{ route('crew.contracts.sign', $crewContract) }}" class="profile-grid">@csrf
    <label>Full legal name<input name="signed_name" value="{{ old('signed_name', auth()->user()->crewProfile?->legal_name) }}" required autocomplete="name"></label>
    <label>DancePro password<input type="password" name="password" required autocomplete="current-password"></label>
    <label class="wide" style="flex-direction:row;align-items:flex-start"><input type="checkbox" name="accept_contract" value="1" required style="width:auto"> <span>I have read and agree to this contract and consent to signing it electronically.</span></label>
    <div class="wide"><button type="submit">Sign contract</button></div>
</form></section>
@endif
@endsection
