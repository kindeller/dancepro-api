@extends('layouts.public', ['title' => $content['heading'], 'websiteDesign' => true])
@include('website._assets')
@push('styles')
<style>
.terms-tabs{display:flex;gap:4px;flex-wrap:wrap;border-bottom:1px solid var(--line);margin:0 0 28px}.terms-tabs a{padding:12px 15px;text-decoration:none;font-weight:750;color:var(--muted);border-bottom:3px solid transparent}.terms-tabs a[aria-current=page]{color:#087aa4;border-color:#00a3d5;background:#fff}.terms-tabs a:focus-visible{outline:2px solid #087aa4;outline-offset:2px}.terms-content{padding:clamp(20px,3vw,40px)}.terms-content h2{margin:0 0 12px;font-size:clamp(24px,3vw,34px)}.terms-content .terms-intro{color:var(--muted);margin:0 0 26px}.terms-content section+section{border-top:1px solid var(--line);padding-top:24px;margin-top:24px}.terms-content h3{font-size:20px;margin:0 0 10px}.terms-content p{white-space:pre-line;line-height:1.75;max-width:95ch}.terms-tier-list{display:grid;grid-template-columns:repeat(auto-fit,minmax(135px,1fr));gap:12px;padding:0;list-style:none}.terms-tier-list li{padding:16px;background:#eaf7fc;border:1px solid var(--line);border-radius:8px}.terms-tier-list strong{display:block;font-size:22px;color:#087aa4}.terms-contact{margin-top:26px}#website-content{scroll-margin-top:20px}
</style>
@endpush
@section('content')
@if($preview)<aside class="website-preview">Draft preview · Visible only to administrators. <a href="{{ route('admin.website.pages.edit', $page) }}">Back to editor</a></aside>@endif
<section class="hero"><div class="container"><div class="eyebrow">DancePro information</div><h1>Terms &amp; Conditions</h1><p class="lead">Clear information about purchases, discounts, access and your privacy.</p></div></section>
<section class="section" id="website-content"><div class="container">
    <x-help-terms-navigation active="terms" />
    <nav class="terms-tabs" aria-label="Terms categories">
        @foreach($termsTabs as $key => $tab)
            <a href="{{ route('website.detail', ['websiteSlug' => 'terms', 'tab' => $key]) }}#website-content" @if($key === $termsTab)aria-current="page"@endif>{{ $tab['label'] }}</a>
        @endforeach
    </nav>
    <article class="card terms-content">
        <h2>{{ $content['heading'] }}</h2><p class="terms-intro">{{ $content['description'] }}</p>
        @if(filled($content['body'] ?? null))<p>{{ $content['body'] }}</p>@endif
        @foreach($content['sections'] ?? [] as $section)
            @if($section['visible'] ?? true)
                <section><h3>{{ $section['title'] }}</h3><p>{{ $section['body'] ?? '' }}</p>
                    @if(!empty($section['link_url']) && !empty($section['link_label']))<a href="{{ $section['link_url'] }}">{{ $section['link_label'] }}</a>@endif
                </section>
            @endif
        @endforeach
        @if($termsTab === 'discounts' && $bulkTiers->isNotEmpty())
            <section><h3>Current bulk discount tiers</h3><p>Applied to each eligible studio or competition cart after the discount-code allocation.</p>
                <ul class="terms-tier-list">@foreach($bulkTiers as $tier)<li>Spend ${{ number_format($tier['threshold'] / 100, 2) }}<strong>Save {{ $tier['percent'] }}%</strong></li>@endforeach</ul>
            </section>
        @endif
    </article>
    <div class="terms-contact"><a class="button secondary" href="{{ route('website.help') }}#contact">Contact DancePro</a></div>
</div></section>
@endsection
