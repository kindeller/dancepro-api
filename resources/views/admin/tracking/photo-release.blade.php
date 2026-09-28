@extends('layouts.admin', ['title' => $definition['numbered_label'], 'heading' => $event->studio?->name ?? $event->name, 'subheading' => $event->name])
@section('content')
@include('admin.tracking._task-bar', ['bar' => $trackerBar])
@if(session('status'))<p role="status">{{ session('status') }}</p>@endif
@if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
<p>{{ $definition['description'] }}</p>
<a class="button secondary" href="{{ route('admin.photo-uploads.show', $event) }}">View photo uploads</a>
@include('admin.tracking._photo-release-workspace', $releaseWorkspace)
@endsection
@push('styles')<style>.release-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:20px}.release-parent-cover{max-width:240px;max-height:160px;object-fit:contain}.release-cover-grid{display:flex;flex-wrap:wrap;gap:8px}.release-cover-option img{width:100px;height:70px;object-fit:contain}@media(max-width:900px){.release-grid{grid-template-columns:1fr}}</style>@endpush
