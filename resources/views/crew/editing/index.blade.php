@extends('layouts.crew', ['title' => 'My editing'])
@section('content')
<h1>My editing</h1>
<p>Concert edits assigned to you. Open an event to update progress or mark editing complete.</p>
@forelse($events as $event)
<article class="card" style="padding:20px;margin-bottom:14px">
    <h2><a href="{{ route('crew.editing.show', $event) }}">{{ $event->name }}</a></h2>
    <p>{{ $event->studio?->name }} · {{ $event->event_date?->format('j M Y') }}</p>
</article>
@empty
<p>No concert edits are assigned to you.</p>
@endforelse
@endsection
