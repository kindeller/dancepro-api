<section class="web-account-start container" id="website-content" aria-labelledby="account-start-heading">
    <div class="web-account-intro"><span class="web-kicker">Your media, in one place</span><h2 id="account-start-heading">From the stage to your account.</h2>@if(!empty($content['body']))<p>{{ $content['body'] }}</p>@endif</div>
    <ol class="web-account-steps">
        @foreach($sections as $section)
            <li><span class="web-step-number" aria-hidden="true">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><h3>{{ $section['title'] }}</h3><p>{{ $section['body'] ?? '' }}</p>@if(!empty($section['link_url']) && !empty($section['link_label']))<a class="web-text-link" href="{{ $section['link_url'] }}">{{ $section['link_label'] }} →</a>@endif</li>
        @endforeach
    </ol>
    <p><a class="web-text-link" href="{{ route('website.help') }}#article-guide-media-account-guide">Explore your account: galleries, purchases, downloads &amp; more <span aria-hidden="true">→</span></a></p>
</section>
<section class="web-home-galleries container" aria-label="Find your photos and videos">
    <article class="web-home-gallery" data-reveal>
        <a class="web-home-photo" href="{{ route('guest-galleries.index', ['type' => 'concert']) }}" aria-label="Browse concert galleries"><x-website.picture photo="concert-finale" sizes="(max-width: 700px) 100vw, 50vw" /></a>
        <div class="web-home-gallery-copy"><span class="web-kicker">Your studio. Your show.</span><h2>Concert memories.</h2><p>Find your studio’s released photos and access your concert videos with the code supplied by your studio.</p><a class="web-text-link" href="{{ route('guest-galleries.index', ['type' => 'concert']) }}">Browse concert galleries <span aria-hidden="true">↗</span></a></div>
    </article>
    <article class="web-home-gallery" data-reveal>
        <a class="web-home-photo" href="{{ route('guest-galleries.index', ['type' => 'competition']) }}" aria-label="Browse competition galleries"><x-website.picture photo="competition-leap" sizes="(max-width: 700px) 100vw, 50vw" /></a>
        <div class="web-home-gallery-copy"><span class="web-kicker">Every routine. Every achievement.</span><h2>Competition moments.</h2><p>Find your event, explore your routine photos and videos, and keep your purchases together in My Media.</p><a class="web-text-link" href="{{ route('guest-galleries.index', ['type' => 'competition']) }}">Browse competitions <span aria-hidden="true">↗</span></a></div>
    </article>
</section>
<div class="web-event-wrap web-home-events"><div class="container">@include('website._events')</div></div>
<section class="web-home-organisers" aria-labelledby="organiser-heading"><div class="container">
    <x-website.picture photo="curtain-call" sizes="(max-width: 700px) 100vw, 40vw" />
    <div><span class="web-kicker">For studios, schools &amp; organisers</span><h2 id="organiser-heading">Organising a concert or competition?</h2><p>Dance photography and video in Perth, with a team that knows performance. Explore our services or talk to us about your next event.</p><div class="web-organiser-actions"><a class="web-text-link" href="{{ route('website.services') }}">Explore services <span aria-hidden="true">→</span></a><a class="web-text-link" href="{{ route('website.help') }}#contact">Enquire about an event <span aria-hidden="true">→</span></a></div></div>
</div></section>
