@extends('layouts.public', ['title' => $content['heading'], 'footerLabel' => 'Photography & Video · Perth, Western Australia', 'websiteDesign' => true])
@include('website._assets')

@section('content')
@php
    $visibleSections = collect($content['sections'] ?? [])->filter(fn ($section) => $section['visible'] ?? true);
    $placeholders = $page->slug === 'about' ? $visibleSections->where('kind', 'crew_placeholder') : collect();
    $sections = $visibleSections->reject(fn ($section) => $page->slug === 'about' && ($section['kind'] ?? '') === 'crew_placeholder')->values();
    $detailPage = !in_array($page->slug, ['home', 'about', 'services']);
    $photos = config('website.stories.'.$page->slug, []);
@endphp
@if($preview)
    <aside class="website-preview">Draft preview · Visible only to administrators. <a href="{{ route('admin.website.pages.edit', $page) }}">Back to editor</a></aside>
@endif
@include('website._hero', ['heroSlug' => $page->slug, 'heroPhoto' => config('website.heroes.'.$page->slug, 'flight'), 'heroImage' => $content['image'] ?? null, 'heroEyebrow' => $content['eyebrow'], 'heroHeading' => $content['heading'], 'heroDescription' => $content['description']])
@if($page->slug === 'home')
    @include('website._home')
@else
<section class="web-content container" id="website-content">
    @if($detailPage)
        <nav class="web-breadcrumb" aria-label="Page navigation"><a href="{{ in_array($page->slug, ['terms', 'privacy']) ? route('website.home') : route('website.services') }}">← {{ in_array($page->slug, ['terms', 'privacy']) ? 'Home' : 'All services' }}</a><a href="{{ route('website.help') }}">Help &amp; contact ↗</a></nav>
    @endif
    @if(!empty($content['body']))
        <div class="web-intro" data-reveal><span class="web-kicker">{{ $content['eyebrow'] }}</span><p>{{ $content['body'] }}</p></div>
    @endif
    @if($page->slug === 'services')
        <div class="web-stories">
            @foreach($sections as $section)
                <article class="web-story" data-reveal>
                    @if($photo = $photos[$section['link_url'] ?? ''] ?? null)
                        <div class="web-story-image"><x-website.picture :photo="$photo" sizes="(max-width: 700px) 100vw, 50vw" /><span class="web-story-number">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span></div>
                    @endif
                    <div class="web-story-copy"><h2>{{ $section['title'] }}</h2><p>{{ $section['body'] ?? '' }}</p>
                        @if(!empty($section['link_url']) && !empty($section['link_label']))<a class="web-text-link" href="{{ $section['link_url'] }}">{{ $section['link_label'] }} <span aria-hidden="true">↗</span></a>@endif
                    </div>
                </article>
            @endforeach
        </div>
    @elseif($page->slug === 'about')
        <div class="web-about">
            <div class="web-about-images" data-reveal><x-website.picture photo="rehearsal-line" /><x-website.picture photo="ballet-finale" /><span class="web-image-note">An eye for dance.<br>A feel for the moment.</span></div>
            <div class="web-about-copy">
                @foreach($sections as $section)
                    <article data-reveal><span class="web-kicker">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }} / Our story</span><h2>{{ $section['title'] }}</h2><p>{{ $section['body'] ?? '' }}</p>
                    @if(!empty($section['link_url']) && !empty($section['link_label']))<a class="web-text-link" href="{{ $section['link_url'] }}">{{ $section['link_label'] }} ↗</a>@endif</article>
                @endforeach
            </div>
        </div>
    @else
        <div class="web-details">
            <nav class="web-contents" aria-label="On this page"><span class="web-kicker">On this page</span>@foreach($sections as $section)<a href="#detail-{{ $loop->iteration }}">{{ $section['title'] }}</a>@endforeach</nav>
            <div class="web-detail-copy">
                @foreach($sections as $section)
                    <article id="detail-{{ $loop->iteration }}" data-reveal><span class="web-kicker">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><h2>{{ $section['title'] }}</h2><p>{{ $section['body'] ?? '' }}</p>
                        @if(!empty($section['link_url']) && !empty($section['link_label']))<a class="web-button" href="{{ $section['link_url'] }}">{{ $section['link_label'] }} ↗</a>@endif
                    </article>
                    @if($loop->iteration === 3 && $sections->count() > 4 && config('website.details.'.$page->slug))<figure class="web-detail-photo" data-reveal><x-website.picture :photo="config('website.details.'.$page->slug)" /></figure>@endif
                @endforeach
            </div>
        </div>
    @endif
</section>
@if($page->slug === 'about' && ($bios->isNotEmpty() || $placeholders->isNotEmpty()))
    <section class="web-crew container" id="our-crew"><div class="web-section-heading" data-reveal><span class="web-kicker">The people behind the lens</span><h2>Meet the crew<span>.</span></h2></div><div class="web-crew-grid">
        @foreach($bios as $bio)
            <article class="web-bio" data-reveal>
                @if(!empty($bio->published['image']))<img class="website-avatar" loading="lazy" src="{{ Storage::disk(config('uploads.public_disk'))->url($bio->published['image']) }}" alt="{{ $bio->published['name'] }}">@else<div class="web-bio-mark" aria-hidden="true">DP</div>@endif
                <span class="web-kicker">{{ $bio->published['role'] ?? '' }}</span><h3>{{ $bio->published['name'] }}</h3><p>{{ $bio->published['body'] }}</p>
            </article>
        @endforeach
        @foreach($placeholders as $section)
            <article class="web-bio" data-reveal data-placeholder-bio><div class="web-bio-mark" aria-hidden="true">DP</div><span class="website-placeholder-label">Placeholder biography</span><h3>{{ $section['title'] }}</h3><p>{{ $section['body'] }}</p></article>
        @endforeach
    </div></section>
@endif
<section class="web-outro"><div class="container" data-reveal><span class="web-kicker">Let's make something worth keeping.</span><h2>Your next moment<br>starts here<span>.</span></h2><div><a class="web-button" href="{{ route('website.services') }}">Explore our services ↗</a><a class="web-text-link" href="{{ route('website.help') }}#contact">Talk to DancePro →</a></div></div></section>
@endif
@endsection
