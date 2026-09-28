@extends('layouts.admin', ['title' => 'Programme preparation', 'heading' => 'Programme preparation', 'subheading' => $event->name])
@section('content')
@if(isset($trackerBar))
@include('admin.tracking._task-bar', ['bar' => $trackerBar])
<div class="toolbar"><a class="button secondary" href="{{ route('admin.scheduling-events.index') }}">← Back to event availability</a></div>
@endif
@include('admin.programmes._editor')
@endsection
