@extends('layouts.public', ['title' => 'Improvisation · '.$title, 'footerLabel' => 'Competition media'])

@section('content')
@php($competitionRoutes = $competitionRoutes ?? ['show' => 'customer.competitions.show'])
<section class="hero"><div class="container"><div class="eyebrow">{{ $title }}</div><h1>Improvisation</h1><p class="lead">Browse ready improvisation routines, even when a competitor or studio name is unavailable.</p></div></section>
<section class="section"><div class="container">
    @include('customers.competitions.partials.tabs')
    @if(empty($routines))
        <div class="empty"><h2>No ready improvisation routines yet</h2><p>Check back as sections become ready, or browse the whole event.</p></div>
    @else
        <div class="grid">
            @foreach($routines as $routine)
                <a class="card card-body" style="text-decoration:none" href="{{ route($competitionRoutes['show'], ['schedulingEvent' => $event, 'folder' => $routine['uuid']]) }}">
                    <h2>{{ $routine['label'] }}</h2>
                    <p class="meta">@if($routine['day']){{ $routine['day'] }} · @endif{{ $routine['section'] }}</p>
                </a>
            @endforeach
        </div>
    @endif
    <p style="margin-top:24px">You can always browse every ready section through <a href="{{ route($competitionRoutes['show'], $event) }}">Whole Event</a>.</p>
</div></section>
@endsection
