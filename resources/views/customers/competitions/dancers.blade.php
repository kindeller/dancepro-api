@extends('layouts.public', ['title' => 'My Dancers’ Routines · '.$title, 'footerLabel' => 'Competition media'])
@section('headerParentUrl', route(($competitionRoutes ?? ['show' => 'customer.competitions.show'])['show'], $event))
@section('content')
@php($competitionRoutes = $competitionRoutes ?? ['show' => 'customer.competitions.show'])
<section class="hero"><div class="container">@include('customers._heading', ['headingImageUrl' => $event->logoUrl() ?? $event->crewLogoUrl(), 'eyebrow' => $title, 'heading' => 'My Dancers’ Routines', 'description' => 'Routines that may match names saved in your profile.'])</div></section>
<section class="section"><div class="container">
    @include('customers.competitions.partials.tabs')
    <x-gallery-breadcrumbs :items="[['label' => $title, 'url' => route($competitionRoutes['show'], $event)], ['label' => 'My Dancers’ Routines']]" />
    @if($guestProfileRequired ?? false)
        <div class="empty"><h2>Create your profile to see your dancers’ routines</h2><p>Your favourites and cart will transfer when you register or sign in.</p><a class="button" href="{{ route('customer.register') }}">Create account</a> <a href="{{ route('login') }}">Sign in</a></div>
    @else
    <p><a href="{{ route('customer.profile.edit', ['tab' => 'family']) }}">Edit dancers in My Family →</a></p>
    @if($dancers->isEmpty())
        <div class="empty"><h2>Add a dancer to your profile</h2><p>Save a dancer’s name or programme name to find possible routines here.</p><a class="button" href="{{ route('customer.profile.edit', ['tab' => 'family']) }}">Add now</a></div>
    @elseif(empty($routines))
        <div class="empty"><h2>No ready routines matched yet</h2><p>Check back as sections become ready, or browse the whole event.</p></div>
    @else
        @include('customers.competitions.partials.routine-cards')
    @endif
    @endif
    <p style="margin-top:24px">These suggestions depend on programme names. You can always browse every ready section through <a href="{{ route($competitionRoutes['show'], $event) }}">Whole Event</a>.</p>
</div></section>
@endsection
