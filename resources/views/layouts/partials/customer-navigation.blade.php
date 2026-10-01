@php
    $hasMediaAccount = auth()->user()?->canAccessCustomer() ?? false;
    $mediaIsActive = ! $helpIsActive && request()->routeIs('customer.media.*', 'customer.profile.studio', 'customer.competitions.*', 'customer.concerts.*', 'customer.checkout.*', 'customer.concert-family.*', 'concerts.*', 'guest-galleries.*');
    $servicesIsActive = request()->routeIs('website.services') || (request()->routeIs('website.detail') && in_array(request()->route('websiteSlug'), ['book', 'competition', 'portfolio', 'pose', 'shootme', 'promo', 'studio', 'shop'], true));
@endphp
<nav aria-label="Main navigation">
    <a href="{{ route('website.home') }}" @if(request()->routeIs('website.home')) aria-current="page" @endif><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 10 9-7 9 7M5 9v12h14V9M9 21v-8h6v8"/></svg>Home</a>
    @if($hasMediaAccount)
        <a href="{{ route('customer.media.index') }}" @if($mediaIsActive) aria-current="page" @endif><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="m10 8 6 4-6 4Z"/></svg>My Media</a>
        <a href="{{ route('customer.messages.index') }}" @if(request()->routeIs('customer.messages.*')) aria-current="page" @endif>
            @if($customerInboxCounts['total'])<span class="inbox-badge" data-unread-count="total" aria-label="{{ $customerInboxCounts['total'] }} unread messages and notifications">{{ $customerInboxCounts['total'] }}</span>@endif
            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 6 9 7 9-7"/></svg>My Messages
        </a>
    @else
        <a href="{{ route('guest-galleries.index') }}" @if(request()->routeIs('guest-galleries.*')) aria-current="page" @endif><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="m10 8 6 4-6 4Z"/></svg>Media galleries</a>
    @endif
    <a href="{{ route('website.services') }}" @if($servicesIsActive) aria-current="page" @endif><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="6" width="18" height="15" rx="2"/><path d="M8 6V3h8v3M3 12h18"/></svg>Services</a>
    <a href="{{ route('website.about') }}" @if(request()->routeIs('website.about')) aria-current="page" @endif><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 6c-3-2-6-2-9-1v15c3-1 6-1 9 1 3-2 6-2 9-1V5c-3-1-6-1-9 1Zm0 0v15M6 9h3m6 0h3M6 13h3m6 0h3"/></svg>About</a>
    <a href="{{ $hasMediaAccount ? route('customer.media.help') : route('website.help') }}" @if($helpIsActive) aria-current="page" @endif><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 1 1 4 2c-1 .7-1.5 1-1.5 2M12 16h.01"/></svg>Help</a>
    @if($hasMediaAccount)
        <a href="{{ route('customer.profile.edit') }}" @if(request()->routeIs('customer.profile.*') && !request()->routeIs('customer.profile.studio')) aria-current="page" @endif>
            @if($customerProfileCounts['total'])<span class="inbox-badge" aria-label="{{ $customerProfileCounts['total'] }} profile actions required">{{ $customerProfileCounts['total'] }}</span>@endif
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/></svg>My Profile
        </a>
    @elseif(!auth()->check())
        <a class="studio-hub-link sign-in-link" href="{{ route('login') }}" @if(request()->routeIs('login', 'customer.login')) aria-current="page" @endif><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 3h6v18h-6M3 12h12m-4-4 4 4-4 4"/></svg>Sign in</a>
    @endif
</nav>
