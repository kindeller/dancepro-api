@if(app()->environment(['local', 'testing']) && auth()->check() && $localSwitchAccounts->flatten(1)->isNotEmpty())
    <div class="local-account-switcher" role="region" aria-label="Local test account switcher">
        <form method="POST" action="{{ route('local.account.switch') }}">
            @csrf
            <strong>Test account</strong>
            <span class="local-account-current">{{ auth()->user()->name }}</span>
            <label for="local-account-user">Switch to</label>
            <select id="local-account-user" name="user_id" required>
                @foreach(['customer' => 'Customer', 'studio' => 'Studio', 'staff' => 'Staff', 'admin' => 'Admin'] as $role => $label)
                    @if($localSwitchAccounts->get($role)?->isNotEmpty())
                        <optgroup label="{{ $label }}">
                            @foreach($localSwitchAccounts->get($role) as $account)
                                <option value="{{ $account['id'] }}" @selected(auth()->id() === $account['id'])>{{ $account['name'] }} · {{ $account['email'] }}</option>
                            @endforeach
                        </optgroup>
                    @endif
                @endforeach
            </select>
            <button type="submit">Switch</button>
        </form>
    </div>
    <style nonce="{{ request()->attributes->get('csp_nonce') }}">
        .local-account-switcher { position:relative; z-index:10000; width:100%; padding:8px 18px; border-bottom:1px solid #29404d; background:#0b151c; color:#fff; font:600 13px/1.3 Arial,sans-serif; }
        .local-account-switcher form { display:flex; align-items:center; justify-content:center; gap:10px; max-width:1500px; margin:0 auto; }
        .local-account-switcher strong { color:#65d1f4; text-transform:uppercase; letter-spacing:.08em; }
        .local-account-switcher .local-account-current { max-width:230px; overflow:hidden; color:#c6d6dd; text-overflow:ellipsis; white-space:nowrap; }
        .local-account-switcher label { position:absolute; width:1px; height:1px; overflow:hidden; clip:rect(0 0 0 0); }
        .local-account-switcher select { width:min(430px,45vw); min-height:34px; margin:0; padding:5px 34px 5px 10px; border:1px solid #49616e; border-radius:5px; background:#fff; color:#101820; font:600 13px Arial,sans-serif; }
        .local-account-switcher button { min-height:34px; margin:0; padding:6px 15px; border:0; border-radius:5px; background:#0aa0db; color:#fff; font:800 13px Arial,sans-serif; cursor:pointer; }
        @media (max-width:700px) { .local-account-switcher form { flex-wrap:wrap; gap:6px; } .local-account-switcher strong { width:100%; text-align:center; } .local-account-switcher select { width:min(100%,430px); } .local-account-switcher .local-account-current { display:none; } }
    </style>
@endif

@if(app(\App\Features\Tracking\Services\ConcertEmailRehearsal::class)->capturesLocally())
    <div style="padding:8px 16px;background:#e1f4fb;color:#143544;text-align:center;font-size:14px" role="status">Local email rehearsal · Emails stay in the test inbox. <a href="http://localhost:8025" target="_blank" rel="noopener">Open test inbox</a></div>
@endif
