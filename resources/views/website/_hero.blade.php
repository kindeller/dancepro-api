<section class="web-hero {{ ($heroSlug ?? '') === 'home' ? 'web-hero-home' : '' }}" aria-labelledby="website-title">
    <div class="web-hero-visual web-photo-{{ $heroPhoto }}">
        @if(!empty($heroImage))
            <img src="{{ Storage::disk(config('uploads.public_disk'))->url($heroImage) }}" alt="" fetchpriority="high" class="web-hero-image">
        @else
            <x-website.picture :photo="$heroPhoto" :priority="true" class="web-hero-image" sizes="100vw" />
        @endif
    </div>
    <div class="container web-hero-content">
        <span class="web-kicker">{{ $heroEyebrow }}</span>
        <h1 id="website-title">{{ $heroHeading }}</h1>
        <p>{{ $heroDescription }}</p>
        <div class="web-hero-actions">
            @if(($heroSlug ?? '') === 'home')
                @if(auth()->user()?->canAccessCustomer())
                    <a class="web-button" href="{{ route('customer.media.index') }}">Open My Media <span aria-hidden="true">→</span></a>
                    <a class="web-button web-button-outline" href="{{ route('customer.media.index', ['tab' => 'events']) }}#add-media-heading">Add a studio or event <span aria-hidden="true">+</span></a>
                @else
                    <a class="web-button" href="{{ route('login') }}">Sign in <span aria-hidden="true">→</span></a>
                    <a class="web-button web-button-outline" href="{{ route('customer.register') }}">Create an account <span aria-hidden="true">+</span></a>
                @endif
            @else
                <a class="web-text-link" href="#website-content">Explore {{ ($heroSlug ?? '') === 'help' ? 'Help' : 'this page' }} <span aria-hidden="true">↓</span></a>
            @endif
        </div>
        @if(($heroSlug ?? '') === 'home')
            <a class="web-browse-link" href="{{ route('guest-galleries.index') }}">Browse event galleries <span aria-hidden="true">↗</span></a>
        @endif
    </div>
    <div class="container web-hero-bottom"><span>DancePro Photography &amp; Video</span><a href="#website-content">Scroll to explore <span aria-hidden="true">↓</span></a></div>
</section>
