<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#101820">
    <title>Temporary maintenance | DancePro</title>
    <style>
        :root {
            color-scheme: light;
            --brand: #00a1dd;
            --brand-strong: #087fb0;
            --brand-dark: #101820;
            --ink: #141719;
            --muted: #66737a;
            --paper: #f7fbfd;
            --surface: #ffffff;
            --line: #d7e4ea;
            --soft: #eaf8fd;
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            color: var(--ink);
            background:
                radial-gradient(circle at 12% 18%, rgb(0 161 221 / 16%), transparent 24rem),
                linear-gradient(135deg, var(--paper) 0 52%, #edf7fa 52% 100%);
            font-family: "Helvetica Neue", Arial, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .page {
            display: grid;
            grid-template-rows: auto 1fr auto;
            min-height: 100vh;
            padding: clamp(22px, 4vw, 48px);
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            width: fit-content;
            color: var(--brand-dark);
            font-size: 0.82rem;
            font-weight: 800;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }

        .brand img {
            width: 42px;
            height: 42px;
            object-fit: contain;
        }

        main {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(260px, 0.75fr);
            align-items: center;
            gap: clamp(40px, 8vw, 110px);
            width: min(1120px, 100%);
            margin: auto;
            padding: 60px 0;
        }

        .eyebrow {
            margin: 0 0 16px;
            color: var(--brand-strong);
            font-size: 0.78rem;
            font-weight: 800;
            letter-spacing: 0.13em;
            text-transform: uppercase;
        }

        h1 {
            max-width: 700px;
            margin: 0;
            color: var(--brand-dark);
            font-size: clamp(2.75rem, 7vw, 5.6rem);
            line-height: 0.98;
            letter-spacing: -0.045em;
        }

        .intro {
            max-width: 620px;
            margin: 26px 0 0;
            color: var(--muted);
            font-size: clamp(1.05rem, 2vw, 1.28rem);
            line-height: 1.65;
        }

        .intro strong {
            color: var(--ink);
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 18px;
            margin-top: 34px;
        }

        .button {
            display: inline-flex;
            align-items: center;
            min-height: 46px;
            padding: 11px 18px;
            border: 1px solid var(--brand);
            border-radius: 4px;
            color: #ffffff;
            background: var(--brand);
            font-weight: 750;
            text-decoration: none;
        }

        .button:hover,
        .button:focus-visible {
            border-color: var(--brand-strong);
            background: var(--brand-strong);
        }

        .button:focus-visible {
            outline: 3px solid rgb(0 161 221 / 25%);
            outline-offset: 3px;
        }

        .retry-note {
            color: var(--muted);
            font-size: 0.92rem;
        }

        .visual {
            position: relative;
            display: grid;
            aspect-ratio: 1;
            place-items: center;
            isolation: isolate;
        }

        .visual::before,
        .visual::after {
            position: absolute;
            z-index: -1;
            border-radius: 50%;
            content: "";
        }

        .visual::before {
            width: 92%;
            height: 92%;
            border: 1px solid rgb(0 161 221 / 28%);
            box-shadow: 0 0 0 34px rgb(0 161 221 / 6%), 0 0 0 68px rgb(0 161 221 / 3%);
        }

        .visual::after {
            width: 66%;
            height: 66%;
            background: var(--surface);
        }

        .visual img {
            width: 45%;
        }

        footer {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--line);
            color: var(--muted);
            font-size: 0.8rem;
        }

        footer span:last-child {
            color: var(--brand-strong);
            font-weight: 700;
        }

        @media (max-width: 760px) {
            main {
                grid-template-columns: 1fr;
                padding: 50px 0;
            }

            .visual {
                width: min(300px, 72vw);
                margin: 10px auto 0;
                grid-row: 1;
            }

            footer {
                flex-direction: column;
            }
        }

        @media (prefers-reduced-motion: no-preference) {
            .visual::before {
                animation: pulse 3.5s ease-in-out infinite;
            }

            @keyframes pulse {
                50% { transform: scale(1.035); opacity: 0.72; }
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <header>
            <div class="brand">
                <img src="/storage/1024.png" alt="">
                <span>DancePro</span>
            </div>
        </header>

        <main>
            <section aria-labelledby="maintenance-heading">
                <p class="eyebrow">Planned maintenance · 503</p>
                <h1 id="maintenance-heading">We're developing something better.</h1>
                <p class="intro">
                    DancePro is temporarily unavailable while we carry out scheduled maintenance.
                    <strong>This maintenance window should last no longer than 30 minutes.</strong>
                    Please check back soon.
                </p>

                <div class="actions">
                    <a class="button" href="">Try again</a>
                    <span class="retry-note">Your information is safe.</span>
                </div>
            </section>

            <div class="visual" aria-hidden="true">
                <img src="/storage/1024.png" alt="">
            </div>
        </main>

        <footer>
            <span>Thank you for your patience.</span>
            <span>Temporary service interruption</span>
        </footer>
    </div>
</body>
</html>
