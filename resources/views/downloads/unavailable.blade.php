<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $heading }} | DancePro</title>
    <style>
        :root {
            color-scheme: light;
            --ink: #141719;
            --muted: #66737a;
            --paper: #f7fbfd;
            --surface: #ffffff;
            --brand: #00A1DD;
            --brand-strong: #087fb0;
            --brand-dark: #101820;
            --border: #d7e4ea;
            --soft: #eaf8fd;
        }

        * {
            box-sizing: border-box;
        }

        body {
            display: grid;
            min-height: 100vh;
            margin: 0;
            padding: clamp(20px, 5vw, 48px);
            place-items: center;
            color: var(--ink);
            background: var(--paper);
            font-family: "Helvetica Neue", Arial, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        main {
            width: min(100%, 560px);
            overflow: hidden;
            border: 1px solid var(--border);
            border-radius: 4px;
            background: var(--surface);
            box-shadow: 0 18px 44px rgb(16 24 32 / 7%);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 0;
            padding: 18px clamp(24px, 6vw, 44px);
            color: #f4fbff;
            background: var(--brand-dark);
            font-size: 0.8rem;
            font-weight: 800;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .brand-mark {
            display: grid;
            width: 36px;
            height: 36px;
            flex: 0 0 auto;
            place-items: center;
            border-radius: 4px;
            color: #ffffff;
            background: var(--brand);
            letter-spacing: 0;
        }

        .content {
            padding: clamp(30px, 7vw, 52px) clamp(24px, 6vw, 44px);
            border-top: 4px solid var(--brand);
        }

        h1 {
            margin: 0 0 14px;
            color: var(--brand-dark);
            font-size: clamp(1.9rem, 7vw, 2.7rem);
            line-height: 1.08;
            letter-spacing: -0.025em;
        }

        p {
            margin: 0;
            color: var(--muted);
            font-size: 1.05rem;
            line-height: 1.6;
        }

        .filename {
            margin: 24px 0;
            padding: 14px 16px;
            overflow-wrap: anywhere;
            border: 1px solid var(--border);
            border-left: 4px solid var(--brand);
            border-radius: 4px;
            color: var(--ink);
            background: var(--soft);
            font-weight: 600;
        }

        .help {
            margin-top: 24px;
            font-size: 0.95rem;
        }

        .expiry {
            margin-top: 16px;
            color: var(--brand-strong);
            font-size: 0.95rem;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <main>
        <p class="brand"><span class="brand-mark">DP</span> DancePro</p>

        <div class="content">
            <h1>{{ $heading }}</h1>
            <p>{{ $message }}</p>

            @if ($filename)
                <p class="filename">{{ $filename }}</p>
            @endif

            @if ($expiresAt)
                <p class="expiry">Expired on <time datetime="{{ $expiresAt }}">{{ $expiresAt }}</time></p>
            @endif

            <p class="help">If you still need this file, please contact the person who provided the download link.</p>
        </div>
    </main>

    @if ($expiresAt)
        <script>
            (() => {
                const expiry = document.querySelector('time[datetime]');

                if (!expiry) {
                    return;
                }

                const date = new Date(expiry.dateTime);

                if (!Number.isNaN(date.getTime())) {
                    expiry.textContent = new Intl.DateTimeFormat(undefined, {
                        dateStyle: 'long',
                        timeStyle: 'short',
                    }).format(date);
                }
            })();
        </script>
    @endif
</body>
</html>
