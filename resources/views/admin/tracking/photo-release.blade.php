@extends('layouts.admin', ['title' => $definition['numbered_label'], 'heading' => $event->studio?->name ?? $event->name, 'subheading' => $event->concertBookingItem?->title ?? $event->name])
@section('content')
<div class="photo-release-page">
@include('admin.tracking._task-bar', ['bar' => $trackerBar])
@if(session('status'))<p role="status">{{ session('status') }}</p>@endif
@if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
@include('admin.tracking._photo-release-workspace', $releaseWorkspace)
</div>
@endsection
@push('styles')<style>@media(max-width:900px){.photo-release-page .tracker-bar{position:static}}</style>@endpush
