@extends('layouts.public', ['title' => 'Media galleries', 'footerLabel' => 'Competition and concert media'])

@push('styles')
<style>
    .gallery-switcher { display:flex; width:fit-content; margin:0 0 28px; border-bottom:2px solid #cddbe1; }
    .gallery-switcher a { padding:11px 22px; border-radius:9px 9px 0 0; color:#38505d; font-weight:800; text-decoration:none; }
    .gallery-switcher a[aria-current="page"] { margin-bottom:-2px; border:2px solid #cddbe1; border-bottom-color:#fff; background:#fff; color:#087eae; }
    .public-gallery-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(230px,1fr)); gap:18px; }
    .public-gallery-card { display:flex; flex-direction:column; min-height:320px; }
    .public-gallery-art { display:grid; height:145px; place-items:center; background:#101820; }
    .public-gallery-art img { width:100%; height:100%; object-fit:contain; }
    .public-gallery-art.is-photo img { object-fit:cover; }
    .public-gallery-art span { display:grid; width:62px; height:62px; place-items:center; border-radius:50%; background:#0aa0db; color:#fff; font-size:26px; font-weight:900; }
    .public-gallery-body { display:flex; flex:1; flex-direction:column; padding:18px; }
    .public-gallery-body h2 { margin:3px 0 8px; font-size:21px; line-height:1.2; }
    .coming-banner { margin:12px 0; border-radius:7px; background:#fff2c9; color:#704f00; padding:8px 10px; font-weight:850; }
    .notify-form { margin-top:auto; }
    .notify-form label { margin-bottom:9px; }
    .notify-form .choice { font-size:12px; }
</style>
@endpush

@section('content')
<section class="hero"><div class="container"><div class="eyebrow">DancePro media</div><h1>Find your gallery</h1><p class="lead">Browse released competition and concert galleries without creating an account. You’ll need the gallery login code to see protected media.</p></div></section>
<section class="section"><div class="container">
    <nav class="gallery-switcher" aria-label="Gallery type">
        <a href="{{ route('guest-galleries.index', ['type' => 'competition']) }}" @if($type === 'competition')aria-current="page"@endif>Competitions</a>
        <a href="{{ route('guest-galleries.index', ['type' => 'concert']) }}" @if($type === 'concert')aria-current="page"@endif>Concerts</a>
    </nav>
    @if(session('status'))<p class="card card-body" role="status">{{ session('status') }}</p>@endif
    @if($errors->any())<p class="error" role="alert">{{ $errors->first() }}</p>@endif
    @if($cards->isNotEmpty())<div class="public-gallery-grid">
        @foreach($cards as $card)<article class="card public-gallery-card">
            <div class="public-gallery-art @if($card['is_photo_cover'] ?? false) is-photo @endif">@if($card['image'])<img src="{{ $card['image'] }}" alt="">@else<span aria-hidden="true">{{ mb_substr($card['title'], 0, 1) }}</span>@endif</div>
            <div class="public-gallery-body"><div class="meta">{{ $card['subtitle'] }}</div><h2>{{ $card['title'] }}</h2><p class="meta">{{ $card['date']?->format('j F Y') }}</p>
                @if($card['released'])
                    @if($card['type'] === 'competition')<a class="button" href="{{ route('guest-galleries.competitions.access', $card['event']) }}">View gallery</a>
                    @else<a class="button" href="{{ route('guest-galleries.concerts.access', $card['concert']) }}">View gallery</a>@endif
                @else
                    <div class="coming-banner">Coming soon — register now</div>
                    <form class="notify-form" method="post" action="{{ route('guest-galleries.notifications.store', $card['event']) }}">@csrf
                        <label>Email address<input type="email" name="email" autocomplete="email" maxlength="255" required></label>
                        <label class="choice"><input type="checkbox" name="notification_consent" value="1" required><span>Email me once when this event’s media is released.</span></label>
                        <button class="button" type="submit">Notify me</button>
                    </form>
                @endif
            </div>
        </article>@endforeach
    </div>@else<div class="empty"><h2>No {{ $type }} galleries yet</h2><p>Released galleries and events starting within seven days will appear here.</p></div>@endif
</div></section>
@endsection
