@extends('layouts.public', ['title' => 'Customer sign in', 'footerLabel' => 'DancePro Media'])
@section('content')
<section class="section"><div class="container" style="max-width:520px">
    <h1>Welcome back</h1><p>Sign in to access your DancePro photos and videos.</p>
    @if(session('status'))<p role="status">{{ session('status') }}</p>@endif
    <form method="POST" action="{{ route('customer.login.store') }}" class="card card-body" style="display:grid;gap:16px">
        @csrf
        <label>Email<input name="email" type="email" value="{{ old('email') }}" autocomplete="email" required></label>
        @error('email')<p role="alert">{{ $message }}</p>@enderror
        <label>Password<input name="password" type="password" autocomplete="current-password" required></label>
        @error('password')<p role="alert">{{ $message }}</p>@enderror
        <label><input type="checkbox" name="remember" value="1"> Keep me signed in</label>
        <button class="button">Sign in</button>
        <a href="{{ route('password.request') }}">Forgot password?</a>
    </form>
    <p>New to DancePro? <a href="{{ route('customer.register') }}">Create an account</a></p>
</div></section>
@endsection
