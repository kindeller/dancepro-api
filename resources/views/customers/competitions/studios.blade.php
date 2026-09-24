@extends('layouts.public', ['title' => 'My Studios’ Routines · '.$title, 'footerLabel' => 'Competition media'])
@section('content')
@php($competitionRoutes = $competitionRoutes ?? ['show' => 'customer.competitions.show'])
<section class="hero"><div class="container"><div class="eyebrow">{{ $title }}</div><h1>My Studios’ Routines</h1><p class="lead">Ready routines assigned to studios in your profile.</p></div></section>
<section class="section"><div class="container">
    @include('customers.competitions.partials.tabs')
    @if($guestProfileRequired ?? false)
        <div class="empty"><h2>Create your profile to see your studio’s routines</h2><p>Your favourites and cart will transfer when you register or sign in.</p><a class="button" href="{{ route('customer.register') }}">Create account</a> <a href="{{ route('customer.login') }}">Sign in</a></div>
    @else
    <p><a href="{{ route('customer.profile.edit') }}#studios">Edit studios in My Profile →</a></p>
    @if($studios->isEmpty())
        <div class="empty"><h2>Add a studio to your profile</h2><p>Save your studio to find its assigned routines here.</p><a class="button" href="{{ route('customer.profile.edit') }}#studios">Add now</a></div>
    @elseif(empty($routines))
        <div class="empty"><h2>No ready studio routines yet</h2><p>Check back as sections become ready, or browse the whole event.</p></div>
    @else
        <div class="grid">
            @foreach($routines as $routine)
                <a class="card card-body" style="text-decoration:none" href="{{ route($competitionRoutes['show'], ['schedulingEvent' => $event, 'folder' => $routine['uuid']]) }}">
                    <h2>{{ $routine['label'] }}</h2>
                    <p class="meta">@if($routine['day']){{ $routine['day'] }} · @endif{{ $routine['section'] }}</p>
                    <p>{{ $routine['studio'] }}</p>
                </a>
            @endforeach
        </div>
    @endif
    @endif
    <p style="margin-top:24px">You can always browse every ready section through <a href="{{ route($competitionRoutes['show'], $event) }}">Whole Event</a>.</p>
</div></section>
@endsection
