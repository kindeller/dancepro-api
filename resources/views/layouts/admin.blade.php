<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'DancePro Admin' }}</title>
    <style>
        :root {
            color-scheme: light;
            --ink: #17211f;
            --muted: #65706d;
            --line: #dce3df;
            --paper: #fbfcfb;
            --panel: #ffffff;
            --brand: #0f766e;
            --brand-strong: #115e59;
            --warn: #b45309;
            --danger: #b91c1c;
            --ok: #15803d;
            --soft: #eef6f4;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: var(--paper);
            color: var(--ink);
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            font-size: 15px;
            line-height: 1.5;
        }

        a {
            color: var(--brand-strong);
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }

        .shell {
            display: grid;
            grid-template-columns: 248px minmax(0, 1fr);
            min-height: 100vh;
        }

        .sidebar {
            background: #102522;
            color: #e8f5f2;
            padding: 24px 18px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 30px;
            font-weight: 800;
            letter-spacing: .02em;
        }

        .brand-mark {
            display: grid;
            width: 34px;
            height: 34px;
            place-items: center;
            border-radius: 8px;
            background: #f5c542;
            color: #102522;
        }

        .nav {
            display: grid;
            gap: 6px;
        }

        .nav a,
        .logout-button {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            min-height: 42px;
            padding: 10px 12px;
            border: 0;
            border-radius: 8px;
            background: transparent;
            color: #e8f5f2;
            cursor: pointer;
            font: inherit;
            text-align: left;
        }

        .nav a[aria-current="page"],
        .nav a:hover,
        .logout-button:hover {
            background: rgba(255, 255, 255, .1);
            text-decoration: none;
        }

        .main {
            min-width: 0;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            min-height: 72px;
            padding: 18px 28px;
            border-bottom: 1px solid var(--line);
            background: var(--panel);
        }

        .content {
            width: min(1180px, 100%);
            padding: 28px;
        }

        h1,
        h2,
        h3 {
            margin: 0;
            line-height: 1.15;
        }

        h1 {
            font-size: 28px;
        }

        h2 {
            font-size: 20px;
        }

        h3 {
            font-size: 16px;
        }

        .muted {
            color: var(--muted);
        }

        .grid {
            display: grid;
            gap: 16px;
        }

        .stats {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .two-col {
            grid-template-columns: minmax(0, 1.1fr) minmax(320px, .9fr);
        }

        .card {
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--panel);
            overflow: hidden;
        }

        .card-pad {
            padding: 18px;
        }

        .metric {
            display: grid;
            gap: 6px;
            padding: 18px;
        }

        .metric strong {
            font-size: 28px;
            line-height: 1;
        }

        .toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: end;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 16px;
        }

        .filters {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        label {
            display: grid;
            gap: 6px;
            color: var(--muted);
            font-size: 13px;
            font-weight: 650;
        }

        input,
        select,
        textarea {
            width: 100%;
            min-height: 42px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            color: var(--ink);
            font: inherit;
            padding: 9px 11px;
        }

        textarea {
            min-height: 190px;
            resize: vertical;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 13px;
        }

        .button,
        button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 40px;
            border: 1px solid transparent;
            border-radius: 8px;
            background: var(--brand);
            color: #fff;
            cursor: pointer;
            font: inherit;
            font-weight: 700;
            padding: 9px 13px;
        }

        .button:hover,
        button:hover {
            background: var(--brand-strong);
            text-decoration: none;
        }

        .button.secondary {
            border-color: var(--line);
            background: #fff;
            color: var(--ink);
        }

        .button.danger,
        button.danger {
            background: var(--danger);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 12px 14px;
            border-bottom: 1px solid var(--line);
            text-align: left;
            vertical-align: top;
        }

        th {
            color: var(--muted);
            font-size: 12px;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .truncate {
            max-width: 420px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            min-height: 24px;
            padding: 3px 8px;
            border-radius: 999px;
            background: var(--soft);
            color: var(--brand-strong);
            font-size: 12px;
            font-weight: 800;
            text-transform: capitalize;
        }

        .badge.expired {
            background: #fff7ed;
            color: var(--warn);
        }

        .badge.revoked {
            background: #fef2f2;
            color: var(--danger);
        }

        .notice {
            margin-bottom: 16px;
            border: 1px solid #b7ddd4;
            border-radius: 8px;
            background: #effaf7;
            padding: 12px 14px;
        }

        .error-list {
            margin-bottom: 16px;
            border: 1px solid #fecaca;
            border-radius: 8px;
            background: #fff1f2;
            color: var(--danger);
            padding: 12px 14px;
        }

        .detail-list {
            display: grid;
            grid-template-columns: 180px minmax(0, 1fr);
            gap: 10px 14px;
        }

        .detail-list dt {
            color: var(--muted);
            font-weight: 700;
        }

        .detail-list dd {
            margin: 0;
            overflow-wrap: anywhere;
        }

        .login-page {
            display: grid;
            min-height: 100vh;
            place-items: center;
            padding: 24px;
            background: linear-gradient(135deg, #102522, #173f3a 48%, #f5c542 48%, #f5c542);
        }

        .login-card {
            width: min(430px, 100%);
            border: 1px solid rgba(255, 255, 255, .55);
            border-radius: 8px;
            background: rgba(255, 255, 255, .96);
            padding: 26px;
            box-shadow: 0 24px 70px rgba(16, 37, 34, .24);
        }

        .pagination {
            padding: 14px;
        }

        @media (max-width: 900px) {
            .shell {
                grid-template-columns: 1fr;
            }

            .sidebar {
                position: static;
            }

            .stats,
            .two-col {
                grid-template-columns: 1fr;
            }

            .topbar,
            .content {
                padding: 18px;
            }
        }
    </style>
</head>
<body>
    <div class="shell">
        <aside class="sidebar">
            <div class="brand">
                <span class="brand-mark">DP</span>
                <span>DancePro Admin</span>
            </div>

            <nav class="nav" aria-label="Admin navigation">
                <a href="{{ route('admin.dashboard') }}" @if(request()->routeIs('admin.dashboard')) aria-current="page" @endif>Dashboard</a>
                <a href="{{ route('admin.download-links.index') }}" @if(request()->routeIs('admin.download-links.*')) aria-current="page" @endif>Download Links</a>
                <a href="{{ route('admin.download-links.create') }}">Create Links</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="logout-button" type="submit">Sign out</button>
                </form>
            </nav>
        </aside>

        <main class="main">
            <header class="topbar">
                <div>
                    <h1>{{ $heading ?? 'Dashboard' }}</h1>
                    @isset($subheading)
                        <div class="muted">{{ $subheading }}</div>
                    @endisset
                </div>
                <div class="muted">{{ auth()->user()?->name }}</div>
            </header>

            <div class="content">
                @if (session('status'))
                    <div class="notice">{{ session('status') }}</div>
                @endif

                @if ($errors->any())
                    <div class="error-list">
                        <strong>Something needs attention.</strong>
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </div>
        </main>
    </div>
</body>
</html>
