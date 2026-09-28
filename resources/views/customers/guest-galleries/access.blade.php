@extends('layouts.public', ['title' => $galleryTitle, 'footerLabel' => 'Guest gallery access'])

@section('content')
<section class="hero"><div class="container"><div class="eyebrow">{{ ucfirst($galleryType) }} gallery</div><h1>{{ $galleryTitle }}</h1><p class="lead">Enter your email and the gallery access code. You can browse before deciding whether to create an account.</p></div></section>
<section class="section"><div class="card unlock">
    <p><a href="{{ route('guest-galleries.index', ['type' => $galleryType]) }}">← All {{ $galleryType }} galleries</a></p>
    @if($errors->any())<p class="error" role="alert">{{ $errors->first() }}</p>@endif
    <form method="post" action="{{ $galleryType === 'competition' ? route('guest-galleries.competitions.unlock', $event) : route('guest-galleries.concerts.unlock', $concert) }}">@csrf
        <label>Email address<input type="email" name="email" value="{{ old('email', $prefillEmail) }}" autocomplete="email" maxlength="255" required></label>
        <label>Gallery access code<input type="password" name="login_code" autocomplete="one-time-code" maxlength="64" required></label>
        <button class="button" type="submit">Open gallery</button>
    </form>
    <p class="muted" style="margin-top:18px">Your code may have been supplied by the competition organiser or studio.</p>
</div></section>
@endsection
