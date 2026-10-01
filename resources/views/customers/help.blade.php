@extends('layouts.public', ['title' => 'Help', 'websiteDesign' => $publicHelp ?? false])

@if($publicHelp ?? false) @include('website._assets') @endif

@push('styles')
<style>
    .help-topics{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 28px}.help-topics a{padding:8px 12px;border:1px solid var(--line);border-radius:22px;font-weight:700;text-decoration:none}.help-article,.help-category{scroll-margin-top:110px}.help-article:target{outline:2px solid #00a3d5;outline-offset:3px}.help-shortcuts{display:flex;gap:12px 24px;flex-wrap:wrap;margin-bottom:24px}.help-search{display:flex;gap:10px;margin:0 0 24px}.help-search input{margin:0}.article-groups{display:grid;gap:20px;margin-bottom:34px}.article-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:12px}.help-article{padding:0}.help-article summary{cursor:pointer;padding:18px;font-weight:800}.article-content{padding:0 18px 18px}.article-feedback{display:flex;align-items:center;gap:8px;border-top:1px solid var(--line);padding-top:13px}.article-feedback form{margin:0}.article-feedback button{min-height:36px;padding:6px 11px}.help-layout{display:grid;grid-template-columns:minmax(0,1fr) minmax(320px,.72fr);gap:22px;align-items:start}.request-list{display:grid;gap:12px}.request-card{padding:18px}.request-meta{display:flex;gap:8px;flex-wrap:wrap;align-items:center}.help-form{display:grid;gap:14px}.help-form label{display:grid;gap:6px;font-weight:700}.help-form select,.help-form input,.help-form textarea{width:100%}.status-pill{display:inline-flex;padding:5px 10px;border-radius:999px;background:#e7eef2;font-size:12px;font-weight:800;text-transform:capitalize}.status-pill.approved,.status-pill.resolved{background:#dff5e8;color:#12663b}.status-pill.declined{background:#fde6e9;color:#a62232}@media(max-width:800px){.help-layout{grid-template-columns:1fr}.help-search{display:grid}}
    .help-article[open]{grid-column:1/-1}.article-grid{align-items:start}.help-visual-guide{margin-top:26px;padding-top:24px;border-top:1px solid var(--line)}.help-visual-guide h3{margin-bottom:6px}.help-visual-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:20px;align-items:start}.help-shot{margin:0;padding:18px;background:#f5fafc;border:1px solid var(--line);border-radius:12px;min-width:0}.help-shot figcaption strong{display:flex;align-items:center;gap:10px}.help-step{display:grid;place-items:center;flex:0 0 28px;height:28px;border-radius:50%;background:#dff4fc;color:#075f84}.help-shot p{margin:10px 0 16px}.help-shot a{display:block;text-decoration:none;color:#087bab}.help-shot a:focus-visible{outline:3px solid #087bab;outline-offset:5px}.help-shot img{width:100%;height:auto;border:1px solid var(--line);border-radius:6px;background:#fff}.help-shot-enlarge{display:block;margin-top:10px;font-weight:750;font-size:14px}.help-image-viewer{width:min(1100px,calc(100% - 24px));max-height:calc(100dvh - 24px);padding:22px;border:1px solid var(--line);border-radius:14px;background:#fff;color:var(--ink);overflow:auto}.help-image-viewer::backdrop{background:#07151ddd}.help-image-toolbar{display:flex;justify-content:space-between;gap:16px;align-items:center;margin-bottom:16px}.help-image-toolbar h2{margin:0;font-size:22px;line-height:1.3}.help-image-toolbar button{flex-shrink:0}.help-image-stage{overflow:auto;max-height:65dvh}.help-image-stage.is-zoomed img{max-width:none;max-height:none}.help-image-stage img{max-height:65dvh;max-width:100%;height:auto;width:auto;margin:auto;object-fit:contain}.help-image-footer{display:flex;flex-wrap:wrap;gap:8px 20px;margin-bottom:0}.help-search button{white-space:nowrap;flex-shrink:0}@media(max-width:900px){.help-visual-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:600px){.help-visual-grid{grid-template-columns:minmax(0,320px)}}@media(max-width:500px){.help-image-viewer{padding:14px}.help-image-toolbar h2{font-size:18px}.help-shot{padding:12px}}
</style>
@endpush

@section('content')
@php($helpRoute = ($publicHelp ?? false) ? route('website.help') : route('customer.media.help'))
@if($publicHelp ?? false)
@include('website._hero', ['heroSlug' => 'help', 'heroPhoto' => 'ballet', 'heroEyebrow' => 'Help & contact', 'heroHeading' => 'A little help. A lot of answers.', 'heroDescription' => 'Galleries, orders, downloads or your next event. Let’s point you in the right direction.'])
@else
<section class="hero"><div class="container">@include('customers._heading', ['heading' => 'Help', 'description' => 'Find answers about galleries, orders, downloads, bookings and your DancePro account.'])</div></section>
@endif
<section class="section" id="website-content"><div class="container">
    <x-help-terms-navigation />
    @if(filled($content))<div class="card card-body" style="margin-bottom:24px;white-space:pre-line">{{ $content }}</div>@endif
    @if(session('status'))<div class="card card-body" role="status" style="margin-bottom:18px">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="card card-body" role="alert" style="margin-bottom:18px;color:#a62232">{{ $errors->first() }}</div>@endif
    <form method="get" action="{{ $helpRoute }}" class="help-search" role="search"><input type="search" name="q" value="{{ $query }}" placeholder="Search gallery access, downloads, orders…" aria-label="Search help"><button>Search help</button>@if($query)<a class="button secondary" href="{{ $helpRoute }}">Clear</a>@endif</form>
    @if(!$query)
    <nav class="help-shortcuts" aria-label="Account quick links">@guest<a href="{{ route('login') }}">Sign in</a><a href="{{ route('customer.register') }}">Create an account</a>@else<a href="{{ route('customer.media.index') }}">My Media</a>@endguest<a href="{{ route('guest-galleries.index') }}">Find a gallery</a>@auth<a href="{{ route('account.security') }}">Account security</a>@endauth<a href="#contact">Contact DancePro</a></nav>
    @endif
    @if($articleGroups->isNotEmpty())<nav class="help-topics" aria-label="Help topics">@foreach($articleGroups as $category => $articles)<a href="#topic-{{ Str::slug($category) }}">{{ $category }}</a>@endforeach</nav>@endif
    @if($query && $articleGroups->isEmpty())<div class="card card-body" style="margin-bottom:24px"><h2>No answer found</h2><p>Try fewer words or contact us below. @auth Your search has been recorded so we can improve Help. @endauth</p></div>@endif
    <div class="article-groups">@foreach($articleGroups as $category => $articles)<section class="help-category" id="topic-{{ Str::slug($category) }}"><h2>{{ $category }}</h2><div class="article-grid">@foreach($articles as $article)<details class="card help-article" id="article-{{ $article->slug }}" @if($query) open @endif><summary>{{ $article->title }}</summary><div class="article-content"><p class="meta">{{ $article->summary }}</p><div style="white-space:pre-line">{{ $article->body }}</div>@include('customers._help-screenshots')@if(auth()->user()?->canAccessCustomer())<div class="article-feedback"><span class="meta">Did this answer your question?</span><form method="post" action="{{ route('customer.media.help.feedback', $article) }}">@csrf<input type="hidden" name="helpful" value="1"><button class="button secondary" aria-label="Yes, this article helped">Yes</button></form><form method="post" action="{{ route('customer.media.help.feedback', $article) }}">@csrf<input type="hidden" name="helpful" value="0"><button class="button secondary" aria-label="No, this article did not help">No</button></form></div>@endif</div></details>@endforeach</div></section>@endforeach</div>
    <div class="card card-body" id="contact"><h2>Still need help?</h2>
    @if(auth()->user()?->canAccessCustomer())<p>Send a message to DancePro. Your enquiry and our replies will stay together in My Messages.</p><a class="button" href="{{ route('customer.messages.index', ['tab' => 'new', 'type' => request('type'), 'order' => request('order'), 'entitlement' => request('entitlement')]) }}">Contact us</a> <a class="button secondary" href="{{ route('customer.messages.index', ['tab' => 'conversations']) }}">My conversations</a>
    @else<p>Sign in to keep your enquiry and our replies together in My Messages. You can also email info@dancepro.com.au. Include your event name and order number if applicable.</p><a class="button" href="{{ route('login') }}">Sign in to message us</a> <a class="button secondary" href="mailto:info@dancepro.com.au">Email DancePro</a>@endif</div>
</div></section>
@include('customers._help-image-viewer')
@endsection

@push('scripts')
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
(() => {
    function openLinkedAnswer() {
        const target = document.getElementById(location.hash.slice(1));
        if (!target?.matches('details.help-article')) return;
        target.open = true;
        requestAnimationFrame(() => target.scrollIntoView({block: 'start'}));
    }
    window.addEventListener('hashchange', openLinkedAnswer);
    openLinkedAnswer();
    const viewer = document.getElementById('help-image-viewer');
    const stage = viewer.querySelector('.help-image-stage');
    const zoom = viewer.querySelector('[data-help-image-zoom]');
    let opener;
    document.querySelectorAll('[data-help-screenshot]').forEach(link => link.addEventListener('click', event => {
        if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || typeof viewer.showModal !== 'function') return;
        event.preventDefault();
        opener = link;
        const image = link.querySelector('img');
        document.getElementById('help-full-image').src = link.href;
        document.getElementById('help-full-image').alt = image.alt;
        document.getElementById('help-image-title').textContent = link.dataset.caption;
        document.getElementById('help-original-image').href = link.href;
        stage.classList.remove('is-zoomed');
        zoom.setAttribute('aria-pressed', 'false');
        zoom.textContent = 'Zoom in';
        viewer.showModal();
    }));
    zoom.addEventListener('click', () => {
        const expanded = stage.classList.toggle('is-zoomed');
        zoom.setAttribute('aria-pressed', String(expanded));
        zoom.textContent = expanded ? 'Fit to screen' : 'Zoom in';
    });
    viewer.querySelector('[data-help-image-close]').addEventListener('click', () => viewer.close());
    viewer.addEventListener('click', event => {
        const box = viewer.getBoundingClientRect();
        if (event.target === viewer && (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom)) viewer.close();
    });
    viewer.addEventListener('close', () => opener?.focus({preventScroll: true}));
})();
</script>
@endpush
