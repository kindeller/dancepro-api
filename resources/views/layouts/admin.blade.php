<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'DancePro Admin' }}</title>
    <style>
        :root {
            color-scheme: light;
            --ink: #141719;
            --muted: #66737a;
            --line: #d7e4ea;
            --paper: #f7fbfd;
            --panel: #ffffff;
            --brand: #0AA0DB;
            --brand-strong: #087fb0;
            --brand-dark: #101820;
            --warn: #a66214;
            --danger: #b42318;
            --ok: #147a52;
            --soft: #eaf8fd;
            --shadow: 0 18px 44px rgba(16, 24, 32, .07);
        }

        * {
            box-sizing: border-box;
        }

        [hidden] {
            display: none !important;
        }

        body {
            margin: 0;
            background: var(--paper);
            color: var(--ink);
            font-family: "Helvetica Neue", Arial, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
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
            background: var(--brand-dark);
            color: #f4fbff;
            padding: 28px 18px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 34px;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: .12em;
            text-transform: uppercase;
        }

        .brand-mark {
            display: grid;
            width: 34px;
            height: 34px;
            place-items: center;
            border-radius: 4px;
            background: var(--brand);
            color: #ffffff;
            letter-spacing: 0;
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
            border-radius: 4px;
            background: transparent;
            color: #f4fbff;
            cursor: pointer;
            font: inherit;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
            text-align: left;
        }

        .nav a[aria-current="page"],
        .nav a:hover,
        .logout-button:hover {
            background: rgba(10, 160, 219, .2);
            color: #ffffff;
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
            background: rgba(255, 255, 255, .92);
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
            letter-spacing: .01em;
        }

        h2 {
            font-size: 20px;
            font-weight: 800;
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
            border-radius: 4px;
            background: var(--panel);
            box-shadow: var(--shadow);
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
            color: var(--brand-dark);
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
            border-radius: 4px;
            background: #fff;
            color: var(--ink);
            font: inherit;
            padding: 9px 11px;
        }

        input:focus,
        select:focus,
        textarea:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(10, 160, 219, .16);
            outline: 0;
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
            border-radius: 4px;
            background: var(--brand);
            color: #fff;
            cursor: pointer;
            font: inherit;
            font-weight: 700;
            letter-spacing: .02em;
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

        .button.secondary:hover {
            border-color: var(--brand);
            background: var(--soft);
            color: var(--brand-strong);
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
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        tbody tr:hover {
            background: #f5fbfe;
        }

        .selectable-row {
            cursor: pointer;
        }

        .selectable-row.is-selected,
        .selectable-row.is-selected:hover {
            background: var(--soft);
        }

        input.selection-checkbox {
            width: auto;
            cursor: pointer;
        }

        .competition-objects-table td {
            vertical-align: middle;
        }

        .competition-objects-table .selection-cell {
            width: 76px;
            text-align: center;
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
            border-radius: 4px;
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
            border: 1px solid #9bdcf4;
            border-radius: 4px;
            background: var(--soft);
            padding: 12px 14px;
        }

        .error-list {
            margin-bottom: 16px;
            border: 1px solid #fecaca;
            border-radius: 4px;
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
            background: linear-gradient(135deg, #101820, #1b2a35 50%, #0AA0DB 50%, #0AA0DB);
        }

        .login-card {
            width: min(430px, 100%);
            border: 1px solid rgba(255, 255, 255, .55);
            border-radius: 4px;
            background: rgba(255, 255, 255, .96);
            padding: 26px;
            box-shadow: 0 24px 70px rgba(16, 24, 32, .24);
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
                <a href="{{ route('admin.competition.objects.index') }}" @if(request()->routeIs('admin.competition.objects.index')) aria-current="page" @endif>Competition Objects</a>
                <a href="{{ route('admin.download-links.index') }}" @if(request()->routeIs('admin.download-links.index', 'admin.download-links.show')) aria-current="page" @endif>Download Links</a>
                <a href="{{ route('admin.download-links.create') }}" @if(request()->routeIs('admin.download-links.create')) aria-current="page" @endif>Create Links</a>
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
