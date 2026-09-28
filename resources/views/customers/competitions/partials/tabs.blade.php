@once
    @push('styles')
        <style>
            .competition-tabs { display:flex; gap:4px; overflow-x:auto; margin:0 0 24px; border-bottom:1px solid #cbd9df; scrollbar-width:thin; }
            .competition-tab { display:inline-flex; flex:0 0 auto; align-items:center; gap:9px; min-height:48px; padding:10px 15px; border:1px solid transparent; border-bottom:0; border-radius:9px 9px 0 0; background:#eaf1f5; color:#34434d; font-weight:750; text-decoration:none; white-space:nowrap; }
            .competition-tab:hover, .competition-tab:focus-visible { background:#dff2fb; color:#073e58; }
            .competition-tab[aria-current="page"] { position:relative; margin-bottom:-1px; border-color:#cbd9df; border-top:3px solid #078bc4; border-bottom:1px solid #fff; background:#fff; color:#101820; }
            .competition-tab:focus-visible { outline:3px solid #078bc4; outline-offset:-3px; }
            .competition-tab-icon { display:block; width:19px; height:19px; flex:0 0 19px; stroke:currentColor; stroke-width:1.8; fill:none; stroke-linecap:round; stroke-linejoin:round; }
            .competition-tab-logo { display:block; width:21px; height:21px; flex:0 0 21px; border-radius:4px; background:#fff; object-fit:contain; }
            .competition-tab-count { display:inline-grid; min-width:20px; height:20px; place-items:center; border-radius:10px; background:#d7edf7; color:#07587d; font-size:11px; padding:0 5px; }
            .competition-selection-links { display:flex; flex:0 0 auto; gap:7px; margin-left:auto; padding-left:18px; }
            .competition-selection-links .competition-tab { min-height:42px; margin:3px 0 5px; border:1px solid #bfd0d8; border-radius:9px; background:#fff; }
            .competition-selection-links .competition-tab:hover,.competition-selection-links .competition-tab:focus-visible { border-color:#078bc4; background:#e7f7fe; }
            @media (max-width:650px) { .competition-tab { padding:9px 11px; font-size:13px; } }
        </style>
    @endpush
@endonce

@php
    $competitionTabRoutes = $competitionTabRoutes ?? [
        'event' => 'customer.competitions.show', 'dancers' => 'customer.competitions.dancers',
        'studios' => 'customer.competitions.studios', 'improvisation' => 'customer.competitions.improvisation',
        'favourites' => 'customer.competitions.favourites', 'cart' => 'customer.competitions.cart',
    ];
    $usesAccountSelections = auth()->check() && $competitionTabRoutes['event'] === 'customer.competitions.show';
    $favouritesUrl = $usesAccountSelections
        ? route('customer.media.favourites', ['focus_kind' => 'competition', 'focus_event' => $event->uuid])
        : route($competitionTabRoutes['favourites'], $event);
    $cartUrl = $usesAccountSelections
        ? route('customer.media.cart', ['focus_kind' => 'competition', 'focus_event' => $event->uuid])
        : route($competitionTabRoutes['cart'], $event);
@endphp

<nav class="competition-tabs" aria-label="Competition gallery pages">
    <a class="competition-tab" href="{{ route($competitionTabRoutes['event'], $event) }}" @if($activeTab === 'event')aria-current="page"@endif>
        @if($event->crewLogoUrl())<img class="competition-tab-logo" src="{{ $event->crewLogoUrl() }}" alt="">@else
            <svg class="competition-tab-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4m10-4v4M3 10h18"/></svg>
        @endif
        <span>Whole Event</span>
    </a>
    <a class="competition-tab" href="{{ route($competitionTabRoutes['dancers'], $event) }}" @if($activeTab === 'dancers')aria-current="page"@endif>
        <svg class="competition-tab-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="7" r="3.5"/><path d="M4.5 20c.5-4.3 3.2-6.5 7.5-6.5s7 2.2 7.5 6.5"/></svg>
        <span>My Dancers’ Routines</span>
    </a>
    <a class="competition-tab" href="{{ route($competitionTabRoutes['studios'], $event) }}" @if($activeTab === 'studios')aria-current="page"@endif>
        @if($studioLogoUrl)<img class="competition-tab-logo" src="{{ $studioLogoUrl }}" alt="">@else
            <svg class="competition-tab-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 10h1m4 0h1M9 14h1m4 0h1M10 21v-4h4v4"/></svg>
        @endif
        <span>My Studios’ Routines</span>
    </a>
    <a class="competition-tab" href="{{ route($competitionTabRoutes['improvisation'], $event) }}" @if($activeTab === 'improvisation')aria-current="page"@endif>
        <svg class="competition-tab-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="13" cy="4.5" r="2"/><path d="m10 9 3 2 3-1 3-3M13 11l-3 4-5 2m8-6 3 5 4 3M10 15l-1 6"/></svg>
        <span>Improvisation</span>
    </a>
    @if($usesAccountSelections)<span class="competition-selection-links">@endif
    <a class="competition-tab" data-favourites-tab href="{{ $favouritesUrl }}" @if(!$usesAccountSelections && $activeTab === 'favourites')aria-current="page"@endif>
        <svg class="competition-tab-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M20.8 8.5c0 5-8.8 11-8.8 11s-8.8-6-8.8-11a4.6 4.6 0 0 1 8.8-1.8 4.6 4.6 0 0 1 8.8 1.8Z"/></svg>
        <span>Favourites</span>
        @if(($favouriteCount ?? 0) > 0)<span class="competition-tab-count">{{ $favouriteCount }}</span>@endif
    </a>
    <a class="competition-tab" data-cart-tab href="{{ $cartUrl }}" @if(!$usesAccountSelections && $activeTab === 'cart')aria-current="page"@endif>
        <svg class="competition-tab-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.1 11h11.5l2-8H6"/><circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>
        <span>Cart</span>
        @if(($cartCount ?? 0) > 0)<span class="competition-tab-count">{{ $cartCount }}</span>@endif
    </a>
    @if($usesAccountSelections)</span>@endif
</nav>
