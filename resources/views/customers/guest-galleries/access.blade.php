@extends('layouts.public', ['title' => $galleryTitle, 'footerLabel' => 'Guest gallery access'])

@section('content')
<section class="hero"><div class="container">@include('customers._heading', ['headingImageUrl' => $galleryType === 'competition' ? ($event->logoUrl() ?? $event->crewLogoUrl()) : $studio->logoUrl(), 'eyebrow' => $galleryType === 'concert' ? 'Studio galleries' : 'Competition gallery', 'heading' => $galleryTitle, 'description' => $galleryType === 'concert' ? 'Enter your email and studio access code once to browse all released concert photo galleries from this studio.' : 'Enter your email and the gallery access code. You can browse before deciding whether to create an account.'])</div></section>
<section class="section"><div class="card unlock">
    <p><a href="{{ route('guest-galleries.index', ['type' => $galleryType]) }}">← All {{ $galleryType }} galleries</a></p>
    @if($errors->any())<p class="error" role="alert">{{ $errors->first() }}</p>@endif
    <form method="post" action="{{ $galleryType === 'competition' ? route('guest-galleries.competitions.unlock', $event) : route('guest-galleries.studios.unlock', $studio) }}">@csrf
        <label>Email address<input type="email" name="email" value="{{ old('email', $prefillEmail) }}" autocomplete="email" maxlength="255" required></label>
        <label>{{ $galleryType === 'concert' ? 'Studio access code' : 'Gallery access code' }}<input type="password" name="login_code" autocomplete="one-time-code" maxlength="64" required></label>
        <button class="button" type="submit">{{ $galleryType === 'concert' ? 'Open studio galleries' : 'Open gallery' }}</button>
    </form>
    <p class="muted" style="margin-top:18px">{{ $galleryType === 'concert' ? 'Use the code supplied by your studio. Access lasts up to 30 days in this browser; only released photo galleries are available.' : 'Your code may have been supplied by the competition organiser.' }}</p>
</div></section>
@endsection
