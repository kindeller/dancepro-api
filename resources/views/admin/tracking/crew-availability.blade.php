@extends('layouts.admin', ['title' => $definition['numbered_label'], 'heading' => $event->studio?->name ?? $event->name, 'subheading' => $definition['numbered_label']])
@section('content')
@include('admin.tracking._task-bar', ['bar' => $trackerBar])
<div class="toolbar" style="margin:18px 0"><a class="button secondary" href="{{ route('admin.scheduling-events.index') }}">Full event availability</a></div>
<section class="card card-pad" aria-label="Studio event availability" style="min-width:0">
<h2>{{ $event->studio?->name ?? $event->name }} · Event availability</h2>
<p class="muted">This is the live availability sheet for this studio’s events. Changes save to the same roster used in full event availability.</p>
@include('admin.scheduling-events._availability', ['isolated' => true])
</section>
@endsection
