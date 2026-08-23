@extends('layouts.public')

@section('content')
<div class="card unlock">
    <div class="eyebrow">Protected concert</div><h2>{{ $concert->name }}</h2><p class="muted">Enter the student name and password supplied by your studio.</p>
    @if($errors->any())<div class="error">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('concerts.unlock', $concert) }}">@csrf
        <label>Student name<input name="student_name" value="{{ old('student_name') }}" required autocomplete="name"></label>
        <label>Concert password<input type="password" name="password" required autocomplete="current-password"></label>
        <button class="button" type="submit">Unlock concert</button>
    </form>
</div>
@endsection
