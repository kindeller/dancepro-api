<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'DancePro' }}</title>
    <style>
        :root { --brand: #0aa0db; --ink: #101820; --muted: #66737a; --paper: #f4f8fa; --panel: #fff; --line: #dbe5e9; --accent: var(--concert-brand, #0aa0db); color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--paper); color: var(--ink); font: 16px/1.55 Inter, ui-sans-serif, system-ui, -apple-system, sans-serif; }
        a { color: inherit; }
        img { display: block; max-width: 100%; }
        .site-header { background: var(--ink); color: #fff; }
        .header-inner, .container { width: min(1180px, calc(100% - 40px)); margin: auto; }
        .header-inner { display: flex; min-height: 74px; align-items: center; justify-content: space-between; gap: 24px; }
        .logo { display: flex; align-items: center; gap: 10px; color: #fff; font-weight: 850; letter-spacing: .08em; text-decoration: none; text-transform: uppercase; }
        .logo img { width: 38px; height: 38px; border-radius: 5px; }
        .site-header nav { display: flex; gap: 22px; }
        .site-header nav a { color: #dff6ff; font-size: 14px; font-weight: 700; text-decoration: none; }
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
        .card a.stretched { text-decoration: none; }
        .meta, .muted { color: var(--muted); }
        .meta { font-size: 14px; font-weight: 650; }
        .button { display: inline-flex; min-height: 44px; align-items: center; justify-content: center; border: 0; border-radius: 7px; background: var(--accent); color: #fff; cursor: pointer; font: inherit; font-weight: 800; padding: 10px 17px; text-decoration: none; }
        .button.secondary { border: 1px solid var(--line); background: #fff; color: var(--ink); }
        .empty { border: 1px dashed #b6c8cf; border-radius: 12px; background: #fff; padding: 48px; text-align: center; }
        .unlock { width: min(520px, calc(100% - 40px)); margin: 70px auto; padding: 30px; }
        label { display: grid; gap: 7px; margin-bottom: 16px; color: var(--muted); font-size: 14px; font-weight: 750; }
        input { width: 100%; min-height: 46px; border: 1px solid var(--line); border-radius: 7px; padding: 10px 12px; font: inherit; }
        input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 18%, transparent); outline: 0; }
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
        @media (max-width: 850px) { .grid, .utility-grid, .player-grid { grid-template-columns: 1fr; } .hero { padding-top: 48px; background: #101820; } .header-inner, .container { width: min(100% - 28px, 1180px); } }
    </style>
</head>
<body style="--concert-brand: {{ $concert->brand_color ?? $studio->brand_color ?? '#0aa0db' }}">
<header class="site-header">
    <div class="header-inner">
        <a class="logo" href="{{ route('studios.index') }}"><img src="{{ asset('storage/1024.png') }}" alt=""><span>DancePro</span></a>
        <nav aria-label="Main navigation"><a href="{{ route('studios.index') }}">Studios & concerts</a><a href="{{ route('login') }}">Staff</a></nav>
    </div>
</header>
<main>@yield('content')</main>
<footer><div class="container">DancePro · Protected concert media</div></footer>
@stack('scripts')
</body>
</html>
