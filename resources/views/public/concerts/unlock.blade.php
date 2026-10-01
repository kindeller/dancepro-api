@extends('layouts.public', ['title' => $concert->name])

@section('content')
<section class="hero"><div class="container">@include('customers._heading', ['headingImageUrl' => $concert->studio->logoUrl(), 'eyebrow' => 'Protected concert', 'heading' => $concert->name, 'description' => 'Use the access details supplied by your studio to view your concert media.'])</div></section>
<div class="card unlock">
    @if($concert->concert_code_group_id)<p class="muted">Have a concert video code? Sign in to your DancePro account and add it in My Media.</p><p><a class="button secondary" href="{{ auth()->check() ? route('customer.media.index') : route('login') }}">{{ auth()->check() ? 'Open My Media' : 'Sign in' }}</a></p>@endif
    @if($concert->access_password_hash)<p class="muted">You can also enter the student name and individual concert access code supplied by your studio.</p>
    @if($errors->any())<div class="error">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('concerts.unlock', $concert) }}">@csrf
        <label>Student name<input name="student_name" value="{{ old('student_name') }}" required autocomplete="name"></label>
        <label>Concert access code<input type="password" name="password" required autocomplete="current-password"></label>
        <button class="button" type="submit">Unlock concert</button>
    </form>
    @endif
</div>
@endsection
