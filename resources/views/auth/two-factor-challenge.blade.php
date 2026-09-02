@extends('layouts.auth', ['title' => 'Two-factor authentication'])
@section('content')
<h1>Authentication code</h1><p>Enter the six-digit code from your authenticator app.</p>
<form method="POST" action="{{ route('two-factor.verify') }}">@csrf<label>Six-digit code<input name="code" inputmode="numeric" pattern="[0-9]{6}" autocomplete="one-time-code" autofocus></label><button>Continue</button></form>
<hr style="margin:22px 0;border:0;border-top:1px solid var(--line)">
<p>Can’t access your authenticator? Use one of your recovery codes.</p>
<form method="POST" action="{{ route('two-factor.verify') }}">@csrf<label>Recovery code<input name="recovery_code" autocomplete="one-time-code"></label><button style="background:#68767d">Use recovery code</button></form>
@endsection
