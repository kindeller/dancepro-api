@extends('layouts.public', ['title' => 'Media galleries', 'footerLabel' => 'Competition and concert media'])

@push('styles')
<style>
    .gallery-switcher { display:flex; width:fit-content; margin:0 0 28px; border-bottom:2px solid #cddbe1; }
    .gallery-switcher a { padding:11px 22px; border-radius:9px 9px 0 0; color:#38505d; font-weight:800; text-decoration:none; }
    .gallery-switcher a[aria-current="page"] { margin-bottom:-2px; border:2px solid #cddbe1; border-bottom-color:#fff; background:#fff; color:#087eae; }
    .public-gallery-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(min(100%,290px),1fr)); gap:22px; align-items:stretch; }
    .public-gallery-grid.is-concert { grid-template-columns:repeat(auto-fill,minmax(min(100%,230px),270px)); }
    .public-gallery-card { display:flex; min-width:0; flex-direction:column; }
    .public-gallery-art { display:grid; width:100%; aspect-ratio:16/9; overflow:hidden; place-items:center; background:#101820; }
    .public-gallery-art img { width:100%; height:100%; background:#101820; object-fit:contain; padding:20px; }
    .public-gallery-art.is-photo img { object-fit:cover; padding:0; }
    .public-gallery-art.is-logo { display:block; aspect-ratio:auto; background:transparent; }
    .public-gallery-art.is-logo img { display:block; width:100%; height:auto; padding:0; background:transparent; object-fit:initial; }
    .public-gallery-art span { display:grid; width:62px; height:62px; place-items:center; border-radius:50%; background:#0aa0db; color:#fff; font-size:26px; font-weight:900; }
    .public-gallery-body { display:flex; flex:1; flex-direction:column; padding:20px; }
    .public-gallery-subtitle { min-height:44px; color:#596971; font-size:14px; font-weight:750; line-height:1.4; }
    .public-gallery-body h2 { min-height:76px; margin:7px 0 9px; overflow-wrap:anywhere; font-size:21px; line-height:1.2; }
    .public-gallery-date { margin:0 0 18px; color:#596971; font-size:14px; font-weight:750; }
    .public-gallery-body>.button { width:100%; margin-top:auto; }
    .coming-banner { margin:12px 0; border-radius:7px; background:#fff2c9; color:#704f00; padding:8px 10px; font-weight:850; }
    .notify-form { margin-top:auto; }
    .notify-form label { margin-bottom:9px; }
    .notify-form .choice { font-size:12px; }
    @media(max-width:680px) {
        .public-gallery-grid { grid-template-columns:1fr; }
        .public-gallery-subtitle,.public-gallery-body h2 { min-height:0; }
    }
</style>
@endpush

@section('content')
<section class="hero"><div class="container"><div class="eyebrow">DancePro media</div><h1>Find your gallery</h1><p class="lead">@auth Browse competition galleries available to your account. Concert media is available through the studios added to your profile. @else Browse released competition and concert galleries without creating an account. You’ll need the gallery access code to see protected media. @endauth</p></div></section>
<section class="section"><div class="container">
    <nav class="gallery-switcher" aria-label="Gallery type">
        <a href="{{ route('guest-galleries.index', ['type' => 'competition']) }}" @if($type === 'competition')aria-current="page"@endif>Competitions</a>
        @guest
        <a href="{{ route('guest-galleries.index', ['type' => 'concert']) }}" @if($type === 'concert')aria-current="page"@endif>Concerts</a>
        @endguest
    </nav>
    @if(session('status'))<p class="card card-body" role="status">{{ session('status') }}</p>@endif
    @if($errors->any())<p class="error" role="alert">{{ $errors->first() }}</p>@endif
    @if($cards->isNotEmpty())<div class="public-gallery-grid @if($type === 'concert') is-concert @endif">
        @foreach($cards as $card)<article class="card public-gallery-card">
            <div class="public-gallery-art @if($card['is_photo_cover'] ?? false) is-photo @elseif($card['image']) is-logo @endif">@if($card['image'])<img src="{{ $card['image'] }}" alt="">@else<span aria-hidden="true">{{ mb_substr($card['title'], 0, 1) }}</span>@endif</div>
            <div class="public-gallery-body"><div class="public-gallery-subtitle">{{ $card['subtitle'] }}</div><h2>{{ $card['title'] }}</h2><p class="public-gallery-date">{{ $card['date']?->format('j F Y') }}</p>
                @if($card['released'])
                    @if($card['type'] === 'competition')<a class="button" href="{{ route('guest-galleries.competitions.access', $card['event']) }}">View gallery</a>
                    @else<a class="button" href="{{ route('guest-galleries.concerts.access', $card['concert']) }}">View gallery</a>@endif
                @else
                    <div class="coming-banner">Coming soon — register now</div>
                    <form class="notify-form" method="post" action="{{ route('guest-galleries.notifications.store', $card['event']) }}">@csrf
                        @guest
                        <label>Email address<input type="email" name="email" autocomplete="email" maxlength="255" required></label>
                        <label class="choice"><input type="checkbox" name="notification_consent" value="1" required><span>Email me once when this event’s media is released.</span></label>
                        @endguest
                        <button class="button" type="submit">Notify me</button>
                    </form>
                @endif
            </div>
        </article>@endforeach
    </div>@else<div class="empty"><h2>No {{ $type }} galleries yet</h2><p>Released galleries and events starting within seven days will appear here.</p></div>@endif
</div></section>
@endsection
