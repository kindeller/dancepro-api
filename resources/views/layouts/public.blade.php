<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'DancePro' }}</title>
    @include('layouts.partials.foundation-styles')
    <style>
        :root { --brand: #0aa0db; --ink: #101820; --muted: #66737a; --paper: #f4f8fa; --panel: #fff; --line: #dbe5e9; --accent: var(--concert-brand, #0aa0db); color-scheme: light; }
        body { margin: 0; background: var(--paper); color: var(--ink); font: 16px/1.55 Inter, ui-sans-serif, system-ui, -apple-system, sans-serif; }
        a { color: inherit; }
        img { display: block; max-width: 100%; }
        .site-header { background: var(--ink); color: #fff; }
        .header-inner, .container { width: min(1180px, calc(100% - 40px)); margin: auto; }
        .header-inner { display: flex; min-height: 74px; align-items: center; justify-content: space-between; gap: 24px; }
        .header-brand { display:flex; align-items:center; gap:14px; min-width:0; }
        .logo { display: flex; align-items: center; gap: 10px; color: #fff; font-weight: 850; letter-spacing: .08em; text-decoration: none; text-transform: uppercase; }
        .logo img { width: 38px; height: 38px; border-radius: 5px; }
        .header-back { display:inline-flex; min-height:38px; align-items:center; gap:7px; padding:7px 11px; border:1px solid #38505d; border-radius:7px; color:#dff6ff; font-size:13px; font-weight:800; text-decoration:none; transition:background-color .18s ease,border-color .18s ease,color .18s ease,transform .18s ease; }
        .header-back svg { width:17px; height:17px; fill:none; stroke:currentColor; stroke-width:2.2; stroke-linecap:round; stroke-linejoin:round; transition:transform .18s ease; }
        .header-back:hover,.header-back:focus-visible { border-color:#6ed8ff; background:#17303c; color:#fff; outline:0; transform:translateY(-1px); }
        .header-back:hover svg,.header-back:focus-visible svg { transform:translateX(-2px); }
        .site-header nav { display: flex; align-items:center; gap:7px; }
        .site-header nav>a { position:relative; display:inline-flex; min-height:42px; align-items:center; justify-content:center; padding:9px 13px; border-radius:7px; color:#dff6ff; font-size:14px; font-weight:750; line-height:1; text-decoration:none; transition:background-color .18s ease,color .18s ease,transform .18s ease; }
        .site-header nav>a::after { position:absolute; right:13px; bottom:5px; left:13px; height:2px; border-radius:2px; background:#61d3fb; content:""; opacity:0; transform:scaleX(.35); transition:opacity .18s ease,transform .18s ease; }
        .site-header nav>a:hover,.site-header nav>a:focus-visible { background:#17303c; color:#fff; outline:0; transform:translateY(-1px); }
        .site-header nav>a[aria-current="page"] { background:#213845; color:#fff; }
        .site-header nav>a[aria-current="page"]::after { opacity:1; transform:scaleX(1); }
        .site-header nav form { display:flex; align-items:center; margin:0; }
        .site-header nav .button { min-height:42px; padding:9px 15px; line-height:1; transition:border-color .18s ease,background-color .18s ease,color .18s ease,transform .18s ease; }
        .site-header nav .button:hover,.site-header nav .button:focus-visible { border-color:#6ed8ff; background:#e8f8fe; color:#087eae; outline:0; transform:translateY(-1px); }
        main { min-height: calc(100vh - 150px); }
        .hero { padding: 72px 0 54px; background: linear-gradient(135deg, #101820 0 54%, color-mix(in srgb, var(--accent) 82%, #101820) 54%); color: #fff; }
        .eyebrow { color: #86dcff; font-size: 13px; font-weight: 850; letter-spacing: .14em; text-transform: uppercase; }
        h1, h2, h3, p { margin-top: 0; }
        h1 { max-width: 760px; margin: 10px 0 16px; font-size: clamp(38px, 7vw, 76px); line-height: .98; letter-spacing: -.045em; }
        h2 { font-size: clamp(26px, 4vw, 40px); letter-spacing: -.025em; }
        h3 { margin-bottom: 8px; }
        .lead { max-width: 680px; color: #d9e8ee; font-size: 19px; }
        .section { padding: 48px 0 68px; }
        .section-head { display: flex; align-items: end; justify-content: space-between; gap: 24px; margin-bottom: 22px; }
        .grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 20px; }
        .card { overflow: hidden; border: 1px solid var(--line); border-radius: 12px; background: var(--panel); box-shadow: 0 16px 45px rgba(16, 24, 32, .07); }
        .card-media { aspect-ratio: 16/9; background: linear-gradient(135deg, var(--accent), #101820); object-fit: cover; width: 100%; }
        .card-body { padding: 20px; }
        .entity-logo { width: 180px; aspect-ratio: 3508 / 2480; border: 1px solid var(--line); border-radius: 7px; background: #fff; object-fit: contain; }
        .card a.stretched { text-decoration: none; }
        .meta, .muted { color: var(--muted); }
        .meta { font-size: 14px; font-weight: 650; }
        .button { display: inline-flex; min-height: 44px; align-items: center; justify-content: center; border: 0; border-radius: 7px; background: var(--accent); color: #fff; cursor: pointer; font: inherit; font-weight: 800; padding: 10px 17px; text-decoration: none; }
        .button.secondary { border: 1px solid var(--line); background: #fff; color: var(--ink); }
        .empty { border: 1px dashed #b6c8cf; border-radius: 12px; background: #fff; padding: 48px; text-align: center; }
        .unlock { width: min(520px, calc(100% - 40px)); margin: 70px auto; padding: 30px; }
        label { display: grid; gap: 7px; margin-bottom: 16px; color: var(--muted); font-size: 14px; font-weight: 750; }
        input, select, textarea { width: 100%; min-height: 46px; border: 1px solid var(--line); border-radius: 7px; background:#fff; padding: 10px 12px; color:var(--ink); font: inherit; }
        input:focus, select:focus, textarea:focus { border-color: var(--accent); box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 18%, transparent); outline: 0; }
        input[type="checkbox"], input[type="radio"] { width:18px; min-height:18px; height:18px; margin:2px 0 0; padding:0; flex:0 0 18px; accent-color:var(--accent); }
        .choice { display:flex; align-items:flex-start; gap:10px; margin-bottom:12px; color:var(--ink); font-weight:650; }
        .agreement-group { margin-top:22px; border-top:1px solid var(--line); padding-top:18px; }
        .error { margin-bottom: 18px; border-left: 4px solid #b42318; background: #fff1f2; color: #8e1b13; padding: 12px; }
        .player-grid { display: grid; grid-template-columns: minmax(0, 1.6fr) minmax(290px, .7fr); gap: 20px; }
        .player { background: #05090c; color: #fff; }
        .player video { width: 100%; aspect-ratio: 16/9; background: #000; }
        .player-info { padding: 18px; }
        .playlist { max-height: 620px; overflow-y: auto; }
        .playlist-item { display: grid; grid-template-columns: 72px 1fr; gap: 12px; width: 100%; border: 0; border-bottom: 1px solid var(--line); background: #fff; color: var(--ink); cursor: pointer; padding: 12px; text-align: left; }
        .playlist-item:hover, .playlist-item.active { background: #eaf8fd; }
        .playlist-thumb { display: grid; aspect-ratio: 16/9; place-items: center; border-radius: 5px; background: #101820; color: #fff; font-size: 20px; }
        .actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 16px; }
        .utility-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-top: 24px; }
        .utility { padding: 20px; }
        footer { padding: 28px 0; background: #101820; color: #a9bec8; font-size: 14px; }
        @media (max-width: 850px) { .grid, .utility-grid, .player-grid { grid-template-columns: 1fr; } .hero { padding-top: 48px; background: #101820; } .header-inner, .container { width: min(100% - 28px, 1180px); } .header-inner { align-items:flex-start; flex-direction:column; gap:10px; padding:14px 0; } .site-header nav { width:100%; overflow-x:auto; padding-bottom:2px; } .site-header nav>a { flex:0 0 auto; } }
        @media (max-width: 460px) { .logo span { display:none; } .header-brand { width:100%; } .header-back { margin-left:auto; } }
        @media (prefers-reduced-motion: reduce) { .header-back,.header-back svg,.site-header nav>a,.site-header nav>a::after,.site-header nav .button { transition:none; } .header-back:hover,.header-back:focus-visible,.site-header nav>a:hover,.site-header nav>a:focus-visible,.site-header nav .button:hover,.site-header nav .button:focus-visible { transform:none; } }
    </style>
    @stack('styles')
</head>
<body style="--concert-brand: {{ ($concert ?? null)?->brand_color ?? ($studio ?? null)?->brand_color ?? '#0aa0db' }}">
@include('layouts.partials.local-account-switcher')
<header class="site-header">
    <div class="header-inner">
        @php
            $headerParentUrl = trim($__env->yieldContent('headerParentUrl'));
            $headerParentLabel = trim($__env->yieldContent('headerParentLabel')) ?: 'Back';
            $helpIsActive = request()->routeIs('customer.media.help', 'customer.media.help.*');
        @endphp
        <div class="header-brand">
            <a class="logo" href="https://www.dancepro.com.au/"><img src="{{ asset('images/brand/dancepro-icon-blue-official.png') }}" alt=""><span>DancePro</span></a>
            @if($headerParentUrl)<a class="header-back" href="{{ $headerParentUrl }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/><path d="M9 12h10"/></svg><span>{{ $headerParentLabel }}</span></a>@endif
        </div>
        <nav aria-label="Main navigation">@if(auth()->user()?->canAccessCustomer())@if(auth()->user()->canAccessAdmin())<a href="{{ route('admin.dashboard') }}">Admin Hub</a>@endif<a href="{{ route('customer.media.index') }}" @if(request()->routeIs('customer.media.*') && !$helpIsActive) aria-current="page" @endif>My Media</a>@if(auth()->user()->canAccessStudioHub())<a href="{{ route('customer.studio-hub') }}" @if(request()->routeIs('customer.studio-hub', 'customer.studio-*')) aria-current="page" @endif>Studio Hub</a>@endif<a href="{{ route('customer.profile.edit') }}" @if(request()->routeIs('customer.profile.*')) aria-current="page" @endif>My Profile</a><a href="{{ route('customer.media.help') }}" @if($helpIsActive) aria-current="page" @endif>Help</a>@elseif(!auth()->check())<a href="{{ route('customer.login') }}" @if(request()->routeIs('customer.login*')) aria-current="page" @endif>Customer sign in</a><a href="{{ route('guest-galleries.index') }}" @if(request()->routeIs('guest-galleries.*')) aria-current="page" @endif>Media galleries</a>@endif @unless(auth()->user()?->canAccessCustomer())<a href="{{ route('studios.index') }}" @if(request()->routeIs('studios.*', 'concerts.*')) aria-current="page" @endif>Studios & concerts</a><a href="{{ route('admin.dashboard') }}">Staff</a>@endunless
            @if(auth()->user()?->canAccessCustomer())
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="button secondary" type="submit">Sign out</button></form>
            @endif
        </nav>
    </div>
</header>
<main>@yield('content')</main>
<footer><div class="container">DancePro · {{ $footerLabel ?? 'Protected concert media' }}</div></footer>
@stack('scripts')
</body>
</html>
