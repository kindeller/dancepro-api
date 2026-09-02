@extends('layouts.auth', ['title' => 'Account security'])
@section('content')
<h1>Account security</h1>
@if($user->two_factor_confirmed_at)
    <p><strong>Two-factor authentication is active.</strong> You will use your authenticator app each time you sign in.</p>
    @if($recoveryCodes)<div class="message"><strong>Save these recovery codes now.</strong><p>Each code works once. Store them somewhere private.</p>@foreach($recoveryCodes as $code)<div><code>{{ $code }}</code></div>@endforeach</div>@endif
    <form method="POST" action="{{ route('two-factor.recovery-codes') }}">@csrf<label>Current password<input type="password" name="current_password" required></label><button>Generate new recovery codes</button></form>
    <hr style="margin:22px 0;border:0;border-top:1px solid var(--line)">
    <form method="POST" action="{{ route('two-factor.disable') }}">@csrf @method('DELETE')<label>Current password<input type="password" name="current_password" required></label><button style="background:#68767d">Disable two-factor authentication</button></form>
@elseif($user->two_factor_secret)
    <p>Scan this QR code with Apple Passwords, 1Password, Google Authenticator or another authenticator app.</p>
    <img src="{{ $qrCode }}" alt="Two-factor authenticator QR code" style="display:block;width:240px;max-width:100%;margin:14px auto">
    <p>Manual setup key: <code style="word-break:break-all">{{ $user->two_factor_secret }}</code></p>
    <form method="POST" action="{{ route('two-factor.confirm') }}">@csrf<label>Six-digit code<input name="code" inputmode="numeric" pattern="[0-9]{6}" autocomplete="one-time-code" required></label><button>Confirm and enable</button></form>
@else
    <p>Add an authenticator app to protect the personal and financial information in your account.</p>
    <form method="POST" action="{{ route('two-factor.begin') }}">@csrf<label>Confirm your password<input type="password" name="current_password" autocomplete="current-password" required></label><button>Set up two-factor authentication</button></form>
@endif
<p style="margin-top:18px"><a href="{{ $user->crewProfile ? route('crew.profile.edit') : route('admin.dashboard') }}">Back to DancePro</a></p>
@endsection
