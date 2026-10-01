@extends('layouts.public', ['title' => 'Improvisation · '.$title, 'footerLabel' => 'Competition media'])
@section('headerParentUrl', route(($competitionRoutes ?? ['show' => 'customer.competitions.show'])['show'], $event))

@section('content')
@php($competitionRoutes = $competitionRoutes ?? ['show' => 'customer.competitions.show'])
<section class="hero"><div class="container">@include('customers._heading', ['headingImageUrl' => $event->logoUrl() ?? $event->crewLogoUrl(), 'eyebrow' => $title, 'heading' => 'Improvisation', 'description' => 'Browse ready improvisation routines, even when a competitor or studio name is unavailable.'])</div></section>
<section class="section"><div class="container">
    @include('customers.competitions.partials.tabs')
    <x-gallery-breadcrumbs :items="[['label' => $title, 'url' => route($competitionRoutes['show'], $event)], ['label' => 'Improvisation']]" />
    @if(empty($routines))
        <div class="empty"><h2>No ready improvisation routines yet</h2><p>Check back as sections become ready, or browse the whole event.</p></div>
    @else
        @include('customers.competitions.partials.routine-cards')
    @endif
    <p style="margin-top:24px">You can always browse every ready section through <a href="{{ route($competitionRoutes['show'], $event) }}">Whole Event</a>.</p>
</div></section>
@endsection
