<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'DancePro Admin' }}</title>
    @include('layouts.partials.foundation-styles')
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

        .mobile-nav-toggle {
            display: none;
        }

        .brand-mark {
            width: 34px;
            height: 34px;
            object-fit: contain;
            border-radius: 4px;
        }

        .nav {
            display: grid;
            gap: 6px;
        }

        .nav a,
        .nav summary,
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
        .nav summary:hover,
        .logout-button:hover {
            background: rgba(10, 160, 219, .2);
            color: #ffffff;
            text-decoration: none;
        }

        .nav-group {
            border-radius: 4px;
        }

        .nav-group summary {
            list-style: none;
        }

        .nav-group summary::-webkit-details-marker {
            display: none;
        }

        .nav-group summary::after {
            margin-left: auto;
            content: "+";
            font-size: 18px;
            line-height: 1;
        }

        .nav-group[open] summary::after {
            content: "−";
        }

        .nav-group.active > summary {
            background: rgba(10, 160, 219, .2);
            color: #ffffff;
        }

        .nav-submenu {
            display: grid;
            gap: 3px;
            margin: 3px 0 8px 10px;
            padding-left: 10px;
            border-left: 1px solid rgba(244, 251, 255, .2);
        }

        .nav-submenu a {
            min-height: 36px;
            padding: 8px 10px;
            font-size: 11px;
        }

        .nav-notification {
            display: grid;
            min-width: 18px;
            height: 18px;
            padding: 0 5px;
            place-items: center;
            border-radius: 99px;
            background: #e84848;
            color: #fff;
            font-size: 10px;
            line-height: 1;
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
            width: 100%;
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
            overflow-x: auto;
            overflow-y: hidden;
        }

        .card-pad {
            padding: 18px;
        }

        .entity-logo {
            width: 180px;
            aspect-ratio: 3508 / 2480;
            border: 1px solid var(--line);
            border-radius: 4px;
            background: #fff;
            object-fit: contain;
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

        .contact-tabs {
            display: flex;
            gap: 5px;
            margin-bottom: 20px;
            padding: 4px;
            border: 1px solid var(--line);
            border-radius: 4px;
            background: #fff;
        }

        .contact-tabs a {
            flex: 1;
            padding: 9px 12px;
            border-radius: 3px;
            color: var(--muted);
            font-weight: 800;
            text-align: center;
        }

        .contact-tabs a.active {
            background: var(--brand-dark);
            color: #fff;
            text-decoration: none;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 8px;
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
            text-align: center;
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
            height: 18px;
            min-height: 0;
            padding: 0;
            cursor: pointer;
        }

        .competition-objects-table th,
        .competition-objects-table td {
            padding: 7px 10px;
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

        .badge.attention {
            background: #fff2cc;
            color: var(--warn);
        }

        .badge.success {
            background: #dcfce7;
            color: var(--ok);
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

        .invoice-overview {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }

        .invoice-overview > div {
            display: flex;
            min-height: 78px;
            flex-direction: column;
            justify-content: center;
            gap: 5px;
            padding: 14px 16px;
            border-radius: 4px;
            background: #f2f7f9;
        }

        .invoice-overview span {
            color: var(--muted);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .invoice-table-wrap { overflow-x: auto; border: 1px solid var(--line); border-radius: 4px; }
        .invoice-table { min-width: 760px; }
        .invoice-table th:nth-last-child(-n+3), .invoice-table td:nth-last-child(-n+3) { text-align: right; }
        .invoice-table tfoot th { border-bottom: 0; background: var(--soft); color: var(--ink); }

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

        .admin-pagination {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .admin-pagination-links {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 5px;
        }

        .admin-page-link {
            display: inline-flex;
            min-width: 36px;
            min-height: 36px;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--line);
            border-radius: 4px;
            background: #fff;
            padding: 6px 10px;
            font-weight: 700;
        }

        .admin-page-link.current { border-color: var(--brand); background: var(--brand); color: #fff; }
        .admin-page-link.disabled { color: var(--muted); opacity: .55; }

        .loading-indicator {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: var(--brand-strong);
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .loading-spinner {
            width: 30px;
            height: 30px;
            border: 3px solid var(--line);
            border-top-color: var(--brand);
            border-radius: 50%;
            animation: competition-loading-spin .7s linear infinite;
        }

        @keyframes competition-loading-spin {
            to {
                transform: rotate(360deg);
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .loading-spinner {
                animation: none;
            }
        }

        @media (max-width: 900px) {
            .shell {
                grid-template-columns: 1fr;
            }

            .sidebar {
                position: sticky;
                z-index: 20;
                top: 0;
                display: flex;
                align-items: center;
                justify-content: space-between;
                min-height: 64px;
                width: 100vw;
                min-width: 0;
                padding: 12px 18px;
                box-shadow: 0 8px 24px rgba(16, 24, 32, .18);
            }

            .brand {
                min-width: 0;
                margin: 0;
            }

            .brand span {
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .mobile-nav-toggle {
                display: inline-flex;
                flex: none;
                min-width: 44px;
                min-height: 44px;
                border: 1px solid rgba(255, 255, 255, .32);
                background: transparent;
                padding: 8px 12px;
            }

            .mobile-nav-toggle:hover {
                background: rgba(10, 160, 219, .2);
            }

            .nav {
                position: absolute;
                top: 100%;
                right: 0;
                left: 0;
                display: none;
                max-height: calc(100vh - 64px);
                overflow-y: auto;
                overscroll-behavior: contain;
                padding: 10px 18px 18px;
                background: var(--brand-dark);
                box-shadow: 0 18px 30px rgba(16, 24, 32, .22);
            }

            .sidebar[data-mobile-open="true"] .nav {
                display: grid;
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
    @stack('styles')
</head>
<body>
    <div class="shell">
        <aside class="sidebar">
            <div class="brand">
                <img class="brand-mark" src="{{ asset('storage/1024.png') }}" alt="">
                <span>DancePro Admin</span>
            </div>

            <button class="mobile-nav-toggle" type="button" aria-expanded="false" aria-controls="admin-navigation">
                <span class="mobile-nav-label">Menu</span>
            </button>

            <nav class="nav" id="admin-navigation" aria-label="Admin navigation">
                @if(auth()->user()?->crewProfile)
                    <a href="{{ route('crew.availability.index') }}">My Hub</a>
                @endif
                @php($hubManagementActive = request()->routeIs('admin.hub.*', 'admin.exceptions.*', 'admin.studios.*', 'admin.competition-contacts.*', 'admin.crew.*', 'admin.crew-roles.*', 'admin.crew-contracts.*', 'admin.crew-management.*', 'admin.scheduling-events.*', 'admin.event-management.*', 'admin.event-types.*', 'admin.venues.*', 'admin.operations.*', 'admin.payments.*', 'admin.timesheets.*', 'admin.concert-bookings.*', 'admin.concert-booking-events.*'))
                <details class="nav-group {{ $hubManagementActive ? 'active' : '' }}" @if($hubManagementActive) open @endif>
                    <summary>Hub Management</summary>
                    <div class="nav-submenu">
                        @can('manageScheduling')
                            <a href="{{ route('admin.hub.dashboard') }}" @if(request()->routeIs('admin.hub.*')) aria-current="page" @endif>Dashboard</a>
                        @endcan
                        @can('manageStudios')
                            <a href="{{ route('admin.studios.index') }}" @if(request()->routeIs('admin.studios.*', 'admin.competition-contacts.*')) aria-current="page" @endif>Contacts</a>
                        @endcan
                        @can('manageCrew')
                            <a href="{{ route('admin.crew.index') }}" @if(request()->routeIs('admin.crew.*', 'admin.crew-roles.*', 'admin.crew-contracts.*', 'admin.crew-management.*')) aria-current="page" @endif>Crew Management</a>
                        @endcan
                        @can('manageScheduling')
                            <a href="{{ route('admin.exceptions.index') }}" @if(request()->routeIs('admin.exceptions.*')) aria-current="page" @endif>@if($adminExceptionCount)<span class="nav-notification">{{ $adminExceptionCount }}</span>@endif Exceptions</a>
                            <a href="{{ route('admin.concert-bookings.index') }}" @if(request()->routeIs('admin.scheduling-events.*', 'admin.event-management.*', 'admin.event-types.*', 'admin.concert-bookings.*', 'admin.concert-booking-events.*')) aria-current="page" @endif>Event Management</a>
                            <a href="{{ route('admin.venues.index') }}" @if(request()->routeIs('admin.venues.*')) aria-current="page" @endif>Venue Management</a>
                            <a href="{{ route('admin.timesheets.index') }}" @if(request()->routeIs('admin.timesheets.*', 'admin.payments.*')) aria-current="page" @endif>Crew Payments</a>
                        @endcan
                    </div>
                </details>
                @php($mediaActive = request()->routeIs('admin.dashboard', 'admin.concerts.*', 'admin.competition.objects.*', 'admin.download-links.*'))
                <details class="nav-group {{ $mediaActive ? 'active' : '' }}" @if($mediaActive) open @endif>
                    <summary>Media</summary>
                    <div class="nav-submenu">
                        <a href="{{ route('admin.dashboard') }}" @if(request()->routeIs('admin.dashboard')) aria-current="page" @endif>Media Dashboard</a>
                        @can('manageConcerts')
                            <a href="{{ route('admin.concerts.index') }}" @if(request()->routeIs('admin.concerts.*')) aria-current="page" @endif>Concert Media</a>
                        @endcan
                        <a href="{{ route('admin.competition.objects.index') }}" @if(request()->routeIs('admin.competition.objects.*')) aria-current="page" @endif>Competition Media</a>
                        <a href="{{ route('admin.download-links.index') }}" @if(request()->routeIs('admin.download-links.index', 'admin.download-links.show')) aria-current="page" @endif>Download Links</a>
                        <a href="{{ route('admin.download-links.create') }}" @if(request()->routeIs('admin.download-links.create')) aria-current="page" @endif>Create Link</a>
                    </div>
                </details>
                @if(config('security.two_factor.enabled'))<a href="{{ route('account.security') }}">Account security</a>@endif
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
    <script nonce="{{ request()->attributes->get('csp_nonce') }}">
        const copyTextWithFallback = async text => {
            if (navigator.clipboard?.writeText) {
                try {
                    await navigator.clipboard.writeText(text);
                    return;
                } catch (_) {
                    // Clipboard access can be blocked outside a secure context.
                }
            }

            const field = document.createElement('textarea');
            field.value = text;
            field.setAttribute('readonly', '');
            field.style.cssText = 'position:fixed;left:-9999px;opacity:0';
            document.body.appendChild(field);
            field.select();
            const copied = document.execCommand('copy');
            field.remove();

            if (! copied) throw new Error('Copy command was rejected');
        };

        document.addEventListener('click', async event => {
            const button = event.target.closest('[data-copy-emails]');
            if (! button) return;

            event.stopPropagation();
            const status = document.getElementById('copy-status');
            try {
                await copyTextWithFallback(button.dataset.copyEmails);
                if (status) status.textContent = 'Email addresses copied';
            } catch (_) {
                if (status) status.textContent = 'Could not copy email addresses';
            }
            if (status) {
                status.classList.add('visible');
                window.setTimeout(() => status.classList.remove('visible'), 1800);
            }
        });

        (() => {
            const sidebar = document.querySelector('.sidebar');
            const toggle = document.querySelector('.mobile-nav-toggle');
            const navigation = document.getElementById('admin-navigation');

            if (! sidebar || ! toggle || ! navigation) return;

            const close = () => {
                sidebar.dataset.mobileOpen = 'false';
                toggle.setAttribute('aria-expanded', 'false');
                toggle.querySelector('.mobile-nav-label').textContent = 'Menu';
            };

            toggle.addEventListener('click', () => {
                const isOpen = sidebar.dataset.mobileOpen === 'true';
                sidebar.dataset.mobileOpen = isOpen ? 'false' : 'true';
                toggle.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
                toggle.querySelector('.mobile-nav-label').textContent = isOpen ? 'Menu' : 'Close';
            });

            navigation.addEventListener('click', event => {
                if (event.target.closest('a')) close();
            });

            document.addEventListener('keydown', event => {
                if (event.key === 'Escape' && sidebar.dataset.mobileOpen === 'true') {
                    close();
                    toggle.focus();
                }
            });

            window.matchMedia('(min-width: 901px)').addEventListener('change', event => {
                if (event.matches) close();
            });
        })();
    </script>
    @stack('scripts')
</body>
</html>
