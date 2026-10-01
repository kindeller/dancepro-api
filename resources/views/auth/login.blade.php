@extends('layouts.public', ['title' => 'DancePro sign in', 'footerLabel' => 'DancePro account'])

@section('content')
<section class="hero"><div class="container">@include('customers._heading', ['heading' => 'Welcome back', 'description' => 'Sign in to access your DancePro account.'])</div></section>
<section class="section">
    <div class="container" style="max-width:520px">
        @if(session('status'))<p role="status">{{ session('status') }}</p>@endif
        @if(session('publicity_join.name'))<p class="card card-body">{{ session('publicity_join.name') }} will be added to My Media after you sign in or create and verify your account. You won't need to enter a gallery code.</p>@endif
        <form method="POST" action="{{ route('login.store') }}" class="card card-body sign-in-form" style="display:grid;gap:16px">
            @csrf
            <label>Email<input name="email" type="email" value="{{ old('email', $familyInvitation['email'] ?? '') }}" autocomplete="email" required></label>
            @if(!empty($familyInvitation))<p class="meta">Use the email address that received your family invitation. If it is incorrect, ask the account manager to update it and send a new invitation.</p>@endif
            @error('email')<p role="alert">{{ $message }}</p>@enderror
            <label>Password<input name="password" type="password" autocomplete="current-password" required></label>
            @error('password')<p role="alert">{{ $message }}</p>@enderror
            <label class="choice"><input type="checkbox" name="remember" value="1"><span>Keep me signed in</span></label>
            <button class="button" type="submit">Sign in</button>
            <a href="{{ route('password.request') }}">Forgot password?</a>
        </form>
        <p>New to DancePro? <a href="{{ route('customer.register') }}">Create account</a></p>
    </div>
</section>
@endsection
