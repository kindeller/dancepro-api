<style>
    :root {
        color-scheme: light;
        --ink: #171313;
        --muted: #6f6765;
        --line: #ded7d4;
        --brand: #f26f63;
        --brand-strong: #d94f45;
        --danger: #b42318;
    }

    * {
        box-sizing: border-box;
    }

    body {
        margin: 0;
        font-family: "Helvetica Neue", Arial, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    }

    .login-page {
        display: grid;
        min-height: 100vh;
        place-items: center;
        padding: 24px;
        background: linear-gradient(135deg, #1d1717, #332827 50%, #f26f63 50%, #f26f63);
    }

    .login-card {
        width: min(430px, 100%);
        border: 1px solid rgba(255, 255, 255, .55);
        border-radius: 4px;
        background: rgba(255, 255, 255, .96);
        padding: 26px;
        box-shadow: 0 24px 70px rgba(29, 23, 23, .24);
    }

    h1 {
        margin: 0 0 8px;
        color: var(--ink);
        font-size: 28px;
        line-height: 1.15;
        letter-spacing: .01em;
    }

    p {
        margin: 0 0 22px;
        color: var(--muted);
    }

    form,
    label {
        display: grid;
        gap: 12px;
    }

    label {
        gap: 6px;
        color: var(--muted);
        font-size: 13px;
        font-weight: 700;
    }

    input {
        min-height: 44px;
        border: 1px solid var(--line);
        border-radius: 4px;
        padding: 9px 11px;
        color: var(--ink);
        font: inherit;
    }

    input:focus {
        border-color: var(--brand);
        box-shadow: 0 0 0 3px rgba(242, 111, 99, .16);
        outline: 0;
    }

    button {
        min-height: 44px;
        border: 0;
        border-radius: 4px;
        background: var(--brand);
        color: #fff;
        cursor: pointer;
        font: inherit;
        font-weight: 800;
        letter-spacing: .02em;
    }

    button:hover {
        background: var(--brand-strong);
    }

    .error-list {
        margin-bottom: 16px;
        border: 1px solid #fecaca;
        border-radius: 4px;
        background: #fff1f2;
        color: var(--danger);
        padding: 12px 14px;
    }
</style>

<section class="login-card">
    <h1>DancePro Admin</h1>
    <p>Sign in to inspect download links, access history, and platform data.</p>

    @if ($errors->any())
        <div class="error-list">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('login.store') }}">
        @csrf

        <label>
            Email
            <input name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
        </label>

        <label>
            Password
            <input name="password" type="password" autocomplete="current-password" required>
        </label>

        <label style="display:flex;align-items:center;gap:8px;">
            <input name="remember" type="checkbox" value="1" style="width:auto;min-height:auto;">
            Remember this browser
        </label>

        <button type="submit">Sign in</button>
    </form>
</section>
