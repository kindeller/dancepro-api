@extends('layouts.public', ['title' => 'Create your account', 'footerLabel' => 'DancePro Media'])
@section('content')
<section class="section"><div class="container" style="max-width:520px">
    <h1>Create your account</h1><p>One DancePro account for competition media, concert photos and concert videos.</p>
    @if(session('publicity_join.name'))<p class="card card-body">{{ session('publicity_join.name') }} will be added to My Media after you sign in or create and verify your account. You won't need to enter a gallery code.</p>@endif
    <form method="POST" action="{{ route('customer.register.store') }}" class="card card-body" style="display:grid;gap:16px">
        @csrf
        <label>Name<input name="name" value="{{ old('name') }}" maxlength="255" autocomplete="name" required></label>
        @error('name')<p role="alert">{{ $message }}</p>@enderror
        <label>Email<input name="email" type="email" value="{{ old('email', $invitedEmail ?? '') }}" maxlength="255" autocomplete="email" required></label>
        @error('email')<p role="alert">{{ $message }}</p>@enderror
        <label>Password<input name="password" type="password" minlength="12" autocomplete="new-password" required></label>
        <p class="meta">Use at least 12 characters.</p>
        @error('password')<p role="alert">{{ $message }}</p>@enderror
        <label>Confirm password<input name="password_confirmation" type="password" autocomplete="new-password" required></label>
        <label class="choice"><input type="checkbox" name="policy_accepted" value="1" required><span>I have read and agree to the <a href="{{ config('customer.terms_url') }}" target="_blank" rel="noopener">Terms &amp; Conditions</a> and <a href="{{ config('customer.privacy_url') }}" target="_blank" rel="noopener">Privacy Policy</a>.</span></label>
        @error('policy_accepted')<p role="alert">{{ $message }}</p>@enderror
        <button class="button">Create account</button>
    </form>
    <p>Already have an account? <a href="{{ route('customer.login') }}">Sign in</a></p>
</div></section>
@endsection
