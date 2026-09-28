@extends('layouts.admin', ['title' => 'Video titles', 'heading' => 'Video titles', 'subheading' => $event->name])

@section('content')
@include('admin.tracking._task-bar', ['bar' => $trackerBar])
@include('admin.programmes._title-cards')
@endsection
