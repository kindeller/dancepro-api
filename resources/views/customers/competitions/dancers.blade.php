@extends('layouts.public', ['title' => 'My Dancers’ Routines · '.$title, 'footerLabel' => 'Competition media'])
@section('headerParentUrl', route(($competitionRoutes ?? ['show' => 'customer.competitions.show'])['show'], $event))
@section('content')
@php($competitionRoutes = $competitionRoutes ?? ['show' => 'customer.competitions.show'])
<section class="hero"><div class="container"><div class="eyebrow">{{ $title }}</div><h1>My Dancers’ Routines</h1><p class="lead">Routines that may match names saved in your profile.</p></div></section>
<section class="section"><div class="container">
    @include('customers.competitions.partials.tabs')
    @if($guestProfileRequired ?? false)
        <div class="empty"><h2>Create your profile to see your dancers’ routines</h2><p>Your favourites and cart will transfer when you register or sign in.</p><a class="button" href="{{ route('customer.register') }}">Create account</a> <a href="{{ route('customer.login') }}">Sign in</a></div>
    @else
    <p><a href="{{ route('customer.profile.edit', ['tab' => 'dancers']) }}">Edit dancers in My Profile →</a></p>
    @if($dancers->isEmpty())
        <div class="empty"><h2>Add a dancer to your profile</h2><p>Save a dancer’s name or programme name to find possible routines here.</p><a class="button" href="{{ route('customer.profile.edit', ['tab' => 'dancers']) }}">Add now</a></div>
    @elseif(empty($routines))
        <div class="empty"><h2>No ready routines matched yet</h2><p>Check back as sections become ready, or browse the whole event.</p></div>
    @else
        <div class="grid">
            @foreach($routines as $routine)
                <a class="card card-body" style="text-decoration:none" href="{{ route($competitionRoutes['show'], ['schedulingEvent' => $event, 'folder' => $routine['uuid']]) }}">
                    <h2>{{ $routine['label'] }}</h2>
                    <p class="meta">@if($routine['day']){{ $routine['day'] }} · @endif{{ $routine['section'] }}</p>
                    <p>Matched: {{ implode(', ', $routine['dancers']) }}</p>
                </a>
            @endforeach
        </div>
    @endif
    @endif
    <p style="margin-top:24px">These suggestions depend on programme names. You can always browse every ready section through <a href="{{ route($competitionRoutes['show'], $event) }}">Whole Event</a>.</p>
</div></section>
@endsection
