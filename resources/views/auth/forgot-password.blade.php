@extends('layouts.auth', ['title' => 'Forgot password'])
@section('content')
<h1>Reset your password</h1><p>Enter your account email and we’ll send you a secure reset link.</p>
<form method="POST" action="{{ route('password.email') }}">@csrf<label>Email<input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus></label><button type="submit">Send reset link</button><a href="{{ route('login') }}">Back to sign in</a></form>
@endsection
