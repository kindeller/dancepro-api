@extends('layouts.auth', ['title' => 'Choose a new password'])
@section('content')
<h1>{{ $onboarding ? 'Welcome to DancePro Crew' : 'Choose a new password' }}</h1><p>{{ $onboarding ? 'First, choose a secure password. You will then complete your crew profile.' : 'Enter and confirm your new password.' }}</p>
<form method="POST" action="{{ route('password.update') }}">@csrf<input type="hidden" name="token" value="{{ $token }}">@if($onboarding)<input type="hidden" name="onboarding" value="1">@endif<label>Email<input type="email" name="email" value="{{ old('email', $email) }}" autocomplete="email" required></label><label>New password<input type="password" name="password" autocomplete="new-password" required></label><label>Confirm new password<input type="password" name="password_confirmation" autocomplete="new-password" required></label><button type="submit">{{ $onboarding ? 'Create password and continue' : 'Reset password' }}</button></form>
@endsection
