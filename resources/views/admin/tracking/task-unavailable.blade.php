@extends('layouts.admin', ['title' => $definition['numbered_label'], 'heading' => $event->studio?->name ?? $event->name, 'subheading' => $event->name])
@section('content')
@include('admin.tracking._task-bar', ['bar' => $trackerBar])
@include('admin.tracking._action-blockers', ['blockers' => $blockers, 'blockerHeading' => 'This task cannot be completed yet'])
<a class="button secondary" href="{{ route('admin.concert-bookings.index') }}">Event bookings</a>
@endsection
