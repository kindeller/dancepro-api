@extends('layouts.public')

@section('content')
<div class="card unlock">
    @if($concert->cover_image_url)<img src="{{ $concert->cover_image_url }}" alt="{{ $concert->name }} cover" style="width:100%;max-height:280px;object-fit:contain;border-radius:8px;margin-bottom:16px">@endif
    <div class="eyebrow">Protected concert</div><h2>{{ $concert->name }}</h2><p class="muted">Enter the student name and password supplied by your studio.</p>
    @if($errors->any())<div class="error">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('concerts.unlock', $concert) }}">@csrf
        <label>Student name<input name="student_name" value="{{ old('student_name') }}" required autocomplete="name"></label>
        <label>Concert password<input type="password" name="password" required autocomplete="current-password"></label>
        <button class="button" type="submit">Unlock concert</button>
    </form>
</div>
@endsection
