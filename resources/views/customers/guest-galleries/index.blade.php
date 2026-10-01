@extends('layouts.public', ['title' => ($galleryStudio ?? null)?->name ?? 'Media galleries', 'footerLabel' => 'Competition and concert media'])

@push('styles')
<style>
    .gallery-switcher { display:flex; width:fit-content; margin:0 0 28px; border-bottom:2px solid #cddbe1; }
    .gallery-switcher a { padding:11px 22px; border-radius:9px 9px 0 0; color:#38505d; font-weight:800; text-decoration:none; }
    .gallery-switcher a[aria-current="page"] { margin-bottom:-2px; border:2px solid #cddbe1; border-bottom-color:#fff; background:#fff; color:#087eae; }
    .coming-banner { margin:12px 0; border-radius:7px; background:#fff2c9; color:#704f00; padding:8px 10px; font-weight:850; }
    .registration-dialog { width:min(520px,calc(100% - 28px)); max-height:calc(100dvh - 40px); padding:24px; border:1px solid #cddbe1; border-radius:14px; color:#101820; overflow-y:auto; }
    .registration-dialog::backdrop { background:rgba(0,0,0,.65); }
    .registration-dialog-head { display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:20px; }
    .registration-dialog-head h2 { margin:0; }
    .registration-event { display:flex; align-items:center; gap:18px; margin-bottom:20px; }
    .registration-event-copy { min-width:0; }
    .registration-event-copy h3 { margin-bottom:8px; }
    .registration-event-copy p { margin:0; }
    .registration-logo { display:block; width:110px; height:90px; flex:0 0 auto; object-fit:contain; }
    @media(max-width:480px) { .registration-logo { width:78px; height:72px; } .registration-event { gap:12px; } }
    .registration-dialog h3 { overflow-wrap:anywhere; }
    .registration-dialog .actions { display:flex; gap:10px; flex-wrap:wrap; }
    .notify-form label { margin-bottom:9px; }
    .notify-form .choice { font-size:12px; }
</style>
@endpush

@section('content')
@php($galleryStudio = $galleryStudio ?? null)
<section class="hero"><div class="container">@include('customers._heading', ['headingImageUrl' => $galleryStudio?->logoUrl(), 'eyebrow' => $galleryStudio ? 'Studio galleries' : 'DancePro Media', 'heading' => $galleryStudio?->name ?? 'Find your gallery', 'description' => $galleryStudio ? 'Browse your studio’s event galleries. Register once for updates when new galleries are released.' : (auth()->check() ? 'Browse competition galleries available to your account. Concert media is available through the studios added to your profile.' : 'Browse released competition and concert galleries without creating an account. You’ll need the gallery access code to see protected media.')])</div></section>
<section class="section"><div class="container">
    @if($galleryStudio)
    <x-gallery-breadcrumbs :items="[['label' => 'Studios', 'url' => route('guest-galleries.index', ['type' => 'concert'])], ['label' => $galleryStudio->name]]" />
    <div class="section-head"><a class="button secondary" href="{{ route('guest-galleries.index', ['type' => 'concert']) }}">← Back to studios</a><button class="button" type="button" data-register-open="register-studio-{{ $galleryStudio->uuid }}" aria-haspopup="dialog" aria-controls="register-studio-{{ $galleryStudio->uuid }}">Register now</button></div>
    @else
    <nav class="gallery-switcher" aria-label="Gallery type" data-preserve-scroll>
        @foreach($galleryTypes as $galleryType => $galleryLabel)
            @if($galleryType !== 'concert' || ! auth()->check())
                <a href="{{ route('guest-galleries.index', ['type' => $galleryType]) }}" @if($type === $galleryType)aria-current="page"@endif>{{ $galleryLabel }}</a>
            @endif
        @endforeach
    </nav>
    @endif
    @if(session('status'))<p class="card card-body" role="status">{{ session('status') }}</p>@endif
    @if($errors->any())<p class="error" role="alert">{{ $errors->first() }}</p>@endif
    <details class="card card-body" style="margin-bottom:24px"><summary style="cursor:pointer;font-weight:800">New here? Galleries, access codes &amp; updates</summary><p>Register for gallery updates, then use your studio or event code to view protected media. Your account keeps events and purchases together; favourites let you save selections before buying.</p><p><a href="{{ route('website.help') }}#article-guide-gallery-updates">How registration and access codes work</a> · <a href="{{ route('website.help') }}#topic-shopping">Photos, videos and packages</a></p></details>
    @if($cards->isNotEmpty())<div class="gallery-navigation-grid">
        @foreach($cards as $card)
            @if($card['type'] === 'studio')
                <x-studio-gallery-card :studio="$card['studio']" :href="route('guest-galleries.studios.show', $card['studio'])" />
                @continue
            @endif
            @php($cardUrl = $card['released'] ? ($card['type'] === 'competition' ? route('guest-galleries.competitions.access', $card['event']) : route('guest-galleries.concerts.show', $card['concert'])) : null)
            <x-gallery-navigation-card :title="$card['title']" :image="$card['image']" :logo="!($card['is_photo_cover'] ?? false)" :subtitle="$card['date']?->format('j M Y') ?? 'Date to be confirmed'" :href="$cardUrl">
                @if(!$card['released'])
                    <div class="coming-banner">Coming soon</div>
                    <x-slot:actions><button class="gallery-nav-action" type="button" data-register-open="register-{{ $galleryStudio ? 'studio-'.$galleryStudio->uuid : $card['event']->uuid }}" aria-haspopup="dialog" aria-controls="register-{{ $galleryStudio ? 'studio-'.$galleryStudio->uuid : $card['event']->uuid }}">Register now <span aria-hidden="true">→</span></button></x-slot:actions>
                @endif
            </x-gallery-navigation-card>@endforeach
    </div>@else<div class="empty"><h2>No {{ $type }} galleries yet</h2><p>Released galleries and events starting within seven days will appear here.</p></div>@endif
</div></section>
@if($galleryStudio)
    @include('customers.guest-galleries._registration', [
        'registrationId' => 'register-studio-'.$galleryStudio->uuid, 'registrationKey' => $galleryStudio->uuid,
        'registrationTitle' => $galleryStudio->name, 'registrationDate' => null,
        'registrationLogo' => $galleryStudio->logoUrl(), 'studioSubscription' => true,
        'registrationAction' => route('guest-galleries.studios.subscribe', $galleryStudio),
    ])
@else
    @foreach($cards->where('released', false) as $card)
        @include('customers.guest-galleries._registration', [
            'registrationId' => 'register-'.$card['event']->uuid, 'registrationKey' => $card['event']->uuid,
            'registrationTitle' => $card['title'], 'registrationDate' => $card['date'],
            'registrationLogo' => $card['event']->logoUrl() ?? $card['event']->crewLogoUrl(), 'studioSubscription' => false,
            'registrationAction' => route('guest-galleries.notifications.store', $card['event']),
        ])
    @endforeach
@endif

@endsection

@push('scripts')
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
(() => {
    document.querySelectorAll('[data-register-open]').forEach(button => {
        button.addEventListener('click', () => document.getElementById(button.dataset.registerOpen)?.showModal());
    });
    document.querySelectorAll('.registration-dialog').forEach(dialog => {
        dialog.querySelectorAll('[data-register-close]').forEach(button => button.addEventListener('click', () => dialog.close()));
        dialog.addEventListener('click', event => {
            const bounds = dialog.getBoundingClientRect();
            if (event.target === dialog && (event.clientX < bounds.left || event.clientX > bounds.right
                || event.clientY < bounds.top || event.clientY > bounds.bottom)) dialog.close();
        });
        if (dialog.hasAttribute('data-open-on-load')) dialog.showModal();
    });
})();
</script>
@endpush
